<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\PengaturanInstansi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login & logout — email + PIN 8 digit, BUKAN password konvensional (L-11).
 * `User::getAuthPassword()` di-override ke kolom `pin`, jadi `Auth::attempt()`
 * tetap pakai kredensial biasa (dicek ke `User.php` asli 28 Agu 2026).
 *
 * Tanpa "ingat saya": computer kantor dipakai bersama, dan kolom
 * `remember_token` sudah dibuang dari skema (L-11 + squash S11).
 *
 * Pembuatan akun SENGAJA tidak di sini: admin yang membuatkan akun lewat
 * `UserController@store` (L-10/A3 sudah final 28 Agu 2026, bukan self-register).
 *
 * View `auth.login` berdiri sendiri (tidak pakai `layouts.app`) supaya halaman
 * login bisa dibuka tanpa sesi. `$instansi` (baris tunggal `pengaturan_instansi`)
 * dikirim ke view untuk nama + logo instansi — pola akses baris tunggal yang sama
 * dengan `PengaturanInstansiController` (`first()`/`firstOrFail()`, sudah
 * di-cross-check 31 Agu 2026).
 */
class AuthController extends Controller
{
    /**
     * Tampilkan form login, sertakan data instansi (nama/logo) untuk branding.
     */
    public function create(): View
    {
        return view('auth.login', [
            'instansi' => PengaturanInstansi::first(),
        ]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Route 'dashboard' sudah ada per 28 Agu 2026 (DashboardController), lihat AGENTS_HISTORY.md 12.15
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
