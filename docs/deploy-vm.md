# Deploy PRADANA di VM gratis — Azure for Students atau Oracle Always Free

Ditulis 21 Okt 2026, dipakai kembali 26 Okt untuk jalur tanpa kartu. Halaman ini bagian dari
`docs/kesiapan-deploy.md`: vonis kesiapannya di sana, langkah pemasangannya di sini, dan
cara memasang aplikasinya (`bin/deploy.sh`) ada di `docs/deploy.md`.

**Kenapa dua penyedia di satu halaman.** Setelah langkah "masuk lewat SSH" semuanya identik:
Ubuntu 24.04, `bin/sediakan-server.sh`, `.env`, `bin/deploy.sh`, bukti. Yang berbeda cuma tiga
hal: **cara daftar** (Azure tidak butuh kartu, Oracle butuh), **api firewall di luar VM** (NSG
vs security list), dan **apa yang bikin VM-nya hilang** (kredit habis vs reklamasi idle).
Memecah halaman ini jadi dua dokumen berarti menyalin langkah yang sama dua kali — dan yang
disalin selalu melenceng.

**Pilihan yang diambil untuk PRADANA hari ini: Azure for Students**, karena satu-satunya
syaratmu yang tidak bisa dipenuhi Oracle adalah kartu kredit/debit. Kalau nanti kamu punya
kartu (atau kantor yang punya), bagian 1B tetap di sini dan langkah sisanya tidak berubah.

**Status jujur:** `bin/sediakan-server.sh` belum pernah dijalankan di Ubuntu, dan jalur Azure
belum pernah dijalankan sama sekali. Yang sudah dibuktikan di mesin pengembang: sintaks bersih
(`bash -n`), isi konfigurasi Apache yang dihasilkan benar (`DocumentRoot` menunjuk
`public/`, `${APACHE_LOG_DIR}` tidak kena ekspansi shell), baris cron terbentuk dengan
kolom user yang benar, dan mode `--cek` menampilkan rencananya seutuhnya tanpa menulis
apa pun. Sisanya baru terbukti saat dijalankan sekali di VM — dan bagian 8 di bawah
adalah cara membuktikannya.

## 0. Yang kamu butuhkan sebelum mulai

| | **A. Azure for Students** | **B. Oracle Cloud Always Free** |
|---|---|---|
| Kartu kredit/debit | **Tidak perlu** | Perlu, untuk verifikasi identitas (tidak ditagih selama Always Free) |
| Bukti yang diminta | Status mahasiswa: email kampus, atau verifikasi manual (KTM / surat keterangan aktif kuliah) lewat SheerID | Identitas + kartu; region asal tidak bisa diganti setelah akun jadi |
| Yang kamu dapat | $100 kredit, dipakai dalam 12 bulan; langganan tidak naik ke berbayar otomatis — kredit habis = layanan berhenti | Always Free: VM ARM sampai 4 OCPU/24 GB (dipangkas ke 2 OCPU/12 GB pertengahan 2026), 200 GB block storage |
| Cara matinya | Kredit habis atau 12 bulan lewat → VM berhenti; perpanjang tiap tahun selama masih mahasiswa | **Reklamasi idle**: instance Always Free boleh diambil kembali setelah >7 hari nyaris tidak dipakai (bagian 6) |
| Region terdekat | `Southeast Asia` (Singapura) atau `Central US`/`East US 2` — dan di sinilah kuota VM sering jadi soal (bagian 1A) | `ap-batam-1` (Indonesia North, Batam); Oracle tidak punya region Jakarta |
| Waktu | ±1 jam | ±1–2 jam, sebagian besar menunggu provisioning |

Satu keputusan lain yang tetap milikmu: **HTTPS atau tidak** (bagian 7). Untuk demo tugas akhir
tanpa domain, `http://` polos itu cukup dan konsekuensinya tertulis di sana — jangan dibuat-buat.

## 1A. Daftar Azure for Students (tanpa kartu)

