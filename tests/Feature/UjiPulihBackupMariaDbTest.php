<?php

namespace Tests\Feature;

use App\Console\Commands\BackupDatabaseCommand;
use App\Models\SuratMasuk;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\MariaDbHarness;

/**
 * Uji pulih (X5): buktikan `arsip:backup-db` menghasilkan berkas yang BENAR-BENAR
 * bisa membangun ulang database, bukan cuma berkas yang ukurannya kelihatan wajar.
 *
 * Kenapa kelas ini ada dan kenapa jalurnya dipotong jadi dua arah: seluruh project
 * ini belum pernah memulihkan satu pun cadangan. Cadangan yang tidak pernah
 * dicoba dipulihkan adalah asumsi — dan asumsi itu baru diuji pada hari datanya
 * hilang. Keputusan S7/X5 di AGENTS.md menyebut "tes restore wajib sebelum serah
 * terima"; tes ini yang membuatnya berarti, sekaligus jadi prosedur yang bisa
 * diulang petugas (dicatat di docs/manual-pemakaian.md Lampiran A.6).
 *
 * Yang dibuktikan:
 *  1. dump SUNGGUHAN dari command ini, nama skemanya diganti (prosedur pemulihan
 *     ke skema lain — tidak pernah menimpa database kantor untuk latihan), bisa
 *     di-import dan menghasilkan JUMLAH TABEL yang sama DAN jumlah baris per tabel
 *     yang sama dengan sumbernya;
 *  2. versi TERPOTONG dari dump yang sama TIDAK menghasilkan salinan yang utuh —
 *     kontrol negatif. Tanpa butir ini, tes nomor 1 bisa lolos karena server sudah
 *     kebetulan punya skema itu, dan "restore berhasil" tidak membuktikan apa-apa.
 *
 * Skip dengan pesan kalau MariaDB atau biner `mysql`/`mysqldump` tidak ada, sama
 * seperti kelas engine-asli lain (lihat Tests\MariaDbHarness).
 */
class UjiPulihBackupMariaDbTest extends MariaDbHarness
{
    private string $dir = '';

    private string $skemaPulih = '';

    private string $skemaCacat = '';

    private string $cnf = '';

    public function test_dump_yang_dibuat_perintah_ini_bisa_dipulihkan_utuh(): void
    {
        $mysql = $this->cariBiner('mysql');
        $mysqldump = $this->cariBiner('mysqldump');

        if ($mysql === null || $mysqldump === null) {
            $this->markTestSkipped(
                'Biner `mysql`/`mysqldump` tidak ditemukan — isi MYSQLDUMP_PATH di .env dan pastikan folder bin XAMPP ada di PATH.'
            );
        }

        $database = (string) Config::get('database.connections.mysql_test_a.database');
        $this->skemaPulih = $database.'_pulih';
        $this->skemaCacat = $database.'_cacat';
        $this->dir = storage_path('app/private/backup-uji-pulih');

        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }

        // Satu surat + lampiran catatan: observer sudah menulis baris `aktivitas`
        // saat surat ini dibuat, jadi ada dua tabel berisi data yang harus ikut.
        SuratMasuk::query()->create([
            'user_id' => $this->userId,
            'pengirim' => 'Kecamatan Uji Pulih',
            'klasifikasi_primer_id' => $this->primerId,
            'nomor_surat' => static::PENANDA.'-PULIH1',
            'perihal' => 'Perihal uji pulih sungguhan',
            'tanggal_surat' => '2026-06-06',
            'tanggal_diterima' => '2026-06-07',
            'sifat' => 'biasa',
            'status_arsip' => 'aktif',
        ]);

        Config::set('backup.mysqldump', $mysqldump);
        Config::set('backup.dir', $this->dir);

        $this->artisan('arsip:backup-db', ['--connection' => 'mysql_test_a', '--no-rotate' => true])
            ->expectsOutputToContain('Cadangan dibuat:')
            ->assertExitCode(0);

        $berkas = glob($this->dir.'/prd-*.sql') ?: [];
        $this->assertCount(1, $berkas, 'Uji pulih mengasumsikan satu dump di folder tes.');

        $isi = (string) file_get_contents($berkas[0]);

        // Pemulihan latihan tidak pernah menyentuh database kantor: nama skemanya
        // dipindah. Ini juga prosedur yang dicatat di manual untuk uji pulih rutin.
        $pindah = str_replace('`'.$database.'`', '`'.$this->skemaPulih.'`', $isi);
        $this->assertStringNotContainsString('USE `'.$database.'`', $pindah, 'Rewrite nama skema tidak terjadi — import bisa menimpa sumbernya.');

        $proses = $this->import($mysql, $pindah);
        $this->assertTrue($proses->isSuccessful(), 'Import dump sah gagal: '.trim($proses->getErrorOutput()));

        $this->assertSameSchema($database, $this->skemaPulih);

        // Baris yang ditanam tes harus terbaca di hasil pemulihan, lewat skema lain.
        $surat = DB::connection('mysql_test_a')->select(
            'SELECT nomor_surat FROM `'.$this->skemaPulih.'`.`surat_masuk` WHERE nomor_surat = ?',
            [static::PENANDA.'-PULIH1']
        );
        $this->assertCount(1, $surat, 'Surat yang ditanam sebelum dump tidak ikut terpulihkan.');

