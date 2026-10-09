<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPinRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Manajemen akun kantor — admin saja (A3/B3 [LOCKED]; middleware('admin') di
 * `routes/web.php:112-114` membatasi route-nya ke index/create/store/destroy).
 *
 * Yang ADA: membuat akun (store), menghapus akun (destroy), dan reset PIN oleh
 * admin (updatePin — jalur terpisah `PATCH users/{user}/pin`, L-12/A4, karena
 * "ganti PIN sendiri" milik semua role dan hidup di `ProfilController`).
 *
 * Yang SENGAJA tidak ada: `edit`/`update`. Nama, email, dan peran tidak bisa
 * diubah lewat layar setelah akun dibuat — kantor kecil, satu admin, dan
 * kombinasi "hapus + buat ulang" sudah menutup kebutuhan itu tanpa membuat
 * form baru. Konsekuensi yang perlu diketahui saat serah terima: mengubah peran
 * pegawai → admin berarti menghapus akunnya (log aktivitas tetap menyebut id user
 * lama; L-10: user boleh dihapus walau punya surat) lalu membuat ulang.
 *
 * Docblock ini diganti 9 Okt 2026 karena versi sebelumnya masih mengaku
 * "edit/delete/ganti-PIN SENGAJA belum dibuat" dan merujuk "Bagian 10" versi lama
 * AGENTS.md — keduanya sudah tidak benar sejak 4 Okt (L-07 & L-12 selesai), dan
 * komentar yang salah lebih berbahaya daripada tidak ada komentar.
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
            'email' => $validated['email'],
            'pin' => Hash::make($validated['pin']),
            'role' => $validated['role'],
        ]);

        return redirect()->route('users.index')->with('success', 'User baru berhasil ditambahkan.');
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

        return back()->with('success', "PIN {$user->nama_lengkap} sudah diganti. Sampaikan PIN barunya secara langsung.");
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
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if(
            $user->id === $request->user()->id,
            403,
            'Anda tidak bisa menghapus akun Anda sendiri.'
        );

        $user->delete(); // soft delete (model pakai SoftDeletes)

        return redirect()->route('users.index')->with('success', "Akun {$user->nama_lengkap} berhasil dihapus.");
    }
}
