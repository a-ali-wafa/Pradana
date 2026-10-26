<?php

/*
 * Pembuat subset Font Awesome.
 *
 * Kenapa: `all.min.css` (99,6 KB) mendefinisikan 1.856 ikon, sedangkan aplikasi
 * ini memakai <80. CSS itu blocking dan diminta di setiap halaman, jadi memangkasnya
 * adalah penghematan byte paling murah yang ada (tercatat 12 Okt:
 * terukur 12 Okt: 99,6 KB -> 19,3 KB). Skrip ini juga membuat subset FONT-nya;
 * lihat tahap dua di bawah.
 *
 * Cara: salin all.min.css, buang HANYA aturan berformat persis
 *   .fa-<nama>:before{content:"\fxxx"}
 * yang namanya tidak dipakai. Semua aturan lain (utility ukuran, animasi,
 * @font-face, @media, selector gabungan) dibiarkan apa adanya — menyimpan terlalu
 * banyak selalu lebih aman daripada menghapus ikon yang sedang dipakai.
 *
 * Jebakan yang sudah dibayar: `partials/arsip-aksi.blade.php` menulis
 * `fa-toggle-{{ $inaktif ? 'on' : 'off' }}`. Pemindai naif melihat "toggle" dan
 * membuang fa-toggle-on/off -> ikon tombol "Nyahkan" hilang diam-diam. Karena itu
 * nama yang di belakangnya masih ada tanda hubung diperlakukan sebagai PREFIX:
 * semua ikon dengan awalan itu ikut disimpan.
 *
 * Jalankan ulang setiap kali menambah ikon:  php bin/buat-subset-ikon.php
 * `IkonSubsetTest` menuntut setiap ikon yang terpakai benar-benar punya aturan di
 * berkas hasil, jadi lupa menjalankan skrip ketahuan oleh tes, bukan oleh petugas.
 */

$akar = dirname(__DIR__);
$sumber = $akar.'/public/vendor/font-awesome/css/all.min.css';
$tujuan = $akar.'/public/vendor/font-awesome/css/pradana-icons.min.css';

if (! is_file($sumber)) {
    fwrite(STDERR, "Tidak menemukan {$sumber}\n");
    exit(1);
}

$pakai = [];
$awalan = [];

$telusuri = static function (string $isi) use (&$pakai, &$awalan): void {
    if (! preg_match_all('/fa-([a-z0-9]+(?:-[a-z0-9]+)*)/', $isi, $m, PREG_OFFSET_CAPTURE)) {
        return;
    }
    foreach ($m[1] as $cocok) {
        [$nama, $posisi] = [$cocok[0], $cocok[1]];
        // Kelas dibangun dinamis: `fa-toggle-` + `{{ ... }}` -> simpan semua turunannya.
        if (substr($isi, $posisi + strlen($nama), 1) === '-') {
            $awalan[] = $nama;
        } else {
            $pakai[] = $nama;
        }
    }
};

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($akar.'/resources/views')) as $f) {
    if (! $f->isDir() && str_ends_with($f->getFilename(), '.blade.php')) {
        $telusuri((string) file_get_contents($f->getPathname()));
    }
}
foreach (glob($akar.'/public/js/*.js') ?: [] as $js) {
    $telusuri((string) file_get_contents($js));
}

$pakai = array_values(array_unique($pakai));
$awalan = array_values(array_unique($awalan));
sort($pakai);
sort($awalan);

$simpan = static function (string $nama) use ($pakai, $awalan): bool {
    if (in_array($nama, $pakai, true)) {
        return true;
    }
    foreach ($awalan as $a) {
        if ($nama === $a || str_starts_with($nama, $a.'-')) {
            return true;
        }
    }

    return false;
};

$css = (string) file_get_contents($sumber);

/*
 * Aturan ikon di Font Awesome 6 memakai LIST selector, contoh nyata:
 *   .fa-users-cog:before,.fa-users-gear:before{content:"\f509"}
 * Membuang dengan pola ".fa-x:before{content:...}" akan mencocokkan bagian
 * KEDUA saja (".fa-users-gear:before{...}"), menghapusnya, dan menyisakan
 * ".fa-users-cog:before," yang menggantung — seluruh aturan jadi tidak valid
 * dan ikonnya hilang tanpa error apa pun. Persis bug yang ditemukan 12 Okt
 * saat subset diuji di browser: ikon "Manajemen User" mendadak kosong.
 *
 * Jadi: satu aturan = satu kesatuan selector list. Aturan disimpan kalau SALAH
 * SATU namanya dipakai; kalau tidak, dibuang utuh.
 */
$polaAturan = '/((?:\.fa-[a-z0-9-]+:before\s*,\s*)*\.fa-[a-z0-9-]+:before)\{content:"[^"]*"\}/';

