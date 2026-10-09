# Plan perapian kode & alur data PRADANA (9 Okt 2026)

Dokumen kerja. Isinya hasil audit berbasis kode + angka pengukuran, bukan dugaan.
Urutan: analisis → plan → analisis ulang (verifikasi tiap butir) → jalankan.

## Cara mengukur

`tests/Feature/JumlahQueryLayarTest.php` menanam 200 surat aktif + 40 surat lama
inaktif (+ lampiran/draf/pengajuan), lalu menghitung statement yang benar-benar
diproduksi satu layar lewat HTTP. Ambang jadi guardrail: optimasi yang membuat
angka turun harus menurunkan ambangnya, dan N+1 yang kembali masuk akan gagal di
sini — bukan di layar petugas saat arsip sudah ribuan surat.

Baseline (SQLite in-memory, 240 surat):

| Layar | Query baseline | Catatan |
|---|---|---|
| `/dashboard` | **25** | 9 `count()` terpisah + `klasifikasi_sekunder` x3 (relasi diakses view tanpa eager load) |
| `/surat-masuk` | 9 | includes eager load `tersier` yang tidak pernah ditampilkan |
| `/surat-masuk` + kata kunci | 9 | |
| `/surat-masuk?jenis=semua` | **14** | eager load dobel per jenis + `tersier` tak terpakai |
| `/surat-masuk/{id}` | 7 | wajar |
| `/laporan` | 6 query, **TAPI** memuat SELURUH baris ke memori hanya untuk `count()` | |
| `/pengajuan-hapus-lampiran` | 7 | |
| `/pemusnahan-arsip/create` | **47** | **40 = `select count(*) from lampiran` per baris kandidat** |
| `/aktivitas` | 4 | |

Angka ini akan memburuk linear dengan isi arsip kantor: 1.000 surat lama = ±1.000
query di form pemusnahan.

## Fase 1 — benar dulu, cepat kemudian (kalender & batas tanggal)

Bug kelas yang sama sudah saya temukan & perbaiki di pencarian (9 Okt). Yang tersisa:

1. `LaporanController::baris()` (`app/Http/Controllers/LaporanController.php:156`)
   memakai `whereBetween('tanggal_surat', [$dari->toDateString(), $sampai->toDateString()])`.
   Di SQLite kolom date tersimpan `2026-12-31 00:00:00` (Eloquent menulis cast `date`
   dengan format `Y-m-d H:i:s`), jadi surat tertanggal **31 Desember hilang dari rekap
   dan Buku Agenda** kalau `sampai` = 31 Desember. Di MariaDB tidak kelihatan karena
   kolom DATE memotong jam. Ganti ke interval setengah terbuka (`>= dari`, `< sampai+1 hari`),
   sama seperti `FilterArsip`.
2. `AktivitasController::index()` (`:40`, `:44`) memakai `whereDate('created_at', ...)`
   — fungsi di atas kolom, index tidak terpakai, dan pembulatan ke hari harus eksplisit.
   Ganti ke rentang datetime terbuka di ujung atas.
3. `DashboardController::index()` (`:35-40`) `whereMonth()+whereYear()` — fungsi di atas
   kolom, index `tanggal_diterima`/`tanggal_surat` tidak terpakai. Ganti ke rentang bulan
   setengah terbuka.
4. `AktivitasController::index()` membangun `LIKE "%$kata%"` sendiri (`:32`) tanpa escape,
   jadi `%`/`_` yang diketik user masih jadi wildcard di halaman log — tidak konsisten
   dengan `CariArsip`. Pakai `CariArsip::pola()` (satu sumber, sudah diuji).

### Hasil Fase 1 (dijalankan 9 Okt 2026)

Keempat butir di atas selesai, plus tiga tambahan yang muncul dari pengukuran:

| Layar | Baseline | Sesudah Fase 1 | Ambang guardrail |
|---|---|---|---|
| `/dashboard` | 25 | **13** | 15 |
| `/surat-masuk` | 9 | **6** | 8 |
| `/surat-masuk` + kata kunci | 9 | **6** | 8 |
| `/surat-masuk?jenis=semua` | 14 | **10** | 12 |
| `/surat-masuk/{id}` | 7 | **5** | 7 |
| `/laporan` | 6 + seluruh baris di memori | **4 query, nol baris** | 6 |
| `/pengajuan-hapus-lampiran` | 7 | **6** | 8 |
| `/pemusnahan-arsip/create` | 47 | 45 (belum disentuh — Fase 2) | 47 |
| `/aktivitas` | 4 | **3** | 5 |

