<?php

namespace Tests\Feature;

use App\Console\Commands\BackupDatabaseCommand;
use App\Models\SuratMasuk;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Tests\MariaDbHarness;

/**
 * Jalur SUNGGUHAN `arsip:backup-db`: mysqldump dijalankan betulan terhadap MariaDB.
 *
 * Kelas terpisah dari BackupDatabaseTest karena dua alasan: (1) dia butuh
 * `MariaDbHarness` (koneksi `mysql_test_a` + skema asli), dan (2) skip-nya tidak
 * boleh mengaburkan empat guard di kelas itu saat XAMPP mati — cadangan yang
 * "lulus tes" tanpa pernah benar-benar meng-dump adalah cara paling tenang untuk
 * sampai di server kantor tanpa cadangan sama sekali.
 *
 * Ini satu-satunya tes di project yang membuktikan hasil mysqldump bisa dibaca
 * kembali sebagai struktur + data, bukan cuma bahwa exit code-nya 0.
 */
class BackupDatabaseMariaDbTest extends MariaDbHarness
{
    private string $dir = '';

    public function test_dump_sungguhan_membawa_struktur_dan_isinya(): void
    {
        $dump = (new ExecutableFinder)->find('mysqldump')
            ?? (is_file('C:/xampp/mysql/bin/mysqldump.exe') ? 'C:/xampp/mysql/bin/mysqldump.exe' : null);

        if ($dump === null) {
            $this->markTestSkipped('Biner `mysqldump` tidak ditemukan — jalankan dengan MYSQLDUMP_PATH yang benar.');
        }

        Config::set('backup.mysqldump', $dump);

        $this->dir = storage_path('app/private/backup-uji-mariadb');

        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }

        foreach (glob($this->dir.'/prd-*.sql') ?: [] as $lama) {
            unlink($lama);
        }

        // Satu surat sungguhan, supaya "INSERT INTO" bukan sekadar asumsi.
        SuratMasuk::query()->create([
            'user_id' => $this->userId,
            'pengirim' => 'Kecamatan Uji Dump',
            'klasifikasi_primer_id' => $this->primerId,
            'nomor_surat' => static::PENANDA.'-DUMP1',
            'perihal' => 'Perihal uji dump sungguhan',
            'tanggal_surat' => '2026-05-05',
            'tanggal_diterima' => '2026-05-06',
            'sifat' => 'biasa',
            'status_arsip' => 'aktif',
        ]);

        Config::set('backup.dir', $this->dir);

        $this->artisan('arsip:backup-db', ['--connection' => 'mysql_test_a', '--no-rotate' => true])
            ->expectsOutputToContain('Cadangan dibuat:')
            ->assertExitCode(0);

        $berkas = glob($this->dir.'/prd-*.sql') ?: [];
        $this->assertNotEmpty($berkas, 'Perintah keluar 0 tapi tidak ada satu berkas pun di folder cadangan.');

        $isi = (string) file_get_contents($berkas[0]);
        $database = (string) Config::get('database.connections.mysql_test_a.database');

        // Ditulis sebagai pencarian baris, BUKAN sebagai satu string persis: MariaDB
        // mengeluarkan `CREATE DATABASE /*!32312 IF NOT EXISTS*/ \`db\`` dan MySQL 8
        // `CREATE DATABASE IF NOT EXISTS \`db\``. Asumsi lama ("coba cocokkan teks
        // MySQL") menolak dump sah hasil biner di laptop ini — itulah bug yang
        // ditangkap tes nyata ini pada 10 Okt 2026.
        $baris = array_values(array_filter(
            explode("\n", $isi),
            fn (string $s): bool => str_starts_with($s, 'CREATE DATABASE')
        ));

        $this->assertNotEmpty($baris, 'Dump tidak menciptakan database-nya sendiri.');
        $this->assertStringContainsString('IF NOT EXISTS', (string) $baris[0]);
        $this->assertStringContainsString('`'.$database.'`', (string) $baris[0],
            'Database yang diciptakan bukan yang dicadangkan.');
        $this->assertStringContainsString('USE `'.$database.'`', $isi);
        $this->assertStringContainsString('CREATE TABLE `surat_masuk`', $isi,
            'Dump harus membawa struktur, bukan hanya data.');
        $this->assertMatchesRegularExpression('/INSERT INTO `surat_masuk` VALUES/i', $isi,
            'Baris yang baru ditanam tidak ikut terbawa.');
        $this->assertStringContainsString(static::PENANDA.'-DUMP1', $isi);
        $this->assertStringContainsString('-- Dump completed', $isi, 'Footer hilang = dump terpotong.');

        $this->assertSame([], $this->panggilPeriksa($berkas[0]), 'Dump sungguhan harus lolos pemeriksaan mutunya sendiri.');

        foreach ($berkas as $f) {
            unlink($f);
        }
    }

    private function panggilPeriksa(string $path): array
    {
        $command = new BackupDatabaseCommand;
        $database = (string) Config::get('database.connections.mysql_test_a.database');

        // Nama database sengaja disebut: `getTables()` tanpa argumen mengembalikan
        // semua tabel yang terlihat di SERVER (79 di laptop ini), bukan tabel
        // koneksi ini (15) — dan itu membuat backup yang sah dituduh tidak lengkap.
        $tabel = count(DB::connection('mysql_test_a')->getSchemaBuilder()->getTables($database));

        $this->assertGreaterThanOrEqual(14, $tabel, 'Skema tes tidak utuh — hasil pemeriksaan dump tidak berarti.');

        return $command->periksaDump($path, $database, $tabel);
    }

    protected function tearDown(): void
    {
        if ($this->engineSiap && $this->dir !== '') {
            foreach (glob($this->dir.'/prd-*.sql') ?: [] as $f) {
                unlink($f);
            }

            @rmdir($this->dir);
        }

        parent::tearDown();
    }
}
