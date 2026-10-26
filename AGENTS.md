# AGENTS.md — PRADANA

Konteks singkat untuk yang melanjutkan proyek ini (agent atau programmer). Isinya hanya
gambaran besar dan aturan yang perlu diketahui sebelum menyentuh kode. Riwayat pengerjaan,
catatan insiden, dan analisis per tanggal sengaja tidak disimpan di repo ini.

## 1. Apa ini

Aplikasi arsip surat masuk dan surat keluar untuk kantor kelurahan/desa — pengganti
Google Sheets + Google Drive yang dipakai kantor sebelumnya. Fitur inti: input surat,
klasifikasi tiga tingkat, penomoran surat keluar otomatis, unggah lampiran, cetak PDF,
dashboard, pencarian, peminjaman/pemusnahan arsip dengan Berita Acara, dan log aktivitas.

Belum dipakai kantor sungguhan. Kodenya dikembangkan dan diuji di mesin pengembang lokal.

## 2. Stack

| Bagian | Yang dipakai |
|---|---|
| Framework | Laravel **12.69.3**, struktur bootstrap lama (`app/Http/Kernel.php`, provider di `config/app.php`) — bukan skeleton 11+ |
| Bahasa / runtime | PHP **8.2** (XAMPP di dev) |
| Database | **MariaDB 10.4** (`pradana`), SQLite in-memory untuk sebagian besar tes |
| Frontend | Blade + Bootstrap 5. **Tidak ada Node/Vite** dan tidak ada langkah build |
| Aset vendor | Bootstrap, Font Awesome, SweetAlert2 dilayani dari `public/vendor/` sendiri, bukan CDN |
| PDF | `barryvdh/laravel-dompdf`; pembaca isi lampiran `smalot/pdfparser` (murni PHP) |
| Queue | tidak ada — `QUEUE_CONNECTION=sync` |
| Auth | login email + **PIN 8 digit** (bcrypt), rate limit 5/menit, tanpa remember-me, tanpa reset lewat email |

## 3. Peta kode

```
routes/web.php          satu-satunya rute (halaman web); grup `web` + `auth`, admin lewat alias `admin`
app/Http/Controllers/   20 controller, satu per area (surat masuk/keluar, lampiran, klasifikasi,
                        instansi, laporan, aktivitas, pemusnahan, auth, user)
app/Models/             14 model; skema = 16 file migration (13 hasil squash + 3 additive)
resources/views/        46 view Blade
app/Support/            CariArsip, FilterArsip, RentangTanggal, RetensiArsip, TandaiPrivat, Terbilang
app/Services/           NomorSuratKeluarGenerator, PembacaIsiLampiran, DaftarArsipGabungan, GoogleDriveService
app/Observers/          7 observer + trait LogsAktivitas -> satu jalur penulisan log aktivitas
app/Console/            Commands/ (6 perintah) + Kernel.php (jadian `schedule:run`)
public/js/              pradana-arsip.js - satu-satunya JavaScript layout
public/vendor/          aset Bootstrap / Font Awesome / SweetAlert2 yang ikut di-commit
storage/app/private/    lampiran kantor (`arsip/`) dan dump database (`db-backup/`) - DI LUAR public/
```

Yang biasanya perlu diketahui dari peta itu:

- **Pencarian** memakai `LIKE` multi-kata (AND antar kata) lewat `CariArsip::terapkan()` dan
  `scopeCari()` di kedua model surat, dengan peringkat relevansi di `ORDER BY CASE`. Tidak ada
  index FULLTEXT dan tidak ada halaman pencarian sendiri: kotak cari ada di daftar surat,
  termasuk mode gabungan masuk + keluar (`?jenis=semua`, kerangka UNION di `DaftarArsipGabungan`).
- **Rentang tanggal** selalu lewat `RentangTanggal` (interval setengah terbuka `[awal, akhir+1)`),
  bukan `whereBetween`/`whereYear` — `date` di-cast sebagai `Y-m-d H:i:s` sehingga batas atas
  inkclusive membuang hari terakhir di SQLite, dan fungsi di atas kolom membuat index tidak terpakai.
- **Nomor surat keluar** dihasilkan `NomorSuratKeluarGenerator` dari tabel `surat_counters`
  dengan `upsert` + `lockForUpdate()` di dalam transaksi, dan retry di `store()`.
- **Isi lampiran** (PDF/DOCX/XLSX) dibaca otomatis saat unggah dan disimpan di kolom
  `surat_masuk.isi_hasil_baca`; teks hasil baca **tidak pernah** otomatis menjadi ringkasan —
  itu terjadi hanya kalau petugas mencentangnya.
