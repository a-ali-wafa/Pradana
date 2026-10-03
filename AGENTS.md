# AGENTS.md — PRADANA (konteks teknis untuk AI agent)

> Untuk agent, bukan stakeholder. Riwayat detail (Bagian 12/13 lama) ada di `AGENTS_HISTORY.md` — buka on-demand kalau ada rujukan spesifik.
> Diperbarui: **3 Okt 2026** — ditulis ulang dari hasil review kode + register keputusan user (`Obsidian Vault/Pradana/Daftar Keputusan PRADANA.md`, diisi 3 Okt 2026).

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
| Framework | Laravel **10.50.2** — EOL keamanan sejak 4 Feb 2025 | **Upgrade ke Laravel 12** (S1=a), dikerjakan **sebelum** fitur baru (Q3=a). Laravel 12 dukung PHP 8.2, jadi tidak perlu ganti PHP |
| PHP | 8.2.12 (`C:\xampp\php`) | tetap 8.2 |
| Database | **MariaDB 10.4.32** (bawaan XAMPP), DB `pradana` | tetap MariaDB (S2=a). Dokumen lama menyebut "MySQL" = nama driver PDO saja. `restrictOnDelete()` di MariaDB berperilaku `NO ACTION` — jangan andalkan `RESTRICT` asli |
| Auth | PIN-based: kolom `users.pin` ter-hash bcrypt, `User::getAuthPassword()` di-override, login **email + PIN** + rate limit 5/menit | tetap (A1=a), PIN jadi **8 digit** (A11=b) |
| Frontend | Blade + Bootstrap 5, desktop-first, tanpa dark mode, tanpa i18n | tetap (X1/G1/G2/M2/V2=a) |
| Penyimpanan file | Google Drive via Service Account, upload via **queue** | **Lokal** (`storage/app/private`) sebagai primer + **Drive sebagai backup terjadwal** (S5=b). Upload & log jadi **sync** (S3=a, S4=a) |
| Queue | `QUEUE_CONNECTION=database`, worker tidak pernah jalan otomatis | **dihapus/dinonaktifkan** — tidak ada `queue:work` di shared hosting |
| PDF | `barryvdh/laravel-dompdf ^3.1` | tetap DomPDF (F2=a), **multi-template** (F3=b) menyesuaikan punya desa |
| Target deploy | belum pernah deploy | **shared hosting PHP** (K1=a) + `APP_ENV=production`, `APP_DEBUG=false` (S12) |

## 3. Status pengerjaan

Sudah ada & jalan: migration + model 11 tabel, Auth login/logout (email+PIN, rate limit), CRUD surat masuk/keluar, CRUD klasifikasi 3 level, Pengaturan Instansi (edit-only), Lampiran (upload/unduh), Pengajuan hapus lampiran, Dashboard + view, Pencarian, Cetak PDF, logging via 9 Observer, middleware `admin`, 40 view Blade.
**Selesai 4 Okt 2026 (branch `upgrade/laravel-12`):** framework upgrade ke Laravel 12.69.3; lampiran pindah ke **disk lokal** (L-01) dengan upload **sync** + AJAX floating progress bar (L-02, diverifikasi lewat browser: tulis file, lewati duplikat, unduh inline & attachment); queue + 2 Job dihapus; log aktivitas kini **sync** dan terbukti menulis (`Mengunggah lampiran: ...`); daftar pengajuan hapus diberi filter status + pagination (L-23); tipe file diperluas ke Word/Excel + batas 25MB (L-13); `arsip:sinkron-ke-drive` (backup harian, `--dry-run`) dan `arsip:daftar-usang` menggantikan `CleanupRecordsCommand`. Tes: **12 passed**.
Test suite: 12 tes lolos (`php artisan test`, SQLite in-memory) — cakupan masih sempit, **tes yang jalan di MariaDB belum dibuat** (L-24/Q1).
Yang belum ada / masih mati: **CRUD `draf_konten_surat_keluar`** (→ fitur cetak PDF selalu menolak, TC-85), halaman error 403/404/500, UI log aktivitas, export laporan, buku agenda, modul pemusnahan arsip, reset/ganti PIN, tombol nyahkan untuk surat masuk, soft delete arsip, fix race `generateNomorSurat()`, role 2 tingkat.

## 4. Keputusan user [LOCKED]

