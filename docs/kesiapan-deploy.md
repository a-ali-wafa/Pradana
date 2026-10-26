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

**Susulan 26 Okt — satu syarat gugur, dan rekomendasinya pindah penyedia.** User tidak
punya kartu kredit/debit. Karena yang ditolak Oracle bukan aplikasinya tapi formulir
pendaftarannya, jalurnya tetap **satu VM Ubuntu dengan SSH + cron + MySQL/MariaDB**,
hanya pendaftarannya lewat **Azure for Students**: tanpa kartu, verifikasinya status
mahasiswa, $100 kredit untuk 12 bulan, dan langganan tidak naik ke berbayar otomatis —
kalau habis, yang mati adalah VM-nya, bukan dompetmu. Oracle Always Free tetap didokumentasikan
(§1B di runbook) sebagai yang paling murah dalam uang permanen kalau nanti ada kartu.
Database **tidak** dipindah ke Supabase/Postgres: itu ganti mesin, bukan ganti host —
ukurannya ada di 3.4, dan pasangan "hosting gratis + Supabase" bahkan tidak bisa koneksi
keluar di port 5432 (3.3). Tidak ada satu baris pun di `app/` yang ikut berubah.

## 1. Yang dibuktikan hari ini (dijalankan, bukan dibaca)

| Gerbang | Perintah | Hasil |
|---|---|---|
| Suite | `php artisan test` | **275 passed / 0 skipped, 1.549 assertion** (±85 s) dengan MariaDB hidup |
| Gaya kode | `vendor/bin/pint --test` | **194 file, bersih** (193 sebelum + 1 skrip baru hari ini) |
| Analisis statis | `vendor/bin/phpstan analyse` | **[OK] No errors** (level 5, baseline 5 entri tidak bertambah) |
| Helper JS | `node bin/uji-pradana-arsip.js` | semua jalur terverifikasi |
| Skema dev | `php artisan migrate:status` | 16 migrasi, semuanya `Ran` — tidak ada yang tertinggal |
| **Cache build dijalankan beneran** | `config:cache` + `route:cache` + `view:cache` + `event:cache`, lalu Apache 8080 | `/login` **200** dengan 3 aset `vendor/` + nama instansi + token CSRF di HTML; `/dashboard`, `/surat-masuk`, `/laporan`, `/aktivitas`, `/pengaturan-instansi/edit` = 302 (memang belum login). Kunci baru terbaca dari cache: `security.trusted_proxies = []`, `csp_aktif = true`, `app.timezone = Asia/Jakarta` — dan keadaan dev dikembalikan (`optimize:clear`, maintenance OFF) |
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

Dua jalur ini tidak melanggar satu pun syarat keras. Perbandingannya sengaja satu tabel
saja (kalau tabel kembar, yang berubah nanti cuma salah satunya — pelajaran yang sudah
dibayar di `docs/deploy.md`):

