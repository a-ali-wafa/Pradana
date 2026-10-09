<?php

namespace Tests;

use App\Models\KlasifikasiPrimer;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Plumbing untuk tes yang harus jalan di MariaDB SUNGGUHAN (L-24 / Q1=b).
 *
 * Kenapa kelas ini ada: suite utama berjalan di SQLite in-memory, dan ada bentuk
 * SQL yang TIDAK bisa dibuktikan dari sana — `LIKE ? ESCAPE '!'`, `CASE` di
 * ORDER BY, UNION dua kaki berparameter, `SUM(CASE WHEN …)` agregat dashboard,
 * dan kolom DATE yang memotong jam. Semua itu baru menabrak produksi di server
 * kantor, jadi jalur-jalurnya diuji di engine aslinya.
 *
 * Yang dilakukan kelas ini (dan hanya ini — builder surat tetap di subclass
 * karena tiap kelompok tes butuh isi baris yang berbeda):
 * 1. KALAU MariaDB mati, tes di-SKIP dengan pesan yang jelas, BUKAN lulus semu.
 *    Ini penting karena `php artisan test` yang hijau bisa saja berarti XAMPP
 *    sedang tidak jalan.
 * 2. Membangun skema `mysql_test_a` lewat migration asli (bukan `migrate:fresh`,
 *    supaya data kantor yang kebetulan ada di DB lain tidak pernah disentuh).
 * 3. Membersihkan baris uji lewat PREFIX nomor surat + kode klasifikasi khusus,
 *    jadi database tes tetap bisa dipakai ulang.
 *
 * `NomorSuratKeluarTest` sengaja TIDAK memakai kelas ini: dia butuh DUA koneksi
 * sekaligus (mysql_test_a & mysql_test_b) untuk membuktikan lockForUpdate().
 */
abstract class MariaDbHarness extends TestCase
{
    /** Prefix nomor surat semua baris uji; dipakai untuk bersih-bersih. */
    protected const PENANDA = 'MJD';

    /** Kode klasifikasi primer khusus harness — ikut dihapus saat bersih-bersih. */
    protected const KODE_PRIMER = 'ZD';

    protected int $primerId;

    protected int $userId;

    public static function setUpBeforeClass(): void
    {
        // Facade `DB::` BELUM bisa dipakai di sini: setUpBeforeClass jalan sebelum
        // aplikasi Laravel dibuat (`CreatesApplication::setUp()` yang menghidupkan
        // facade root) — memakai DB:: di sini melempar "A facade root has not been
        // set". Karena itu koneksi PDO mentah, sama seperti aslinya.
        try {
            $pdo = new \PDO(
                'mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';port='.(getenv('DB_PORT') ?: '3306'),
                getenv('DB_USERNAME') ?: 'root',
                getenv('DB_PASSWORD') ?: '',
            );
        } catch (\Throwable) {
            return; // markTestSkipped() di setUp(), di sini belum ada test case
        }

        $namaDb = preg_replace('/[^A-Za-z0-9_]/', '', getenv('DB_TEST_DATABASE') ?: 'pradana_test');

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$namaDb}` CHARACTER SET utf8mb4");
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! static::mariaHidup()) {
            $this->markTestSkipped(
                'MariaDB (XAMPP) tidak berjalan — tes engine-asli di '.static::class.' dilewati. '.
                'Nyalakan MySQL di XAMPP lalu jalankan: php artisan test --filter='.class_basename(static::class)
            );
        }

        Artisan::call('migrate', ['--database' => 'mysql_test_a', '--force' => true]);

        config(['database.default' => 'mysql_test_a']);

        $this->bersihkan();

        $this->primerId = KlasifikasiPrimer::query()->create([
            'kode' => static::KODE_PRIMER,
            'nama' => 'Klasifikasi Uji Engine',
        ])->id;

        $petugas = User::query()->firstOrCreate(
            ['email' => 'petugas.harness@example.test'],
            [
                'nama_lengkap' => 'Petugas Uji Engine',
                'pin' => bcrypt('12345678'),
                'role' => 'pegawai',
            ]
        );

        $this->userId = $petugas->id;
    }

    protected function tearDown(): void
    {
        $this->bersihkan();

        config(['database.default' => 'mysql']);

        parent::tearDown();
    }

    protected static function mariaHidup(): bool
    {
        try {
            DB::connection('mysql_test_a')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Buang semua baris yang ditinggalkan tes dengan PENANDA kelas ini. */
    protected function bersihkan(): void
    {
        $pola = static::PENANDA.'-%';

        $idMasuk = SuratMasuk::query()->where('nomor_surat', 'like', $pola)->pluck('id')->all();

        if ($idMasuk !== []) {
            DB::table('lampiran')
                ->where('lampiranable_type', SuratMasuk::class)
                ->whereIn('lampiranable_id', $idMasuk)
                ->delete();

            SuratMasuk::query()->whereIn('id', $idMasuk)->forceDelete();
        }

        $idKeluar = SuratKeluar::query()->where('nomor_surat', 'like', $pola)->pluck('id')->all();

        if ($idKeluar !== []) {
            DB::table('draf_konten_surat_keluar')->whereIn('surat_keluar_id', $idKeluar)->delete();

            DB::table('lampiran')
                ->where('lampiranable_type', SuratKeluar::class)
                ->whereIn('lampiranable_id', $idKeluar)
                ->delete();

            SuratKeluar::query()->whereIn('id', $idKeluar)->forceDelete();
        }

        // Lampiran ditulis lebih dulu (sebelum suratnya dihapus) karena relasinya
        // morph: FK-nya tidak bisa dipakai untuk membersihkan silang. File fisik
        // tidak pernah dibuat tes ini — hanya baris tabelnya.
        KlasifikasiPrimer::query()->where('kode', static::KODE_PRIMER)->delete();
    }
}