| Kode | Keputusan |
|---|---|
| L-01 | **File arsip disimpan di disk lokal** (`storage/app/private`, di luar `public/`); Google Drive jadi **backup/sinkronisasi terjadwal**, bukan sistem pencatatan. Akses tetap lewat controller `middleware('auth')` — JANGAN link publik (locked lama #9, dipertahankan) |
| L-02 | Upload lampiran & pencatatan log aktivitas **sync/tanpa worker** (alasan user: tidak mau bergantung pada worker). Upload pakai **AJAX + floating loading bar** ala Google Drive di pojok layar + peringatan "jangan tutup halaman" (permintaan eksplisit user di S3) |
| L-03 | **Tidak ada migrasi data lama** (C4, L1, L2 = tidak/tidak ada). Kemungkinan nanti ada input manual surat lama — sistem harus tetap bisa menerima surat dengan `created_at` baru |
| L-04 | Retensi tetap **5 tahun seragam** semua jenis, tapi **sistem hanya memberi DAFTAR surat berumur >5 tahun**; keputusan men-*inaktif*-kan tetap di tangan user (E5, diturunkan dari locked lama #12) |
| L-05 | **Hapus arsip permanen: tidak boleh** (B2=d). Penghapusan jadi **soft delete** (B9=a) — arsip & lampirannya tetap utuh, bisa dipulihkan admin |
| L-06 | **Modul pemusnahan arsip (surat) + Berita Acara dibuat** (E3=a), pola pengajuan→approval seperti lampiran. File fisik baru hilang pada jalur pemusnahan ini (bukan lewat `destroy()`) |
| L-07 | Role dipangkas jadi **2**: `admin` = **kepala** (diberi hak nyata) dan `pegawai`. Jawaban eksplisit user: "admin adalah kepala dan diberikan hak nyata". Enum lama 3 nilai → migrasi pemetaan `kepala`→`admin`, `perangkat`→`pegawai` |
| L-08 | Klasifikasi boleh diedit semua yang login **tapi tercatat di log aktivitas** (B5=c) — catatan: ini memperluas B1, lihat catatan di bawah |
| L-09 | Surat `rahasia`: akses **sama untuk semua yang login** (B6=a) — tidak ada pembatasan khusus |
| L-10 | User boleh dihapus walau punya surat (B8=a, "tidak ada sistem kepemilikan, semua milik kantor") — log aktivitas jadi penjaga |
| L-11 | Tidak ada 2FA (A6=a), **remember-me dihapus** dari form login (H4=a), login tetap email+PIN (A1=a) |
| L-12 | Alur lupa PIN dibuat lengkap: admin reset PIN **dan** user bisa ganti PIN sendiri (A4/A5=a) |
| L-13 | Tipe file lampiran diperluas: `pdf, jpg, jpeg, png` **+ `doc, docx, xls, xlsx`** (C1=a) |
| L-14 | Filter daftar surat **disamakan** untuk masuk & keluar: cari, tahun, sifat, status arsip, klasifikasi (H1=a). Pagination seragam 20/halaman (H2) |
| L-15 | Pencarian ikut menjangkau **isi** (`ringkasan`, `isi_surat`) (P4=b) |
| L-16 | Ada **halaman khusus untuk mengisi draf/generate PDF** surat keluar (form header + isi) (P1, jawaban user) |
| L-17 | Notifikasi: hanya untuk "pengajuan hapus menunggu approval admin" (H1=b). Export laporan per periode (I1=b) dan cetak **Buku Agenda Surat** (I2=b) **dibuat** |
| L-18 | Tombol cetak: **stream + download dua-duanya** (F5=c). Notasi "Lampiran" di PDF **otomatis** dari jumlah file (P6=a) |
| L-19 | `status_arsip` bisa diubah lewat **tombol "Nyahkan" di halaman show** (E8+E9), dan dikunci otomatis setelah N hari (B10=c) — nilai N diputuskan nanti |
| L-20 | Koreksi nomor surat manual: **hanya admin** (D7=b). Gap nomor saat surat hilang **dibiarkan** (D8=a). `nomor_surat` surat masuk **boleh duplikat** (D9=a) |
| L-21 | Acuan umur arsip **diseragamkan ke `tanggal_surat`** untuk kedua jenis surat (E7=c) |
| L-22 | Log aktivitas: sync + **ada halaman UI untuk admin** (S4=a, P3=a). Retensi log: hapus >2 tahun **kecuali** log pengajuan/pemusnahan (E6=b) |
| L-23 | UI riwayat pengajuan hapus: filter status, default **tampilkan semua** (P2=a) |
| L-24 | Upload **multi-file sekaligus** (P5=a). Testing: tambah tes concurrency nomor surat, penolakan non-admin di route admin, alur >5 tahun — **dijalankan di MariaDB** (Q1=b). Pasang **Larastan + Pint** (Q2=a) |
| L-25 | Aplikasi **dipakai sungguhan oleh kantor** (W1=d). Setelah user berhenti: user kantor mengoperasikan, user tetap bertanggung jawab (X4) → **manual pemakaian + pelatihan diperlukan** (X3=a) |
| L-26 | `AGENTS.md` ditulis ulang ringkas (X1=a) dan README ditujukan untuk orang kantor, sisa boilerplate dihapus (X2=a) |

**Catatan L-07/L-08:** jawaban user untuk #16 (pangkas role + admin = kepala berhak nyata) dan B5 (klasifikasi boleh diedit semua yang login) saling menempel dengan B1 lama. Interpretasi yang dipakai: `admin` punya hak lebih (hapus user, koreksi nomor, approval, pengaturan); `pegawai` tetap boleh mutasi klasifikasi dengan pencatatan log. Kalau kantor menolak "pegawai boleh ubah master data", ini yang pertama harus direvisi.

## 5. Keputusan yang saya ambilkan ([DEFAULT-agent], user bilang "atur yang terbaik")

- **S7 Backup:** hosting shared pilih paket dengan backup harian; ditambah `mysqldump` mingguan + salinan folder arsip ke Drive, rotasi 30 hari. **X5:** tes restore wajib sebelum serah terima.
- **S9 Dev env:** tetap XAMPP, tapi MariaDB + Apache didaftarkan sebagai service Windows supaya tidak tergantung control panel.
- **S10 Session/cache:** pindah ke `database` setelah squash (lock & rate limiter jadi atomik).
- **S11 Migration:** squash jadi satu set bersih + buat tabel `sessions`/`cache` + index FULLTEXT + kolom baru (`deleted_at`, `hash_file`, `path`, `terkunci_pada`). Data test saat ini hilang → `migrate:fresh --seed`.
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
cp .env.example .env && php artisan key:generate   # lalu isi DB_*, GOOGLE_*
php artisan migrate --seed
php artisan serve            # http://127.0.0.1:8000
php artisan test             # suite lama 6 tes
```
Service Account JSON: `storage/app/google/service-account.json` (jangan pernah di-commit; sudah tercakup `.gitignore`).
Dokumen uji manual: `Obsidian Vault/Pradana/Manual_Testing_PRADANA.md`. Register keputusan: `.../Daftar Keputusan PRADANA.md`.

## 7. Urutan kerja (disetujui user lewat Q3 + P1)

1. **Upgrade Laravel 10 → 12** + hapus Sanctum/boilerplate, pin dependency, `composer optimize-autoloader`.
2. **Storage lokal + upload sync** (L-01/L-02) + AJAX loading bar; hapus 2 Job & matikan queue.
3. **CRUD draf konten / halaman generate PDF** (L-16) — membuka fitur PDF yang sekarang mati.
4. **Fix `generateNomorSurat()`** (race: docblock menyebut `lockForUpdate()`+retry padahal keduanya tidak ada) + tes MariaDB-nya.
5. **Soft delete + tombol "Nyahkan" + modul pemusnahan + Berita Acara** (L-05/L-06/L-19).
6. **Role 2 tingkat + hak nyata admin + migration pemetaan enum** (L-07), konsolidasi otorisasi ke policy/`@can`.
7. **PIN 8 digit, reset PIN admin, ganti PIN sendiri, hapus remember-me** (L-07, L-11, L-12).
8. **UI log aktivitas + retensi log**, perluasan pencarian (isi + FULLTEXT, pagination) (L-15, L-22).
9. **Ekspor laporan + Buku Agenda + notifikasi approval** (L-17).
10. **Halaman error, README kantor, manual pemakaian** (K16, L-26, L-25).
11. **Squash migration + kolom baru + index** (S11) — dikerjakan sekali, setelah skema fitur-final stabil.