Tambahan yang dikerjakan di luar daftar butir 1–4 karena angkanya kelihatan saat
mengukur:

- **`App\Support\RentangTanggal`** — satu helper interval setengah terbuka
  (`satuHari`, `rentang`, `bulan`, `terapkan`) dipakai laporan, log, dan dashboard.
  Catatan pentingnya: Carbon itu mutable, batas atas wajib `->copy()->addDay()`.
- **`LaporanController`** dipecah jadi `tanyakan()` (kerangka, `@template`),
  `barisMasuk()`/`barisKeluar()` (bentuk baris), dan `jumlah()` (COUNT di database).
  Bug yang dibuktikan `BatasTanggalLaporanTest` sebelum perbaikan: CSV hanya berisi
  `UKU-DEPAN` dan `UKU-TENGAH` — surat 31 Desember hilang.
- **Badge antrian sidebar** (`layouts.app`, jalan di SETIAP halaman): dua `count()`
  jadi satu `UNION ALL`.
- **Baseline Larastan 26 → 7.** Penyebabnya tipe, bukan bungkam: 31 method relasi di
  12 model sekarang ditulis `@return BelongsTo<User, $this>` (related dulu, declaring
  belakangan — urutan parameternya begitu, dan `static::` untuk method private harus
  `self::`), `SuratMasuk`/`SuratKeluar` punya `@property-read int $lampiran_count`,
  dan `isiUmum()` memakai tipe konkret alih-alih `$model` string + `$kolomLawan` string.
  Empat entri `dead catch QueryException` dan `Model::$primer` (morphTo) dibiarkan —
  itu keterbatasan analisis statis atas PDO runtime, sudah dibaca satu-satu.
- **Guardrail mesin asli**: `tests/MariaDbHarness.php` (plumbing skip-if-down +
  migrate + bersih-bersih, dipakai `CariArsipMariaDbTest` dan yang baru
  `AgregatMariaDbTest`) — membuktikan `SUM(CASE …)` dan `UNION ALL` badge benar di
  MariaDB, lewat HTTP, dengan assertion SELISIH supaya tidak bergantung pada isi DB tes.
- `arsip:daftar-usang` dijadwalkan mingguan + `withoutOverlapping()`, dan 12 flash
  `->with('status', …)` dinormalkan ke `success` (kunci yang layout render) —
  dipaksa tetap benar oleh `FlashKonsistenTest` yang memindai kedua sisi.

Belum disentuh dari daftar Fase 1: tidak ada. Yang disengaja TIDAK diubah: jumlah
baris per halaman log tetap 30 (H2/L-14 mengatur daftar surat, bukan log — alasannya
ada di `AktivitasController::BARIS_PER_HALAMAN`).

## Fase 2 — alur data & query (dampak terukur)
5. `PemusnahanArsipController::kandidatPemusnahan()` (`:248`, `:260`) menghitung lampiran
   per baris → ganti `withCount('lampiran')` (2 query total, bukan 2N).
   Sekalian: `with('primer')` dimuat tapi **tidak pernah dibaca** oleh bentuk hasilnya → buang.
   `$terpakai` memuat SELURUH item pemusnahan sebagai model (`:233-237`) → cukup
   `pluck('arsipable_type','arsipable_id')` jadi satu set lookup, bukan closure `in_array` O(n·m).
6. `PemusnahanArsipController::store()` (`:75`) menjalankan seluruh kandidatPemusnahan()
   (scan dua tabel + count) hanya untuk memvalidasi id yang dikirim form → ganti dua query
   target: ambil baris yang dipilih saja, cek syaratnya per baris (umur & inaktif & belum diajukan).
7. `DashboardController::index()` — 9 `count()` digabung jadi 2 query agregasi
   (`SUM(CASE WHEN ...)`) + 1 untuk mendesak. Target: dashboard 25 → ≤ 10 query.
8. Eager-load yang salah: daftar surat memuat `tersier` yang tidak ditampilkan di baris
   daftar (hanya `primer.kode` + `sekunder.kode`); dashboard memuat `primer`+`petugas`
   tapi view-nya menyentuh `sekunder`. Perbaiki daftar relasi yang dimuat per layar
   (ukur, bukan tebak — probe fase ini yang membuktikan).
9. `LaporanController::index()` memuat semua baris hanya untuk menampilkan "N surat cocok"
   → pakai `count()` di database (dua query), `baris()` tetap satu sumber untuk CSV/PDF.
