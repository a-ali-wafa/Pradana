# AGENTS.md — PRADANA (konteks teknis untuk AI agent)

> Untuk agent, bukan stakeholder. Riwayat detail (Bagian 12/13 lama) ada di `AGENTS_HISTORY.md` — buka on-demand kalau ada rujukan spesifik. **Rujukan lama berbentuk "AGENTS.md Bagian 5/8/9/10/11" atau "lihat 12.x" menunjuk ke nomor bagian versi sebelum 3 Okt 2026** (skema database lengkap + daftar keputusan [LOCKED]/[WAJIB TANYA] yang lama); isinya sekarang di `AGENTS_HISTORY.md`, kecuali keputusan yang tetap berlaku — lihat Bagian 4 file ini.
> Diperbarui: **4 Okt 2026** — ditulis ulang dari hasil review kode + register keputusan user (`Obsidian Vault/Pradana/Daftar Keputusan PRADANA.md`, diisi 3 Okt 2026).

## 0. Aturan main

1. Baca file ini penuh sebelum mengubah kode. Kode = sumber kebenaran; dokumen ini mencatat **keputusan**, bukan asumsi.
2. Item `[LOCKED]` = sudah diputuskan user. Jangan ubah tanpa konfirmasi baru; kalau ubah, tambahkan baris di `AGENTS_HISTORY.md` Bagian 13.
3. Item `[DEFAULT-agent]` = keputusan yang user delegasikan ke agent ("atur yang terbaik"). Boleh direvisi bebas, catat alasannya di kode kalau tidak lazim.
4. Jangan menerjemahkan nama domain Indonesia (`surat_masuk`, bukan `incoming_letters`). Sengaja.
5. Setelah kerja berarti: update Bagian 3 (status) + `AGENTS_HISTORY.md` Bagian 13.

## 1. Project

Aplikasi arsip surat masuk/keluar untuk kantor kelurahan/desa. Migrasi dari Google Apps Script + Sheets + Drive → Laravel + database relasional. Fitur inti: input surat, klasifikasi 3 level, auto-nomor surat keluar, generate PDF, dashboard, pencarian, retensi/pemusnahan arsip, log aktivitas. **Akan dipakai sungguhan oleh kantor** (user W1=d), target ±2 bulan dari 3 Okt 2026 → **±awal Desember 2026**.

## 2. Stack riil [LOCKED]

| Aspek | Kenyataan sekarang | Target keputusan |
|---|---|---|
| Framework | Laravel **12.69.3** (upgrade selesai 4 Okt, langkah 1 Bagian 7) — struktur bootstrap LAMA (`app/Http/Kernel.php` + `config/app.php['providers']`), bukan skeleton 11+ | tetap 12.x; middleware alias didaftarkan di `Kernel.php`, BUKAN `bootstrap/app.php` |
| PHP | 8.2.12 (`C:\xampp\php`) | tetap 8.2 |
| Database | **MariaDB 10.4.32** (bawaan XAMPP), DB `pradana`; skema = **13 migration hasil squash S11** | tetap MariaDB (S2=a). Dokumen lama menyebut "MySQL" = nama driver PDO saja. `restrictOnDelete()` di MariaDB berperilaku `NO ACTION` — jangan andalkan `RESTRICT` asli |
| Auth | PIN-based: kolom `users.pin` ter-hash bcrypt, `User::getAuthPassword()` di-override, login **email + PIN 8 digit** + rate limit 5/menit, **tanpa remember-me** (kolom `remember_token` sudah dihapus) | tetap (A1=a, L-11, L-12) |
| Frontend | Blade + Bootstrap 5, desktop-first, tanpa dark mode, tanpa i18n | tetap (X1/G1/G2/M2/V2=a) |
| Penyimpanan file | **Disk lokal `arsip`** (`storage/app/private/arsip`) sebagai satu-satunya tempat penyimpanan primer; upload sync lewat AJAX | Google Drive hanya **backup terjadwal** (L-01) — akses file tetap lewat controller `middleware('auth')`, tidak ada link publik |
| Queue | **tidak ada** — `QUEUE_CONNECTION=sync`, 2 Job sudah dihapus, tabel `jobs`/`failed_jobs` ikut hilang saat squash S11 | jangan menambah Job tanpa worker/hardening cron (L-02) |
| PDF | `barryvdh/laravel-dompdf ^3.1` | tetap DomPDF (F2=a), **multi-template** (F3=b) menyesuaikan punya desa |
| Target deploy | belum pernah deploy | **shared hosting PHP** (K1=a) + `APP_ENV=production`, `APP_DEBUG=false` (S12) |

## 3. Status pengerjaan

