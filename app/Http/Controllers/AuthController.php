<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\PengaturanInstansi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login & logout — email + PIN 8 digit, BUKAN password konvensional.
 * `User::getAuthPassword()` di-override ke kolom `pin`, jadi `Auth::attempt()`
 * tetap pakai kredensial biasa Laravel.
 *
 * Tanpa "ingat saya": komputer kantor dipakai bersama, dan kolom
 * `remember_token` sudah dibuang dari skema.
 *
 * Pembuatan akun SENGAJA tidak di sini: admin yang membuatkan akun lewat
 * `UserController@store`, bukan pendaftaran mandiri lewat halaman login.
 *
 * View `auth.login` berdiri sendiri (tidak pakai `layouts.app`) supaya halaman
 * login bisa dibuka tanpa sesi. `$instansi` (baris tunggal `pengaturan_instansi`)
 * dikirim ke view untuk nama + logo instansi — pola akses baris tunggal yang sama
 * dengan `PengaturanInstansiController` (`first()`/`firstOrFail()).
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

        // Route 'dashboard' sudah ada (DashboardController)
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
