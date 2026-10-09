<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Satu tempat yang menetapkan "halaman ini jangan di-index".
 *
 * Dipakai dua pemanggil, dan keduanya harus ada:
 *
 * 1. `App\Http\Middleware\TandaiArsipPrivat` — respons normal.
 * 2. `App\Exceptions\Handler::render()` — respons error.
 *
 * Kenapa bukan middleware saja: exception di-render DI LUAR pipeline middleware
 * (Laravel menangkap throwable di kernel, bukan melanjutkan `$next()`), jadi
 * halaman 404/419/500/503 tidak pernah menyinggung header yang dipasang
 * middleware. Halaman-halaman itu justru yang paling sering dilihat crawler:
 * tautan basi, bookmark lama, dan URL hasil tebak-tebakan.
 */
class TandaiPrivat
{
    public static function terapkan(Response $respons): void
    {
        $respons->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet', true);

        // Tidak ada halaman yang boleh dibingkai situs lain — tombol "Setujui
        // pemusnahan arsip" adalah target clickjacking yang masuk akal.
        $respons->headers->set('X-Frame-Options', 'DENY');

        // Browser tidak boleh menebak sendiri tipe isi berkas lampiran yang
        // ekstensinya salah (mis. .pdf berisi HTML).
        $respons->headers->set('X-Content-Type-Options', 'nosniff');

        // Nomor surat dan kata kunci ada di URL; jangan ikut terkirim ke host
        // lain lewat referrer (CDN Bootstrap/Font Awesome dituju dari halaman ini).
        $respons->headers->set('Referrer-Policy', 'same-origin');

        // Nilainya di config/security.php. `env()` mengembalikan null begitu
        // `config:cache` jalan, jadi dibaca lewat config.
        if (config('security.csp_aktif')) {
            $respons->headers->set('Content-Security-Policy', (string) config('security.csp'));
        }
    }
}
