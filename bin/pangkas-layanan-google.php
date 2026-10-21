<?php

/*
 * Pemangkas stub layanan Google yang tidak dipakai PRADANA.
 *
 * Kenapa: `google/apiclient-services` (36.901 berkas di mesin ini, 670 entri = 335
 * layanan x {Drive.php + folder Drive/}) ikut terpasang hanya karena kita memakai
 * SATU layanan: Drive, untuk cadangan lampiran (L-01). Kode aplikasi + apiclient +
 * auth tidak menunjuk layanan lain mana pun (dibuktikan di bawah, dan skrip ini
 * membaca ulang buktinya setiap jalan, bukan mempercayai komentar ini).
 *
 * Ini bukan kegemaran estetik, ini syarat deploy. Paket `vendor/` produksi hari ini
 * = 43.561 berkas. Hosting gratis dengan batas inode (InfinityFree: 30.000 berkas)
 * menolak akun yang melewatinya — gejalanya "upload gagal" atau "file hilang",
 * bukan pesan yang menyebut penyebabnya. Setelah dipangkas, jumlah itu turun ke
 * sekitar 7 ribu dan muat di mana saja.
 *
 * Cara: daftar layanan yang DIPERTAHANKAN dihitung dari `use Google\Service\X` yang
 * benar-benar ada di `app/`, `config/`, `routes/`, `database/`, `bootstrap/`,
 * `public/index.php`, plus yang direferensikan `vendor/google/apiclient` dan
 * `vendor/google/auth` (fallback autoloader-nya mengenal namespace itu). Lainnya
 * dihapus — berkas `src/<Nama>.php` DAN folder `src/<Nama>/` berpasangan, jadi
 * keduanya ikut.
 *
 * Yang perlu diketahui sebelum mempercayai hasil "berkas hilang":
 *   1. `composer install`/`update` mengembalikan SEMUA stub. Karena ini langkah
 *      pemangkasan dijalankan ulang setiap deploy (lihat bin/deploy.sh), bukan
 *      sekali lalu dilupakan.
 *   2. Tidak perlu `composer dump-autoload`: paket ini autoload-nya PSR-4
 *      (`Google\Service\` => src), jadi kelas yang dihapus tidak pernah tercatat di
 *      classmap. KECUALI kalau nanti memasang `--classmap-authoritative` — itu
 *      menuntut dump-autoload ulang SETELAH pemangkasan.
 *   3. Verifikasi bukan "exit code 0": skrip ini mengecek `class_exists()` untuk
 *      kelas yang dipakai aplikasi, dan `arsip:sinkron-ke-drive --dry-run` tetap
 *      harus bisa dijalankan sesudahnya.
 *
 * Cara pakai:
 *   php bin/pangkas-layanan-google.php --cek        # laporan saja, tidak menghapus
 *   php bin/pangkas-layanan-google.php              # pangkas
 */

$akar = dirname(__DIR__);
$src = $akar.'/vendor/google/apiclient-services/src';
$paket = dirname($src);

if (! is_dir($src)) {
    fwrite(STDERR, "Tidak menemukan {$src} — paket google/apiclient-services belum terpasang?\n");
    exit(1);
}

$cekSaja = in_array('--cek', array_slice($argv, 1), true);
foreach (array_slice($argv, 1) as $arg) {
    if (! in_array($arg, ['--cek'], true)) {
        fwrite(STDERR, "Argumen tidak dikenal: {$arg} (yang ada: --cek)\n");
        exit(2);
    }
}

/**
 * Hitung semua berkas + byte di bawah satu folder. Dipakai untuk melaporkan
 * sebelum/sesudah dengan angka, bukan kesan.
 */
$ukur = function (string $dir): array {
    $berkas = 0;
    $byte = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $f) {
        if ($f->isFile()) {
            $berkas++;
            $byte += $f->getSize();
        }
    }

    return [$berkas, $byte];
};

/**
 * Ambil nama layanan yang ditulis apa adanya di satu folder kode.
 * `Google\Service\Drive` dan `Google\Service\Drive\DriveFile` keduanya -> "Drive".
 */
