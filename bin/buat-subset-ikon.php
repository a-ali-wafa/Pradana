<?php

/*
 * Pembuat subset Font Awesome.
 *
 * Kenapa: `all.min.css` (99,6 KB) mendefinisikan 1.856 ikon, sedangkan aplikasi
 * ini memakai 78. CSS itu blocking dan diminta di setiap halaman, jadi memangkasnya
 * adalah penghematan byte paling murah yang ada (docs/daftar-peningkatan.md 1.4#17,
 * terukur 12 Okt 2026: 99,6 KB -> 32,6 KB).
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
