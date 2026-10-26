<?php

use App\Http\Controllers\AktivitasController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CetakSuratKeluarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DrafKontenSuratKeluarController;
use App\Http\Controllers\KlasifikasiPrimerController;
use App\Http\Controllers\KlasifikasiSekunderController;
use App\Http\Controllers\KlasifikasiTersierController;
use App\Http\Controllers\LampiranController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LogoInstansiController;
use App\Http\Controllers\PemusnahanArsipController;
use App\Http\Controllers\PengajuanHapusLampiranController;
use App\Http\Controllers\PengaturanInstansiController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\SuratKeluarController;
use App\Http\Controllers\SuratMasukController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — PRADANA
|--------------------------------------------------------------------------
| Yang perlu diketahui sebelum menambah route di sini:
|
| 1. Tidak ada self-register. "Registrasi user" = admin membuat akun lewat
|    `users.*`; login memakai email + PIN 8 digit. Semua halaman butuh login,
|    kecuali `instansi/logo` (dipakai halaman login) dan keduanya tetap
|    membawa header anti-index dari middleware `TandaiArsipPrivat`.
| 2. URI kebab-case, wildcard snake_case ({klasifikasi_primer}) — mengikuti
|    pola Route::resource() yang sudah dipakai controller surat.
| 3. Klasifikasi tidak punya `show` (->except(['show'])): tabel referensi
|    tiga tingkat cukup tampil bersama induknya di halaman daftar.
| 4. `pengaturan-instansi` satu baris untuk satu kantor: hanya edit+update
|    lewat Route::singleton()->only(['edit','update']); tidak ada
|    create/store/destroy, dan logo disimpan di disk `public` lokal.
| 5. Menghapus lampiran tidak lewat DELETE langsung: ada alur pengajuan (staf)
|    -> persetujuan (admin) di `PengajuanHapusLampiranController`, dan hanya
|    untuk surat berumur lebih dari 5 tahun (retensi 5 tahun seragam).
| 6. Tidak ada route pencarian sendiri. Penggalian isi (`ringkasan`, `isi_surat`,
|    isi hasil baca lampiran) hidup di filter `cari` pada `surat-masuk.index`
|    dan `surat-keluar.index`, termasuk mode gabungan `?jenis=semua`.
| 7. Yang mengubah struktur (klasifikasi, user, pengaturan instansi,
|    pemusnahan, menyetujui/menolak pengajuan) berada di belakang middleware
|    `admin`; yang cuma mencatat surat cukup di `auth` dan ditulis ke log
|    aktivitas lewat observer.
|*/