10. Mode gabungan: `DaftarArsipGabungan::muat()` memuat relasi dua kali (per jenis) —
    sudah benar secara semantik; pastikan tidak membawa relasi yang tidak dirender.

### Hasil Fase 2 (dijalankan 9 Okt 2026)

Butir 5–10 selesai atau ternyata sudah tidak berlaku. Angka terukur
(`LAPOR_QUERY=1 php artisan test --filter=JumlahQueryLayarTest`, 240 surat):

| Layar | Baseline | Fase 1 | Fase 2 | Ambang sekarang |
|---|---|---|---|---|
| `/pemusnahan-arsip/create` | 47 | 45 | **4** | 6 |
| `/surat-masuk/{id}` (dengan berkas) | 7 | 5* | **6** | 8 |
| layar lain | — | sudah diukur | tidak berubah | sama |

\* angka Fase 1 untuk layar show diukur pada surat yang TIDAK punya lampiran, jadi
N+1 per berkas tidak kelihatan sama sekali. Fixture diganti ke surat berberkas;
dengan eager load sekarang 6 query dan **bertambahnya berkas tidak menambah query**.

Yang dikerjakan:

- **`kandidatPemusnahan()`**: `withCount('lampiran')` menggantikan `$s->lampiran()->count()`
  per baris; `with('primer')` dibuang (view tidak membacanya); "sudah diantrikan"
  tidak lagi memuat seluruh `PemusnahanArsipItem` sebagai model lalu `in_array` —
  sekarang satu query dua kolom, `groupBy`, dan `whereNotIn` di database.
  40 arsip tua: 45 → 4 statement. Ditambah tes skala: 80 kandidat tetap 4 statement
  (`test_form_pemusnahan_tidak_tumbuh_bersama_isi_gudang`).
- **`store()`** tidak lagi menjalankan `kandidatPemusnahan()` (scan seluruh gudang)
  untuk memvalidasi id yang dikirim form: `idLayak()` membatasi query ke id terpilih.
  Snapshot item dibuat dari satu query `withCount` per jenis, bukan `findOrFail` +
  `count()` per baris, lewat helper `dataItem()`.
- **`App\Support\RetensiArsip`** = satu sumber angka retensi (E1/L-04 [LOCKED], 5 tahun).
  Dipakai `UmurArsip::lewatRetensi()`, `PemusnahanArsipController`, dan
  `arsip:daftar-usang`. Kenapa bukan konstanta di trait: PHP melarang akses
  `UmurArsip::BATAS_RETENSI_TAHUN` dari luar kelas pemakainya — kesalahan yang
  dibuat pagi ini dan langsung ditangkap 10 tes (semua layar pemusnahan 500).
- **Trait `App\Models\Concerns\UmurArsip`** (`lewatRetensi()`, `umurTahun()`) di
  `SuratMasuk` + `SuratKeluar`. Ini menutup pelanggaran **L-21 [LOCKED]**: view
  surat masuk dulu menghitung umur dari `tanggal_diterima`, sedangkan server
  (dan form pemusnahan, dan `arsip:daftar-usang`) dari `tanggal_surat` — tombol
  "Ajukan Hapus" bisa tampil untuk surat yang pasti ditolak, atau hilang untuk
  surat yang sebenarnya boleh diajukan. `Lampiran::isEligibleForDeletion()`
  di-ubah namanya jadi `layakDihapus()` (konsisten kosakata Indonesia) dan isinya
  sekarang mendelegasikan ke induknya, bukan menghitung ulang.
- **Halaman show** masuk & keluar eager-load `lampiran.pengajuanHapus` +
  `lampiran.pengunggah`, dan view membaca koleksi itu (`->where('status','menunggu')->isEmpty()`)
  alih-alih `->exists()` per berkas. Perilaku dikunci `AjukanHapusLampiranTampilTest` (5 tes),
  termasuk kasus "diterima 2019 tapi dibuat 2025 → tombol tidak boleh muncul".
- Butir 8 (daftar surat memuat `tersier`, dashboard menyentuh `sekunder`) **tidak
  berlaku lagi**: `tersier` sudah tidak dimuat di daftar, dan `$with` global di
  model klasifikasi dibuang di Fase 1 — terukur, bukan diasumsikan.
- `phpstan-baseline.neon` menyusut lagi: 7 → **6** entri. Yang tersisa semuanya
  sudah dibaca: 4 `dead catch QueryException` (PDO runtime), 1 `Model::$primer`
  di jalur `morphTo` perintah backup Drive, 1 nullsafe defensif.

