<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * K16 (4 Okt 2026): halaman error 403/404/419/500/503 khusus berbahasa
 * Indonesia. Yang diuji di sini bukan cuma "isinya ada", tapi dua sifat yang
 * bikin halaman error berguna di kantor: (1) dilewati aplikasi betulan (404,
 * 403 dari middleware admin, 503 dari mode pemeliharaan), dan (2) bisa
 * dirender TANPA tahu siapa yang login — klaim "berdiri sendiri" di
 * resources/views/errors/layout.blade.php.
 */
class HalamanErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_tidak_dikenal_menggunakan_halaman_sendiri(): void
    {
        $this->get('/Alamat-Yang-Tidak-Pernah-Ada')
            ->assertNotFound()
            ->assertSee('Halaman atau arsip ini tidak ditemukan')
            ->assertSee('Pencarian Arsip');
    }

    public function test_pegawai_yang_membuka_halaman_admin_diberi_penjelasan(): void
    {
        $pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        // `abort_unless(..., 403)` di EnsureIsAdmin -> HttpException -> errors/403.blade.php
        $this->actingAs($pegawai)
            ->get(route('aktivitas.index'))
            ->assertForbidden()
            ->assertSee('Halaman ini tidak boleh dibuka dari akun Anda')
            ->assertSee('admin (kepala desa/lurah)');
    }

    public function test_mode_pemeliharaan_menggunakan_halaman_sendiri(): void
    {
        Artisan::call('down');

        try {
            $this->get('/dashboard')
                ->assertStatus(503)
                ->assertSee('Sedang dipelihara sebentar');
        } finally {
            // Kalau gagal di tengah dan `down` tertinggal, seluruh suite
            // berikutnya ikut rusak (semua request jadi 503).
            Artisan::call('up');
        }

        $this->get('/login')->assertOk();
    }

    public function test_semua_halaman_error_bisa_dirender_tanpa_ada_yang_login(): void
    {
        // Ini alasan utama layout error tidak @extends('layouts.app'):
        // layout utama memanggil auth()->user()->isAdmin() + query pengajuan,
        // yang justru bisa gagal lagi di atas halaman error (mis. DB mati).
        foreach (['403', '404', '419', '500', '503'] as $kode) {
            $html = view("errors.$kode")->render();

            $this->assertStringContainsString('<html lang="id">', $html, "errors/$kode tidak berbahasa Indonesia");
            $this->assertStringContainsString($kode, $html, "errors/$kode tidak menampilkan kodenya");
            $this->assertStringContainsString(' Arsip Surat Digital', $html);
            $this->assertStringNotContainsString('logout', $html, "errors/$kode memakai form POST (butuh token CSRF)");
        }
    }

    public function test_halaman_error_tidak_membocorkan_isi_sistem(): void
    {
        foreach (['403', '404', '419', '500', '503'] as $kode) {
            $html = view("errors.$kode")->render();

            // $exception tersedia di view error — kalau ikut dicetak, nama
            // tabel/path server terbaca orang luar.
            $this->assertStringNotContainsString('$exception', $html);
            $this->assertStringNotContainsString('storage/app', $html);
            $this->assertStringNotContainsString('sqlstate', mb_strtolower($html));
        }
    }
}
