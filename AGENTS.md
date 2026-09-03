# AGENTS.md — PRADANA (Konteks Teknis untuk AI Agent)

> **Audiens dokumen ini: AI coding agent** (Claude Code, atau agent lain yang melanjutkan project ini di sesi/tool berbeda) — bukan stakeholder manusia.
> Taruh file ini di **root project Laravel** (banyak agent, termasuk Claude Code, otomatis membaca `AGENTS.md` di root).
> Terakhir diperbarui: 1 September 2026.

---

## 0. Instruksi Operasional untuk Agent

1. Baca **seluruh** file ini sebelum membuat/mengubah file apa pun.
2. Item berlabel **[LOCKED]** = sudah final. Jangan diubah tanpa alasan teknis kuat — dan kalau diubah, WAJIB tambahkan baris baru di Bagian 13 (Riwayat Perubahan).
3. Item berlabel **[DEFAULT]** = asumsi kerja yang sudah dipilih dengan alasan jelas. Boleh langsung dipakai, tidak perlu tanya user lagi. Tetap tulis kode agar gampang diubah nanti (jangan hardcode di banyak tempat).
4. Item berlabel **[WAJIB TANYA USER]** = JANGAN diimplementasikan final sebelum dikonfirmasi user. Kalau tugas kamu menyentuh item ini:
   - Boleh scaffold sementara (pakai "asumsi sementara" yang tercantum kalau ada),
   - tandai jelas dengan komentar kode `// TODO(<kode-pertanyaan>): konfirmasi ke user`,
   - dan laporkan ke user secara eksplisit sebelum menganggap task selesai.
5. Kalau menemukan keputusan baru yang belum tercatat di sini, tambahkan ke Bagian 10 dengan kode baru (lanjutkan huruf/nomor yang sudah ada).
6. Setelah menyelesaikan pekerjaan berarti: update Bagian 3 (Status Pengerjaan) dan tambahkan baris di Bagian 13 (Riwayat Perubahan). Ini satu-satunya cara sesi/agent berikutnya tahu state terbaru — jangan andalkan chat history.
7. Jangan menerjemahkan nama tabel/kolom/variabel domain (bahasa Indonesia) ke bahasa Inggris. Ini keputusan sengaja — lihat Bagian 2.
8. ⭐ **(ditambahkan 1 Sep 2026)** File ini (`AGENTS.md`) memuat Bagian 0–11 (instruksi, keputusan, status, roadmap) — ini yang WAJIB dibaca penuh tiap sesi (poin 1). Bagian 12 (Catatan Teknis Tambahan) & Bagian 13 (Riwayat Perubahan) — termasuk SEMUA referensi "lihat 12.x" yang muncul di seluruh dokumen ini — sudah dipindah apa adanya ke `AGENTS_HISTORY.md` di folder yang sama, supaya beban baca tiap sesi lebih ringan. Buka file itu **on-demand** saat ada rujukan spesifik yang perlu dicek (mis. "lihat 12.11"), TIDAK perlu dibaca penuh di setiap sesi seperti file ini.

---

## 1. Ringkasan Project

PRADANA: aplikasi arsip surat masuk/keluar untuk kantor pemerintah desa/kelurahan. Migrasi dari **Google Apps Script + Google Sheets + Google Drive** → **Laravel + database relasional + Google Drive (Service Account)**.

Sumber asli: 1 file HTML (Bootstrap 5 + SweetAlert2) + 1 file `Code.gs` (Apps Script), pakai Google Sheets sebagai "database". Fitur inti: input surat masuk/keluar, klasifikasi berjenjang, auto-nomor surat, generate PDF surat keluar, dashboard statistik, pencarian global, manajemen retensi arsip, log aktivitas.

---

## 2. Tech Stack & Konvensi [LOCKED]

| Aspek | Ketentuan |
|---|---|
| Backend | Laravel — lihat Bagian 9 (K3) untuk versi |
| Database | Relasional, standar Laravel Schema Builder (kompatibel MySQL/PostgreSQL, tidak pakai raw SQL vendor-specific) |
| Auth | **PIN-based**, bukan password konvensional. Kolom `users.pin` (string, ter-hash). Model `User` override `getAuthPassword()` supaya `Auth::attempt()` verifikasi ke kolom `pin`. |
| Penyimpanan file | Google Drive via **Service Account** (BUKAN OAuth per user, BUKAN Shared Drive sebagai metode auth — walau folder tujuan BOLEH ada di Shared Drive, lihat Bagian 8). |
| Aturan file di DB | **JANGAN PERNAH** simpan link Drive publik di database. Simpan `google_drive_file_id`. Akses file HARUS lewat controller ber-`middleware('auth')` yang stream isi file dari Drive API (lihat `LampiranController`). |
| Naming | Domain **Bahasa Indonesia**, `snake_case`, TIDAK diterjemahkan ke Inggris (`surat_masuk`, bukan `incoming_letters`). Ini sengaja — jangan "diperbaiki". |
| Model | Setiap model set `protected $table` eksplisit — jangan andalkan tebakan pluralisasi Eloquent. |
| Migration | Sudah final di `database/migrations/`, urut sesuai dependensi FK. Jangan re-generate dari nol — edit in-place kalau perlu revisi kecil sebelum `php artisan migrate` pertama kali dijalankan di lingkungan manapun; kalau migration SUDAH pernah dijalankan di suatu environment, buat migration baru (`alter table`), jangan edit file lama. |

---

## 3. Status Pengerjaan