## Fase 3 — hapus duplikasi kode (perilaku tidak berubah)

11. `SuratMasukController` dan `SuratKeluarController` menyalin `create/edit/destroy/
    restore/updateStatusArsip` dan `status_arsip='aktif'` + `user_id`. Setelah filter &
    pencarian disatukan (9 Okt), sisa duplikasinya tinggal action sekunder → trait
    `app/Http/Controllers/Concerns` (pola yang sudah dipakai untuk mode gabungan).
12. `updateStatusArsip()` dua controller memakai `$request->validate()` inline sementara
    project ini punya pola FormRequest → `UpdateStatusArsipRequest` (satu untuk dua jenis).
13. Tiga controller klasifikasi (primer/sekunder/tersier, 84/89/87 baris) hampir identik —
    tinggal beda relasi induk & pesan. Tunggu hasil audit agen untuk memutuskan bentuk
    paling kecil yang tidak jadi "abstraksi yang lebih sulit dibaca".
14. 11 Observer + `LogsAktivitas` trait: cek apakah semuanya perlu ada atau bisa satu
    observer generik; cari `updated` yang mencatat perubahan yang tidak berarti.
15. `LampiranController::simpanLampiran()`: multi-write (`lampiran` + `surat`) tanpa
    `DB::transaction`, dan file yang sudah `store()` bisa jadi yatim kalau insert gagal →
    bungkus transaksi + rapikan pembentukan folder/penamaan.

### Hasil Fase 3 (dijalankan 9 Okt 2026 — subset bedah, bukan perombakan)

Selesai:

- **`SuratMasukController::destroy()`**: `try/catch (QueryException)` yang menjanjikan
  "masih direferensikan data lain" dibuang. Soft delete itu UPDATE `deleted_at`, bukan
  DELETE, jadi FK `restrictOnDelete` tidak pernah tersentuh dan pesan itu tidak bisa
  muncul — kalau pun muncul, itu kesalahan lain yang justru tersamar. `SuratKeluarController`
  memang tidak pernah punya catch begitu, jadi keduanya kini sama. Satu entri baseline
  Larastan ikut hilang.
- **`PengajuanHapusLampiranController::setujui()`**: urutan dibalik + transaksi.
  DB (`lampiran.delete()` + status pengajuan) dalam satu `DB::transaction`,
  `hapusBerkasFisik()` SESUDAH commit — file tidak bisa di-rollback, baris bisa.
  Dikunci tes baru yang memaksa `update()` gagal lewat observer penyamar: setelah
  kegagalan, berkas masih ada, baris masih ada, status masih `menunggu`. Pada bentuk
  lama tes ini GAGAL (file sudah terlanjur hilang, baris sudah terhapus).
- **`LampiranController::simpanLampiran()`**: kalau `Lampiran::create()` gagal, berkas
  yang barusan ditulis ke disk ikut dibuang sebelum exception diteruskan — tidak ada
  lagi file yatim yang tidak terlihat di layar mana pun. Perilaku untuk user tidak berubah.
- **Kegagalan baca isi lampiran sekarang dicatat** (`Log::warning`) di dua tempat:
  `PembacaIsiLampiran::dariPdf()` dan `LampiranController::cobaBacaIsi()`. Upload tetap
  tidak pernah gagal karena baca gagal (L-02), tapi "kok isinya nggak kebaca" sekarang
  bisa dijawab dari `storage/logs`, bukan dengan tebakan.
- **Komentar basi dibersihkan**: 7 observer masih menulis "Belum diregistrasikan, lihat
  `CATATAN.md`" padahal semuanya terdaftar di `AppServiceProvider::boot()` dan file itu
  tidak ada; `LogsAktivitas` masih menyebut dirinya "rencana, belum ada implementasi";
  `AppServiceProvider` merujuk "AGENTS.md 12.22" yang sudah pindah ke `AGENTS_HISTORY.md`.
  Import `Carbon\Carbon` di `Lampiran` dan `QueryException` di `SuratMasukController`
  yang jadi mati ikut dibuang.
- **Urutan dropdown klasifikasi disamakan**: `SuratKeluarController::create()`/`edit()`
  satu-satunya yang memakai `orderBy('nama')`; semua tempat lain (8 pemanggil) sudah
  `orderBy('kode')`. Kode memang urutan domain yang dipakai form cascade.

