<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * `arsip:backup-db` — cadangkan database ke berkas SQL di luar `public/`.
 *
 * Kenapa perintah ini ada (P1 di docs/daftar-peningkatan.md, dikerjakan 10 Okt 2026):
 * `arsip:sinkron-ke-drive` mengurus LAMPIRAN, tapi isi database (13 tabel: surat,
 * klasifikasi, log aktivitas, pemusnahan + Berita Acara) belum punya jalur cadangan
 * otomatis sama sekali — cuma catatan `mysqldump` manual di Lampiran A. Desakan
 * perintah ini bukan teori: hari ini MariaDB di mesin pengembangan korup setelah
 * mati listrik paksa dan seluruh datadir-nya harus diselamatkan dengan salinan
 * manual yang kebetulan ada. Cadangan yang tidak pernah dibuat = cadangan yang
 * tidak ada.
 *
 * Keputusan teknis yang tidak bisa ditebak dari kode:
 *
 * 1. **Password TIDAK pernah lewat baris perintah.** Di Windows dan Linux, baris
 *    perintah proses yang sedang berjalan bisa dibaca siapa pun (`tasklist /v`,
 *    `ps aux`, Task Manager detail). Kredensial ditulis ke file `defaults-extra-file`
 *    sementara (0600, lalu dihapus di `finally`) dan hanya PATH file itu yang masuk
 *    ke argumen. Ada tes yang menuntut `--defaults-extra-file` muncul dan password
 *    tidak muncul di array argumen.
 * 2. **`--single-transaction`** supaya dump konsisten tanpa mengunci tabel: InnoDB
 *    (S2=a, MariaDB) mendukungnya, dan kantor tetap bisa input surat saat cron
 *    jalan tengah malam. `--lock-tables` sengaja tidak dipakai — mengunci tabel
 *    tersibuk di jam kerja justru merusak pemakaian.
 * 3. **`--databases` wajib, `--routines`/`--events` bisa dimatikan**: tanpa
 *    `--databases`, restore masuk ke schema yang sedang dipilih dan tidak
 *    menciptakan database-nya sendiri. Dua flag pelengkap itu sengaja dibuat
 *    konfigurabel karena MariaDB menuntut privilege di luar database kantor untuk
 *    membacanya — dan hari ini skema PRADANA tidak punya prosedur maupun event
 *    sama sekali (alasannya ada di `config/backup.php`).
 * 4. **Rotasi mengenal polanya sendiri.** Berkas lain di folder yang sama (mis.
 *    dump manual petugas) tidak pernah dihapus — perintah yang menghapus apa pun di
 *    luar jejaknya sendiri adalah cara tercepat menghancurkan cadangan orang lain.
 * 5. **Verifikasi bukan percaya exit code 0.** mysqldump bisa keluar 0 sambil
 *    menghasilkan berkas kosong atau terpotong; karena itu ukuran, baris
 *    `CREATE DATABASE` untuk database yang benar, JUMLAH `CREATE TABLE` dibanding
 *    tabel sungguhan di information_schema, dan footer `-- Dump completed`
 *    diperiksa. Kegagalan verifikasi = backup gagal, berkasnya dibuang — bukan
 *    peringatan, karena "cadangan" yang tidak bisa dipulihkan lebih berbahaya
 *    daripada tidak ada cadangan sama sekali (orang berhenti cek).
 */
class BackupDatabaseCommand extends Command
{
    protected $signature = 'arsip:backup-db
                            {--connection= : Koneksi yang dicadangkan (default: koneksi aktif)}
                            {--retain= : Jumlah hari dump disimpan (default: config backup.simpan_hari)}
                            {--dry-run : Tunjukkan rencananya, jangan menulis apa pun}
                            {--no-rotate : Buat dump, tapi jangan buang dump lama}';

    protected $description = 'Cadangkan database kantor ke berkas SQL di folder privat (rotasi otomatis)';

    /** Pola berkas yang BOLEH dihapus rotasi — tidak ada file lain yang disentuh. */
    public const POLA_DUMP = '/^prd-(\d{4}-\d{2}-\d{2}-\d{6})\.sql$/';