        // Kontrol negatif: dump yang sama, dipotong di tengah, tidak boleh
        // menghasilkan salinan yang setara. Kalau bagian ini lolos, tes di atas
        // cuma membuktikan sesuatu yang sudah ada di server.
        $berkasCacat = $this->dir.'/prd-potong.sql';
        file_put_contents($berkasCacat, substr($pindah, 0, (int) (strlen($pindah) * 0.6)));

        $periksa = (new BackupDatabaseCommand)->periksaDump($berkasCacat, $this->skemaPulih, null);
        $this->assertNotSame([], $periksa, 'Dump terpotong harus dikenali pemeriksaannya sendiri: '.implode(' ', $periksa));

        $pindahCacat = str_replace('`'.$this->skemaPulih.'`', '`'.$this->skemaCacat.'`', (string) file_get_contents($berkasCacat));

        // Import sebagian ini BOLEH gagal (potongan SQL memang rusak) — yang tidak
        // boleh adalah ia menghasilkan skema yang terlihat lengkap.
        $this->import($mysql, $pindahCacat);

        $jumlahUtuh = $this->jumlahTabel($this->skemaPulih);
        $jumlahCacat = $this->jumlahTabel($this->skemaCacat);

        $this->assertLessThan($jumlahUtuh, $jumlahCacat,
            "Salinan dari dump terpotong punya {$jumlahCacat} tabel, sumber punya {$jumlahUtuh} — kalau sama, tes ini tidak membuktikan apa pun.");
    }

    /**
     * Jumlah tabel dan jumlah baris per tabel harus identik antara sumber dan hasil
     * pemulihan. Perbandingan MENYELURUH begini yang membedakan "berkas ada" dari
     * "cadangan bisa dipakai".
     */
    private function assertSameSchema(string $sumber, string $hasil): void
    {
        $tabel = $this->daftarTabel($sumber);
        $this->assertNotEmpty($tabel);

        $this->assertSame($tabel, $this->daftarTabel($hasil), 'Daftar tabel hasil pemulihan berbeda dari sumber.');

        foreach ($tabel as $t) {
            $s = (int) (DB::connection('mysql_test_a')->select('SELECT COUNT(*) AS n FROM `'.$sumber.'`.`'.$t.'`')[0]->n ?? 0);
            $h = (int) (DB::connection('mysql_test_a')->select('SELECT COUNT(*) AS n FROM `'.$hasil.'`.`'.$t.'`')[0]->n ?? 0);

            $this->assertSame($s, $h, "Jumlah baris `{$t}` tidak sama setelah pemulihan (sumber {$s}, hasil {$h}).");
        }
    }

    /** @return list<string> */
    private function daftarTabel(string $skema): array
    {
        $rows = DB::connection('mysql_test_a')->select(
            "SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE' ORDER BY table_name",
            [$skema]
        );

        return array_map(static fn ($r): string => (string) $r->t, $rows);
    }

    private function jumlahTabel(string $skema): int
    {
        return count($this->daftarTabel($skema));
    }

    /**
     * Import lewat `mysql` CLI. Kredensial memakai file sementara, sama seperti
     * mysqldump-nya — password tidak pernah lewat baris perintah.
     */
    private function import(string $mysql, string $sql): Process
    {
        if ($this->cnf === '') {
            $konfig = (array) Config::get('database.connections.mysql_test_a');

            $this->cnf = sys_get_temp_dir().DIRECTORY_SEPARATOR.'prd-pulih-'.bin2hex(random_bytes(6)).'.cnf';
            file_put_contents($this->cnf, "[client]\nuser=".($konfig['username'] ?? '')
                ."\npassword=\"".str_replace(['\\', '"'], ['\\\\', '\"'], (string) ($konfig['password'] ?? ''))."\"\n"
                .'host='.($konfig['host'] ?? '127.0.0.1')."\nport=".($konfig['port'] ?? 3306)."\n");
        }

        $proses = new Process([$mysql, '--defaults-extra-file='.$this->cnf, '--default-character-set=utf8mb4']);
        $proses->setTimeout(300);
        $proses->setInput($sql);
        $proses->run();

        return $proses;
    }

    private function cariBiner(string $nama): ?string
    {
        $ditemukan = (new ExecutableFinder)->find($nama);

        if ($ditemukan !== null) {
            return $ditemukan;
        }

        $xampp = 'C:/xampp/mysql/bin/'.$nama.(DIRECTORY_SEPARATOR === '\\' ? '.exe' : '');

        return is_file($xampp) ? $xampp : null;
    }

    protected function tearDown(): void
    {
        if ($this->engineSiap) {
            foreach ([$this->skemaPulih, $this->skemaCacat] as $skema) {
                if ($skema !== '') {
                    DB::connection('mysql_test_a')->statement('DROP DATABASE IF EXISTS `'.$skema.'`');
                }
            }

            foreach (glob($this->dir.'/prd-*.sql') ?: [] as $f) {
                unlink($f);
            }

            if ($this->dir !== '') {
                @rmdir($this->dir);
            }

            if ($this->cnf !== '') {
                @unlink($this->cnf);
            }
        }

        $this->skemaPulih = '';
        $this->skemaCacat = '';
        $this->dir = '';
        $this->cnf = '';

        parent::tearDown();
    }
}
