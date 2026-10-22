# Deploy PRADANA di Oracle Cloud Always Free

Ditulis 21 Okt 2026, untuk dikerjakan sendiri lewat SSH. Halaman ini bagian dari
`docs/kesiapan-deploy.md`: vonis kesiapannya di sana, langkah pemasangannya di sini, dan
cara memasang aplikasinya (`bin/deploy.sh`) ada di `docs/deploy.md`.

**Status jujur:** `bin/sediakan-server.sh` yang dipakai di bawah ini belum pernah
dijalankan di Ubuntu. Yang sudah dibuktikan di mesin pengembang: sintaks bersih
(`bash -n`), isi konfigurasi Apache yang dihasilkan benar (`DocumentRoot` menunjuk
`public/`, `${APACHE_LOG_DIR}` tidak kena ekspansi shell), baris cron terbentuk dengan
kolom user yang benar, dan mode `--cek` menampilkan rencananya seutuhnya tanpa menulis
apa pun. Sisanya baru terbukti saat dijalankan sekali di VM — dan langkah 8 di bawah
adalah cara membuktikannya.

## 0. Yang kamu butuhkan sebelum mulai

- Kartu kredit/debit untuk verifikasi identitas Oracle (tidak ditagih selama Always Free).
- ±1–2 jam, sebagian besar menunggu provisioning.
- Keputusan **home region** — dan ini **tidak bisa diganti setelah akun dibuat**.
  Untuk pengguna di Jawa: `ap-batam-1` (Indonesia North, Batam). Oracle tidak punya
  region Jakarta.
- Keputusan **HTTPS atau tidak** (lihat bagian 5). Untuk demo tugas akhir tanpa domain,
  http polos itu cukup dan konsekuensinya tertulis di sana — jangan dibuat-buat.

## 1. Daftar akun, dan dua hal yang biasa gagal

1. Daftar di `https://signup.cloud.oracle.com` (Free Tier). Isi home region di langkah 1
   — setelah selesai, tidak bisa dipindah.
2. Kalau kartu ditolak: itu terjadi pada banyak akun mahasiswa, dan biasanya bukan
   karena saldonya. Coba kartu lain, atau tunggu 24 jam lalu ulangi; jangan buat akun
   kedua (Oracle menautkan identitas, dan akun ganda bikin keduanya berisiko).
3. Kalau nanti shape A1 tidak bisa dibuat (`OUT_OF_CAPACITY`): itu masalah kapasitas
   region, bukan salah konfigurasi. Tiga jalan keluar, berurutan dari yang paling murah:
   - coba lagi di jam berbeda (kapasitas dilepas bergelombang);
   - **Upgrade ke Pay As You Go.** Always Free resource-nya tetap Rp 0, tapi kamu tidak
     lagi tergantung kapasitas "free-only" dan bisa bikin instance kapan pun. WAJIB pasang
     **spending limit / budget alert** di konsol begitu akun di-upgrade — dan kalau
     ragu menahan risiko tagihan, jangan ambil jalur ini;
   - fallback: `VM.Standard.E2.1.Micro` (1 CPU / 1 GB). Cukup untuk arsip satu kantor
     kecil, tapi 1 GB berarti swap wajib (bagian 3) dan `memory_limit` PHP harus dijaga.

## 2. Buat instance

- Image: **Ubuntu 24.04** (canonical "Ubuntu 24.04" yang tersedia untuk Always Free).
  PHP 8.3 bawaannya tidak dipakai — `bin/sediakan-server.sh` memasang **8.2**, versi yang
  sama dengan mesin pengembang dan CI, supaya tidak menguji hal yang belum pernah diuji.
- Shape: `VM.Standard.A1.Flex`. 1 OCPU / 6 GB sudah cukup untuk kantor kecil; 2/12 kalau
  memang bebas. Ini juga yang menahan reklamasi idle (bagian 6).
- Boot volume: minimal 47–50 GB. Cukup — aplikasi ini ±0,5 GB kode + lampiran kantor.
- **Centang "Assign public IPv4 address"** tanpa ini kamu harus membuat tunnel lagi.
- Kunci SSH: buat sendiri dan simpan di file, atau pakai yang sudah ada. Oracle tidak
  menyimpan private key-nya.

## 3. Masuk, dan dua hal yang harus dicek dulu

