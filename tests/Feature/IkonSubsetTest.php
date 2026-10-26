<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guardrail subset Font Awesome.
 *
 * Kenapa perlu: `bin/buat-subset-ikon.php` membuang 1.800 aturan ikon dari
 * all.min.css. Kalau suatu hari ada view memakai ikon baru tanpa menjalankan
 * ulang skripnya, yang terjadi bukan error — hanya kotak kosong di tombol,
 * jenis kegagalan yang tidak dibaca siapa pun sebagai "CSS kurang".
 *
 * Tes ini SENGAJA memindai ulang view dengan kodenya sendiri (bukan memanggil
 * fungsi dari skripnya). Kalau pemindai di skrip meleset — persis kasus
 * `fa-toggle-{{ $inaktif ? 'on' : 'off' }}` yang hampir membuat ikon tombol
 * "Nyahkan" hilang — perbedaan antara dua pemindai itu yang menjerit.
 */
class IkonSubsetTest extends TestCase
{
    private const CSS = 'vendor/font-awesome/css/pradana-icons.min.css';

    private const SUMBER = 'vendor/font-awesome/css/all.min.css';

    private function cssSubset(): string
    {
        $path = public_path(self::CSS);
        $this->assertFileExists($path, 'Jalankan: php bin/buat-subset-ikon.php');

        return (string) file_get_contents($path);
    }

    /** @return array{0:array<int,string>,1:array<int,string>} nama ikon + awalan dinamis */
    private function pindai(): array
    {
        $nama = [];
        $awalan = [];
        $berkas = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $f) {
            if (! $f->isDir() && str_ends_with($f->getFilename(), '.blade.php')) {
                $berkas[] = $f->getPathname();
            }
        }
        $berkas = array_merge($berkas, glob(public_path('js/*.js')) ?: []);

