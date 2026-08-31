<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
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
 * Belum ada view (`auth.login`) — item roadmap terpisah, tergantung G1
 * (Blade+Bootstrap vs stack lain, juga masih [WAJIB TANYA USER]).
 */
class AuthController extends Controller
{
    /**
     * Tampilkan form login. Placeholder sampai view dibuat & G1 dikonfirmasi.
     */
    public function create(): View
    {
        return view('auth.login');
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
