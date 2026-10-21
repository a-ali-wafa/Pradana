# Kesiapan deploy PRADANA — audit 21 Okt 2026

Untuk user dan untuk siapa pun yang akan memasang. Beda dengan `docs/deploy.md`
(cara memasang) dan `docs/manual-pemakaian.md` (cara memakai): file ini menjawab
**"sudah siap atau belum, dan apa yang kurang"**, dengan angka dari run hari ini.

## 0. Vonis

**Kodenya siap. Deploy-nya belum — dan bukan karena aplikasinya kurang fitur.**

Yang siap diukur dan lulus hari ini: 275 tes / 0 skipped, Pint 194 file, Larastan
level 5 bersih, helper JS 22 pemeriksaan, skema dev di posisi migrasi terakhir.

Yang menahan deploy bukan pekerjaan kode, tapi empat hal yang hanya bisa datang dari
luar: (1) server + kredensial, (2) nama domain/HTTPS (keputusan S14 yang belum jatuh),
(3) bahan kantor untuk kop resmi desa (F3) dan klasifikasi resmi (D4), (4) satu
bentrok yang baru kelihatan hari ini — batas 25 MB per lampiran (L-13) tidak bisa
hidup di hosting gratis mana pun yang saya temukan, lihat bagian 3.3.

**Konteks yang mengubah rekomendasi (kata user, 21 Okt):** pihak desa belum
menanggapi, jadi deployment tahap ini melayani **kebutuhan akademik lebih dulu (HKI +
tugas akhir)**. Anggarannya nol, dan kompromi infrastruktur/keamanan diterima **sebatas
yang tidak menambah biaya** — dengan syarat praktik dasar yang gratis tetap jalan
(bagian 4.3) dan sistem tidak boleh jadi sulit dipindah ke infrastruktur yang lebih
aman ketika desa benar-benar pakai (bagian 4.4). Karena datanya masih data contoh,
keberatan "surat kantor nyangkut di server luar negeri" belum berlaku di tahap ini —
itu alasan kenapa VM gratis naik peringkat, dan sekaligus alasan kenapa dia tidak boleh
dipakai untuk data kantor sebelum S14 + cadangan keluar selesai.

## 1. Yang dibuktikan hari ini (dijalankan, bukan dibaca)

| Gerbang | Perintah | Hasil |
|---|---|---|
| Suite | `php artisan test` | **275 passed / 0 skipped, 1.549 assertion** (±85 s) dengan MariaDB hidup |
| Gaya kode | `vendor/bin/pint --test` | **194 file, bersih** (193 sebelum + 1 skrip baru hari ini) |
| Analisis statis | `vendor/bin/phpstan analyse` | **[OK] No errors** (level 5, baseline 5 entri tidak bertambah) |
| Helper JS | `node bin/uji-pradana-arsip.js` | semua jalur terverifikasi |
| Skema dev | `php artisan migrate:status` | 16 migrasi, semuanya `Ran` — tidak ada yang tertinggal |
| Cadangan DB nyata | `php artisan arsip:backup-db` | dijalankan 10 Okt di mesin ini (26,6 KB dari `pradana`) |
| **Dipakai lewat Apache sungguhan** | conf uji `httpd -f .../httpd.conf` (port 8080, DocumentRoot `public/`) | `/login` 200; `/.env`, `/../.env`, `/storage/app/private/arsip`, `/vendor/autoload.php`, `/.git/HEAD` semuanya **404**; `robots.txt` = `Disallow: /`; kompresi terukur **232.803 → 31.248 B**; `Cache-Control: max-age=31536000`; header anti-index ikut menempel di halaman 404 |
| **Dibuka dari internet** | `cloudflared tunnel --url http://127.0.0.1:8080` (Quick Tunnel) | `https://<empat-kata>.trycloudflare.com/login` = **200** dan `/.env` = 404 lewat URL publik; tanpa `TRUSTED_PROXIES` halaman datang dengan **4 aset `http://` di dalam halaman `https://`** (mixed content → layar tanpa gaya), dengan `TRUSTED_PROXIES=127.0.0.1` keempatnya `https://` — dibuktikan dua arah, `.env` sesudahnya dikembalikan (0 baris tersisa) |