// Logo instansi: sengaja di luar grup auth supaya halaman login (guest) bisa memintanya.
Route::get('instansi/logo', [LogoInstansiController::class, 'show'])->name('instansi.logo');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Manajemen user — admin-only penuh
    Route::resource('users', UserController::class)
        ->only(['index', 'create', 'store', 'destroy'])
        ->middleware('admin');

    // Reset PIN oleh admin. Di luar resource di atas karena route
    // users.* admin-only, dan jalur "ganti PIN sendiri" tidak boleh admin-only.
    Route::patch('users/{user}/pin', [UserController::class, 'updatePin'])
        ->name('users.pin.update')
        ->middleware('admin');

    // Ganti PIN milik sendiri — semua role yang login. Sejak 4 Okt
    // 2026 pop up dari menu "Akun" di topbar, jadi tidak ada halaman GET-nya lagi.
    Route::patch('profil/pin', [ProfilController::class, 'updatePin'])->name('profil.pin.update');

    // Klasifikasi: index() terbuka untuk semua role yang login;
    // create/store/edit/update/destroy hanya admin 
    Route::resource('klasifikasi-primer', KlasifikasiPrimerController::class)->only(['index']);
    Route::resource('klasifikasi-primer', KlasifikasiPrimerController::class)->except(['show', 'index'])->middleware('admin');

    Route::resource('klasifikasi-sekunder', KlasifikasiSekunderController::class)->only(['index']);
    Route::resource('klasifikasi-sekunder', KlasifikasiSekunderController::class)->except(['show', 'index'])->middleware('admin');

    Route::resource('klasifikasi-tersier', KlasifikasiTersierController::class)->only(['index']);
    Route::resource('klasifikasi-tersier', KlasifikasiTersierController::class)->except(['show', 'index'])->middleware('admin');

    // Pengaturan Instansi — admin-only penuh
    // Lihat catatan #4 di atas: satu baris untuk satu kantor, hanya edit+update
    Route::singleton('pengaturan-instansi', PengaturanInstansiController::class)
        ->only(['edit', 'update'])
        ->middleware('admin');

    Route::resource('surat-masuk', SuratMasukController::class);
    Route::resource('surat-keluar', SuratKeluarController::class);

    // Aktif/inaktif status arsip (sistem hanya menampilkan daftar surat
    // lewat retensi, keputusan menonaktifkan tetap di tangan user).
    Route::patch('surat-masuk/{surat_masuk}/status-arsip', [SuratMasukController::class, 'updateStatusArsip'])
        ->name('surat-masuk.status-arsip');
    Route::patch('surat-keluar/{surat_keluar}/status-arsip', [SuratKeluarController::class, 'updateStatusArsip'])
        ->name('surat-keluar.status-arsip');

    // Simpan/validasi hasil baca otomatis isi lampiran. Semua role
    // yang login boleh; endpoint terpisah karena UpdateSuratMasukRequest
    // memvalidasi seluruh field surat, bukan satu kolom teks.
    Route::patch('surat-masuk/{surat_masuk}/isi', [SuratMasukController::class, 'updateIsi'])
        ->name('surat-masuk.isi.update');

    // Pulihkan dari tempat sampah — admin only (tombol "Nyahkan" = soft delete)
    Route::patch('surat-masuk/{surat_masuk}/pulihkan', [SuratMasukController::class, 'restore'])
        ->name('surat-masuk.restore')
        ->middleware('admin');
    Route::patch('surat-keluar/{surat_keluar}/pulihkan', [SuratKeluarController::class, 'restore'])
        ->name('surat-keluar.restore')
        ->middleware('admin');

    // Cetak PDF surat keluar — bukan aksi destruktif, jadi `auth` saja
    Route::get('surat-keluar/{surat_keluar}/cetak', [CetakSuratKeluarController::class, 'cetak'])
        ->name('surat-keluar.cetak');

    // Draf konten surat keluar — tanpa halaman ini tabel
    // draf_konten_surat_keluar tidak pernah terisi, dan cetak PDF selalu menolak.
    Route::get('surat-keluar/{surat_keluar}/draf', [DrafKontenSuratKeluarController::class, 'edit'])
        ->name('surat-keluar.draf.edit');
    Route::put('surat-keluar/{surat_keluar}/draf', [DrafKontenSuratKeluarController::class, 'update'])
        ->name('surat-keluar.draf.update');

    // Tidak ada halaman /pencarian sendiri (lihat catatan #6 di atas): penggalian isi
    // surat hidup di filter `cari` pada surat-masuk.index & surat-keluar.index.

    // Log aktivitas — read-only, khusus admin
    Route::get('aktivitas', [AktivitasController::class, 'index'])->name('aktivitas.index');
    // Rekap CSV untuk audit: kerangka filter yang sama dengan layar di atas
    // (lihat AktivitasController::terapkanFilter()), jadi angka layar = isi berkas.
    Route::get('aktivitas/rekap', [AktivitasController::class, 'rekap'])->name('aktivitas.rekap');

    // Laporan & Buku Agenda — semua role yang login
    Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('laporan/rekap', [LaporanController::class, 'rekap'])->name('laporan.rekap');
    Route::get('laporan/agenda', [LaporanController::class, 'agenda'])->name('laporan.agenda');

    // Lampiran — nested di bawah surat induk (lihat catatan #5)
    Route::post('surat-masuk/{surat_masuk}/lampiran', [LampiranController::class, 'storeForSuratMasuk'])
        ->name('surat-masuk.lampiran.store');
    Route::post('surat-keluar/{surat_keluar}/lampiran', [LampiranController::class, 'storeForSuratKeluar'])
        ->name('surat-keluar.lampiran.store');
    Route::get('lampiran/{lampiran}/unduh', [LampiranController::class, 'download'])
        ->name('lampiran.download');

    // Pengajuan hapus lampiran (lihat catatan #5 di atas) —
    // store() terbuka untuk semua role yang login (siapa saja boleh ajukan);
    // index/setujui/tolak admin-only 
    Route::post('lampiran/{lampiran}/pengajuan-hapus', [PengajuanHapusLampiranController::class, 'store'])
        ->name('lampiran.pengajuan-hapus.store');
    Route::get('pengajuan-hapus-lampiran', [PengajuanHapusLampiranController::class, 'index'])
        ->name('pengajuan-hapus-lampiran.index')
        ->middleware('admin');
    Route::post('pengajuan-hapus-lampiran/{pengajuan_hapus_lampiran}/setujui', [PengajuanHapusLampiranController::class, 'setujui'])
        ->name('pengajuan-hapus-lampiran.setujui')
        ->middleware('admin');
    Route::post('pengajuan-hapus-lampiran/{pengajuan_hapus_lampiran}/tolak', [PengajuanHapusLampiranController::class, 'tolak'])
        ->name('pengajuan-hapus-lampiran.tolak')
        ->middleware('admin');

    // Pemusnahan arsip SURAT + Berita Acara — persetujuan hanya admin
    // pola sama seperti pengajuan hapus lampiran: semua role boleh mengajukan,
    // hanya admin yang menyetujui/menolak. setujui() = satu-satunya jalur yang
    // menghapus data sungguhan, jadi dipagari middleware admin di route DAN
    // abort_unless di dalam controller (jangan cuma salah satu).
    Route::resource('pemusnahan-arsip', PemusnahanArsipController::class)
        ->only(['index', 'create', 'store', 'show']);
    Route::post('pemusnahan-arsip/{pemusnahan_arsip}/setujui', [PemusnahanArsipController::class, 'setujui'])
        ->name('pemusnahan-arsip.setujui')
        ->middleware('admin');
    Route::post('pemusnahan-arsip/{pemusnahan_arsip}/tolak', [PemusnahanArsipController::class, 'tolak'])
        ->name('pemusnahan-arsip.tolak')
        ->middleware('admin');
    // Berita Acara boleh dicetak semua role yang login (dokumen bukti arsip,
    // bukan aksi destruktif — sama seperti surat-keluar.cetak).
    Route::get('pemusnahan-arsip/{pemusnahan_arsip}/berita-acara', [PemusnahanArsipController::class, 'beritaAcara'])
        ->name('pemusnahan-arsip.berita-acara');
});
