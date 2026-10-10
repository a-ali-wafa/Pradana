# Daftar peningkatan PRADANA — lengkap, berdasarkan pengukuran

Ditulis 9 Okt 2026, sesudah perapian Fase 1–7 (`docs/plan-perapian-kode.md`).
Setiap butir punya label: **terukur** (angka nyata dari MariaDB/HTTP di laptop ini),
**dibaca di kode** (fakta struktur), atau **belum diukur** (dugaan yang jujur).
Ditandai 🔒 kalau menyentuh keputusan user [LOCKED], ⚠️ kalau butuh bahan/keputusan dari kamu.

## 0. Lima temuan hari ini yang paling penting

| # | Temuan | Bukti | Kalau dibiarkan |
|---|---|---|---|
| 1 | **Semua CSS/JS + SweetAlert2 dari CDN asing; nol guard.** 7 salinan `pradanaConfirmHapus()` memanggil `Swal.fire()` tanpa cek `typeof Swal` (`grep "typeof Swal\|window.Swal" resources/views/` = 0) | dibaca di kode: `layouts/app.blade.php:50-52,343` + `login.blade.php:41-42,266` | Internet kantor mati → UI tanpa gaya **dan tombol Hapus / Nyahkan / Pindahkan ke sampah jadi diam total** (JavaScript error, tidak ada fallback). Ini kegagalan paling mudah terjadi di desa |
| 2 | **Ekspor laporan memakai seluruh baris sebagai model PHP** (`LaporanController::barisMasuk/barisKeluar` → `->get()`) | terukur @16.005 surat: **1.674 ms + 56 MB** untuk load, padahal `get(['id','nomor_surat',...])` hanya **383 ms + 2 MB** (4× cepat, 27× hemat) | Shared hosting biasanya `memory_limit` 128–256 MB; rekap 5–10 tahun bisa 500 → blank page / 500. Periode panjang = risiko nyata |
| 3 | **Buku Agenda PDF lambat dan boros** | terukur: dompdf 1.000 baris = **8,36 s + 52 MB** (33 KB hasil) | Setahun agenda kantor (±2.000–3.000 surat) = 20–30 s → menabrak `max_execution_time` 30 s hosting → user klik "cetak agenda" lalu timeout |
| 4 | **`lokasi_fisik` direkam di form & halaman show, tapi tidak bisa dicari/disaring** — **SEBAGIAN BENAR, diperbaiki 10 Okt:** bagian "tidak bisa dicari" sudah TIDAK berlaku sejak refactor `CariArsip` 9 Okt (kolomnya ada di `KOLOM_CARI` kedua model), cuma belum ada satu pun tes yang membuktikannya — sekarang ada (`CariArsipTest::test_lokasi_fisik_ikut_digali`, diverifikasi dua arah dengan menghapus kolomnya: tes merah dengan pesan sendiri). Yang masih benar: tidak ada FILTER khusus lokasi dan tidak ada kolom "Rak/Box" di daftar | dibaca di kode: muncul di `create/edit/show` kedua surat + 4 Request, tidak ada satu pun filter | Petugas tahu surat itu ada di "Rak B-07", tapi tidak bisa menjawab "surat apa saja di rak itu?" — padahal itu kegunaan utama kolomnya |
| 5 | **Tidak ada CI sama sekali** | `ls .github/workflows` = tidak ada | Gerbang (213 tes + pint + Larastan) cuma jalan kalau agent/kamu ingat menjalankannya. Di GitHub aksi gratis untuk repo publik/privat kecil |

## 1. Performa

**Sudah selesai (Fase 1–7), dicatat biar tidak diulang:** agregat dashboard (25→13 query),
daftar surat (9→6), mode gabungan (14→10), `/laporan` (6+memori penuh→4 query halaman),
form pemusnahan (45→4 dan tidak tumbuh lagi), halaman show (eager load, N+1 hilang),
index `aktivitas (created_at, id)` (22,5→1,0 ms), interval tanggal setengah terbuka supaya
index terpakai, escape LIKE satu sintaks dua engine.

Yang tersisa, urut manfaat/usaha:

1. **`baris()` laporan → `get([...])` kolom yang dibutuhkan** (P0, ±1 jam, dampak besar).
   Angka di atas. Sekalian: `chunk(500)` untuk CSV supaya memori tidak pernah memegang semua
   baris sekaligus. Tidak mengubah kolom keluaran.
