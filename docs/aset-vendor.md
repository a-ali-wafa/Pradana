# Aset pihak ketiga di `public/vendor/`

Semua berkas CSS/JS/font di `public/vendor/` disimpan **di dalam repo ini**, bukan
diambil dari CDN saat halaman dimuat (10 Okt 2026). Alasannya operasional: kantor
punya satu ISP, dan sebelumnya internet/DNS bermasalah berarti arsip tetap terbuka
tapi tanpa tata letak dan tanpa konfirmasi apa pun di tombol Hapus/Nyahkan.
`docs/daftar-peningkatan.md` temuan #1.

| Folder | Paket | Versi | Lisensi | Sumber unduhan |
|---|---|---|---|---|
| `bootstrap/` | Bootstrap (CSS + bundle JS, berisi Popper) | 5.3.3 | MIT | cdn.jsdelivr.net/npm/bootstrap@5.3.3 |
| `sweetalert2/` | SweetAlert2 (`sweetalert2.all.min.js`) | 11.26.25 | MIT | cdn.jsdelivr.net/npm/sweetalert2@11.26.25 |
| `font-awesome/` | Font Awesome Free (CSS `all.min.css` + 4 `.woff2`) | 6.4.0 | Font: SIL OFL 1.1 · Ikon: CC BY 4.0 · Kode: MIT | cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0 |

## Yang sebenarnya dimuat halaman (subset, dibuat 12 Okt 2026)

`all.min.css` dan `fa-solid-900.woff2` adalah **bahan**, bukan yang disajikan. Yang
dimuat browser adalah dua berkas hasil `php bin/buat-subset-ikon.php`:

| Berkas yang diminta browser | Ukuran | Sebagai ganti | Ukuran asal |
|---|---|---|---|
| `css/pradana-icons.min.css` | 19,3 KB | `all.min.css` | 99,6 KB |
| `webfonts/pradana-solid-900.woff2` | 6,7 KB (72 glyph) | `fa-solid-900.woff2` | 146,6 KB (1.391 glyph) |

Jalankan ulang skripnya setiap kali menambah ikon. Skrip itu butuh **Python +
fontTools + brotli** (`pip install fonttools brotli`) untuk tahap font — sama seperti
Node di `bin/uji-pradana-arsip.js`, ini alat di mesin pengembang saja: aplikasi tidak
pernah memanggil Python dan hosting tidak butuh apa pun selain berkas hasilnya.
Kalau Python tidak ada, CSS-nya tetap bisa dibuat; yang gagal hanya berkas fontnya,
dan skrip berhenti dengan pesan yang menyebutkan perintah `pip`-nya.

Font disubset per **codepoint yang benar-benar ditulis CSS hasil**, lalu cmap berkas
jadi dibaca ulang (`bin/cek-kodepoin-font.py`) dan skrip keluar dengan kode 1 kalau ada
glyph yang hilang. Itu bukan seremonial: pemeriksaan ini langsung menemukan bahwa
`U+0020` yang tadinya diminta "buat jaga-jaga" memang tidak ada di `fa-solid-900` —
font Font Awesome berisi area PUA saja, tanpa glyph ASCII.

Berkas `fa-solid-900.woff2`, `fa-regular-400.woff2`, `fa-brands-400.woff2` dan
`fa-v4compatibility.woff2` sengaja **tetap di repo**: yang pertama dibutuhkan untuk
membuat subset, tiga lainnya dirujuk oleh blok `@font-face` yang dibiarkan supaya
`far`/`fab` di masa depan langsung jalan. Browser tidak mengunduh `@font-face` yang
tidak dipakai — terukur 12 Okt: dari lima berkas font hanya satu yang benar-benar
ditransfer.

## Ukur ulang di Apache, bukan di `artisan serve`

`php artisan serve` tidak membaca `.htaccess` dan memakai satu proses PHP untuk semua
request, jadi ia tidak bisa dipakai menilai kompresi, header cache, maupun kecepatan
aplikasi. Angka di bawah diambil 12 Okt 2026 dengan Apache 2.4.58 + mod_php 8.2.12
(XAMPP, konfigurasi terpisah di luar repo) pada halaman dashboard:

| | tanpa kompresi | dengan mod_deflate |
|---|---|---|
| `bootstrap.min.css` | 232.803 B | **31.248 B** (−87%) |
| `bootstrap.bundle.min.js` | 79 KB | **23.799 B** (−70%) |
| `pradana-solid-900.woff2` | 6.852 B | 6.852 B (sengaja tidak deflate — sudah Brotli) |
| seluruh aset, kunjungan pertama | 561 KB | **179 KB** |
| seluruh aset, kunjungan kedua | — | **0 KB** (semuanya dari cache) |

Waktu muat halaman pertama (cold): TTFB 151 ms, first contentful paint 260 ms, load
359 ms. Reload hangat: FCP 320 ms dengan 7 dari 8 aset layan dari cache. Untuk
perbandingan yang jujur: di mesin yang sama `php artisan serve` butuh 304–393 ms untuk
`/login` sedangkan mod_php 45–68 ms — jadi jangan pakai `artisan serve` untuk
menyimpulkan "aplikasinya lambat".

Dua jebakan yang dibayar mahal saat pengujian ini (baca `public/.htaccess` sebelum
menyentuhnya): satu direktif yang tidak dikenali Apache membuat **seluruh situs
menjawab 500**, bukan sekadar mematikan satu fitur.

Catatan yang perlu diingat sebelum seseorang mengunggah versi baru:

1. **Versi SweetAlert2 dulu mengambang** (`sweetalert2@11` di tag `<script>`), jadi
   browser staf bisa menerima rilis terbaru tanpa ada diff di repo ini. Sekarang
   versinya tertulis di nama file `?v=` di Blade dan di banner berkasnya — dan
   `AsetLokalTest` menuntut banner itu menyebut `11.26.25`. Naikkan versi = ubah
   angka di tes + `?v=` + tabel di atas, dalam satu perubahan yang kelihatan.
2. **File font yang diambil hanya `.woff2`** (solid/regular/brands/v4compatibility).
   `all.min.css` aslinya juga menyebut `.ttf` sebagai fallback, tapi berkas `.ttf`
   tidak pernah di-commit — rujukan itu mati, jadi rujukan `.ttf` dibuang dari CSS
   hasil subset (12 Okt). Kalau suatu hari ada perangkat kantor yang sangat tua dan
   icon-nya kosong, penyebabnya bukan `.ttf` yang hilang melainkan font sistemnya
   sendiri; jangan pura-pura fallback itu menolong.
3. **Font Awesome minta atribusi** (CC BY 4.0 untuk ikon). Berkas ini adalah bentuk
   atribusinya — jangan dihapus saat "merapikan folder".
4. `config/security.php` (CSP) sudah tidak mengizinkan `cdn.jsdelivr.net` dan
   `cdnjs.cloudflare.com`. Menambah aset CDN baru berarti membuka lagi dua host
   itu, dan `AsetLokalTest` + `HeaderKeamananTest` akan menolaknya lebih dulu.
5. Ganti berkas = tulis ulang file yang sama (nama path tidak berubah), jadi
   `?v=` di Blade adalah satu-satunya penanda cache untuk browser staf. Naikkan
   `?v=` setiap kali mengganti isi aset.