Sudah ada & jalan: migration + model (**14 tabel** dalam **13 file** hasil squash S11: 10 skema awal + `pengajuan_hapus_lampiran` + `surat_counters` + `pemusnahan_arsip` + `pemusnahan_arsip_item`), Auth login/logout (email+PIN, rate limit), CRUD surat masuk/keluar, CRUD klasifikasi 3 level, Pengaturan Instansi (edit-only), Lampiran (upload/unduh), Pengajuan hapus lampiran, **Pemusnahan arsip + Berita Acara**, Dashboard + view, Pencarian, Cetak PDF + halaman draf, **Ekspor laporan CSV + Buku Agenda PDF**, **halaman error 403/404/419/500/503**, logging via 11 Observer, middleware `admin`.

Langkah 1–11 Bagian 7 **selesai & terverifikasi** (4 Okt 2026):

1. Laravel **12.69.3** (PHP 8.2), Sanctum/`routes/api.php`/welcome dihapus, `optimize-autoloader`.
2. Lampiran ke **disk lokal `arsip`** (L-01) + upload sync AJAX multi-file (L-02/L-13/L-24) dengan duplikat-sha256 dilewati; queue + 2 Job dihapus; log sync; `arsip:sinkron-ke-drive` (backup harian, `--dry-run`) & `arsip:daftar-usang`.
3. Halaman **draf konten surat keluar** (`surat-keluar/{id}/draf`) — cetak PDF jadi bisa dipakai (L-16).
4. Penomoran surat keluar jadi **`NomorSuratKeluarGenerator`** + tabel `surat_counters` + `lockForUpdate()` di dalam transaksi; dibuktikan dengan **dua koneksi MariaDB nyata** (bukan cuma docblock) + retry 3× di `store()` (L-20).
5. **Soft delete surat** (`deleted_at` di kedua tabel) + tombol **"Nyahkan"** (ubah `status_arsip`) + **tempat sampah admin** (`?sampah=1`, `?usang=1`, `Pulihkan`) + **modul Pemusnahan Arsip** (pengajuan → approval admin → `forceDelete` baris & file + **Berita Acara** PDF bernomor `BA-###/romawi/tahun`). Parsial `partials/arsip-aksi.blade.php` dipakai kedua halaman show (menghilangkan duplikasi H6). Sidebar kini menyaring link admin (`isAdmin()`).

6. **Role 2 tingkat + hak nyata admin** (L-07): migration pemetaan enum (`kepala`→`admin`, `perangkat`→`pegawai`, dijalankan & diverifikasi di MariaDB dev — urutannya harus "perluas enum → ubah data → persempit enum"; percobaan pertama gagal karena `pegawai` belum ada di enum lama), satu sumber label role (`User::peranTersedia()`/`labelRole()`) dipakai form user, daftar user, dan topbar; `StoreUserRequest` cuma menerima 2 nilai. Kebijakan otorisasi **sengaja tidak** dipindah ke Policy per-model: dengan 2 tingkat semuanya cuma mengulang `isAdmin()`. `app/Policies/LampiranPolicy.php` dihapus (scaffold kosong + aturan kepemilikan yang justru melarang staf membuka arsip kantor, menabrak L-09/L-10) beserta `authorize('view')` di `LampiranController::download()`. L-20 ikut ditutup: nomor surat keluar tidak bisa diubah non-admin (divalidasi di server, input `readonly` di form). H3: kartu "klasifikasi terbanyak" dashboard kini menghitung surat masuk **dan** keluar (dulu hanya masuk), cache 60 detik dashboard dihapus supaya angka tidak tertinggal sesudah input surat.

7. **PIN & sesi dirampungkan** (L-11/L-12): PIN **8 digit** di 4 jalur (login, buat user, reset admin, ganti sendiri) — `LoginRequest` tidak lagi menerima `remember`, kolom `remember_token` tidak pernah ditulis lagi; form popup "Ganti PIN" di menu Akun (ganti PIN sendiri, wajib PIN lama + `different`), form inline "Reset PIN" di daftar user (admin), dua perintah terminal untuk serah terima: `arsip:akun-pertama` (satu-satunya jalan membuat admin setelah seeder dilucuti) dan `arsip:reset-pin` (kalau semua admin lupa PIN — tidak ada SMTP kantor). I3: `DatabaseSeeder` produksi kini **hanya** baris `pengaturan_instansi`; akun contoh + klasifikasi contoh pindah ke `DevSeeder` yang menolak jalan di luar `APP_ENV=local` dan membaca PIN dari `DEV_PIN` di `.env`. `.env.example` ditulis ulang jadi versi PRADANA (buang boilerplate Pusher/Mail/Redis/Vite yang tidak dipakai, tambah `DB_TEST_DATABASE`, `DEV_PIN`, `GOOGLE_DRIVE_*`).