Terverifikasi dan ternyata TIDAK perlu diubah (dibaca, bukan diasumsikan):
`SinkronkanLampiranKeDriveCommand` tidak punya `DB::transaction` — panggilan HTTP ke
Drive tidak pernah berada di dalam transaksi, jadi butir "bawa keluar transaksi" gugur.
Empat `catch (QueryException)` di controller klasifikasi BIAR dipertahankan (FK restrict
benar-benar melemparnya di runtime; PHPStan hanya tidak bisa melihatnya) — itulah 4 dari
5 entri baseline yang tersisa.

Belum dikerjakan (sengaja ditunda, bukan kelupaan): deduplikasi `SuratMasuk` ↔
`SuratKeluar` dan tiga controller klasifikasi jadi trait/parsial; `UpdateStatusArsipRequest`
eksplisit; `{!! sorot() !!}` → `{{ }}`; angka tanggal hard-coded di JS pratinjau kop.
Semuanya perubahan bentuk tanpa perubahan perilaku, dan lebih aman dikerjakan sendiri
dengan tes per layar daripada diburu dalam satu commit.

Baseline Larastan: 6 → **5** entri, semuanya sudah dibaca satu-satu.

## Fase 4 — sampah & metadata

16. Boilerplate yang tidak dipakai (frontend via CDN, tanpa Vite): `package.json`,
    `vite.config.js`, `resources/js/*`, `resources/css/*`, `routes/channels.php` —
    diverifikasi grep dulu (agen), baru dihapus.
17. `composer.json`: `"name": "laravel/laravel"`, `"description": "The skeleton
    application..."` → metadata PRADANA; `"php": "^8.1"` → `^8.2` (stack terkunci 8.2).
18. Config yang menyebut fitur mati (broadcasting/pusher/sanctum/mail) → rapikan nilai
    default + komentar supaya orang kantor tidak mengira fiturnya ada.

### Hasil Fase 4 (dikerjakan 9 Okt 2026)

Sampah yang dihapus — semua diverifikasi grep lebih dulu, dan **nol** view memakai
`@vite` (Bootstrap 5 dimuat dari CDN), jadi tidak ada satu pun layar yang berubah:

| Dihapus | Bukti tidak dipakai |
|---|---|
| `package.json`, `vite.config.js` | tidak ada `@vite`; `public/build` memang tidak pernah ada |
| `resources/js/{app,bootstrap}.js` + `resources/css/app.css` (direktori kosongnya ikut hilang) | tidak ada yang meng-include |
| `routes/channels.php`, `app/Providers/BroadcastServiceProvider.php`, `config/broadcasting.php` | providernya sudah dikomentari di `config/app.php` — baris komentarnya sekarang dibuang sekalian |
| `.gitignore`: `/node_modules`, `/public/build`, `/public/hot`, `npm-debug.log`, `yarn-error.log` | tidak ada lagi alat Node |
| `EventServiceProvider`: pasangan `Registered => SendEmailVerificationNotification` | kantor tanpa registrasi publik dan tanpa SMTP (L-11/L-12); akun dibuat lewat `arsip:akun-pertama` |
| `config/services.php`: blok Mailgun/Postmark/SES jadi `[]` + komentar | `grep config('services` → nol pemakai; Drive hidup di `config/gdrive.php` |

Butir 18 sebagian dikerjakan: broadcasting hilang total (bukan dirapikan nilainya),
`config/services.php` dikosongkan, `config/cors.php` ternyata sudah `'paths' => []`
sehingga `HandleCors` tidak pernah cocok — dibiarkan karena middleware itu tetap
membayar satu baca config per request dan tidak memengaruhi apa pun.

Metadata: `composer.json` jadi `pradana/arsip-surat` + deskripsi/keyword PRADANA,
`"php": "^8.1"` → `^8.2` (stack terkunci 8.2.12), `laravel/pint: "*"` → `^1.30`
(terkunci v1.30.4), skrip `post-update-cmd` (`vendor:publish --tag=laravel-assets`
— tidak ada paket frontend) dibuang. `license` **sengaja tidak disentuh**: MIT masih
tertulis, dan mengganti lisensi dokumen kantor itu keputusan user.

