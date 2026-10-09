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
     * `img-src ... blob:` dibutuhkan pratinjau logo di Pengaturan Instansi
     * (URL.createObjectURL, 4 Okt 2026); `font-src` ke cdnjs dipakai icon
     * Font Awesome; `connect-src 'self'` masih mengizinkan unggah AJAX lampiran
     * karena endpoint-nya milik aplikasi sendiri.
     */
    'csp' => 'default-src \'self\'; '
        ."script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        ."style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        ."img-src 'self' data: blob:; "
        ."font-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net data:; "
        ."connect-src 'self'; "
        ."object-src 'none'; "
        ."frame-ancestors 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self'",
];