$pemakai = static function (string $dir, string $pola): array {
    if (! is_dir($dir)) {
        return [];
    }
    $hasil = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (! $f->isFile() || ! preg_match('/\.(php|blade\.php)$/', $f->getFilename())) {
            continue;
        }
        $isi = (string) file_get_contents($f->getPathname());
        if (preg_match_all($pola, $isi, $m)) {
            foreach ($m[1] as $nama) {
                $hasil[$nama] = true;
            }
        }
    }

    return array_keys($hasil);
};

$pola = '/Google\\\\Service\\\\([A-Za-z0-9_]+)/';

$daftarPemakai = [];
foreach ([$akar.'/app', $akar.'/config', $akar.'/routes', $akar.'/database', $akar.'/bootstrap', $akar.'/public'] as $d) {
    foreach ($pemakai($d, $pola) as $n) {
        $daftarPemakai[$n] = true;
    }
}
// Pustaka Google sendiri ikut: `Google\Service` dasar menyebut nama di dalam string
// (mis. service-restore), dan itu bukan kode kita tapi tetap harus ada.
foreach ([$paket.'/../apiclient/src', $paket.'/../auth/src'] as $d) {
    foreach ($pemakai($d, $pola) as $n) {
        $daftarPemakai[$n] = true;
    }
}

// Semua entri layanan yang ADA di paket ini. Dibaca lebih dulu karena daftar yang
// dipertahankan harus dibandingkan terhadap kenyataan, bukan terhadap asumsi.
$entri = array_values(array_filter(scandir($src), fn ($n) => $n !== '.' && $n !== '..'));
$layanan = [];
foreach ($entri as $n) {
    if (is_dir($src.'/'.$n)) {
        $layanan[$n] = true;
    } elseif (substr($n, -4) === '.php') {
        $layanan[substr($n, 0, -4)] = true;
    }
}
ksort($layanan);
$layananSemua = $layanan;

$pertahankan = array_keys($daftarPemakai);
sort($pertahankan);

if ($pertahankan === []) {
    fwrite(STDERR, "Berhenti: tidak ada satu pun `Google\\Service\\X` yang terpakai di kode.\n");
    fwrite(STDERR, "Ini mencurigakan (aplikasi ini seharusnya memakai Drive) — tidak menghapus apa pun.\n");
    exit(1);
}

// `Google\Service\Resource` dan `Google\Service\Exception` disebut kode kita DAN
// disebut apiclient, tapi kelasnya hidup di paket `google/apiclient`, bukan di sini.
// Karena itu keduanya tidak boleh masuk laporan sebagai "layanan yang dipertahankan":
// menyajikan angka yang tidak bisa diverifikasi adalah cara tercepat membuat orang
// percaya pada hal yang salah.
$diSini = array_values(array_filter($pertahankan, fn ($n) => isset($layananSemua[$n])));
$diluar = array_values(array_diff($pertahankan, $diSini));
$pertahankan = $diSini;

if ($pertahankan === []) {
    fwrite(STDERR, "Berhenti: tidak ada layanan di paket ini yang cocok dengan pemakai di kode.\n");
    fwrite(STDERR, 'Daftar yang ditemui: '.implode(', ', $diluar)."\n");
    exit(1);
}

[$berkasSebelum, $byteSebelum] = $ukur($paket);

$buang = array_diff(array_keys($layananSemua), $pertahankan);
$buang = array_values($buang);
sort($buang);

echo "== Pemangkas layanan Google ==\n";
echo 'Akar paket   : '.$paket."\n";
echo 'Entri layanan: '.count($layananSemua)."\n";
echo 'Dipertahankan: '.implode(', ', $pertahankan)."\n";
if ($diluar !== []) {
    echo '  (nama lain yang disebut kode/pustaka tapi kelasnya hidup di google/apiclient, tidak ada di paket ini: '
        .implode(', ', $diluar).")\n";
}
echo 'Akan dibuang : '.count($buang)." layanan\n";
echo 'Berkas saat ini: '.number_format($berkasSebelum).' berkas, '.number_format($byteSebelum / 1024 / 1024, 1)." MB\n\n";