```bash
ssh -i ~/kunci-oracle.pem ubuntu@<IP-PUBLIK>

# 1) apakah instance ini benar-benar punya RAM cukup? (kalau E2.1.Micro: bikin swap)
free -h
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile \
  && sudo mkswap /swapfile && sudo swapon /swapfile \
  && echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# 2) disk dan port
df -h / && (ss -tlnp | head -5)
```

Sebelum lanjut, pastikan **dua lapis** firewall mengizinkan 80/443: security list di
VCN Oracle (konsol → Networking → VCN → Security Lists) dan `ufw` di dalam VM. Salah
satunya saja cukup membuat halaman tidak terbuka, dan yang menyebalkan: errornya
"connection refused/timeout" yang tidak memberi tahu lapis mana yang menolak.

## 4. Bawa kodenya ke server

```bash
# di server — repo ini publik, jadi clone langsung bisa
sudo mkdir -p /var/www/pradana && sudo chown -R $USER:www-data /var/www/pradana
sudo chmod 775 /var/www/pradana
cd /var/www/pradana
git clone https://github.com/a-ali-wafa/Pradana.git .
```

`git clone` lebih disarankan daripada scp: kalau nanti perlu tahu "versi apa yang sedang
hidup di server", jawabannya ada di `git log`, bukan di ingatan.

Kloning **sebelum** skrip provisi (urutan di atas), karena skrip itu hanya menyetel
kepemilikan folder dan tidak menghapus isinya — dan `git` menolak kloning ke folder yang
sudah berisi.

## 5. Provisi server, lalu pasang aplikasinya

```bash
cd /var/www/pradana
sudo bash bin/sediakan-server.sh --cek     # baca dulu rencananya
sudo bash bin/sediakan-server.sh           # Apache + PHP 8.2 + MariaDB + cron + composer
```

Skripnya memasang: DocumentRoot ke `public/` + `AllowOverride All`, modul
`rewrite filter deflate expires headers mime dir` (tanpa `filter`, blok kompresi di
`public/.htaccess` membuat SELURUH situs 500 — itu terjadi sungguhan 12 Okt), `upload_max_filesize=32M`/
`post_max_size=40M` di **dua** php.ini (apache2 dan cli), `date.timezone=Asia/Jakarta`,
database + user MariaDB khusus (kredensial acak ke `/root/pradana-db-kredensial.txt`,
0600, tidak pernah lewat baris perintah), `/etc/cron.d/pradana` untuk `schedule:run`,
dan Composer (installer resminya diverifikasi SHA-384 dulu).

Lanjut ke aplikasi. **Semua perintah artisan berjalan sebagai `www-data`**, bukan sebagai
user SSH — skrip provisi men-`chown` seluruh folder ke `www-data:www-data`, dan itu
sengaja satu penulis: `storage/logs`, `storage/framework/`, lampiran yang diunggah lewat
browser, dump `db-backup/`, dan migrasi yang dijalankan dari terminal harus dimiliki user
yang sama. Kalau dicampur, gejalanya bukan pesan yang jelas — login jalan tapi unggah
lampiran menjawab 500, atau `php artisan migrate` mati dengan "Permission denied" di
langkah paling menentukan. `env HOME=/tmp` ikut karena `www-data` tidak punya folder home
dan Composer menulis ke `$HOME`.

```bash
P='sudo -u www-data env HOME=/tmp bash -c'   # dipanggil di bawah, tinggal salin

$P 'cd /var/www/pradana && cp .env.example .env && php artisan key:generate'
sudo -u www-data nano /var/www/pradana/.env  # isi nilai di bawah, lalu simpan
$P 'cd /var/www/pradana && bash bin/deploy.sh'
```