2. **Agenda PDF: batasi + perpendek** — **SELESAI 10 Okt, opsi (a) saja.** Periode yang
   melewati `AGENDA_BATAS_BARIS` (bawaan 2.000, `config/laporan.php`) ditolak lewat
   `jumlah()` SEBELUM satu baris pun dihidrasi, dengan pesan yang menyebut batas,
   jumlah sebenarnya, dan jalan keluar (cetak per semester); `/laporan` sudah
   memperingatkan di layar. Opsi (b) render-per-250-lalu-gabung dan (c) file hasil
   perintah terjadwal **tidak** dikerjakan: keduanya menambah keadaan yang harus
   dibersihkan (file sementara/antrian) tanpa angka yang membayarnya, dan L-02
   melarang worker. Kalau kantor ternyata butuh agenda tahunan sungguhan, (c) yang
   jadi jawabannya — itu keputusan user, bukan asumsi agent.
3. **Vendor asset lokal** (P0, ±1 jam — lihat temuan #1). Unduh `bootstrap.min.css`,
   `bootstrap.bundle.min.js`, subset `font-awesome` (only the `fa-` icons used — or swap to
   inline SVG), `sweetalert2.min.js` ke `public/vendor/`, ganti 4 tag URL, **update CSP**
   (`config/security.php` + `App\Support\TandaiPrivat`) supaya `self` + hapus dua host CDN,
   dan tambahkan guard: `if (typeof Swal === 'undefined') { submit pakai confirm() bawaan }`.
   Bonus: halaman pertama tidak menunggu internet (kenyamanan di LAN lambat), dan aplikasi
   tetap jalan kalau DNS/ISP bermasalah.
4. **FULLTEXT** (P1, 🔒 keputusanmu — user sudah memilih "tahan LIKE dulu"). Ukuran
   sekarang: dengan 16.005 surat, pencarian multi-kata LIKE tetap full scan per tabel.
   Belum terasa (≤ 100 ms di laptop). Kalau arsip digital tembus ±50.000 surat atau
   kantor mulai komplain "lemot", satu migration `FULLTEXT(nomor_surat, perihal, ringkasan,
   isi_hasil_baca)` + `MATCH AGAINST` jadi penggantinya — `CariArsip` sudah satu pintu,
   jadi perubahannya terlokalisir. Jangan pasang sekarang; tidak ada bukti.
5. **`my.ini` XAMPP/server kantor: `innodb_buffer_pool_size`** (P2, 5 menit). Bawaan XAMPP
   kecil sekali; untuk 50.000 baris + full scan LIKE, naikkan ke 256 MB. Tidak berlaku kalau
   deploy ke shared hosting (di sana kamu tidak pegang my.cnf — catatan untuk K1/S14).
6. **Offset pagination dalam** (P3). Terukur: halaman 500 `/aktivitas` = 73 ms dan index tidak
   menolong (sifat `LIMIT … OFFSET`). Solusi keyset ("Halaman berikutnya" saja, tanpa lompat
   nomor) hanya perlu kalau orang kantor ternyata benar-benar membuka halaman jauh — saat ini
   mereka pakai filter, bukan pagination. Jangan dikerjakan sekarang.
7. **Perpanjang guardrail query** (P2, ±1 jam): `JumlahQueryLayarTest` belum menutup
   `/laporan/agenda`, `/pemusnahan-arsip/{id}`, `/users`, `/pengajuan-hapus-lampiran`,
   `/surat-keluar/{id}/draf`, `cetak`. Tambah fixtures + ambang terukur supaya penurunan
   berikutnya tetap kebukti dan kenaikan diam-diam langsung kedetect.
8. **Deploy hygiene (sudah didokumentasikan, belum ada skripnya)** (P1, ±30 menit):
   satu `docs/deploy.md` + `bin/deploy.sh`: `composer install --no-dev -o`, `artisan
   config:cache && route:cache && view:cache`, `cache:clear` sebelum/ sesudah, `migrate
   --force`, set `APP_ENV=production APP_DEBUG=false`, `storage/app/private` di luar
   `public/`, cron `schedule:run` tiap menit, `chmod` benar. Manual Lampiran A sudah menyebut
   isinya; skrip menghilangkan risiko "salin-tempel salah".

## 2. Kenyamanan user (yang benar-benar dipakai petugas tiap hari)

Diurut dari yang paling sering kena. Tidak ada satu pun yang butuh keputusan [LOCKED],
kecuali yang ditandai.

1. **Fallback tanpa JavaScript** untuk semua aksi destruktif — sekarang kalau JS mati,
   tombol hapus tidak berbuat apa-apa. Perbaikan kecil: `<button type="submit" form=...>` +
   `onsubmit` sebagai penambah, bukan sebagai satu-satunya jalan. Sekalian menghapus 7 salinan
   `pradanaConfirmHapus()` jadi satu parsial (pola H6).
2. **Autosave isian panjang** di form surat & draf konten (localStorage/sessionStorage + pulihkan
   saat kembali + "draf dipulihkan dari 12:41"). Ini jawaban untuk kasus nyata yang sudah
   kamu catat di halaman 419 ("form dibiarkan > 2 jam / laptop tidur"): sekarang pesannya
   menyuruh hati-hati, tapi belum ada yang mencegah.
3. **Flash "Undo"** sesudah soft delete: `… dipindahkan ke tempat sampah. [Batal]` dengan link
   `pulihkan` (admin) — jalur dan route-nya sudah ada, tinggal satu link di pesan.
4. **Aksi massal di `?usang=1`**: checkbox "Nyahkan semua terpilih" (L-04 tidak dilanggar —
   keputusan tetap di tangan manusia, hanya jumlah kliknya yang turun). Sekarang 40 arsip tua
   = 40 kali buka surat, klik Nyahkan, lihat flash.
5. **Kotak filter yang "berisiko"**: badge filter aktif + "× hapus semua filter" (mis.
   `sifat=mendesak, status=inaktif, 2024`). Saat ini orang bisa lupa sedang tersaring dan
   mengira datanya hilang.
6. **Tandai duplikat saat mengetik nomor surat** (surat masuk boleh duplikat — D9 — tapi
   petugas perlu tahu "nomor ini sudah dipakai di 2 surat, lihat?"). Data sudah ada;
   butuh 1 endpoint kecil + debounce.
7. **"Buat salinan" surat keluar** (P1): kantor menulis surat yang sama dengan beda tanggal/
   tujuan. Satu tombol → form edit dengan nomor kosong + counter baru. Hemat waktu paling
   besar dibanding fitur lain di daftar ini.
8. **Filter `lokasi_fisik`** (P1, lihat temuan #4) + kolom "Rak/Box" di daftar; opsional
   daftar "isi per lokasi". Catatan 10 Okt: bagian *mencari* lokasi sudah jalan dan sudah
   dikunci tes — `cari=Lemari 07` menemukan suratnya; yang belum dibuat adalah menyaring
   DAFTAR ("semua surat di Rak B-07") dan menampilkannya sebagai kolom di tabel.
9. **Preferensi daftar diingat per petugas** (kolom kecil di `users` atau cookie): kolom yang
   ditampilkan, urutan, halaman per 20/50/100 (⚠️ H2/L-14 mengunci "seragam 20" — kalau mau
   picker, itu keputusan baru, bukan bagian perapian).
10. **Ringkasan hari ini di dashboard**: "masuk 4 / keluar 2 / 3 surat belum dilampiri" +
    daftar "surat yang belum ada berkasnya" (kolum `lampiran_count` sudah di-`withCount`).
11. **Tanggal: tombol "Hari ini" + format isian jelas** (`YYYY-MM-DD` vs `dd/mm/yyyy` — sumber
    salah input paling senyap), dan tampilkan `18-08-2026 (3 hari lalu)`.
12. **Tabel: header sticky + baris bertumpuk saat scroll panjang**, dan "Halaman 3 dari 41".
13. **Upload: hasil per file** — sekarang loading bar sudah ada; tambahkan daftar hasil
    ("3 diunggah, 1 dilewati karena sama dengan `scan.pdf` di surat 001/01/…", memakai
    `hash_file` yang tersimpan), tombol "coba lagi" untuk file yang gagal.
14. **Pratinju cepat lampiran**: gambar (jpg/png) tampil thumbnail di halaman show (GD tersedia
    di shared hosting); PDF/DOCX tetap unduh — ghostscript/OCR tidak ada (sudah dicatat jujur
    di manual).
15. **Cari cepat dari mana saja** (Ctrl/Cmd+K fokus ke kotak cari daftar) — kecil, terasa.
16. **Aksesibilitas**: `aria-live` untuk flash, kontras badge, target klik ≥ 40 px, semua
    form punya `<label for>` (sudah baik), dan hindari `onclick=` murni untuk aksi penting.
17. **Log aktivitas: saring per petugas** — `user_id` sudah ter-index (`aktivitas_user_id_foreign`)
    dan UI admin-nya tinggal ditambah dropdown; berguna saat ada pertanyaan "siapa yang ubah ini".

## 3. Fitur yang belum ada sama sekali

1. **Impor massal surat lama dari CSV/Sheets** ⚠️ (P1, terbesar). L-03 bilang tidak ada migrasi
   otomatis, tapi kamu juga bilang "kemungkinan nanti ada input manual surat lama" — input
   manual 1.200 surat = berhari-hari kerja. Yang dibutuhkan: unggah CSV → pratinjau → validasi
   per baris (kode klasifikasi, tanggal) → masukkan; surat gagal tidak menghalangi yang lain;
   hasil akhir tercatat di log sebagai satu impor. Ini bukan "migrasi data lama" (tidak ada
   skema lama yang dibaca), jadi tidak menyentuh L-03.
2. **Disposisi / tindak lanjut surat masuk** ⚠️ (P2, fitur kantor klasik): surat → siapa yang
   harus tindak → tenggat → status (baru/diproses/selesai). Sekarang sistem hanya mencatat
   surat, bukan apa yang terjadi setelahnya. Butuh 1 tabel (`disposisi`) + UI kecil; dan ini
   keputusan fungsional, jadi perlu kamu tanyakan ke petugas dulu, bukan aku tebak.
3. **Status surat keluar**: dikirim / diterima / ada tanda terima + tanggal. Sekarang surat
   keluar berhenti di "sudah dicetak PDF".
4. **Backup database terjadwal** — **SELESAI 10 Okt 2026** (P1): `arsip:sinkron-ke-drive` hanya mengurus **lampiran**.
   Isi DB (13 tabel) belum ada jalur backup otomatis — sekarang hanya catatan mysqldump manual
   di Lampiran A. Perlu `arsip:backup-db` (mysqldump via `Process`/`exec`, simpan ke folder
   Drive atau disk lokal, rotasi 30 hari) + prosedur uji pulih (X5) yang bisa dijalankan sungguhan.
   **Kenyataan yang terjadi 10 Okt 2026, dan ini argumen terbaik untuk butir ini:** MariaDB dev di
   laptop tidak bisa dinyalakan lagi setelah mati listrik/mati paksa — `[FATAL] InnoDB: Trying to
   read page number 32769 in space 0 ... which is outside the tablespace bounds` (redo log menuntut
   `ibdata1` ±512 MB, file sungguhan 79 MB). Tidak ada satu pun tes engine-asli yang bisa jalan, dan
   **yang hilang bukan cuma data tes: database `pradana` (arsip dev kantor) ada di datadir yang sama**.
   Satu-satunya alasan kejadiannya bisa dideal-kan adalah salinan manual folder
   `C:\xampp\mysql\data` yang dibuat di tempat (`prd-mysql-data-backup-20261010`, 172 MB) — itu
   persamaan uji yang diminta X5, hanya saja dilakukan setelah insiden, bukan sebelumnya.
   Catatan: `mysqldump` per-database (`pradana`) juga harus masuk rutinitas, karena salinan datadir
   hanya berguna selama mesin MariaDB versi yang sama masih ada untuk membukanya.
   **Yang landed 10 Okt:** `arsip:backup-db` + `config/backup.php`, dijadwalkan harian 02:10, dump ke
   `storage/app/private/db-backup` (di luar `public/`), rotasi 30 hari yang hanya menghapus berkas
   berpola `prd-<tanggal>.sql`, kredensial lewat file `defaults-extra-file` 0600 (bukan `-p` di baris
   perintah), dan **verifikasi hasil** (ukuran + `CREATE DATABASE` skema yang benar + jumlah
   `CREATE TABLE` vs tabel sungguhan + footer) — kegagalan verifikasi = backup gagal, berkas dibuang.
   Uji pulih (X5) sekarang ada versi jalannya: `UjiPulihBackupMariaDbTest` memulihkan dump ke skema
   `*_pulih`, membandingkan daftar tabel **dan jumlah baris per tabel** dengan sumbernya, lalu
   membuktikan dump yang dipotong TIDAK menghasilkan salinan setara. Dua bug nyata ditangkap jalur
   ini: verifikasi lama menuntut bentuk `CREATE DATABASE` gaya MySQL 8 sehingga menolak dump sungguhan
   hasil MariaDB, dan `getSchemaBuilder()->getTables()` tanpa argumen menghitung **seluruh tabel di
   server** (79 di laptop ini) sehingga cadangan yang sah dituduh tidak lengkap.
5. **Ekspor log aktivitas** (P2) untuk keperluan audit: CSV dari `/aktivitas` dengan filter
   yang sedang aktif.
6. **Multi-template PDF kop desa** 🔒 (F3, butuh bahan darimu: contoh kop/template asli desa).
7. **Auto-lock arsip setelah N hari** 🔒 (L-19/B10, kolom `terkunci_pada` belum ada; **nilai N
   belum kamu putuskan**).
8. **Klasifikasi resmi kantor** ⚠️ (D4): yang terisi sekarang contoh sementara dari DevSeeder.
9. **Cetak label/box arsip** (P3): stiker "Rak B-07 · 2024 · Klasifikasi 02" dari daftar lokasi
   (nyambung ke #8 di bagian kenyamanan).
10. **OCR** (P3, dan kemungkinan besar tidak bisa di targetmu): `smalot/pdfparser` hanya membaca
    teks yang tertanam; PDF hasil scan tetap butuh tesseract — biasanya tidak tersedia di shared
    hosting. Sudah jujur dicatat di manual; jangan dijanjikan ke kantor.

## 4. Keandalan, kualitas, dan "supaya tidak rusak nanti"

1. **CI GitHub Actions** (P1, ±45 menit): satu workflow: `composer install`, `php artisan test`
   (SQLite saja — tes MariaDB otomatis di-skip dengan pesan), `vendor/bin/pint --test`,
   `vendor/bin/phpstan analyse`. Manfaatnya konkret: bug flash Fase 6 dan guardrail yang buta
   terhadap multi-line tidak akan lolos diam-diam lagi.
2. **Tes isi PDF, bukan cuma header** (P2): `smalot/pdfparser` sudah jadi dependency —
   assert bahwa Berita Acara memuat nomor `BA-001/…`, tanggal, dan daftar nomor suratnya, dan
   surat keluar memuat nomor + perihal. Selama ini tes hanya `application/pdf` + `%%EOF`.
3. **Regresi guardrail untuk jalur destruktif** (P1): approval pemusnahan & setujui-hapus sudah
   diuji atomic-nya (Fase 3); tambahkan "file fisik tidak pernah hilang saat DB gagal commit"
   untuk jalur pemusnahan `forceDelete` (Fase 2 menyebutnya tapi tesnya ada di jalur lampiran).
4. **Rotasi log aplikasi** (P2): `storage/logs` tidak pernah dipangkas; shared hosting suka
   jengkel dengan file 200 MB. Set `days`/`MaxFiles` di `config/logging.php` + satu entri
   cron pembersih.
5. **Sweep sesi & cache view saat deploy** (P3) — bagian dari `bin/deploy.sh` (#8 performa).
6. **Audit otorisasi sekali lagi di jalur beresiko** (P2): `restore`, `destroy`, approval
   pemusnahan = 403 untuk staf (sudah diuji); belum diuji: staff non-admin mencoba `PATCH
   users/{id}/pin` milik orang lain, `pengaturan-instansi` update oleh staf, `lampiran` surat
   lain. Semua ada di `middleware('admin')`, tapi satu tes eksplisit per endpoint lebih murah
   daripada penemuan mahal di produksi.
7. **Kunci sesi di HTTPS** (P1, 🔗 bersama deploy): `SESSION_SECURE_COOKIE=true` + `HSTS` hanya
   begitu kantor punya HTTPS (S14 belum diputuskan) — sekarang `config/session.php` membaca
   dari env, jadi tinggal dikonfigurasi, tidak perlu kode.
8. **Serah terima yang bisa diverifikasi** (P1): versi di footer (`git describe` → konstanta
   `PRADANA_VERSION`), daftar `php artisan` yang ada di satu halaman admin "Kondisi sistem"
   (jumlah surat, ukuran folder arsip, tanggal backup terakhir, `APP_ENV`, versi PHP) — orang
   kantor butuh satu layar untuk bilang "ini sehat".
9. **Dokumentasi keputusan UI**: kalau salah satu kenyamanan di atas diterima/ditolak petugas,
   catat di register `Obsidian Vault/Pradana/Daftar Keputusan PRADANA.md` (preseden: L-17
   di-badge-kan tanpa konfirmasi eksplisit, masih tercatat ⚠️ sampai sekarang).
10. **Zona waktu aplikasi** — **SELESAI 10 Okt 2026** (ditemukan malam itu juga, saat nama berkas dump
   `arsip:backup-db` keluar tujuh jam lebih awal): `config/app.php` masih `'timezone' => 'UTC'`
   bawaan Laravel, padahal nomor Berita Acara (`BA-###/romawi/tahun`), `tanggal_pelaksanaan`, periode
   default `/laporan`, dan agregat "bulan ini" di dashboard semuanya dihitung dari `now()`. Jadi
   persetujuan yang ditekan pukul 01 Januari 02:30 WIB menghasilkan dokumen **XII/2025 bertanggal 31
   Desember 2025** — salah hari di dokumen resmi, bukan sekadar tampilan. Sekarang
   `env('APP_TIMEZONE', 'Asia/Jakarta')` (kantor WITA/WIT cukup ganti `.env`). Dua tes baru mengunci
   dua arah pada satu instan absolut; perpindahan zona dinyatakan aman oleh `grep` (nol
   `DB::raw('NOW()')` dan nol `->useCurrent()` — tidak ada timestamp yang dihasilkan database).
   Yang tersisa untuk petugas: memastikan `APP_TIMEZONE` terisi di `.env` server kantor (A.2 manual).

## 5. Urutan yang kusarankan (kalau kamu mau aku lanjutkan)

| Prioritas | Paket | Kenapa | Menunggu kamu? |
|---|---|---|---|
| P0 **SELESAI 10 Okt** | **Aset lokal + guard Swal + fallback tanpa JS** (temuan #1) | commit `416aed5` (aset + CSP) & berikutnya (konfirmasi); `AsetLokalTest` + `KonfirmasiDestruktifTest` | tidak |
| P0 **SELESAI 10 Okt (dengan satu pengecualian)** | **`baris()` laporan → kolom saja** + chunk CSV | commit `ffa0fd0`; 56 MB → 2 MB terukur. Chunk/streaming CSV **sengaja tidak** dikerjakan: `baris()` harus menggabungkan & mengurutkan dua tabel sebelum baris pertama dicetak, jadi seluruh baris tetap dibutuhkan — setelah pemangkasan kolom sisanya ±2 MB | tidak |
| P0 **SELESAI 10 Okt** | **CI Actions** (#4.1) | commit `7fee81c`; `.github/workflows/ci.yml` — catatan: 18 tes engine-asli MariaDB di-skip di CI, jadi suite lokal dengan XAMPP menyala tetap wajib sebelum serah terima | baru jalan setelah `git push` |
| P1 **SELESAI 10 Okt** | **Agenda PDF: plafon `AGENDA_BATAS_BARIS` + peringatan di layar** (temuan #3) | commit di bawahnya; `AgendaBatasBarisTest` (5 tes). Opsi render-bertahap/file terjadwal sengaja tidak dipilih — lihat §1 butir 2 | kalau butuh agenda tahunan sungguhan: putuskan opsi (c) |
| P1 **SELESAI 10 Okt** | **`arsip:backup-db` + uji pulih** (#3.4) | `config/backup.php` + command + jadwal harian 02:10; dump sungguhan 26,6 KB dari `pradana` terbukti lolos pemeriksaan sendiri; `BackupDatabaseTest` (10) + `BackupDatabaseMariaDbTest` (1) + `UjiPulihBackupMariaDbTest` (1, memulihkan ke skema lain lalu membandingkan jumlah baris per tabel — dan menguji dump terpotong agar TIDAK setara). Prosedur manualnya ditulis ulang di Lampiran A.6 | tidak |
| P1 | **Undo-after-delete, autosave form, filter chips, buat salinan surat keluar** | kenyamanan harian, semua lokal | tidak |
| P2 | **Filter lokasi + kolom "Rak/Box" di daftar, dan filter per petugas di log** (`lokasi_fisik` sudah BISA dicari — terbukti oleh tes 10 Okt; yang belum: menyaring daftar berdasarkan lokasi dan menampilkannya sebagai kolom) | kolom sudah ada, tinggal dibuka | tidak |
| P2 | **Impor CSV surat lama** | hemat paling banyak waktu manusia | ⚠️ perlu contoh kolom dari petugas |
| P3 | **FULLTEXT, keyset pagination, OCR, thumbnail** | belum ada bukti perlu / tidak bisa di target | ya (kecuali OCR: tolak saja) |
| 🔒 | **Multi-template PDF, N auto-lock, klasifikasi resmi, HTTPS/domain** | bahan/angka/keputusan kantor | ya |
