<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPinRequest;
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

    /**
     * L-12 (A4): admin menetapkan PIN baru untuk user yang lupa PIN.
     * Tidak ada alur reset lewat email di aplikasi ini (kantor tanpa SMTP),
     * jadi reset oleh admin satu-satunya jalan keluar — dan PIN tidak bisa
     * dibaca ulang, hanya bisa diganti.
     *
     * Untuk dirinya sendiri, user memakai halaman "Ganti PIN"
     * (`ProfilController@editPin`) yang meminta PIN lama. Jalur admin di sini
     * sengaja TIDAK bertanya PIN lama: situasinya justru "lupa".
     */
    public function updatePin(ResetPinRequest $request, User $user): RedirectResponse
    {
        $user->update(['pin' => Hash::make($request->validated('pin'))]);

        return back()->with('status', "PIN {$user->nama_lengkap} sudah diganti. Sampaikan PIN barunya secara langsung.");
    }

    /**
     * Hapus (soft-delete) user — admin only (middleware `admin` di route).
     * L-10/B8: user boleh dihapus walau sudah punya surat, karena tidak ada
     * sistem kepemilikan (semua arsip milik kantor); log aktivitas yang jadi
     * penjaga jejaknya. Relasi `petugas()` di model surat sudah `withTrashed()`
     * sehingga nama petugas lama tetap terbaca di daftar surat.
     *
     * Admin tidak boleh menghapus akunnya sendiri — view menyembunyikan
     * tombolnya, cek di sini penjaga belakangnya.
     */
    public function destroy(\Illuminate\Http\Request $request, User $user): RedirectResponse
    {
        abort_if(
            $user->id === $request->user()->id,
            403,
            'Anda tidak bisa menghapus akun Anda sendiri.'
        );

        $user->delete(); // soft delete (model pakai SoftDeletes)

        return redirect()->route('users.index')->with('status', "Akun {$user->nama_lengkap} berhasil dihapus.");
    }
}
