# PRADANA — Arsip Surat Digital Kantor

Aplikasi arsip **surat masuk dan surat keluar** untuk kantor desa/kelurahan. Dibuat untuk
menggantikan pencatatan di Google Sheets + Google Drive: satu tempat untuk mencatat surat,
menyimpan scan-nya, menomori surat keluar, mencetak PDF berkop, dan menyiapkan Berita
Acara pemusnahan arsip yang diminta saat audit.

- **Manual pemakaian (untuk staf & kepala desa):** [`docs/manual-pemakaian.md`](docs/manual-pemakaian.md)
- **Pasang & pelihara (untuk petugas IT):** Lampiran A di manual yang sama
- **Konteks teknis untuk AI agent / programmer yang melanjutkan:** [`AGENTS.md`](AGENTS.md)
  (riwayat keputusan panjang: `AGENTS_HISTORY.md`)

## Yang bisa dilakukan aplikasi

| Untuk | Fitur |
|---|---|
| Staf arsip | Catat surat masuk/keluar, unggah lampiran (banyak berkas sekaligus), cari arsip termasuk dari isi surat, men-*nyahkan* arsip lewat retensi, isi draf & cetak PDF surat keluar, ajukan hapus lampiran / pemusnahan |
| Admin (kepala desa/lurah) | Semua di atas + setujui pengajuan hapus & pemusnahan (lengkap dengan **Berita Acara** PDF), tempat sampah & pulihkan arsip, kelola user & PIN, klasifikasi 3 tingkat, kop/logo instansi, **Laporan CSV & Buku Agenda PDF**, baca **Log Aktivitas** |
| Otomatis | Nomor surat keluar (`001/01.02.03/IX/2026`, urut lagi tiap Januari), **pembacaan isi lampiran** (PDF/DOCX/XLSX) jadi draf yang tinggal diperiksa petugas, notasi lampiran di PDF dihitung dari jumlah berkas, log setiap perubahan, backup arsip ke Google Drive harian, pembersihan log lama |

## Cara membuka

Aplikasi berjalan di browser (Chrome / Firefox / Edge versi baru) di jaringan kantor.
Alamatnya diberikan petugas yang memasang; biasanya `http://<alamat-server>` di jaringan
kantor atau `http://127.0.0.1:8000` kalau dijalankan di satu computer.

Masuk pakai **email + PIN 8 digit** dari admin. Tidak ada pendaftaran sendiri dan tidak ada
link "lupa PIN" — reset PIN dilakukan admin lewat menu Manajemen User.

## Alur kerja sehari-hari yang paling sering

1. Catat surat masuk baru beserta scan-nya (bagian 3 & 6 manual).
2. Catat surat keluar, isi drafnya, cetak PDF (bagian 4 & 5).
3. Sesudah selesai dipakai, tekan **Nyahkan** di halaman surat (bagian 8).
4. Awal tahun berikutnya: unduh rekap CSV + Buku Agenda tahun lalu (bagian 12), lalu
   ajukan pemusnahan arsip yang sudah lewat 5 tahun (bagian 10).

## Catatan yang perlu diketahui sejak awal

- File arsip disimpan di computer server kantor, di luar folder yang bisa diakses publik.
  Tidak ada satu pun tautan file yang dibagikan tanpa login.
- Penghapusan surat **tidak pernah** langsung permanen dari layar — masuk tempat sampah
  dulu, dan hanya bisa dipulihkan admin. Hapus total hanya lewat alur **Pemusnahan Arsip**
  yang meninggalkan Berita Acara.
- PDF surat mengikuti tata naskah dinas desa pada umumnya (kop bertingkat Kabupaten →
  Kecamatan → Desa, blok Nomor/Klasifikasi/Sifat/Lampiran/Perihal, tempat & tanggal,
  blok tanda tangan, tembusan bernomor). Kop **resmi** desa (logo dan susunan khusus)
  bisa menyusul begitu contoh dari kantor diberikan.
- Isi lampiran surat masuk ikut terbaca kalau berkasnya PDF berisi teks, DOCX, atau
  XLSX. Foto/hasil scan dan DOC/XLS lama tidak terbaca (tidak ada OCR di server kantor),
  dan sistem selalu mengatakannya, bukan diam-diam.
- Aplikasi ini dibuat untuk dipakai satu kantor (bukan untuk banyak desa sekaligus).

## Untuk pengembang

```bash
composer install
cp .env.example .env && php artisan key:generate   # lalu isi DB_* dan DEV_PIN
php artisan migrate --seed
php artisan db:seed --class=DevSeeder              # data contoh, hanya di APP_ENV=local
php artisan arsip:akun-pertama admin@kantor.desa --nama="Kepala Desa"
php artisan serve
php artisan test
```

Framework: Laravel 12 (PHP 8.2), MariaDB/MySQL, Bootstrap 5 via CDN, PDF lewat dompdf.
Aturan penamaan domain sengaja bahasa Indonesia (`surat_masuk`, `pemusnahan_arsip`) —
lihat `AGENTS.md` sebelum mengubah apa pun.
