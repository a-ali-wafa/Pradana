<?php

namespace Tests\Feature;

use App\Console\Commands\BackupDatabaseCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * `arsip:backup-db` — cadangan database kantor (P1 di docs/daftar-peningkatan.md,
 * dikerjakan 10 Okt 2026, pada hari yang sama MariaDB dev di laptop ini korup dan
 * tidak ada satu pun dump yang bisa dipakai untuk memulihkan).
 *
 * Yang dijaga di sini adalah lima cara perintah cadangan bisa menjadi ilusi:
 *  1. password dibocorkan lewat baris perintah (bisa dibaca siapa pun lewat Task
 *     Manager / `ps aux`) — kredensial lewat file 0600, dan tes ini membaca array
 *     argumen sungguhan, bukan cuma membaca kodenya;
 *  2. driver salah dianggap sukses — di suite (SQLite) perintah harus MENOLAK,
 *     bukan membuat berkas kosong lalu keluar 0;
 *  3. rotasi menghapus berkas yang bukan miliknya di folder yang sama;
 *  4. exit code 0 dianggap cadangan sah — mysqldump bisa keluar 0 dengan output
 *     terpotong, jadi ukuran + CREATE DATABASE + CREATE TABLE + footer diperiksa;
 *  5. bentuk yang ditulis biner sungguhannya tidak sama dengan bentuk yang ditulis
 *     dokumentasi — verifikasi lama hanya mengenali gaya MySQL 8 dan menolak dump
 *     MariaDB yang sah. Bug ini ditemukan oleh tes dump nyata, lihat
 *     `test_kedua_bentuk_create_database_diterima_dan_database_lain_ditolak`.
 *
 * Jalur mysqldump SUNGGUHAN ada di `BackupDatabaseMariaDbTest` (skip dengan pesan
 * kalau server/biner tidak ada), supaya lima guard di atas tetap terbaca hijau
 * saat XAMPP mati.
 */
