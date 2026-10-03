<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang "hanya admin" untuk route admin (L-07 / lama B1 [LOCKED #16]).
 *
 * Isinya sengaja cuma satu pengecekan biner `User::isAdmin()`, BUKAN matriks
 * per-modul×per-role: user memutuskan (3 Okt 2026) bahwa role dipangkas jadi
 * 2 tingkat — `admin` (= kepala, berhak nyata) dan `pegawai`. Karena itu juga
 * tidak ada Policy per-model: semuanya akan cuma mengulang satu cek yang sama
 * sebelas kali. `app/Policies/LampiranPolicy.php` (scaffold kosong + aturan
 * kepemilikan yang ternyata bertentangan dengan L-09/L-10) sudah dihapus 4 Okt 2026.
 *
 * Dipakai di `routes/web.php` sebagai alias `admin` (didaftarkan di
 * `app/Http/Kernel.php`, lihat catatan K3 AGENTS.md — project ini masih
 * pakai struktur bootstrap lama).
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            Auth::check() && Auth::user()->isAdmin(),
            403,
            'Hanya admin yang boleh mengakses halaman ini.'
        );

        return $next($request);
    }
}
