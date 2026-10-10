<?php

namespace Tests\Feature;

use App\Models\DrafKontenSuratKeluar;
use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Support\CariArsip;
use App\Support\FilterArsip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Perilaku kotak "cari" di daftar arsip — hasil Track 1a+1b (9 Okt 2026).
 *
 * L-15 (pencarian ikut menjangkau isi) tetap acuan, dan keputusan S11 SENGAJA
 * tidak disentuh: semuanya masih LIKE, tanpa index FULLTEXT. Portabilitas
 * MariaDB-nya dijaga kelas terpisah (CariArsipMariaDbTest), karena suite ini
 * berjalan di SQLite.
 *
 * Yang diuji bukan cuma "kata ketemu", tapi sifat pencarian yang dulu tidak
 * ada: tiap kata harus cocok walau terpencar (dulu harus persis beruntun), isi
 * lampiran terbaca + nama berkas ikut digali, dan nomor surat yang paling cocok
 * didahulukan. Satu di antaranya regression test bug OR di dalam whereHas()
 * yang sudah ada sebelum perubahan ini — lihat
 * test_isi_draf_surat_lain_tidak_bocor_ke_surat_tanpa_draf.
 */
class CariArsipTest extends TestCase
{
    use RefreshDatabase;

    private User $pegawai;

    private User $admin;