Isi `.env` (dari `/root/pradana-db-kredensial.txt`):

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<IP publik VM>     # atau https://<domain kantor> kalau sudah ada
APP_TIMEZONE=Asia/Jakarta         # jangan dibiarkan UTC — Berita Acara jadi bertanggal salah
DB_HOST=127.0.0.1
DB_DATABASE=pradana
DB_USERNAME=pradana
DB_PASSWORD=<di berkas kredensial itu>
MYSQLDUMP_PATH=/usr/bin/mysqldump
TRUSTED_PROXIES=                  # kosong kecuali ada proxy di depan VM ini
```

`deploy.sh` menolak jalan kalau `.env` masih `APP_DEBUG=true` atau `APP_KEY` kosong, lalu
memangkas stub Google, menjalankan migrasi, dan membangun cache. Setelah itu:

```bash
sudo -u www-data env HOME=/tmp bash -c 'cd /var/www/pradana && php artisan arsip:akun-pertama <email-untuk-demo> --nama="Ali"'
```

PIN 8 digit diminta di terminal — **jangan pakai nilai dev `12345678`** yang tertulis di
dokumen ini; VM-nya punya IP publik.

## 6. Setelah hidup: dua hal yang bikin Oracle bukan buntu

**Reklamasi idle.** Instance Always Free boleh direklamasi setelah >7 hari dengan
CPU <20% (p95) DAN network <20% DAN memory <20%. VM demo yang jarang dibuka memenuhi
ketiga syarat itu. Yang menahan risikonya bukan pura-pura sibuk, tapi:

1. **cadangan keluar VM** — `php artisan arsip:backup-db` menghasilkan dump di
   `storage/app/private/db-backup` (di dalam VM, jadi tidak menolong kalau VM-nya hilang).
   Salin keluar: `scp ubuntu@<IP>:/var/www/pradana/storage/app/private/db-backup/*.sql ./`,
   atau aktifkan `GOOGLE_DRIVE_*` + `arsip:sinkron-ke-drive` (butuh Service Account JSON
   dan folder Drive yang sudah di-share ke email service account — itu pekerjaan 10 menit).
2. **build ulang harus murah.** Kuncinya: `.env` disimpan di tempat aman (bukan hanya di
   VM), `APP_KEY` jangan didaur ulang dari laptop, dan dump-nya harus terbukti bisa
   dipulihkan. Dengan ketiganya, instance baru = ±30 menit kerja, bukan kehilangan data.

**Backup volume Oracle.** Always Free memberi 5 backup volume. Jadwalkan satu untuk boot
volume kalau kantor jadi memakai ini — murah, dan menutup kegagalan yang tidak
dijangkau `mysqldump` (mis. lampiran di disk).

## 7. HTTPS, dinyatakan apa adanya

- Tanpa domain, tidak ada sertifikat yang wajar. `http://<IP>` untuk demo itu pilihan yang
  jujur, dengan konsekuensinya: PIN dan cookie berjalan dalam teks biasa di jaringan yang
  bukan jaringanmu. Jangan pakai data kantor, jangan pakai PIN yang ada di dokumen.
- Ada domain (punya sendiri atau nanti punya kantor): pasang `certbot` + Apache SSL, lalu
  `APP_URL=https://…` + `SESSION_SECURE_COOKIE=true`. Ini keputusan S14 yang masih
  menunggu user.
- Ada Cloudflare di depan (proxy atau Tunnel): **isi `TRUSTED_PROXIES`** dengan alamat
  yang dipercaya. Ini bukan teori — terukur 21 Okt, tanpa itu halaman `https://` memanggil
  aset `http://` dan browser memblokirnya: **layar tanpa gaya**, dan semua pengunjung
  tampak memakai satu IP sehingga throttle login (5/menit per email+IP) bisa mengunci
  demonstrasi itu sendiri.

## 8. Bukti selesai (jangan percaya "deploy sukses" sebelum ini)

Checklist lengkap ada di `docs/kesiapan-deploy.md` bagian 7. Lima yang paling sering
dilewat orang dan paling mahal:

1. `curl -I http://<IP>/login` → 200, dan `X-Robots-Tag: noindex...` ikut menempel.
2. `curl -s http://<IP>/.env | head -3` → **harus kosong/404**. Ini satu-satunya bukti
   docroot benar; membaca konfigurasi Apache bukan bukti.
3. Upload sungguhan satu berkas ±20 MB lewat UI. Kalau `post_max_size` masih 8M di sisi
   FPM, gejalanya "tombol Unggah diam" — dan hanya unggahan nyata yang membuktikannya.
4. `sudo -u www-data php artisan arsip:backup-db` lalu pulihkan ke skema kosong dan
   bandingkan jumlah baris per tabel (Lampiran A.6 `docs/manual-pemakaian.md`).
5. `grep CRON /var/log/syslog | tail` dan `tail /var/log/pradana-cron.log` besok pagi —
   membuktikan `schedule:run` benar-benar dijalankan, bukan hanya dipasang.