if ($cekSaja) {
    echo "--cek: tidak ada yang dihapus. 10 contoh yang akan dibuang:\n";
    foreach (array_slice($buang, 0, 10) as $n) {
        echo "  {$n}\n";
    }
    exit(0);
}

if ($buang === []) {
    echo "Sudah terpangkas — tidak ada yang perlu dibuang.\n";
    exit(0);
}

/**
 * Coba sekali operasi berkas, lalu ulangi dengan jeda naik. Ini bukan hiasan:
 * di Windows, `unlink()` yang berhasil masih meninggalkan entri berstatus
 * *pending delete* selama ada proses lain memegang handle-nya (Defender/indexer
 * melakukan ini persis pada file yang barusan dihapus), dan `rmdir()` induk
 * gagal karena itu. Run ketiga skrip ini berhenti di `src/CloudHealthcare/Resource`
 * dengan kondisi persis begitu — isinya sudah kosong, foldernya tidak mau hilang.
 */
$cobaLagi = function (callable $fn, string $p, int $kali = 8): bool {
    for ($i = 0; $i < $kali; $i++) {
        // Sudah tidak ada = sudah selesai. Ini yang membuat skripnya idempoten:
        // dijalankan ulang di tengah pohon yang terpotong tidak boleh dianggap gagal.
        if (! file_exists($p)) {
            return true;
        }
        if (@$fn($p)) {
            return true;
        }
        clearstatcache(true, $p);
        usleep(80000 + ($i * 120000));
    }

    return false;
};

/**
 * Hapus folder beserta isinya. Kegagalan di sini WAJIB menghentikan skrip: vendor
 * terpotong separuh terlihat "normal" sampai ada kelas yang benar-benar diminta.
 *
 * Tiga penyebab kegagalan sudah dibayar nyata oleh tiga run pertama, dan semuanya
 * Windows — di server Linux kantor jalur ini tidak akan terpakai, tapi skripnya
 * tetap harus benar karena pemangkasan dilakukan di mesin pengembang:
 *   1. `RecursiveDirectoryIterator` MEMEGANG HANDLE folder. Selama iteratornya hidup,
 *      `rmdir()` induk gagal dengan "Directory not empty" padahal isinya sudah
 *      terhapus. Karena itu daftar path dikumpulkan dulu, iteratornya dibuang
 *      (`unset`), baru penghapusan dimulai.
 *   2. *Pending delete* (lihat `$cobaLagi` di atas) — butuh jeda, bukan percobaan
 *      serentak.
 *   3. Path panjang TIDAK menjadi soal: terukur 203 karakter maksimum, di bawah
 *      batas API lama 260. Prefiks `\\?\` sengaja TIDAK dipakai — diuji di PHP
 *      8.2.12 Windows, `file_exists()` justru mengembalikan false untuk bentuk itu,
 *      sehingga skrip "tidak menemukan" folder yang ada (itu persis kegagalan run
 *      kedua: nol berkas terhapus tanpa satu pun pesan galat).
 *
 * Pesan galat selalu menyebut path penuh: yang dicari operator adalah berkas mana
 * yang menahan, bukan nama layanan.
 */
$hapusPohon = function (string $path, int $kedalaman = 0) use (&$hapusPohon, $cobaLagi): int {
    if (! file_exists($path)) {
        return 0;
    }
    if (! is_dir($path)) {
        if (! $cobaLagi('unlink', $path)) {
            throw new RuntimeException("Gagal menghapus berkas {$path}");
        }

        return 1;
    }

    $item = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $item[] = [$f->isDir(), $f->getPathname()];
    }
    unset($it);

    $n = 0;
    foreach ($item as [$adalahFolder, $p]) {
        if ($adalahFolder) {
            if (! $cobaLagi('rmdir', $p)) {
                throw new RuntimeException("Gagal menghapus folder {$p}");
            }
        } elseif (! $cobaLagi('unlink', $p)) {
            throw new RuntimeException("Gagal menghapus berkas {$p}");
        } else {
            $n++;
        }
    }

    if (! $cobaLagi('rmdir', $path)) {
        // Terakhir: kalau masih ada isinya (listing Windows kadang telat), hapus
        // ulang sekali secara rekursif sebelum menyerah. Tanpa jaring ini, skrip
        // berhenti permanen pada folder yang sebenarnya bisa diselesaikan.
        $sisa = array_values(array_diff((array) @scandir($path), ['.', '..']));
        if ($sisa !== [] && $kedalaman < 2) {
            return $n + $hapusPohon($path, $kedalaman + 1);
        }
        throw new RuntimeException("Gagal menghapus folder {$path} — masih ditahan proses lain; jalankan ulang skrip ini (ia idempoten).");
    }

    return $n + 1;
};