    private KlasifikasiPrimer $klasifikasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $this->admin = User::forceCreate([
            'nama_lengkap' => 'Kepala Desa',
            'email' => 'kepala@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        $this->klasifikasi = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);
    }

    private function masuk(array $ubah = []): SuratMasuk
    {
        return SuratMasuk::forceCreate(array_merge([
            'user_id' => $this->pegawai->id,
            'pengirim' => 'Kecamatan Gondanglegi',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'nomor_surat' => 'MSK-'.uniqid(),
            'perihal' => 'Undangan musyawarah desa',
            'ringkasan' => 'Pembahasan anggaran jalan desa',
            'tanggal_surat' => '2026-02-01',
            'tanggal_diterima' => '2026-02-02',
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ], $ubah));
    }

    private function keluar(array $ubah = []): SuratKeluar
    {
        return SuratKeluar::forceCreate(array_merge([
            'user_id' => $this->pegawai->id,
            'penerima' => 'Warga RT 01',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'nomor_surat' => 'KLR-'.uniqid(),
            'perihal' => 'Pemberitahuan kerja bakti',
            'tanggal_surat' => '2026-03-01',
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ], $ubah));
    }

    private function lampiranMasuk(SuratMasuk $surat, string $nama): void
    {
        Lampiran::forceCreate([
            'lampiranable_type' => SuratMasuk::class,
            'lampiranable_id' => $surat->id,
            'nama_file' => $nama,
            'disk' => 'arsip',
            'path' => 'uji/'.$nama,
        ]);
    }

    /**
     * Daftar nomor surat yang benar-benar tampil di layar.
     *
     * Diambil dari teks tautan halaman detail (`/surat-masuk/5`), bukan dari
     * seluruh HTML, supaya `assertNotContains` tidak keliru lolos kena kalimat
     * bantuan di halaman. `<mark>` di dalamnya dibuang lebih dulu.
     *
     * @return list<string>
     */
    private function nomorDiLayar(string $url, ?User $atasNama = null): array
    {
        $html = $this->actingAs($atasNama ?? $this->pegawai)->get($url)->getContent();

        preg_match_all('#<a href="[^"]*surat-(?:masuk|keluar)/\d+"[^>]*>(.*?)</a>#s', $html, $tautan);

        return array_values(array_filter(array_map(
            static fn (string $isi): string => trim(strip_tags($isi)),
            $tautan[1] ?? []
        )));
    }

    // ------------------------------------------------------------------
    // tiap kata harus cocok, boleh terpencar di kolom berbeda
    // ------------------------------------------------------------------

    public function test_setiap_kata_harus_cocok_boleh_di_kolom_berbeda(): void
    {
        $cocok = $this->masuk([
            'nomor_surat' => 'KOPI-1',
            'perihal' => 'Undangan koordinasi koperasi desa',
            'ringkasan' => 'Pembahasan modal 2026',
        ]);
        $setengah = $this->masuk([
            'nomor_surat' => 'KOPI-2',
            'perihal' => 'Undangan koordinasi koperasi desa',
            'ringkasan' => 'Pembahasan modal 2025',
        ]);

        // "koperasi 2026" tidak pernah ada sebagai frasa beruntun di satu kolom:
        // kata pertama di perihal, kata kedua di ringkasan. LIKE lama pasti nol
        // hasil, sekarang ketemu.
        $this->assertSame([$cocok->nomor_surat], $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'koperasi 2026'])));
        $this->assertSame([$setengah->nomor_surat], $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'koperasi 2025'])));
    }

    public function test_urutan_kata_dalam_pencarian_tidak_menentukan(): void
    {
        $surat = $this->masuk(['nomor_surat' => 'BALIK-1', 'perihal' => 'Pembangunan irigasi barat']);

        $this->assertSame(
            [$surat->nomor_surat],
            $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'irigasi pembangunan barat']))
        );
    }

    public function test_satu_kata_saja_yang_tidak_ada_menggagalkan_kecocokan(): void
    {
        $this->masuk(['nomor_surat' => 'SEPARUH-1', 'perihal' => 'Pembangunan irigasi']);

        $this->assertSame(
            [],
            $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'irigasi gedung']))
        );
    }

    // ------------------------------------------------------------------
    // isi lampiran terbaca + nama berkas lampiran
    // ------------------------------------------------------------------

    public function test_isi_hasil_baca_lampiran_ikut_digali(): void
    {
        $tergali = $this->masuk(['nomor_surat' => 'ISI-1', 'perihal' => 'Tagihan pembayaran']);
        $tergali->forceFill(['isi_hasil_baca' => 'Rincian biaya pengerasan jalan beton'])->saveQuietly();

        $lain = $this->masuk(['nomor_surat' => 'ISI-2', 'perihal' => 'Tagihan pembayaran']);
        $lain->forceFill(['isi_hasil_baca' => 'Daftar hadir rapat koordinasi'])->saveQuietly();

        // "pengerasan beton" hanya ada di kolom hasil baca mesin.
        $this->assertSame(
            [$tergali->nomor_surat],
            $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'pengerasan beton']))
        );
    }

    /**
     * "Lemari B, Box 07" satu-satunya petunjuk yang diingat petugas ketika arsip
     * sudah masuk gudang. Kolomnya direkam di form DAN sudah ikut digali sejak
     * refactor `CariArsip` 9 Okt — tapi tidak ada satu pun tes yang membuktikannya,
     * sehingga `docs/daftar-peningkatan.md` masih menuliskannya sebagai "tidak bisa
     * dicari". Tes ini mengubah klaim jadi guarded: kalau seseorang nanti memangkas
     * daftar kolom, ia yang merah, bukan petugas yang kehilangan jalur pencarian.
     */
    public function test_lokasi_fisik_ikut_digali(): void
    {
        $ini = $this->masuk(['nomor_surat' => 'RAK-1', 'perihal' => 'Laporan bulanan']);
        $ini->update(['lokasi_fisik' => 'Lemari B, Box 07']);

        $lain = $this->masuk(['nomor_surat' => 'RAK-2', 'perihal' => 'Laporan bulanan']);
        $lain->update(['lokasi_fisik' => 'Lemari A, Box 01']);

        $keluar = $this->keluar(['nomor_surat' => 'RAK-3', 'perihal' => 'Laporan bulanan']);
        $keluar->update(['lokasi_fisik' => 'Rak gudang C-03']);

        $this->assertSame(
            ['RAK-1'],
            $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'lemari 07'])),
            'Lokasi fisik surat masuk tidak tergali.'
        );
        $this->assertSame(
            ['RAK-3'],
            $this->nomorDiLayar(route('surat-keluar.index', ['cari' => 'gudang c-03'])),
            'Lokasi fisik surat keluar tidak tergali.'
        );
    }

    public function test_nama_berkas_lampiran_ikut_digali(): void
    {
        $surat = $this->masuk(['nomor_surat' => 'BERKAS-1', 'perihal' => 'Tagihan']);
        $this->lampiranMasuk($surat, 'scan_pembayaran_sppd_2026.pdf');

        $keluar = $this->keluar(['nomor_surat' => 'BERKAS-2', 'perihal' => 'Kwitansi']);
        Lampiran::forceCreate([
            'lampiranable_type' => SuratKeluar::class,
            'lampiranable_id' => $keluar->id,
            'nama_file' => 'kwitansi-bensin-operasional.pdf',
            'disk' => 'arsip',
            'path' => 'uji/k.pdf',
        ]);

        $this->assertSame(
            [$surat->nomor_surat],
            $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'sppd']))
        );
        $this->assertSame(
            [$keluar->nomor_surat],
            $this->nomorDiLayar(route('surat-keluar.index', ['cari' => 'kwitansi bensin']))
        );
    }

    /**
     * Regression — dan bug ini SUDAH ada sebelum perubahan 9 Okt 2026.
     *
     * `orWhereHas('drafKonten', fn ($d) => $d->where(...)->orWhere(...))`
     * menghasilkan `WHERE fk = surat.id AND isi_surat LIKE ? OR tembusan LIKE ?`.
     * AND menang atas OR, jadi `tembusan LIKE ?` berdiri sendiri: satu baris
     * draf milik surat lain membuat EXISTS benar untuk SEMUA surat. Perbaikan:
     * kondisi kolom dibungkus `where()` di dalam callback relasi.
     */
    public function test_isi_draf_surat_lain_tidak_bocor_ke_surat_tanpa_draf(): void
    {
        $punyaDraf = $this->keluar(['nomor_surat' => 'PUNYA-1']);
        DrafKontenSuratKeluar::forceCreate([
            'surat_keluar_id' => $punyaDraf->id,
            'isi_surat' => 'Tidak ada kata khusus di sini',
            'tembusan' => 'Yth. Dinas PU — arsip kata ajaib',
        ]);

        // Surat ini TIDAK punya draf sama sekali, tidak boleh ikut muncul.
        $kosong = $this->keluar(['nomor_surat' => 'KOSONG-1', 'perihal' => 'Surat keterangan domisili']);

        $nomor = $this->nomorDiLayar(route('surat-keluar.index', ['cari' => 'ajaib']));

        $this->assertSame([$punyaDraf->nomor_surat], $nomor);
        $this->assertNotContains($kosong->nomor_surat, $nomor);
    }

    /** Sama untuk lampiran: `... type = ? OR nama_file LIKE ?` punya lubang yang sama. */
    public function test_nama_lampiran_surat_lain_tidak_bocor_ke_surat_tanpa_lampiran(): void
    {
        $berlampiran = $this->masuk(['nomor_surat' => 'LAM-1', 'perihal' => 'Rekap']);
        $this->lampiranMasuk($berlampiran, 'rekap-absensi-harian.pdf');

        $polos = $this->masuk(['nomor_surat' => 'LAM-2', 'perihal' => 'Rekap']);

        $nomor = $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'absensi']));

        $this->assertSame([$berlampiran->nomor_surat], $nomor);
        $this->assertNotContains($polos->nomor_surat, $nomor);
    }

    // ------------------------------------------------------------------
    // karakter wildcard yang diketik user harus jadi huruf biasa
    // ------------------------------------------------------------------

    public function test_karakter_wildcard_dalam_kata_diacu_sebagai_huruf_biasa(): void
    {
        $surat = $this->masuk(['nomor_surat' => 'TOPENG-1', 'perihal' => 'Rincian biaya 100 persen']);
        $lain = $this->masuk(['nomor_surat' => 'TOPENG-2', 'perihal' => 'Rincian biaya 50 persen']);
        $this->lampiranMasuk($surat, 'scan_pembayaran.pdf');

        // `_` dulu dicocokkan dengan satu karakter apa pun -> "scanXpembayaran"
        // ikut kena. Sekarang literal.
        $this->assertSame(
            [$surat->nomor_surat],
            $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'scan_pembayaran']))
        );
        $this->assertSame(
            [],
            $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'scanXpembayaran']))
        );

        // `%` juga huruf biasa, bukan "apa pun setelahnya".
        $this->assertSame([], $this->nomorDiLayar(route('surat-masuk.index', ['cari' => '100%'])));
        $this->assertNotContains($lain->nomor_surat, $this->nomorDiLayar(route('surat-masuk.index', ['cari' => '100 persen'])));
    }

    public function test_karakter_escape_itu_sendiri_diacu_sebagai_huruf_biasa(): void
    {
        $surat = $this->masuk(['nomor_surat' => 'MASK-1', 'perihal' => 'Angka 5! persen']);

        $this->assertSame(
            [$surat->nomor_surat],
            $this->nomorDiLayar(route('surat-masuk.index', ['cari' => '5!']))
        );
    }

    // ------------------------------------------------------------------
    // peringkat relevansi
    // ------------------------------------------------------------------

    public function test_nomor_surat_yang_paling_cocok_dimulai_dari_yang_persis(): void
    {
        $persis = $this->masuk([
            'nomor_surat' => '471/001',
            'perihal' => 'Undangan',
            'tanggal_diterima' => '2026-01-05',
        ]);
        // Jauh lebih baru, tapi cuma MENYEBUT angkanya di perihal.
        $sebagian = $this->masuk([
            'nomor_surat' => '999/777',
            'perihal' => 'Refisi anggaran 471 untuk irigasi',
            'tanggal_diterima' => '2026-09-09',
        ]);

        $nomor = $this->nomorDiLayar(route('surat-masuk.index', ['cari' => '471']));

        // Tanpa peringkat, urutan tanggal_diterima desc menempatkan 999/777 di atas.
        $this->assertSame([$persis->nomor_surat, $sebagian->nomor_surat], $nomor);
    }

    public function test_tanpa_kata_kunci_urutan_tetap_menurut_tanggal(): void
    {
        $lama = $this->masuk(['nomor_surat' => 'URUT-LAMA', 'tanggal_diterima' => '2026-01-01']);
        $baru = $this->masuk(['nomor_surat' => 'URUT-BARU', 'tanggal_diterima' => '2026-08-08']);

        $this->assertSame(
            [$baru->nomor_surat, $lama->nomor_surat],
            $this->nomorDiLayar(route('surat-masuk.index'))
        );
    }

    // ------------------------------------------------------------------
    // index tetap terpakai: whereYear -> whereBetween
    // ------------------------------------------------------------------

    public function test_filter_tahun_memakai_perbandingan_kolom_bukan_fungsi_year(): void
    {
        $query = SuratMasuk::query();
        FilterArsip::terapkan($query, Request::create('/surat-masuk', 'GET', ['tahun' => '2026']));

        // Fungsi di atas kolom (YEAR/stri) membuat index `tanggal_surat` tidak
        // terpakai, jadi yang harus ada adalah dua perbandingan biasa.
        $this->assertStringNotContainsString('year(', strtolower($query->toSql()));
        $this->assertStringNotContainsString('strftime', strtolower($query->toSql()));
        $this->assertSame(['2026-01-01', '2027-01-01'], array_slice($query->getBindings(), 0, 2));

        $tahunIni = $this->masuk(['nomor_surat' => 'TAHUN-2026', 'tanggal_surat' => '2026-07-07']);
        $tahunLalu = $this->masuk(['nomor_surat' => 'TAHUN-2024', 'tanggal_surat' => '2024-05-05']);

        $this->assertSame([$tahunIni->nomor_surat], $this->nomorDiLayar(route('surat-masuk.index', ['tahun' => 2026])));
        $this->assertSame([$tahunLalu->nomor_surat], $this->nomorDiLayar(route('surat-masuk.index', ['tahun' => 2024])));
        $this->assertSame([], $this->nomorDiLayar(route('surat-masuk.index', ['tahun' => 2023])));
    }

    /**
     * Batas tahun. Dua-duanya pernah salah, dan yang kedua cuma kelihatan di
     * SQLite: kolom date di sana menyimpan '2026-12-31 00:00:00', sehingga
     * `BETWEEN '2026-01-01' AND '2026-12-31'` diam-diam MEMBUANG surat tertanggal
     * 31 Desember. Interval setengah terbuka benar di SQLite maupun MariaDB.
     */
    public function test_filter_tahun_mencakup_31_desember_dan_tidak_kebabatan_januari(): void
    {
        $ujungTahun = $this->masuk(['nomor_surat' => 'UJUNG', 'tanggal_surat' => '2026-12-31']);
        $awalTahun = $this->masuk(['nomor_surat' => 'AWAL', 'tanggal_surat' => '2026-01-01']);
        $tahunDepan = $this->masuk(['nomor_surat' => '2027', 'tanggal_surat' => '2027-01-01']);

        $nomor = $this->nomorDiLayar(route('surat-masuk.index', ['tahun' => 2026]));

        $this->assertContains($ujungTahun->nomor_surat, $nomor);
        $this->assertContains($awalTahun->nomor_surat, $nomor);
        $this->assertNotContains($tahunDepan->nomor_surat, $nomor);

        // Hasil harus sama dengan whereYear lama.
        $rentang = SuratMasuk::query()->where('tanggal_surat', '>=', '2026-01-01')
            ->where('tanggal_surat', '<', '2027-01-01')->orderBy('nomor_surat')->pluck('nomor_surat')->all();
        $fungsi = SuratMasuk::query()->whereYear('tanggal_surat', 2026)
            ->orderBy('nomor_surat')->pluck('nomor_surat')->all();

        $this->assertSame($fungsi, $rentang);
    }

    // ------------------------------------------------------------------
    // mode gabungan "Semua arsip"
    // ------------------------------------------------------------------

    public function test_tab_semua_arsip_menggabungkan_dua_jenis(): void
    {
        $masuk = $this->masuk(['nomor_surat' => 'GAB-M', 'perihal' => 'Kata gabungan unik']);
        $keluar = $this->keluar(['nomor_surat' => 'GAB-K', 'perihal' => 'Kata gabungan unik']);

        foreach (['surat-masuk.index', 'surat-keluar.index'] as $route) {
            $nomor = $this->nomorDiLayar(route($route, ['cari' => 'gabungan unik', 'jenis' => 'semua']));

            $this->assertCount(2, $nomor, "route {$route}");
            $this->assertContains($masuk->nomor_surat, $nomor, "route {$route}");
            $this->assertContains($keluar->nomor_surat, $nomor, "route {$route}");
        }
    }

    public function test_mode_gabungan_menghormati_filter_lain(): void
    {
        $lain = KlasifikasiPrimer::forceCreate(['kode' => '09', 'nama' => 'Lainnya']);

        $ini = $this->masuk(['nomor_surat' => 'SIFT-A', 'perihal' => 'kata seragam']);
        $bedaNama = $this->keluar(['nomor_surat' => 'SIFT-B', 'perihal' => 'kata seragam', 'klasifikasi_primer_id' => $lain->id]);
        $inaktif = $this->keluar(['nomor_surat' => 'SIFT-C', 'perihal' => 'kata seragam', 'status_arsip' => 'inaktif']);

        $nomor = $this->nomorDiLayar(route('surat-masuk.index', [
            'jenis' => 'semua',
            'cari' => 'kata seragam',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'status_arsip' => 'aktif',
        ]));

        $this->assertSame([$ini->nomor_surat], $nomor);
        $this->assertNotContains($bedaNama->nomor_surat, $nomor);
        $this->assertNotContains($inaktif->nomor_surat, $nomor);
    }

    public function test_mode_gabungan_bisa_ke_halaman_dua_dengan_benar(): void
    {
        // 25 masuk + 25 keluar. Dua `paginate()` terpisah tidak bisa memberi
        // halaman 2 gabungan yang benar — makanya UNION.
        foreach (range(1, 25) as $i) {
            $tanggal = (new \DateTime('2026-01-01'))->modify("+{$i} days")->format('Y-m-d');
            $this->masuk([
                'nomor_surat' => "H2-M{$i}",
                'perihal' => 'dokumen berjenjang',
                'tanggal_surat' => $tanggal,
                'tanggal_diterima' => $tanggal,
            ]);
            $this->keluar([
                'nomor_surat' => "H2-K{$i}",
                'perihal' => 'dokumen berjenjang',
                'tanggal_surat' => $tanggal,
            ]);
        }

        $halaman1 = $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'dokumen berjenjang', 'jenis' => 'semua']));
        $halaman2 = $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'dokumen berjenjang', 'jenis' => 'semua', 'page' => 2]));

        $this->assertCount(20, $halaman1);
        $this->assertCount(20, $halaman2);

        // Campuran kedua jenis di halaman 1, bukan 20 surat masuk saja.
        $this->assertContains('H2-M25', $halaman1);
        $this->assertContains('H2-K25', $halaman1);
        $this->assertNotContains('H2-M10', $halaman1);

        $this->assertContains('H2-M10', $halaman2);
        $this->assertContains('H2-K10', $halaman2);

        // Tidak ada surat yang muncul dua kali di dua halaman.
        $this->assertEmpty(array_intersect($halaman1, $halaman2));
    }

    public function test_surat_terhapus_tidak_muncul_di_mode_gabungan(): void
    {
        $terhapus = $this->masuk(['nomor_surat' => 'GAB-HILANG', 'perihal' => 'kata gabungan']);
        $terhapus->delete();

        $this->masuk(['nomor_surat' => 'GAB-ADA', 'perihal' => 'kata gabungan']);

        $this->assertSame(
            ['GAB-ADA'],
            $this->nomorDiLayar(route('surat-masuk.index', ['cari' => 'kata gabungan', 'jenis' => 'semua']))
        );
    }

    public function test_tempat_sampah_menang_atas_mode_gabungan(): void
    {
        $terhapus = $this->masuk(['nomor_surat' => 'SAMPAH-1', 'perihal' => 'laporan buang']);
        $terhapus->delete();

        $this->actingAs($this->admin)
            ->get(route('surat-masuk.index', ['sampah' => 1, 'jenis' => 'semua']))
            ->assertOk()
            ->assertSee('Tempat Sampah Surat Masuk');

        $this->assertSame(
            ['SAMPAH-1'],
            $this->nomorDiLayar(route('surat-masuk.index', ['sampah' => 1]), $this->admin)
        );
    }

    public function test_mode_gabungan_tidak_menambah_route_baru(): void
    {
        // `/pencarian` sudah dihapus 5 Okt 2026 dan tab ini sengaja TIDAK
        // menghidupkannya lagi sebagai halaman sendiri.
        $this->actingAs($this->pegawai)->get('/pencarian')->assertNotFound();
        $this->actingAs($this->pegawai)->get('/arsip')->assertNotFound();
    }

    /**
     * Mode gabungan tetap ADA (L-15) tapi tidak lagi jadi tombol ketiga di kepala
     * halaman (permintaan user 10 Okt 2026): dia muncul sebagai tautan di samping
     * pemilih jenis surat, hanya saat ada kata kunci — karena justru di situ orang
     * tidak ingat suratnya masuk atau keluar. Yang diuji di sini: bantuan itu ada
     * saat dibutuhkan, hilang saat tidak, dan ada jalan keluar yang menyebut
     * daftar yang benar-benar akan ditampilkan.
     */
    public function test_mode_gabungan_ditemukan_lewat_tautan_bukan_tab_ketiga(): void
    {
        // Tanpa kata kunci: tidak ada ajakan gabungan sama sekali (layar tetap bersih).
        $polos = $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('di semua arsip', $polos);
        $this->assertStringNotContainsString('jenis=semua', $polos);

        // Dengan kata kunci: tautannya muncul dan membawa filter yang sedang dipakai.
        $denganKata = $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index', ['cari' => 'koperasi']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('di semua arsip', $denganKata);
        $this->assertStringContainsString('jenis=semua', $denganKata);
        $this->assertStringContainsString('cari=koperasi', $denganKata,
            'Tautan gabungan tidak boleh menghapus kata kunci yang sedang diketik.');
    }

    public function test_mode_gabungan_menampilkan_status_dan_jalan_keluar_yang_benar(): void
    {
        $masuk = $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index', ['jenis' => 'semua']))
            ->assertOk()
            ->assertSee('Semua arsip (masuk + keluar)')
            ->getContent();

        // Jalan keluar harus menyebut daftar yang akan ditampilkan. Bug lama: label
        // diambil dari `$aktif` yang di cabang ini selalu 'semua', jadi selalu salah.
        $this->assertStringContainsString('Kembali ke Surat Masuk saja', $masuk);

        $keluar = $this->actingAs($this->pegawai)
            ->get(route('surat-keluar.index', ['jenis' => 'semua']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Kembali ke Surat Keluar saja', $keluar);
    }

    public function test_info_panjang_dari_daftar_dipindah_ke_tooltip(): void
    {
        // Permintaan user: penjelasan tidak boleh memakan tempat di layar. Teks
        // "Tiap kata harus cocok ..." dan "Daftar gabungan: ..." tidak lagi ditulis
        // sebagai paragraf — isinya kini hidup di atribut `title` ikon info, yang
        // sengaja TETAP ada supaya penjelasan tidak hilang saat JavaScript mati.
        $html = $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Daftar gabungan: surat masuk dan keluar', $html);
        $this->assertStringNotContainsString('<div class="form-text">', $html);
        $this->assertStringContainsString('data-bs-toggle="tooltip"', $html);
        $this->assertMatchesRegularExpression(
            '/title="Tiap kata harus cocok[^"]*"/',
            $html,
            'Penjelasan pencarian harus tetap terbaca tanpa JavaScript (title, bukan data-bs-title kosong).'
        );
    }

    // ------------------------------------------------------------------
    // highlight
    // ------------------------------------------------------------------

    public function test_sorot_menandai_kata_dan_tetap_ter_escape(): void
    {
        $hasil = (string) CariArsip::sorot('Undangan rapat 471', 'rapat 471');
        $this->assertStringContainsString('<mark>rapat</mark>', $hasil);
        $this->assertStringContainsString('<mark>471</mark>', $hasil);

        // Isi surat adalah isian user: tag tidak boleh lolos jadi markup.
        $bahaya = (string) CariArsip::sorot('<script>alert(1)</script> izin', 'izin');
        $this->assertStringNotContainsString('<script>', $bahaya);
        $this->assertStringContainsString('&lt;script&gt;', $bahaya);
        $this->assertStringContainsString('<mark>izin</mark>', $bahaya);

        $this->assertSame('Kerja bakti', (string) CariArsip::sorot('Kerja bakti', null));
        $this->assertSame('', (string) CariArsip::sorot(null, 'apa'));
    }

    public function test_sorot_muncul_di_daftar_saat_ada_kata_kunci(): void
    {
        $this->masuk(['nomor_surat' => 'MARK-1', 'perihal' => 'Usulan pembangunan taman']);

        $html = $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index', ['cari' => 'pembangunan']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<mark>pembangunan</mark>', $html);
    }

    public function test_sorot_dipakai_di_daftar_gabungan(): void
    {
        $this->masuk(['nomor_surat' => 'MARK-M', 'perihal' => 'Usulan pembangunan taman']);

        $html = $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index', ['cari' => 'pembangunan', 'jenis' => 'semua']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<mark>pembangunan</mark>', $html);
    }

    // ------------------------------------------------------------------
    // pemecahan kata: batas-batasnya
    // ------------------------------------------------------------------

    public function test_pemisahan_kata_membuang_spasi_berlebih_dan_membatas_jumlah(): void
    {
        $this->assertSame(['surat', 'izin', '2024'], CariArsip::kata("  surat\n\tizin   2024  "));
        $this->assertSame([], CariArsip::kata('   '));
        $this->assertSame([], CariArsip::kata(null));
        $this->assertSame(['a', 'b'], CariArsip::kata('a b a b'));
        $this->assertCount(CariArsip::MAKS_KATA, CariArsip::kata(implode(' ', range(1, 20))));

        // Kata tunggal yang kepanjangan dipotong, bukan jadi pola LIKE raksasa.
        $this->assertSame(CariArsip::MAKS_PANJANG_KATA, mb_strlen(CariArsip::kata(str_repeat('a', 500))[0]));
    }

    public function test_kata_kunci_dengan_spasi_berlebihan_tetap_berhasil(): void
    {
        $surat = $this->masuk(['nomor_surat' => 'SPASI-1', 'perihal' => 'Rapat koordinasi']);

        // Enter/tab di tengah kotak cari (kaca dari pengalaman menyalin dari
        // chat) tidak boleh membuat pencarian jadi nol hasil.
        $this->assertSame(
            [$surat->nomor_surat],
            $this->nomorDiLayar(route('surat-masuk.index', ['cari' => "  koordinasi\t rapat \n"]))
        );
    }
}
