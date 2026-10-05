# Manual Pemakaian PRADANA

**Untuk:** staf arsip dan kepala desa/lurah. Tidak perlu bisa pemrograman.
**Versi:** 4 Oktober 2026.
Aplikasi dibuka lewat browser di computer kantor (Chrome, Firefox, atau Edge versi baru). Tidak perlu install apa pun di computer staf.

Semua nama tombol dan nama menu di manual ini sama dengan yang ada di layar.

---

## 1. Masuk (login)

1. Buka alamat aplikasi yang diberikan petugas (contoh: `http://192.168.1.20:8000`).
2. Isi **email** dan **PIN 8 digit** yang diberikan admin.
3. Tekan **Masuk**. Anda langsung sampai di Dashboard.

Catatan penting:

- Tidak ada link "lupa PIN" di layar. Kalau lupa, **minta admin**-nya — admin membuka menu **Manajemen User** lalu menekan **Reset PIN** di baris nama Anda, dan membacakan PIN barunya.
- Kalau Anda salah mengetik PIN **5 kali berturut-turut**, login dikunci sementara ± 1 menit. Tunggu, jangan terus dicoba.
- Aplikasi keluar sendiri kalau tidak dipakai ± 2 jam. Lihat bagian 15 kalau muncul kode **419**.
- Setiap orang pakai akun sendiri. Jangan pinjam-meminjam PIN: log aktivitas mencatat atas nama siapa sebuah surat diubah.

Menu di kiri layar: **Dashboard, Surat Masuk, Surat Keluar, Pencarian Arsip, Pemusnahan Arsip, Laporan & Agenda**. Untuk admin ada tambahan: **Pengajuan Hapus Lampiran, Klasifikasi Primer/Sekunder/Tersier, Log Aktivitas, Manajemen User, Pengaturan Instansi**. Hal-hal milik akun Anda (nama, email, ganti PIN, keluar) ada di tombol **Akun** di kanan atas.

---

## 2. Dashboard

Halaman pertama sesudah login. Isinya ringkasan kerja hari itu: jumlah surat masuk/keluar, surat terbaru, klasifikasi paling banyak dipakai, dan (untuk admin) catatan aktivitas terakhir.

Semua pekerjaan dimulai dari **menu di kiri layar** — dashboard sengaja tidak mengulang tombol pintasan, supaya tidak ada dua jalan ke tempat yang sama.

---

## 3. Mencatat surat masuk

1. Menu **Surat Masuk** → tombol **Tambah Surat Masuk**.
2. Isi kolom bertanda wajib: **Pengirim**, **Nomor Surat**, **Tanggal Surat**, **Tanggal Diterima**, **Perihal**, **Sifat** (mendesak/penting/rahasia/biasa), **Status Berkas** (asli/salinan), dan **Klasifikasi Primer**.
3. Kolom lain boleh dikosongkan: jabatan & instansi pengirim, kota/provinsi asal, klasifikasi sekunder & tersier, ringkasan, lokasi fisik arsip.
4. Tekan **Simpan**.

Yang perlu diketahui:

- **Tanggal Diterima tidak boleh lebih awal dari Tanggal Surat** — sistem menolaknya, dan itu benar: surat tidak mungkin diterima sebelum ditulis.
- **Klasifikasi** memilih berjenjang: setelah Primer dipilih, dropdown Sekunder dan Tersier terisi sendiri. Kalau Sekunder/Tersier belum ada di daftar, itu tugas admin menambahkannya (bagian 13).
- Klasifikasi yang dipilih menentukan **folder tempat file arsip disimpan**, jadi jangan asal pilih "Umum".
- Surat masuk boleh disimpan dengan **nomor surat yang sama** dengan surat lain (misal satu surat dikirim ke beberapa instansi). Yang tidak boleh duplikat hanya nomor surat keluar.
- Lampiran diunggah **setelah** surat tersimpan — lihat bagian 6. Berkas PDF/DOCX/XLSX yang diunggah akan **dicoba dibaca isinya** otomatis, lalu teksnya muncul untuk Anda periksa sebelum dipakai (bagian 6).

---

## 4. Mencatat surat keluar

Sama seperti surat masuk, lewat **Surat Keluar** → **Tambah Surat Keluar**. Bedanya:

- Yang diisi **Penerima** (bukan pengirim) dan **Kota/Provinsi Tujuan**.
- **Nomor surat dibuat otomatis** oleh sistem, formatnya `001/01.02.03/IX/2026` = urutan-tahun, kode klasifikasi, bulan romawi, tahun. Nomor urut mulai dari 001 lagi setiap ganti tahun.
- Karena nomor itu otomatis, **jangan diketik sendiri**. Kalau salah catat dan perlu dikoreksi, hanya admin yang boleh mengubahnya (sistem menolak kalau bukan admin).
- Kalau muncul pesan bahwa nomor sudah dipakai, tekan saja simpan sekali lagi — sistem mengambil nomor berikutnya. Ini terjadi kalau dua orang menyimpan pada detik yang sama.

---

## 5. Mengisi isi surat & mencetak PDF

Surat keluar punya "isi surat" untuk diterbitkan sebagai PDF berkop:

1. Buka detail surat keluar → tombol **Isi Draf & Cetak**.
2. Isi **alamat tujuan, salam pembuka, isi surat, tembusan, penandatangan, NIP/NIK**. Penomoran lampiran di PDF terisi otomatis dari jumlah file yang diunggah, jadi tidak perlu diketik.
3. Tekan **Simpan**, atau **Simpan & Cetak** langsung untuk membuka PDF-nya.
4. Tombol **Cetak PDF** di halaman detail baru muncul setelah draf disimpan sekali.

PDF dibuka di tab baru. Simpan lewat browser (Ctrl+S) atau tekan Ctrl+P untuk mencetak ke printer.

Bentuk PDF mengikuti tata naskah dinas yang lazim di kantor desa/kelurahan: kop bertingkat (Kabupaten → Kecamatan → Desa) dengan garis ganda, blok **Nomor / Klasifikasi / Sifat / Lampiran / Perihal** sejajar, tempat & tanggal di kanan ("Urek-Urek, 5 Oktober 2026"), alamat tujuan dengan **Kepada Yth. … di …**, isi rata kiri-kanan per paragraf, blok tanda tangan dengan jabatan + nama digarisbawahi + NIP/NIK, dan **Tembusan** bernomor. Isi kop diambil dari menu **Pengaturan Instansi** (bagian 13.3). Kalau desa sudah punya kop/lembar berlogo resmi dan ingin PDF-nya disamakan, itu dicatat sebagai penyesuaian tersendiri — sampai sekarang dipakai satu template umum.

---

## 6. Lampiran (scan & file)

Di halaman detail surat (masuk maupun keluar) ada kotak **Unggah Lampiran Baru**:

1. Tekan **Pilih berkas**, boleh memilih **lebih dari satu berkas sekaligus** (maks 10 berkas sekali jalan).
2. Tekan **Unggah**. Muncul bar proses mengambang di pojok layar.
3. **Jangan tutup atau pindah halaman** selama bar masih jalan — sistem menolaknya dan akan muncul pertanyaan dari browser.
4. Setelah selesai, jumlah lampiran di layar ikut naik sendiri.

Aturan yang berlaku:

- Format diterima: **PDF, JPG, PNG, DOC, DOCX, XLS, XLSX**. Maksimal **25 MB** per berkas.
- Berkas yang **isinya sama persis** dengan yang sudah ada di surat itu **dilewati** (tidak dihitung dua kali), dan muncul pesan "1 berkas dilewati…". Kalau memang perlu menyimpan dua salinan identik, ubah dulu isinya (misal tambah catatan di file) — ini sengaja supaya arsip tidak membengkak.
- File arsip tersimpan di computer arsip kantor, **tidak bisa dibuka dari luar** tanpa login. Tekan **Unduh** untuk melihat (dibuka di layar) atau menyimpan ke computer.
- Nomor telepon/alamat di dalam file tetap terbaca orang lain kalau file-nya dipublikasikan sendiri oleh staf — tanggung jawab ada di yang mengunggah.

### Isi surat ikut terbaca (khusus surat masuk)

Sesudah unggah, sistem **mencoba membaca isi berkasnya** dan menampilkan teksnya di kartu **Hasil Baca Isi Lampiran** pada halaman surat itu juga — tidak perlu muat ulang.

Yang perlu diketahui pemakai:

- Teks itu **draf hasil mesin, belum pasti benar**. Kartu ditandai **belum diverifikasi** sampai ada orang menekan **Simpan (sudah saya periksa)**. Perbaiki langsung di kolomnya kalau ada yang salah baca, lalu simpan.
- Centang **Salin teks ini ke Ringkasan surat** hanya kalau memang mau ringkasan surat terisi dari teks tersebut. Tanpa dicentang, ringkasan yang sudah Anda ketik tidak akan tertimpa.
- Yang bisa dibaca: **PDF berisi teks (hasil export Word/Excel/LibreOffice), DOCX, dan XLSX**. Yang TIDAK bisa dibaca: **foto/scan (JPG, PNG)** — sistem tidak punya pembaca tulisan tangan/OCR — dan **DOC/XLS format lama**. Untuk dua kasus terakhir, simpan ulang berkasnya sebagai PDF/DOCX/XLSX dari program Office, atau ketik ringkasannya sendiri.
- Berkas yang gagal terbaca **tetap tersimpan** sebagai lampiran. Kegagalan baca tidak pernah membuat unggahan gagal; alasannya muncul di keterangan kartu.

---

## 7. Mencari arsip

Menu **Pencarian Arsip** sudah tidak ada sejak 5 Okt 2026 — pencariannya pindah ke tempat orang memang mencarinya: kotak **Cari** di halaman **Surat Masuk** dan di halaman **Surat Keluar**.

Kotak itu menggali **nomor surat, perihal, pengirim atau penerima, instansi, ringkasan, dan isi surat keluar** (termasuk isi draf yang diketik di halaman draf). Jadi cukup mengetik potongan kata, misalnya `irigasi`, tanpa perlu hafal nomornya.

Samping kotak ada saringan yang bekerja bersama kata kunci: **tahun, sifat, status arsip, klasifikasi primer**. Hasil ditampilkan **20 per halaman**; kosongkan kotak untuk melihat semua.

Sedikit trik yang berguna:

- Surat yang sedang di **tempat sampah** tidak muncul di pencarian. Untuk itu, buka daftar surat lalu centang/tekan penyaring tempat sampah (khusus admin).
- Kalau kata kunci panjang (satu kalimat) tidak ketemu, coba potongan yang lebih pendek — sistem mencari teks persis, bukan sinonim.
- Isi surat masuk yang berasal dari lampiran terbaca hanya kalau lampirannya PDF/DOCX/XLSX (lihat bagian 6). Foto/hasil scan tidak ikut ketemu karena isinya belum terbaca sistem.

---

## 8. Menyortir: "Nyahkan" (aktif → inaktif)

Arsip yang sudah selesai dipakai pindah status dari **aktif** ke **inaktif**:

1. Buka halaman detail surat.
2. Tekan **Nyahkan**. Status berubah, tidak ada file yang hilang.
3. Untuk mengembalikannya: tekan **Aktifkan Kembali**.

Sistem **tidak pernah** men-*inaktif*-kan surat sendiri. Surat berumur lewat 5 tahun hanya **didaftarkan** supaya ditinjau — centang **Lewat retensi 5 tahun** di halaman daftar surat untuk melihatnya. Keputusan menonaktifkan tetap di tangan staf.

---

## 9. Menghapus surat & mengembalikannya (khusus admin)

Yang boleh menghapus surat **hanya admin**, dan penghapusannya **tidak langsung hilang permanen**:

1. Di halaman detail surat → **Hapus** → konfirmasi. Surat pindah ke **tempat sampah**, lampirannya tetap utuh.
2. Untuk melihatnya lagi: halaman daftar surat → tombol **Tempat sampah** di kanan atas.
3. Untuk mengembalikan: tekan **Pulihkan** di baris surat itu.

Cara memulihkan juga berlaku kalau surat terhapus karena kekeliruan. Selama surat ada di tempat sampah, ia **tidak muncul** di daftar biasa dan **tidak bisa ditemukan** lewat pencarian.

Hapus permanen **tidak** tersedia di menu ini. Jalur satu-satunya adalah pemusnahan (bagian 10).

---

## 10. Memusnahkan arsip + Berita Acara

Memusnahkan = menghancurkan arsip sampai tuntas, dengan bukti resmi. Syarat sebuah surat boleh diajukan musnah:

- umur surat **lebih dari 5 tahun** (dihitung dari tanggal surat), **dan**
- statusnya sudah **inaktif**, **dan**
- belum ada pengajuan pemusnahan yang menunggu keputusan.

Alur:

1. Menu **Pemusnahan Arsip** → **Ajukan Pemusnahan**. Hanya surat yang memenuhi syarat di atas yang tampil di daftar itu.
2. Centang surat yang akan dimusnahkan, isi alasannya, kirim. Status pengajuan: **menunggu**.
3. Admin membuka menu **Pemusnahan Arsip** (ada angka "N menunggu" di menu), memeriksa, lalu **Setujui** atau **Tolak**.
4. Saat disetujui: baris surat **dan** file lampirannya benar-benar dihapus, dan sistem membuat **Berita Acara** bernomor (contoh `BA-003/X/2026`).
5. Cetak Berita Acara lewat tombol **Cetak Berita Acara** di halaman pengajuan, tanda tangani, dan simpan bersama arsip kantor.

Yang tercatat di Berita Acara: nomor, perihal, tanggal tiap surat, siapa yang mengajukan, siapa yang menyetujui, dan tanggal pelaksanaannya. Jejak ini **tidak pernah dibuang** sistem, meskipun log biasa dibersihkan.

---

## 11. Menghapus satu lampiran saja (alur pengajuan)

Kadang file scan yang salah unggah perlu dibuang, tapi suratnya tetap disimpan. Lampiran baru boleh dihapus kalau suratnya sudah lewat 5 tahun:

1. Halaman detail surat → di baris file tersebut tekan **Ajukan Hapus**, isi alasan.
2. Admin membuka menu **Pengajuan Hapus Lampiran** → **Setujui & Hapus Lampiran** atau **Tolak**.
3. Daftar pengajuan menampilkan juga riwayat yang sudah diproses; gunakan penyaring status di kanan atas.

---

## 12. Laporan & Buku Agenda

Menu **Laporan & Agenda**. Satu filter dipakai untuk semua keluaran: **periode (dari–sampai)**, jenis surat, klasifikasi primer, dan status arsip. Periode bawaan adalah bulan berjalan.

Di layar tertulis **"N surat cocok dengan filter ini"** — bacanya dulu sebelum mengunduh. Kalau N = 0, akan muncul peringatan bahwa periodenya memang kosong (itu bukan aplikasi rusak).

- **Unduh Rekap CSV** — tabel rekap untuk dibuka di Excel / LibreOffice. Pemisah berkas ini memakai `;` dan tampil benar di Excel versi Indonesia.
- **Cetak Buku Agenda (PDF)** — Buku Agenda Surat A4 memanjang, format seperti buku agenda manual kantor: bagian A surat masuk, bagian B surat keluar, blok tanda tangan di bawah. Buka di tab baru lalu cetak.

---

## 13. Untuk admin: user, klasifikasi, instansi, log

### 13.1 Membuat akun & mengatur PIN
Menu **Manajemen User** → **Tambah User**: nama, email, PIN 8 digit, dan role. Cuma ada dua role:

- **Admin (Kepala)** — menyetujui pemusnahan & hapus lampiran, menghapus/memulihkan surat, mengelola user, klasifikasi, pengaturan instansi, mengoreksi nomor surat keluar, dan membuka Log Aktivitas.
- **Pegawai** — mencatat & mengubah surat, mengunggah/mengunduh lampiran, men-*nyahkan* arsip, mengajukan penghapusan dan pemusnahan.

Reset PIN lewat tombol **Reset PIN** di baris user. Menghapus user boleh dilakukan meskipun ia punya surat: arsip adalah milik kantor, dan jejak perbuatannya tetap ada di log.

### 13.2 Klasifikasi
Menu **Klasifikasi Primer / Sekunder / Tersier**, tambah lewat tombol **Tambah**. Hierarki: Primer → Sekunder → Tersier. Menghapus induk ikut menghapus yang di bawahnya, jadi sistem menolak menghapus klasifikasi yang sudah dipakai surat.

### 13.3 Pengaturan instansi
Menu **Pengaturan Instansi** — ini yang menentukan bentuk kop surat kantor. Layarnya dua kolom: kiri isian, kanan **pratinjau kop** yang berubah langsung mengikuti tulisan di kiri, jadi kelihatan benar atau tidaknya sebelum disimpan.

