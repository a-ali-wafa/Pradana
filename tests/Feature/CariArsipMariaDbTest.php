<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Services\DaftarArsipGabungan;
use App\Support\FilterArsip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jalur pencarian yang paling rawan beda engine, dijalankan di MariaDB SUNGGUHAN
 * (bukti, bukan asumsi) — pola yang sama dengan NomorSuratKeluarTest, L-24/Q1=b.
 *
 * Suite utama berjalan di SQLite in-memory, dan ada tiga hal yang TIDAK bisa
 * dibuktikan dari sana:
 * 1. `LIKE ? ESCAPE '!'` — karakter escape sengaja dipilih agar satu sintaks
 *    berlaku di dua engine; di sinilah dibuktikan MariaDB menerimanya.
 * 2. `CASE ... END` di dalam ORDER BY (peringkat relevansi).
 * 3. tab "Semua arsip": UNION dua kaki yang masing-masing membawa parameter di
 *    selectRaw DAN where. Kalau urutan binding Laravel tidak sama dengan urutan
 *    di teks SQL, datanya salah tanpa error — yang paling bahaya.
 *
 * Kolom DATE MariaDB juga memotong bagian jam, sedangkan SQLite menyimpan
 * '2026-12-31 00:00:00' — selisih yang membuat filter tahun versi BETWEEN salah
 * di salah satu engine. Diuji dua-duanya.
 *
 * Kalau XAMPP/MariaDB mati, semua tes di kelas ini DI-SKIP dengan pesan yang
 * jelas, bukan lulus semu.
 */
class CariArsipMariaDbTest extends TestCase
{
    private const PENANDA = 'MJT';

    private int $primerId;

    private int $userId;