$terhapus = 0;
$sudah = 0;
try {
    foreach ($buang as $n) {
        // pasangan `src/<Nama>.php` + `src/<Nama>/` dibuang berdua (dibuktikan:
        // 335 berkas berpasangan 335 folder, tidak ada yang satu sisi).
        $terhapus += $hapusPohon($src.'/'.$n.'.php');
        $terhapus += $hapusPohon($src.'/'.$n);
        $sudah++;
        if ($sudah % 40 === 0) {
            // Ini berjalan beberapa menit di disk Windows. Tanpa tanda hidup,
            // operator tidak bisa membedakan "sedang kerja" dari "macet".
            echo '  ... '.$sudah.'/'.count($buang).' layanan, '
                .number_format($terhapus)." berkas dibuang\n";
            flush();
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "\nTERHENTI DI TENGAH: ".$e->getMessage()."\n");
    fwrite(STDERR, "JANGAN lanjutkan deploy. Pulihkan dengan: composer reinstall google/apiclient-services\n");
    exit(1);
}

// Sisa entri diperiksa ulang dari DISK, bukan dari angka yang dihitung selama
// penghapusan. Alasan yang sama dengan bug `src/Books` di atas: skrip yang melaporkan
// dirinya sendiri bisa bilang "selesai" padahal folder masih ada.
$sisa = [];
foreach (scandir($src) as $n) {
    if ($n === '.' || $n === '..') {
        continue;
    }
    $inti = substr($n, -4) === '.php' ? substr($n, 0, -4) : $n;
    if (! in_array($inti, $pertahankan, true)) {
        $sisa[] = $n;
    }
}
if ($sisa !== []) {
    fwrite(STDERR, 'GAGAL: masih ada '.count($sisa).' entri layanan yang tidak dipakai, contoh: '
        .implode(', ', array_slice($sisa, 0, 6))."\n");
    fwrite(STDERR, "Jalankan ulang skrip ini; kalau gagal di berkas yang sama, itu tahanantivirus/indeks Windows.\n");
    exit(1);
}

[$berkasSesudah, $byteSesudah] = $ukur($paket);

echo "Berkas dibuang : {$terhapus}\n";
echo 'Berkas tersisa : '.number_format($berkasSesudah).' berkas, '.number_format($byteSesudah / 1024 / 1024, 1)." MB\n";
echo 'Hemat          : '.number_format($berkasSebelum - $berkasSesudah).' berkas ('
    .number_format(($byteSebelum - $byteSesudah) / 1024 / 1024, 1)." MB)\n\n";

// Verifikasi: kelas yang dipakai aplikasi masih bisa dimuat. Ini bukan seremonial —
// kalau satu nama salah eja di daftar pemakai, inilah yang menangkapnya.
require $akar.'/vendor/autoload.php';
$pemuatanGagal = [];
foreach (['Google\Client', 'Google\Service', 'Google\Service\Drive', 'Google\Service\Drive\DriveFile'] as $kelas) {
    if (! class_exists($kelas)) {
        $pemuatanGagal[] = $kelas;
    }
}

if ($pemuatanGagal !== []) {
    fwrite(STDERR, 'GAGAL: kelas berikut tidak bisa dimuat setelah pemangkasan: '.implode(', ', $pemuatanGagal)."\n");
    fwrite(STDERR, "Pulihkan dengan: composer reinstall google/apiclient-services\n");
    exit(1);
}

echo "Verifikasi muat: Google\\Client, Google\\Service, Google\\Service\\Drive, Google\\Service\\Drive\\DriveFile — OK\n";
echo "Langkah berikutnya: php artisan arsip:sinkron-ke-drive --dry-run (harus bisa jalan)\n";