Kunci penting — menghindari jebakan yang tercatat (`dump-autoload -o` ±6,5 menit):
perubahan `require` membuat `composer.lock` *stale*, dan yang dipakai untuk memperbaikinya
adalah `composer update --lock --no-scripts --no-autoloader --no-plugins` → **9 detik**,
diff lock hanya 2 baris (content-hash + `platform.php`), **tidak ada versi paket yang
berubah**. Diverifikasi ulang dengan `composer validate` ("valid", peringatan lock hilang),
`composer install --dry-run` ("Nothing to install, update or remove"), dan
`config:cache` + `config:clear` yang tetap jalan — bukti `config/broadcasting.php` memang
tidak dibaca saat boot maupun saat caching.

Sengaja **tidak** dikerjakan (butuh `composer update` sungguhan: resolusi + jejaring,
bukan bagian "rapikan"): buang `laravel/sail` (terkunci v1.68.0, tidak pernah dipakai karena
dev-nya XAMPP) dan `nunomaduro/larastan` → `larastan/larastan` (composer sendiri sekarang
memperingatkan "Package nunomaduro/larastan is abandoned"). Kalau dikerjakan, itu satu pass
tersendiri + `dump-autoload` + suite penuh. `.env` lokal (tidak ter-commit) masih menyimpan
`BROADCAST_DRIVER=log` dan baris `VITE_*`; dibiarkan — tidak lagi dibaca sejak config file-nya
hilang, dan mengutak-atik env milik user tanpa keperluan bukan bagian rencana ini.

Gerbang: `Tests: 203 passed (805 assertions)` · `pint --test` PASS 169 file (sebelumnya 172 —
persis tiga file PHP yang dihapus) · `phpstan analyse` [OK] No errors · baseline tetap 5.

## Fase 5 — skema (hanya kalau terbukti perlu)

19. Index tambahan additive (BUKAN mengubah file squash): kandidat dari audit —
    `pemusnahan_arsip_items.(arsipable_type, arsipable_id)`, `draf_konten_surat_keluar.surat_keluar_id`
    (relasi HasOne tapi belum tentu unique), `aktivitas.(user_id, created_at)`,
    `pemusnahan_arsip.status`, `klasifikasi_primer_id` pada kedua tabel surat.
    Aturan: hanya index yang dibuktikan `EXPLAIN` dipakai, atau yang menopang kolom yang
    selalu difilter di layar nyata. Satu migration additive, `migrate` biasa, tanpa `migrate:fresh`.

### Hasil Fase 5 (dikerjakan 9 Okt 2026) — satu index, bukan lima

Langkah pertama bukan menulis migration, tapi membaca `SHOW INDEX` semua tabel di
MariaDB development dan membandingkannya dengan daftar kandidat butir 19. Hasilnya:

| Kandidat dari audit | Kenyataan di skema | Putusan |
|---|---|---|
| `pemusnahan_arsip_item.(arsipable_type, arsipable_id)` | **sudah ada** (dibuat `morphs`/`nullableMorphs` di squash) | tidak dikerjakan |
| `draf_konten_surat_keluar.surat_keluar_id` | **sudah ada, malah UNIQUE** | tidak dikerjakan |
| `pemusnahan_arsip.status` + `pengajuan_hapus_lampiran.status` | **sudah ada** | tidak dikerjakan |
| `klasifikasi_primer_id`/`_sekunder_id`/`_tersier_id`/`user_id` di kedua tabel surat | **sudah ada** (InnoDB wajib index untuk FK) | tidak dikerjakan |
| `aktivitas.(user_id, created_at)` | `user_id` ada; **`created_at` tidak ada** | satu-satunya yang diukur lalu ditambah |

Jadi empat dari lima kandidat sudah terpasang sejak lama. Kalau daftar itu dipercaya
apa adanya, perapian ini justru menambah empat index redundan di tabel yang paling
sering ditulis.

**Pengukuran** (DB tanding `pradana_test`, bukan `pradana`; 40.000 baris log + 8.000
surat masuk + 8.000 surat keluar, `ANALYZE TABLE` sebelum SETIAP perubahan — percobaan
pertama sempat memberi hasil menyesatkan karena optimizer mengganti rencananya hanya
karena statistik baru dihitung, dan itu ketahuan justru setelah ANALYZE dijadikan
bagian dari protokol):