Catatan yang harus dibaca bareng angka di atas: **275 itu jumlah di laptop ini.**
Checkout bersih masih 259 tes — `IkonSubsetTest` (7) dan `HtaccessApacheTest` (9)
sengaja tidak di-commit atas permintaan user (aturan ronde 12 Okt). Kalau tes itu
akhirnya ikut, angka ter-commit naik ke 275 / 42 class.

## 2. Yang menghambat, dan siapa yang bisa menutupnya

| # | Hambatan | Bukti / angka | Status | Penutup |
|---|---|---|---|---|
| H1 | **`vendor/` tidak muat di hosting ber-inode** | 46.448 berkas terpasang, 43.561 di antaranya paket produksi, dan **36.901 hanya dari `google/apiclient-services`** (±250 stub layanan; yang dipakai aplikasi ini cuma `Drive`) | **DITUTUP hari ini** — `bin/pangkas-layanan-google.php`: 36.901 → **98 berkas** (130,0 MB → 0,5 MB), vendor 46.448 → **9.645**; dibuktikan `class_exists()` 4 kelas + `arsip:sinkron-ke-drive --dry-run` jalan + suite penuh | — |
| H2 | Belum ada server/kredensial | `bin/deploy.sh` butuh SSH + `composer` + `mysqldump`; tidak ada satu pun host target yang masih hidup | **BUKA** | user (pilih bagian 4) |
| H3 | HTTPS & domain belum diputuskan (S14) | `SESSION_SECURE_COOKIE` dan `TRUSTED_PROXIES` sekarang ada di `.env.example` tapi keduanya **tidak boleh dinyalakan sebelum sertifikat terpasang** | **BUKA** | user |
| H4 | **Batas 25 MB per lampiran (L-13) vs hosting gratis** | `StoreLampiranRequest`: `max:25600` (25 MB) × 10 berkas; `upload_max_filesize`/`post_max_size` harus ≥ 26M | **BUKA hanya untuk shared hosting gratis** — yang memenuhi: A/B/C di 3.2; bagian 3.3 adalah host yang gagal | user: pindah server, atau turunkan L-13 |
| H5 | Cron belum pernah dibuktikan di mesin target | 4 jadwal di `app/Console/Kernel.php` (Drive harian, dump DB 02:10, usang mingguan, bersihkan log bulanan); semuanya mati tanpa `schedule:run` per menit | **BUKA** (bisa diuji di mesin pengembang, tapi artinya baru terbukti di server) | user + agent saat provisioning |
| H6 | Uji pulih backup di server target (X5) | jalurnya sudah ada dan sudah berjalan di laptop (`UjiPulihBackupMariaDbTest`, lulus 3,64 s di run hari ini); yang belum: dijalankan sekali di atas mesin produksi | **BUKA** — prosedur Lampiran A.6 | user + agent |
| H7 | Kop resmi desa (F3) + klasifikasi resmi kantor (D4) + nilai N auto-lock (L-19) | bukan pekerjaan kode; tidak ada bahan angkanya | **BUKA** | user |
| H8 | Engine target ≠ engine dev, kalau servernya MySQL 8 / Postgres | dev = MariaDB 10.4.32; `restrictOnDelete()` berperilaku `NO ACTION` di MariaDB; SQL yang dipakai (`LIKE ? ESCAPE '!'`, `CASE` di `ORDER BY`, `EXPLAIN`, mysqldump) diuji di MariaDB & SQLite | **DITUTUP dengan pilihan** — pasang **MariaDB/MySQL** di server (bukan Postgres), idealnya 10.x/11.x supaya identik dengan yang dites | lihat bagian 4 |

Dua hal yang ikut dirapikan hari ini karena terbukti menyesatkan, bukan kosmetik:

- **`TrustProxies` tidak bisa diset dari konfigurasi.** `$proxies` di kode itu `null`,
  jadi di belakang proxy/tunnel SEMUA petugas tampak memakai satu IP. Konsekuensinya
  nyata: kunci throttle login = email + IP (`LoginRequest::throttleKey()`, 5/menit) →
  lima salah ketik PIN oleh satu orang mengunci seluruh kantor, dan aplikasi tidak
  menyimpan kolom IP di log jadi tidak ada yang bisa menjelaskan ke petugas. Sekarang
  dibaca dari `config('security.php') → TRUSTED_PROXIES`, default tetap kosong
  (sikap aman), dan dibuktikan **dua arah**: dengan `127.0.0.1` dipercaya → `ip()`
  = `192.0.2.10` + skema `https`; dengan daftar kosong → header diabaikan, `ip()`
  = `203.0.113.9` + skema `http`.
