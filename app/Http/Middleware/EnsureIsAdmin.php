<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * BARU — 1 Sep 2026. Implementasi B1 [LOCKED #16, lihat Bagian 8 AGENTS.md]
 * versi disederhanakan: HANYA 2 tingkat (admin vs non-admin), dikonfirmasi
 * user 1 Sep 2026. `kepala` dan `perangkat` diperlakukan SAMA (non-admin) —
 * kolom `users.role` TETAP 3 nilai (skema Bagian 5 tidak berubah), middleware
 * ini cuma mengecek satu hal: apakah user yang login itu admin atau bukan.
 *
 * Menggantikan pola ad-hoc `ensureAdmin()`/`abort_unless(...->role==='admin',403,...)`
 * yang tersebar di beberapa controller (Klasifikasi ×3 sejak 12.11, Pengaturan
 * Instansi sejak 12.18, kemungkinan SuratMasukController::destroy() untuk B2) —
 * SEMUA `// TODO(B1)` di file-file itu sekarang bisa diganti middleware ini,
 * TAPI retrofit-nya BELUM dilakukan di sesi ini (controller aslinya tidak
 * diupload) — lihat 12.23 di AGENTS_HISTORY.md.
 *
 * Pakai helper `User::isAdmin()` yang sudah terkonfirmasi ada di model asli
 * (28 Agu 2026, lihat 12.14) — bukan tebakan baru.
 *
 * CARA PAKAI setelah diregistrasikan (lihat snippet-registrasi-observer-1sep2026.php):
 *   Route::middleware(['auth', 'admin'])->group(function () { ... });
 * atau per-route:
 *   Route::delete('klasifikasi-primer/{klasifikasi_primer}', [...])->middleware('admin');
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