| | **A. VM Ubuntu gratis — Azure for Students / Oracle Always Free** | **B. PC/mini-PC kantor + Cloudflare Tunnel** |
|---|---|---|
| Untuk apa | server yang tetap jalan setelah sidang, dan bentuknya sama dengan yang nanti dipakai kantor | kantor pakai sungguhan tanpa surat keluar dari gedung |
| Biaya | Rp 0 (Azure: $100 kredit untuk 12 bulan, bisa diperpanjang selama masih mahasiswa; Oracle: Always Free permanen dengan syarat) | Rp 0 + listrik |
| Perlu kartu kredit? | **Azure for Students: tidak.** Oracle: ya, untuk verifikasi identitas | tidak |
| Waktu setup | 2–4 jam (install sendiri; `bin/sediakan-server.sh` + `docs/deploy-vm.md`) | 1–2 jam |
| URL untuk penguji | IP publik sendiri, atau domain kantor kalau sudah ada | nama tetap di domain kantor |
| Hidup 24 jam? | **ya** | ya, selama UPS-nya hidup |
| 25 MB lampiran (L-13) | terpenuhi (php.ini sendiri) | terpenuhi (di laptop ini terukur 40M) |
| Cron `schedule:run` | ada (SSH + cron asli) | Windows Task Scheduler tiap menit |
| SSH + composer + `migrate` | ada | perlu diatur per mesin |
| Docroot `public/` | diatur sendiri → `.env`/`storage` aman | conf Apache terukur: `/.env` 404 (5.1) |
| Batas/risiko | **Azure:** ukuran VM sering ditolak kuota di akun mahasiswa (semua size `NotAvailableForSubscription`) — jalan keluarnya di `docs/deploy-vm.md` 1A; kredit habis = VM berhenti; B1s cuma 1 GB (swap wajib). **Oracle:** 2 OCPU / 12 GB / 200 GB (dipangkas dari 4/24 pertengahan 2026), kapasitas `VM.Standard.A1.Flex` bisa penuh, dan **reklamasi idle**: >7 hari CPU<20% p95 + network<20% + memory<20% boleh dihapus | listrik mati paksa = **InnoDB korup** — sudah terjadi di laptop ini 10 Okt dan nyaris memakan `pradana_test`; tanpa UPS itu jadwal, bukan kemungkinan |
| Region / latensi | Azure: `Southeast Asia` (Singapura) atau `Central US`/`East US 2` (kuota size hampir selalu ada). Oracle: `ap-batam-1`, tidak ada Jakarta | di kantor, 1–5 ms |
| Catatan HTTPS | tanpa domain: sertifikat tidak wajar → http polos (demos) atau beli domain; jangan klaim "aman" tanpa sertifikat. Di Azure: public IP dasar ikut lepas saat VM di-deallocate | HTTPS + domain kantor (S14) — dan **`TRUSTED_PROXIES` wajib diisi**, lihat 5.1 |

Jalur ketiga (Quick Tunnel tanpa akun dari laptop) sengaja TIDAK dihitung sebagai opsi
deploy: ia hanya menyalurkan Apache yang sama ke internet, URL-nya acak dan mati bersama
prosesnya. Ia tetap dipakai hari ini — tapi sebagai **alat ukur**, dan itu yang dicatat
di 5.1.

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
filesystem read-only, butuh rombak total). 000webhost tutup Oktober 2024.

**Dan satu yang harus saya sebut karena ditanyakan: hosting gratis itu juga tidak bisa
dipasangkan dengan Supabase.** Bukan soal fiturnya, tapi soal port: staf InfinityFree
menyatakan koneksi keluar hanya dibuka pada **port 3306**, dan ada thread forum September
2026 berjudul "pdo_pgsql outbound connection to external PostgreSQL database fails" —
5432/6554 diblok. Jadi "aplikasi di hosting gratis + database di Supabase" tidak pernah
menjadi kombinasi yang jalan, dan Supabase kehilangan satu-satunya alasan yang membuatnya
terlihat menarik di daftar ini.

### 3.4 Kenapa database tidak saya pindahkan ke Postgres (Supabase)

Supabase secara teknis bisa dipakai Laravel (`config/database.php` sudah punya blok `pgsql`),
tapi "bisa tersambung" bukan "bisa dipakai dengan bukti yang kita punya hari ini". Angkanya
dari repo ini, bukan dari kesan:

