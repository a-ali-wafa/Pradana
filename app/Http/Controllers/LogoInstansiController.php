<?php

namespace App\Http\Controllers;

use App\Models\PengaturanInstansi;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Melayani berkas logo instansi ke browser — BARU 5 Okt 2026.
 *
 * Kenapa perlu route sendiri padahal Laravel punya `Storage::url()`: URL itu
 * menunjuk `public/storage/logo/...` yang HANYA ada kalau `php artisan
 * storage:link` pernah dijalankan. Di laptop dev user symlink itu belum ada
 * (logo jadi tidak tampil di Pengaturan Instansi maupun halaman login), dan di
 * shared hosting kantor (K1=a) symlink juga sering tidak bisa dibuat. Route ini
 * membaca langsung dari disk `public`, jadi logo tampil tanpa setup tambahan.
 *
 * Rutenya SENGAJA di luar grup `auth`: halaman login menampilkan kop + logo
 * instansi sebelum user masuk, dan itu bukan data rahasia (logo memang tercetak
 * di kop surat yang dikirim ke luar). Berkas lain di disk `public` tidak ikut
 * terjangkau — route ini hanya pernah membaca path yang tersimpan di kolom
 * `pengaturan_instansi.logo_path`.
 *
 * `immutable` + satu tahun: nama berkas logo acak (`store('logo')`), jadi URL
 * dengan `?v=` yang sama tidak pernah berubah isinya; ganti logo = URL baru.
 */
class LogoInstansiController extends Controller
{
    public function show(): BinaryFileResponse
    {
        $pengaturan = PengaturanInstansi::first();
        $logo = $pengaturan?->logo_path;

        // `if` + `abort()`, bukan `abort_unless()`: setelahnya `$logo` dipakai sebagai
        // path, dan cuma bentuk ini yang memberi tahu analyzer bahwa null sudah pasti
        // tidak tersisa.
        if (! $logo || ! Storage::disk('public')->exists($logo)) {
            abort(404, 'Logo instansi belum diunggah.');
        }

        return response()->file(
            Storage::disk('public')->path($logo),
            [
                // Tipe MIME ditetapkan dari ekstensi, bukan ditebak dari isi berkas:
                // `mime_content_type()` di beberapa server bersama hosting hanya
                // mengembalikan `application/octet-stream`, dan browser butuh
                // `image/*` supaya <img> mau menampilkan gambarnya.
                'Content-Type' => match (mb_strtolower(pathinfo($logo, PATHINFO_EXTENSION))) {
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'jpeg', 'jpg' => 'image/jpeg',
                    default => 'application/octet-stream',
                },
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]
        );
    }
}