Isian yang ada sekarang: **Nama Instansi** (baris besar kop), **Jenis Instansi** (mis. "Pemerintah Desa" — dipakai untuk menulis "Kepala Desa" di blok tanda tangan), **Kabupaten** dan **Kecamatan** (dua baris atas kop bertingkat; kosongkan kalau kantor Anda memakai kop satu baris), **Alamat**, **Kode Pos**, **Telepon**, **Email**, dan **Logo**. Telepon, email, kode pos, kabupaten, dan kecamatan boleh kosong — barisnya cukup tidak ikut tercetak.

Field terkunci sampai tombol **Edit** ditekan, supaya kop tidak kepencet berubah.

Logo: setelah **Edit**, kolom "Ganti Logo" muncul. Begitu berkas dipilih, **pratinjau logo** langsung tampil — baik di blok logo maupun di kop pratinjau sebelah kanan — tanpa perlu menyimpan dulu. Pakai itu untuk memastikan logo tidak terbalik, terpotong, atau pecah. Logo lama tetap yang dipakai sampai perubahan disimpan. Logo tampil lewat aplikasi (bukan folder internet), jadi tidak perlu pengaturan tambahan di server; kalau dulu logo tidak muncul sama sekali, itu masalah yang sudah diperbaiki.

Baris tanggal surat ("Urek-Urek, 5 Oktober 2026") memakai nama instansi tanpa kata "Pemerintah"/"Kantor"/"Sekretariat" — jadi kalau Nama Instansi diisi "PEMERINTAH DESA UREK-UREK", tanggalnya tertulis "DESA UREK-UREK, 5 Oktober 2026".

### 13.4 Log aktivitas
Menu **Log Aktivitas** (hanya admin): siapa melakukan apa dan kapan, disaring per orang, per kata, atau per rentang tanggal. Layar ini hanya membaca — tidak ada tombol hapus. Catatan lebih dari 2 tahun dibersihkan otomatis oleh sistem, **kecuali** jejak pemusnahan.

### 13.5 Ganti PIN sendiri
Tekan tombol **Akun** di kanan atas → **Ganti PIN**. Popup berisi PIN lama, PIN baru, dan ulangi PIN baru. Popup tidak tertutup sendiri kalau isinya salah — perbaiki tulisan merah di kolomnya lalu simpan lagi.

---

## 14. Kebiasaan yang menjaga arsip tetap aman

- Unggah scan **sesudah** surat tersimpan, dan langsung cek jumlahnya di layar.
- Jangan menyalin, memindah, atau menamai ulang file di folder arsip computer server — sistem kehilangan jejaknya kalau nama file diubah.
- Kalau satu surat sudah benar dan tidak ada perubahan, jangan diedit lagi hanya untuk "merapikan": setiap perubahan tercatat di log dan bisa menimbulkan pertanyaan saat audit.
- Jangan menaruh salinan arsip **hanya** di email atau flashdisk pribadi — satu salinan resminya harus ada di computer kantor.
- Sekali setahun (biasanya Januari): admin membuka Laporan, periode tahun lalu, unduh rekap + Buku Agenda, simpan sebagai arsip tahun.

---

## 15. Kalau muncul halaman bermasalah

Setiap layar kesalahan menampilkan kode besar dan apa yang harus dilakukan. Ringkasnya:

| Kode | Artinya | Yang perlu Anda lakukan |
|---|---|---|
| **403** | Halaman itu khusus admin | Minta admin yang membuka; jangan minta hak akses |
| **404** | Alamat salah / surat tidak ada | Buka daftar **Surat Masuk** / **Surat Keluar** lalu cari di kotaknya, jangan menebak alamat |
| **419** | Formulir terlalu lama dibiarkan terbuka | Muat ulang, isi ulang, kirim lagi. Untuk teks panjang, ketik di Word lalu tempel |
| **500** | Kesalahan di sisi server | Tunggu sebentar, coba lagi **satu kali**; kalau gagal lagi catat jam & sedang membuka apa, lalu laporkan |
| **503** | Sedang diperbarui petugas | Tunggu beberapa menit lalu muat ulang |

Laporan ke petugas aplikasi akan cepat selesai kalau menyebut: **kode**, **jam**, **halaman/menu**, dan **nomor surat** yang sedang dibuka.

---

## Lampiran A — untuk petugas yang memasang & memelihara

