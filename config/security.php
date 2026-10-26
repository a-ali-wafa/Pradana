<?php

/*
|--------------------------------------------------------------------------
| Header keamanan PRADANA
|--------------------------------------------------------------------------
|
| Kenapa file config sendiri dan bukan `env()` di dalam middleware: deploy
| target memakai `php artisan config:cache` (shared hosting), dan begitu
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
     * Proxy yang boleh dipercaya untuk header `X-Forwarded-*`. Kosong = tidak ada,
     * dan itu memang default yang benar: siapa pun yang bisa mengirim
     * `X-Forwarded-For` bebas memalsukan alamat IP kalau server ikut percaya.
     *
     * Perlu diisi justru pada dua jalur deploy yang realistis untuk kantor ini:
     *   - Cloudflare Tunnel (`cloudflared`): TLS berakhir di luar aplikasi, jadi
     *     tanpa daftar ini `url()` menghasilkan `http://`, `SESSION_SECURE_COOKIE=true`
     *     membuat login tidak pernah diterima (cookie aman tidak dikirim di http),
     *     dan `$request->ip()` selalu 127.0.0.1.
     *   - CDN di depan server: semua petugas tampak memakai satu IP.
     *
     * Konsekuensi spesifik di PRADANA: kunci throttle login adalah email + IP
     * (`LoginRequest::throttleKey()`, 5 percobaan/menit). Kalau IP selalu sama,
     * lima salah ketik PIN oleh SATU orang mengunci seluruh kantor — dan itu
     * tidak bisa dijelaskan dari layar karena tidak ada kolom IP di log aktivitas.
     *
     * Isinya daftar sempit, dipisah koma: `127.0.0.1` untuk tunnel di mesin yang
     * sama, atau rentang IP milik CDN. `*` berarti percaya ke siapa pun — jangan.
     */
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', ''))
    ))),

    /*
     * Isi Content-Security-Policy.
     *
     * 'unsafe-inline' untuk script & style itu SENGAJA, bukan kelalaian: 10 halaman
     * memakai `@push('scripts')`, enam tombol masih menulis `onclick="..."` di Blade,
     * dan setiap dokumen PDF menulis `<style>` inline. Menggantinya dengan hash/nonce
     * berarti merombak semua view — untuk aplikasi internal satu kantor, nilai yang
     * hilang tidak sebanding. Yang tetap ditahan CSP: sumber daya dari host tak
     * dikenal, form yang dikirim ke luar, dan frame.
     *
     * (Dulu komentar ini menyebut `onclick="pradanaConfirmHapus(...)"`. Fungsi itu
     * sudah dibuang 10 Okt — konfirmasi aksi destruktif kini form POST sungguhan —
     * dan `onclick` yang tersisa adalah yang lain: buka/tutup sidebar, mode edit
     * Pengaturan Instansi, salin hasil baca, reload. Angka di atas dihitung dari
     * `resources/views/`, supaya klaim ini bisa diperiksa, tidak dipercaya.)
     *
     * Sejak aset vendor pindah ke repo, `script-src`/`style-src`/`font-src` tidak lagi
     * menyebut cdn.jsdelivr.net atau cdnjs.cloudflare.com. Bootstrap, SweetAlert2, dan
     * Font Awesome hidup di `public/vendor/` sendiri (versinya dicatat di
     * `docs/aset-vendor.md`), jadi dua host itu cuma membuka jalan bagi muatan dari luar
     * yang tidak dipakai lagi — semakin sedikit asal yang diizinkan, semakin berarti
     * header-nya. Kalau nanti ada aset CDN baru, itu keputusan sadar, bukan warisan.
     *
     * `img-src ... blob:` dibutuhkan pratinjau logo di Pengaturan Instansi
     * (URL.createObjectURL); `data:` di font-src dibiarkan karena
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