| Yang harus dikerjakan ulang | Ukuran hari ini |
|---|---|
| Titik SQL mentah di luar tes | **7 tempat** (`DashboardController` 76 & 136, `AppServiceProvider` 94 & 98, `DaftarArsipGabungan` 92, `NomorSuratKeluarGenerator` 55, `CariArsip` 186). Mayoritas selamat di Postgres, tapi tiap satu adalah tempat perilaku bisa berbeda diam-diam — `count(*) as total` bertemu aturan Postgres yang melipat alias tak berikutip jadi huruf kecil |
| Pencarian | `LIKE ? ESCAPE '!'` di dua jalur (`CariArsip` 203 & 259, dipakai juga oleh filter `/aktivitas`). LIKE di Postgres **peka huruf**; hari ini kasus-tidak-peka datang dari collation `utf8mb4_unicode_ci` dan itu **dikunci tes** (`CariArsipMariaDbTest:110`). Perlu `ILIKE`, dan seluruh L-15 + peringkat relevansi + `<mark>` harus diuji lagi |
| Penomoran surat keluar (L-20) | `upsert` + `lockForUpdate()` di `surat_counters`. Bukti balapnya dulu dibuat dengan **dua koneksi MariaDB nyata**; di Postgres kuncian baris yang belum ada berperilaku berbeda, jadi bukti itu harus diulang |
| Skema | **11 konstruksi** yang Postgres maknai lain: `unsigned*` (tidak ada UNSIGNED di PG), `->after()` (posisi kolom tidak berarti), `longText` (→ `text`, aman), `enum()` (→ varchar + CHECK), dan `restrictOnDelete()` yang justru **bekerja sungguh-sungguh** di PG sementara AGENTS.md Bagian 2 mencatat MariaDB memperlakukannya sebagai `NO ACTION` — asumsi yang tertulis di dokumen akan berbalik |
| Cadangan & uji pulih (X5) | `arsip:backup-db` 100% rasa MySQL: `mysqldump` + `--defaults-extra-file` + pemeriksaan `CREATE DATABASE /*!32312 IF NOT EXISTS*/` + jumlah tabel lewat `information_schema`. Perlu implementasi `pg_dump`, dan `UjiPulihBackupMariaDbTest` dibangun ulang. Di Supabase, `pg_dump` harus versi mayor yang sama dengan server (galat klasik "version mismatch") dan plan gratis tidak menyediakan backup otomatis |
| Mesin tempat tes berjalan | Laptop ini hanya punya `pdo_mysql`; **tidak ada `pdo_pgsql`, tidak ada `psql`/`pg_dump`**. Artinya 16 tes engine-asli (MariaDB) jadi skip dan klaim "275 tes dijalankan di engine produksi" (Q1=b) hilang — di sidang, itu klaim yang mahal |

Ditambah empat batas plan gratis Supabase yang menabrak tujuan "demonstrasi tugas akhir":
**project gratis di-pause setelah 1 minggu tanpa aktivitas** (arsip yang desa belum pakai
justru definisi dari "tidak aktif" — penguji membuka halaman, aplikasi mati 500),
**500 MB database** dan **1 GB file storage** dengan cadangan 7 hari, **maksimal 2 project
aktif**, dan koneksi langsung hanya IPv6 — dari IPv4 harus lewat pooler (port 6554) yang mode
*transaction*-nya memecah prepared statement, jadi Laravel perlu mode session atau
emulated prepares. Untuk aplikasi yang tidak memakai RLS/Auth Supabase sama sekali (kita
login pakai PIN sendiri), semua itu dibayar tanpa mengambil satu pun fitur yang bikin
Supabase berguna.

Kesimpulannya: Supabase bukan pintu keluar dari masalah kartu kredit. Masalahnya ada di
**host aplikasi**, dan solusinya Azure for Students (§4) — yang membuat database tetap di
MariaDB, di mesin yang sama dengan 275 tes ini.

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

**Rekomendasi saya (26 Okt): A, VM Ubuntu gratis — jalur pendaftarannya Azure for Students.**
Alasannya tetap sama seperti 21 Okt: VM adalah satu-satunya bentuk yang benar-benar gratis
DAN tetap hidup saat penguji membukanya sendiri DAN bentuknya identik dengan yang nanti dipakai
kantor (Linux + Apache + MariaDB + SSH + cron + `bin/deploy.sh`) — dan yang paling penting untuk
aturan "jangan bikin sulit dipindah" (4.4): **tidak perlu satu pun trik di kode**. Yang berubah
hanya penyedianya, karena satu-satunya syarat Oracle yang tidak bisa kamu penuhi adalah kartu
kredit/debit: **Azure for Students tidak menuntut kartu** (verifikasinya status mahasiswa:
email kampus atau surat keterangan aktif kuliah), memberi $100 kredit untuk 12 bulan, dan
karena langganannya tidak naik ke berbayar secara otomatis, kegagalan keuangannya "VM berhenti",
bukan "tagihan datang". Oracle Always Free (bagian 1B) tetap terdokumentasi dan tetap lebih
murah dalam uang permanen — aktifkan itu kalau nanti ada kartu (punya sendiri atau kantor).

