<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guardrail `public/.htaccess` — dan kenapa ia ada.
 *
 * 12 Okt 2026: blok kompresi yang ditulis 12 Okt membuat SELURUH aplikasi menjawab
 * 500 di Apache. Dua kesalahan, keduanya tidak mungkin kelihatan di laptop sebelum
 * ada yang mencoba Apache sungguhan:
 *   1. `DeflateCompressionLevel 6` — ditolak di konteks .htaccess
 *      ("DeflateCompressionLevel not allowed here").
 *   2. `AddOutputFilterByType` dimiliki **mod_filter**, bukan mod_deflate, jadi
 *      blok yang hanya dijaga `<IfModule mod_deflate.c>` tetap meledak kalau
 *      mod_filter tidak terpasang ("Invalid command 'AddOutputFilterByType'").
 * `php artisan serve` tidak membaca .htaccess sama sekali — itu sebabnya bug kelas
 * ini lolos dari seluruh suite dan hanya muncul di server target.
 *
 * Aturan yang dipegang tes ini: satu baris yang tidak dikenali Apache = situs mati,
 * jadi direktif yang bermasalah tidak boleh masuk, dan direktif yang bergantung
 * modul harus selalu berada di dalam IfModule modul pemiliknya.
 */
class HtaccessApacheTest extends TestCase
{
    private function isi(): string
    {
        return (string) file_get_contents(public_path('.htaccess'));
    }

    /**
     * Direktif yang secara dokumentasi TIDAK boleh ditulis di .htaccess.
     *
     * Bentuknya array-of-array: PHPUnit menuntut satu array argumen per dataset.
     *
     * @return array<int, array<int, string>>
     */
    public static function direktifTerlarang(): array
    {
        return [
            ['DeflateCompressionLevel'],
            ['DeflateMemLevel'],
            ['DeflateFilterNote'],
            ['DeflateInflateLimitRequestBody'],
            ['CacheEnable'],
            ['CacheDisable'],
        ];
    }

    /**
     * @dataProvider direktifTerlarang
     */
    public function test_direktif_yang_ditolak_apache_di_htaccess_tidak_masuk(string $direktif): void
    {
        // Komentar penjelasan boleh menyebut namanya (persis file ini yang
        // menceritakan sejarahnya), tapi bukan sebagai direktif aktif: baris
        // direktif tidak diawali '#'.
        $aktif = array_filter(
            explode("\n", $this->isi()),
            static fn (string $baris): bool => ! str_starts_with(trim($baris), '#')
        );

        foreach ($aktif as $baris) {
            $this->assertStringNotContainsString(
                $direktif,
                $baris,
                "{$direktif} tidak diizinkan di .htaccess — Apache menjawab 500 untuk SEMUA request."
            );
        }
    }

    public function test_kompresi_dijaga_mod_filter_bukan_hanya_mod_deflate(): void
    {
        $isi = $this->isi();

        if (! str_contains($isi, 'AddOutputFilterByType')) {
            $this->markTestSkipped('Blok kompresi tidak ada — tidak ada yang perlu dijaga.');
        }

        // Ambil isi setiap blok <IfModule mod_filter.c> ... </IfModule>; setiap
        // AddOutputFilterByType harus berada di dalam salah satunya.
        $blok = [];
        preg_match_all('/<IfModule mod_filter\.c>(.*?)<\/IfModule>/s', $isi, $m);
        foreach ($m[1] as $isiBlok) {
            $blok[] = $isiBlok;
        }

        $gabung = implode("\n", $blok);
        $jumlah = substr_count($isi, 'AddOutputFilterByType');
        $this->assertGreaterThan(0, $jumlah, 'Tidak ada aturan kompresi sama sekali.');
        $this->assertSame(
            $jumlah,
            substr_count($gabung, 'AddOutputFilterByType'),
            'AddOutputFilterByType di luar <IfModule mod_filter.c>: hosting tanpa mod_filter akan 500.'
        );
        $this->assertStringContainsString('mod_deflate.c', $gabung, 'Kompresi tidak dijaga mod_deflate juga.');
    }

    public function test_aset_berganti_nama_membawa_query_stringversi(): void
    {
        // Aturan "cache 1 tahun" di .htaccess hanya aman selama SETIAP URL aset
        // membawa ?v=<versi>. Kalau ada yang menambah <link>/<script> aset lokal
        // tanpa versi, petugas bisa memegang berkas basi selamanya dan gejalanya
        // baru muncul setelah deploy berikutnya.
        $tanpaVersi = [];

        foreach (['layouts/app', 'auth/login', 'errors/layout'] as $layout) {
            $isi = (string) file_get_contents(resource_path('views/'.$layout.'.blade.php'));
            preg_match_all('#(?:href|src)="\{\{ asset\(\'.*?\'\) \}\}([^"]*)"#', $isi, $m);
            foreach ($m[1] as $ekor) {
                if (! str_contains($ekor, '?v=')) {
                    $tanpaVersi[] = $layout.': '.$ekor;
                }
            }
        }

        $this->assertSame([], $tanpaVersi, 'Aset lokal dimuat tanpa penanda versi padahal .htaccess menyimpannya 1 tahun.');
    }

    public function test_text_html_tidak_ikut_disimpan_browser(): void
    {
        $isi = $this->isi();

        $this->assertMatchesRegularExpression(
            '/ExpiresByType text\/html\s+"access plus 0 seconds"/',
            $isi,
            'Halaman HTML harus selalu dimuat ulang; kalau tidak, petugas melihat daftar basi sesudah orang lain input surat.'
        );
    }
}