- **Komentar CSP menyebut `onclick="pradanaConfirmHapus(...)"`**, fungsi yang sudah
  dibuang 10 Okt. Diganti hitungan yang bisa diperiksa: 10 `@push('scripts')` dan
  6 `onclick` yang masih ada di `resources/views/` — alasan `'unsafe-inline'` tetap
  berlaku, hanya rujukan kodenya yang basi.

## 3. Opsi "full gratis", diuji ke kebutuhan nyata — bukan ke brosur

### 3.1 Angka kebutuhan PRADANA (dari repo ini, hari ini)

| Kebutuhan | Nilai | Sumber |
|---|---|---|
| PHP | ≥ 8.2 (diuji di 8.2.12) | `composer.json` `platform`, AGENTS Bagian 2 |
| Database | **MySQL/MariaDB saja** | SQL khusus engine + `mysqldump` |
| Ekstensi | `pdo_mysql, mbstring, fileinfo, zip, dom, xml, openssl, curl, gd, tokenizer` | dites lewat `php -r` di `bin/deploy.sh` langkah 2 |
| Jumlah berkas produksi | **7.002** (setelah H1; sebelumnya ±46.000 terpasang) | dihitung hari ini: `vendor/` 9.645 dikurangi 2.856 berkas paket dev (dipisah lewat `composer.lock`) = 6.789, + 213 berkas `app/ public/ resources/ config/ routes/ database/ bootstrap/ bin/`. Belum dari `composer install --no-dev` sungguhan, dan SELISIHNYA baru terbukti kalau perintah itu dijalankan di mesin target |
| Unggahan per berkas | **25 MB** (post/`upload` ≥ 26M) | `StoreLampiranRequest` |
| Penjadwal | cron per menit (`schedule:run`) | 4 jadwal di `Kernel.php` |
| Akses | SSH + composer + `php artisan migrate` | `bin/deploy.sh` |
| Docroot | harus bisa menunjuk `public/` | kalau tidak, `.env` + `storage/` + `db-backup/` jadi berkas web |

### 3.2 Yang lolos dari angka kebutuhan di 3.1

Tiga jalur ini tidak melanggar satu pun syarat keras. Perbandingannya sengaja satu tabel
saja (kalau tabel kembar, yang berubah nanti cuma salah satunya — pelajaran yang sudah
dibayar di `docs/deploy.md`):

| | **A. Oracle Cloud Always Free (VM)** | **B. Quick Tunnel dari laptop ini** | **C. PC kantor + Tunnel ber-akun** |
|---|---|---|---|
| Untuk apa | server "beneran" yang bisa tetap jalan setelah sidang | **demo/HKI minggu ini** | kantor pakai sungguhan, data tidak keluar |
| Biaya | Rp 0 permanen (dengan syarat) | Rp 0, tanpa akun apa pun | Rp 0 + listrik |
| Waktu setup | 2–4 jam (install sendiri) | **±10 menit, sudah dibuktikan hari ini (5.1)** | 1–2 jam |
| URL untuk penguji | IP publik sendiri / domain sendiri | `https://<empat-kata>.trycloudflare.com` | nama tetap di domain kantor |
| Hidup 24 jam? | **ya** | tidak — mati saat proses/laptop mati | ya, selama UPS-nya hidup |
| 25 MB lampiran (L-13) | terpenuhi (php.ini sendiri) | terpenuhi (yang di sini terukur 40M) | terpenuhi |
| Cron `schedule:run` | ada (SSH + cron asli) | **tidak ada** → perintah dijadwalkan manual/di-demo | Windows Task Scheduler tiap menit |
| SSH + composer + `migrate` | ada | tidak perlu (menjalankan dari folder kerja) | perlu diatur per mesin |
| Docroot `public/` | diatur sendiri → `.env`/`storage` aman | conf Apache ini sudah benar → `/.env` terukur 404 | sama seperti B |
| Batas/risiko | 2 OCPU / 12 GB / 200 GB (Always Free; dipangkas dari 4/24 pertengahan 2026), **butuh kartu kredit** untuk daftar, kapasitas `VM.Standard.A1.Flex` bisa penuh, dan **reklamasi idle**: >7 hari CPU<20% p95 + network<20% + memory<20% boleh dihapus | URL acak berganti tiap dijalankan; bukan tempat data kantor | listrik mati paksa = **InnoDB korup** — sudah terjadi di laptop ini 10 Okt dan nyaris memakan `pradana_test`; tanpa UPS itu jadwal, bukan kemungkinan |
| Region / latensi | `ap-batam-1` (Indonesia North, Batam); Oracle tidak punya region Jakarta | lewat edge Cloudflare | di kantor, 1–5 ms |
| Catatan HTTPS | tanpa domain → sertifikat tidak wajar; http polos atau self-signed | **HTTPS nyata dari Cloudflare**, tapi lihat 5.1: `TRUSTED_PROXIES` wajib diisi, kalau tidak halaman datang **tanpa gaya** (mixed content) | HTTPS + domain kantor (S14) |