$namaDalamAturan = static function (string $selector): array {
    preg_match_all('/\.fa-([a-z0-9]+(?:-[a-z0-9]+)*):before/', $selector, $m);

    return $m[1];
};

$buang = 0;
$hasil = preg_replace_callback(
    $polaAturan,
    function (array $c) use ($simpan, $namaDalamAturan, &$buang): string {
        foreach ($namaDalamAturan($c[1]) as $n) {
            if ($simpan($n)) {
                return $c[0];
            }
        }
        $buang++;

        return '';
    },
    $css
);

$kepala = '/* pradana-icons.min.css - subset all.min.css (Font Awesome 6.4.0, MIT), dibuat oleh '
    .'bin/buat-subset-ikon.php. '.count($pakai).' ikon dipakai + '.count($awalan)
    .' awalan dinamis, '.$buang." aturan dibuang. JANGAN edit manual: jalankan ulang skripnya. */\n";

/*
 * Sumber huruf diganti ke berkas hasil subset glyph. Dulu CSS-nya sudah ramping
 * (20 KB) tapi masih menuntut `fa-solid-900.woff2` seberat 150 KB: berkas itu
 * memuat 1.391 glyph, padahal halaman ini hanya memanggil 71. CSS yang dipangkas
 * tanpa memangkas fontnya hanya memindahkan pemborosan.
 *
 * `src:` ditulis ulang untuk SEMUA blok @font-face yang menunjuk berkas solid
 * (Font Awesome 6 Free 900, Font Awesome 5 Free 900, FontAwesome) — kalau hanya
 * satu yang diganti, alias lama masih menyedot berkas penuh.
 *
 * Reference `.ttf` dibuang sekaligus: berkasnya tidak pernah di-commit (10 Okt:
 * hanya `.woff2`), jadi ia cuma janji gagal yang bisa terlihat di Network panel.
 * Blok brands/regular/v4compatibility SENGAJA dibiarkan: browser tidak mengunduh
 * @font-face yang tidak dipakai (terukur 12 Okt: hanya berkas solid yang jalan),
 * dan membuangnya membuat pemakaian `far`/`fab` di masa depan gagal diam-diam.
 */
$hasil = str_replace(
    'url(../webfonts/fa-solid-900.woff2) format("woff2")',
    'url(../webfonts/pradana-solid-900.woff2) format("woff2")',
    $hasil
);
$hasil = (string) preg_replace(
    '/,url\(\.\.\/webfonts\/[A-Za-z0-9._-]+\.ttf\) format\("truetype"\)/',
    '',
    $hasil
);

file_put_contents($tujuan, $kepala.$hasil);

printf(
    "all.min.css            %7.1f KB\npradana-icons.min.css  %7.1f KB  (-%.1f KB, %d aturan ikon dibuang)\n",
    strlen($css) / 1024,
    strlen($kepala.$hasil) / 1024,
    (strlen($css) - strlen($kepala.$hasil)) / 1024,
    $buang
);
printf("Ikon dipakai: %d | awalan dinamis: %s\n", count($pakai), $awalan === [] ? '-' : implode(', ', $awalan));

// Verifikasi terhadap BERKAS HASIL: pertanyaan yang penting bukan "apakah namanya
// dikenal Font Awesome", tapi "apakah ikon itu masih punya aturan setelah subset".
// Verifikasi terhadap BERKAS HASIL, dengan aturan yang sama seperti pembuatnya:
// nama dianggap "ada" hanya kalau ia muncul di selector list yang benar-benar
// punya deklarasi content. Mengecek keberadaan teks ".fa-nama" saja menipu —
// selector yang menggantung setelah penghapusan parsial juga mengandung teks itu.
$punyaAturan = [];
preg_match_all($polaAturan, $hasil, $m);
foreach ($m[1] as $selector) {
    foreach ($namaDalamAturan($selector) as $n) {
        $punyaAturan[$n] = true;
    }
}

// Hanya nama yang MEMANG ikon di Font Awesome yang ditagih. `fa-lg`, `fa-2x`,
// `fa-fw` adalah utility (mengatur ukuran/lebar, bukan glyph) dan tidak pernah
// punya aturan content — memasukkannya ke daftar tagihan membuat skrip gagal
// untuk hal yang bukan bug.
$punyaSumber = [];
preg_match_all($polaAturan, $css, $ms);
foreach ($ms[1] as $selector) {
    foreach ($namaDalamAturan($selector) as $n) {
        $punyaSumber[$n] = true;
    }
}

