<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang "hanya admin" untuk route admin.
 *
 * Isinya sengaja cuma satu pengecekan biner `User::isAdmin()`, BUKAN matriks
 * per-modul×per-role: role dipangkas jadi 2 tingkat — `admin` (= kepala,
 * berhak nyata) dan `pegawai`. Karena itu juga tidak ada Policy per-model:
 * semuanya akan cuma mengulang satu cek yang sama sebelas kali.
 * `app/Policies/LampiranPolicy.php` (scaffold kosong + aturan kepemilikan yang
 * melarang staf membuka arsip kantor) sudah dihapus.
 *
 * Dipakai di `routes/web.php` sebagai alias `admin` (didaftarkan di
 * `app/Http/Kernel.php`, karena project ini masih pakai struktur bootstrap lama).
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