8. **Pencarian, parsial, dan log** (L-15/L-22/P3/P4/H6/H7/E6): `PencarianController` ditulis ulang — `ringkasan` surat masuk dan `isi_surat` draf surat keluar ikut digali (L-15), filter disamakan dengan daftar surat (klasifikasi/sifat/status arsip/dari-sampai + jenis), dan hasilnya **di-pagination 20** lewat UNION `fromSub` + `LengthAwarePaginator` (sebelumnya `limit(50)` per tabel yang memotong diam-diam dan tidak bisa memberi halaman 2 yang benar). Surat yang sudah dihapus lunak tidak muncul (kerangka UNION memakai scope soft delete). Halaman **`/aktivitas`** (admin-only, read-only) + link sidebar; perintah `arsip:bersihkan-log [--tahun=2] [--dry-run]` dijadwalkan bulanan — catatan pemusnahan (subjek `PemusnahanArsip`/Item ATAU teks aksinya mengandung "musnahkan"/"pemusnahan"/"berita acara", dicek case-insensitive) tidak pernah dibuang karena Berita Acara butuh jejak itu (E6/L-22). H6: dua salinan `_klasifikasi_cascade_js.blade.php` (surat-masuk & surat-keluar, isinya sudah melenceng) digabung jadi `partials/klasifikasi-cascade.blade.php` dan dipakai 4 form.

9. **Ekspor laporan, Buku Agenda, dan antrian approval** (L-17): `LaporanController` + halaman `/laporan` — satu filter (periode, jenis, klasifikasi primer, status arsip) dipakai tiga keluaran: halaman itu sendiri (dengan caption "N surat cocok" + peringatan eksplisit kalau 0, supaya rekap kosong tidak dibaca sebagai aplikasi rusak), **`/laporan/rekap` = CSV** (`;` + BOM UTF-8, dibuka di Excel/LibreOffice kantor; bukan `.xlsx` karena `maatwebsite/excel` butuh extension yang belum tentu ada di shared hosting K1=a), dan **`/laporan/agenda` = PDF Buku Agenda A4 landscape** (dompdf, kop instansi, kolom sesuai format agenda manual kantor, blok tanda tangan Kepala Instansi + Petugas Arsip). Kedua keluaran dibaca dari **satu method yang sama** (`baris()`) supaya tidak bisa berbeda kesimpulan. Surat di tempat sampah otomatis terluar (scope soft delete); periode terbalik dibalik, bukan menghasilkan dokumen kosong. H1=b "notifikasi" diwujudkan **badge jumlah pengajuan `menunggu` di sidebar admin** (2 sumber: hapus lampiran + pemusnahan) lewat `View::composer('layouts.app')` — kantor tanpa SMTP/WA, jadi tidak ada jalur kirim; angka 0 meniadakan query untuk non-admin.

10. **Halaman error, README kantor, dan manual pemakaian** (K16, X2=a, L-25): `resources/views/errors/{403,404,419,500,503}.blade.php` + `errors/layout.blade.php`. Layout error **berdiri sendiri** (sengaja TIDAK `@extends('layouts.app')`) karena layout utama memanggil `auth()->user()->isAdmin()` + query pengajuan — exception kedua di atas halaman error (mis. DB mati = penyebab 500-nya) bikin user malah lihat layar kosong; karena itu juga tidak ada tombol "Keluar" (form POST butuh token CSRF yang justru sering kedaluwarsa di layar ini). Bahasanya untuk orang kantor, bukan pesan default Laravel: 403 menjelaskan siapa admin, 404 mengarahkan ke Pencarian Arsip, **419** ("halaman kedaluwarsa", kasus nyata: formulir dibiarkan >2 jam / laptop tidur — dijabarkan cara menghindari kehilangan isian panjang), 500 (jangan ulang-ulang, catat jam & nomor surat, jangan utak-atik folder arsip), 503 (mode `artisan down`). Tidak ada variabel `$exception` yang dicetak (nama tabel/path server tidak boleh terbaca). `README.md` ditulis ulang dari boilerplate Laravel jadi halaman untuk orang kantor; manual pemakaian lengkap ada di **`docs/manual-pemakaian.md`** (16 bagian + Lampiran A petugas: `upload_max_filesize`/`post_max_size` ≥ 26M, cron `schedule:run`, uji pulih backup sebelum serah terima, daftar batasan yang diketahui). Sekalian diperbaiki: bullet halaman login masih menjanjikan "Lampiran tersimpan aman di Google Drive" padahal L-01 sudah pindah ke disk lokal.

11. **Squash migration** (S11): 20 file → **13 file** `2026_10_04_100001..100013`, dijalankan `migrate:fresh --seed` di MariaDB dev dan dibangun ulang di kedua koneksi tes. Kolom mati dibuang; `jobs`/`failed_jobs`/`personal_access_tokens` hilang. Diverifikasi: 95 tes lolos, lalu lewat browser nyata — login akun hasil pemulihan, 11 halaman 200, buat surat masuk (`UJI-001` dua kali, terbukti boleh duplikat sesuai D9) dan surat keluar (`001/01/X/2026`, `002/01/X/2026` + baris `surat_counters`), 4 baris log aktivitas tertulis. Artefak tes sudah dibersihkan (0 surat, 0 log, 3 user, 2 klasifikasi, nama instansi "PEMERINTAH DESA UREK-UREK" dipulihkan dari snapshot).