$hilang = [];
foreach (array_intersect($pakai, array_keys($punyaSumber)) as $n) {
    if (! isset($punyaAturan[$n])) {
        $hilang[] = $n;
    }
}
foreach ($awalan as $a) {
    $cocok = false;
    foreach (array_keys($punyaAturan) as $n) {
        if (str_starts_with($n, $a.'-')) {
            $cocok = true;
            break;
        }
    }
    if (! $cocok) {
        $hilang[] = $a.'-*';
    }
}

if ($hilang !== []) {
    fwrite(STDERR, 'IKON HILANG di hasil subset: '.implode(', ', $hilang)."\n");
    exit(1);
}

echo "verifikasi: semua ikon yang dipakai punya aturan di berkas hasil.\n";

/*
 * Tahap dua: subset BERKAS FONT.
 *
 * Kenapa terpisah dari potongan CSS: font asli `fa-solid-900.woff2` memuat 1.391
 * glyph (150 KB) sedangkan CSS hasil di atas hanya menuntut 71 codepoint. woff2
 * sudah ter-Brotli, jadi mod_deflate hosting TIDAK menambah apa pun di sini —
 * memangkas glyph adalah satu-satunya cara mengurangi berkasnya. Font ini juga
 * satu-satunya aset yang tidak bisa diperbaiki dengan cache header: ia tetap
 * diunduh penuh pada kunjungan pertama.
 *
 * Tool-nya Python + fontTools (brotli). Ini ALAT PENGEMBANG, sama seperti Node di
 * `bin/uji-pradana-arsip.js`: aplikasi tidak pernah memanggil Python dan hosting
 * tidak butuh apa pun selain dua berkas hasil yang di-commit.
 *
 * Verifikasi dibaca ULANG dari cmap berkas jadi, bukan dari angka yang dikirim ke
 * pyftsubset — kalau ada codepoint yang ternyata tidak ada di font sumber, glyph
 * itu hilang tanpa satu pun pesan di layar. Bukan kebetulan: daftar pertama sempat
 * minta U+0020 "buat jaga-jaga" dan pemeriksaan itu gagal — `fa-solid-900` memang
 * tidak punya glyph ASCII sama sekali (isinya PUA saja). Yang tidak bisa diminta
 * tidak boleh ditagih, jadi daftar yang dipakai adalah codepoint yang benar-benar
 * ditulis CSS.
 */
$sumberFont = $akar.'/public/vendor/font-awesome/webfonts/fa-solid-900.woff2';
$tujuanFont = $akar.'/public/vendor/font-awesome/webfonts/pradana-solid-900.woff2';

$kodepoin = [];
foreach ($m[0] as $aturan) {
    if (preg_match_all('/content:"\\\\\s*([0-9a-fA-F]{4,6})/', $aturan, $u)) {
        foreach ($u[1] as $k) {
            $kodepoin[strtoupper($k)] = true;
        }
    }
}
ksort($kodepoin);

$python = null;
$keluar = [];
foreach (['python', 'python3'] as $calon) {
    exec($calon.' --version 2>&1', $keluar, $kode);
    if ($kode === 0) {
        $python = $calon;
        break;
    }
}

if ($python === null || ! is_file($sumberFont)) {
    fwrite(STDERR, "Font tidak bisa disubset: butuh Python + fontTools + brotli.\n");
    fwrite(STDERR, "Pasang sekali:  pip install fonttools brotli\n");
    exit(1);
}

$daftar = implode(',', array_map(static fn (string $k): string => 'U+'.$k, array_keys($kodepoin)));

$jalankan = sprintf(
    '%s -m fontTools.subset %s --unicodes=%s --layout-features= --flavor=woff2 --output-file=%s 2>&1',
    $python,
    escapeshellarg($sumberFont),
    escapeshellarg($daftar),
    escapeshellarg($tujuanFont)
);
$out = (string) shell_exec($jalankan);

if (! is_file($tujuanFont)) {
    fwrite(STDERR, "pyftsubset gagal:\n".$out."\n");
    exit(1);
}

$cek = trim((string) shell_exec(sprintf(
    '%s %s %s %s 2>&1',
    $python,
    escapeshellarg($akar.'/bin/cek-kodepoin-font.py'),
    escapeshellarg($tujuanFont),
    escapeshellarg($daftar)
)));
if (! str_starts_with($cek, 'OK')) {
    fwrite(STDERR, "Berkas font hasil subset TIDAK memuat semua glyph yang dituntut CSS: {$cek}\n");
    exit(1);
}

printf(
    "fa-solid-900.woff2      %7.1f KB  (sumber, semua glyph)\npradana-solid-900.woff2  %7.1f KB  (%s, %d codepoint dipakai)\n",
    filesize($sumberFont) / 1024,
    filesize($tujuanFont) / 1024,
    $cek,
    count($kodepoin)
);
echo "verifikasi: cmap berkas font memuat semua codepoint yang dituntut CSS.\n";
