<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\PengaturanInstansi;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login & logout — sesuai A1 [DEFAULT]: email + PIN, BUKAN password konvensional.
 * Dicek 28 Agu 2026 terhadap User.php asli: getAuthPassword() sudah benar,
 * kredensial 'pin' di LoginRequest cocok tanpa perubahan.
 *
 * "Registrasi" user baru SENGAJA dipisah ke UserController, bukan di sini —
 * lihat A3 [WAJIB TANYA USER] (asumsi sementara "approval admin" dipakai di
 * UserController, bukan self-register publik lewat controller ini — MASIH
 * belum dikonfirmasi user).
 *
 * View `auth.login` sudah dibuat (Blade+Bootstrap standalone, G1 [LOCKED #15]),
 * lihat resources/views/auth/login.blade.php.
 *
 * `create()` mengirim `$instansi` (baris tunggal `pengaturan_instansi`, sesuai
 * Bagian 5 AGENTS.md) supaya nama/logo instansi tampil di panel login —
 * BUKAN dari AuthController::create() versi sebelumnya (dulu view() polos
 * tanpa data). ⚠️ `PengaturanInstansi::first()` ASUMSI cara akses baris
 * tunggal (konsisten dengan desain edit-only tanpa create/store, Bagian 5/8
 * AGENTS.md) — belum di-cross-check langsung ke `PengaturanInstansiController`
 * asli karena file itu tidak ada di sesi yang sama dengan perubahan ini.
 * TODO: cocokkan pola akses ini kalau `PengaturanInstansiController.php` di-upload.
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

        // Route 'dashboard' sudah ada per 28 Agu 2026 (DashboardController), lihat AGENTS.md 12.15
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