    public function handle(): int
    {
        $koneksi = (string) ($this->option('connection') ?: Config::get('database.default'));
        $konfig = Config::get("database.connections.{$koneksi}");

        if (! is_array($konfig)) {
            $this->error("Koneksi `{$koneksi}` tidak dikenal — periksa config/database.php.");

            return self::FAILURE;
        }

        $driver = (string) ($konfig['driver'] ?? '');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->componentError($driver, $koneksi);

            return self::FAILURE;
        }

        $biner = (string) config('backup.mysqldump');
        $database = (string) ($konfig['database'] ?? '');
        $dir = (string) config('backup.dir');
        $hari = (int) ($this->option('retain') ?: config('backup.simpan_hari'));

        if ($this->option('dry-run')) {
            $this->info('[dry-run] Tidak ada satu berkas pun yang ditulis.');
            $this->line('  mysqldump  : '.$biner.' ('.$this->temukan($biner).')');
            $this->line('  database   : '.$database);
            $this->line('  tujuan     : '.$dir);
            $this->line('  rotasi     : '.($this->option('no-rotate') ? 'mati' : "buang dump berumur > {$hari} hari"));

            return self::SUCCESS;
        }

        if (! $this->eksis($biner)) {
            $this->error("Berkas `{$biner}` tidak ditemukan. Isi MYSQLDUMP_PATH di .env dengan path absolut, misalnya C:\\xampp\\mysql\\bin\\mysqldump.exe");

            return self::FAILURE;
        }

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            $this->error("Folder cadangan tidak bisa dibuat: {$dir}");

