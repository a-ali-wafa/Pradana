<?php

/*
|--------------------------------------------------------------------------
| Header keamanan PRADANA
|--------------------------------------------------------------------------
|
| Kenapa file config sendiri dan bukan `env()` di dalam middleware: deploy
| target memakai `php artisan config:cache` (K1=a, shared hosting), dan begitu
| config di-cache Laravel mengembalikan null untuk `env()` — jadi sakelar ini
| akan diam-diam "mati" di server kantor padahal menyala di laptop. Nilai env
| hanya boleh dibaca di file config. (Jebakan yang sama sudah dicatat untuk
| DEV_PIN.)
|
| SECURITY_CSP=false dipakai saat petugas/maintainer perlu mendiagnosis layout
| rusak karena kebijakan konten. Jangan dibiarkan mati di produksi.
 */

return [
    'csp_aktif' => filter_var(env('SECURITY_CSP', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * Isi Content-Security-Policy.
     *
     * 'unsafe-inline' untuk script & style itu SENGAJA, bukan kelalaian: seluruh
     * halaman memakai `@push('scripts')` dan `onclick="pradanaConfirmHapus(...)"`
     * di Blade, dan setiap dokumen PDF menulis `<style>` inline. Menggantinya
     * dengan hash/nonce berarti merombak semua view — untuk aplikasi internal
     * satu kantor, nilai yang hilang tidak sebanding. Yang tetap ditahan CSP:
     * sumber daya dari host tak dikenal, form yang dikirim ke luar, dan frame.
     *
     * 10 Okt 2026: `script-src`/`style-src`/`font-src` TIDAK lagi menyebut
     * cdn.jsdelivr.net atau cdnjs.cloudflare.com. Bootstrap, SweetAlert2, dan
     * Font Awesome sekarang hidup di `public/vendor/` (lihat AGENTS.md,
     * "Susulan 10 Okt"), jadi dua host itu cuma membuka jalan bagi muatan dari
     * luar yang tidak dipakai lagi — semakin sedikit asal yang diizinkan, semakin
     * berarti header-nya. Kalau nanti ada aset CDN baru, itu keputusan sadar,
     * bukan warisan.
     *
     * `img-src ... blob:` dibutuhkan pratinjau logo di Pengaturan Instansi
     * (URL.createObjectURL, 4 Okt 2026); `data:` di font-src dibiarkan karena
     * sebagian browser memuat font base64 dari stylesheet vendor. `connect-src
     * 'self'` masih mengizinkan unggah AJAX lampiran karena endpoint-nya milik
     * aplikasi sendiri.
     */
    'csp' => 'default-src \'self\'; '
        ."script-src 'self' 'unsafe-inline'; "
        ."style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data: blob:; "
        ."font-src 'self' data:; "
        ."connect-src 'self'; "
        ."object-src 'none'; "
        ."frame-ancestors 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self'",
];