1. `https://azure.microsoft.com/free/students` → masuk dengan akun Microsoft, verifikasi status
   mahasiswa (email kampus paling gampang; kalau tidak diterima, unggah surat keterangan aktif
   kuliah). Ini akun mahasiswa, bukan trial biasa — jangan daftar lewat halaman "Azure free
   account" kalau kamu memang mahasiswa, karena halaman itu menuntut kartu.
2. Buka **Cost Management + Billing → Credits** dan catat dua angka: sisa kredit dan tanggal
   kedaluwarsa. Semua yang di bawah ini harus selesai sebelum kreditnya habis.
3. Buat **Resource group** di region yang kamu pakai untuk VM (mis. `East US 2`).

**Rintangan yang paling sering dialami akun mahasiswa, dan jalan keluarnya.** Banyak yang
melapor semua ukuran VM tampil `NotAvailableForSubscription` / error kuota, termasuk di
regional yang kelihatan bebas. Yang terbukti menyelesaikan itu di thread-thread 2026:

- **Security type** pilih **Standard**, jangan "Trusted Launch" (default formulir) — mode
  Trusted Launch memfilter ukuran kecil keluar dari daftar.
- **Availability zones** dimatikan.
- Coba region `Central US` atau `East US 2` untuk `Standard_B1s`; kalau `Southeast Asia`
  menolak, itu kuota regional, bukan salah konfigurasi kamu.
- Kalau masih nol: kuota VM langganan itu memang 0 — jalurnya adalah **Request quota** di
  portal (permintaan kuota bisa diterima dalam hitungan jam untuk akun mahasiswa), bukan
  membuat langganan kedua.

Angka "750 jam B1s gratis tiap bulan" yang sering dikutip itu punya halaman sendiri di
dokumentasi Azure dan **tidak otomatis berlaku di langganan mahasiswa**. Jangan mengambil
keputusan desain di atas angka itu sebelum kamu melihatnya sendiri di **Subscriptions →
Resource usage + quota** di akunmu; kalau tidak ada, VM-nya tetap dibayar dari $100 kredit
(±sebulan penuh B1s menyala memakan sekitar sepersepuluh kredit — angkanya baca dari estimasi
di portal, bukan dari dokumen ini).

## 1B. Daftar Oracle, dan dua hal yang biasa gagal

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

## 2. Buat VM-nya

Yang sama di kedua penyedia: image **Ubuntu 24.04 LTS**, user default **`ubuntu`**, kunci SSH
yang private-nya tidak pernah disimpan penyedia, dan port 22/80/443 terbuka (bagian 3).
PHP 8.3 bawaan Ubuntu tidak dipakai — `bin/sediakan-server.sh` memasang **8.2**, versi yang
sama dengan mesin pengembang dan CI, supaya tidak menguji hal yang belum pernah diuji.

**Azure:** Size `Standard_B1s` (1 vCPU / 1 GB) adalah yang paling mungkin lolos kuota
mahasiswa; `B2s` (4 GB) kalau kuota mengizinkan — dan dengan 1 GB, swap di bagian 3 bukan
saran, itu prasyarat. Disk OS 30–32 GB cukup (aplikasi ±0,5 GB kode + lampiran kantor).
"Inbound ports" biarkan default 22 + **tambahkan 80 dan 443 lewat Network Security Group
setelah VM jadi** — formulir cepat Azure sering hanya membuka 22.

**Oracle:** Shape `VM.Standard.A1.Flex`, 1 OCPU / 6 GB sudah cukup untuk kantor kecil, 2/12
kalau memang bebas (ini juga yang menahan reklamasi — bagian 6). Boot volume minimal
47–50 GB. **Centang "Assign public IPv4 address"**, tanpa ini kamu harus membuat tunnel lagi.

## 3. Masuk, dan tiga hal yang harus dicek dulu

