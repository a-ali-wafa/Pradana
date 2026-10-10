<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anti-indexing + header keamanan (9 Okt 2026).
 *
 * Alasannya bukan formalitas: berkas `public/robots.txt` bawaan Laravel berisi
 * `User-agent: *` + `Disallow:` KOSONG, dan nilai kosong itu berarti crawler
 * boleh mendatangi semua halaman — persis kebalikan dari yang dibutuhkan arsip
 * surat kantor. Karena itu robots.txt saja tidak dipercaya: penenang utamanya
 * header `X-Robots-Tag` yang dipasang middleware di SETIAP respons (berkas
 * statis bisa tertimpa versi bawaan saat deploy tanpa ada yang sadar).
 *
 * CSP diuji sekaligus pada hal-hal yang justru gampang patah karena CSP:
 * pratinjau logo (`blob:`), script/`onclick` inline di Blade, dan CDN Bootstrap/
 * Font Awesome. Kalau salah satu dilepas, halaman terlihat normal di laptop dan
 * rusak di kantor.
 */
class HeaderKeamananTest extends TestCase
{
    use RefreshDatabase;

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);
    }

    private function assertDitandaiPrivat(string $url, ?User $atasNama = null): void
    {
        $respons = ($atasNama ? $this->actingAs($atasNama) : $this)->get($url);

        $robots = $respons->headers->get('X-Robots-Tag');

        $this->assertNotNull($robots, "Respons {$url} tidak membawa X-Robots-Tag sama sekali.");
        $this->assertStringContainsString('noindex', $robots);
        $this->assertStringContainsString('nofollow', $robots);
        $this->assertStringContainsString('noarchive', $robots, 'Salinan isi surat tidak boleh disimpan mesin pencari.');

        $respons->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin');
    }

    public function test_halaman_login_ditandai_jangan_di_index(): void
    {
        // Satu-satunya halaman yang bisa dibuka tamu — dan karena itu justru
        // paling menarik bagi crawler.
        $this->assertDitandaiPrivat(route('login'));

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    public function test_halaman_setelah_login_ikut_ditandai(): void
    {
        $this->assertDitandaiPrivat(route('dashboard'), $this->pegawai);
        $this->assertDitandaiPrivat(route('surat-masuk.index'), $this->pegawai);
    }

    public function test_route_publik_logo_juga_ditandai(): void
    {
        // Rutenya sengaja di luar grup auth (halaman login butuh), jadi header
        // harus datang dari middleware web, bukan dari pagar login.
        $this->assertDitandaiPrivat(route('instansi.logo'));
    }

    public function test_halaman_error_juga_ditandai(): void
    {
        $this->assertDitandaiPrivat('/AlamatTidakAda123');

        // Layout error berdiri sendiri (sengaja tidak @extends layouts.app),
        // jadi meta robots-nya harus terbukti ada di sana juga.
        $this->get('/AlamatTidakAda123')
            ->assertNotFound()
            ->assertSee('name="robots" content="noindex', false);
    }

    public function test_daftar_surat_yang_menggunakan_inline_script_tetap_ada_izinya(): void
    {
        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index'))
            ->assertOk();

        $csp = $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index'))
            ->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("'unsafe-inline'", $csp, 'Blade memakai @push(scripts) dan onclick inline.');
        $this->assertStringContainsString('blob:', $csp, 'Pratinjau logo memakai URL.createObjectURL.');
        // 10 Okt 2026: Bootstrap/SweetAlert2/Font Awesome dilokalkan ke
        // public/vendor, jadi dua host CDN itu tidak lagi punya alasan berada di
        // dalam CSP. Arah assertnya sengaja dibalik: yang diizinkan sekarang
        // HANYA 'self' — kalau ada orang menambah host asing lagi, tes ini yang
        // bertanya lebih dulu (lihat AsetLokalTest untuk sisi view-nya).
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $csp);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com', $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_csp_bisa_dimatikan_lewat_config_saat_diagnosis(): void
    {
        config(['security.csp_aktif' => false]);

        $respons = $this->actingAs($this->pegawai)->get(route('dashboard'));

        // Yang lain tetap terpasang — yang mati hanya kebijakan konten.
        $this->assertNull($respons->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('noindex', (string) $respons->headers->get('X-Robots-Tag'));
    }

    public function test_robots_txt_melarang_seluruh_halaman(): void
    {
        $isi = (string) file_get_contents(base_path('public/robots.txt'));

        // Tanpa `/` di belakang Disallow, isinya sama dengan "silakan jelajahi
        // semua" — jebakan versi bawaan Laravel.
        $this->assertMatchesRegularExpression('/^Disallow:\s*\/\s*$/mi', $isi);

        // Berkomentar "jangan tambah sitemap" boleh; direktifnya yang tidak boleh.
        $this->assertDoesNotMatchRegularExpression('/^Sitemap:\s*\S/mi', $isi, 'Arsip kantor tidak boleh punya sitemap.');
    }
}