- **Umur arsip** selalu dihitung dari `tanggal_surat` lewat `RetensiArsip::TAHUN` (satu sumber),
  bukan dari `tanggal_diterima`.
- **Header anti-index/keamanan** dipasang di middleware `TandaiArsipPrivat` **dan** di
  `App\Exceptions\Handler::render()` (dua-duanya memanggil `App\Support\TandaiPrivat`) karena
  respons exception tidak melewati pipeline middleware.
- **Lampiran** hanya bisa diambil lewat controller di belakang `auth` — tidak ada tautan publik,
  dan folder `private` tidak pernah tersedia dari URL karena docroot adalah `public/`.

## 4. Aturan yang berlaku di repo ini

1. Nama domain tetap bahasa Indonesia (`surat_masuk`, `klasifikasi_primer_id`, `arsip:*`). Sengaja.
2. Pesan ke pengguna, komentar, dan dokumentasi: bahasa Indonesia.
3. Flash message hanya pada kunci `success` dan `error` — `layouts/app.blade.php` tidak merender
   yang lain, jadi kunci lain berarti pesan hilang tanpa tanda apa pun.
4. Nilai konfigurasi dibaca lewat `config()`, tidak pernah `env()` langsung di dalam kode aplikasi:
   deploy memakai `config:cache` dan setelah itu `env()` mengembalikan `null`.
5. Docroot tetap `public/`. Jangan memindahkan `index.php` ke folder web untuk "mengakali" hosting.
6. Otorisasi dua tingkat (`admin`, `pegawai`) lewat `User::isAdmin()` + middleware `admin`.
   Sengaja tidak ada Policy per-model; `@can` tidak dipakai.
7. Nomor surat keluar tidak boleh diubah non-admin; gap nomor saat surat dihapus dibiarkan.
8. Lampiran: maksimum 25 MB per berkas dan 10 berkas per unggah. Server perlu
   `upload_max_filesize`/`post_max_size` >= 26M, kalau tidak PHP menolak sebelum Laravel bicara.
9. Tambah index hanya setelah diukur (`ANALYZE TABLE` + `EXPLAIN` di database tanding), dan
   temuan PHPStan tidak dibungkam ke baseline tanpa dibaca satu-satu.
10. Hal yang hanya benar di Apache (`.htaccess`, kompresi, header) tidak bisa disimpulkan dari
    `php artisan serve` — harus diuji dengan Apache sungguhan.
11. Perubahan yang menyentuh perilaku teks/kode (kunci flash, aset, guard HTML) dijaga tes yang
    memindai berkas sumber; guardrail itu harus diuji dengan menanam bug, bukan dipercaya hijau.

## 5. Menjalankan

```bash
composer install
cp .env.example .env && php artisan key:generate     # lalu isi DB_*, DEV_PIN, GOOGLE_*
php artisan migrate                                  # seeder produksi hanya baris pengaturan instansi
php artisan arsip:akun-pertama email@kantor.desa --nama="Kepala Desa"   # akun admin pertama
php artisan serve                                    # http://127.0.0.1:8000

php artisan db:seed --class=DevSeeder                # data contoh; menolak jalan kalau APP_ENV bukan local
```

Perintah yang dipakai saat serah terima: `arsip:reset-pin {email}`, `arsip:backup-db [--dry-run]`,
`arsip:sinkron-ke-drive [--dry-run]`, `arsip:daftar-usang`, `arsip:bersihkan-log`. Semuanya
dijadwalkan di `app/Console/Kernel.php` dan butuh `php artisan schedule:run` dari cron.

## 6. Gerbang verifikasi

```bash
php artisan test                 # SQLite in-memory; sebagian butuh MariaDB hidup (skip dengan pesan)
vendor/bin/pint --test           # gaya (preset laravel); hapus --test untuk memperbaiki
vendor/bin/phpstan analyse       # Larastan level 5; temuan lama ada di phpstan-baseline.neon
node bin/uji-pradana-arsip.js    # perilaku helper JS: fallback tanpa Swal, anti klik-dobel, tooltip
```

Tes engine-asli memakai database `mysql_test_a`/`mysql_test_b` (`DB_TEST_DATABASE`, default
`pradana_test`) dan berjalan lewat `tests/MariaDbHarness.php`. `bin/` berisi alat bantu
pengembang (subset font, pemangkas stub Google, skrip deploy/provisi) — bukan bagian dari
aplikasi saat dipakai.

## 7. Dokumentasi lain

- `README.md` — halaman depan untuk orang kantor.
- `docs/manual-pemakaian.md` — manual pemakaian + Lampiran A untuk petugas IT (persyaratan
  `php.ini`, cron, cadangan dan uji pulih).
- `docs/aset-vendor.md` — versi dan lisensi aset pihak ketiga yang ikut di repo.