        foreach ($berkas as $path) {
            $isi = (string) file_get_contents($path);
            if (! preg_match_all('/fa-([a-z0-9]+(?:-[a-z0-9]+)*)/', $isi, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($m[1] as [$teks, $posisi]) {
                if (substr($isi, $posisi + strlen($teks), 1) === '-') {
                    $awalan[$teks] = $teks;
                } else {
                    $nama[$teks] = $teks;
                }
            }
        }

        return [array_keys($nama), array_keys($awalan)];
    }

    /**
     * Nama ikon yang benar-benar punya aturan `content` di sebuah berkas CSS.
     *
     * Sengaja tidak sekadar mencari teks ".fa-nama": Font Awesome 6 menulis ikon
     * sebagai list selector (`.fa-users-cog:before,.fa-users-gear:before{content:...}`),
     * dan subset yang menghapus separuh list itu meninggalkan teks ".fa-users-cog:before,"
     * yang menggantung — secara teks "ada", secara CSS mati. Bug ini benar-benar
     * terjadi 12 Okt 2026 dan membuat ikon menu "Manajemen User" hilang tanpa error.
     *
     * @return array<int,string>
     */
    private function ikonDenganAturan(string $css): array
    {
        $punya = [];
        $pola = '/((?:\.fa-[a-z0-9-]+:before\s*,\s*)*\.fa-[a-z0-9-]+:before)\{content:"[^"]*"\}/';
        if (preg_match_all($pola, $css, $m)) {
            foreach ($m[1] as $selector) {
                if (preg_match_all('/\.fa-([a-z0-9]+(?:-[a-z0-9]+)*):before/', $selector, $n)) {
                    foreach ($n[1] as $nama) {
                        $punya[$nama] = true;
                    }
                }
            }
        }

        return array_keys($punya);
    }

    public function test_setiap_ikon_yang_dipakai_punya_aturan_di_subset(): void
    {
        $css = $this->cssSubset();
        [$nama, $awalan] = $this->pindai();
        $punya = $this->ikonDenganAturan($css);

        $this->assertGreaterThan(30, count($nama), 'Pemindai menemukan terlalu sedikit ikon — pasti salah baca.');
        $this->assertNotEmpty($punya, 'Tidak ada satu pun aturan ikon di berkas hasil.');

        // `fa-lg`, `fa-2x`, `fa-fw` adalah utility, bukan glyph: tidak pernah punya
        // aturan content di Font Awesome mana pun, jadi tidak bisa ditagih ke sini.
        $diSumber = $this->ikonDenganAturan((string) file_get_contents(public_path(self::SUMBER)));
        $ikon = array_values(array_intersect($nama, $diSumber));
        $this->assertNotEmpty($ikon, 'Tidak ada nama yang dikenali sebagai ikon — pemindai atau polanya salah.');

        $hilang = array_values(array_diff($ikon, $punya));
        $this->assertSame([], $hilang, 'Ikon dipakai tapi tidak ada aturan content-nya di subset. Jalankan ulang bin/buat-subset-ikon.php.');

        foreach ($awalan as $a) {
            $turunan = array_filter($punya, static fn (string $n): bool => str_starts_with($n, $a.'-'));
            $this->assertNotEmpty($turunan, "Ikon dinamis '{$a}-*' tidak tersisa di subset.");
        }
    }

    public function test_ikon_alias_selector_gabungan_tetap_utuh(): void
    {
        // Regresi 12 Okt 2026: aturan ini ditulis sebagai list selector, dan
        // penghapusan parsial membuatnya tidak valid.
        $punya = $this->ikonDenganAturan($this->cssSubset());

        foreach (['users-cog', 'users-gear', 'trash-alt', 'file-alt', 'chart-pie'] as $alias) {
            $this->assertContains($alias, $punya, "Alias '{$alias}' kehilangan aturan content-nya.");
        }
    }

    public function test_subset_jauh_lebih_kecil_dari_all_min_css(): void
    {
        $subset = filesize(public_path(self::CSS));
        $sumber = filesize(public_path(self::SUMBER));

        $this->assertLessThan($sumber * 0.5, $subset, 'Subset tidak lebih kecil dari aslinya — tidak ada yang dipangkas.');
        // Ambang bawah: berkas ini TIDAK boleh menyusut sampai aturan dasar/@font-face
        // ikut terbuang. Angka 20.000 dipakai sebelum 12 Okt; rujukan `.ttf` yang mati
        // (berkasnya memang tidak di-commit) ikut dibuang, jadi isinya sekarang
        // turun ke ~19,8 KB tanpa kehilangan satu glyph pun.
        $this->assertGreaterThan(15_000, $subset, 'Subset mencurigakan kecil — mungkin aturan dasar/@font-face ikut terbuang.');
    }

    public function test_aturan_dasar_ikon_tetap_ada(): void
    {
        $css = $this->cssSubset();

        // Tanpa ketiganya, ikon yang "ada" pun tidak akan ter-render.
        $this->assertMatchesRegularExpression('/\.fa-solid|\.fas\b/', $css, 'Definisi gaya solid hilang.');
        $this->assertStringContainsString('@font-face', $css, '@font-face hilang — tidak ada glyph yang bisa dimuat.');
    }

    public function test_font_solid_mengarah_ke_berkas_subset(): void
    {
        // 12 Okt: CSS-nya sudah 19 KB tapi `src:` masih menunjuk fa-solid-900.woff2
        // (146,6 KB, 1.391 glyph) padahal halaman hanya menuntut 71 codepoint.
        // woff2 sudah Brotli di dalam filenya, jadi mod_deflate hosting tidak bisa
        // menolongnya — satu-satunya jalan adalah memangkas glyph.
        $css = $this->cssSubset();

        $this->assertMatchesRegularExpression(
            '/url\(\.\.\/webfonts\/pradana-solid-900\.woff2\)/',
            $css,
            'Rujukan ke font subset hilang — halaman akan menyedot font penuh 146 KB lagi.'
        );
        $this->assertStringNotContainsString(
            'fa-solid-900.woff2',
            $css,
            'Masih ada rujukan ke font penuh; subset font tidak terpakai.'
        );

        $font = public_path('vendor/font-awesome/webfonts/pradana-solid-900.woff2');
        $this->assertFileExists($font, 'Jalankan: php bin/buat-subset-ikon.php');
        $this->assertLessThan(20_000, filesize($font), 'Font subset mencurigakan besar — mungkin tidak dipangkas.');

        // Rujukan .ttf dibuang sekaligus: berkasnya tidak pernah di-commit, jadi tiap
        // browser tanpa woff2 hanya menghasilkan 404.
        $this->assertStringNotContainsString('.ttf', $css, 'Rujukan .ttf mati: berkasnya tidak ada di repo.');
    }

    public function test_codepoint_font_dijaga_oleh_pembuatnya_bukan_phpunit(): void
    {
        // PHPUnit tidak bisa membaca isi woff2 (butuh Brotli + parser font), jadi
        // pemeriksaan "setiap codepoint yang dituntut CSS ada di glyph" hidup di
        // bin/cek-kodepoin-font.py dan dipanggil otomatis oleh pembuat subset.
        // Yang bisa dikunci di sini: berkas pemeriksanya masih ada dan CSS memang
        // menuntut codepoint (kalau tidak, tidak ada yang perlu diperiksa).
        $this->assertFileExists(base_path('bin/cek-kodepoin-font.py'));

        preg_match_all('/content:"\\\\\s*([0-9a-fA-F]{4,6})/', $this->cssSubset(), $m);
        $this->assertGreaterThan(30, count(array_unique($m[1])), 'Subset CSS tidak menuntut glyph apa pun.');
    }

    public function test_layout_tidak_memuat_all_min_css_lagi(): void
    {
        foreach (['layouts/app', 'auth/login', 'errors/layout'] as $layout) {
            $isi = (string) file_get_contents(resource_path('views/'.$layout.'.blade.php'));
            $this->assertStringNotContainsString(
                'font-awesome/css/all.min.css',
                $isi,
                "{$layout} masih memuat 99,6 KB penuh padahal subset tersedia."
            );
        }
    }
}