            return self::FAILURE;
        }

        $nama = 'prd-'.date('Y-m-d-His').'.sql';
        $tujuan = rtrim($dir, '/\\').'/'.$nama;
        $cnf = $this->tulisKredensial($konfig);

        try {
            $proses = new Process($this->perintahDump($cnf, $database, $koneksi));
            $proses->setTimeout(3600);

            $handle = @fopen($tujuan, 'wb');

            if ($handle === false) {
                $this->error("Tidak bisa menulis {$tujuan} — cek izin folder.");

                return self::FAILURE;
            }

            $galat = '';
            $proses->run(function ($jenis, $isi) use ($handle, &$galat) {
                if ($jenis === Process::ERR) {
                    $galat .= $isi;

                    return;
                }

                fwrite($handle, $isi);
            });

            fclose($handle);

            if (! $proses->isSuccessful()) {
                $this->error('mysqldump gagal (exit '.$proses->getExitCode().'): '.trim($galat));
                @unlink($tujuan);

                return self::FAILURE;
            }

            $masalah = $this->periksaDump($tujuan, $database, $this->jumlahTabel($koneksi, $database));

            if ($masalah !== []) {
                $this->error('Dump TIDAK lolos pemeriksaan — dianggap gagal, berkas dibuang:');
                $this->line('  '.implode("\n  ", $masalah));
                @unlink($tujuan);

                return self::FAILURE;
            }

            $this->info('Cadangan dibuat: '.$nama.' ('.number_format(filesize($tujuan) / 1024, 1).' KB)');
        } finally {
            // Kredensial tidak boleh tinggal di disk lebih lama dari prosesnya.
            @unlink($cnf);
        }

        if (! $this->option('no-rotate')) {
            $dibuang = $this->rotasi($dir, $hari);
            $this->line($dibuang === 0
                ? "Rotasi: tidak ada dump lama yang dibuang (penyimpanan {$hari} hari)."
                : "Rotasi: {$dibuang} dump berumur > {$hari} hari dibuang.");
        }

        return self::SUCCESS;
    }

    /**
     * Argumen mysqldump. Dipisah jadi method publik supaya TIDAK PERLU dijalankan
     * untuk membuktikan bahwa password tidak pernah ikut baris perintah.
     *
     * `--defaults-extra-file` WAJIB jadi opsi pertama: kalau tidak, mysqldump
     * membacanya sebagai argumen biasa dan kredensialnya tidak terpakai.
     *
     * @return list<string>
     */
    public function perintahDump(string $cnf, string $database, string $koneksi): array
    {
        $argumen = [
            (string) config('backup.mysqldump'),
            '--defaults-extra-file='.$cnf,
            '--single-transaction',
            '--quick',
            '--default-character-set=utf8mb4',
        ];

        // Dua flag ini bisa ditolak hosting (butuh privilege di luar database
        // kantor) — lihat alasannya di config/backup.php.
        if (config('backup.sertakan_prosedur')) {
            $argumen[] = '--routines';
        }

        if (config('backup.sertakan_event')) {
            $argumen[] = '--events';
        }

        $argumen[] = '--triggers';
        $argumen[] = '--databases';
        $argumen[] = $database;

        return $argumen;
    }

    /**
     * Buang dump yang lebih tua dari `$hari`, HANYA berkas berpola POLA_DUMP.
     *
     * @return int jumlah berkas yang dihapus
     */
    public function rotasi(string $dir, int $hari): int
    {
        $batas = time() - ($hari * 86400);
        $dibuang = 0;

        foreach ((new \GlobIterator(rtrim($dir, '/\\').'/*.sql')) as $file) {
            if (! preg_match(self::POLA_DUMP, $file->getFilename())) {
                continue;
            }

            if ($file->getMTime() < $batas && @unlink($file->getPathname())) {
                $dibuang++;
            }
        }

        return $dibuang;
    }

    /**
     * Pemeriksaan mutu dump. Mengembalikan daftar masalah; [] = layak dipakai.
     *
     * Dibaca per-baris dari disk, BUKAN `file_get_contents` + `str_contains`:
     * `--quick` membuat mysqldump mengeluarkan INSERT sebagai satu baris panjang,
     * dan tidak ada alasan verifikasi mencaplok seluruh isi disk ke memori.
     *
     * `$tabelDiharapkan` (dari `jumlahTabel()`) dipakai sebagai batas BAWAH: dump
     * yang strukturnya lebih sedikit dari tabel yang sebenarnya adalah cara paling
     * sunyi untuk punya "cadangan" yang isinya database kosong. Di atasnya
     * dibiarkan, karena mysqldump juga menulis tabel dummy untuk view.
     *
     * @param  int|null  $tabelDiharapkan  null = pemeriksaan jumlah dilewati
     * @return list<string>
     */
    public function periksaDump(string $path, string $database, ?int $tabelDiharapkan = null): array
    {
        $masalah = [];

        if (! is_file($path)) {
            return ['berkas tidak ada'];
        }

        $ukuran = (int) filesize($path);

        if ($ukuran < 2048) {
            $masalah[] = 'ukuran hanya '.$ukuran.' byte — dump hampir pasti kosong';
        }

        // Dua bentuk yang benar-benar dihasilkan biner di lapangan:
        //   MariaDB : CREATE DATABASE /*!32312 IF NOT EXISTS*/ `pradana` /*!40100 ... */;
        //   MySQL 8 : CREATE DATABASE IF NOT EXISTS `pradana` /*!40100 ... */;
        // Pola lama di versi ini menuntut bentuk kedua dan MENOLAK dump sungguhan
        // dari MariaDB 10.4 — tes dump nyata yang menangkapnya, 10 Okt 2026.
        $pola = '/CREATE DATABASE\s+(?:\/\*!\d+\s+IF NOT EXISTS\s*\*\/|IF NOT EXISTS)\s+`'
            .preg_quote($database, '/').'`/';

        $punyaCreateDb = false;
        $jumlahCreateTable = 0;
        $sisa = '';

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['berkas tidak bisa dibaca'];
        }

        while (! feof($handle)) {
            $potongan = (string) fread($handle, 65536);
            $baris = explode("\n", $sisa.$potongan);
            $sisa = (string) array_pop($baris);

            foreach ($baris as $satu) {
                if (! $punyaCreateDb && preg_match($pola, $satu) === 1) {
                    $punyaCreateDb = true;
                }

                if (str_starts_with(trim($satu), 'CREATE TABLE')) {
                    $jumlahCreateTable++;
                }
            }
        }

        fclose($handle);

        if (! $punyaCreateDb) {
            $masalah[] = "tidak ada `CREATE DATABASE IF NOT EXISTS `{$database}`` — hasil ini tidak bisa dipakai membangun ulang database-nya";
        }

        if ($jumlahCreateTable === 0) {
            $masalah[] = 'tidak ada satu pun CREATE TABLE di dalam berkas';
        } elseif ($tabelDiharapkan !== null && $jumlahCreateTable < $tabelDiharapkan) {
            $masalah[] = "hanya {$jumlahCreateTable} CREATE TABLE, padahal database punya {$tabelDiharapkan} tabel — dump tidak lengkap";
        }

        $ekor = '';

        if ($ukuran > 2048) {
            $handle = fopen($path, 'rb');

            if ($handle !== false) {
                fseek($handle, -2048, SEEK_END);
                $ekor = (string) fread($handle, 2048);
                fclose($handle);
            }
        } else {
            $ekor = (string) @file_get_contents($path);
        }

        if (! str_contains($ekor, '-- Dump completed')) {
            $masalah[] = 'footer "-- Dump completed" hilang — dump terpotong di tengah';
        }

        return $masalah;
    }

    /**
     * Berapa tabel yang sebenarnya ada di database tujuan, dibaca lewat koneksi
     * yang sama (information_schema), bukan dari daftar nama yang hardcoded —
     * satu-satunya rujukan yang tidak basi begitu ada migration baru.
     *
     * `getTables()` WAJIB diberi nama database. Dipanggil tanpa argumen, Laravel
     * tidak membatasi ke `config('database.connections.x.database')` melainkan
     * mengembalikan SEMUA tabel yang bisa dilihat pemakai itu di server: di laptop
     * ini 79 (termasuk `mysql_corrupted`, `phpmyadmin`, `mysql`) padahal
     * `pradana_test` cuma 15 — di shared hosting angka yang sama membuat setiap
     * backup malam dilaporkan "tidak lengkap". Ditemukan tes dump sungguhan.
     */
    private function jumlahTabel(string $koneksi, string $database): int
    {
        return count(DB::connection($koneksi)->getSchemaBuilder()->getTables($database));
    }

    /**
     * File kredensial sementara: 0600, isi [client], hanya path-nya yang masuk
     * ke argumen mysqldump.
     */
    private function tulisKredensial(array $konfig): string
    {
        $cnf = sys_get_temp_dir().DIRECTORY_SEPARATOR.'prd-backup-'.bin2hex(random_bytes(6)).'.cnf';

        $isi = "[client]\n"
            .'user='.($konfig['username'] ?? '')."\n"
            .'password="'.str_replace(['\\', '"'], ['\\\\', '\"'], (string) ($konfig['password'] ?? ''))."\"\n"
            .'host='.($konfig['host'] ?? '127.0.0.1')."\n"
            .'port='.($konfig['port'] ?? 3306)."\n";

        file_put_contents($cnf, $isi);
        @chmod($cnf, 0600);

        return $cnf;
    }

    private function temukan(string $biner): string
    {
        return $this->eksis($biner) ? 'ditemukan' : 'TIDAK ditemukan';
    }

    /** Apakah mysqldump bisa dieksekusi — lewat PATH atau path absolut. */
    private function eksis(string $biner): bool
    {
        if (str_contains($biner, DIRECTORY_SEPARATOR) || str_contains($biner, '/')) {
            return is_file($biner) || (new ExecutableFinder)->find($biner) !== null;
        }

        return (new ExecutableFinder)->find($biner) !== null;
    }

    private function componentError(string $driver, string $koneksi): void
    {
        $this->error("Perintah ini khusus MySQL/MariaDB, koneksi `{$koneksi}` memakai driver `{$driver}`.");
        $this->line('  Cadangan database kantor dijalankan di server produksi (S2=a) — di laptop');
        $this->line('  pakai --connection=mysql_test_a untuk menguji jalur dump-nya.');
    }
}
