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

## Fase 4 — sampah & metadata

16. Boilerplate yang tidak dipakai (frontend via CDN, tanpa Vite): `package.json`,
    `vite.config.js`, `resources/js/*`, `resources/css/*`, `routes/channels.php` —
    diverifikasi grep dulu (agen), baru dihapus.
17. `composer.json`: `"name": "laravel/laravel"`, `"description": "The skeleton
    application..."` → metadata PRADANA; `"php": "^8.1"` → `^8.2` (stack terkunci 8.2).
18. Config yang menyebut fitur mati (broadcasting/pusher/sanctum/mail) → rapikan nilai
    default + komentar supaya orang kantor tidak mengira fiturnya ada.

## Fase 5 — skema (hanya kalau terbukti perlu)

19. Index tambahan additive (BUKAN mengubah file squash): kandidat dari audit —
    `pemusnahan_arsip_items.(arsipable_type, arsipable_id)`, `draf_konten_surat_keluar.surat_keluar_id`
    (relasi HasOne tapi belum tentu unique), `aktivitas.(user_id, created_at)`,
    `pemusnahan_arsip.status`, `klasifikasi_primer_id` pada kedua tabel surat.
    Aturan: hanya index yang dibuktikan `EXPLAIN` dipakai, atau yang menopang kolom yang
    selalu difilter di layar nyata. Satu migration additive, `migrate` biasa, tanpa `migrate:fresh`.

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
