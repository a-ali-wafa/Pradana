# Deploy PRADANA ke server kantor

Ditulis 12 Okt 2026. Untuk petugas/IT yang memasang, bukan untuk pemakai akhir.
Manual pemakaian kantor ada di `docs/manual-pemakaian.md` (Lampiran A isinya tumpang
tindih dengan halaman ini — halaman ini versi yang bisa dijalankan).

Satu perintah: **`bash bin/deploy.sh`** dari root aplikasi lewat SSH. Skripnya berhenti
dengan pesan kalau `.env` belum benar; ia tidak pernah menulis `.env`.

## Yang dilakukan skrip, dan kenapa tiap baris ada

| Langkah | Perintah | Alasannya (bukan rutinitas) |
|---|---|---|
| Gerbang `.env` | `grep APP_ENV/APP_DEBUG/APP_KEY/APP_TIMEZONE` | `APP_DEBUG=true` di server memancarkan nama tabel + path server di halaman 500. `APP_TIMEZONE` kosong = Berita Acara dihitung dari jam UTC — dokumen resmi bisa bertanggal sehari lebih awal (bug nyata yang ketemu 10 Okt). |
| Dependency | `composer install --no-dev -o` | `--no-dev` tidak mengunggah phpstan/pint/DevSeeder ke kantor; `-o` = classmap teroptimasi, satu berkas autoload yang lebih sedikit dibaca di disk hosting yang lambat. |
| Folder | `mkdir -p storage/...` | Lampiran ditulis ke `storage/app/private/arsip` — **di luar `public/`** (L-01), jadi tidak ada `storage:link` yang perlu diingat dan tidak ada jalur publik ke berkas kantor. |
| Migrasi | `php artisan migrate --force` | Tanpa `--force` perintahnya diam di `APP_ENV=production`; hasil akhirnya "deploy sukses" dengan skema ketinggalan. |
| Build cache | `config:cache` `route:cache` `view:cache` `event:cache` | Lihat angka di bawah. `config:cache` juga penting secara *benar*, bukan cuma cepat: setelah cache dibuat, `env()` mengembalikan `null` — karena itu CSP, batas baris agenda, dan zona waktu dibaca lewat `config()`, bukan `env()` di kode. |
| Panaskan | `curl` 6 jalur | Klik pertama sesudah deploy membayar kompilasi Blade + pengisian OPcache. Terukur 12 Okt: 0,5–1,0 s pada permintaan pertama vs 23–72 ms sesudahnya. |
| Verifikasi | `php artisan up`, `about --only=environment` | Berkas `storage/framework/down` yang tertinggal dari deploy sebelumnya membuat situs 503 sementara semua orang mengira servernya mati. |

## Angka yang benar-benar diukur (12 Okt 2026)

Mesin pengembang: Apache 2.4.58 + mod_php 8.2.12 (XAMPP), MariaDB 10.4.32 dengan
`innodb_buffer_pool_size=256M`, OPcache aktif, database dev (1 surat). Ini **lantai**,
bukan angka server kantor — tapi cukup untuk memisahkan yang nyata dari yang tidak.

| | tanpa cache | dengan `config/route/view` cache |
|---|---|---|
| permintaan pertama suatu layar | 0,26–0,98 s | 0,04–0,92 s |
| layar yang sama, hangat | 23–72 ms | 32–59 ms |

Jujur: **di mesin ini cache build tidak mengubah waktu steady** — selisihnya di dalam
noise. Yang mengubah keadaan adalah (a) aset: 561 KB → 179 KB dan 0 KB pada kunjungan
kedua (`docs/aset-vendor.md`), dan (b) bentuk penyajiannya: `/login` butuh 304–393 ms
lewat `php artisan serve` tapi 45–68 ms lewat Apache mod_php pada berkas yang sama.
Tetap jalankan cache-nya — di shared hosting (disk lambat, banyak file stat per
request) manfaatnya nyata, dan risikonya nol — tapi jangan klaim angka yang tidak
diukur.

