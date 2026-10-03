<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Services\NomorSuratKeluarGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * L-24/Q1: tes yang berjalan di MariaDB asli (bukan SQLite in-memory), khusus
 * untuk jalur paling kritis: nomor surat resmi tidak boleh kembar.
 *
 * Pakai database terpisah (DB_TEST_DATABASE, default `pradana_test`) supaya
 * data development user tidak tersentuh. Kalau MariaDB tidak jalan, seluruh
 * tes di kelas ini di-skip dengan pesan yang jelas — bukan lulus semu.
 */
class NomorSuratKeluarTest extends TestCase
{
    private const TAHUN_UJI = 2031;

    private int $primerId;

    public static function setUpBeforeClass(): void
    {
        // Buat database uji kalau belum ada (root PDO tanpa memilih database).
        try {
            $pdo = new \PDO(
                'mysql:host='.(getenv('DB_HOST') ?: '127.0.0.1').';port='.(getenv('DB_PORT') ?: '3306'),
                getenv('DB_USERNAME') ?: 'root',
                getenv('DB_PASSWORD') ?: '',
            );
        } catch (\Throwable $e) {
            return; // dilewati di setUp() dengan pesan skip
        }

        $namaDb = preg_replace('/[^A-Za-z0-9_]/', '', getenv('DB_TEST_DATABASE') ?: 'pradana_test');
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$namaDb}` CHARACTER SET utf8mb4");
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->mariaHidup()) {
            $this->markTestSkipped('MariaDB (XAMPP) tidak berjalan — tes konkurensi penomoran dilewati.');
        }

        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--database' => 'mysql_test_a',
            '--force' => true,
        ]);

        config(['database.default' => 'mysql_test_a']);

        $this->bersihkanTahunUji();

        $this->primerId = KlasifikasiPrimer::query()->create(['kode' => 'Z9', 'nama' => 'Uji Penomoran'])->id;

        // Database uji baru = tabel users kosong, padahal surat_keluar.user_id
        // punya FK. Butuh satu petugas.
        if (DB::table('users')->count() === 0) {
            DB::table('users')->insert([
                'nama_lengkap' => 'Petugas Uji',
                'email' => 'petugas.uji@example.test',
                'pin' => bcrypt('12345678'),
                'role' => 'perangkat',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        config(['database.default' => 'mysql']);

        parent::tearDown();
    }

    public function test_nomor_naik_berurutan_dan_formatnya_benar(): void
    {
        $nomor = [];

        foreach (range(1, 3) as $i) {
            $nomor[] = DB::transaction(fn () => app(NomorSuratKeluarGenerator::class)
                ->generate(self::TAHUN_UJI.'-02-1'.$i, $this->primerId));
        }

        $this->assertSame([
            '001/Z9/II/'.self::TAHUN_UJI,
            '002/Z9/II/'.self::TAHUN_UJI,
            '003/Z9/II/'.self::TAHUN_UJI,
        ], $nomor);
    }

    /**
     * Inti perbaikan 4 Okt 2026: koneksi kedua yang mencoba mengambil nomor
     * harus MENUNGGU transaksi pertama commit. Ini yang membuktikan
     * lockForUpdate() bekerja — sebelumnya SELECT biasa membaca snapshot dan
     * kedua request mendapat angka yang sama.
     */
    public function test_koneksi_kedua_terkunci_sampai_transaksi_pertama_selesai(): void
    {
        $a = DB::connection('mysql_test_a');
        $b = DB::connection('mysql_test_b');

        $a->beginTransaction();

        // Semua di dalam try/finally: kalau salah satu assert gagal, transaksi A
        // yang memegang lock baris counter HARUS tetap ditutup — kalau tidak,
        // tes berikutnya menggantung di "Lock wait timeout" dan hasilnya jadi
        // kekacauan, bukan kegagalan yang bisa dibaca.
        try {
            $nomorPertama = app(NomorSuratKeluarGenerator::class)
                ->generate(self::TAHUN_UJI.'-03-01', $this->primerId);

            $terblokir = false;
            config(['database.default' => 'mysql_test_b']);
            $b->beginTransaction();
            $b->statement('SET SESSION innodb_lock_wait_timeout = 1');

            try {
                app(NomorSuratKeluarGenerator::class)->generate(self::TAHUN_UJI.'-03-01', $this->primerId);
            } catch (QueryException $e) {
                $terblokir = str_contains($e->getMessage(), 'Lock wait timeout');
            }

            $this->assertSame('001/Z9/III/'.self::TAHUN_UJI, $nomorPertama);
            $this->assertTrue(
                $terblokir,
                'Koneksi kedua tidak terkunci oleh baris counter — lockForUpdate() tidak efektif.'
            );
        } finally {
            $b->statement('SET SESSION innodb_lock_wait_timeout = 50');
            if ($b->transactionLevel() > 0) {
                $b->rollBack();
            }

            config(['database.default' => 'mysql_test_a']);

            if ($a->transactionLevel() > 0) {
                $a->commit();
            }
        }

        // Setelah commit, koneksi kedua baru boleh dapat nomor berikutnya (bukan nomor yang sama).
        $nomorKedua = DB::transaction(fn () => app(NomorSuratKeluarGenerator::class)
            ->generate(self::TAHUN_UJI.'-03-02', $this->primerId));

        $this->assertSame('002/Z9/III/'.self::TAHUN_UJI, $nomorKedua);
        $this->assertNotSame($nomorPertama, $nomorKedua);
    }

    public function test_nomor_surat_yang_disimpan_ke_table_selalu_unik(): void
    {
        $nomor = [];

        foreach (range(1, 5) as $i) {
            $nomor[] = DB::transaction(function () use ($i) {
                $n = app(NomorSuratKeluarGenerator::class)->generate(self::TAHUN_UJI.'-04-0'.$i, $this->primerId);

                DB::table('surat_keluar')->insert([
                    'user_id' => DB::table('users')->value('id') ?? 1,
                    'penerima' => 'Penerima uji '.$i,
                    'klasifikasi_primer_id' => $this->primerId,
                    'sifat' => 'biasa',
                    'nomor_surat' => $n,
                    'perihal' => 'Perihal uji '.$i,
                    'tanggal_surat' => self::TAHUN_UJI.'-04-0'.$i,
                    'status_berkas' => 'asli',
                    'status_arsip' => 'aktif',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $n;
            });
        }

        $this->assertCount(5, array_unique($nomor));
        $this->assertSame(5, DB::table('surat_keluar')->where('nomor_surat', 'like', '%/'.self::TAHUN_UJI)->count());
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

    private function bersihkanTahunUji(): void
    {
        DB::table('surat_keluar')->where('nomor_surat', 'like', '%/'.self::TAHUN_UJI)->delete();
        DB::table('surat_counters')->where('tahun', self::TAHUN_UJI)->delete();
        DB::table('klasifikasi_primer')->where('kode', 'Z9')->delete();
    }
}
