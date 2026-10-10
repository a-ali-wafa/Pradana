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

Catatan yang perlu diingat sebelum seseorang mengunggah versi baru:

1. **Versi SweetAlert2 dulu mengambang** (`sweetalert2@11` di tag `<script>`), jadi
   browser staf bisa menerima rilis terbaru tanpa ada diff di repo ini. Sekarang
   versinya tertulis di nama file `?v=` di Blade dan di banner berkasnya — dan
   `AsetLokalTest` menuntut banner itu menyebut `11.26.25`. Naikkan versi = ubah
   angka di tes + `?v=` + tabel di atas, dalam satu perubahan yang kelihatan.
2. **File font yang diambil hanya `.woff2`** (solid/regular/brands/v4compatibility).
   CSS-nya juga menyebut `.ttf` sebagai fallback, tapi browser modern tidak akan
   pernah memintanya; `.svg` font Awesome 6 memang tidak ada. Kalau suatu hari
   ada perangkat kantor yang sangat tua dan icon-nya kosong, itu penyebabnya.
3. **Font Awesome minta atribusi** (CC BY 4.0 untuk ikon). Berkas ini adalah bentuk
   atribusinya — jangan dihapus saat "merapikan folder".
4. `config/security.php` (CSP) sudah tidak mengizinkan `cdn.jsdelivr.net` dan
   `cdnjs.cloudflare.com`. Menambah aset CDN baru berarti membuka lagi dua host
   itu, dan `AsetLokalTest` + `HeaderKeamananTest` akan menolaknya lebih dulu.
5. Ganti berkas = tulis ulang file yang sama (nama path tidak berubah), jadi
   `?v=` di Blade adalah satu-satunya penanda cache untuk browser staf. Naikkan
   `?v=` setiap kali mengganti isi aset.