Langkah-langkahnya ada di satu halaman: **`docs/deploy-vm.md`** + `bin/sediakan-server.sh`.

Dua hal yang perlu kamu sediakan untuk jalur ini: verifikasi status mahasiswa, dan kesabaran
pada kuota VM. Akun mahasiswa terkenal sulit membuat VM (semua ukuran tampil
`NotAvailableForSubscription`); jalan keluarnya sudah dicatat di `docs/deploy-vm.md` bagian 1A
— security type **Standard** (bukan Trusted Launch), availability zones mati, region
`Central US`/`East US 2`, dan kalau tetap nol: ajukan kenaikan kuota, jangan buat langganan
kedua. Kalau semua itu gagal, B di 3.2 (PC kantor + Tunnel) tetap bisa dipakai dan tidak
mengubah apa pun di aplikasi.

**Jangan** shared hosting gratis (InfinityFree/ProFreeHost/ByetHost). Alasannya bukan
"gratis jadi jelek" — batas 30.000 berkasnya sudah dilewati sejak pemangkasan hari ini —
tapi tiga angka yang tidak bisa dinegosiasi: **10 MB per berkas** vs 25 MB yang
dijanjikan ke petugas, **tanpa cron dan tanpa SSH/CLI** vs 4 pekerjaan terjadwal +
`migrate`, dan **docroot yang tidak bisa dipindah** yang memaksa `.env` +
`storage/app/private/arsip` + `db-backup/` berdiri di dalam folder web. Yang terakhir
ini justru melanggar praktik dasar yang user minta dijaga.

### 4.3 Kompromi yang saya terima untuk tahap ini, dan apa yang menggantikannya

Ditulis apa adanya supaya tidak ada yang mengira ini kondisi siap-pakai-kantor:

| Kompromi | Kenapa masih aman di tahap akademik | Pengamanannya yang tetap jalan (Rp 0) |
|---|---|---|
| Tidak ada cron | tidak ada data kantor; cadangan tetap bisa dipicu manual | `php artisan arsip:backup-db` dijalankan manual sebelum/ sesudah demo; jalurnya sudah ada dan sudah diuji pulih (X5) |
| Data contoh, bukan surat asli | justru syarat untuk boleh demo di server gratis | `DevSeeder` menolak jalan di luar `APP_ENV=local`, jadi isinya tidak bisa ikut ke server produksi; akun demo dibuat lewat `arsip:akun-pertama` |
| kredensial di VM pihak ketiga | isinya belum ada yang punya nilai hukum | user DB khusus (bukan root), `DB_PASSWORD` hanya di `.env` (di-`.gitignore`), `mysqldump` pakai `--defaults-extra-file` 0600 yang dihapus di `finally` — kredensial tidak pernah masuk baris perintah |
| HTTP polos kalau belum ada sertifikat | login demo di mesin sendiri | begitu ada akses luar kantor: HTTPS + `SESSION_SECURE_COOKIE=true` + `TRUSTED_PROXIES` (bagian 6 sudah menyiapkan kuncinya, dan alasannya tertulis di sana) |
| Arsip di server luar negeri | tidak ada arsip nyata | `robots.txt` `Disallow: /` + `X-Robots-Tag: noindex` tetap terpasang, lampiran hanya lewat controller `middleware('auth')` (L-01), tidak ada satu pun tautan publik |
| **Harga yang dibayar karena tidak punya kartu** | Azure for Students adalah kredit pelajar, bukan Always Free permanen | VM-nya `Standard_B1s` (1 vCPU / 1 GB): swap wajib, `AGENDA_BATAS_BARIS` diturunkan jadi 800 (9 Okt terukur: 1.000 baris agenda = 8,36 s + 52 MB dalam satu request), dan kredit 12 bulan berarti **cadangan keluar VM** (`scp` dump + Drive) jadi syarat, bukan saran. Build ulang tetap murah karena tidak ada yang diikat ke Azure: Ubuntu 24.04 + `bin/sediakan-server.sh` + `.env` di tempat aman |

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
   jalankan). Kalau suatu hari pindah Postgres, itu pekerjaan baru — ukurannya ada di 3.4:
   7 titik SQL mentah, `LIKE ? ESCAPE '!'` yang di Postgres jadi peka huruf, `CASE` di
   `ORDER BY`, `restrictOnDelete`, `upsert`/`lockForUpdate` untuk penomoran (L-20), dan
   `arsip:backup-db` yang hari ini 100% rasa MySQL. Bukan sekadar ganti driver, dan tidak
   ada `pdo_pgsql` di mesin ini untuk membuktikannya.