class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private function command(): BackupDatabaseCommand
    {
        return new BackupDatabaseCommand;
    }

    private function dirUji(): string
    {
        return storage_path('app/private/backup-uji-'.substr(str_replace('.', '', (string) microtime(true)), -8));
    }

    public function test_password_tidak_pernah_masuk_baris_perintah(): void
    {
        Config::set('backup.mysqldump', 'mysqldump');
        Config::set('backup.sertakan_prosedur', true);
        Config::set('backup.sertakan_event', true);

        $argumen = $this->command()->perintahDump('C:/temp/prd-backup-abcd.cnf', 'pradana', 'mysql');
        $gabungan = implode(' ', $argumen);

        // Opsi kredensial harus jadi ARGUMEN PERTAMA setelah biner: mysqldump
        // mengabaikannya di posisi lain, dan hasilnya bukan cadangan tanpa izin
        // melainkan kegagalan yang terdengar misterius.
        $this->assertSame('--defaults-extra-file=C:/temp/prd-backup-abcd.cnf', $argumen[1]);

        $this->assertNotContains('-p', $argumen, 'Bentuk -pPASSWORD adalah kebocoran klasik.');
        $this->assertNotContains('--password', $argumen);
        $this->assertNotContains('--password=pradana', $argumen);

        $senjata = (string) Config::get('database.connections.mysql.password');

        if ($senjata !== '') {
            $this->assertStringNotContainsString($senjata, $gabungan, 'Password koneksi ada di dalam baris perintah.');
        }

        // Nama database selalu argumen terakhir -- karena dia mengikuti --databases.
        $this->assertSame('pradana', $argumen[count($argumen) - 1]);
        $this->assertContains('--databases', $argumen, 'Tanpa --databases, restore tidak menciptakan database-nya sendiri.');

        // Flag yang menentukan cadangan ini bisa dipercaya saat restore.
        $this->assertContains('--single-transaction', $argumen, 'Tanpa ini tabel dikunci selama dump, atau hasilnya tidak konsisten.');
        $this->assertContains('--routines', $argumen, 'Prosedur hilang dari cadangan tanpa ini.');
        $this->assertContains('--events', $argumen);
        $this->assertContains('--triggers', $argumen);
    }

    public function test_flag_prosedur_dan_event_bisa_dimatikan_saat_hosting_menolak(): void
    {
        // Shared hosting (K1=a) sering menolak `--routines`/`--events` karena dua
        // privilege di luar database kantor. Skema PRADANA hari ini tidak punya
        // keduanya, jadi cadangan harus tetap bisa dibuat tanpa memaksa orang
        // menaikkan privilege yang memang bukan miliknya.
        Config::set('backup.sertakan_prosedur', false);
        Config::set('backup.sertakan_event', false);

        $argumen = $this->command()->perintahDump('/tmp/x.cnf', 'pradana', 'mysql');

        $this->assertNotContains('--routines', $argumen);
        $this->assertNotContains('--events', $argumen);

        // Yang tidak boleh ikut hilang saat mematikan keduanya.
        foreach (['--defaults-extra-file=/tmp/x.cnf', '--single-transaction', '--quick', '--databases'] as $wajib) {
            $this->assertContains($wajib, $argumen, "Flag `{$wajib}` bukan opsional — cadangan tanpa ini tidak bisa dipulihkan.");
        }

        Config::set('backup.sertakan_prosedur', true);
        Config::set('backup.sertakan_event', true);
    }

    public function test_menolak_saat_koneksi_bukan_mysql(): void
    {
        // Suite jalan di SQLite. Perintah yang diam-diam "berhasil" pada driver yang
        // salah adalah cadangan yang tidak ada.
        $this->artisan('arsip:backup-db')
            ->expectsOutputToContain('Perintah ini khusus MySQL/MariaDB')
            ->assertExitCode(1);
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        $dir = $this->dirUji();
        Config::set('backup.dir', $dir);

        $jalankan = $this->artisan('arsip:backup-db', ['--connection' => 'mysql', '--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Tidak ada satu berkas pun yang ditulis.')
            ->assertExitCode(0);

        // Folder tujuan DAN nama database harus terbaca di rencananya — kalau tidak,
        // "[dry-run] aman" tidak memberitahu apa-apa tentang apa yang akan ditulis.
        $jalankan->expectsOutputToContain($dir)->assertExitCode(0);

        $this->assertDirectoryDoesNotExist($dir, 'dry-run tetap membuat folder = dry-run yang berbohong.');
    }

    public function test_rotasi_hanya_membuang_dump_yang_dibuat_perintah_ini(): void
    {
        $dir = $this->dirUji();
        mkdir($dir, 0775, true);

        $tua = $dir.'/prd-2026-01-01-020000.sql';
        $baru = $dir.'/prd-'.date('Y-m-d-His').'.sql';
        $milikOrangLain = $dir.'/manual-petugas-2026.sql';
        $namaAneh = $dir.'/prd-bukan-tanggal.sql';

        foreach ([$tua, $baru, $milikOrangLain, $namaAneh] as $f) {
            file_put_contents($f, '-- uji');
        }

        touch($tua, time() - 200 * 86400);
        touch($milikOrangLain, time() - 200 * 86400);
        touch($namaAneh, time() - 200 * 86400);

        $dibuang = $this->command()->rotasi($dir, 30);

        $this->assertSame(1, $dibuang, 'Rotasi harus membuang TEPAT SATU berkas: dump lama miliknya sendiri.');
        $this->assertFileDoesNotExist($tua);
        $this->assertFileExists($baru, 'Dump segar tidak boleh ikut terbuang.');
        $this->assertFileExists($milikOrangLain, 'Dump manual petugas di folder yang sama tidak boleh disentuh.');
        $this->assertFileExists($namaAneh, 'Nama yang tidak cocok pola bukan milik perintah ini.');

        foreach ([$baru, $milikOrangLain, $namaAneh] as $f) {
            unlink($f);
        }

        rmdir($dir);
    }

    public function test_dump_terpotong_ditolak_sebelum_disebut_cadangan(): void
    {
        $dir = $this->dirUji();
        mkdir($dir, 0775, true);

        $sampah = $dir.'/prd-sampah.sql';
        file_put_contents($sampah, '-- mysqldump of nothing at all');

        $masalah = implode(' ', $this->command()->periksaDump($sampah, 'pradana'));
        $this->assertStringContainsString('kosong', $masalah, 'Dump 29 byte tidak boleh lolos sebagai cadangan.');
        $this->assertStringContainsString('CREATE TABLE', $masalah);
        $this->assertStringContainsString('Dump completed', $masalah);

        unlink($sampah);
        rmdir($dir);
    }

    /**
     * Regression yang dibuktikan biner sungguhan, bukan dibaca dari dokumen:
     * MariaDB 10.4 membungkus "IF NOT EXISTS" di dalam komentar versi, sedangkan
     * MySQL 8 menulisnya sebagai teks biasa. Verifikasi lama menuntut bentuk MySQL 8
     * dan MENOLAK dump sungguhan hasil mysqldump MariaDB — cadangan yang sebenarnya
     * sah dilaporkan gagal. (Tulisan aslinya tidak bisa dikutip di sini: tanda
     * penutup komentar versi itu menutup docblock-nya sendiri, dan PHP membacanya
     * sebagai kode — error sintaks, bukan komentar.)
     */
    public function test_kedua_bentuk_create_database_diterima_dan_database_lain_ditolak(): void
    {
        $dir = $this->dirUji();
        mkdir($dir, 0775, true);

        $mariadb = $this->dumpSintetis($dir, 'prd-mariadb.sql',
            'CREATE DATABASE /*!32312 IF NOT EXISTS*/ `pradana` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;');
        $mysql = $this->dumpSintetis($dir, 'prd-mysql.sql',
            'CREATE DATABASE IF NOT EXISTS `pradana` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;');

        $this->assertSame([], $this->command()->periksaDump($mariadb, 'pradana'), 'Dump bentuk MariaDB harus diterima.');
        $this->assertSame([], $this->command()->periksaDump($mysql, 'pradana'), 'Dump bentuk MySQL harus diterima.');

        // Nama database harus cocok: dump dari database lain (mis. sisa pengukuran
        // yang kebetulan ada di folder yang sama) tidak boleh dipakai memulihkan kantor.
        $asing = $this->dumpSintetis($dir, 'prd-asing.sql',
            'CREATE DATABASE /*!32312 IF NOT EXISTS*/ `pradana_test` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;');
        $masalah = $this->command()->periksaDump($asing, 'pradana');
        $this->assertNotEmpty($masalah, 'Dump milik database lain tidak boleh lolos.');
        $this->assertStringContainsString('CREATE DATABASE', implode(' ', $masalah));

        foreach ([$mariadb, $mysql, $asing] as $f) {
            unlink($f);
        }

        rmdir($dir);
    }

    public function test_dump_yang_kehilangan_tabel_ditolak(): void
    {
        // Ini kegagalan yang paling bahaya: exit code 0, ukuran wajar, footer ada,
        // tapi isinya cuma sebagian struktur — misalnya setelah database sempat
        // korup. Rujukan jumlahnya diambil dari parameter (di produksi dari
        // information_schema), supaya tes ini tidak butuh server.
        $dir = $this->dirUji();
        mkdir($dir, 0775, true);

        $sebagian = $this->dumpSintetis($dir, 'prd-sebagian.sql',
            'CREATE DATABASE /*!32312 IF NOT EXISTS*/ `pradana` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;', 2);

        $masalah = implode(' ', $this->command()->periksaDump($sebagian, 'pradana', 14));
        $this->assertStringContainsString('dump tidak lengkap', $masalah);
        $this->assertStringContainsString('2 CREATE TABLE', $masalah);
        $this->assertStringContainsString('14 tabel', $masalah);

        // Jumlah yang sama, dan yang lebih banyak (view menulis tabel dummy), harus lolos.
        $ini = $this->dumpSintetis($dir, 'prd-cukup.sql',
            'CREATE DATABASE /*!32312 IF NOT EXISTS*/ `pradana` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;', 2);
        $this->assertSame([], $this->command()->periksaDump($ini, 'pradana', 2), 'Jumlah cocok tidak boleh dianggap bermasalah.');
        $this->assertSame([], $this->command()->periksaDump($ini, 'pradana', 1), 'Tabel tambahan (dummy view) bukan alasan menolak.');
        $this->assertSame([], $this->command()->periksaDump($ini, 'pradana', null), 'null = pemeriksaan jumlah memang dilewati.');

        foreach ([$sebagian, $ini] as $f) {
            unlink($f);
        }

        rmdir($dir);
    }

    public function test_koneksi_yang_tidak_dikenal_ditolak(): void
    {
        $this->artisan('arsip:backup-db', ['--connection' => 'tidak_ada'])
            ->expectsOutputToContain('tidak dikenal')
            ->assertExitCode(1);
    }

    public function test_nama_dump_ikut_pola_yang_rotasi_kenal(): void
    {
        // Nama berkas dan pola rotasi hidup di dua tempat yang berbeda dalam satu
        // command; kalau mereka lari sendiri, dump tidak akan pernah dibuang dan
        // disk hosting penuh. Tes ini mengunci keduanya tetap satu bentuk.
        $nama = 'prd-'.date('Y-m-d-His').'.sql';

        $this->assertSame(1, preg_match(BackupDatabaseCommand::POLA_DUMP, $nama));
    }

    /**
     * Dump sintetis yang cukup besar untuk melewati batas ukuran minimum, dengan
     * jumlah CREATE TABLE yang bisa diatur.
     */
    private function dumpSintetis(string $dir, string $nama, string $createDb, int $jumlahTabel = 2): string
    {
        $path = $dir.'/'.$nama;
        $isi = "-- MySQL dump 10.19\n".$createDb."\nUSE `pradana`;\n";

        for ($i = 0; $i < $jumlahTabel; $i++) {
            $isi .= 'DROP TABLE IF EXISTS `t_'.$i."`;\nCREATE TABLE `t_".$i.'` (`id` bigint unsigned NOT NULL) ENGINE=InnoDB;\n'
                .'INSERT INTO `t_'.$i."` VALUES (1);\n";
        }

        $isi .= str_repeat("-- pengisi supaya ukuran melewati batas minimum dump sah\n", 80);
        $isi .= "-- Dump completed on 2026-10-10 17:00:00\n";

        file_put_contents($path, $isi);

        return $path;
    }
}
