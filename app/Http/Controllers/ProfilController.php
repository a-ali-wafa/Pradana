<?php

namespace App\Http\Controllers;

use App\Http\Requests\GantiPinRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Profil pengguna yang sedang login. Sekarang cuma satu hal: ganti PIN sendiri
 * (L-12 / A5=a) — nama & email sengaja tidak bisa diubah staf, karena email
 * adalah kredensial login dan satu akun = satu orang di kantor.
 */
class ProfilController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function editPin(): View
    {
        return view('profil.ganti-pin');
    }

    public function updatePin(GantiPinRequest $request): RedirectResponse
    {
        $request->user()->update(['pin' => Hash::make($request->validated('pin'))]);

        return redirect()->route('dashboard')->with('status', 'PIN Anda berhasil diganti.');
    }
}