### 3.3 Kenapa "hosting PHP gratis" (InfinityFree/ByetHost/ProFreeHost) saya singkirkan

Mereka gagal di angka, bukan di kesan. Semua batasnya pernyataan staf mereka sendiri:

- **Batas 30.000 berkas (inode) per akun** — setelah H1 kita 7.002, jadi syarat ini
  lolos hari ini. **Sebelum pangkas (43.561) akunnya tidak bisa dibuat sama sekali**,
  dan gejalanya "upload gagal", bukan pesan yang menyebut inode.
- **Batas ukuran 10 MB per berkas, staf menolak menaikkannya** — aplikasi ini
  menerima lampiran sampai 25 MB (L-13, keputusan user). Ini bentrok langsung:
  mau tidak mau harus mengubah L-13 atau membuang fitur lampiran besar.
- **Tanpa SSH/CLI dan tanpa cron** (cron dimatikan sejak Agu 2023 dan belum
  dikembalikan) — tidak ada `php artisan migrate`, tidak ada `composer install`,
  tidak ada 4 jadwal di `Kernel.php`. Deploy-nya harus lewat zip + trik docroot,
  dan cadangan database jadi tidak otomatis sama sekali.
- **Docroot tidak bisa dipindah ke `public/`** — satu-satunya jalan adalah menaruh
  aplikasi di dalam folder web; kalau satu aturan rewrite salah, `.env`,
  `storage/app/private/arsip` dan `storage/app/private/db-backup` ikut tersaji
  sebagai berkas web. Untuk arsip surat kantor, itu kegagalan yang tidak bisa
  dimaafkan.