    public static function setUpBeforeClass(): void
    {
        try {
            $pdo = new \PDO(
                'mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';port='.(getenv('DB_PORT') ?: '3306'),
                getenv('DB_USERNAME') ?: 'root',
                getenv('DB_PASSWORD') ?: '',
            );
        } catch (\Throwable) {
            return; // markTestSkipped() di setUp()
        }

        $namaDb = preg_replace('/[^A-Za-z0-9_]/', '', getenv('DB_TEST_DATABASE') ?: 'pradana_test');
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$namaDb}` CHARACTER SET utf8mb4");
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->mariaHidup()) {
            $this->markTestSkipped(
                'MariaDB (XAMPP) tidak berjalan — tes portabilitas pencarian dilewati. '.
                'Nyalakan MySQL di XAMPP lalu jalankan: php artisan test --filter=CariArsipMariaDbTest'
            );
        }

        Artisan::call('migrate', ['--database' => 'mysql_test_a', '--force' => true]);

        config(['database.default' => 'mysql_test_a']);

        $this->bersihkan();

        $this->primerId = KlasifikasiPrimer::query()->create(['kode' => 'ZC', 'nama' => 'Uji Cari'])->id;

        $petugas = User::query()->where('email', 'petugas.cari@example.test')->first();
        if (! $petugas) {
            $petugas = User::query()->create([
                'nama_lengkap' => 'Petugas Uji Cari',
                'email' => 'petugas.cari@example.test',
                'pin' => bcrypt('12345678'),
                'role' => 'pegawai',
            ]);
        }

        $this->userId = $petugas->id;
    }

    protected function tearDown(): void
    {
        $this->bersihkan();

        config(['database.default' => 'mysql']);

        parent::tearDown();
    }

    private function bersihkan(): void
    {
        $idMasuk = SuratMasuk::query()->where('nomor_surat', 'like', self::PENANDA.'-%')->pluck('id')->all();

        if ($idMasuk !== []) {
            Lampiran::query()->where('lampiranable_type', SuratMasuk::class)
                ->whereIn('lampiranable_id', $idMasuk)->delete();
        }

        $idKeluar = SuratKeluar::query()->where('nomor_surat', 'like', self::PENANDA.'-%')->pluck('id')->all();

        if ($idKeluar !== []) {
            DB::table('draf_konten_surat_keluar')->whereIn('surat_keluar_id', $idKeluar)->delete();
            Lampiran::query()->where('lampiranable_type', SuratKeluar::class)
                ->whereIn('lampiranable_id', $idKeluar)->delete();
        }

        SuratMasuk::query()->where('nomor_surat', 'like', self::PENANDA.'-%')->forceDelete();
        SuratKeluar::query()->where('nomor_surat', 'like', self::PENANDA.'-%')->forceDelete();
        KlasifikasiPrimer::query()->where('kode', 'ZC')->delete();
    }

    private function masuk(array $ubah = []): SuratMasuk
    {
        $surat = SuratMasuk::query()->create(array_merge([
            'user_id' => $this->userId,
            'pengirim' => 'Kecamatan Uji',
            'klasifikasi_primer_id' => $this->primerId,
            'nomor_surat' => self::PENANDA.'-M-'.str_pad((string) uniqid(), 6, '0', STR_PAD_LEFT),
            'perihal' => 'Undangan koordinasi',
            'ringkasan' => 'Anggaran jalan desa',
            'tanggal_surat' => '2026-02-01',
            'tanggal_diterima' => '2026-02-02',
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ], $ubah));

        return $surat;
    }

    private function keluar(array $ubah = []): SuratKeluar
    {
        return SuratKeluar::query()->create(array_merge([
            'user_id' => $this->userId,
            'penerima' => 'Warga RT 01',
            'klasifikasi_primer_id' => $this->primerId,
            'nomor_surat' => self::PENANDA.'-K-'.str_pad((string) uniqid(), 6, '0', STR_PAD_LEFT),
            'perihal' => 'Pemberitahuan kerja bakti',
            'tanggal_surat' => '2026-03-01',
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ], $ubah));
    }

    private function mariaHidup(): bool
    {
        try {
            DB::connection('mysql_test_a')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Character escape `!` dan CASE di ORDER BY harus diterima MariaDB apa adanya.
     * Kalau tidak, pencarian melempar QueryException — tes ini menangkapnya.
     */
    public function test_like_escape_dan_order_by_case_diterima_mariadb(): void
    {
        $surat = $this->masuk(['nomor_surat' => self::PENANDA.'-ESCAPE', 'perihal' => 'Rincian biaya 100 persen']);

        $nomor = SuratMasuk::query()->cari('100 persen')->palingRelevan('100 persen')
            ->orderByDesc('tanggal_diterima')->pluck('nomor_surat')->all();

        $this->assertSame([$surat->nomor_surat], $nomor);

        // `%` dan `_` yang diketik user tidak boleh jadi wildcard.
        $this->assertSame([], SuratMasuk::query()->cari('100%')->pluck('nomor_surat')->all());
        $this->assertSame('Rincian biaya 100 persen', $surat->fresh()->perihal);
    }

    public function test_underscore_dalam_nama_berkas_diacu_literal_di_mariadb(): void
    {
        $surat = $this->masuk(['nomor_surat' => self::PENANDA.'-UNDERSCORE', 'perihal' => 'Tagihan']);
        Lampiran::query()->create([
            'lampiranable_type' => SuratMasuk::class,
            'lampiranable_id' => $surat->id,
            'nama_file' => 'scan_pembayaran.pdf',
            'disk' => 'arsip',
            'path' => 'uji/scan_pembayaran.pdf',
        ]);

        $this->assertSame(
            [$surat->nomor_surat],
            SuratMasuk::query()->cari('scan_pembayaran')->pluck('nomor_surat')->all()
        );
        $this->assertSame(
            [],
            SuratMasuk::query()->cari('scanXpembayaran')->pluck('nomor_surat')->all(),
            'Di MariaDB, LIKE tanpa ESCAPE membuat _ jadi wildcard satu karakter.'
        );
    }

    /** Collation utf8mb4_unicode_ci membuat LIKE tidak peka huruf — perilaku produksi. */
    public function test_pencarian_tidak_peka_huruf_di_mariadb(): void
    {
        $surat = $this->masuk(['nomor_surat' => self::PENANDA.'-CI', 'perihal' => 'Undangan Musyawarah Desa']);

        foreach (['undangan musyawarah', 'UNDANGAN', 'Undangan'] as $ketikan) {
            $this->assertContains(
                $surat->nomor_surat,
                SuratMasuk::query()->cari($ketikan)->pluck('nomor_surat')->all(),
                "kata kunci '{$ketikan}' harus tetap cocok"
            );
        }
    }

    public function test_isi_hasil_baca_dan_nama_lampiran_tergali_di_mariadb(): void
    {
        $mesin = $this->masuk(['nomor_surat' => self::PENANDA.'-ISI', 'perihal' => 'Tagihan']);
        $mesin->forceFill(['isi_hasil_baca' => 'Rincian biaya pengerasan jalan beton'])->save();

        $berkas = $this->masuk(['nomor_surat' => self::PENANDA.'-BERKAS', 'perihal' => 'Rekap']);
        Lampiran::query()->create([
            'lampiranable_type' => SuratMasuk::class,
            'lampiranable_id' => $berkas->id,
            'nama_file' => 'rekap-absensi-harian.pdf',
            'disk' => 'arsip',
            'path' => 'uji/rekap.pdf',
        ]);

        $this->assertSame(
            [$mesin->nomor_surat],
            SuratMasuk::query()->cari('pengerasan beton')->pluck('nomor_surat')->all()
        );
        $this->assertSame(
            [$berkas->nomor_surat],
            SuratMasuk::query()->cari('absensi harian')->pluck('nomor_surat')->all()
        );
    }

    /** Bug lama (OR menempel ke constraint relasi) — dibuktikan juga di engine produksi. */
    public function test_isi_draf_surat_lain_tidak_bocor_di_mariadb(): void
    {
        $punyaDraf = $this->keluar(['nomor_surat' => self::PENANDA.'-PUNYA']);
        DB::table('draf_konten_surat_keluar')->insert([
            'surat_keluar_id' => $punyaDraf->id,
            'isi_surat' => 'Tidak ada kata khusus',
            'tembusan' => 'Yth. Dinas PU — arsip kata ajaib',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kosong = $this->keluar(['nomor_surat' => self::PENANDA.'-KOSONG', 'perihal' => 'Keterangan domisili']);

        $nomor = SuratKeluar::query()->cari('ajaib')->pluck('nomor_surat')->all();

        $this->assertSame([$punyaDraf->nomor_surat], $nomor);
        $this->assertNotContains($kosong->nomor_surat, $nomor);
    }

    /**
     * Tiga tier peringkat diuji sekaligus di engine produksi, dan tiap surat
     * yang tertukar cuma terlihat karena tanggalnya dibalik: yang paling baru
     * justru harus jatuh ke bawah karena cuma "menyebut".
     */
    public function test_peringkat_relevansi_berjalan_di_mariadb(): void
    {
        $persis = $this->masuk([
            'nomor_surat' => self::PENANDA.'-471',
            'perihal' => 'Undangan',
            'tanggal_diterima' => '2026-01-05',
        ]);
        $awalan = $this->masuk([
            'nomor_surat' => self::PENANDA.'-4712',
            'perihal' => 'Sanggahan',
            'tanggal_diterima' => '2026-05-05',
        ]);
        $sebut = $this->masuk([
            'nomor_surat' => self::PENANDA.'-999',
            'perihal' => 'Refisi '.self::PENANDA.'-471 untuk irigasi',
            'tanggal_diterima' => '2026-09-09',
        ]);

        $nomor = SuratMasuk::query()->cari(self::PENANDA.'-471')->palingRelevan(self::PENANDA.'-471')
            ->orderByDesc('tanggal_diterima')->orderByDesc('id')->pluck('nomor_surat')->all();

        $this->assertSame(
            [$persis->nomor_surat, $awalan->nomor_surat, $sebut->nomor_surat],
            $nomor,
            'Urutan harus menurut relevansi: nomor persis (400) > nomor diawali (300) > cuma disebut (100).'
        );
    }

    /**
     * Kolom DATE sungguhan menyimpan tanpa jam; SQLite menyimpan '2026-12-31
     * 00:00:00'. Interval setengah terbuka benar di keduanya — di sinilah sisi
     * MariaDB-nya dibuktikan.
     */
    public function test_filter_tahun_mencakup_31_desember_di_kolom_date_sungguhan(): void
    {
        $ujung = $this->masuk(['nomor_surat' => self::PENANDA.'-DESEMBER', 'tanggal_surat' => '2026-12-31']);
        $tahunDepan = $this->masuk(['nomor_surat' => self::PENANDA.'-JANUARI', 'tanggal_surat' => '2027-01-01']);

        $query = SuratMasuk::query();
        FilterArsip::terapkan($query, Request::create('/surat-masuk', 'GET', ['tahun' => '2026']));

        $nomor = $query->pluck('nomor_surat')->all();

        $this->assertContains($ujung->nomor_surat, $nomor);
        $this->assertNotContains($tahunDepan->nomor_surat, $nomor);

        // Buktinya index bisa dipakai: tidak ada fungsi di atas kolom, dan
        // optimizer menyebut index `tanggal_surat` sebagai kandidat.
        $this->assertStringNotContainsString('year(', strtolower($query->toSql()));

        $explain = DB::selectOne(
            "EXPLAIN SELECT id FROM surat_masuk WHERE tanggal_surat >= '2026-01-01' AND tanggal_surat < '2027-01-01'"
        );

        $this->assertNotNull($explain->possible_keys, 'Range tanggal seharusnya punya kandidat index.');
        $this->assertStringContainsString('tanggal_surat', (string) $explain->possible_keys);
    }

    /**
     * Inti tab "Semua arsip": dua kaki UNION yang MASING-MASING membawa
     * parameter di selectRaw (skor) dan di where (kata kunci). Kalau Laravel
     * menata binding tidak seurut teks SQL, skor/hasil kaki kedua dipakai kaki
     * pertama tanpa error apa pun. Di sini kedua kaki sengaja pakai kata kunci
     * BERBEDA supaya tertukar langsung kelihatan.
     */
    public function test_binding_union_dua_kaki_tidak_tertukar_di_mariadb(): void
    {
        $masuk = $this->masuk(['nomor_surat' => self::PENANDA.'-UNION-M', 'perihal' => 'Koordinasi antar desa']);
        $keluar = $this->keluar(['nomor_surat' => self::PENANDA.'-UNION-K', 'perihal' => 'Pemberitahuan kerja bakti']);

        $kakiMasuk = SuratMasuk::query()
            ->selectRaw("id, 'masuk' as jenis, tanggal_surat, CASE WHEN perihal LIKE ? ESCAPE '!' THEN 400 ELSE 0 END as skor", ['%koordinasi%'])
            ->where('perihal', 'like', '%koordinasi%');

        $kakiKeluar = SuratKeluar::query()
            ->selectRaw("id, 'keluar' as jenis, tanggal_surat, CASE WHEN perihal LIKE ? ESCAPE '!' THEN 400 ELSE 0 END as skor", ['%kerja bakti%'])
            ->where('perihal', 'like', '%kerja bakti%');

        $baris = DB::query()
            ->fromSub($kakiMasuk->union($kakiKeluar), 'hasil_arsip')
            ->orderByDesc('skor')
            ->get();

        $this->assertCount(2, $baris);

        $olehJenis = $baris->keyBy('jenis');
        $this->assertSame($masuk->nomor_surat, SuratMasuk::find($olehJenis->get('masuk')->id)->nomor_surat);
        $this->assertSame($keluar->nomor_surat, SuratKeluar::find($olehJenis->get('keluar')->id)->nomor_surat);
        $this->assertEquals(400, $olehJenis->get('masuk')->skor, 'skor kaki masuk tertukar');
        $this->assertEquals(400, $olehJenis->get('keluar')->skor, 'skor kaki keluar tertukar');
    }

    /** Service gabungan dipakai apa adanya lewat HTTP, di MariaDB. */
    public function test_mode_gabungan_berfungsi_di_mariadb(): void
    {
        $masuk = $this->masuk(['nomor_surat' => self::PENANDA.'-GAB-M', 'perihal' => 'kata seragam']);
        $keluar = $this->keluar(['nomor_surat' => self::PENANDA.'-GAB-K', 'perihal' => 'kata seragam']);

        $hasil = DaftarArsipGabungan::halaman(
            Request::create('/surat-masuk', 'GET', ['cari' => 'kata seragam', 'jenis' => 'semua'])
        );

        $nomor = collect($hasil->items())->map(fn ($s) => $s->nomor_surat)->all();

        $this->assertSame(2, $hasil->total());
        $this->assertContains($masuk->nomor_surat, $nomor);
        $this->assertContains($keluar->nomor_surat, $nomor);

        // Satu dari masing-masing jenis, apa pun urutan halamannya.
        $jenis = collect($hasil->items())->pluck('jenis_arsip')->sort()->values()->all();
        $this->assertSame(['keluar', 'masuk'], $jenis);
    }

    public function test_mode_gabungan_menghormati_filter_tanggal_di_mariadb(): void
    {
        $ini = $this->masuk(['nomor_surat' => self::PENANDA.'-T2026', 'perihal' => 'kata seragam', 'tanggal_surat' => '2026-04-04']);
        $itu = $this->keluar(['nomor_surat' => self::PENANDA.'-T2024', 'perihal' => 'kata seragam', 'tanggal_surat' => '2024-04-04']);

        $hasil = DaftarArsipGabungan::halaman(
            Request::create('/surat-masuk', 'GET', ['cari' => 'kata seragam', 'tahun' => '2026', 'jenis' => 'semua'])
        );

        $nomor = collect($hasil->items())->map(fn ($s) => $s->nomor_surat)->all();

        $this->assertSame([$ini->nomor_surat], $nomor);
        $this->assertNotContains($itu->nomor_surat, $nomor);
    }
}