```bash
ssh -i ~/kunci-vm.pem ubuntu@<IP-PUBLIK>

# 1) RAM cukup? (B1s dan E2.1.Micro = 1 GB: bikin swap, jangan lewati)
free -h
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile \
  && sudo mkswap /swapfile && sudo swapon /swapfile \
  && echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# 2) disk dan port yang listen
df -h / && (ss -tlnp | head -5)

# 3) zona waktu OS — aplikasi memakai jam dinding kantor, tapi cron mengikuti OS
timedatectl | head -3
```

Sebelum lanjut, pastikan **dua lapis** firewall mengizinkan 80/443: lapis luar di konsol
(**NSG inbound rules** kalau Azure, **security list di VCN** kalau Oracle) dan `ufw` di dalam
VM. Salah satunya saja sudah cukup membuat halaman tidak terbuka, dan yang menyebalkan:
errornya "connection refused/timeout" tanpa menyebut lapis mana yang menolak.

Di Azure masih ada satu lapis yang bisa menipu: **public IP dasar ikut lepas saat VM
di-deallocate**, jadi IP yang kamu catat untuk demo bisa berubah sesudah kamu mematikan VM.
Untuk PRADANA: biarkan VM menyala (arsip kantor kecil tidak butuh daya besar), atau pasang
domain sendiri + `A record` yang kamu perbarui (S14 tetap keputusanmu, lihat bagian 7).

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
sudah berisi. Skripnya sendiri menolak folder yang berisi **sesuatu yang bukan** checkout
PRADANA (dikenali dari adanya `artisan` + `composer.json`), supaya tidak ada kerja orang
lain yang tertimpa.

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
sudo -u www-data env HOME=/tmp nano /var/www/pradana/.env  # isi nilai di bawah, lalu simpan
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
AGENDA_BATAS_BARIS=800        # B1s 1 GB; VM 4 GB+ boleh 2000 (bawaan)
TRUSTED_PROXIES=                  # kosong kecuali ada proxy di depan VM ini
```

`deploy.sh` menolak jalan kalau `.env` masih `APP_DEBUG=true` atau `APP_KEY` kosong, lalu
memangkas stub Google, menjalankan migrasi, dan membangun cache. Setelah itu:

```bash
sudo -u www-data env HOME=/tmp bash -c 'cd /var/www/pradana && php artisan arsip:akun-pertama <email-untuk-demo> --nama="Ali"'
```

PIN 8 digit diminta di terminal — **jangan pakai nilai dev `12345678`** yang tertulis di
dokumen ini; VM-nya punya IP publik.

## 6. Setelah hidup: yang bikin VM gratis bukan buntu

Kedua penyedia punya cara mati sendiri, dan keduanya dilawan dengan kebiasaan yang sama:

- **Oracle — reklamasi idle.** Instance Always Free boleh direklamasi setelah >7 hari dengan
  CPU <20% (p95) DAN network <20% DAN memory <20%. VM demo yang jarang dibuka memenuhi
  ketiga syarat itu.
- **Azure — kredit, bukan idle.** $100 habis atau 12 bulan lewat = layanan berhenti; dan di
  akun mahasiswa tidak ada tagihan yang menagalami itu, jadi matinya diam-diam, bukan lewat
  email "silakan perbaiki metode pembayaran". Cek sisa kredit sebulan sekali, dan ingat bahwa
  public IP dasar ikut lepas saat VM di-deallocate (bagian 3). Perpanjang langganan mahasiswa
  tiap tahun selama kamu masih aktif kuliah.

Lawannya sama untuk keduanya, dan keduanya bukan hal yang bisa dikerjakan skrip:

1. **cadangan keluar VM** — `php artisan arsip:backup-db` menghasilkan dump di
   `storage/app/private/db-backup` (di dalam VM, jadi tidak menolong kalau VM-nya hilang).
   Salin keluar: `scp ubuntu@<IP>:/var/www/pradana/storage/app/private/db-backup/*.sql ./`,
   atau aktifkan `GOOGLE_DRIVE_*` + `arsip:sinkron-ke-drive` (butuh Service Account JSON
   dan folder Drive yang sudah di-share ke email service account — itu pekerjaan 10 menit).
2. **build ulang harus murah.** Kuncinya: `.env` disimpan di tempat aman (bukan hanya di
   VM), `APP_KEY` jangan didaur ulang dari laptop, dan dump-nya harus terbukti bisa
   dipulihkan. Dengan ketiganya, instance baru = ±30 menit kerja, bukan kehilangan data.
3. **lampiran juga keluar.** Backup DB tidak menyalakan file di `storage/app/private/arsip`;
   satu-satunya yang menutup itu hari ini adalah sinkron Drive (L-01: Drive = cadangan, bukan
   tempat utama). Kalau demo sudah mendekati sidang, salin foldernya dengan `scp -r` juga.

**Backup volume Oracle.** Always Free memberi 5 backup volume. Jadwalkan satu untuk boot
volume kalau kantor jadi memakai ini — murah, dan menutup kegagalan yang tidak
dijangkau `mysqldump` (mis. lampiran di disk). Azure punya fitur yang sama (backup VM /
snapshot disk) tapi dihitung dari kredit yang sama.

## 7. HTTPS, dinyatakan apa adanya

- Tanpa domain, tidak ada sertifikat yang wajar. `http://<IP>` untuk demo itu pilihan yang
  jujur, dengan konsekuensinya: PIN dan cookie berjalan dalam teks biasa di jaringan yang
  bukan jaringanmu. Jangan pakai data kantor, jangan pakai PIN yang ada di dokumen.
- Domain: cek dulu apa yang benar-benar kamu punya (GitHub Student Pack pernah mencantumkan
  domain gratis satu tahun — baca daftar offer di akunmu, jangan percaya kalimat ini). Kalau
  ada domain, pasang `certbot` + Apache SSL, lalu `APP_URL=https://…` +
  `SESSION_SECURE_COOKIE=true`. Ini keputusan S14 yang masih menunggu user.
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

## 9. Urutan kerja untuk user (yang bisa dikerjakan cuma kamu)

Bagian 0–8 di atas adalah *penjelasan*; ini daftar yang tinggal dicentang, berurutan, dengan
perkiraan waktu. Kolom "kalau macet" menunjuk bagian yang menyimpan alasannya — jangan
mencoba menebak di portal.

### Tahap 0 — enam keputusan (±15 menit, tidak perlu portal)

| # | Keputusan | Pilihanku (default kalau kamu hanya bilang "jalan") | Kalau kamu pilih lain |
|---|---|---|---|
| D1 | Penyedia | **Azure for Students** (tanpa kartu). Oracle cuma kalau nanti ada kartu | Oracle → bagian 1B; PC kantor + Tunnel → `docs/kesiapan-deploy.md` §5.B |
| D2 | Region + ukuran VM | `Central US` atau `East US 2`, `Standard_B1s` (1 GB). `B2s` (4 GB) kalau kuota memberi — dan kalau B2s tidak ada, B1s + swap tetap jalan | Region jauh = latensi demo lebih lambat; bukan kegagalan |
| D3 | HTTPS untuk demo | **Tidak dulu** — `http://<IP>` dengan data contoh, dinyatakan apa adanya di laporan | Butuh domain (bagian 7); domain kantor = keputusan S14 yang bisa menyusul |
| D4 | Isi database demo | **3–5 surat diketik lewat UI saat demo**, bukan dump dari laptop | Dump dev berisi akun dev ber-PIN `12345678` (dokumen ini menyebutnya) — itu tidak boleh berdiri di IP publik |
| D5 | Email + PIN akun admin | Email yang kamu pegang sendiri; PIN 8 digit baru, bukan nilai dev | Kalau kamu nanti serahkan ke desa, PIN itu harus di-`arsip:reset-pin` — jangan pakai PIN yang pernah dikirim di chat |
| D6 | Cadangan keluar VM | **`scp` dump ke laptop** setiap habis demo (Rp 0, tidak perlu apa-apa lagi) | Google Drive = butuh Service Account JSON + folder yang di-share (10 menit, dan itu pekerjaan tambahan yang tidak menahan demo) |

Dua hal yang **tidak** perlu kamu putuskan sekarang dan jangan menahan deploy: kop resmi desa
(F3) dan klasifikasi resmi kantor (D4 register) — keduanya isi, bukan pemasangan.

### Tahap 1 — akun Azure (±10 menit + menunggu verifikasi)

- [ ] Buka `https://azure.microsoft.com/free/students`, masuk dengan akun Microsoft-mu (buat
      satu kalau belum punya; **jangan** pakai akun kampus yang nanti kamu tinggalkan kalau
      kamu masih bisa pegang akun pribadi).
- [ ] Verifikasi status mahasiswa: email kampus (`@student.um.ac.id`) paling cepat; alternatifnya
      unggah surat keterangan aktif kuliah / KTM.
- [ ] **Jangan** lewat halaman "Azure free account" biasa — di situ kartu ditanya.
- [ ] Setelah aktif: **Cost Management + Billing → Credits** → catat sisa kredit + tanggal
      kedaluwarsa. Kalau angka ini tidak pernah dilihat, kamu akan mengira VM mati karena aplikasi.
- [ ] Buat resource group, region sama dengan yang kamu pakai untuk VM nanti.

Kalau verifikasi ditolak: itu bukan soal aplikasinya; ulangi dengan dokumen (surat keterangan
aktif), jangan buat akun kedua.

### Tahap 2 — buat VM (±15 menit klik + 5 menit provisioning)

Di portal, Virtual Machines → Create. Nilai yang harus kamu **ubah** dari default, sisanya biarkan:

- [ ] Image: **Ubuntu Server 24.04 LTS** (bukan 22.04, bukan "minimal").
- [ ] Size: `Standard_B1s` — kalau daftar kosong atau muncul `NotAvailableForSubscription`,
      lihat bagian 1A: **Security type = Standard** (default-nya Trusted Launch, dan itu yang
      menyembunyikan size kecil), availability **zones mati**, ganti region.
- [ ] Username: `ubuntu`, Authentication type: **SSH public key**, "SSH key source: Generate new"
      → **Download private key file** sebelum menekan Create. File itu tidak bisa diunduh dua kali.
- [ ] Inbound rules: aktifkan **HTTP (80)** dan **HTTPS (443)** selain SSH 22.
- [ ] OS disk: 30 GB cukup. Public IP: biarkan default (catat alamatnya sesudah jadi).
- [ ] Review + create. Tunggu sampai "Deployment completed".

### Tahap 3 — masuk dan siapkan mesin (±10 menit)

- [ ] `ssh -i <kunci>.pem ubuntu@<IP>` — kalau timeout, cek **NSG inbound** (lapis luar) dulu,
      baru `ufw` (bagian 3). Dua lapis ini menghasilkan error yang sama.
- [ ] `free -h` → kalau 1 GB, jalankan tiga baris swap di bagian 3 (jangan dilewati; composer
      dan dompdf adalah pemakan RAM).
- [ ] `timedatectl` → zona bukan UTC juga tidak apa untuk cron, tapi `APP_TIMEZONE` di `.env`
      **wajib** `Asia/Jakarta` (berkas itu yang menentukan nomor Berita Acara).

### Tahap 4 — kodenya masuk (±5 menit)

- [ ] `sudo mkdir -p /var/www/pradana && sudo chown -R $USER:www-data /var/www/pradana && sudo chmod 775 /var/www/pradana`
- [ ] `cd /var/www/pradana && git clone https://github.com/a-ali-wafa/Pradana.git .`
- [ ] `sudo bash bin/sediakan-server.sh --cek` → **baca dulu**, harus mengeluarkan ±23 tindakan
      bertanda `[cek]` dan tidak ada satu pun berkas yang ditulis.

### Tahap 5 — provisi + pasang aplikasinya (±25 menit, sebagian besar menunggu apt)

- [ ] `sudo bash bin/sediakan-server.sh` → di akhir ia mencetak kredensial DB ada di
      `/root/pradana-db-kredensial.txt` (0600, milik root). Baca dengan
      `sudo cat /root/pradana-db-kredensial.txt`; **jangan** salin isinya ke chat, repo, atau laporan.
- [ ] `.env` (bagian 5): `sudo -u www-data env HOME=/tmp bash -c 'cd /var/www/pradana && cp .env.example .env && php artisan key:generate'`
      lalu `sudo -u www-data env HOME=/tmp nano /var/www/pradana/.env` — isi `APP_ENV=production`,
      `APP_DEBUG=false`, `APP_URL=http://<IP>`, `APP_TIMEZONE=Asia/Jakarta`, DB dari berkas tadi,
      `MYSQLDUMP_PATH=/usr/bin/mysqldump`, `AGENDA_BATAS_BARIS=800`, `TRUSTED_PROXIES` **kosong**.
- [ ] `sudo -u www-data env HOME=/tmp bash -c 'cd /var/www/pradana && bash bin/deploy.sh'`
- [ ] `sudo -u www-data env HOME=/tmp bash -c 'cd /var/www/pradana && php artisan arsip:akun-pertama <email> --nama="<nama>"'`
      → PIN 8 digit diminta di terminal (D5).

Semua perintah artisan sebagai `www-data` — bukan selera, alasannya bagian 5. Menjalankan
`migrate` sebagai user SSH mati dengan "Permission denied".

### Tahap 6 — bukti (±20 menit) — jangan lanjut ke demo sebelum lima ini lewat

Jalankan bagian 8. Tambah dua langkah yang khusus untuk demo:

- [ ] Buka `http://<IP>/login` dari **HP-mu** (jaringan lain, bukan laptop) → form muncul,
      CSS terpasang, Console browser tidak merah.
- [ ] Isi 3–5 surat lewat UI + unggah satu lampiran ±20 MB (D4 + bukti nomor 3).

### Tahap 7 — sesudah demo (±5 menit, jangan dilewat)

- [ ] `sudo -u www-data env HOME=/tmp bash -c 'cd /var/www/pradana && php artisan arsip:backup-db'`
      lalu `scp ubuntu@<IP>:/var/www/pradana/storage/app/private/db-backup/*.sql ./` (D6).
- [ ] Kalau VM akan dibiarkan menganggur >1 minggu: matikan saja (`deallocate`) dan catat IP-nya —
      public IP dasar ikut lepas saat deallocate, jadi IP demo bisa berubah saat dinyalakan lagi.
- [ ] Simpan catatan kecil di `docs/` (atau laporan tugas akhir): tanggal, IP, versi commit
      (`git rev-parse --short HEAD`), dan apa yang kamu ubah. Ini yang membuat cerita "sistem ini
      pernah jalan di server" bisa dipertanggungjawabkan, bukan cuma diklaim.

### Kalau macet

| Gejala | Penyebab yang paling mungkin | Baca |
|---|---|---|
| Semua size VM `NotAvailableForSubscription` | Trusted Launch / zones / kuota regional | 1A |
| `ssh` timeout | NSG (lapis luar) atau `ufw` | 3 |
| Halaman tidak terbuka, padahal SSH bisa | cuma satu lapis firewall yang dibuka | 3 |
| Layar login muncul tapi tanpa gaya | `TRUSTED_PROXIES` kosong di belakang proxy — atau memang belum ada HTTPS | 7 |
| Tombol Unggah diam | `post_max_size` di sisi web belum 26M+ | 5 |
| `migrate` → Permission denied | artisan dijalankan sebagai user SSH, bukan `www-data` | 5 |
| 500 saat deploy di Apache | `.htaccess` + modul `filter` tidak ada | 5 |
| Aplikasi mati total, DB tidak bisa dibuat | kredensial DB belum dibaca dari `/root/pradana-db-kredensial.txt` | 5 |