| Query nyata | Sebelum | +`aktivitas(created_at,id)` |
|---|---|---|
| `/aktivitas` tanpa filter (halaman 1) | 22,53 ms · `type=ALL` + filesort 40k baris | **1,02 ms** · `type=index`, 30 baris |
| `/aktivitas` filter periode | 28,45 ms · ALL + filesort | **1,42 ms** · `range` |
| `arsip:bersihkan-log` ambil batch | 23,76 ms · scan PRIMARY | **1,00 ms** · `range` + `Using index` |
| daftar surat masuk polos | 2,16 ms (sudah pakai `tanggal_diterima` terbalik) | tidak berubah → komposit `(deleted_at, tanggal_diterima)` **ditolak** (1,93 vs 2,16 ms = noise) |
| daftar surat + `status_arsip` | 1,83 ms | tidak berubah |
| kandidat pemusnahan (`tanggal_surat < ? AND status_arsip='inaktif'`) | 9,04 ms | komposit `(status_arsip, tanggal_surat)` → 6,13 ms; **ditolak**: satu layar admin, menang 3 ms, bayar index keempat di tabel tersibuk |
| halaman 500 (`OFFSET 14970`) | 75 ms | 72,9 ms — index TIDAK memperbaiki offset dalam; itu sifat pagination limit-offset, dicatat, bukan pura-pura selesai |

**Dua temuan yang tidak bisa ditebak dari kode:**

1. **`created_at` saja tidak cukup.** Percobaan pertama membuat index satu kolom dan
   halaman log tetap `type=ALL + filesort` (23,9 ms). Urutan layar adalah
   `created_at DESC, id DESC`, dan MariaDB 10.4 tidak memakai suffix PK implisit pada
   secondary index untuk memenuhi ORDER BY dua kolom — `id` harus ditulis eksplisit di
   index. Makanya migration ini `(created_at, id)`, bukan `(created_at)`.
2. **Titik balik optimizer diukur, bukan diasumsikan.** Pada LIMIT 30: 1.000 baris →
   filesort 2,34 ms (dan itu **benar**, filesort memang lebih murah); 5.000 → 4,16 ms
   filesort; 8.000 → index, 0,97 ms; 30.000 → index, 1,31 ms. Manfaat index ini baru
   ada mulai ±8.000 baris log (±2 tahun kantor aktif). Karena itu
   `UrutAktivitasMariaDbTest` menanam 12.000 baris dan docblock migration mencatat
   titik baliknya — supaya nanti tidak ada yang mengira index ini "tidak bekerja" saat
   tabel masih kecil.

**Isi perubahan:** satu migration additive
`2026_10_09_000001_tambah_index_urut_aktivitas` (nama index eksplisit
`aktivitas_created_at_id_index`, `down()` drop dengan nama yang sama) + dua tes baru:
`SchemaIndexAktivitasTest` (2 tes, mesin-agnostic — `SHOW INDEX` di MariaDB,
`PRAGMA index_list`/`index_info` di SQLite — membuktikan index ADA dan kolomnya berurutan
`(created_at, id)`, plus menjaga index lama yang masih dipakai; catatan penting: index
bantu FK `aktivitas_user_id_foreign` hanya ada di MySQL/MariaDB, SQLite membuat constraint
tanpa index terpisah, jadi assertion itu digating per engine) dan
`UrutAktivitasMariaDbTest` (1 tes, MariaDB, skip dengan pesan kalau XAMPP mati — membuktikan
optimizer sungguh memakai index itu untuk halaman log + filter periode, dan untuk jalur
`arsip:bersihkan-log` hanya menuntut `possible_keys` karena ORDER BY id boleh memilih PRIMARY).
Dijalankan sungguhan di `pradana` development: `migrate` 30 ms, index muncul di `SHOW INDEX`,
3 baris log yang ada tidak tersentuh. DB tanding sudah disapu bersih dari index eksperimen
dan baris tanaman.

Gerbang: `Tests: 206 passed (819 assertions) / 0 skipped` · `pint --test` PASS 172 file ·
`phpstan analyse` [OK] No errors · baseline tetap 5.

## Fase 6 — sisa daftar "ditunda" (9 Okt, setelah Fase 5 hijau)

Dua dari empat item yang di Fase 3 dicatat sebagai "perubahan bentuk tanpa perubahan perilaku"
kerucut jadi satu, dan pembahasannya membuktikan penundaan itu benar:

**`UpdateStatusArsipRequest` — ternyata BUKAN refactor kosmetik.** Saat menyentuh method ini
ditemukan bug nyata: `SuratMasukController::updateStatusArsip()` dan
`SuratKeluarController::updateStatusArsip()` keduanya menulis flash ke kunci **`status`**,
yang tidak dirender `layouts/app.blade.php` (hanya `success`/`error`). Jadi setelah tombol
"Nyahkan"/"Aktifkan kembali", data berubah TAPI layarnya tidak memberi tahu apa pun — persis
kelas bug yang `FlashKonsistenTest` (Fase 1) supposedly tangkap. Kenapa lolos: pola guardrail
`/->with\('([a-z_]+)'\s*,/` hanya membaca `->with(` yang kutipnya ada di **baris yang sama**,
sedangkan kedua method ini menulis multi-line. Duplikasi tidak membuat bug itu lebih kecil —
ia membuat bug itu ada dua kali dan lebih mudah terlewat; itu alasan forms request bersama.

Isi Fase 6:
1. `app/Http/Requests/UpdateStatusArsipRequest.php` — satu sumber aturan
   (`required` + `Rule::in(['aktif','inaktif'])`, pesan berbahasa Indonesia, `dinonaktifkan()`),
   dipakai kedua controller. "musnah" tetap bukan nilai yang sah di sini (pemusnahan jalur sendiri, L-06).
2. Kedua controller: flash pindah ke `success`; impor `Rule` yang jadi tidak terpakai dibuang.
3. `FlashKonsistenTest` ditajamkan, dua lapis:
   - pola jadi `/->with\(\s*'([a-z_]+)'\s*,/s` (mengizinkan newline);
   - pembacaan file lewat **`token_get_all()`** dengan komentar (`T_COMMENT`, `T_DOC_COMMENT`,
     `T_INLINE_HTML`) dibuang — tanpa ini, docblock request baru yang MENULIS
     `->with('status', ...)` sebagai penjelasan bug malah dianggap flash sungguhan;
   - kunci yang dirender layout juga dibaca setelah komentar Blade/HTML (`{{-- --}}`, `{# #}`,
     `<!-- -->`) dibuang, supaya komentar tidak bisa "melegalkan" kunci liar.
   - tes perilaku baru `test_petugas_lihat_konfirmasi_setelah_menyahkan_surat`: PATCH →
     `status_arsip` berubah **dan** `session('success')` tidak null **dan** pesannya muncul di
     HTML halaman show. Diverifikasi terhadap file versi `7641d5f`: pola lama mengembalikan
     `success, status`, pola baru hanya `success`.
4. Docblock `UserController` diganti: masih mengaku "edit/delete/ganti-PIN belum dibuat" dan
   merujuk "Bagian 10" versi lama AGENTS.md, padahal L-07/L-12 selesai 4 Okt. Sekalian mencatat
   batasan yang benar-benar ada (tidak ada form `edit`/`update` → ubah peran = hapus + buat ulang).

Yang **tetap ditunda** setelah dilihat: `{!! sorot() !!}` → `{{ }}` (tidak bisa — `sorot()`
justru menghasilkan markup `<mark>`; keamanannya sudah ditegakkan di dalam `CariArsip::sorot()`
yang meng-escape sebelum menyisipkan tag, dan ada tes XSS-nya), deduplikasi
`SuratMasuk`↔`SuratKeluar` + tiga controller klasifikasi (butuh `@template`/dynamic class-string
yang akan menambah baseline Larastan; nilai praktisnya rendah karena perilaku keduanya sudah
dikunci tes), angka tanggal hard-coded di JS pratinjau kop (kosmetik, layar sudah terverifikasi
mata pada 5 Okt).

Gerbang: `Tests: 207 passed (824 assertions) / 0 skipped` · `pint --test` PASS 173 file ·
`phpstan analyse` [OK] No errors · baseline tetap 5.

## Analisis ulang (gerbang sebelum eksekusi)

Setiap butir di atas harus lolos tiga pertanyaan sebelum disentuh:
(a) apakah kutipan kode/barisnya masih cocok dengan kondisi sekarang,
(b) apakah perubahannya terukur oleh probe (fase 2) atau oleh tes perilaku (fase 1),
(c) apakah ada keputusan user [LOCKED] yang tersenggol — kalau ya, tidak dikerjakan
diam-diam (contoh: pagination seragam 20 vs `/aktivitas` yang 30 — itu H2/L-14,
sengaja saya tandai sebagai pertanyaan, bukan diubah sepihak).

## Gerbang verifikasi tiap fase

```bash
php artisan test            # harus tetap hijau; ambang query diturunkan, bukan dinaikkan
vendor/bin/pint --test      # gaya
vendor/bin/phpstan analyse  # Larastan level 5, tanpa menambah baseline
```

Diakhir tiap fase: commit terpisah + `AGENTS.md` Bagian 3 + `AGENTS_HISTORY.md` Bagian 13.