Alat quality (L-24 / Q2=a) dipasang 4 Okt 2026: **Pint** (preset `laravel`, 142 file lolos `--test` setelah 34 file dirapikan) dan **Larastan ^3.12 level 5** lewat `phpstan.neon` (path: `app`, `database`, `routes`). Perubahan nyata dari analisisnya: **27 method relasi di 10 model kini punya return type** (`BelongsTo`/`HasMany`/`MorphMany`/…) — sebelumnya tidak ada, akibatnya Larastan (dan IDE) buta terhadap `$surat->primer`, `$surat->lampiran`, dst; dan `PencarianController::kerangka()` memakai type `Illuminate\Database\Eloquent\Builder` yang benar (sebelumnya `Contracts\...\Builder`, yang tidak punya `orWhereHas`), sementara `DevSeeder` baca `DEV_PIN` lewat `getenv()` karena `env()` mengembalikan null begitu `config:cache` aktif. Sisanya 29 temuan masuk `phpstan-baseline.neon` — semuanya keterbatasan analisis statis atas relasi polymorphic + `catch (QueryException)` yang memang dilempar PDO runtime, sudah dibaca satu-satu, BUKAN dibungkam massal. Regenerasi baseline kalau file itu berubah: `vendor/bin/phpstan analyse --generate-baseline`.

**Perubahan UI 4 Okt sore (permintaan user, bukan roadmap Bagian 7):** (1) hal milik akun dikumpulkan di **menu "Akun"** (dropdown di topbar: nama + email + role + "Ganti PIN") dan **ganti PIN jadi popup, bukan halaman sendiri** — route GET `profil/pin`, method `editPin()`, dan view `profil/ganti-pin.blade.php` dihapus; `PATCH profil/pin` sekarang menjawab JSON kalau `expectsJson()` dan tetap redirect+flash kalau JavaScript mati (formnya `<form>` sungguhan, bukan div klik-tanpa-JS), dan flash-nya dipindah dari kunci `'status'` ke `'success'` karena layout hanya merender `success`/`error` — pesan sukses sebelumnya tidak pernah tampil. (2) **Pratinjau logo** di Pengaturan Instansi: begitu berkas dipilih, `URL.createObjectURL` menampilkan gambarnya + nama + ukuran sebelum disimpan. Sekalian: satu huruf `a` nyasar di `pengaturan-instansi/edit.blade.php` (di antara `--}}` dan `@section`) ikut ter-render di layar dan sudah dibuang.

Test suite: **99 passed** (`php artisan test`, 16 class) — +11 `PencarianDanLogTest`, +10 `LaporanAgendaTest`, +5 `HalamanErrorTest`, +3 `PengaturanInstansiTest` (halaman ini sebelumnya tidak punya tes sama sekali), +2 tes popup/AJAX ganti PIN. 3 tes penomoran (`NomorSuratKeluarTest`) jalan di **MariaDB sungguhan** pakai dua koneksi `mysql_test_a`/`mysql_test_b` dan mengunci baris betulan; sisanya SQLite in-memory.
Diverifikasi lewat browser nyata di `http://127.0.0.1:8000`: alur pemusnahan penuh (ajukan → setujui → `surat_masuk` hilang dari DB, Berita Acara `BA-001/X/2026` ter-render 200 `application/pdf`, log urut), DOM halaman user/dashboard/form edit surat keluar (opsi role tinggal dua, nomor tetap bisa diedit admin), DOM popup Ganti PIN di menu Akun (dibuka lewat klik sungguhan, 3 field PIN, PIN lama salah → 422 + pesan per kolom "PIN lama tidak cocok." tanpa popup tertutup, popup ditutup → field kosong lagi), pratinjau logo sungguhan (berkas dipilih → `blob:` tampil + nama + ukuran, tanpa menyimpan dulu), `/users` (3 form reset PIN inline), halaman login (checkbox "Ingat saya" hilang, `pattern=\d{8}`), `arsip:reset-pin`/`arsip:akun-pertama` dijalankan sungguhan di terminal, lalu `/laporan` (caption jumlah + peringatan periode kosong berbahasa Indonesia "01 Mei 2026"), unduhan CSV nyata (BOM `EF BB BF`, header 12 kolom `;`, baris surat masuk & keluar), dan `/laporan/agenda` 200 `application/pdf` `%PDF-1.7`. Halaman error juga diambil betulan lewat browser: `/AlamatTidakAda123` → 404 dengan kode "404" di layar, tanpa sidebar, dan tanpa halaman debugger; tes mengesahkan 403 dari middleware admin + 503 dari `artisan down` benar-benar lewat view baru (file `storage/framework/down` dipastikan bersih lagi sesudah tes).

