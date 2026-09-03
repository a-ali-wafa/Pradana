<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CetakSuratKeluarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KlasifikasiPrimerController;
use App\Http\Controllers\KlasifikasiSekunderController;
use App\Http\Controllers\KlasifikasiTersierController;
use App\Http\Controllers\LampiranController;
use App\Http\Controllers\PencarianController;
use App\Http\Controllers\PengajuanHapusLampiranController;
use App\Http\Controllers\PengaturanInstansiController;
use App\Http\Controllers\SuratKeluarController;
use App\Http\Controllers\SuratMasukController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — PRADANA
|--------------------------------------------------------------------------
| Catatan (31 Agu 2026), baca sebelum menambah route baru:
|
| 1. Route Auth (login/logout) & manajemen user ("registrasi" oleh admin)
|    ditambahkan 28 Agu 2026 — grup `guest` baru untuk login, digabung ke
|    grup `auth` yang sudah ada untuk logout + `users.*`. "Register" =
|    admin bikin user langsung (BUKAN self-register publik), sesuai A3
|    [LOCKED, dikonfirmasi user 28 Agu 2026]. Belum ada view (`auth.login`,
|    `users.index`, `users.create`). Detail lengkap: AGENTS.md 12.14.
|
| 2. Route Lampiran ditambahkan 31 Agu 2026 — `LampiranController` BENAR-BENAR
|    BARU (sebelumnya cuma migration `lampiran` yang ada, controllernya belum
|    pernah dibuat sama sekali — koreksi klaim lama, lihat AGENTS.md Bagian 3
|    & 12.16). Upload nested di bawah surat induk (surat-masuk/{..}/lampiran,
|    surat-keluar/{..}/lampiran); unduh langsung lewat {lampiran}. Struktur
|    folder Drive (root env = Arsip_PRADANA, subfolder cuma level primer)
|    SUDAH dikonfirmasi user (lihat 12.17, kode C6 [DEFAULT] Bagian 9).
|    **Hapus lampiran BUKAN admin-langsung** — sejak 31 Agu 2026 lewat alur
|    pengajuan (staf) + persetujuan (admin) di `PengajuanHapusLampiranController`,
|    hanya untuk lampiran pada surat berumur >5 tahun (E1 [LOCKED]). Lihat
|    AGENTS.md 12.17.
|
| 2b. Route Pengaturan Instansi ditambahkan 31 Agu 2026 (koreksi catatan #2 di
|     atas yang sudah basi — controller & Form Request-nya sekarang sudah ada,
|     $fillable model SUDAH cross-checked cocok 100%). Single-row settings →
|     cuma edit()+update() lewat Route::singleton()->only(['edit','update']),
|     TIDAK ada create/store/destroy. Logo disimpan lokal (disk `public`),
|     BUKAN Google Drive — dikonfirmasi user 31 Agu 2026. Detail: AGENTS.md 12.18.
|
| 3. Route `surat-masuk` didaftarkan penuh (termasuk `show`) — status
|    "disengketakan" di `SuratMasukController` SUDAH resolved 26 Agu 2026:
|    dikonfirmasi user file lama memang stub kosong, CRUD lengkap baru
|    dibuat di sesi ini. Lihat AGENTS.md Bagian 3 & 12.11.
|
| 4. Konvensi parameter route: URI kebab-case, wildcard snake_case
|    (mis. `klasifikasi-primer` → {klasifikasi_primer}), otomatis dari
|    Route::resource() — sesuai pola yang sudah dipakai di
|    SuratMasukController/SuratKeluarController (lihat AGENTS.md 12.7).
|
| 5. Klasifikasi TIDAK punya route `show` (->except(['show'])) — halaman
|    detail terpisah dianggap tidak perlu untuk tabel referensi sederhana
|    ini; index sudah menampilkan list + relasi induknya. Kalau nanti
|    dianggap perlu, tinggal hapus except-nya.
|
| 6. `users` resource cuma daftar `index`/`create`/`store` (->only(...))
|    — belum ada edit/delete/ganti-PIN, di luar scope Auth sesi ini
|    (lihat A4/A5 di AGENTS.md Bagian 10, belum diimplementasikan).
|
| 7. `dashboard` (28 Agu 2026): widget statistik dipilih bebas dari skema,
|    BUKAN spesifikasi eksplisit user — gampang diubah. ⚠️ **Koreksi 1 Sep
|    2026**: baris ini sebelumnya bilang relasi `Aktivitas::user()` "dipakai
|    tanpa verifikasi" — itu sudah basi, relasi itu SUDAH dicek 28 Agu 2026
|    (model `Aktivitas.php` diupload & `belongsTo(User::class)` terkonfirmasi
|    tepat, lihat AGENTS_HISTORY.md 12.15). Belum ada view (`dashboard.index`).
|
| 8. Route `surat-keluar/{surat_keluar}/cetak` (baru, 1 Sep 2026) — generate
|    PDF surat keluar via `CetakSuratKeluarController` (dompdf, sesuai F2
|    [DEFAULT]). `auth` polos, bukan admin-only (mencetak bukan aksi
|    destruktif). Detail: AGENTS.md 12.20.
|
| 9. Route `pencarian` (baru, 1 Sep 2026) — pencarian arsip gabungan surat
|    masuk+keluar via `PencarianController`. `auth` polos, semua role boleh
|    akses. Detail: AGENTS.md 12.21.
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // "Registrasi" user baru — admin-only, sesuai A3 [LOCKED, dikonfirmasi 28 Agu 2026]
    Route::resource('users', UserController::class)->only(['index', 'create', 'store']);

    Route::resource('klasifikasi-primer', KlasifikasiPrimerController::class)->except(['show']);
    Route::resource('klasifikasi-sekunder', KlasifikasiSekunderController::class)->except(['show']);
    Route::resource('klasifikasi-tersier', KlasifikasiTersierController::class)->except(['show']);

    // Pengaturan Instansi (baru, 31 Agu 2026, lihat catatan #2b di atas & AGENTS.md 12.18)
    Route::singleton('pengaturan-instansi', PengaturanInstansiController::class)
        ->only(['edit', 'update']);

    Route::resource('surat-masuk', SuratMasukController::class);
    Route::resource('surat-keluar', SuratKeluarController::class);

    // Cetak PDF surat keluar (baru, 1 Sep 2026, lihat catatan #8 di atas & AGENTS.md 12.20)
    Route::get('surat-keluar/{surat_keluar}/cetak', [CetakSuratKeluarController::class, 'cetak'])
        ->name('surat-keluar.cetak');

    // Pencarian arsip global (baru, 1 Sep 2026, lihat catatan #9 di atas & AGENTS.md 12.21)
    Route::get('pencarian', [PencarianController::class, 'index'])
        ->name('pencarian.index');

    // Lampiran (baru, 31 Agu 2026) — lihat catatan #2 di atas & AGENTS.md 12.16/12.17.
    Route::post('surat-masuk/{surat_masuk}/lampiran', [LampiranController::class, 'storeForSuratMasuk'])
        ->name('surat-masuk.lampiran.store');
    Route::post('surat-keluar/{surat_keluar}/lampiran', [LampiranController::class, 'storeForSuratKeluar'])
        ->name('surat-keluar.lampiran.store');
    Route::get('lampiran/{lampiran}/unduh', [LampiranController::class, 'download'])
        ->name('lampiran.download');

    // Pengajuan hapus lampiran (baru, 31 Agu 2026, lihat AGENTS.md 12.17) —
    // GANTIKAN route DELETE lampiran/{lampiran} langsung yang sempat ada.
    Route::post('lampiran/{lampiran}/pengajuan-hapus', [PengajuanHapusLampiranController::class, 'store'])
        ->name('lampiran.pengajuan-hapus.store');
    Route::get('pengajuan-hapus-lampiran', [PengajuanHapusLampiranController::class, 'index'])
        ->name('pengajuan-hapus-lampiran.index');
    Route::post('pengajuan-hapus-lampiran/{pengajuan_hapus_lampiran}/setujui', [PengajuanHapusLampiranController::class, 'setujui'])
        ->name('pengajuan-hapus-lampiran.setujui');
    Route::post('pengajuan-hapus-lampiran/{pengajuan_hapus_lampiran}/tolak', [PengajuanHapusLampiranController::class, 'tolak'])
        ->name('pengajuan-hapus-lampiran.tolak');
});
