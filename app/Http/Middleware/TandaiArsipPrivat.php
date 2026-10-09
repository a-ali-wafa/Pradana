<?php

namespace App\Http\Middleware;

use App\Support\TandaiPrivat;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PRADANA bukan website publik: ini arsip surat kantor di belakang login, dan
 * satu-satunya hal yang benar untuk mesin pencari adalah TIDAK ADA yang
 * ter-index (sejalan dengan L-01 — tidak ada tautan publik; isi surat kantor
 * tidak boleh berpindah ke internet).
 *
 * Kenapa header dan bukan cuma `public/robots.txt`:
 *
 * 1. `robots.txt` hanyalah larangan MERAYAPI, dan dia berkas statis. Versi
 *    bawaan Laravel isinya `Disallow:` KOSONG — yang berarti "silakan jelajahi
 *    semua" — dan file itu gampang tertimpa saat deploy tanpa ada yang sadar
 *    (itu persis keadaan sebelum 9 Okt 2026).
 * 2. `noindex` menang atas segalanya: kalaupun crawler tetap datang (bookmark,
 *    tautan dari email, plugin), halamannya tidak masuk indeks.
 * 3. `noarchive` supaya salinan isi surat tidak disimpan; `nofollow` supaya URL
 *    turunan (page, sampah, cetak) tidak dirayapi.
 *
 * Aplikasi tidak pernah menyemai `sitemap.xml`, dan itu memang tidak boleh
 * ditambahkan. Kalau nanti ada keputusan baru untuk membuka satu halaman publik
 * (mis. layanan warga mengecek status surat), JANGAN lepas middleware ini
 * secara global — pindahkan ke per-route dan putuskan halaman mana yang boleh.
 *
 * Respons error TIDAK lewat sini (lihat App\Support\TandaiPrivat); dia dipasang
 * di App\Exceptions\Handler::render().
 */
class TandaiArsipPrivat
{
    public function handle(Request $request, Closure $next): Response
    {
        $respons = $next($request);

        if ($respons instanceof Response) {
            TandaiPrivat::terapkan($respons);
        }

        return $respons;
    }
}