Catatan untuk user: karena PIN sekarang 8 digit, **PIN akun dev `aliwafa3575@gmail.com` sudah diganti menjadi `12345678`** — PIN lama yang 6 digit tidak bisa dipakai login lagi.

Bug nyata yang ditemukan tes (bukan dokumen) dan sudah diperbaiki:
- `$model->forceDeleting` di Observer melempar `BadMethodCallException` (propertinya protected; magic getter malah mencocokkan namanya dengan method statis `forceDeleting($callback)` yang butuh 1 argumen) → **hapus surat masuk/keluar selalu 500**, bahkan sebelum modul pemusnahan ada. Ganti `isForceDeleting()`.
- `PemusnahanArsipItem::pemusnahan()` menebak FK `pemusnahan_id`, kolom sebenarnya `pemusnahan_arsip_id`.
- `LampiranPolicy::view()` memberi 403 ke staf yang membuka lampiran bukan miliknya — tidak pernah kelihatan karena semua uji manual sebelumnya dijalankan sebagai admin.
- **`pengaturan_instansi` tidak pernah punya kolom cache folder Drive** (`gdrive_folder_surat_masuk_id`/`_keluar_id`) padahal `SinkronkanLampiranKeDriveCommand` menulisnya lalu `save()` → backup pertama ke Drive akan langsung gagal dengan `Unknown column`. Terbongkar saat squash membandingkan migration dengan DB sungguhan; kolomnya sekarang dibuat (root folder tetap dari `.env`).
- `2026_09_04_174904_drop_unused_columns...` bermaksud membuang kolom mati `draf_konten_surat_keluar.lampiran` tapi menargetkan `surat_masuk`/`surat_keluar` — kolomnya tetap ada 1 bulan. Tidak ada yang membacanya sejak P6, jadi hilang di squash tidak mengubah perilaku.
- `database/factories/UserFactory.php` masih murni boilerplate Laravel (`name`, `password`, `email_verified_at`, `remember_token`) padahal modelnya pakai `nama_lengkap`/`pin` → `User::factory()->create()` pasti gagal. Ditulis ulang supaya benar-benar bisa dipakai.

Yang belum ada / masih mati: deploy sungguhan ke hosting kantor + uji pulih backup (butuh server dulu; K1/S14 belum diputuskan user).

Dua item **tidak bisa diselesaikan agent** dan butuh bahan/angka dari user — jangan ditebak:
- **F3 multi-template PDF** (jawaban user: "nanti multi template, menyesuaikan dengan punya desa"): butuh kop/template resmi desa aslinya. Sekarang satu template umum di `resources/views/surat-keluar/cetak.blade.php`.
- **B10/L-19 auto-lock arsip** (`terkunci_pada` setelah N hari): kolomnya belum ada dan **nilai N belum diputuskan user**.

## 4. Keputusan user [LOCKED]