4. **Cadangan harus bisa dipulihkan di mesin lain.** `arsip:backup-db` sudah begitu —
   dan itu satu-satunya alasan matinya VM (reklamasi idle di Oracle, kredit habis di Azure)
   bukan bencana: `git clone` + `.env` + pulihkan dump = kantor lagi, di mana pun.
5. **Semua langkah deploy tetap ada di `bin/deploy.sh`.** Kalau suatu hari terpaksa
   melakukan sesuatu "manual di cPanel", tuliskan apa yang dilakukan dan kenapa — kalau
   tidak, enam bulan lagi langkah itu hilang dan deploy berikutnya cacat diam-diam.
6. **Uji yang hanya bisa dibuktikan di Apache** (`.htaccess`, kompresi, header) jangan
   disimpulkan dari `php artisan serve` — lihat `docs/deploy.md`; laptop ini sudah punya
   resep Apache uji di port 8080.

## 5. Runbook

### A. VM Ubuntu (Azure for Students / Oracle Always Free)

Satu sumber untuk langkahnya: **`docs/deploy-vm.md`** — dari pendaftaran akun (Azure:
verifikasi status mahasiswa tanpa kartu; Oracle: kartu + home region yang tidak bisa diganti),
buat VM, dua lapis firewall (NSG / security list + `ufw`), `sudo bash bin/sediakan-server.sh`
untuk provisi Apache/PHP 8.2/MariaDB/cron/Composer,
isi `.env`, `bash bin/deploy.sh` (semua perintah sisi server berjalan sebagai `www-data`,
dan alasannya ditulis di sana), sampai lima bukti yang menandakan deploy-nya benar-benar
hidup. Jangan menyalin langkah itu ke tempat lain: yang disalin selalu melenceng.

### B. PC/mini-PC kantor + Cloudflare Tunnel

Dipakai kalau nanti kantor menolak data suratnya berdiri di server luar. Tidak ada
perubahan kode:

1. Apache virtual host menunjuk `public/` (resep conf-nya sudah dicatat di
   `docs/deploy.md` bagian "`.htaccess` harus diuji di Apache"), `Listen 80` lokal.
2. `cloudflared tunnel` + route DNS ke `http://localhost`, jadi HTTPS dan nama domain
   selesai tanpa IP publik/port-forward. Isi `TRUSTED_PROXIES` — tanpanya halaman tampil
   **tanpa gaya** (bukti di 5.1).
3. Task Scheduler tiap menit: `php artisan schedule:run` (perintahnya sama dengan cron).
4. Dump keluar mesin: `arsip:sinkron-ke-drive` (sudah ada) — di jalur ini dia bukan
   pemanis, dia satu-satunya salinan kedua, dan PC-nya satu-satunya salinan pertama.
5. UPS. Insiden 10 Okt di laptop ini (mati listrik paksa → InnoDB korup) adalah gambaran
   masa depan jalur ini tanpa UPS.

### 5.1 Bekas pengukuran: apa yang terbukti soal perilaku di balik proxy

