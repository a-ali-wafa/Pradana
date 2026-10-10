<?php

namespace Tests\Feature;

use App\Models\DrafKontenSuratKeluar;
use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\PengajuanHapusLampiran;
use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guardrail jumlah query — dibuat untuk fase "analisis" rencana perapian kode
 * (9 Okt 2026), supaya optimasi diukur, bukan dirasakan.
 *
 * Cara kerjanya: tanam data secukupnya (200 surat), kosongkan query log, lalu
 * panggil satu layar lewat HTTP seperti user dan hitung berapa statement yang
 * benar-benar jalan. Ambang di bawah = ANGKA TERUKUR + 2 (diukur 9 Okt 2026
 * dengan `LAPOR_QUERY=1 php artisan test --filter=JumlahQueryLayarTest`); setiap
 * optimasi yang menurunkan angkanya harus ikut MENURUNKAN ambangnya, dan kenaikan
 * di masa depan akan gagal di sini — bukan di layar petugas saat data kantor
 * sudah ribuan surat.
 *
 * `LAPOR_SEMUA=1` mencetak daftar statement-nya, berguna waktu mau menurunkan
 * ambang: angka total saja tidak memberi tahu query mana yang boleh dibuang.
 *
 * Ambang +2 (bukan pas angka) supaya tes ini tidak rapuh terhadap refactor kecil
 * seperti menambah satu eager load; fungsinya menangkap N+1 yang kembali masuk.
 *
 * `CetakSuratKeluarController` dan PDF tidak diukur: dompdf membuat ratusan
 * query internal kecil yang tidak relevan dengan alur data aplikasi.
 */
class JumlahQueryLayarTest extends TestCase
{
    use RefreshDatabase;

    private const JUMLAH_SURAT = 200;

    /** Surat lama + sudah inaktif: kandidat pemusnahan (L-04 + L-19). */
    private const JUMLAH_USANG = 40;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'nama_lengkap' => 'Kepala Desa',
            'email' => 'kepala@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        $primer = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);

        // 200 surat + 20 draf + 20 lampiran + beberapa pengajuan: cukup besar
        // supaya N+1 kelihatan, cukup kecil supaya tes tetap cepat.
        for ($i = 1; $i <= self::JUMLAH_SURAT; $i++) {
            $tanggal = (new \DateTime('2026-01-01'))->modify('+'.($i % 300).' days')->format('Y-m-d');

            $masuk = SuratMasuk::forceCreate([
                'user_id' => $this->admin->id,
                'pengirim' => 'Pengirim '.$i,
                'klasifikasi_primer_id' => $primer->id,
                'nomor_surat' => 'UKUR-M'.$i,
                'perihal' => 'Perihal surat masuk '.$i,
                'ringkasan' => 'Ringkasan '.$i,
                'tanggal_surat' => $tanggal,
                'tanggal_diterima' => $tanggal,
                'status_arsip' => $i % 3 === 0 ? 'inaktif' : 'aktif',
                'sifat' => ['biasa', 'penting', 'mendesak'][$i % 3],
            ]);

            $keluar = SuratKeluar::forceCreate([
                'user_id' => $this->admin->id,
                'penerima' => 'Penerima '.$i,
                'klasifikasi_primer_id' => $primer->id,
                'nomor_surat' => 'UKUR-K'.$i,
                'perihal' => 'Perihal surat keluar '.$i,
                'tanggal_surat' => $tanggal,
                'status_arsip' => $i % 4 === 0 ? 'inaktif' : 'aktif',
                'sifat' => ['biasa', 'penting', 'rahasia'][$i % 3],
            ]);

            if ($i % 10 === 0) {
                DrafKontenSuratKeluar::forceCreate([
                    'surat_keluar_id' => $keluar->id,
                    'isi_surat' => 'Isi surat nomor '.$i,
                ]);
                Lampiran::forceCreate([
                    'lampiranable_type' => SuratMasuk::class,
                    'lampiranable_id' => $masuk->id,
                    'nama_file' => 'berkas-'.$i.'.pdf',
                    'disk' => 'arsip',
                    'path' => 'uji/berkas-'.$i.'.pdf',
                ]);
                PengajuanHapusLampiran::forceCreate([
                    'lampiran_id' => Lampiran::query()->latest('id')->value('id'),
                    'nama_file_snapshot' => 'berkas-'.$i.'.pdf',
                    'diajukan_oleh' => $this->admin->id,
                    'status' => 'menunggu',
                ]);
            }
        }