| Kode | Keputusan |
|---|---|
| L-01 | **File arsip disimpan di disk lokal** (`storage/app/private`, di luar `public/`); Google Drive jadi **backup/sinkronisasi terjadwal**, bukan sistem pencatatan. Akses tetap lewat controller `middleware('auth')` — JANGAN link publik (locked lama #9, dipertahankan) |
| L-02 | Upload lampiran & pencatatan log aktivitas **sync/tanpa worker** (alasan user: tidak mau bergantung pada worker). Upload pakai **AJAX + floating loading bar** ala Google Drive di pojok layar + peringatan "jangan tutup halaman" (permintaan eksplisit user di S3) |
| L-03 | **Tidak ada migrasi data lama** (C4, L1, L2 = tidak/tidak ada). Kemungkinan nanti ada input manual surat lama — sistem harus tetap bisa menerima surat dengan `created_at` baru |
| L-04 | Retensi tetap **5 tahun seragam** semua jenis, tapi **sistem hanya memberi DAFTAR surat berumur >5 tahun**; keputusan men-*inaktif*-kan tetap di tangan user (E5, diturunkan dari locked lama #12) |
| L-05 | **Hapus arsip permanen: tidak boleh** (B2=d). Penghapusan jadi **soft delete** (B9=a) — arsip & lampirannya tetap utuh, bisa dipulihkan admin |
| L-06 | **Modul pemusnahan arsip (surat) + Berita Acara dibuat** (E3=a), pola pengajuan→approval seperti lampiran. File fisik baru hilang pada jalur pemusnahan ini (bukan lewat `destroy()`) |
| L-07 | Role dipangkas jadi **2**: `admin` = **kepala** (diberi hak nyata) dan `pegawai`. Jawaban eksplisit user: "admin adalah kepala dan diberikan hak nyata". **Selesai 4 Okt 2026**: migration `2026_10_04_000003_pangkas_role_menjadi_dua_tingkat` memetakan `kepala`→`admin`, `perangkat`→`pegawai` lalu mempersempit enum; label role ada satu sumber (`User::peranTersedia()`/`labelRole()`) dipakai form user, daftar user, dan topbar |
| L-08 | Surat boleh **diubah siapa pun yang login** (B5=c "semua boleh, tapi tercatat di log aktivitas") — `update()` memang cuma `middleware('auth')`, dan observer sudah mencatatnya, jadi tidak ada perubahan kode diperlukan. **Koreksi 4 Okt 2026**: baris ini dulu tertulis salah ("klasifikasi boleh diedit semua yang login") — yang ditanyakan di B5 itu surat, bukan klasifikasi. B3 (klasifikasi hanya admin) di register user jawabannya **"Lanjut"**, jadi mutasi klasifikasi tetap admin-only seperti sekarang |
| L-09 | Surat `rahasia`: akses **sama untuk semua yang login** (B6=a) — tidak ada pembatasan khusus |
| L-10 | User boleh dihapus walau punya surat (B8=a, "tidak ada sistem kepemilikan, semua milik kantor") — log aktivitas jadi penjaga |
| L-11 | Tidak ada 2FA (A6=a), **remember-me dihapus** dari form login (H4=a), login tetap email+PIN (A1=a). **Selesai 4 Okt 2026** — kolom `remember_token` masih ada di skema tapi tidak pernah ditulis; dibersihkan sekalian saat squash S11 |
| L-12 | Alur lupa PIN dibuat lengkap: admin reset PIN **dan** user bisa ganti PIN sendiri (A4/A5=a). **Selesai 4 Okt 2026**: PIN **8 digit** di semua jalur (login, buat user, reset, ganti sendiri); admin → `PATCH users/{user}/pin` + form inline di daftar user; staf → popup "Ganti PIN" di menu Akun (wajib PIN lama; dulu halaman `profil/pin`, jadi popup atas permintaan user 4 Okt 2026); kalau semua admin terkunci: `php artisan arsip:reset-pin email`. Tidak ada reset lewat email (kantor tanpa SMTP) |
| L-13 | Tipe file lampiran diperluas: `pdf, jpg, jpeg, png` **+ `doc, docx, xls, xlsx`** (C1=a). Batas yang berlaku di kode (`StoreLampiranRequest`): **maks 25MB per berkas, 10 berkas sekali unggah** — perlu `upload_max_filesize`/`post_max_size` ≥ 26M di `php.ini` server, kalau tidak PHP menolak sebelum Laravel sempat memberi pesan ramah (dicatat di manual Lampiran A.1) |
| L-14 | Filter daftar surat **disamakan** untuk masuk & keluar: cari, tahun, sifat, status arsip, klasifikasi (H1=a). Pagination seragam 20/halaman (H2) |
| L-15 | Pencarian ikut menjangkau **isi** (`ringkasan`, `isi_surat`) (P4=b) |
| L-16 | Ada **halaman khusus untuk mengisi draf/generate PDF** surat keluar (form header + isi) (P1, jawaban user) |
| L-17 | Notifikasi: hanya untuk "pengajuan hapus menunggu approval admin" (H1=b). Export laporan per periode (I1=b) dan cetak **Buku Agenda Surat** (I2=b) **dibuat**. **Selesai 4 Okt 2026**: `/laporan` (CSV `;`+BOM + PDF agenda A4 landscape). ⚠️ H1=b **diinterpretasikan** sebagai badge jumlah antrian di sidebar admin (kantor tanpa SMTP/WA, jadi tidak ada jalur kirim sama sekali) — belum dikonfirmasi eksplisit ke user, sampaikan saat serah terima |
| L-18 | Tombol cetak: **stream + download dua-duanya** (F5=c). Notasi "Lampiran" di PDF **otomatis** dari jumlah file (P6=a) |
| L-19 | `status_arsip` bisa diubah lewat **tombol "Nyahkan" di halaman show** (E8+E9), dan dikunci otomatis setelah N hari (B10=c) — nilai N diputuskan nanti |
| L-20 | Koreksi nomor surat manual: **hanya admin** (D7=b) — ditegakkan di `UpdateSuratKeluarRequest` (nomor harus sama dengan yang tersimpan kalau yang mengubah bukan admin) + input `readonly` di form edit. Gap nomor saat surat hilang **dibiarkan** (D8=a). `nomor_surat` surat masuk **boleh duplikat** (D9=a) |
| L-21 | Acuan umur arsip **diseragamkan ke `tanggal_surat`** untuk kedua jenis surat (E7=c) |
| L-22 | Log aktivitas: sync + **ada halaman UI untuk admin** (S4=a, P3=a). Retensi log: hapus >2 tahun **kecuali** log pengajuan/pemusnahan (E6=b) |
| L-23 | UI riwayat pengajuan hapus: filter status, default **tampilkan semua** (P2=a) |
| L-24 | Upload **multi-file sekaligus** (P5=a). Testing: tambah tes concurrency nomor surat, penolakan non-admin di route admin, alur >5 tahun — **dijalankan di MariaDB** (Q1=b). Pasang **Larastan + Pint** (Q2=a) — **selesai 4 Okt 2026**, lihat paragraf "Alat quality" di Bagian 3 |
| L-25 | Aplikasi **dipakai sungguhan oleh kantor** (W1=d). Setelah user berhenti: user kantor mengoperasikan, user tetap bertanggung jawab (X4) → **manual pemakaian + pelatihan diperlukan** (X3=a) |
| L-26 | `AGENTS.md` ditulis ulang ringkas (X1=a) dan README ditujukan untuk orang kantor, sisa boilerplate dihapus (X2=a) |

**Catatan L-07:** `admin` = kepala desa/lurah dan punya hak nyata: hapus/pulihkan arsip, setujui pemusnahan & hapus lampiran, kelola user, mutasi klasifikasi (B3 "Lanjut"), pengaturan instansi, dan koreksi nomor surat keluar (L-20). `pegawai` = staf: input & ubah surat, unggah/unduh lampiran, nyahkan/aktifkan arsip, dan mengajukan penghapusan/pemusnahan. Kalau kantor nanti ingin membedakan "kepala" dari "perangkat" lagi, itu keputusan baru — jangan diam-diam mengembalikan enum 3 nilai.

## 5. Keputusan yang saya ambilkan ([DEFAULT-agent], user bilang "atur yang terbaik")

- **S7 Backup:** hosting shared pilih paket dengan backup harian; ditambah `mysqldump` mingguan + salinan folder arsip ke Drive, rotasi 30 hari. **X5:** tes restore wajib sebelum serah terima.
- **S9 Dev env:** tetap XAMPP, tapi MariaDB + Apache didaftarkan sebagai service Windows supaya tidak tergantung control panel.
- **S10 Session/cache:** **DIBATALKAN 4 Okt 2026 (saat squash)** — tetap `file`. Alasan: aplikasi ini satu server satu kantor, `SESSION_DRIVER=file` + `CACHE_DRIVER=file` sudah mengunci atomik (lock file pakai `flock`), dan memindah ke `database` cuma menambah round-trip DB tiap request + mengunci login saat DB bermasalah. Konsekuensinya tabel `sessions`/`cache` **tidak dibuat**. Kalau nanti benar-benar perlu "keluar paksa semua sesi user", itu dibuat sebagai fitur eksplisit (tambah kolom/version di `users` + cek di middleware), bukan sebagai efek samping pindah driver.
- **S11 Migration:** **SELESAI 4 Okt 2026** — 20 file migration (termasuk semua ALTER dan tabel peninggalan queue/Sanctum) disquash jadi **13 file** `2026_10_04_100001..100013`. Kolom mati dibuang (`remember_token`, `email_verified_at`, `pengaturan_instansi.gdrive_root_folder_id`, `surat_*.file_path`, `draf_konten_surat_keluar.lampiran`), tabel `jobs`/`failed_jobs`/`personal_access_tokens` ikut hilang. **FULLTEXT sengaja TIDAK dibuat** (lihat Bagian 3 langkah 8: LIKE identik di SQLite & MariaDB, dataset masih ribuan baris) — kalau pencarian nanti terbukti lambat, itu satu migration baru, bukan alasan untuk menahan squash. ⚠️ Habis squash, **setiap database yang sudah ada harus dibangun ulang** (`migrate:fresh` untuk `pradana`, plus `--database=mysql_test_a` dan `mysql_test_b`) karena nama file migration lama tidak dikenal lagi.
- **S8 Dependency:** pin `google/apiclient:^2.19`; hapus `laravel/sanctum`, `routes/api.php`, `welcome.blade.php`.
- **S15 Kolom mati:** hapus `users.email_verified_at` dan `pengaturan_instansi.gdrive_root_folder_id` (root folder jadi konfigurasi `.env`).
- **I1/I2:** tambah `hash_file` (sha256) untuk peringatan duplikat; setiap lampiran punya file fisik sendiri (tidak dibagikan antar surat).
- **I3 Seeder:** `DatabaseSeeder` produksi hanya baris `pengaturan_instansi` kosong; akun tes + klasifikasi contoh dipindah ke `DevSeeder` yang berjalan hanya saat `APP_ENV=local`. (Menjawab A10: kredensial keras `280306` + email pribadi dihapus dari seeder produksi, PIN dev di reading dari `.env`.)
- **H3/H5/H6/H7/S11b:** dashboard "klasifikasi terpopuler" jadi gabungan masuk+keluar; urutan baku tetap (`tanggal_diterima`/`tanggal_surat` + `id` desc); partial cascade JS digabung jadi 1 dengan parameter (saat ini dua salinan yang isinya sudah melenceng); hasil pencarian di-paginate 20 dengan caption; `CleanupRecordsCommand` diganti jadi perintah "daftar surat >5 tahun" + `withoutOverlapping()` + `--dry-run`.
- **K16:** buat halaman error 403/404/500 khusus berbahasa Indonesia.
- **S14 Domain/HTTPS:** user belum memutuskan (menunggu keputusan desa). Default sementara: HTTPS wajib begitu ada akses luar kantor; tanpa domain dulu (IP/VPN) untuk uji internal.

## 6. Setup & jalankan

```bash
# prasyarat: XAMPP MariaDB sebagai service, DB `pradana` sudah dibuat
composer install
cp .env.example .env && php artisan key:generate   # lalu isi DB_*, GOOGLE_*, DEV_PIN
php artisan migrate --seed                         # seeder produksi: cuma baris pengaturan instansi
php artisan arsip:akun-pertama email@kantor.desa --nama="Kepala Desa"   # akun admin pertama
php artisan serve            # http://127.0.0.1:8000
php artisan test             # 95 tes (SQLite in-memory + 3 tes MariaDB, butuh DB_TEST_DATABASE)
vendor/bin/pint --test       # gaya kode (preset laravel) — hapus --test untuk memperbaiki
vendor/bin/phpstan analyse   # Larastan level 5; temuan lama ada di phpstan-baseline.neon

# data contoh untuk pengembangan (TOLAK jalan kalau APP_ENV bukan local):
php artisan db:seed --class=DevSeeder
```
Perintah lain yang perlu diketahui saat serah terima: `arsip:reset-pin {email}` (kalau semua admin lupa PIN — tidak ada reset lewat email), `arsip:sinkron-ke-drive [--dry-run]` (backup), `arsip:daftar-usang [--tahun=5]` (daftar arsip lewat retensi).
Service Account JSON: `storage/app/google/service-account.json` (jangan pernah di-commit; sudah tercakup `.gitignore`).
Dokumen untuk kantor: **`docs/manual-pemakaian.md`** (manual pemakaian + Lampiran A petugas — ikut ter-commit, dicetak saat serah terima), `README.md` (halaman depan untuk orang kantor). Dokumen uji manual: `Obsidian Vault/Pradana/Manual_Testing_PRADANA.md`. Register keputusan: `.../Daftar Keputusan PRADANA.md`.

## 7. Urutan kerja (disetujui user lewat Q3 + P1)

1. ✅ **Upgrade Laravel 10 → 12** + hapus Sanctum/boilerplate, pin dependency, `composer optimize-autoloader`.
2. ✅ **Storage lokal + upload sync** (L-01/L-02) + AJAX loading bar; hapus 2 Job & matikan queue.
3. ✅ **CRUD draf konten / halaman generate PDF** (L-16) — membuka fitur PDF yang sekarang mati.
4. ✅ **Fix `generateNomorSurat()`** (race: docblock menyebut `lockForUpdate()`+retry padahal keduanya tidak ada) + tes MariaDB-nya.
5. ✅ **Soft delete + tombol "Nyahkan" + modul pemusnahan + Berita Acara** (L-05/L-06/L-19).
6. ✅ **Role 2 tingkat + hak nyata admin + migration pemetaan enum** (L-07). Otorisasi sengaja tetap `isAdmin()` + middleware `admin` di route (BUKAN Policy per-model — lihat alasan di Bagian 3 langkah 6); `@can` tidak dipakai karena hanya ada satu cek.
7. ✅ **PIN 8 digit, reset PIN admin, ganti PIN sendiri, hapus remember-me** (L-11, L-12) + **I3 DevSeeder**.
8. **UI log aktivitas + retensi log**, perluasan pencarian (isi + FULLTEXT, pagination) (L-15, L-22). ✅ *(FULLTEXT sengaja ditunda ke S11 — lihat alasan LIKE di Bagian 3 langkah 8)*
9. **Ekspor laporan + Buku Agenda + notifikasi approval** (L-17). ✅ *(notifikasi = badge antrian di sidebar, bukan email/WA — kantor tanpa SMTP; lihat Bagian 3 langkah 9)*
10. **Halaman error, README kantor, manual pemakaian** (K16, L-26, L-25). ✅
11. **Squash migration + kolom mati** (S11). ✅ 4 Okt 2026 — **kecuali** dua hal yang memang tidak ikut dikerjakan: index FULLTEXT (sengaja, alasannya di Bagian 5 S11) dan kolom `terkunci_pada` (menunggu nilai N dari user, lihat L-19).
