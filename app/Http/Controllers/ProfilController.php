<?php

namespace App\Http\Controllers;

use App\Http\Requests\GantiPinRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Profil pengguna yang sedang login. Sekarang cuma satu hal: ganti PIN sendiri
 * (L-12 / A5=a) — nama & email sengaja tidak bisa diubah staf, karena email
 * adalah kredensial login dan satu akun = satu orang di kantor.
 *
 * Sejak 4 Okt 2026 tidak ada halaman khusus: formulirnya popup "Ganti PIN" di
 * menu Akun (lihat resources/views/layouts/app.blade.php), jadi jawabannya JSON
 * kalau yang memanggil AJAX, dan redirect + flash kalau JavaScript mati.
 */
class ProfilController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function updatePin(GantiPinRequest $request): RedirectResponse|JsonResponse
    {
        $request->user()->update(['pin' => Hash::make($request->validated('pin'))]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'PIN Anda berhasil diganti.']);
        }

        return redirect()->route('dashboard')->with('success', 'PIN Anda berhasil diganti.');
    }
}