- ToS-nya: akun bisa disuspend karena tidak aktif, dan mereka **tidak menjamin
  cadangan apa pun** ("does not warrant that it backs-up any Account or User
  Content"). Kantor yang tidak mengaktifkan arsip selama sebulan adalah kasus
  normal, bukan kasus tepi.

Juga disingkirkan dengan alasan yang sama: PaaS/serverless tier gratis (Render —
sleep 15 menit + disk persisten berbayar; Koyeb — 2 GB ephemeral; Fly.io — tidak ada
lagi tier gratis; Railway — kredit trial saja; Vercel/Netlify/Cloudflare Workers —
filesystem read-only, butuh rombak total). **Supabase/Neon tidak membantu**:
komputenya tetap harus dicari dan basis datanya Postgres, sementara SQL aplikasi ini
dites di MySQL/MariaDB. 000webhost tutup Oktober 2024.

Kalau "gratis" boleh digeser jadi "murah": shared hosting Indonesia berbayar
(±Rp 12.500–25.000/bulan) biasanya sudah memenuhi docroot, cron, PHP 8.2, dan backup
mingguan — dan itu persis target K1=a yang dulu diputuskan. Bukan gratis, jadi
saya catat sebagai pembanding, bukan sebagai jawaban.

## 4. Rekomendasi untuk tahap akademik (HKI + tugas akhir)

Satu hal yang perlu diluruskan lebih dulu supaya tidak salah prioritas: **HKI tidak
menuntut server.** Yang didaftarkan itu ciptaan (kode + dokumen + tanggal), dan buktinya
adalah repo, manual, dan rekaman/penampakan sistem. Yang butuh alamat hidup adalah
**demonstrasi di tugas akhir** — dan itu boleh di mesin yang sama sekali bukan "infrastruktur
kantor". Jadi urutannya: pilih yang paling murah dalam uang DAN waktu setup DAN paling
bentuknya seperti produksi sungguhan, sambil menjaga pintu pindah tetap terbuka (4.4).
Perbandingan tiga jalurnya sudah ada di satu tempat — **bagian 3.2** — jadi tidak diulang di sini.

**Rekomendasi saya: A sebagai sasaran, B sebagai jalan keluar minggu ini.** A
menempatkan tugas akhir pada bentuk yang sebenarnya (Linux + Apache + MariaDB + SSH +
cron + `bin/deploy.sh` sungguhan), selalu hidup saat penguji membukanya, dan — yang
paling penting untuk aturan "jangan bikin sulit dipindah" — tidak memerlukan satu pun
trik di kode. Kalau pendaftaran Oracle tersendat (kartu kredit atau kapasitas shape
sering menendang mahasiswa), B tetap memberi demonstrasi nyata di layar penguji tanpa
menunggu apa pun; catatan jujurnya ada di 4.3.

**Jangan** shared hosting gratis (InfinityFree/ProFreeHost/ByetHost). Alasannya bukan
"gratis jadi jelek" — batas 30.000 berkasnya sudah dilewati sejak pemangkasan hari ini —
tapi tiga angka yang tidak bisa dinegosiasi: **10 MB per berkas** vs 25 MB yang
dijanjikan ke petugas, **tanpa cron dan tanpa SSH/CLI** vs 4 pekerjaan terjadwal +
`migrate`, dan **docroot yang tidak bisa dipindah** yang memaksa `.env` +
`storage/app/private/arsip` + `db-backup/` berdiri di dalam folder web. Yang terakhir
ini justru melanggar praktik dasar yang user minta dijaga.

Kalau "gratis" boleh digeser jadi "murah": shared hosting Indonesia berbayar
(±Rp 12.500–25.000/bulan) biasanya sudah memenuhi docroot, cron, PHP 8.2, dan backup
mingguan — persis target K1=a yang dulu diputuskan. Bukan gratis, jadi dicatat sebagai
pembanding, bukan sebagai jawaban.

### 4.3 Kompromi yang saya terima untuk tahap ini, dan apa yang menggantikannya

Ditulis apa adanya supaya tidak ada yang mengira ini kondisi siap-pakai-kantor:

| Kompromi | Kenapa masih aman di tahap akademik | Pengamanannya yang tetap jalan (Rp 0) |
|---|---|---|
| Tidak ada cron | tidak ada data kantor; cadangan tetap bisa dipicu manual | `php artisan arsip:backup-db` dijalankan manual sebelum/ sesudah demo; jalurnya sudah ada dan sudah diuji pulih (X5) |
| Data contoh, bukan surat asli | justru syarat untuk boleh demo di server gratis | `DevSeeder` menolak jalan di luar `APP_ENV=local`, jadi isinya tidak bisa ikut ke server produksi; akun demo dibuat lewat `arsip:akun-pertama` |
| kredensial di VM pihak ketiga | isinya belum ada yang punya nilai hukum | user DB khusus (bukan root), `DB_PASSWORD` hanya di `.env` (di-`.gitignore`), `mysqldump` pakai `--defaults-extra-file` 0600 yang dihapus di `finally` — kredensial tidak pernah masuk baris perintah |
| HTTP polos kalau belum ada sertifikat | login demo di mesin sendiri | begitu ada akses luar kantor: HTTPS + `SESSION_SECURE_COOKIE=true` + `TRUSTED_PROXIES` (bagian 6 sudah menyiapkan kuncinya, dan alasannya tertulis di sana) |
| Arsip di server luar negeri | tidak ada arsip nyata | `robots.txt` `Disallow: /` + `X-Robots-Tag: noindex` tetap terpasang, lampiran hanya lewat controller `middleware('auth')` (L-01), tidak ada satu pun tautan publik |

Praktik dasar yang **tidak** ikut dikompromikan: `.env` tidak di-commit, `APP_DEBUG=false`
di server (dijaga gerbang `bin/deploy.sh`), halaman error tidak mencetak `$exception`,
PIN 8 digit + throttle 5/menit, otorisasi admin di server (bukan hanya tombol tersembunyi),
dan lampiran di luar `public/`.

### 4.4 Aturan supaya tidak terjebak (ini bagian yang bikin pindah ke infra kantor nanti murah)

1. **Tidak ada satu baris pun di `app/` yang tahu aplikasi ini sedang di-host di mana.**
   Perbedaan mesin masuk lewat `.env`/`config` saja — itu yang menjaga `config:cache`
   tetap benar. Sudah berlaku; jangan dirusak dengan `if (localhost)` atau IP hard-coded.
2. **Docroot tetap `public/`.** Jangan pernah memindahkan `index.php` ke folder web dan
   menyesuaikannya — bentuk itu gampang dibuat tapi membuat `.env` dan arsip bisa
   terbaca, dan tidak ada tes yang menjaganya.
3. **Engine database tetap MySQL/MariaDB** (idealnya MariaDB, sama dengan 275 tes ini
   jalankan). Kalau suatu hari pindah Postgres, itu pekerjaan baru (`LIKE ? ESCAPE '!'`,
   `CASE` di `ORDER BY`, `restrictOnDelete`), bukan sekadar ganti driver.
4. **Cadangan harus bisa dipulihkan di mesin lain.** `arsip:backup-db` sudah begitu —
   dan itu satu-satunya alasan reklamasi instance Oracle bukan bencana: `git clone` +
   `.env` + pulihkan dump = kantor lagi, di mana pun.
5. **Semua langkah deploy tetap ada di `bin/deploy.sh`.** Kalau suatu hari terpaksa
   melakukan sesuatu "manual di cPanel", tuliskan apa yang dilakukan dan kenapa — kalau
   tidak, enam bulan lagi langkah itu hilang dan deploy berikutnya cacat diam-diam.
6. **Uji yang hanya bisa dibuktikan di Apache** (`.htaccess`, kompresi, header) jangan
   disimpulkan dari `php artisan serve` — lihat `docs/deploy.md`; laptop ini sudah punya
   resep Apache uji di port 8080.

## 5. Runbook singkat

### A. Oracle Cloud VM

1. Daftar Free Tier (butuh kartu kredit untuk verifikasi identitas, tidak ditagih
   selama Always Free). **Pilih home region baik-baik: tidak bisa diganti** —
   `ap-batam-1` paling dekat dengan Jawa, tapi cek dulu shape `VM.Standard.A1.Flex`
   tersedia untuk Always Free di sana (kapasitasnya terbatas dan tidak bisa
   dipesan lebih dulu).
2. Image: Ubuntu 24.04 (PHP 8.3 ≥ 8.2). Install `apache2 libapache2-mod-php php-mysql
   php-zip php-gd php-xml php-curl mariadb-server` — **Apache**, bukan nginx, supaya
   `public/.htaccess` yang sudah diuji dua arah 12 Okt itu tetap yang bekerja.
3. `git clone` ke `/var/www/pradana`, `cp .env.example .env`, isi blok bagian 6,
   `php artisan key:generate`, lalu `bash bin/deploy.sh`.
4. DocumentRoot → `/var/www/pradana/public`; `AllowOverride All`; cron per menit;
   `systemctl enable --now cron`.
5. Uji yang tidak bisa diotomasi (bagian 7), termasuk `arsip:backup-db` + uji pulih,
   dan nyalakan `GOOGLE_DRIVE_*` supaya cadangan keluar benar-benar ada.

### B. PC kantor + Cloudflare Tunnel

1. Apache virtual host menunjuk `public/` (resep conf-nya sudah dicatat di
   `docs/deploy.md` bagian "`.htaccess` harus diuji di Apache"), `Listen 80` lokal.
2. `cloudflared tunnel` + route DNS ke `http://localhost`, jadi HTTPS dan nama
   domain selesai tanpa IP publik/port-forward. Isi `TRUSTED_PROXIES=127.0.0.1` —
   tanpanya halaman tampil **tanpa gaya** (bukti di 5.1), dan login kantor bisa
   terkunci sendiri karena throttle membaca IP proxy.
3. Task Scheduler: `php artisan schedule:run` tiap menit; `arsip:backup-db` harian
   tetap lewat jalur itu.
4. Dump keluar mesin: `arsip:sinkron-ke-drive` (sudah ada) — di opsi B ini bukan
   pemanis, dia satu-satunya salinan kedua.

### 5.1 Demonstrasi lewat Quick Tunnel — dijalankan hari ini, bukan rencana

Ini jalur B yang paling murah untuk tugas akhir: **tanpa akun Cloudflare, tanpa domain,
tanpa port-forward**, dan tidak perlu menunggu server mana pun. Yang dibutuhkan hanya
Apache yang menunjuk `public/` (conf uji laptop ini sudah ada:
`C:\Users\asus\prd-apache-test\httpd.conf`, `Listen 127.0.0.1:8080`) dan satu berkas
biner resmi:

```bash
# alat: unduh sekali (biner resmi Cloudflare; versi terbaca 2026.10.2 di mesin ini)
curl -sL -o cloudflared.exe \
  https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe

# jalan: satu perintah, tanpa login, tanpa konfigurasi
./cloudflared.exe tunnel --url http://127.0.0.1:8080
#   -> https://<empat-kata-acak>.trycloudflare.com   (muncul di log, langsung bisa dibuka)
```

Yang terukur lewat URL publik itu, pada aplikasi yang sama seperti bagian 7:

| yang diuji | hasil |
|---|---|
| halaman `/login` lewat HTTPS luar | `200` |
| `/.env` lewat URL publik | `404` (docroot benar, kredensial tidak tersaji) |
| header anti-index ikut sampai ke luar | `x-robots-tag: noindex, nofollow, noarchive, nosnippet` + dua `X-Robots-Tag: none` tambahan dari Cloudflare (quick tunnel memang tidak boleh ter-index) |
| **`TRUSTED_PROXIES` kosong** | `asset()` menghasilkan **4× `http://`** di dalam halaman `https://` → browser memblokir sebagai mixed content: **layar tanpa gaya, persis kegagalan P0#1 yang sudah diperbaiki 10 Okt, hanya dipindahkan ke sisi proxy** |
| **`TRUSTED_PROXIES=127.0.0.1`** | 4× `https://` — halaman utuh |

Dua hal yang wajib diketahui sebelum mengandalkan jalur ini untuk sidang:

- **Quick tunnel itu sementara.** URL-nya acak dan berubah setiap dijalankan, dan
  berhenti saat proses `cloudflared` mati. Untuk demo di depan penguji itu berarti:
  yang memegang demo adalah laptopmu, bukan Cloudflare — jadi rekam juga video demo
  + tangkapan layar sebagai cadangan (dan itu sekaligus bahan lampiran HKI/tugas akhir).
- **Setop = matikan prosesnya.** Setelah selesai, pastikan tidak ada `cloudflared`
  yang masih hidup (`taskkill /F /IM cloudflared.exe`); meninggalkan tunnel hidup
  sama dengan membiarkan pintu ke Apache-mu terbuka selama listrik menyala.

Sudah diverifikasi bersih di mesin ini: tidak ada `cloudflared` tersisa, port 8080 tidak
lagi menyandang `LISTENING`, Apache milik user di port 80 tidak pernah disentuh, dan
`.env` dikembalikan seperti semula (0 baris `TRUSTED_PROXIES`).

## 6. Isi `.env` produksi (siap tempel, ganti nilai bertanda `«`)

```ini
APP_NAME=PRADANA
APP_ENV=production
APP_KEY=                      # isi lewat: php artisan key:generate (SEKALI, sebelum data masuk)
APP_DEBUG=false               # gerbang bin/deploy.sh: true di server = nama tabel + path server di layar 500
APP_URL=https://«arsip.desakantor.go.id»
APP_TIMEZONE=Asia/Jakarta     # kantor WITA/WIT: Asia/Makassar / Asia/Jayapura — jangan UTC

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=«pradana»
DB_USERNAME=«user_khusus_aplikasi»   # bukan root
DB_PASSWORD=«kata_sandi»

# HTTPS: JANGAN dinyalakan sebelum sertifikat benar-benar terpasang.
# SESSION_SECURE_COOKIE=true
# TRUSTED_PROXIES=127.0.0.1   # isi kalau ada proxy/tunnel; kosong = tidak ada yang dipercaya

LOG_CHANNEL=stack
LOG_LEVEL=warning             # `debug` di produksi menulis satu berkas besar sekali per request

FILESYSTEM_DISK=public
QUEUE_CONNECTION=sync         # L-02: tidak ada worker di server kantor
SESSION_DRIVER=file
SESSION_LIFETIME=120
SECURITY_CSP=true
AGENDA_BATAS_BARIS=2000       # naikkan hanya sesudah servernya diukur
MYSQLDUMP_PATH=mysqldump
BACKUP_SIMPAN_HARI=30

GOOGLE_DRIVE_CREDENTIALS_PATH=storage/app/google/service-account.json
GOOGLE_DRIVE_ROOT_FOLDER_ID=«id_folder_yang_sudah_di_share_ke_service_account»
```

Baris yang TIDAK boleh disalin apa adanya: `DB_USERNAME`/`DB_PASSWORD` (buat user
khusus, bukan root), `GOOGLE_DRIVE_*` (harus ada Service Account + folder yang
di-share), dan `APP_KEY` (dibangkitkan, tidak boleh disalin dari laptop — encrypt
 kolom dengan kunci yang sama berarti kuncinya ada di git).

## 7. Definisi "selesai" untuk deploy pertama

Dijalankan satu per satu, dan yang dicatat adalah hasilnya, bukan niatnya:

1. `bash bin/deploy.sh` selesai tanpa satu pun `GAGAL` (langkah 2–6 sekarang
   memeriksa versi PHP, 10 ekstensi, batas unggahan sisi CLI, ada/tidaknya
   `mysqldump`, izin tulis `storage/` + `bootstrap/cache`).
2. Buka `<APP_URL>/login`: kop + logo instansi tampil, Console browser kosong,
   tidak ada pelanggaran CSP.
3. `curl -I <APP_URL>` → `X-Robots-Tag: noindex...` ada; dan **`curl -I
   <APP_URL>/.env` serta `/../storage/` tidak mengembalikan isi berkas** — ini
   bukti docroot benar, bukan asumsi.
4. Login akun nyata (bukan seeder dev), buat satu surat masuk + unggah lampiran
   **25 MB** → berhasil; kalau PHP yang menolak, perbaiki `php.ini`, bukan kodenya.
5. Aksi destruktif diuji dengan JavaScript dimatikan (form POST sungguhan harus
   tetap bekerja, itu pekerjaan 10 Okt).
6. Cetak PDF surat keluar + Berita Acara: tanggal/nomor pakai jam kantor, bukan UTC
   (bandingkan dengan jam dinding saat itu).
7. `php artisan arsip:backup-db` lalu **pulihkan ke skema kosong dan bandingkan
   jumlah baris per tabel** (Lampiran A.6). Belum disebut cadangan sebelum ini lulus.
8. Tunggu/cek keesokan harinya: log cron membuktikan `schedule:run` benar-benar
   jalan tiap menit, bukan hanya terpasang.
9. Cetak `docs/manual-pemakaian.md` + latih petugas (L-25), serah terima akun lewat
   `arsip:akun-pertama` / `arsip:reset-pin`.

## 8. Yang butuh keputusan user (bukan kerja agent)

1. **Untuk minggu ini (demo/HKI):** boleh langsung pakai jalur B (Quick Tunnel, 5.1) atau
   saya siapkan jalur A (Oracle). B hanya perlu menjalankan dua
   perintah; A butuh **kartu kredit untuk pendaftaran** dan itu tidak bisa saya lakukan.
2. Untuk tahap kantor: A atau C (bagian 3.2). hosting PHP gratis sudah saya
   singkirkan di 3.3; kalau nanti tetap mau itu, konsekuensinya L-13 dan cron yang
   berubah — bukan deploy-nya.
3. 25 MB per lampiran (L-13): tetap, karena A/B/C semuanya menampung; batas ini hanya
   jadi soal kalau kantor pindah ke shared hosting 10 MB, dan itu keputusan sadar,
   bukan kompromi teknis yang boleh agent ambil sendiri.
4. S14: nama domain + HTTPS (wajib begitu bisa diakses dari luar jaringan kantor).
   Untuk demo, HTTPS sudah datang dari Cloudflare sendiri.
5. F3: kirim kop/template resmi desa. D4: klasifikasi resmi kantor. L-19: nilai N
   untuk auto-lock arsip.
