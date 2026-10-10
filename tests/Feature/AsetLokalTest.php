<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aset frontend dimuat dari aplikasi sendiri, bukan dari CDN asing
 * (P0#1 di `docs/daftar-peningkatan.md`, dikerjakan 10 Okt 2026).
 *
 * Kenapa ini perlu dijaga, dan dijaga dengan tes: sampai 10 Okt seluruh
 * `layouts/app`, `auth/login`, dan `errors/layout` mengambil Bootstrap 5.3.3,
 * Font Awesome 6.4.0, dan SweetAlert2 dari cdn.jsdelivr.net / cdnjs.
 * Konsekuensinya di kantor dengan satu ISP:
 *  - internet/DNS bermasalah -> arsip tetap bisa dibuka tapi TANPA tata letak,
 *    dan semua tombol destruktif diam (konfirmasi hidup di SweetAlert2 dan tidak
 *    ada satu pun guard `typeof Swal`),
 *  - `sweetalert2@11` itu tag mengambang -> browser staf menerima versi rilis
 *    terbaru tanpa diff di repo ini; sekarang versinya tertulis di file dan di
 *    `?v=`,
 *  - halaman error (justru yang paling sering dilihat saat server bermasalah)
 *    ikut butuh dua host asing untuk tampil benar.
 *
 * Empat tes di bawah menutup tiga cara berbeda perubahan ini bisa gagal senyap:
 * tag diganti tapi filenya belum diunduh, filenya ada tapi kosong/rusak, dan
 * seseorang menambah link CDN lagi suatu hari nanti.
 */
class AsetLokalTest extends TestCase
{
    use RefreshDatabase;

    /** Berkas yang harus ada di public/vendor, dengan ukuran minimum sanity. */
    private const BERKAS = [
        'vendor/bootstrap/bootstrap.min.css' => 200_000,
        'vendor/bootstrap/bootstrap.bundle.min.js' => 60_000,
        'vendor/sweetalert2/sweetalert2.all.min.js' => 60_000,
        'vendor/font-awesome/css/all.min.css' => 80_000,
        'vendor/font-awesome/webfonts/fa-solid-900.woff2' => 100_000,
        'vendor/font-awesome/webfonts/fa-regular-400.woff2' => 20_000,
        'vendor/font-awesome/webfonts/fa-brands-400.woff2' => 80_000,
        'vendor/font-awesome/webfonts/fa-v4compatibility.woff2' => 3_000,
    ];

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Aset',
            'email' => 'staf-aset@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);
    }

    /**
     * Sumber Blade TANPA komentar.
     *
     * Ini pelajaran yang sudah dibeli dua kali di project ini (FlashKonsistenTest,
     * Fase 6): guardrail teks harus membaca KODE, bukan komentar. Comment block
     * `layouts/app.blade.php` menyebut nama kedua CDN lama sebagai penjelasan
     * kenapa mereka tidak dipakai lagi — kalau komentarnya tidak dibuang, tes ini
     * menegur file yang justru mendokumentasikan perbaikannya.
     */
    private function bladeTanpaKomentar(string $path): string
    {
        $isi = file_get_contents($path);

        return (string) preg_replace(
            '/\{\{--.*?--\}\}/s',
            '',
            (string) preg_replace('/<!--.*?-->/s', '', $isi)
        );
    }

    /** @return list<string> path view blade di resources/views */
    private function views(): \Generator
    {
        $finder = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($finder as $file) {
            // `getExtension()` pada "index.blade.php" menghasilkan "php", bukan
            // "blade.php" — karena itu ujung namanya dicocokkan sendiri. Kalau
            // pakai getExtension(), scan ini menemukan NOL file dan "bersih"
            // terbaca seperti sukses (persis yang guard di bawah tangkap).
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file->getPathname();
            }
        }
    }

    public function test_satu_pun_view_tidak_mengarahkan_aset_ke_host_asing(): void
    {
        $pola = '/https?:\/\/(cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com|maxcdn\.bootstrapcdn\.com|unpkg\.com|code\.jquery\.com)/i';

        $tersalah = [];
        $jumlah = 0;

        foreach ($this->views() as $path) {
            $jumlah++;

            if (preg_match($pola, $this->bladeTanpaKomentar($path), $cocok)) {
                $tersalah[] = basename($path).' -> '.$cocok[0];
            }
        }

        // Guard terhadap scan yang tidak memindai apa-apa: kalau glob-nya rusak,
        // "bersih" akan terbaca seperti sukses.
        $this->assertGreaterThan(20, $jumlah, 'Scan tidak menemukan view — tes ini tidak mengukur apa-apa.');

        $this->assertSame([], $tersalah, 'Aset frontend harus dari public/vendor, bukan CDN.');
    }

    public function test_aset_dimuat_dari_disk_sendiri_dan_ukurnya_waras(): void
    {
        foreach (self::BERKAS as $relatif => $minimum) {
            $path = public_path($relatif);

            $this->assertFileExists($path, 'Tag <link>/<script> menunjuk ke sini, tapi filenya tidak ada.');
            $this->assertGreaterThanOrEqual(
                $minimum,
                filesize($path),
                $relatif.' terlalu kecil — ciri khas berkas hasil unduhan yang malah menyimpan halaman error HTML.'
            );
        }

        // Style/JS yang kepotong jadi halaman error HTML tetap "bisa dimuat"
        // browser dan merusak layout tanpa pesan; karena itu isinya juga dicek.
        $css = (string) file_get_contents(public_path('vendor/bootstrap/bootstrap.min.css'));
        $this->assertStringContainsString('.container', $css);

        $swal = (string) file_get_contents(public_path('vendor/sweetalert2/sweetalert2.all.min.js'));

        // Versi dibaca dari banner berkasnya, bukan dari string panjang yang
        // ditempel ke pesan gagal: assert di bawah ini mencetak satu nomor,
        // bukan 79 KB minified JavaScript, kalau versinya berubah.
        preg_match('/sweetalert2 v([0-9.]+)/i', $swal, $versi);
        $this->assertSame('11.26.25', $versi[1] ?? null, 'Versi SweetAlert2 berubah tanpa dicatat di AGENTS.md.');

        // Tag mengambang `sweetalert2@11` adalah salah satu yang dibuang: kalau
        // file yang ter-commit tidak menyebut versi, tidak ada yang tahu apa yang
        // diterima kantor saat deploy berikutnya.
        $this->assertStringNotContainsString('<!DOCTYPE html>', $swal);
    }

    public function test_font_awesome_memanggil_webfont_yang_sebenarnya_ada(): void
    {
        // Font Awesome 6 menyimpan icon di `css/../webfonts/`. Memindah CSS tanpa
        // webfont-nya (atau salah menyusun folder) tidak melempar error apa pun:
        // halamannya tampil, cuma semua icon hilang — dan staf mengira aplikasinya
        // belum selesai dipasang.
        $css = (string) file_get_contents(public_path('vendor/font-awesome/css/all.min.css'));

        preg_match_all('#webfonts/([A-Za-z0-9_-]+\.woff2)#', $css, $cocok);

        $this->assertNotEmpty($cocok[1], 'CSS-nya tidak menyebut webfonts sama sekali — file yang diunduh bukan all.min.css.');

        foreach (array_unique($cocok[1]) as $font) {
            $this->assertFileExists(
                public_path('vendor/font-awesome/webfonts/'.$font),
                'CSS Font Awesome memanggil '.$font.'; tanpa file itu semua icon kosong.'
            );
        }

        // .ttf memang TIDAK diunduh (browser modern mengambil woff2 lebih dulu),
        // jadi tes ini sengaja hanya memeriksa woff2 — menambah .ttf cuma
        // membengkakkan repo tanpa perubahan perilaku.
        $this->assertFileDoesNotExist(public_path('vendor/font-awesome/webfonts/fa-solid-900.ttf'));
    }

    public function test_tiga_layout_memuat_vendor_lokal(): void
    {
        // Login dan halaman error tidak butuh koneksi ke apa pun selain server
        // kantor sendiri; itu justru layar yang dipakai saat keadaan bermasalah.
        $this->get('/login')
            ->assertOk()
            ->assertSee('vendor/bootstrap/bootstrap.min.css', false)
            ->assertSee('vendor/font-awesome/css/all.min.css', false);

        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index'))
            ->assertOk()
            ->assertSee('vendor/sweetalert2/sweetalert2.all.min.js', false)
            ->assertSee('vendor/bootstrap/bootstrap.bundle.min.js', false);

        $this->get('/AlamatTidakAda123')
            ->assertNotFound()
            ->assertSee('vendor/bootstrap/bootstrap.min.css', false);
    }
}