Quick Tunnel dipakai hari ini **sebagai alat ukur, bukan sebagai jalur deploy** (URL-nya
acak dan mati bersama prosesnya). Alat ini murah dan menjawab pertanyaan yang tidak bisa
dijawab dari kode: apa yang terjadi kalau aplikasi berdiri di belakang proxy — keadaan
yang juga bakal dialami VM Oracle kalau nanti dipasang Cloudflare/domain di depannya.
Peralatannya: conf Apache uji laptop (`C:\Users\asus\prd-apache-test\httpd.conf`, port
8080, DocumentRoot `public/`) + biner resmi
`https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe`
(terpasang di `C:\Users\asus\prd-tools\cloudflared.exe`, v2026.10.2), lalu
`cloudflared.exe tunnel --url http://127.0.0.1:8080`.

| yang diuji lewat URL publik | hasil |
|---|---|
| `/login` lewat HTTPS luar | `200` |
| `/.env` | `404` — kredensial tidak tersaji, dan ini dibuktikan lewat URL luar, bukan lewat percaya conf |
| header anti-index | `x-robots-tag: noindex, nofollow, noarchive, nosnippet` + dua `X-Robots-Tag: none` tambahan dari Cloudflare |
| **`TRUSTED_PROXIES` kosong** | `asset()` menghasilkan **4× `http://`** di dalam halaman `https://` → browser memblokir sebagai mixed content = **layar tanpa gaya**. Ini kegagalan P0#1 (10 Okt) yang dipindahkan ke sisi proxy, dan tidak ada satu pun tes PHPUnit yang bisa menangkapnya |
| **`TRUSTED_PROXIES=127.0.0.1`** | 4× `https://` — halaman utuh |

Dua batas yang perlu disebut, supaya tidak ada yang mengira ini lebih dari yang diukur:

- Yang diuji **permintaan HTTP**, bukan alur lengkap. Login + unggah lampiran + cetak PDF
  sengaja tidak dijalankan lewat tunnel: aplikasi itu sedang terbuka untuk seluruh internet,
  dan basis data di laptop ini masih berisi akun dev ber-PIN `12345678`.
- **Koridor pembersihannya sendiri pernah gagal.** Menutup proses uji dilakukan dengan
  `taskkill /F /IM httpd.exe`, dan perintah itu membunuh SEMUA `httpd` — termasuk Apache
  XAMPP milik user di port 80, yang bukan bagian dari uji. Sudah dinyalakan kembali
  (`C:/xampp/apache/bin/httpd.exe`, port 80 terukur melayani lagi) dan kejadian ini dicatat
  di `AGENTS_HISTORY.md`. Aturan untuk ronde berikutnya: matikan pakai PID yang dicatat
  sendiri, jangan per-nama image.

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
AGENDA_BATAS_BARIS=2000       # naikkan hanya sesudah servernya diukur; di B1s 1 GB: 800
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

1. **Pendaftaran Azure for Students itu sendiri** — `docs/deploy-vm.md` bagian 0–3. Tidak
   butuh kartu, tapi butuh dua hal yang tidak bisa saya sediakan: **bukti status mahasiswa**
   (email kampus / surat keterangan aktif) dan keputusan region. Setelah VM hidup dan SSH-nya
   ada di tanganmu, sisanya tinggal dijalankan: `git clone` → `bin/sediakan-server.sh` →
   `.env` → `bin/deploy.sh`. Kalau semua ukuran VM ditolak kuota dan permintaan kuota tidak
   diterima, kembali ke Oracle (1B, butuh kartu) atau ke opsi B di 3.2.
2. Domain + HTTPS (S14). Tanpa domain, demo di VM berjalan di http dan itu harus dinyatakan
   apa adanya di laporan, bukan ditutupi.
3. Google Drive untuk cadangan keluar (L-01 + bagian 6 `docs/deploy-vm.md`): butuh
   Service Account JSON yang tidak boleh di-commit dan folder Drive yang sudah di-share.
   Ini yang membuat reklamasi instance bukan kehilangan data.
4. F3: kop/template resmi desa. D4: klasifikasi resmi kantor. L-19: nilai N auto-lock.
   Ketiganya tidak menahan deploy, tapi menahan "dipakai sungguhan".
