<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Manajemen user. Saat ini cuma index/create/store — "registrasi" user baru
 * oleh admin, sesuai asumsi sementara A3 (lihat StoreUserRequest untuk detail
 * & TODO konfirmasi — MASIH BELUM DIKONFIRMASI USER). Edit/delete/ganti-PIN
 * SENGAJA belum dibuat, di luar scope "Login/Register/Logout" yang diminta
 * sesi ini (lihat A4/A5 di Bagian 10 — "reset PIN oleh admin" & "user ganti
 * PIN sendiri" — belum diimplementasikan).
 *
 * Admin-only manual check (ensureAdmin()) — TODO(B1), pola sama seperti
 * KlasifikasiPrimerController/dst, BUKAN middleware/Gate resmi karena B1
 * (matriks permission detail role) masih [WAJIB TANYA USER].
 */
class UserController extends Controller
{
    /**
     * Dicek 28 Agu 2026 terhadap User.php asli: helper isAdmin() sudah ada
     * di model, dipakai di sini alih-alih cek role manual.
     */
    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403, 'Hanya admin yang boleh mengelola user.');
    }

    public function index(): View
    {
        $this->ensureAdmin();

        $users = User::orderBy('nama_lengkap')->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $this->ensureAdmin();

        return view('users.create');
    }

    /**
     * "Registrasi" user baru oleh admin (bukan self-register — lihat asumsi A3
     * di StoreUserRequest, MASIH belum dikonfirmasi user).
     *
     * ✅ Dicek 28 Agu 2026 terhadap User.php asli: TIDAK ada mutator
     * setPinAttribute() di model, jadi Hash::make() di bawah memang perlu ada
     * (tanpa ini PIN tersimpan plain text). Bukan risiko double-hash.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->ensureAdmin();

        $validated = $request->validated();

        User::create([
            'nama_lengkap' => $validated['nama_lengkap'],
            'email' => $validated['email'],
            'pin' => Hash::make($validated['pin']),
            'role' => $validated['role'],
        ]);

        return redirect()->route('users.index')->with('status', 'User baru berhasil ditambahkan.');
    }
}