**Cache aktif juga diverifikasi benar, bukan cuma cepat.** 12 Okt, dengan
`config:cache` + `route:cache` + `view:cache` + `event:cache` menyala, sepuluh layar
(`/dashboard`, kedua daftar, mode gabungan `?jenis=semua`, `/laporan`, `/aktivitas`,
`/users`, `/klasifikasi-primer`, `/pemusnahan-arsip`, `/pengaturan-instansi/edit`,
`/surat-masuk/create`) tetap 200 dengan layout dan konten sungguhan (nama instansi
terbaca di daftar, panel "Pratinjau Kop Surat" ada di halaman pengaturan). Pemeriksaan
ini belum pernah dijalankan sama sekali sebelumnya, padahal `config:cache` adalah cara
paling umum membuat `env()` mengembalikan `null` di server: kalau ada satu konfigurasi
yang dibaca lewat `env()` langsung, kegagalannya baru kelihatan di produksi.
Latihan lokalnya: `bash bin/deploy.sh --hanya-cache --warmup=http://127.0.0.1:8080`
(mode itu melewati gerbang `.env` supaya bisa jalan di laptop), lalu
`php artisan optimize:clear` untuk mengembalikan keadaan dev.

Catatan hangat-dari-terukur: `--warmup` hanya bisa mengakses halaman publik, jadi
yang dipanaskan adalah bootstrap framework + OPcache + Blade hasil `view:cache`;
klik pertama per layar sesudah deploy masih bisa 0,3–0,9 s. Itu harga kompilasi sekali
per berkas, bukan bug — dan di OPcache dengan `validate_timestamps=1` ia hanya dibayar
saat kode benar-benar berubah.

## Yang TIDAK bisa diverifikasi dari laptop ini

1. **`upload_max_filesize` / `post_max_size` ≥ 26M.** PHP menolak sebelum Laravel
   sempat memberi pesan ramah, jadi gejalanya "tombol Unggah diam". Cek:
   `php -i | grep -E "upload_max_filesize|post_max_size"`.
2. **cron `schedule:run`.** Tanpa ini tidak ada backup DB 02:10, tidak ada sinkron
   Drive, tidak ada daftar arsip usang, log tidak pernah dibersihkan. Semua hidup di
   `app/Console/Kernel.php` dan hanya jalan kalau cron ada.
3. **Uji pulih backup (X5).** `php artisan arsip:backup-db` lalu pulihkan ke skema
   kosong dan bandingkan jumlah baris per tabel — prosedur lengkapnya di manual
   Lampiran A.6. Lakukan SEBELUM data kantor masuk, bukan sesudahnya.
4. **HTTPS.** `SESSION_SECURE_COOKIE=true` + HSTS baru boleh dinyalakan begitu
   sertifikat terpasang; itu keputusan S14 yang masih menunggu.

## `.htaccess` harus diuji di Apache, tidak bisa diuji di `artisan serve`

Satu direktif yang tidak dikenali Apache membuat **seluruh situs menjawab 500** —
bukan mematikan satu fitur. Ini terjadi sungguhan pada blok kompresi yang ditulis
12 Okt dan tidak mungkin kelihatan lewat `php artisan serve` (server itu tidak membaca
`.htaccess`). Resep mengujinya di laptop (XAMPP sudah punya semuanya):

```bash
# 1. konfigurasi sendiri, JANGAN mengedit C:\xampp\apache\conf\httpd.conf
#    butuh: alias rewrite filter deflate expires headers mime dir authz_core authz_host php
#    Listen 127.0.0.1:8080, DocumentRoot <repo>/public, AllowOverride All
C:/xampp/apache/bin/httpd.exe -t -f <conf>.conf            # validasi dulu
C:/xampp/apache/bin/httpd.exe -f <conf>.conf               # jalan di konsol, Ctrl+C untuk berhenti

# 2. bukti kompresi + cache
curl -sI  -H "Accept-Encoding: gzip" http://127.0.0.1:8080/vendor/bootstrap/bootstrap.min.css?v=5.3.3
curl -s -H "Accept-Encoding: gzip" -o /dev/null -w "%{size_download}\n" \
     http://127.0.0.1:8080/vendor/bootstrap/bootstrap.min.css?v=5.3.3   # 232803 -> 31248
# 3. bukti guard: matikan mod_filter di conf, situs HARUS tetap 200 (tanpa kompresi), bukan 500
```

Langkah 3 itu yang membuat klaim "sudah diuji" berarti: menguji jalur gagal, bukan
hanya jalur happy.

## Kalau tidak ada SSH (hosting cuma cPanel)

Jalankan langkah yang sama di Terminal cPanel. Kalau terminal juga tidak ada,
cache build bisa dipicu lewat satu panggilan web sementara — **hapus berkasnya
setelah dipakai**, karena ia adalah pintu belakang ke `artisan`:

```php
<?php // undo.php — simpan sementara di public/, buka sekali, lalu HAPUS
echo shell_exec('php ../artisan config:cache && php ../artisan route:cache && php ../artisan view:cache');
```