- [x] Analisis kode Apps Script lama
- [x] Skema database ternormalisasi (10 tabel awal, **+1 tabel baru `pengajuan_hapus_lampiran` 31 Agu 2026 → total 11 tabel**, lihat 12.17) — migration + model Eloquent lengkap
- [x] Integrasi Google Drive: `GoogleDriveService` (get-or-create folder, upload, stream, delete) + `config/gdrive.php` + migration `lampiran`. ⚠️ **Koreksi 31 Agu 2026**: baris ini sebelumnya (keliru) juga mengklaim `LampiranController` sudah ada — dikonfirmasi user file itu **belum pernah dibuat sama sekali**, cuma migration-nya yang ada. Lihat bullet `LampiranController` terpisah di bawah & 12.16.
- [x] `LampiranController` (upload/unduh lampiran, surat masuk & keluar) — dibuat **baru** 31 Agu 2026 (BUKAN revisi — klaim lama di atas ternyata salah), lihat 12.16. Dibangun dari `Lampiran.php`/`GoogleDriveService.php`/`config/gdrive.php`/migration/`web.php` **asli** yang diupload user, jadi `$fillable` & signature method Drive TIDAK ditebak. Route sudah digabung ke `web.php`. Struktur folder Drive (root env = Arsip_PRADANA, subfolder cuma level primer) **sudah dikonfirmasi user** (lihat C6, 12.17). **Model `PengaturanInstansi.php` sudah di-cross-check 31 Agu 2026** (lihat 12.18) — `$fillable`-nya TIDAK termasuk 3 kolom `gdrive_*`, jadi pendekatan cache folder ID lewat assignment atribut langsung + `save()` (bukan `update()`) di controller ini **terbukti memang perlu**, bukan cuma jaga-jaga. **`destroy()` (hapus admin-langsung) SUDAH DIHAPUS dari controller ini** — digantikan alur pengajuan-persetujuan (lihat bullet di bawah).
- [x] `PengajuanHapusLampiranController` (alur hapus lampiran: pengajuan staf → persetujuan admin, hanya utk surat >5 tahun) — dibuat **baru** 31 Agu 2026 sesuai keputusan user langsung (BUKAN tebakan), lihat 12.17. Tabel baru `pengajuan_hapus_lampiran` + model `PengajuanHapusLampiran` juga baru. Route sudah digabung ke `web.php`, menggantikan route `DELETE lampiran/{lampiran}` yang sempat ada.
- [x] `DatabaseSeeder` default (1 admin, pengaturan instansi, 2 contoh klasifikasi)
- [x] *(1 Sep 2026, lihat catatan)* Generate PDF Surat Keluar — `CetakSuratKeluarController` (berdiri sendiri) + view `surat-keluar/cetak.blade.php`, **route sudah digabung ke `web.php` asli** (12.20/12.24). **3 Sep 2026**: `barryvdh/laravel-dompdf ^3.1` sudah di-`composer require`. Sisa: belum cross-checked ke model `SuratKeluar`/`DrafKontenSuratKeluar` asli.
- [x] *(1 Sep 2026, lihat catatan)* Pencarian Arsip Global — `PencarianController` (berdiri sendiri), **route sudah digabung ke `web.php` asli**, **view sudah `@extends('layouts.app')`** (skin resmi, bukan standalone lagi), +1 link nav baru di sidebar. Lihat 12.21/12.24. Sisa terbuka: belum cross-checked ke model asli.
- [x] *(1 Sep 2026)* Logging Aktivitas Otomatis — trait `LogsAktivitas` + 9 Model Observer, **SUDAH diregistrasikan ke `AppServiceProvider::boot()` asli — resmi AKTIF**. Lihat 12.22/12.24. Belum ada UI utk melihat log-nya (cuma penulisan).
- [x] *(1 Sep 2026)* Middleware RBAC — **SELESAI & VERIFIED 3 Sep 2026**. B1 [LOCKED #16, admin/non-admin]. `EnsureIsAdmin` middleware aktif, alias `admin` terdaftar di `app/Http/Kernel.php`. **3 Sep 2026**: celah keamanan di `routes/web.php` ditemukan & ditutup — middleware `admin` kini terpasang langsung di route untuk `users.*`, `pengaturan-instansi.*`, mutasi `klasifikasi-*`, dan `pengajuan-hapus-lampiran index/setujui/tolak`. Lihat 12.25 & 12.26.
- [x] *(3 Sep 2026)* View Dashboard — `resources/views/dashboard/index.blade.php` dibuat. Menampilkan 4 kartu statistik, tombol aksi cepat, tabel surat masuk/keluar terbaru, log aktivitas, dan bar visual klasifikasi terpopuler. Lihat 12.26.
- [x] *(3 Sep 2026)* D1 Format Nomor Surat Keluar — **TERJAWAB & DIIMPLEMENTASIKAN**. Format: `{urutan 3 digit}/{kodeP.kodeS.kodeT}/{bulan romawi}/{tahun}` — contoh: `001/01.01.01/IX/2026`. `generateNomorSurat()` di `SuratKeluarController` sudah diperbarui, `TODO(D1)` dihapus. Lihat Bagian 8 #17 & 12.26.
- [ ] Project Laravel yang sebenarnya belum di-`composer create-project` — deliverable saat ini baru berupa file lepas (migrations, models, services, controllers, form requests) yang perlu disalin ke skeleton project
- [x] Auth (login/register/logout) — `AuthController`+`UserController`+`LoginRequest`+`StoreUserRequest` dibuat 28 Agu 2026, **cross-checked terhadap `User.php` & `web.php` asli 28 Agu 2026** (lihat 12.14): `getAuthPassword()` sudah benar, `$fillable` cocok 100%, tidak ada mutator auto-hash (jadi `Hash::make()` manual memang perlu), route sudah digabung ke `web.php`. **A3 (kebijakan registrasi) sudah dikonfirmasi user 28 Agu 2026: admin bikin akun langsung** — sekarang [LOCKED], lihat Bagian 8 #11. Login/logout/registrasi-oleh-admin **selesai & siap pakai secara teknis**. Yang masih di luar scope ini: belum ada view (tergantung G1), ganti/reset PIN (A4/A5, belum diimplementasikan).
- [x] DashboardController — `DashboardController.php` dibuat 28 Agu 2026 (lihat 12.15), widget statistik dipilih bebas dari skema Bagian 5 (BUKAN spek eksplisit user — gampang diubah). Route `dashboard` sudah didaftarkan di `web.php`, redirect login sudah diarahkan ke sini. **Semua relasi (`primer`, `petugas`, `user`) cross-checked terhadap model asli 28 Agu 2026** — tidak ada bug. **View `dashboard.index` dibuat 3 Sep 2026** — selesai & siap pakai.
- [x] CRUD Surat Masuk — ✅ **status disengketakan sudah resolved 26 Agu 2026**. Dikonfirmasi langsung oleh user: file lama (`SuratMasukController.php`) memang stub kosong, `Store/UpdateSuratMasukRequest` memang belum pernah dibuat — klaim "selesai" di riwayat sebelumnya **keliru** (opsi (b) di catatan lama). CRUD lengkap baru dibuat 26 Agu 2026 di sesi ini, lihat 12.11.
- [x] CRUD Surat Keluar — `SuratKeluarController` + `Store/UpdateSuratKeluarRequest` (26 Agu 2026, idem). **Cross-checked penuh terhadap ketiga file asli (Controller + Store + Update Request) 28 Agu 2026** — tidak ada bug ditemukan sama sekali, lihat 12.13. **3 Sep 2026**: `generateNomorSurat()` diperbarui format D1 final.
- [x] CRUD Klasifikasi (Primer/Sekunder/Tersier) — 3 controller + 6 Form Request (26 Agu 2026). **Nama relasi sudah diverifikasi & diperbaiki** terhadap model asli yang diupload user (`sekunder()`/`primer()`/`tersier()`, bukan asumsi awal `klasifikasiSekunder()` dst) — lihat 12.11.
- [x] CRUD Pengaturan Instansi — `PengaturanInstansiController` + `UpdatePengaturanInstansiRequest` dibuat 31 Agu 2026 (mulai dari skema doang, sesi tanpa file project), **cross-checked 31 Agu 2026 (sesi sama, lanjutan)** setelah user upload `PengaturanInstansi.php` + `web.php` asli: `$fillable` **cocok 100%** dengan asumsi awal, tidak ada perubahan kode diperlukan. Desain edit-only (single row, tanpa index/show/create/store/destroy) & admin-only (B4) dipertahankan. **Logo disimpan LOKAL, bukan Drive — dikonfirmasi eksplisit user 31 Agu 2026** (lihat C7, Bagian 9). Route sudah digabung ke `web.php` asli (`Route::singleton()->only(['edit','update'])`). Lihat 12.18 (diperbarui). Sisa yang belum: panjang kolom string masih tebakan (migration belum diperiksa, dampak kecil).
- [x] Routes `web.php` — selesai & **VERIFIED 3 Sep 2026**. Middleware `admin` sudah terpasang di semua route yang butuh proteksi admin. Lihat 12.26.
- [ ] Semua view/frontend — Dashboard ✅, Pengaturan Instansi ✅, Pencarian ✅, Cetak PDF ✅. **Belum**: Surat Masuk (index/create/edit/show), Surat Keluar (index/create/edit/show), Klasifikasi ×3 (index/create/edit), User (index/create), Pengajuan Hapus Lampiran (index), Auth Login (sudah ada).
- [ ] Testing
- [ ] Deployment

Detail lengkap: Bagian 11 (Roadmap).

### 🔴 Klaim Pekerjaan Aktif (Koordinasi Antar-Agent)

> Diisi setiap kali sebuah agent mulai mengerjakan bagian yang bisa tumpang tindih dengan agent lain. **Cek tabel ini SEBELUM mulai kerja** — kalau area yang mau kamu sentuh sudah diklaim, JANGAN kerjakan ulang; tunggu sampai statusnya "selesai & disinkron" di sini.

| Area | Status | Diklaim sejak | Catatan |
|---|---|---|---|
| CRUD Surat Keluar | ✅ Selesai & disinkron & **cross-checked 100%** | 26 Agu 2026 (Controller+Update), 28 Agu 2026 (Store) | `SuratKeluarController.php` + `UpdateSuratKeluarRequest.php` (kode ASLI dari sesi lain) diupload user & dicocokkan ke model `SuratKeluar` asli — **tidak ada bug relasi**, semua (`primer/sekunder/tersier/petugas/drafKonten`) benar. Beberapa temuan minor non-bug dicatat di 12.13 (komentar `file_path` basi, tidak ada `try/catch` di `destroy()`, dll). `StoreSuratKeluarRequest.php` **sudah di-cross-check 28 Agu 2026** — field cocok 100% `$fillable`, `nomor_surat`/`user_id` sengaja dikecualikan (didokumentasikan), validasi hierarki konsisten dengan Update. **Ketiga file asli CRUD Surat Keluar kini 100% terverifikasi, tidak ada bug ditemukan sama sekali.** |
| CRUD Surat Masuk | ✅ Selesai & disinkron | 26 Agu 2026 | Sengketa **resolved**: dikonfirmasi user file lama stub kosong + Form Request belum pernah ada. CRUD lengkap (controller + 2 Form Request) dibuat ulang dari nol, dibundel `pradana-crud-surat-masuk.zip`. Model `SuratMasuk` asli **sudah diupload & diverifikasi** (26 Agu 2026) — `$fillable` cocok 100%, relasi `primer()`/`sekunder()`/`tersier()`/`lampiran()` benar sesuai tebakan awal, HANYA relasi ke `users` yang meleset (`petugas()`, bukan `user()`) — sudah diperbaiki. Lihat 12.12. |
| Google Drive integration (`GoogleDriveService`, `Lampiran` model, migration `lampiran`, `config/gdrive.php`) | ✅ Dilampirkan ulang | 24 Agu 2026 (didesain), 26 Agu 2026 (dilampirkan ulang) | Filenya sempat "hilang" dari konteks agent yang bikin controller (lihat catatan 12.9). Sudah di-attach ulang sebagai `pradana-fix-lampiran-gdrive.zip` — **wajib disalin ke project sebenarnya**, jangan didesain ulang dari nol. |
| CRUD Klasifikasi (Primer/Sekunder/Tersier) | ✅ Selesai & disinkron | 26 Agu 2026 | 3 controller (`KlasifikasiPrimerController`, `KlasifikasiSekunderController`, `KlasifikasiTersierController`) + 6 Form Request, dibundel `pradana-crud-klasifikasi.zip`. Awalnya ditulis dengan asumsi nama relasi yang SALAH (`klasifikasiSekunder()` dst) karena model belum diupload — **sudah diperbaiki** setelah user upload `KlasifikasiPrimer.php`/`Sekunder.php`/`Tersier.php` asli (26 Agu 2026): relasi yang benar adalah `sekunder()`, `primer()`, `tersier()` (nama pendek, tanpa prefix). $fillable sudah cocok dengan asumsi awal. |
| Auth (Login/Register/Logout) | ✅ Selesai & cross-checked | 28 Agu 2026 | `AuthController.php`, `UserController.php`, `LoginRequest.php`, `StoreUserRequest.php` dibuat & di-cross-check ke `User.php` + `web.php` asli (lihat 12.14). `getAuthPassword()` benar, `$fillable` cocok 100%, tidak ada mutator PIN (hash manual sudah tepat), route sudah digabung ke `web.php`. A3 dikonfirmasi user (admin bikin akun langsung) — sekarang [LOCKED]. Belum ada view (tergantung G1), middleware role (blocked B1), dan ganti/reset PIN (A4/A5) — semua di luar scope sesi ini. |
| DashboardController | ✅ Selesai & cross-checked | 28 Agu 2026 | `DashboardController.php` dibuat (lihat 12.15). Semua relasi (`primer`/`petugas`/`user`) dicek terhadap model asli — tidak ada bug. Belum ada view (tergantung G1). |
| Lampiran (`LampiranController` + route) | ✅ Selesai (baru) | 31 Agu 2026 | ⚠️ **Koreksi klaim lama**: baris "Integrasi Google Drive" di Bagian 3 sebelumnya keliru bilang controller ini sudah ada — ternyata belum pernah dibuat, cuma migration `lampiran` yang ada. Dibuat baru dari `Lampiran.php`/`GoogleDriveService.php`/`config/gdrive.php`/migration/`web.php` **asli** yang diupload user (tidak ada tebakan `$fillable`/signature method). Route digabung ke `web.php` asli. Struktur folder Drive **sudah dikonfirmasi user** (C6). `destroy()` sudah dihapus, digantikan alur di bawah. Model `PengaturanInstansi.php` **sudah diverifikasi 31 Agu 2026** (lihat 12.18) — cache folder ID via assignment langsung terbukti perlu karena `gdrive_*` memang tidak ada di `$fillable`. |
| Pengajuan Hapus Lampiran (tabel + model + `PengajuanHapusLampiranController`) | ✅ Selesai (baru) | 31 Agu 2026 | Fitur baru, langsung dari keputusan user (bukan tebakan): hapus lampiran cuma via pengajuan (staf) → persetujuan (admin), hanya utk surat >5 tahun (E1/E4 [LOCKED]). Tabel `pengajuan_hapus_lampiran` baru (bukan bagian 10 tabel awal). Lihat 12.17 untuk 1 asumsi teknis yang masih perlu dicek (kolom tanggal acuan umur surat). |
| CRUD Pengaturan Instansi | ✅ Selesai & cross-checked | 31 Agu 2026 | `PengaturanInstansiController` + `UpdatePengaturanInstansiRequest` dibuat dari skema, lalu **di-cross-check terhadap `PengaturanInstansi.php` + `web.php` asli di sesi yang sama** — `$fillable` cocok 100%, tidak ada bug. Route sudah digabung ke `web.php` asli. Logo lokal dikonfirmasi user (C7). Lihat 12.18. Sisa minor: panjang kolom string masih tebakan (migration belum diperiksa). |
| Generate PDF Surat Keluar | 🔄 Route sudah tergabung, belum cross-checked | 1 Sep 2026 | `CetakSuratKeluarController` (berdiri sendiri) + view `surat-keluar/cetak.blade.php`, dari skema Bagian 5 + relasi terverifikasi (12.13). **Route sudah digabung ke `web.php` asli** (12.24). Dependency `barryvdh/laravel-dompdf` belum di-`composer require`. Lihat 12.20. |
| Pencarian Arsip Global | 🔄 Terintegrasi penuh, belum cross-checked | 1 Sep 2026 | `PencarianController` (berdiri sendiri) dari skema Bagian 5. **Route sudah digabung ke `web.php` asli, view sudah `@extends('layouts.app')`** (12.24). Lihat 12.21. |
| Logging Aktivitas Otomatis | ✅ Aktif | 1 Sep 2026 | Trait `LogsAktivitas` + 9 Model Observer, **sudah diregistrasikan ke `AppServiceProvider::boot()` asli**. Lihat 12.22/12.24. |
| Middleware RBAC (B1) | 🔄 Middleware dibuat, retrofit dikerjakan user sendiri | 1 Sep 2026 | B1 [LOCKED #16]. `EnsureIsAdmin` dibuat. Ditemukan `bootstrap/app.php` asli struktur lama (lihat K3) — retrofit ke controller lama sengaja TIDAK dikerjakan agent (permintaan user). Lihat 12.23/12.24. |

**Aturan pakai tabel ini:**
- Sebelum mengerjakan area besar (controller, view, service baru), tambahkan baris di sini dengan status 🔄.
- Setelah selesai, update status jadi ✅ **dan** pindahkan hasil keputusannya (nama route, nama class, konvensi validasi, dll) ke bagian yang relevan (Bagian 8 kalau final, atau catatan baru di Bagian 12).
- **Selalu upload/salin file HASIL AKHIR ke sesi sinkronisasi berikutnya** — jangan cuma update AGENTS.md tanpa file aslinya, karena itu yang menyebabkan masalah di baris "CRUD Surat Masuk" di atas.

---

## 4. Peta File yang Sudah Ada

```
database/
├── migrations/
│   ├── 2026_07_01_000001_create_users_table.php
│   ├── 2026_07_01_000002_create_pengaturan_instansi_table.php
│   ├── 2026_07_01_000003_create_klasifikasi_primer_table.php
│   ├── 2026_07_01_000004_create_klasifikasi_sekunder_table.php
│   ├── 2026_07_01_000005_create_klasifikasi_tersier_table.php
│   ├── 2026_07_01_000006_create_surat_masuk_table.php
│   ├── 2026_07_01_000007_create_surat_keluar_table.php
│   ├── 2026_07_01_000008_create_lampiran_table.php
│   ├── 2026_07_01_000009_create_draf_konten_surat_keluar_table.php
│   ├── 2026_07_01_000010_create_aktivitas_table.php
│   └── 2026_08_31_000001_create_pengajuan_hapus_lampiran_table.php  ← baru, 31 Agu 2026, lihat 12.17
└── seeders/DatabaseSeeder.php

app/
├── Models/{User,PengaturanInstansi,KlasifikasiPrimer,KlasifikasiSekunder,KlasifikasiTersier,SuratMasuk,SuratKeluar,Lampiran,DrafKontenSuratKeluar,Aktivitas}.php
├── Models/PengajuanHapusLampiran.php   ← baru, 31 Agu 2026, lihat 12.17
├── Services/GoogleDriveService.php
└── Http/Controllers/
    ├── SuratMasukController.php            ← DIBANGUN ULANG 26 Agu 2026 (file lama stub kosong, lihat Bagian 3); **diupdate 1 Sep 2026** — `ensureAdmin()` di `destroy()` diganti `abort_unless(...->isAdmin(),...)` inline (pola sama seperti `SuratKeluarController::destroy()`), lihat 12.25
    ├── SuratKeluarController.php           ← baru, 26 Agu 2026
    ├── KlasifikasiPrimerController.php     ← baru, 26 Agu 2026, lihat 12.11; **diupdate 1 Sep 2026** — `ensureAdmin()` dihapus, mutasi diproteksi lewat middleware `admin` di route, lihat 12.25
    ├── KlasifikasiSekunderController.php   ← baru, 26 Agu 2026, lihat 12.11; **diupdate 1 Sep 2026**, sama seperti Primer, lihat 12.25
    ├── KlasifikasiTersierController.php    ← baru, 26 Agu 2026, lihat 12.11; **diupdate 1 Sep 2026**, sama seperti Primer, lihat 12.25
    ├── AuthController.php                  ← baru, 28 Agu 2026, cross-checked, lihat 12.14
    ├── UserController.php                  ← baru, 28 Agu 2026, cross-checked, lihat 12.14; **diupdate 1 Sep 2026** — `ensureAdmin()` dihapus, resource diproteksi middleware `admin` di route, lihat 12.25
    ├── DashboardController.php             ← baru, 28 Agu 2026, cross-checked, lihat 12.15
    ├── LampiranController.php              ← BENAR-BENAR baru, 31 Agu 2026 (sebelumnya diklaim ada, ternyata belum pernah dibuat), lihat 12.16; `destroy()` sudah dihapus 31 Agu 2026 (lihat 12.17)
    ├── PengajuanHapusLampiranController.php ← baru, 31 Agu 2026, lihat 12.17; **SENGAJA TIDAK diretrofit 1 Sep 2026** — campuran aksi publik (staf) & admin, `ensureAdmin()` private dianggap lebih cocok di sini, lihat 12.25
    ├── PengaturanInstansiController.php     ← baru, 31 Agu 2026, cross-checked, lihat 12.18; **diupdate 1 Sep 2026** — `ensureAdmin()` dihapus, diproteksi middleware `admin` di route (`Route::singleton()->middleware('admin')`), lihat 12.25
    ├── CetakSuratKeluarController.php       ← baru, 1 Sep 2026, berdiri sendiri, route sudah tergabung ke web.php (12.24), belum cross-checked ke model, lihat 12.20
    └── PencarianController.php              ← baru, 1 Sep 2026, berdiri sendiri, route sudah tergabung ke web.php (12.24), belum cross-checked ke model, lihat 12.21

app/Http/Middleware/
└── EnsureIsAdmin.php                       ← baru, 1 Sep 2026, implementasi B1 [LOCKED #16]. **SUDAH DIPAKAI** sejak 1 Sep 2026 (dialiaskan `admin` di `app/Http/Kernel.php`, dipasang di route Klasifikasi ×3/Pengaturan Instansi/User), lihat 12.23/12.25

app/Http/Kernel.php                         ← file LAMA yang keberadaannya baru terkonfirmasi 1 Sep 2026 (lewat `bootstrap/app.php` 12.24 & laporan retrofit 12.25) — **diupdate 1 Sep 2026**, alias `admin` ditambahkan ke `$middlewareAliases`. Isi lengkapnya SEBELUM perubahan ini belum pernah dilihat sesi manapun.

app/Traits/
└── LogsAktivitas.php                       ← baru, 1 Sep 2026, dipakai 9 Observer di bawah, lihat 12.22

app/Observers/  ← baru, 1 Sep 2026, SUDAH diregistrasikan di AppServiceProvider::boot() asli 1 Sep 2026 (12.24) — AKTIF (lihat 12.22)
├── SuratMasukObserver.php
├── SuratKeluarObserver.php
├── KlasifikasiPrimerObserver.php
├── KlasifikasiSekunderObserver.php
├── KlasifikasiTersierObserver.php
├── LampiranObserver.php
├── PengajuanHapusLampiranObserver.php
├── PengaturanInstansiObserver.php
└── UserObserver.php

app/Http/Requests/
├── StoreSuratMasukRequest.php              ← baru, 26 Agu 2026 (sebelumnya tidak pernah ada)
├── UpdateSuratMasukRequest.php             ← baru, 26 Agu 2026 (extends Store, lihat 12.6)
├── StoreSuratKeluarRequest.php             ← baru, 26 Agu 2026
├── UpdateSuratKeluarRequest.php            ← baru, 26 Agu 2026
├── StoreKlasifikasiPrimerRequest.php       ← baru, 26 Agu 2026
├── UpdateKlasifikasiPrimerRequest.php      ← baru, 26 Agu 2026 (extends Store)
├── StoreKlasifikasiSekunderRequest.php     ← baru, 26 Agu 2026
├── UpdateKlasifikasiSekunderRequest.php    ← baru, 26 Agu 2026 (extends Store)
├── StoreKlasifikasiTersierRequest.php      ← baru, 26 Agu 2026
├── UpdateKlasifikasiTersierRequest.php     ← baru, 26 Agu 2026 (extends Store)
├── LoginRequest.php                        ← baru, 28 Agu 2026, cross-checked, lihat 12.14
├── StoreUserRequest.php                    ← baru, 28 Agu 2026, cross-checked, lihat 12.14
├── StoreLampiranRequest.php                ← baru, 31 Agu 2026, lihat 12.16
└── UpdatePengaturanInstansiRequest.php      ← baru, 31 Agu 2026, cross-checked, lihat 12.18

routes/web.php                              ← baru, 26 Agu 2026, lihat 12.11; **diupdate 28 Agu 2026** dengan route Auth (login/logout/users), lihat 12.14; **diupdate lagi 31 Agu 2026** dengan route Lampiran, lihat 12.16; **diupdate lagi (sesi sama) 31 Agu 2026** dengan route Pengajuan Hapus Lampiran, menggantikan route `DELETE lampiran/{lampiran}` yang sempat ada — lihat 12.17; **diupdate lagi 31 Agu 2026 (sesi baru)** dengan route Pengaturan Instansi (`Route::singleton()->only(['edit','update'])`) — lihat 12.18; **diupdate lagi 1 Sep 2026** (file asli diupload user) dengan route Cetak PDF Surat Keluar & Pencarian Arsip Global, plus 1 komentar basi diperbaiki — lihat 12.24; **diupdate lagi 1 Sep 2026** (retrofit B1, dikerjakan & dilaporkan user sendiri) — `Route::resource('users', ...)`, `Route::singleton('pengaturan-instansi', ...)`, dan mutasi ketiga resource Klasifikasi (`->except(['show','index'])`) diberi `->middleware('admin')`, lihat 12.25

app/Providers/AppServiceProvider.php        ← **diupdate 1 Sep 2026** (file asli diupload user) — 9 Observer diregistrasikan di `boot()`, lihat 12.24

config/gdrive.php
```

File `Models/*`, `Services/GoogleDriveService.php`, dan `config/gdrive.php` ada di `pradana-laravel-schema.zip` (diberikan di sesi sebelumnya). ⚠️ **Koreksi 31 Agu 2026**: kalimat ini sebelumnya juga menyebut `Http/Controllers/LampiranController.php` ada di zip yang sama — ternyata **keliru**, file itu belum pernah dibuat sampai 31 Agu 2026 (lihat Bagian 3 & 12.16). **Kecuali**: `KlasifikasiPrimer.php`/`KlasifikasiSekunder.php`/`KlasifikasiTersier.php`, `SuratMasuk.php`, `User.php`, dan `Aktivitas.php` sudah diupload ulang sebagai file lepas untuk verifikasi nama relasi (26 & 28 Agu 2026) — lihat 12.11, 12.12, 12.14, 12.15. `Lampiran.php` **sudah diupload ulang & dipakai langsung** 31 Agu 2026 (lihat 12.16) — relasinya adalah `lampiranable()` (morphTo) dan `pengunggah()` (belongsTo `User`, BUKAN `diunggahOleh()`). Model `SuratKeluar.php` masih belum pernah diverifikasi ulang dengan cara yang sama (controller-nya dianggap sudah lengkap dari sesi sebelumnya, tapi belum ada sesi yang cross-check model aslinya persis seperti Klasifikasi & Surat Masuk). Model `PengaturanInstansi.php` **sudah di-cross-check 31 Agu 2026** (lihat 12.18) — `$fillable` cocok 100% dengan yang dipakai `PengaturanInstansiController`/`UpdatePengaturanInstansiRequest`. Model `DrafKontenSuratKeluar.php` masih belum pernah di-cross-check ulang.

File `SuratKeluarController.php` dan 2 Form Request-nya dibuat di sesi chat **terpisah** (26 Agu 2026), diunduh satu per satu (bukan satu zip). **Pastikan sudah disalin** ke lokasi yang sesuai di project sebenarnya sebelum sesi berikutnya.

File Klasifikasi (3 controller + 6 Form Request), `SuratMasukController.php` + 2 Form Request-nya, dan `routes/web.php` dibuat/diperbaiki di sesi ini (26 Agu 2026): Klasifikasi dibundel `pradana-crud-klasifikasi.zip` (v2, sudah diperbaiki setelah verifikasi model), Surat Masuk dibundel `pradana-crud-surat-masuk.zip` — lihat 12.11 untuk asumsi yang masih perlu diverifikasi (khususnya relasi model `SuratMasuk`).

**Frontend (baru, 31 Agu 2026, lihat 12.19; layout & view Pencarian diupdate 1 Sep 2026, lihat 12.24):**
```
resources/views/
├── layouts/
│   └── app.blade.php                   ← layout dasar, dipakai semua halaman, meniru gaya visual preview HTML sistem lama; **+1 link nav "Pencarian Arsip" 1 Sep 2026**, sisanya sama persis versi 31 Agu 2026
├── pengaturan-instansi/
│   └── edit.blade.php                  ← terintegrasi penuh ke layouts/app.blade.php
├── surat-keluar/
│   └── cetak.blade.php                 ← baru, 1 Sep 2026, STANDALONE (bukan pakai layouts/app, dirender dompdf), lihat 12.20
└── pencarian/
    └── index.blade.php                 ← baru, 1 Sep 2026, **`@extends('layouts.app')` sejak 1 Sep 2026** (sebelumnya sempat standalone di draf pertama hari yang sama, sudah ditulis ulang setelah layout asli diupload), lihat 12.21/12.24
```
Semua view lain (Surat Masuk/Keluar, Klasifikasi ×3, Lampiran, Pengajuan Hapus Lampiran, Users, Dashboard, Auth login) **belum dibuat sama sekali** — controller-nya sudah `return view(...)` ke nama view yang belum ada filenya.

---

## 5. Skema Database [LOCKED]

> 10 tabel awal (24 Agu 2026) + 1 tabel baru `pengajuan_hapus_lampiran` (31 Agu 2026, lihat 12.17) = **11 tabel total**.

### `users`

`id` PK · `nama_lengkap` string(150) · `email` string(150) unique · `pin` string (hash) · `role` enum(`admin`,`perangkat`,`kepala`) default `perangkat` · `email_verified_at` nullable · `remember_token` · `timestamps` · `softDeletes`

### `pengaturan_instansi`

`id` PK · `nama_instansi` · `jenis_instansi` · `alamat_instansi` · `no_telp` · `email` · `logo_path` nullable · `gdrive_root_folder_id` nullable · `gdrive_folder_surat_masuk_id` nullable · `gdrive_folder_surat_keluar_id` nullable · `timestamps`

### `klasifikasi_primer`

`id` PK · `kode` string(5) unique · `nama` string(100) · `timestamps`

### `klasifikasi_sekunder`

`id` PK · `klasifikasi_primer_id` FK→klasifikasi_primer (cascade delete) · `kode` · `nama` · unique(`klasifikasi_primer_id`,`kode`) · `timestamps`

### `klasifikasi_tersier`

`id` PK · `klasifikasi_sekunder_id` FK→klasifikasi_sekunder (cascade delete) · `kode` · `nama` · unique(`klasifikasi_sekunder_id`,`kode`) · `timestamps`

### `surat_masuk`

`id` PK · `pengirim`,`jabatan_pengirim`,`instansi_pengirim` · `klasifikasi_primer_id` FK wajib (restrict delete) · `klasifikasi_sekunder_id`,`klasifikasi_tersier_id` FK nullable (null on delete) · `sifat` enum(`mendesak`,`penting`,`rahasia`,`biasa`) default `biasa` · `nomor_surat` string(100) index · `kota_asal`,`provinsi_asal` · `tanggal_surat` date · `tanggal_diterima` date · `perihal` string(255) · `ringkasan` text nullable · `status_berkas` enum(`asli`,`salinan`) · `status_arsip` enum(`aktif`,`inaktif`) index · `lokasi_fisik` · `user_id` FK→users (restrict delete) · `timestamps`

### `surat_keluar`

Sama seperti `surat_masuk`, kecuali: `penerima`/`jabatan_penerima`/`instansi_penerima` (bukan pengirim), `kota_tujuan`/`provinsi_tujuan` (bukan asal), **tidak ada** `tanggal_diterima`, `nomor_surat` **unique**.

### `lampiran`

`id` PK · `lampiranable_id`+`lampiranable_type` (morphs → `surat_masuk`/`surat_keluar`) · `google_drive_file_id` string **unique** · `google_drive_folder_id` nullable · `nama_file` string(255) · `mime_type` nullable · `ukuran` bigint nullable (bytes) · `diunggah_oleh` FK→users nullable (null on delete) · `timestamps`

### `draf_konten_surat_keluar`

`id` PK · `surat_keluar_id` FK **unique** (cascade delete) · `lampiran` string(100) nullable ⚠️ **teks notasi formal surat** (misal "1 Berkas"), BUKAN referensi file — lihat Bagian 12.1 · `alamat_tujuan`,`salam_pembuka`,`salam_penutup`,`atas_nama`,`jabatan_penandatangan`,`nip_nik` nullable · `isi_surat` longtext nullable · `tembusan` text nullable · `timestamps`

### `aktivitas`

`id` PK · `user_id` FK→users (restrict delete) · `aksi` string(255) · `subjek_type`+`subjek_id` nullableMorphs · `timestamps`

### `pengajuan_hapus_lampiran` ⭐ baru, 31 Agu 2026 (lihat 12.17)

`id` PK · `lampiran_id` FK→lampiran nullable (**null on delete**, BUKAN cascade — riwayat tetap ada untuk audit setelah lampiran benar-benar terhapus) · `nama_file_snapshot` string(255) (snapshot nama file saat pengajuan dibuat) · `diajukan_oleh` FK→users (null on delete) · `alasan` text nullable · `status` enum(`menunggu`,`disetujui`,`ditolak`) default `menunggu` index · `diproses_oleh` FK→users nullable (null on delete) · `diproses_pada` timestamp nullable · `catatan_admin` text nullable · `timestamps`

### Relasi
```
klasifikasi_primer 1─N klasifikasi_sekunder 1─N klasifikasi_tersier
klasifikasi_primer/sekunder/tersier 1─N surat_masuk, surat_keluar
users 1─N surat_masuk, surat_keluar, aktivitas, lampiran(diunggah_oleh)
surat_masuk, surat_keluar 1─N lampiran (polymorphic)
surat_keluar 1─1 draf_konten_surat_keluar
aktivitas N─1 users, opsional polymorphic → surat_masuk/surat_keluar
lampiran 1─N pengajuan_hapus_lampiran
users 1─N pengajuan_hapus_lampiran (diajukan_oleh, diproses_oleh)
```

---

## 6. Environment Variables & Dependency

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pradana
DB_USERNAME=
DB_PASSWORD=

GOOGLE_DRIVE_CREDENTIALS_PATH=storage/app/google/service-account.json
GOOGLE_DRIVE_ROOT_FOLDER_ID=
```

**Sudah dibutuhkan:**
```
composer require google/apiclient:^2.15
```

**Sudah dipakai kodenya, TAPI belum di-`composer require` di project manapun** (1 Sep 2026): `barryvdh/laravel-dompdf` (F2, sudah [DEFAULT]) — dipakai langsung oleh `CetakSuratKeluarController` (lihat 12.20). Ini beda status dari sebelumnya ("kemungkinan dibutuhkan") karena kodenya sudah nyata ada, cuma dependency-nya belum diinstal di lingkungan manapun.

**Kemungkinan dibutuhkan** (tergantung Bagian 10): `spatie/laravel-permission` (B1), `maatwebsite/excel` (I1), `laravel/horizon`+`predis/predis` (K4).

---

## 7. Setup Google Drive

1. Buat **Service Account** di Google Cloud Console, aktifkan Google Drive API, download JSON key → simpan di `storage/app/google/service-account.json` (pastikan masuk `.gitignore`).
2. ⚠️ Service Account non-Workspace **tidak punya kuota "My Drive" sendiri**. Wajib salah satu:
   - Share folder Drive akun asli (akses Editor) ke email Service Account, atau
   - Pakai **Shared Drive** (Google Workspace) dan beri akses Service Account di situ.
3. Isi `GOOGLE_DRIVE_ROOT_FOLDER_ID` di `.env` dengan ID folder tersebut.
4. Pakai `GoogleDriveService::getOrCreateFolder()` untuk subfolder "Surat Masuk"/"Surat Keluar", cache ID-nya ke `pengaturan_instansi.gdrive_folder_surat_masuk_id`/`gdrive_folder_surat_keluar_id`.

---

## 8. Keputusan Sudah Final [LOCKED]

| # | Keputusan |
|---|---|
| 1 | Klasifikasi 3 tabel berjenjang (bukan teks gabungan) |
| 2 | `Petugas` (teks) → `user_id` (FK) di semua tabel relevan |
| 3 | `Status Berkas` dipecah: `status_berkas` (asli/salinan) vs `status_arsip` (aktif/inaktif) |
| 4 | Konten generator PDF di tabel anak `draf_konten_surat_keluar` (relasi 1–1) |
| 5 | PIN disimpan ter-hash |
| 6 | `Konfigurasi` (key-value) → `pengaturan_instansi` (kolom tetap) |
| 7 | Google Drive: **Service Account** |
| 8 | Multi-lampiran per surat → tabel `lampiran` (polymorphic), kolom `file_path` lama dihapus |
| 9 | Akses file **privat**, hanya user login (stream lewat backend, bukan link publik) |
| 10 | `aktivitas` punya kolom polymorphic opsional (`subjek_type`/`subjek_id`) |
| 11 | A3: Registrasi user baru — **akun dibuat langsung oleh admin**, bukan self-register publik. Dikonfirmasi user 28 Agu 2026. |
| 12 | E1: Retensi arsip **5 tahun seragam untuk semua jenis surat** (bukan beda per klasifikasi sesuai JRA resmi). Dikonfirmasi user 31 Agu 2026 — **risiko diterima sadar**: mungkin tidak 100% sesuai regulasi kearsipan resmi, tapi itu pilihan user. |
| 13 | E4 (baru): **Hapus lampiran HANYA lewat alur pengajuan (staf) + persetujuan (admin)**, dan HANYA untuk lampiran pada surat yang sudah berumur lebih dari 5 tahun (turunan langsung dari #12/E1). Dikonfirmasi user 31 Agu 2026, lihat 12.17. **Catatan**: ini baru mengatur hapus LAMPIRAN (file), belum menjawab E3 (alur pemusnahan arsip/SURAT dengan berita acara) maupun B2 (siapa boleh hapus SURAT permanen) — dua-duanya masih [WAJIB TANYA USER] terpisah. |
| 14 | J1: Aplikasi **single-tenant, untuk 1 desa/kelurahan saja** (BUKAN multi-tenant). Dikonfirmasi user 31 Agu 2026 — sesuai asumsi yang sudah dipakai di skema & semua kode sejak awal, jadi **tidak ada perubahan skema/kode diperlukan**. Prioritas tertinggi di Bagian 10 sekarang terjawab. |
| 15 | G1: Frontend **Blade + Bootstrap**, replikasi fungsional tampilan versi lama (BUKAN Livewire/Inertia). Dikonfirmasi user 31 Agu 2026 — lihat 12.19 untuk mulai pengerjaan layout & view pertama. **Catatan**: tidak ada `Code.gs`/screenshot versi lama yang bisa dicek persis di sesi manapun sampai titik ini (lihat 12.16 poin 1), jadi "replikasi tampilan lama" dimaknai sebagai replikasi *alur/fungsi*, bukan tiruan piksel-demi-piksel — desain visual (warna, spacing, komponen) keputusan bebas agent, boleh direvisi. |
| 16 | B1: Matriks permission **disederhanakan jadi 2 tingkat** — `admin` vs non-admin. Role `kepala` dan `perangkat` DIPERLAKUKAN SAMA (non-admin), TIDAK dibedakan lagi meski kolom `users.role` skemanya tetap 3 nilai (Bagian 5, tidak berubah). Dikonfirmasi user 1 Sep 2026. **Implikasi**: middleware/Gate baru cukup 1 pengecekan biner (`isAdmin()`, sudah ada di `User.php` sejak 12.14), BUKAN matriks per-modul×per-role yang rinci. Kalau nanti ternyata `kepala` butuh hak berbeda dari `perangkat`, itu perubahan baru yang perlu dikonfirmasi ulang (balik jadi pertanyaan terpisah, bukan revisi B1 ini). Lihat 12.23. **Retrofit ke 6 controller lama SELESAI 1 Sep 2026** (dikerjakan & dilaporkan user sendiri, bukan lewat sesi ini), lihat 12.25. **Celah keamanan di `routes/web.php` ditutup 3 Sep 2026** — middleware `admin` kini dipasang langsung di route level (bukan hanya di controller), lihat 12.26. |
| 17 | D1: **Format nomor surat keluar menggunakan format sistem lama**: `{urutan 3 digit}/{kodeP.kodeS.kodeT}/{bulan romawi}/{tahun}` — contoh: `001/01.01.01/IX/2026`. Dikonfirmasi user 3 Sep 2026 (bisa disesuaikan nanti oleh user saat serah terima). Diimplementasikan di `SuratKeluarController::generateNomorSurat()`, lihat 12.26. |

---

## 9. Keputusan Default — Boleh Langsung Dipakai [DEFAULT]

| Kode | Keputusan | Alasan singkat |
|---|---|---|
| A1 | Login pakai kombinasi **email + PIN** (`Auth::attempt(['email'=>..,'password'=>$pin])`) | Pola standar Laravel, tidak perlu custom guard rumit. **⚠️ Catatan 31 Agu 2026**: preview frontend sistem lama yang diupload user (lihat 12.19) ternyata login **PIN SAJA** (tanpa email, form cuma 1 field `loginPin`) — beda dari asumsi ini. BELUM diubah (AuthController sudah kadung dibuat 28 Agu 2026 dengan email+PIN, lihat 12.14) — kalau user mau disamakan ke PIN-only, itu perubahan arsitektur Auth yang cukup besar (perlu cara lain identifikasi user selain email, misal PIN unik global), WAJIB dikonfirmasi eksplisit dulu, jangan diasumsikan. |
| A2 | PIN tetap **6 digit angka** | Sudah sesuai skema (`pin` string), sama seperti versi lama |
| A4 | Reset PIN lupa: **manual oleh admin** | Paling simpel untuk MVP, tidak perlu alur reset-via-email |
| A5 | User boleh ganti PIN sendiri | UX standar |
| B3 | Hanya **admin** yang boleh ubah data Klasifikasi | Data referensi sensitif |
| B4 | Hanya **admin** yang boleh ubah Pengaturan Instansi | Kop surat resmi |
| C1 | Tipe file: `pdf`, `jpg`, `jpeg`, `png` | Sama seperti versi lama |
| C2 | Maks ukuran file: **10MB** per file | Batas wajar untuk dokumen scan |
| C3 | Struktur folder Drive: **sama persis** versi lama (`Arsip_PRADANA / Surat Masuk\|Keluar / [Klasifikasi] / file`) | Konsistensi, tidak perlu redesain |
| C5 | Upload **sync** (langsung), bukan queue/async | Sama seperti versi lama; revisit kalau ternyata lambat |
| C6 | Detail struktur folder Drive (rincian dari C3), **dikonfirmasi user 31 Agu 2026**: (1) `GOOGLE_DRIVE_ROOT_FOLDER_ID` di `.env` DIPERLAKUKAN SEBAGAI folder "Arsip_PRADANA" itu sendiri, TIDAK dibuatkan subfolder "Arsip_PRADANA" terpisah; (2) subfolder klasifikasi CUKUP level **primer** saja (`"{kode} - {nama}"`), tidak turun ke sekunder/tersier | Konfirmasi langsung user, lihat 12.17 |
| C7 | Logo instansi (`pengaturan_instansi.logo_path`) disimpan **LOKAL** (disk `public`, folder `logo/`), **BUKAN** lewat Google Drive — beda dari lampiran surat. **Dikonfirmasi user 31 Agu 2026** ("logo gaperlu di gdrive") | Logo kop surat itu aset publik/statis, bukan dokumen arsip rahasia — aturan privasi Drive (Bagian 2) tidak relevan. Lihat 12.18 |
| D2 | Nomor urut surat keluar **direset tiap tahun** | Sudah tersirat dari `getNextSequenceNumber()` versi lama (filter by `currentYear`) |
| D3 | Nomor urut dihitung **global per tahun**, bukan per klasifikasi | Sudah tersirat dari logika lama — `getNextSequenceNumber()` TIDAK filter by klasifikasi, cuma by tahun |
| D5 | `surat_masuk.tanggal_diterima` tidak boleh lebih awal dari `tanggal_surat` (`after_or_equal`) | Logika bisnis wajar (surat tidak mungkin diterima sebelum ditulis); baru ditambahkan 26 Agu 2026 di `StoreSuratMasukRequest`, gampang dihapus kalau ternyata tidak diinginkan |
| E2 | Status ke "Inaktif" tetap **manual** (tombol), belum ada scheduled job | Sama seperti versi lama |
| F1 | Fitur "Buat Surat PDF" **dipertahankan** | Fitur inti versi lama |
| F2 | Render PDF pakai **`barryvdh/laravel-dompdf`** | Instalasi paling ringan, tanpa dependency Chrome/Node (dibanding Browsershot) |
| F3 | **1 template surat umum** (bukan multi-template) | Sama seperti versi lama |
| F4 | Kop surat: **1 setting global** | Sudah sesuai skema `pengaturan_instansi` (single row) |
| G2 | **Tidak perlu** dark mode | Tidak ada di versi lama |
| G3 | Desktop-first + responsive dasar (bukan PWA) | Sama seperti versi lama |
| K2 | Database: **MySQL** | Paling umum utk hosting Laravel di Indonesia; migration tetap kompatibel PostgreSQL kalau berubah |
| K3 | Laravel **13.x** (PHP ^8.3) | Versi stabil saat ini (rilis Maret 2026, Laravel 12 sudah masuk fase security-fix-only sejak 13 Agu 2026) — *cek ulang kalau sesi ini berjalan jauh setelah Agustus 2026*. **Catatan 1 Sep 2026 (lihat 12.24/12.25)**: `bootstrap/app.php` project ini pakai struktur LAMA (`App\Http\Kernel::class` masih ada & dipakai — dikonfirmasi 2×, lewat file asli 12.24 dan laporan retrofit 12.25 yang mendaftarkan alias `admin` ke situ), BUKAN struktur baru Laravel 11+. Ini **BUKAN berarti K3 salah** — project yang di-upgrade composer-nya ke Laravel 13 TIDAK otomatis pindah ke skeleton baru (migrasi struktur itu manual/opsional), jadi tetap konsisten kalau frameworknya memang 13.x tapi bootstrap-nya belum dimigrasi. Cuma perlu diingat: middleware alias didaftarkan di `Kernel.php`, BUKAN `bootstrap/app.php`, untuk project ini. |
| K4 | Tidak perlu queue worker di awal | Konsekuensi dari C5 (upload sync) |
| M1 | Log `aktivitas` yang ada dianggap cukup untuk MVP | Belum perlu audit trail lebih detail |
| M2 | Bahasa Indonesia saja, tidak ada i18n | Sama seperti versi lama |

---

## 10. Keputusan Belum Diambil — WAJIB TANYA USER [WAJIB TANYA USER]

> Agent DILARANG mengimplementasikan bagian yang bergantung pada poin-poin ini secara final tanpa konfirmasi. ~~Prioritas tertinggi: J1~~ — **J1 sudah terjawab 31 Agu 2026, lihat LOCKED #14.** ~~Prioritas berikutnya: B1~~ — **B1 sudah terjawab 1 Sep 2026 (versi disederhanakan), lihat LOCKED #16.** Belum ada prioritas eksplisit berikutnya — pilih dari daftar di bawah sesuai kebutuhan, atau tanyakan ke user kalau mau diprioritaskan.

| Kode | Pertanyaan | Dampak kalau salah asumsi | Asumsi sementara (kalau ada) |
|---|---|---|---|
| A6 | Perlu 2FA untuk login? | Keamanan | Tidak pakai dulu |
| B2 | Siapa yang boleh hapus arsip surat **permanen**? | Risiko kehilangan data arsip resmi | Admin only |
| C4 | Ada data lama di Google Sheets/Drive yang perlu dimigrasikan? Berapa banyak? | Menentukan perlu/tidaknya script importer | — |
| ~~D1~~ | ~~Format nomor surat~~ | — | **TERJAWAB 3 Sep 2026 — lihat LOCKED #17. Format: `{urutan 3 digit}/{kodeP.kodeS.kodeT}/{bulan romawi}/{tahun}`** |
| D4 | Daftar Klasifikasi (Kode Klasifikasi Arsip) sudah ada versi resminya, atau dibuat dari nol? | Data seeding awal | — |
| E3 | Perlu alur pemusnahan arsip (berita acara, dst) difasilitasi aplikasi? **Catatan 31 Agu 2026**: sudah ada preseden alur pengajuan+persetujuan untuk hapus LAMPIRAN (lihat LOCKED #13/E4, 12.17) — kalau user mau pola sama diperluas ke pemusnahan SURAT (dengan berita acara resmi), pertanyaan ini baru terjawab "ya". Sejauh ini scope-nya baru lampiran, bukan surat. | | Tidak, cukup status `inaktif` |
| H1 | Perlu notifikasi (email/WhatsApp/dst) untuk kejadian tertentu? | Scope fitur baru | Tidak ada notifikasi dulu |
| I1 | Perlu export laporan (Excel/PDF)? | Scope fitur baru | Tidak dulu |
| I2 | Perlu cetak "Buku Agenda Surat" format fisik? | Scope fitur baru | Tidak dulu |
| K1 | Target hosting (shared/VPS/cloud, provider mana)? | Tidak blocking untuk development, tapi blocking untuk deployment | — |
| L1/L2 | Kalau C4 = ya, siapa yang eksekusi migrasi data, dan kapan? | Timeline & scope | — |

---

## 11. Roadmap

- [ ] `composer create-project laravel/laravel` (versi sesuai K3) → salin file dari Bagian 4 ke lokasi yang sesuai
- [x] Config auth guard: verifikasi ke kolom `pin` — **ternyata tidak perlu ubah `config/auth.php`**, EloquentUserProvider otomatis panggil `User::getAuthPassword()` (lihat 12.14). Yang masih perlu dicek: apakah override method itu SUDAH ada di `User.php` asli — belum diverifikasi (model belum di-upload).
- [x] Login / Register / Logout (sesuai A1, A3) — dibuat & di-cross-check 28 Agu 2026 terhadap `User.php`+`web.php` asli, lihat 12.14. A3 dikonfirmasi user (admin bikin akun langsung), sekarang [LOCKED] di Bagian 8 #11. Selesai & siap pakai secara teknis (view belum dibuat, tergantung G1 — item roadmap terpisah).
- [x] Middleware role-based access control — **SELESAI & VERIFIED 3 Sep 2026**. B1 [LOCKED #16]. `EnsureIsAdmin` aktif, middleware `admin` terpasang di semua route admin (lihat 12.25/12.26).
- [x] CRUD Surat Masuk (controller + Form Request) — dibangun ULANG 26 Agu 2026 (versi sebelumnya ternyata stub kosong, klaim lama keliru), lihat Bagian 3, 4 & 12.12
- [x] CRUD Surat Keluar (controller + Form Request) — selesai 26 Agu 2026, lihat Bagian 3 & 4
- [x] Routes `web.php` untuk Surat Masuk & Surat Keluar — ⚠️ **koreksi 31 Agu 2026**: baris ini basi, tidak diupdate sejak `web.php` beneran dibuat 26 Agu 2026 (lihat Bagian 3 & 12.11); sudah `Route::resource(...)` penuh untuk keduanya.
- [x] Upload lampiran → `LampiranController` dibuat 31 Agu 2026 (lihat 12.16), panggil `GoogleDriveService::upload()` → simpan ke tabel `lampiran`. Unduh (stream, `middleware('auth')`) juga sudah ada. Struktur folder Drive sudah dikonfirmasi user (C6). Hapus lampiran **dipisah** ke item roadmap "Pengajuan hapus lampiran" di bawah (bukan lagi admin-langsung).
- [x] Pengajuan hapus lampiran (staf ajukan → admin approve, hanya utk surat >5 tahun) — `PengajuanHapusLampiranController` + tabel `pengajuan_hapus_lampiran` baru, dibuat 31 Agu 2026 sesuai keputusan user langsung. Lihat 12.17. **Bagian dari fitur "Manajemen retensi/penyusutan" di bawah** — 1 tahap sudah selesai (hapus lampiran), yang lain (mis. alur pemusnahan SURAT/arsip dengan berita acara, auto-flag "usang") masih terbuka, lihat E3.
- [x] CRUD Klasifikasi — ⚠️ **koreksi 31 Agu 2026**: baris ini juga basi, tidak diupdate sejak selesai 26 Agu 2026 & cross-checked (lihat Bagian 3 & 12.11).
- [x] CRUD Pengaturan Instansi — controller+Form Request dibuat & cross-checked 31 Agu 2026 (lihat Bagian 3 & 12.18), route sudah digabung ke `web.php` asli, `$fillable` cocok 100%. Logo lokal dikonfirmasi (C7).
- [x] Auto-nomor surat keluar — logika counting + retry sudah ada di `SuratKeluarController::generateNomorSurat()` (global per tahun, reset tiap tahun sesuai D2/D3). **Format D1 final diimplementasikan 3 Sep 2026** (lihat 12.26): `{urutan 3 digit}/{kodeP.kodeS.kodeT}/{bulan romawi}/{tahun}`.
- [x] Generate PDF surat keluar (DomPDF, sesuai F2) — `CetakSuratKeluarController` dibuat 1 Sep 2026, **route sudah digabung ke `web.php`** (12.24). **`barryvdh/laravel-dompdf ^3.1` di-install 3 Sep 2026** (lihat 12.26). BELUM: cross-check ke model asli.
- [x] Dashboard statistik — `DashboardController.php` dibuat 28 Agu 2026, lihat 12.15. **View `dashboard.index` dibuat 3 Sep 2026** (lihat 12.26). Semua relasi cross-checked, tidak ada bug. Selesai & siap pakai.
- 🔄 *(parsial, 1 Sep 2026)* Pencarian arsip global — `PencarianController` (berdiri sendiri) dibuat, **route sudah digabung ke `web.php` asli, view sudah `@extends('layouts.app')`** (12.24). BELUM: cross-check ke model asli.
- [x] *(parsial)* Manajemen retensi/penyusutan — E1 **sudah terjawab** (5 tahun seragam, LOCKED #12), dan 1 mekanisme retensi (hapus lampiran via pengajuan+persetujuan setelah >5 tahun) **sudah jalan**, lihat baris "Pengajuan hapus lampiran" di atas & 12.17. Yang BELUM: toggle otomatis `status_arsip` surat ke "inaktif" setelah 5 tahun (masih manual, sesuai E2 [DEFAULT]), dan alur pemusnahan SURAT/arsip formal dengan berita acara (masih blocked by E3).
- [x] Logging aktivitas otomatis di setiap aksi — trait `LogsAktivitas` + 9 Observer dibuat & **SUDAH AKTIF** (diregistrasikan ke `AppServiceProvider::boot()` asli 1 Sep 2026), lihat 12.22/12.24. Belum ada UI utk melihat log-nya (cuma penulisan) — item terpisah kalau dibutuhkan.
- [ ] View/frontend (sesuai G1)
- [ ] Testing
- [ ] Deployment (**blocked by K1**)
- [ ] Script migrasi data lama (**kondisional, blocked by C4**)

---

**Lihat riwayat sesi & catatan teknis detail (Bagian 12 & 13) di `AGENTS_HISTORY.md`, file pasangan di folder yang sama.**