### A.1 Prasyarat
- PHP 8.2+ dengan extension `pdo_mysql`, `mbstring`, `fileinfo`, `zip`, `gd`.
- MariaDB/MySQL.
- Composer.
- **Wajib dicek di `php.ini` hosting/server:** `upload_max_filesize` dan `post_max_size` minimal **26M**. Kalau dibiarkan bawaan (2M), unggahan gagal **sebelum** Laravel sempat memberi pesan yang ramah.

### A.2 Pasang
```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# isi .env: DB_*, APP_URL, SESSION_*, APP_DEBUG=false, APP_ENV=production
php artisan migrate --seed
php artisan arsip:akun-pertama admin@kantor.desa --nama="Kepala Desa"
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
`--seed` hanya menanam satu baris pengaturan instansi kosong — **sengaja**. Akun contoh & klasifikasi contoh ada di `DevSeeder` yang menolak jalan di server produksi.

**Tidak perlu `php artisan storage:link`.** Logo instansi dilayani lewat route `/instansi/logo` yang membaca disk `public` langsung, jadi logo tetap tampil di shared hosting tempat symlink tidak bisa (atau belum) dibuat. Kalau suatu saat pindah ke setup yang menyediakan symlink, route ini tetap jalan — tidak ada yang perlu diubah.

### A.3 Tugas terjadwal (cron)
Scheduler Laravel harus dipanggil tiap menit:
```
* * * * * cd /path/ke/pradana && php artisan schedule:run >> /dev/null 2>&1
```
Isi yang dijadwalkan: backup arsip ke Google Drive (harian), daftar surat lewat retensi (mingguan), `arsip:bersihkan-log` (bulanan). Tanpa cron, ketiganya tidak pernah jalan.

### A.4 Google Drive (opsional, hanya backup)
Simpan kredensial Service Account di `storage/app/google/service-account.json` (**jangan pernah di-commit**), isi `GOOGLE_DRIVE_ROOT_FOLDER_ID` di `.env`, lalu bagikan folder itu (akses Editor) ke email Service Account. Uji: `php artisan arsip:sinkron-ke-drive --dry-run`. Aplikasi tetap jalan penuh tanpa Drive — file arsip ada di disk lokal.

### A.5 Perintah yang paling sering dibutuhkan
```bash
php artisan arsip:reset-pin staff@kantor.desa        # user lupa PIN & admin tidak ada
php artisan arsip:daftar-usang --tahun=5             # tinjauan retensi
php artisan arsip:bersihkan-log --dry-run            # lihat dulu apa yang akan dibuang
php artisan tinker                                   # pemeriksaan data
```

### A.6 Backup & uji pulih (wajib sebelum serah terima)
1. Backup database: `mysqldump -u USER -p PRADANA_DB > backup-db.sql` (mingguan).
2. Backup folder `storage/app/private/arsip` (harian lewat sinkron Drive, atau manual).
3. **Uji pulih** di computer lain: buat database kosong, import `.sql`, `migrate` kalau perlu, salin folder arsip, `php artisan serve`, lalu buka 1 surat + unduh 1 lampiran. Unduh tanpa uji = belum tentu backup.
4. Simpan salinan backup di luar computer server (flashdisk/Drive) — kalau satu computer mati, keduanya tidak ikut mati.

### A.7 Setelah memasang
```bash
php artisan test          # 123 tes; butuh DB_TEST_DATABASE untuk 3 tes penomoran di MariaDB
```
Untuk pengembangan lokal: `php artisan db:seed --class=DevSeeder` (hanya jalan saat `APP_ENV=local`), PIN akun contoh dibaca dari `DEV_PIN` di `.env`.

### A.8 Batasan yang belum dibuat (diketahui, bukan kelupaan)
- **Template PDF masih satu bentuk umum.** Multi-template sesuai kop resmi desa menunggu contoh kop dari kantor.
- **Kunci otomatis arsip setelah N hari** belum diaktifkan — nilai N belum diputuskan.
- **Pembaca isi lampiran bukan OCR.** PDF hasil scan (gambarnya saja), JPG/PNG, dan DOC/XLS format lama tidak terbaca; sistem selalu menyebutkan alasannya di kartu hasil baca, tidak pernah diam-diam.
- **Hasil baca mesin = draf.** Teks yang diambil dari PDF/DOCX/XLSX bisa salah susun (tabel, header) dan baru dianggap diverifikasi setelah petugas menekan Simpan.
- Backup Drive tidak menyalin ulang file yang sudah dihapus di kedua sisi; ia menambah, bukan mencerminkan keadaan.