        // Surat LAMA (lewat retensi 5 tahun) dan sudah dinonaktifkan: tanpa ini,
        // jalur kandidatPemusnahan() tidak tereksplorasi sama sekali dan baseline
        // layar itu menipu (pengalaman 9 Okt: angkanya 5 query, padahal jalur
        // count-per-baris belum pernah jalan).
        for ($i = 1; $i <= self::JUMLAH_USANG; $i++) {
            $lama = SuratMasuk::forceCreate([
                'user_id' => $this->admin->id,
                'pengirim' => 'Pengirim lama '.$i,
                'klasifikasi_primer_id' => $primer->id,
                'nomor_surat' => 'UKUR-USANG'.$i,
                'perihal' => 'Perihal arsip lama '.$i,
                'tanggal_surat' => '2019-01-15',
                'tanggal_diterima' => '2019-01-20',
                'status_arsip' => 'inaktif',
                'sifat' => 'biasa',
            ]);

            Lampiran::forceCreate([
                'lampiranable_type' => SuratMasuk::class,
                'lampiranable_id' => $lama->id,
                'nama_file' => 'lama-'.$i.'.pdf',
                'disk' => 'arsip',
                'path' => 'uji/lama-'.$i.'.pdf',
            ]);
        }

        DB::connection()->enableQueryLog();
        DB::flushQueryLog();
    }

    protected function tearDown(): void
    {
        DB::flushQueryLog();
        DB::disableQueryLog();

        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    private function hitung(string $url, int $batas, string $label): array
    {
        // Bilik dulu: data sudah tertanam di setUp, yang kita hitung hanya
        // statement yang benar-benar diproduksi satu layar ini.
        //
        // Brand instansi (logo + nama di sidebar, dibaca layout di SETIAP halaman)
        // sengaja dipanaskan SEBELUM log dibuka. Dia cache-aside: permintaaan pertama
        // dalam satu proses membayar satu SELECT `pengaturan_instansi`, permintaan
        // berikutnya nol. Tanpa baris ini, tes pembanding "40 kandidat vs 80 kandidat"
        // jadi merah hanya karena urutan — yang 40 itu permintaan pertama di proses
        // ini (5 query), yang 80 sudah hangat (4 query). Yang diukur guardrail ini
        // memang keadaan tunak (steady state), bukan biaya dingin satu kali.
        PengaturanInstansi::untukTampilan();

        DB::flushQueryLog();

        $this->actingAs($this->admin)->get($url)->assertOk();

        $query = array_values(array_filter(
            array_map(fn (array $q): string => (string) $q['query'], DB::getQueryLog()),
            static function (string $q): bool {
                $kecil = strtolower($q);

                // Bukan pembacaan data: pragma introspeksi, dan begin/commit
                // yang dibuat RefreshDatabase di sekeliling tiap tes.
                return ! str_contains($kecil, 'pragma')
                    && ! str_contains($kecil, 'sqlite_master')
                    && ! preg_match('/^\s*(begin|commit|rollback)/', $kecil);
            }
        ));

        $jumlah = count($query);

        if (getenv('LAPOR_QUERY') === '1') {
            fwrite(STDOUT, "\n".$label.': '.$jumlah.' query (batas '.$batas.")\n");
            $this->logKesamaan($query);

            // `LAPOR_SEMUA=1` mencetak DAFTAR lengkapnya, bukan hanya yang
            // berulang. Diperlukan waktu ambang mau diturunkan: angka 11 tanpa
            // daftar isi tidak memberi tahu query mana yang boleh dibuang.
            if (getenv('LAPOR_SEMUA') === '1') {
                foreach ($query as $nomor => $isi) {
                    fwrite(STDOUT, '  '.($nomor + 1).'. '.mb_substr($isi, 0, 150)."\n");
                }
            }
        }

        $this->assertLessThanOrEqual(
            $batas,
            $jumlah,
            $label.' melewati ambang query — kemungkinan N+1 kembali masuk.'
        );

        return $query;
    }

    public function test_dashboard(): void
    {
        // BASELINE sebelum perapian: sembilan `count()` TERPISAH (dua di antaranya
        // `whereMonth()+whereYear()` = fungsi di atas kolom, index tidak terpakai)
        // + `KlasifikasiPrimer::all()` seisi tabel + dua agregasi klasifikasi.
        // Sesudah Fase 1: 13 — 2 agregat + 3 daftar + 4 eager load (terduplikasi
        // karena masuk & keluar dua koleksi terpisah) + 2 agregasi klasifikasi
        // + 1 daftar klasifikasi + 1 composer sidebar.
        $this->hitung(route('dashboard'), 15, 'dashboard');
    }

    public function test_daftar_surat_masuk(): void
    {
        // 6: 1 count pagination + 1 halaman + `with(primer,petugas)` + daftar
        // klasifikasi filter + composer sidebar.
        $this->hitung(route('surat-masuk.index'), 8, 'daftar surat masuk');
    }

    public function test_daftar_surat_masuk_dengan_kata_kunci(): void
    {
        $this->hitung(route('surat-masuk.index', ['cari' => 'perihal surat']), 8, 'daftar + cari');
    }

    public function test_daftar_gabungan(): void
    {
        // 10: count UNION + UNION + hidrasi per jenis (2) + eager load per jenis
        // (4) + filter klasifikasi + composer. Angkanya TIDAK tumbuh bersama
        // jumlah surat — itu yang penting, bukan berapa pas nilainya.
        $this->hitung(route('surat-masuk.index', ['cari' => 'perihal', 'jenis' => 'semua']), 12, 'mode gabungan');
    }

    public function test_halaman_show_surat_masuk(): void
    {
        // Surat YANG PUNYA LAMPIRAN. Fixture lama mengambil surat pertama
        // (id 1) yang kebetulan tidak punya berkas sama sekali, jadi layar ini
        // lulus dengan ambang longgar sementara N+1 per berkas (pengunggah +
        // status pengajuan hapus) tidak pernah terukur.
        $berkas = Lampiran::query()->where('lampiranable_type', SuratMasuk::class)->first();
        $id = $berkas->lampiranable_id;

        // 6: 1 baca surat + lampiran + pengajuan hapus + pengunggah + composer
        // + klasifikasi. Bertambahnya jumlah BERKAS pada satu surat tidak boleh
        // menambah query — itu yang diuji di sini.
        $ini = $this->hitung(route('surat-masuk.show', $id), 8, 'show surat masuk');
        $this->logKesamaan($ini);
    }

    public function test_laporan_index(): void
    {
        // 4. BASELINE sebelum Fase 1: layar yang sama MEMBACA SELURUH baris surat
        // di periode itu hanya untuk menampilkan jumlahnya (200 baris = 200
        // model di memori). Sekarang dua `COUNT(*)` di database.
        $this->hitung(route('laporan.index'), 6, 'laporan index');
    }

    public function test_pengajuan_hapus_lampiran_index(): void
    {
        // 6: 20 pengajuan, tiap baris memuat lampiran -> lampiranable, semuanya
        // lewat whereIn, bukan per baris.
        $this->hitung(route('pengajuan-hapus-lampiran.index'), 8, 'daftar pengajuan hapus');
    }

    public function test_form_pemusnahan_kandidat(): void
    {
        // 45 -> 4 (Fase 2, 9 Okt 2026). Yang hilang: 40 `select count(*) from
        // lampiran` (satu per baris kandidat) dan satu bacaan primer per kandidat
        // yang tidak pernah ditampilkan. Sisanya tetap: 1 item yang sudah
        // diantrikan + 1 surat masuk (dengan `withCount`) + 1 surat keluar + composer.
        $this->hitung(route('pemusnahan-arsip.create'), 6, 'form pemusnahan (kandidat)');
    }

    public function test_form_pemusnahan_tidak_tumbuh_bersama_isi_gudang(): void
    {
        // Angka di atas cuma bukti separuh. Yang penting: FORM-nya tidak boleh
        // menambah statement waktu arsip tuanya bertambah — itulah bentuk asli
        // bug N+1. 40 kandidat -> 4 query; 80 kandidat harus tetap 4.
        $sebelum = count($this->hitung(route('pemusnahan-arsip.create'), 60, 'kandidat 40'));

        for ($i = 1; $i <= self::JUMLAH_USANG; $i++) {
            SuratMasuk::forceCreate([
                'user_id' => $this->admin->id,
                'pengirim' => 'Pengirim lama tambahan '.$i,
                'klasifikasi_primer_id' => KlasifikasiPrimer::query()->value('id'),
                'nomor_surat' => 'UKUR-USANG-B'.$i,
                'perihal' => 'Perihal arsip lama tambahan '.$i,
                'tanggal_surat' => '2018-03-01',
                'tanggal_diterima' => '2018-03-05',
                'status_arsip' => 'inaktif',
                'sifat' => 'biasa',
            ]);
        }

        $sesudah = count($this->hitung(route('pemusnahan-arsip.create'), 60, 'kandidat 80'));

        $this->assertSame(
            $sebelum,
            $sesudah,
            'Jumlah query form pemusnahan tumbuh bersama jumlah arsip — N+1 kembali masuk.'
        );
    }

    public function test_log_aktivitas(): void
    {
        // 3: 1 count pagination + 1 halaman log (with user) + 1 daftar user untuk
        // dropdown filter.
        $this->hitung(route('aktivitas.index'), 5, 'log aktivitas');
    }

    /** Berkas lapornya: tampilkan pola query yang berulang (ciri N+1). */
    private function logKesamaan(array $query): void
    {
        if (getenv('LAPOR_QUERY') !== '1') {
            return;
        }

        $pola = [];

        foreach ($query as $q) {
            $kunci = preg_replace('/\d+/', '#', mb_substr($q, 0, 90));
            $pola[$kunci] = ($pola[$kunci] ?? 0) + 1;
        }

        arsort($pola);

        foreach (array_slice($pola, 0, 5, true) as $kunci => $jumlah) {
            if ($jumlah > 1) {
                fwrite(STDOUT, '  ULANG x'.$jumlah.' '.substr($kunci, 0, 80)."\n");
            }
        }
    }
}
