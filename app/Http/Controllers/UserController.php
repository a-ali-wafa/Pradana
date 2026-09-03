<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Manajemen user. Saat ini cuma index/create/store — "registrasi" user baru
 * oleh admin, sesuai asumsi sementara A3 (lihat StoreUserRequest untuk detail
 * & TODO konfirmasi — MASIH BELUM DIKONFIRMASI USER). Edit/delete/ganti-PIN
 * SENGAJA belum dibuat, di luar scope "Login/Register/Logout" yang diminta
 * sesi ini (lihat A4/A5 di Bagian 10 — "reset PIN oleh admin" & "user ganti
 * PIN sendiri" — belum diimplementasikan).
 *
 * Admin-only — ditangani oleh middleware('admin') di routes/web.php
 * (retrofit B1, 1 Sep 2026).
 */
class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('nama_lengkap')->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
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
        $validated = $request->validated();

        User::create([
            'nama_lengkap' => $validated['nama_lengkap'],
            'email'        => $validated['email'],
            'pin'          => Hash::make($validated['pin']),
            'role'         => $validated['role'],
        ]);

        return redirect()->route('users.index')->with('status', 'User baru berhasil ditambahkan.');
    }
}
