# AGENTS_HISTORY.md — PRADANA (Riwayat Sesi & Catatan Teknis Detail)

> Pasangan file dari `AGENTS.md` di folder yang sama (root project Laravel). Berisi Bagian 12 (Catatan Teknis Tambahan) & Bagian 13 (Riwayat Perubahan) yang dipindah **apa adanya, tanpa perubahan isi** dari `AGENTS.md` pada 1 September 2026, murni untuk mengurangi beban baca (lihat Bagian 0 `AGENTS.md` poin 8, dan baris terakhir tabel Bagian 13 di bawah).
>
> **Cara pakai**: buka file ini on-demand saat `AGENTS.md` atau dokumen lain merujuk ke sesuatu di sini (mis. "lihat 12.11", "Bagian 13"). Tidak perlu dibaca penuh di setiap sesi.

---

## 12. Catatan Teknis Tambahan

**12.1 — Nama kolom `lampiran` di `draf_konten_surat_keluar`.**
Ini teks notasi formal surat (contoh: `"1 Berkas"`), BUKAN file asli. Berbeda dari tabel/model `Lampiran` (file di Drive). Tidak bentrok secara teknis (beda model), tapi membingungkan saat baca kode. **Rekomendasi**: rename jadi `keterangan_lampiran` sebelum migration pertama kali dijalankan di environment manapun.

**12.2 — Enum disimpan lowercase.** (`mendesak`, `asli`, dst) — beda dari versi lama yang pakai kapital. Perlu helper `ucfirst()`/accessor untuk display di view.

**12.3 — `surat_keluar.nomor_surat` unique.** Asumsi nomor selalu auto-generated tanpa duplikat. Kalau nanti ada input manual berpotensi duplikat, tinjau ulang constraint ini setelah D1 dijawab.

**12.4 — Soft delete di `users`.** User tidak benar-benar dihapus (`deleted_at`), supaya riwayat arsip & aktivitas tidak kehilangan referensi. FK dari `surat_masuk`/`surat_keluar`/`aktivitas` ke `users` diset `restrictOnDelete()` sebagai lapis kedua.

**12.5 — `file_path` masih tersisa di `$fillable` model `SuratMasuk`/`SuratKeluar`.** Keputusan locked #8 bilang kolom ini sudah dihapus (diganti tabel `lampiran`), tapi kedua model yang di-upload user (26 Agu 2026) masih mendaftarkannya di `$fillable`. `SuratMasukController`/`SuratKeluarController` yang baru TIDAK memakai/mengisi field ini sama sekali. **Perlu dibersihkan** dari model (dan migration, kalau kolomnya beneran masih ada di DB) — belum dilakukan di sesi ini karena bukan bagian dari scope "buat controller" dan menyentuh file yang bukan milik controller ini.

**12.6 — Pola validasi hierarki klasifikasi.** `Store/UpdateSuratMasukRequest` dan `Store/UpdateSuratKeluarRequest` pakai `withValidator()` + closure `after()` untuk mastiin `klasifikasi_sekunder_id` beneran anak dari `klasifikasi_primer_id` yang dipilih (begitu juga tersier ke sekunder) — supaya dropdown cascading yang di-tamper di sisi client tidak bikin data nyasar. `UpdateSuratMasukRequest` sengaja `extends StoreSuratMasukRequest` (bukan class terpisah) karena aturannya identik — beda dengan `UpdateSuratKeluarRequest` yang punya rule `nomor_surat` tambahan (unique, ignore self) karena nomor surat keluar auto-generate & unique sedangkan nomor surat masuk manual & tidak unique. Ikuti pola validasi hierarki yang sama kalau nanti bikin form lain yang pakai 3 dropdown berjenjang ini (mis. form Klasifikasi sendiri kalau relevan).

**12.7 — Konvensi nama parameter route.** Controller baru pakai URI kebab-case (`surat-masuk`, `surat-keluar`) tapi parameter route model binding snake_case (`SuratMasuk $surat_masuk`, `SuratKeluar $surat_keluar`) — ini otomatis kalau route didaftarkan lewat `Route::resource('surat-masuk', SuratMasukController::class)` (Laravel ganti `-` jadi `_` di wildcard secara default). Ikuti pola sama untuk `klasifikasi-primer`/`klasifikasi-sekunder`/`klasifikasi-tersier`/`pengaturan-instansi` biar konsisten dengan controller yang sudah ada.

**12.8 — Logging `aktivitas` belum disambungkan ke controller manapun.** Sesuai roadmap ("Logging aktivitas otomatis di setiap aksi" masih item terpisah yang belum dikerjakan), `SuratMasukController`/`SuratKeluarController` SENGAJA tidak memanggil `Aktivitas::create()` manual di tiap action — kemungkinan lebih rapi diimplementasikan lewat Model Observer/Event supaya tidak perlu ditulis ulang di setiap controller (termasuk Klasifikasi & Pengaturan Instansi yang belum dibuat). Kalau nanti diputuskan pakai pemanggilan manual per-controller, cek dulu apakah sudah ada Observer supaya `aktivitas` tidak tercatat dobel.

**12.9 — `Lampiran.php`/`GoogleDriveService.php` sempat "hilang" antar sesi, sudah dilampirkan ulang.** File-file ini didesain 24 Agu 2026 (ada di `pradana-laravel-schema.zip`), tapi sesi yang membuat `SuratKeluarController` (26 Agu 2026) menulis di docblock-nya bahwa file itu "belum dibuat (dikonfirmasi user)" — artinya sesi tersebut tidak punya akses ke zip itu. Supaya tidak didesain ulang dari nol (dan berpotensi beda konvensi), file-file berikut dilampirkan ulang persis sama seperti versi asli, dibundel jadi `pradana-fix-lampiran-gdrive.zip`:
- `app/Models/Lampiran.php`
- `app/Services/GoogleDriveService.php`
- `config/gdrive.php`
- `database/migrations/..._create_lampiran_table.php`
- `app/Models/SuratMasuk.php` dan `app/Models/SuratKeluar.php` — **versi sudah dibersihkan** (lihat 12.10)

**Root cause kemungkinan**: masing-masing sesi chat cuma tahu apa yang di-upload/dibahas di sesi itu sendiri, dan sejauh ini belum ada satu project Laravel nyata yang jadi tempat semua deliverable ini disatukan (lihat Bagian 3, item "Project Laravel yang sebenarnya belum di-`composer create-project`"). **Rekomendasi kuat**: satukan semua file dari semua zip/sesi ke SATU repo git secepatnya, supaya AGENTS.md bisa merujuk ke kondisi nyata di repo, bukan ke "file yang mungkin ada di suatu riwayat chat".

**12.10 — `file_path` di `$fillable` `SuratMasuk`/`SuratKeluar` (lanjutan 12.5): sudah dibersihkan di file yang dilampirkan ulang.** Versi `SuratMasuk.php`/`SuratKeluar.php` di `pradana-fix-lampiran-gdrive.zip` (lihat 12.9) sudah tidak punya `file_path` di `$fillable`, dan sudah ditambah relasi `lampiran()` (`morphMany(Lampiran::class, 'lampiranable')`). **Timpa file model yang lama dengan versi ini** — jangan gabung manual, supaya tidak ada sisa `file_path` yang lolos.

**12.11 — CRUD Klasifikasi (Primer/Sekunder/Tersier): asumsi awal SALAH, sudah diperbaiki setelah model diupload user.**
`KlasifikasiPrimerController`/`KlasifikasiSekunderController`/`KlasifikasiTersierController` + 6 Form Request-nya awalnya dibuat murni dari skema Bagian 5 [LOCKED] tanpa melihat file model asli (persis pola masalah 12.9). User lalu mengupload `KlasifikasiPrimer.php`/`KlasifikasiSekunder.php`/`KlasifikasiTersier.php` yang sebenarnya (26 Agu 2026), dan ternyata:
- `$fillable` **cocok** dengan asumsi awal: `KlasifikasiPrimer` = `['kode', 'nama']`; `KlasifikasiSekunder` = `['klasifikasi_primer_id', 'kode', 'nama']`; `KlasifikasiTersier` = `['klasifikasi_sekunder_id', 'kode', 'nama']`.
- **Nama relasi SALAH** di asumsi awal, sudah diperbaiki di controller: `KlasifikasiPrimer::sekunder()` (bukan `klasifikasiSekunder()`), `KlasifikasiSekunder::primer()` (bukan `klasifikasiPrimer()`) + `::tersier()` (bukan `klasifikasiTersier()`), `KlasifikasiTersier::sekunder()` (bukan `klasifikasiSekunder()`). Model asli pakai nama pendek tanpa prefix `klasifikasi`.
- Panjang kolom `kode`/`nama` di `klasifikasi_sekunder`/`klasifikasi_tersier` **masih asumsi** (sama seperti `klasifikasi_primer`: `kode` max 5, `nama` max 100) — model yang diupload tidak mendeklarasikan panjang kolom (itu ranah migration, bukan model), migration aslinya belum diperiksa.

File final (v2, sudah fix) dibundel `pradana-crud-klasifikasi.zip`. **Pelajaran untuk agent berikutnya**: kalau mengandalkan asumsi nama relasi/struktur model karena model tidak tersedia, SELALU tandai eksplisit di docblock controller (bukan cuma di AGENTS.md) — itu yang mempercepat proses koreksi begitu user upload file aslinya.

**Keputusan desain Klasifikasi (belum tercatat di Bagian 8/9, tambahkan kalau mau di-lock):**
- Route `show` untuk ketiga level Klasifikasi **sengaja tidak didaftarkan** (`->except(['show'])`) — dianggap tidak perlu untuk tabel referensi sederhana; halaman `index` sudah menampilkan list + relasi induk.
- Mutasi (create/store/edit/update/destroy) di ketiga controller Klasifikasi memakai pengecekan admin **manual** (`ensureAdmin()` per controller, `abort_unless(Auth::user()->role === 'admin', 403, ...)`), BUKAN middleware/Gate resmi — konsisten dengan keputusan default B3, tapi masih ad-hoc karena B1 masih blocked. Semua diberi `// TODO(B1)`.
- Delete yang melanggar FK restrict (`surat_masuk`/`surat_keluar` → `klasifikasi_primer_id`) ditangkap lewat `catch (QueryException $e)` dan diubah jadi pesan error ramah-pengguna, bukan dibiarkan crash 500.

**12.12 — CRUD Surat Masuk dibangun ulang dari nol 26 Agu 2026; status disengketakan sudah RESOLVED.**
User mengonfirmasi langsung: file `SuratMasukController.php` lama memang stub kosong, dan `Store/UpdateSuratMasukRequest.php` **memang belum pernah dibuat sama sekali** — klaim "selesai" di riwayat AGENTS.md versi sebelumnya (opsi (b) di catatan lama Bagian 3) **keliru**, bukan cuma file lupa diupload. Dibangun ulang di sesi ini: `SuratMasukController` + `StoreSuratMasukRequest` + `UpdateSuratMasukRequest`, dibundel `pradana-crud-surat-masuk.zip`.

Pola yang diikuti (sesuai AGENTS.md sebelum controller ini dibuat):
- `UpdateSuratMasukRequest` extends `StoreSuratMasukRequest` **tanpa override apa pun** (rules identik) — sesuai 12.6, beda dengan Surat Keluar karena `nomor_surat` surat masuk manual & TIDAK unique.
- Validasi hierarki klasifikasi (`withValidator()` + `after()`) mengikuti pola yang sama seperti disebut di 12.6.
- D5 [DEFAULT] diterapkan: `tanggal_diterima` `after_or_equal:tanggal_surat`.
- Auth: `middleware('auth')` polos untuk semua action KECUALI `destroy()` yang admin-only manual (`ensureAdmin()`, `TODO(B1)`) berdasarkan **asumsi sementara** B2 — **beda dengan Klasifikasi** yang admin-only di SEMUA mutasi (B3, sudah [DEFAULT]). Jangan disamakan. ⚠️ **Koreksi 26 Agu 2026** (lihat 12.13): B2 SALAH ditulis di sini seolah sudah [DEFAULT] — B2 sebenarnya masih di tabel [WAJIB TANYA USER] Bagian 10, cuma kebetulan asumsi sementaranya juga "admin only". Docblock controller sudah diperbaiki untuk bilang "asumsi sementara", bukan "keputusan default".
- `user_id` diisi otomatis dari `Auth::id()` saat `store()`, tidak ada di form/tidak bisa diubah lewat `update()`.
- `status_arsip` di-hardcode `'aktif'` saat `store()`, TIDAK ada di form Store/Update — toggle ke "inaktif" sengaja belum diimplementasikan karena itu bagian dari fitur "Manajemen retensi/penyusutan" yang terpisah di roadmap (blocked by E1). Kalau nanti E1 dijawab dan fitur retensi mulai dikerjakan, cek dulu apakah butuh route/action baru atau cukup nambah field ke Update request ini.

✅ **Model `SuratMasuk.php` sudah diupload user & diverifikasi (26 Agu 2026, lanjutan sesi yang sama).** Hasilnya:
- `$fillable` **cocok 100%** dengan field yang dipakai di `Store/UpdateSuratMasukRequest` — tidak ada field yang perlu disesuaikan.
- `SuratMasuk::primer()`, `::sekunder()`, `::tersier()` — **tebakan awal BENAR**, tidak perlu diubah.
- `SuratMasuk::lampiran()` (morphMany ke `Lampiran`) — **benar**, sesuai 12.10.
- `SuratMasuk::user()` — **tebakan awal SALAH**. Relasi ke `users` ternyata bernama **`petugas()`** (`belongsTo(User::class, 'user_id')`), bukan `user()`. Sudah **diperbaiki** di `with()`/`load()` pada `index()`/`show()` di `SuratMasukController`.
- `$casts`: `tanggal_surat` dan `tanggal_diterima` di-cast `'date'` di model — tidak berpengaruh ke controller/Form Request yang sudah dibuat (validasi `'date'` tetap kompatibel), tapi berguna diketahui untuk pembuatan view nanti (format tanggal otomatis jadi `Carbon`, bukan string mentah).

File final (v2, sudah fix `petugas()` + koreksi komentar B2) tetap dibundel `pradana-crud-surat-masuk.zip`.

**12.13 — `SuratKeluarController.php` + `UpdateSuratKeluarRequest.php` (kode ASLI, bukan tulisan ulang) diupload user 26 Agu 2026 untuk cross-check terhadap model `SuratKeluar.php` yang diverifikasi sebelumnya. Hasil: TIDAK ADA BUG relasi.**
Beda dengan Klasifikasi & Surat Masuk (yang sempat salah tebak nama relasi karena ditulis ulang tanpa model), untuk Surat Keluar kode controller aslinya sendiri yang di-cross-check ke model asli — dan semuanya cocok:
- `primer()`, `sekunder()`, `tersier()`, `petugas()`, `drafKonten()` (hasOne ke `DrafKontenSuratKeluar`) — **semua dipakai dengan benar** di `index()`/`show()`/`create()`/`edit()`.
- Field di `UpdateSuratKeluarRequest` **cocok 100%** dengan `$fillable` model (`user_id` sengaja tidak ada di rules, konsisten dengan pola yang sama di Surat Masuk — user_id bukan input form).
- D2/D3 [DEFAULT] diimplementasikan benar: `generateNomorSurat()` filter `whereYear('tanggal_surat', $tahun)` (reset tiap tahun, D2) tanpa filter klasifikasi (global per tahun, D3), pakai `lockForUpdate()` + retry 3x di dalam `DB::transaction()` untuk hindari race condition nomor kembar — lebih robust dari yang saya duga sebelumnya.
- D1 (format nomor final) benar ditandai `TODO(D1)`, placeholder `"{urutan}/SK/{tahun}"`.

**Temuan lain (bukan bug, tapi perlu dicatat):**
1. **Koreksi status B2** (lihat perbaikan di atas): controller asli ini justru BENAR menandai B2 sebagai `TODO(B2)`/`[WAJIB TANYA USER]` ("...ini masih [WAJIB TANYA USER] di AGENTS.md, jadi konfirmasi ulang ke user") — lebih hati-hati dari komentar saya sendiri di `SuratMasukController` yang sempat keliru bilang B2 sudah `[DEFAULT]`. Sudah saya perbaiki (lihat di atas).
2. **Komentar `file_path` di docblock controller sudah BASI/resolved** — controller ini punya catatan besar bilang "model SuratKeluar yang di-upload masih punya 'file_path' di $fillable... baiknya dibersihkan". Tapi model `SuratKeluar.php` yang sekarang (diverifikasi 26 Agu 2026) **sudah TIDAK punya** `file_path` di `$fillable` — sudah dibersihkan lewat 12.10. Jadi catatan itu describe masalah yang **sudah selesai**, cuma komentarnya belum diupdate di file aslinya. Tidak berdampak fungsional (controller tidak pernah pakai `file_path`), tapi kalau sempat, baiknya komentar itu dihapus/diupdate di `SuratKeluarController.php` biar tidak membingungkan agent berikutnya.
3. `destroy()` di `SuratKeluarController` **tidak** membungkus `->delete()` dengan `try/catch (QueryException $e)` seperti pola yang saya pakai di Klasifikasi/Surat Masuk — kalau `surat_keluar` masih direferensikan `draf_konten_surat_keluar`/`lampiran` dengan FK restrict (bukan cascade), delete bisa crash 500 alih-alih pesan ramah. Belum tentu masalah (tergantung `onDelete` di migration masing-masing, yang belum diperiksa) — dicatat sebagai potensi minor inconsistency, bukan bug pasti.
4. `create()`/`edit()` di `SuratKeluarController` eager-load **seluruh pohon klasifikasi sekaligus** (`KlasifikasiPrimer::with('sekunder.tersier')`) supaya dropdown cascading bisa difilter di client-side tanpa round-trip tambahan — pola lebih bagus dibanding `SuratMasukController` yang cuma load list primer flat. Kalau nanti bikin view untuk keduanya, pertimbangkan samakan ke pola Surat Keluar ini.
5. `index()` di `SuratKeluarController` sudah punya filter (`tahun`, `status_arsip`, `klasifikasi_primer_id`, pencarian teks `cari`) + `withQueryString()` — jauh lebih lengkap dari `SuratMasukController::index()` yang masih polos tanpa filter. Bukan bug (di luar scope asli), tapi kalau user mau konsistensi fitur, `SuratMasukController::index()` bisa ditambah filter serupa di sesi lain.

**Update 28 Agu 2026 — `StoreSuratKeluarRequest.php` sudah di-cross-check, bersih.** Semua field cocok `$fillable` model `SuratKeluar`; `nomor_surat` dan `user_id` sengaja dikecualikan dari rules (dokumentasi alasannya sudah jelas di file: `user_id` diisi otomatis dari `Auth::id()` seperti pola `StoreSuratMasukRequest`, `nomor_surat` di-override oleh `generateNomorSurat()` di `store()` controller, bukan input form — konsisten dengan dugaan sebelumnya). Validasi hierarki klasifikasi konsisten dengan `UpdateSuratKeluarRequest` (pola 12.6). **Dengan ini, ketiga file asli CRUD Surat Keluar (`SuratKeluarController.php`, `StoreSuratKeluarRequest.php`, `UpdateSuratKeluarRequest.php`) 100% terverifikasi terhadap model asli — tidak ada satu pun bug ditemukan.**

⚠️ Catatan proses (bukan soal isi filenya, soal alur sesi): berkas `StoreSuratKeluarRequest.php` sendiri **tidak ikut di-upload ke sesi ini** — temuan di atas direlai dari cross-check yang sudah dilakukan sebelumnya. Kalau file finalnya ada, sebaiknya tetap dilampirkan ke sesi sinkronisasi berikutnya (sesuai aturan "selalu upload file HASIL AKHIR" di Bagian 3) supaya tidak jadi kasus "hilang antar sesi" seperti `Lampiran.php`/`GoogleDriveService.php` di 12.9.

**CRUD Pengaturan Instansi**: user secara eksplisit minta ditunda ("nanti aja di agent lain") pada 26 Agu 2026 — JANGAN dikerjakan di sesi lanjutan tanpa diminta ulang, meski statusnya tetap "belum diklaim" (siapapun boleh ambil kalau user memang minta).

**Belum disentuh sama sekali sampai titik ini**: view/frontend (semua modul), CRUD Pengaturan Instansi (sengaja ditunda atas permintaan user), route Lampiran, model `SuratKeluar.php`/`Lampiran.php`/`PengaturanInstansi.php`/`DrafKontenSuratKeluar.php` (belum pernah di-cross-check ulang terhadap file asli, lihat Bagian 4).

**12.14 — Auth (Login/Register/Logout): scaffold dibuat 28 Agu 2026, MASIH ASUMSI, belum final.**
`AuthController.php` (login/logout) + `UserController.php` (registrasi user baru oleh admin) + `LoginRequest.php` + `StoreUserRequest.php` dibuat murni dari Bagian 2/8/9/10 AGENTS.md — **`User.php` model asli belum pernah di-upload ke sesi manapun**, jadi ini persis pola masalah yang sama seperti 12.9/12.11 (nulis kode sebelum lihat file asli). Detail keputusan & asumsi:

- **Mekanisme login** ikut A1 [DEFAULT]: `Auth::attempt(['email' => .., 'password' => $pin])`. Ini **tidak butuh perubahan `config/auth.php`** — `EloquentUserProvider` selalu memanggil `$user->getAuthPassword()` untuk ambil hash pembanding, apa pun nama kolomnya. Yang dibutuhkan cuma override method itu di `User.php` supaya return `$this->attributes['pin']`, sesuai keputusan locked #5/Bagian 2. **Belum diverifikasi** apakah override ini benar-benar sudah ada di model asli — kalau belum, login akan selalu gagal.
- **PIN hashing saat registrasi**: `UserController::store()` manual panggil `Hash::make($pin)` sebelum `User::create()`. **Berisiko double-hash** kalau `User.php` ternyata sudah punya mutator `setPinAttribute()` yang auto-hash — belum bisa dipastikan tanpa model asli. Sudah ditandai jelas di docblock `UserController::store()`.
- **A3 (kebijakan registrasi) — SUDAH dikonfirmasi user 28 Agu 2026: admin bikin akun langsung** (bukan self-register publik). Sekarang [LOCKED], dipindah ke Bagian 8 #11. Desain yang sudah dibuat (admin-only lewat `UserController@store`, tanpa kolom status approval) **sudah sesuai**, tidak perlu diubah. `TODO(A3)` di kode sudah dihapus.
- **A6 (2FA)** tidak diimplementasikan sama sekali, sesuai asumsi sementara "tidak pakai dulu" — tidak ada TODO khusus karena dampaknya kecil kalau salah asumsi (tinggal ditambah belakangan, tidak mengubah struktur yang sudah ada).
- **A4 (reset PIN oleh admin)** dan **A5 (user ganti PIN sendiri)** **belum diimplementasikan sama sekali** — di luar scope "Login/Register/Logout" yang diminta sesi ini. Kandidat kerjaan terpisah berikutnya, kemungkinan masuk sebagai method tambahan di `UserController` (untuk A4) dan controller/halaman profil sendiri (untuk A5).
- ~~**Redirect setelah login** sementara ke `/`~~ **SUDAH diarahkan ke `route('dashboard')`** — `DashboardController` + route-nya dibuat 28 Agu 2026 (lihat 12.15), TODO lama di `AuthController::login()` sudah dibereskan.
- **Belum ada view** (`auth.login`, `users.index`, `users.create`) — terpisah dari scope ini, tergantung G1 (Blade+Bootstrap vs stack lain) yang juga masih [WAJIB TANYA USER].
- ~~**Route belum digabung ke `web.php` asli**~~ **SUDAH digabung 28 Agu 2026** — `routes/web.php` asli di-upload user, route Auth (grup `guest` untuk login, `logout`+`users.*` masuk ke grup `auth` yang sudah ada) ditambahkan langsung ke file itu tanpa mengubah route existing (klasifikasi/surat-masuk/surat-keluar).

**Pelajaran yang sama seperti 12.9/12.11**: begitu `User.php` asli tersedia, WAJIB cross-check `getAuthPassword()`, `$fillable`, dan ada/tidaknya mutator PIN sebelum menganggap Auth ini selesai.

**Update 28 Agu 2026 — `User.php` & `web.php` asli di-upload, di-cross-check, semua asumsi teknis TERKONFIRMASI BENAR:**
- `getAuthPassword()` sudah ada di model, return `$this->pin` — persis sesuai locked #5/Bagian 2. Login jalan tanpa perubahan.
- `$fillable` = `nama_lengkap, email, pin, role` — cocok 100% dengan `StoreUserRequest`/`UserController::store()`.
- **Tidak ada mutator `setPinAttribute()`** — `Hash::make()` manual di `UserController::store()` memang diperlukan, bukan risiko double-hash. Dikonfirmasi aman.
- Model punya helper `isAdmin(): bool` yang belum saya pakai sebelumnya — `UserController::ensureAdmin()` sudah diupdate untuk pakai ini alih-alih cek `role === 'admin'` manual. **Catatan untuk sesi lain**: controller lain (`KlasifikasiPrimerController`/`SuratMasukController`/`SuratKeluarController`, per 12.11/12.12) kemungkinan masih cek role manual — bisa disamakan ke `isAdmin()` nanti kalau mau konsisten, tapi file-file itu tidak di-upload ke sesi ini jadi tidak diubah.
- `User` pakai `SoftDeletes` — dicek: `Auth::attempt()`/`EloquentUserProvider` otomatis hormati global scope soft-delete, jadi user yang di-soft-delete otomatis tidak bisa login. Tidak perlu penanganan tambahan.
- Route Auth **sudah digabung ke `web.php` asli** (bukan snippet lagi): grup `guest` baru untuk `login`, grup `auth` yang sudah ada ditambah `logout` + `Route::resource('users', ...)->only(['index','create','store'])`. Route existing (klasifikasi/surat-masuk/surat-keluar) tidak diubah sama sekali.
- **Dengan A3 dikonfirmasi 28 Agu 2026, Auth (Login/Register/Logout) resmi SELESAI & 100% cross-checked** — tidak ada lagi yang mengganjal untuk scope ini. Sisa pekerjaan terkait (view, middleware role B1, ganti/reset PIN A4/A5) adalah item terpisah, bukan bagian dari "Login/Register/Logout".

**12.15 — DashboardController: dibuat 28 Agu 2026, widget bebas pilihan (bukan spek eksplisit user).**
Item roadmap Bagian 11 cuma bilang "dashboard statistik" tanpa rincian widget. `DashboardController.php` dibuat dengan widget berikut, dipilih dari skema Bagian 5 yang tersedia — **gampang diubah kalau user mau tampilan lain**:
- Statistik hitung (total surat masuk/keluar, per bulan ini, per `status_arsip` aktif/inaktif, jumlah `sifat=mendesak` yang masih aktif).
- Surat masuk & surat keluar terbaru (5 masing-masing), eager-load relasi `primer` + `petugas`.
- Klasifikasi primer terpopuler (top 5 by jumlah surat masuk), eager-load `primer`.
- Aktivitas terbaru (10 item), eager-load `user`.

Nama relasi yang dipakai (`primer`, `petugas`) **sudah terverifikasi benar** di sesi lain (lihat 12.11/12.12/12.13) — aman dipakai tanpa cross-check ulang.

⚠️ **Satu relasi TIDAK terverifikasi**: `Aktivitas::user()`. Model `Aktivitas.php` belum pernah di-upload ke sesi manapun sampai titik ini. Ditebak `user()` (bukan `petugas()`) karena `User::aktivitas()` (dari `User.php` yang sudah dicek) adalah `hasMany` polos tanpa custom key/nama — kemungkinan besar pasangannya simetris `belongsTo(User::class)`. Beda kasus dengan `petugas()` di surat_masuk/surat_keluar yang punya alasan historis spesifik (field teks "Petugas" lama diubah jadi FK, Bagian 8 #2) — `aktivitas.user_id` tidak punya sejarah serupa. **Cross-check begitu `Aktivitas.php` tersedia**, sama seperti pola 12.9/12.11/12.12.

**Update 28 Agu 2026 — `Aktivitas.php` asli di-upload, tebakan TERKONFIRMASI BENAR:**
`user()` return `belongsTo(User::class)`, persis sesuai dugaan. `$fillable` = `user_id, aksi, subjek_type, subjek_id` — cocok 100% dengan skema Bagian 5. Model juga punya `subjek()` (`morphTo()`) untuk relasi polymorphic ke `surat_masuk`/`surat_keluar` — **belum dipakai** di `DashboardController` saat ini, tapi tersedia kalau nanti mau tampilkan "aktivitas ini soal surat yang mana" (misalnya link dari log aktivitas ke detail suratnya). **Dengan ini, DashboardController 100% cross-checked — tidak ada bug relasi sama sekali.**

Route `dashboard` (nama route `dashboard`, method `index()`) sudah ditambahkan ke grup `auth` di `web.php`. **Tidak ada pembatasan role** — dashboard bisa diakses semua role yang login, mengikuti pola versi lama ("semua role akses semua menu") karena B1 masih [WAJIB TANYA USER] tanpa default aman.

Sebagai bonus, TODO lama di `AuthController::login()` (redirect sementara ke `/` karena dashboard belum ada) **sudah dibereskan** — sekarang redirect ke `route('dashboard')` yang sungguhan.

Belum ada view (`dashboard.index`) — tergantung G1, sama seperti modul lain.

**12.16 — `LampiranController.php` dibuat 31 Agu 2026: BUKAN revisi, file ini benar-benar belum pernah ada sebelumnya.**

⚠️ **Koreksi klaim lama**: baris "Integrasi Google Drive" di Bagian 3 (dan paragraf di Bagian 4 soal isi `pradana-laravel-schema.zip`) sejak awal dokumen ini dibuat (24 Agu 2026) **keliru** mengklaim `LampiranController.php` sudah ada bareng `GoogleDriveService`. User mengonfirmasi langsung file ini **belum pernah dibuat sama sekali di sesi manapun** — yang benar-benar ada dari awal cuma migration `create_lampiran_table.php`. Sudah diperbaiki di Bagian 3 & 4.

User mengupload 5 file asli untuk sesi ini: migration `lampiran`, `GoogleDriveService.php`, `Lampiran.php`, `web.php`, `config/gdrive.php` — **semua dipakai langsung tanpa tebakan** (beda dengan pola masalah 12.9/12.11/12.14 yang nulis kode duluan baru cross-check belakangan). Temuan dari file asli:
- `Lampiran::pengunggah()` — relasi ke `users` (`belongsTo(User::class, 'diunggah_oleh')`) bernama **`pengunggah()`**, BUKAN `diunggahOleh()` seperti yang mungkin ditebak dari nama kolom. Belum dipakai di `LampiranController` (tidak perlu load nama pengunggah untuk upload/unduh/hapus), tapi dicatat di sini supaya sesi lain yang bikin view tidak menebak salah.
- `GoogleDriveService::upload()` return array `['id','name','mime_type','size']`; `getFileContent()` return `['content','mime_type','name']`; `getOrCreateFolder(string $name, string $parentId): string`; `delete(string $fileId): void` — semua dipakai persis sesuai signature asli.

**Route baru** (digabung ke `web.php` asli, grup `middleware('auth')`):
- `POST surat-masuk/{surat_masuk}/lampiran` → `storeForSuratMasuk()`
- `POST surat-keluar/{surat_keluar}/lampiran` → `storeForSuratKeluar()`
- `GET lampiran/{lampiran}/unduh` → `download()` (stream dari Drive API, sesuai aturan Bagian 2 "Aturan file di DB")
- `DELETE lampiran/{lampiran}` → `destroy()`

**ASUMSI yang masih perlu dikonfirmasi/dicek** (belum ada kode `WAJIB TANYA USER` khusus untuk ini — ini detail implementasi dari C3 [DEFAULT] yang belum dirinci, bukan keputusan baru yang mengubah skema):
1. **Struktur folder Drive**: `config('gdrive.root_folder_id')` (dari `GOOGLE_DRIVE_ROOT_FOLDER_ID` di `.env`) diperlakukan **sama dengan** folder "Arsip_PRADANA" itu sendiri — kode TIDAK membuat folder "Arsip_PRADANA" terpisah di dalamnya. Kalau ternyata root folder di `.env` dimaksudkan sebagai *parent* dari "Arsip_PRADANA" (bukan folder itu sendiri), perlu ditambah satu level `getOrCreateFolder('Arsip_PRADANA', ...)` di `resolveFolderId()`. Tidak ada `Code.gs` asli yang bisa dicek untuk memastikan perilaku versi lama persis seperti apa.
2. **Level klasifikasi untuk subfolder** cuma **primer** (format nama folder: `"{kode} - {nama}"`), tidak turun ke sekunder/tersier — C3 cuma bilang `[Klasifikasi]` generik tanpa rincian level.
3. Kolom `pengaturan_instansi.gdrive_folder_surat_masuk_id`/`gdrive_folder_surat_keluar_id` dipakai sebagai cache **persis** sesuai instruksi Bagian 7 langkah 4. Kolom `pengaturan_instansi.gdrive_root_folder_id` (yang juga ada di skema Bagian 5) **tidak disentuh** oleh controller ini — belum jelas apakah kolom itu untuk tujuan lain atau cuma duplikat env var.
4. Model **`PengaturanInstansi.php` masih belum pernah di-cross-check** (lihat Bagian 4) — cache folder ID ditulis lewat assignment atribut langsung + `save()` (BUKAN `update([...])`) justru untuk menghindari ketergantungan pada `$fillable` yang belum pasti. Begitu model asli tersedia, cross-check apakah kedua kolom cache itu memang ada & bisa diisi.
5. Endpoint upload menerima **banyak file sekaligus** (`files[]`), bukan satu file per submit — mendukung "multi-lampiran per surat" (keputusan locked #8) dalam satu form. Gampang diubah ke single-file kalau ternyata itu yang diinginkan.
6. **`destroy()` dibuat admin-only** (`TODO(B2)`), mengikuti pola asumsi sementara B2 ("siapa boleh hapus arsip surat permanen?") — padahal B2 aslinya bicara soal hapus SURAT, bukan lampiran individual. Ini keputusan baru tanpa preseden eksplisit; kalau user mau kebijakan berbeda (mis. siapa saja yang mengunggah boleh menghapus lampirannya sendiri), tinggal sesuaikan `ensureAdmin()` di controller.
7. `download()` pakai `Content-Disposition: inline` (tampil langsung di browser), bukan `attachment` (paksa unduh) — supaya PDF/gambar bisa langsung dilihat tanpa download manual.
8. `destroy()` tetap menghapus record DB meskipun hapus file di Drive gagal (di-*catch*, dicatat ke `Log::warning`) — supaya tidak ada lampiran "hantu" yang tidak bisa dihapus dari UI cuma karena error sesaat di Drive API.

⚠️ **Superseded 31 Agu 2026 (lihat 12.17)**: poin 6 di atas (`destroy()` admin-only langsung) dan asumsi struktur folder di poin 1-2 **sudah digantikan** — poin 1-2 dikonfirmasi jadi keputusan final (kode C6), method `destroy()` dihapus total, diganti alur pengajuan+persetujuan.

⚠️ **Superseded lagi 31 Agu 2026 (sesi baru, lihat 12.18)**: poin 4 di atas (model `PengaturanInstansi.php` belum di-cross-check) **sekarang resolved** — model asli diupload & di-cross-check saat membuat `PengaturanInstansiController`, terkonfirmasi `gdrive_*` memang tidak ada di `$fillable` (jadi pendekatan assignment langsung di poin 4 itu sendiri terbukti memang perlu). Poin 3, 5, 7-8 masih berlaku (belum berubah).

**Belum tersentuh**: view/form upload & daftar lampiran (tergantung G1, sama seperti modul lain), dan model `SuratKeluar.php`/`PengaturanInstansi.php`/`DrafKontenSuratKeluar.php` (masih belum pernah di-cross-check ulang — lihat Bagian 4).

**12.17 — Struktur folder Drive dikonfirmasi (C6) + fitur baru "Pengajuan Hapus Lampiran" dibuat 31 Agu 2026.**

User menjawab 3 hal sekaligus dalam satu pesan, tanpa diminta satu-satu:

1. **Konfirmasi struktur folder Drive** ("pakai env aja, sub folder level primer saja") — 2 poin ASUMSI di 12.16 (poin 1 & 2) **sekarang final**, dicatat sebagai kode **C6** baru di Bagian 9 [DEFAULT]:
   - `GOOGLE_DRIVE_ROOT_FOLDER_ID` di `.env` **adalah** folder "Arsip_PRADANA" itu sendiri — kode TIDAK membuat subfolder "Arsip_PRADANA" terpisah.
   - Subfolder klasifikasi cukup level **primer** saja, tidak turun ke sekunder/tersier.
   - `LampiranController::resolveFolderId()` **tidak berubah kodenya sama sekali** — implementasi dari sesi sebelumnya sudah pas dengan konfirmasi ini. Cuma docblock-nya yang diupdate dari "ASUMSI belum dikonfirmasi" jadi "dikonfirmasi user".

2. **E1 terjawab**: retensi arsip **5 tahun seragam** untuk semua jenis surat (bukan per klasifikasi sesuai JRA resmi) — dipindah dari Bagian 10 [WAJIB TANYA USER] ke Bagian 8 [LOCKED] #12. User sadar ini **bisa saja tidak 100% sesuai regulasi kearsipan resmi** (JRA), tapi itu pilihan yang diambil.

3. **Fitur baru, bukan jawaban atas pertanyaan yang sudah ada**: *"hapus lampiran hanya untuk surat yang berumur diatas 5 tahun, staf mengajukan lalu admin approve"*. Ini keputusan bisnis baru yang belum pernah ditanyakan sebelumnya (tidak ada kode WAJIB TANYA USER untuk ini) — dicatat sebagai **LOCKED #13 (kode baru E4)**, karena langsung datang sebagai keputusan final dari user, bukan pertanyaan terbuka.

   **Implementasi**: `LampiranController::destroy()` (dibuat 12.16, admin-only langsung) **DIHAPUS TOTAL** — method itu sudah tidak mencerminkan kebijakan yang benar sama sekali (tidak ada pengecekan umur, tidak ada alur approval). Digantikan controller baru `PengajuanHapusLampiranController` + tabel baru `pengajuan_hapus_lampiran` (model `PengajuanHapusLampiran`) — **skema sekarang 11 tabel**, bukan 10 (lihat Bagian 5, catatan di atas heading).

   **Alur**: `store()` (siapa saja yang login boleh mengajukan) → validasi umur surat >5 tahun & belum ada pengajuan `menunggu` lain untuk lampiran yang sama → simpan `PengajuanHapusLampiran` (snapshot nama file, status `menunggu`) → admin lihat lewat `index()` → `setujui()` (baru benar-benar hapus Drive+DB) atau `tolak()` (cuma update status+catatan, lampiran tidak disentuh).

   **Route** (menggantikan `DELETE lampiran/{lampiran}` yang sempat ada):
   - `POST lampiran/{lampiran}/pengajuan-hapus` → `store()`
   - `GET pengajuan-hapus-lampiran` → `index()` (admin)
   - `POST pengajuan-hapus-lampiran/{pengajuan_hapus_lampiran}/setujui` → `setujui()` (admin)
   - `POST pengajuan-hapus-lampiran/{pengajuan_hapus_lampiran}/tolak` → `tolak()` (admin)

   **Desain FK penting**: `pengajuan_hapus_lampiran.lampiran_id` pakai `nullOnDelete()` (BUKAN cascade) + kolom `nama_file_snapshot` terpisah — supaya riwayat pengajuan (siapa mengajukan, kapan, siapa approve) **tetap ada untuk audit** meskipun `lampiran`-nya sendiri sudah benar-benar terhapus setelah disetujui.

   **ASUMSI yang masih perlu dicek** (belum eksplisit dikonfirmasi user):
   - **Kolom tanggal acuan "umur surat"**: `tanggal_diterima` untuk `surat_masuk` (tanggal masuk ke arsip), `tanggal_surat` untuk `surat_keluar` (tidak punya `tanggal_diterima` — lihat skema Bagian 5). Masuk akal secara logika kearsipan, tapi belum ditanyakan eksplisit.
   - `tolak()` (penolakan admin dengan `catatan_admin`) **ditambahkan sebagai pelengkap wajar** dari "admin approve" — user cuma sebut "approve", tidak sebut skenario tolak. Kalau ternyata tidak diinginkan, tinggal hapus route+method-nya, tidak mempengaruhi bagian lain.
   - **Siapa yang boleh mengajukan** (`store()`) dibuat **siapa saja yang login** (tidak dibatasi role `perangkat`/`kepala` vs `admin`) — user bilang "staf mengajukan", belum jelas apakah "staf" secara eksplisit HARUS bukan admin (mis. admin dilarang mengajukan ke dirinya sendiri). Saat ini admin juga bisa memakai `store()` (tidak ada larangan eksplisit), tapi cuma admin yang bisa `setujui()`/`tolak()` — jadi tidak ada risiko admin approve pengajuannya sendiri tanpa proses, prosesnya tetap 2 langkah.
   - **Belum menjawab E3** (alur pemusnahan arsip/SURAT dengan berita acara) maupun **B2** (siapa boleh hapus SURAT permanen) — dua-duanya masih [WAJIB TANYA USER] terpisah, lihat catatan tambahan di baris E3 Bagian 10.

   **Belum tersentuh**: view untuk form pengajuan & daftar antrian admin (tergantung G1, sama seperti modul lain), notifikasi ke admin saat ada pengajuan baru (di luar scope — H1 masih [WAJIB TANYA USER] soal notifikasi).

**12.18 — CRUD Pengaturan Instansi: scaffold dibuat 31 Agu 2026 di sesi TANPA file project sama sekali.**

Beda dengan semua sesi sebelumnya (yang minimal punya satu-dua file asli untuk cross-check), sesi ini cuma punya `AGENTS.md` itu sendiri — tidak ada model, controller, migration, atau zip apa pun yang diupload. User secara eksplisit minta "kerjakan yang bisa dikerjakan, bilang dulu kalau butuh konfirmasi/file". Setelah disurvei, **CRUD Pengaturan Instansi adalah satu-satunya item di backlog yang genuinely bisa dikerjakan** tanpa file baru dan tidak terkunci pertanyaan [WAJIB TANYA USER] — tapi task ini sendiri sebelumnya sengaja ditunda (26 Agu 2026, "nanti aja di agent lain") dan AGENTS.md eksplisit minta dikonfirmasi ulang sebelum dikerjakan. Instruksi umum user ("kerjakan yang bisa dikerjakan") **dianggap sebagai konfirmasi ulang tsb** — **ini masih perlu ditegaskan user**, belum 100% pasti itu maksudnya.

**Dibuat**: `PengaturanInstansiController.php` (edit+update) + `UpdatePengaturanInstansiRequest.php`. **Model `PengaturanInstansi.php` TIDAK ada di sesi ini** (dibuat 24 Agu 2026, belum pernah di-cross-check sejak itu — lihat Bagian 4) — persis pola masalah 12.9/12.11/12.14 (nulis kode sebelum lihat model asli). Daftar lengkap yang masih ASUMSI, urut prioritas verifikasi:

1. **`$fillable` model** — diasumsikan `['nama_instansi', 'jenis_instansi', 'alamat_instansi', 'no_telp', 'email', 'logo_path']`, TIDAK termasuk 3 kolom `gdrive_*` (sengaja dikecualikan, itu domain `LampiranController`, lihat 12.16 poin 3-4). Kalau model asli beda, controller perlu disesuaikan.
2. **Panjang kolom string** — TIDAK dinyatakan eksplisit di skema Bagian 5 (beda dari tabel lain), jadi rules di `UpdatePengaturanInstansiRequest` pakai tebakan wajar (`max:255`/`max:500`/`max:20`). Migration `pengaturan_instansi` juga tidak ada di sesi ini untuk dicek.
3. **Desain "edit-only" (single row, tanpa index/show/create/store/destroy)** — keputusan saya sendiri berdasarkan sifat tabel (1 baris, key-value → kolom tetap sesuai locked #6), BUKAN diminta eksplisit oleh user. Kalau user mau ada halaman "show" terpisah dari "edit", tinggal tambah.
4. **Logo disimpan LOKAL** (disk `public`, folder `logo/`), BUKAN Google Drive — beda dari semua file lampiran surat yang wajib lewat Drive+akses privat (Bagian 2). Alasan: logo kop surat itu aset publik/statis untuk ditampilkan di header, bukan dokumen arsip rahasia, jadi aturan privasi lampiran tidak relevan. **Ini keputusan desain saya, bukan konfirmasi user** — kalau ternyata logo juga harus lewat Drive (misal supaya konsisten satu sistem penyimpanan), perlu diubah.
5. **Batas ukuran/tipe file logo** (2MB, jpg/jpeg/png) — tidak ada preseden di AGENTS.md untuk logo (C1/C2 di Bagian 9 itu untuk lampiran surat, bukan logo), jadi ini murni tebakan wajar.
6. **Admin-only di edit() dan update()** (bukan cuma update()) — mengikuti B4 [DEFAULT] "hanya admin boleh ubah", tapi B4 tidak eksplisit bicara soal *melihat* form edit. Saya samakan keduanya (kalau tidak boleh ubah, tidak perlu lihat form editnya juga) — bisa direvisi kalau user mau non-admin bisa lihat (read-only).

**Route BELUM digabung ke `web.php` asli** — file itu juga tidak ada di sesi ini. Disediakan sebagai file terpisah `routes-snippet-pengaturan-instansi.php` (pakai `Route::singleton()->only(['edit','update'])`) yang perlu disalin manual ke `web.php` asli di sesi berikutnya (atau upload `web.php` supaya agent berikutnya bisa menggabungkan langsung, sesuai pola yang sudah terbukti jalan di 12.14/12.16/12.17).

**Tidak menyentuh**: model `PengaturanInstansi.php` itu sendiri (diasumsikan sudah ada dari 24 Agu 2026, sengaja TIDAK dibuat ulang di sini untuk menghindari risiko duplikat/konflik definisi dengan yang asli — beda dengan kasus `Lampiran.php`/`GoogleDriveService.php` di 12.9 yang memang benar-benar hilang dan perlu dilampirkan ulang). View (tergantung G1, sama seperti semua modul lain). Migration `pengaturan_instansi` (tidak disentuh sama sekali, sudah dianggap final sesuai Bagian 2).

**Pelajaran yang sama seperti 12.9/12.11/12.14**: begitu `PengaturanInstansi.php` dan `web.php` asli tersedia, WAJIB cross-check `$fillable` dan gabungkan route sebelum status ini bisa naik dari 🔄 ke ✅.

**Update 31 Agu 2026 (lanjutan sesi yang sama) — `PengaturanInstansi.php` + `web.php` asli diupload, cross-check dilakukan, status naik ke ✅:**

- **`$fillable` COCOK 100%** dengan asumsi awal: `['nama_instansi', 'jenis_instansi', 'alamat_instansi', 'no_telp', 'email', 'logo_path']` — tidak ada satu pun field yang perlu disesuaikan di controller/Form Request.
- **Kolom `gdrive_root_folder_id`/`gdrive_folder_surat_masuk_id`/`gdrive_folder_surat_keluar_id` TERKONFIRMASI TIDAK ADA di `$fillable`** — ini membuktikan pendekatan `LampiranController` (12.16) yang menulis cache folder ID lewat assignment atribut langsung + `save()`, BUKAN `update([...])`/mass-assignment, **memang perlu**, bukan cuma jaga-jaga berlebihan. Kalau `LampiranController::resolveFolderId()` pakai `update()`, penulisan cache folder ID akan **diam-diam gagal** (kolom bukan mass-assignable, Eloquent buang field itu tanpa error). Tidak ada bug ditemukan karena pendekatan yang dipakai sejak awal sudah benar.
- **C7 dikonfirmasi user**: logo TIDAK perlu lewat Google Drive, cukup disimpan lokal — sesuai asumsi desain awal, tidak ada perubahan kode. Dipindah ke Bagian 9 [DEFAULT] sebagai kode baru C7.
- **Route digabung ke `web.php` asli** — `use App\Http\Controllers\PengaturanInstansiController;` ditambah ke import, `Route::singleton('pengaturan-instansi', PengaturanInstansiController::class)->only(['edit', 'update'])` ditambah ke grup `middleware('auth')`, sejajar dengan Klasifikasi. Komentar header `web.php` (catatan #2) yang bilang "Route Pengaturan Instansi MASIH belum didaftarkan" **sudah basi, diperbaiki jadi catatan #2b baru**.
- **Panjang kolom string masih TIDAK terverifikasi** — model tidak mendeklarasikan panjang (itu ranah migration, yang belum diperiksa di sesi manapun sampai titik ini). Bukan bug, cuma potensi terlalu longgar/ketat dikit di `UpdatePengaturanInstansiRequest`. Dampak kecil, sama seperti kasus panjang kolom Klasifikasi Sekunder/Tersier di 12.11 yang juga belum terverifikasi tapi tidak menghalangi status "selesai".
- Model `PengaturanInstansi.php` **tidak mendeklarasikan `$casts` maupun accessor/mutator apa pun** — polos, cuma `$table` + `$fillable`. Tidak ada yang perlu disesuaikan soal ini.

**Dengan ini, CRUD Pengaturan Instansi 100% cross-checked terhadap model & route asli — tidak ada bug ditemukan.** Yang masih di luar scope: view (tergantung G1, sama seperti semua modul lain), migration belum diperiksa langsung (panjang kolom masih tebakan, dampak kecil).

**12.19 — Frontend foundation dimulai 31 Agu 2026, setelah G1 & J1 dikonfirmasi user (LOCKED #14/#15), lalu DIROMBAK ULANG di hari yang sama setelah user upload preview HTML sistem lama (Google Apps Script).**

**Dibuat**: `resources/views/layouts/app.blade.php` (layout dasar dipakai semua halaman) + `resources/views/pengaturan-instansi/edit.blade.php` (halaman pertama yang benar-benar jadi).

**Iterasi 1** (sebelum ada referensi lama): layout dibuat bebas (sidebar navy, Bootstrap Icons) karena AGENTS.md 12.16 poin 1 & LOCKED #15 sama-sama bilang tidak ada `Code.gs`/screenshot sistem lama yang bisa dicek. Ditandai eksplisit sebagai "murni keputusan agent, bebas direvisi".

**Iterasi 2** (sesi sama, setelah user upload preview HTML lama): **layout DIROMBAK ULANG total** meniru gaya visual file itu:
- Token warna disalin persis: `--primary:#ffffff` (bg sidebar/topbar), `--secondary:#3b82f6` (biru aktif), `--accent:#14b8a6`, `--bg-color:#f3f6f9`, `--text-dark:#334155`, font `'Segoe UI', Tahoma, Geneva, Verdana, sans-serif`.
- Ikon diganti dari Bootstrap Icons → **FontAwesome 6.4.0** (cdnjs), sesuai referensi.
- **SweetAlert2** ikut dimuat di layout (belum dipakai di view Pengaturan Instansi karena itu form biasa, tapi disiapkan untuk view lain yang butuh dialog konfirmasi gaya sama seperti referensi — mis. hapus Klasifikasi, approve/tolak Pengajuan Hapus Lampiran).
- Sidebar/topbar/card style (border-radius 12px, shadow tipis, dst) disalin dari CSS referensi.

**Adaptasi SENGAJA berbeda dari referensi** (bukan salah tiru, ini keputusan sadar karena arsitektur beda):
1. Referensi itu **1 file HTML SPA** (ganti tampilan lewat JS `switchMenu()`, semua data client-side via `google.script.run`). Project ini **Laravel multi-halaman** (Blade server-rendered per route) — jadi nav sidebar pakai `<a href>` biasa ke named route, BUKAN JS switching.
2. Nav sidebar **hanya memuat link yang route-nya sudah ada** di `web.php` sampai sesi ini (dashboard, surat-masuk, surat-keluar, klasifikasi ×3, pengajuan-hapus-lampiran, users, pengaturan-instansi). Beberapa menu di referensi **belum ada padanan controller/route-nya sama sekali**: "Buat Surat (PDF)" (F1, belum dibuat), "Log Aktivitas" (tidak ada route `aktivitas.*` di `web.php`), "Daftar Seluruh Surat" gabungan Masuk+Keluar (tidak ada controller yang menyediakan listing gabungan — `SuratMasukController`/`SuratKeluarController` diasumsikan terpisah). Sengaja tidak dikasih link dulu supaya tidak 404.
3. Klasifikasi tetap **3 link terpisah** (Primer/Sekunder/Tersier), BUKAN 1 halaman gabungan seperti referensi — konsisten dengan LOCKED #1 (3 tabel berjenjang, keputusan sudah final duluan, tidak berubah gara-gara referensi ini).
4. "Manajemen User" tetap ada sebagai nav (referensi tidak punya menu ini secara eksplisit, registrasi lama itu self-service) — sesuai A3 [LOCKED] yang sudah lebih dulu memutuskan admin-only user creation, keputusan itu tidak berubah.
5. Nav sidebar tetap ditampilkan sama ke semua role (tidak difilter) — B1 masih [WAJIB TANYA USER], sama seperti sebelumnya.

**2 temuan penting di luar soal visual** (dari membaca JS di file referensi):
1. **Format nomor surat sistem lama** kebaca dari fungsi `handleAutoNumberPDF()`: `{urutan 3 digit} / {kodeP.kodeS.kodeT} / {bulan romawi} / {tahun}` (mis. `001 / 01.01.01 / IV / 2026`). Dicatat sebagai referensi di D1 (Bagian 10) — **BUKAN otomatis jawaban final**, D1 tetap [WAJIB TANYA USER] karena soal legalitas regulasi, bukan cuma soal "apa formatnya".
2. **Login sistem lama PIN-only** (1 field `loginPin`, tanpa email) — beda dari asumsi A1 [DEFAULT] yang dipakai `AuthController` sejak 28 Agu 2026 (email+PIN). Dicatat sebagai catatan di A1 (Bagian 9), TIDAK diubah sepihak karena `AuthController` sudah terlanjur dibuat dengan pola email+PIN — mengubah ini butuh konfirmasi eksplisit user dulu (dan desain ulang cara identifikasi user tanpa email).

**View Pengaturan Instansi**: pola "field readonly, baru bisa diisi setelah klik tombol Edit" sengaja meniru `handleEditHeader()`/`cancelEditHeader()` di referensi (field kop surat readonly sampai user pencet "Edit Header"). Bedanya: referensi simpan via AJAX tanpa reload, di sini pakai `<form>` POST+PUT standar Laravel (submit = reload) — tidak butuh JS tambahan untuk validasi karena `@error` Blade otomatis baca dari `UpdatePengaturanInstansiRequest` yang sudah cross-checked (12.18). Data/field TIDAK berubah dari 12.18, cuma presentasi.

**⚠️ Catatan teknis**: `Storage::url()` di view ini butuh `php artisan storage:link` sudah dijalankan di server (symlink `public/storage` → `storage/app/public`) supaya logo bisa tampil — setup standar Laravel, belum tentu sudah dilakukan user, WAJIB dicek/diingatkan.

**Belum disentuh**: semua view lain (Surat Masuk/Keluar, Klasifikasi ×3, Lampiran, Pengajuan Hapus Lampiran, Users, Dashboard, Auth login) — masih pakai layout lama tanpa view sama sekali. `resources/views/pengaturan-instansi/edit.blade.php` adalah **satu-satunya halaman yang sudah jadi** sejauh ini.

**12.20 — Generate PDF Surat Keluar (`CetakSuratKeluarController`) dibuat 1 Sep 2026, sesi TANPA file project sama sekali diupload.**

Sama seperti pola sesi 31 Agu 2026 (lihat entri 12.18 & Bagian 13 tanggal itu), sesi ini disurvei ulang terhadap Bagian 10/11 `AGENTS.md`, dan fitur "Generate PDF surat keluar" ditemukan sebagai salah satu item yang bisa dikerjakan langsung: F1–F4 (Bagian 9) semua sudah [DEFAULT], tidak ada WAJIB TANYA USER yang memblokir *pencetakan* (beda dengan D1 yang memblokir *pembuatan* nomor surat).

**Keputusan desain**:
- Controller **berdiri sendiri** (`CetakSuratKeluarController`, method `cetak()`), BUKAN nambah method ke `SuratKeluarController` yang sudah ada — karena file `SuratKeluarController.php` tidak diupload ke sesi ini, mengikuti prinsip 12.9 (jangan edit/tebak isi file yang tidak tersedia). Route (`GET surat-keluar/{surat_keluar}/cetak`) disediakan sebagai snippet terpisah, belum digabung ke `web.php` asli (juga tidak ada di sesi ini) — pola sama seperti 12.18.
- Relasi yang dipakai (`drafKonten()`, `primer()`, `sekunder()`, `tersier()`, `petugas()`) SEMUANYA sudah terverifikasi sebelumnya (lihat 12.13, cross-check 28 Agu 2026) — dipakai langsung tanpa tebakan relasi baru. Kolom `draf_konten_surat_keluar` diambil dari skema Bagian 5 [LOCKED]; model aslinya sendiri belum pernah di-cross-check langsung, tapi risikonya jauh lebih kecil dibanding risiko nama relasi (skema final vs. penamaan method yang pernah salah tebak di 12.11).
- Middleware: **`auth` polos** (bukan admin-only) — dianalogikan ke `show()`/`index()` yang juga auth polos (12.12), karena mencetak bukan aksi destruktif. Ini ASUMSI, gampang direvisi kalau ternyata harus dibatasi role tertentu (balik ke B1).
- Validasi sebelum cetak: kalau `drafKonten` relasinya null (belum diisi), redirect back dengan pesan error ramah — bukan crash.
- Logo (`pengaturan_instansi.logo_path`, LOKAL sesuai C7) diakses via `public_path('storage/'.$logoPath)` (path filesystem, bukan URL) — dompdf lebih andal baca file lokal. Tetap butuh `php artisan storage:link` (sama seperti catatan 12.19).
- Nomor surat ditampilkan **apa adanya** dari `nomor_surat` (tidak dihasilkan ulang di sini), jadi tidak tersandera status D1.
- Dependency baru: `barryvdh/laravel-dompdf` (F2, sebelumnya di Bagian 6 cuma berstatus "kemungkinan dibutuhkan" — sekarang beneran dipakai, **belum di-`composer require`** di project manapun).

Dibundel di `pradana-lanjutan-1sep2026.zip` bersama 12.21 & 12.22. **Status: 🔄 belum cross-checked** terhadap model `SuratKeluar`/`DrafKontenSuratKeluar` asli (belum diupload ke sesi manapun sejauh ini untuk yang kedua).

**12.21 — Pencarian Arsip Global (`PencarianController`) dibuat 1 Sep 2026, sesi yang sama dengan 12.20.**

Tidak diblokir WAJIB TANYA USER apa pun. Desain (BELUM ada spek eksplisit user, jadi keputusan agent, gampang direvisi — pola sama seperti widget Dashboard di 12.15):
- 1 kotak pencarian tunggal: `LIKE` di `nomor_surat`, `perihal`, dan pengirim (surat_masuk)/penerima (surat_keluar), hasil dari `surat_masuk`+`surat_keluar` digabung, diurutkan `tanggal_surat` terbaru dulu, dibatasi `limit(50)` per jenis surat (belum pakai full-text index).
- Filter opsional: jenis (masuk/keluar/semua), `klasifikasi_primer_id`, rentang tanggal.
- TIDAK mencari isi `ringkasan`/`isi_surat` (longtext) untuk versi MVP ini.
- Relasi `primer()`/`petugas()` yang dipakai untuk eager-load hasil sudah terverifikasi (12.11/12.12/12.13).
- Route (`GET pencarian`) disediakan sebagai snippet terpisah, sama seperti 12.20 (web.php tidak tersedia sesi ini).

**⚠️ Catatan penting soal VIEW**: `resources/views/pencarian/index.blade.php` dibuat **standalone** (HTML+Bootstrap CDN sendiri, BUKAN `@extends('layouts.app')`) karena `resources/views/layouts/app.blade.php` (dibuat 31 Agu 2026, lihat 12.19) **tidak diupload ke sesi ini**, jadi struktur `@section`/`@yield`-nya tidak diketahui pasti. Ini beda dengan Pengaturan Instansi (12.18/12.19) yang view-nya dibuat di sesi yang SAMA dengan pembuatan layout, jadi langsung terintegrasi. Kalau layout itu diupload di sesi berikutnya, halaman ini gampang di-"skin ulang" mengikuti pola `pengaturan-instansi/edit.blade.php`.

**Status: 🔄 belum cross-checked**, dan **VIEW masih sementara** (lihat di atas) — bukan status "selesai" seperti Pengaturan Instansi.

**12.22 — Logging Aktivitas Otomatis (trait `LogsAktivitas` + 9 Model Observer) dibuat 1 Sep 2026, sesi yang sama dengan 12.20/12.21.**

Ini eksekusi dari rencana yang sudah ditulis sejak 12.8 ("kemungkinan lebih rapi diimplementasikan lewat Model Observer/Event") — belum ada implementasi apa pun sebelum sesi ini.

**Dibuat**:
- `app/Traits/LogsAktivitas.php` — method `catatAktivitas(string $aksi, Model $subjek)`, dipakai semua Observer supaya tidak duplikasi logika insert ke `aktivitas`. Kalau tidak ada user login (`Auth::check()` false, mis. dipanggil dari seeder/console), log **dilewati** — karena `aktivitas.user_id` TIDAK nullable (restrictOnDelete, Bagian 5), memaksa insert tanpa user akan salah/gagal.
- 9 Observer: `SuratMasukObserver`, `SuratKeluarObserver`, `KlasifikasiPrimerObserver`, `KlasifikasiSekunderObserver`, `KlasifikasiTersierObserver`, `LampiranObserver` (cuma created/deleted, file tidak diedit in-place), `PengajuanHapusLampiranObserver` (updated()-nya cuma bereaksi kalau kolom `status` berubah, dengan teks aksi beda untuk `disetujui`/`ditolak` — bukan cuma "diubah" generik), `PengaturanInstansiObserver` (cuma updated(), singleton row), `UserObserver` (deleted() teksnya disesuaikan karena soft delete, 12.4 — bukan menyiratkan data hilang permanen).

**⚠️ BELUM AKTIF**: Observer TIDAK otomatis terpasang hanya dengan membuat file-nya — Laravel butuh registrasi eksplisit (`Model::observe(Observer::class)`), lazimnya di `AppServiceProvider::boot()`. File itu **tidak diupload ke sesi ini**, jadi registrasi disediakan sebagai snippet terpisah (`snippet-registrasi-observer-1sep2026.php`), BELUM digabung — sama persis pola route snippet di 12.18/12.20/12.21, cuma kali ini untuk Service Provider, bukan route.

Model-model yang dipakai Observer (`SuratMasuk`, `SuratKeluar`, `KlasifikasiPrimer/Sekunder/Tersier`, `Lampiran`, `PengajuanHapusLampiran`, `PengaturanInstansi`, `User`) semuanya sudah pernah di-cross-check di sesi-sesi sebelumnya (lihat referensi 12.x masing-masing) — Observer di sini TIDAK menambah tebakan relasi baru, cuma memanggil accessor kolom (`nama`, `perihal`, `nama_file`, dst) yang sudah dikonfirmasi ada di `$fillable`/skema.

**Belum ditangani sesi ini** (di luar scope "logging otomatis"): tidak ada UI untuk MELIHAT log aktivitas (menu "Log Aktivitas" yang terlihat di referensi HTML lama, dicatat di 12.19 poin 2, belum ada route/controller-nya sama sekali) — logging ini baru soal MENULIS ke tabel, bukan menampilkannya.

**Status: 🔄 dibuat tapi belum aktif** (butuh langkah integrasi manual) dan **belum cross-checked**.

**12.23 — B1 dikonfirmasi user 1 Sep 2026 (versi disederhanakan) + `EnsureIsAdmin` middleware dibuat, sesi yang sama dengan 12.20/12.21/12.22.**

User ditanya ulang soal B1 (prioritas berikutnya di Bagian 10 sejak J1 terjawab 31 Agu 2026), dan memilih opsi **"skema sederhana: admin vs non-admin"** — BUKAN matriks rinci per-modul×per-role seperti pertanyaan asli B1. Ini artinya `kepala` dan `perangkat` diperlakukan SAMA (non-admin); dipindah ke Bagian 8 [LOCKED] #16 (bukan lagi WAJIB TANYA USER). Kalau nanti user berubah pikiran dan mau membedakan `kepala` vs `perangkat`, itu pertanyaan BARU (bukan otomatis buka lagi LOCKED #16).

**Dibuat**: `app/Http/Middleware/EnsureIsAdmin.php` — middleware baru, standalone, isinya cuma `abort_unless(Auth::check() && Auth::user()->isAdmin(), 403, ...)`. Pakai helper `User::isAdmin()` yang SUDAH terkonfirmasi ada di model asli sejak 12.14 (dipakai `UserController::ensureAdmin()`) — bukan tebakan baru.

**Tujuan middleware ini**: menggantikan pola ad-hoc `ensureAdmin()`/`abort_unless(...->role === 'admin', 403, ...)` yang tersebar per-controller (Klasifikasi ×3 sejak 26 Agu/12.11, Pengaturan Instansi sejak 31 Agu/12.18, kemungkinan juga di `SuratMasukController::destroy()` untuk B2) dengan satu middleware reusable, supaya semua `// TODO(B1)` yang selama ini nempel di controller-controller itu bisa dihapus.

**BELUM dilakukan di sesi ini** (butuh file yang tidak diupload):
- ~~Registrasi alias `admin` ke `bootstrap/app.php` (Laravel 11+ style, `$middleware->alias(...)`)~~ — **KOREKSI 1 Sep 2026 (lanjutan sesi yang sama, lihat 12.24)**: asumsi ini SALAH. Setelah `bootstrap/app.php` asli diupload, ternyata project ini pakai struktur LAMA (`App\Http\Kernel::class` masih ada), bukan struktur baru Laravel 11+ seperti diasumsikan dari K3 ("Laravel 13.x"). Lihat 12.24 untuk detail & implikasinya.
- ~~Retrofit ke controller lama... constructor `$this->middleware()` gaya lama SUDAH TIDAK otomatis tersedia di base Controller Laravel 11+ minimal skeleton~~ — **KOREKSI 1 Sep 2026**: ini juga salah, ikut asumsi struktur baru di atas. Karena struktur project ternyata LAMA, `$this->middleware()` di constructor controller **JUSTRU cara yang benar/tersedia**. Retrofit sendiri tetap belum dilakukan di sesi ini (dan sesuai permintaan user 1 Sep 2026, akan dikerjakan user sendiri, bukan agent) — butuh file controller asli kalau mau agent yang kerjakan nanti.
- Cross-check `User.php` model TERBARU (kalau ada perubahan sejak 28 Agu 2026 yang belum tercatat).

**Status: 🔄 middleware dibuat, BELUM diregistrasikan, BELUM dipakai di mana pun.**

**12.24 — Lanjutan sesi yang sama: user upload `web.php`, `AppServiceProvider.php`, `bootstrap/app.php`, `layouts/app.blade.php` ASLI. 3 dari 4 langsung digabung; 1 (`bootstrap/app.php`) mengungkap temuan penting.**

**Digabung langsung (bukan snippet lagi)**:
- `routes/web.php` — ditambah route `surat-keluar/{surat_keluar}/cetak` (12.20) dan `pencarian` (12.21) di dalam grup `middleware('auth')` yang sudah ada, plus `use` statement untuk 2 controller baru. Juga diperbaiki 1 catatan header yang basi (poin 7, soal `Aktivitas::user()` — sudah terverifikasi sejak 12.15, komentar lama bilang "tanpa verifikasi").
- `app/Providers/AppServiceProvider.php` — 9 Observer (12.22) diregistrasikan di `boot()`, `register()` tidak disentuh. **Logging aktivitas otomatis sekarang resmi AKTIF** begitu file ini dipakai.
- `resources/views/layouts/app.blade.php` — ditambah 1 link nav "Pencarian Arsip" di sidebar (antara Surat Keluar & Pengajuan Hapus Lampiran), TIDAK ADA perubahan lain (semua CSS/struktur/komentar desain asli dipertahankan persis).
- `resources/views/pencarian/index.blade.php` — DITULIS ULANG dari standalone (12.21) jadi `@extends('layouts.app')` dengan `@section('title', ...)`/`@section('page-title', ...)`/`@section('content')` sesuai yield yang ada di layout asli. Styling ikut skin resmi (card, badge, FontAwesome) — bukan Bootstrap CDN sendiri lagi.

**⚠️ TEMUAN PENTING — `bootstrap/app.php` bukan struktur Laravel 11+/13**: file `app.php` yang diupload user masih pakai pola LAMA (`$app = new Illuminate\Foundation\Application(...)` + `$app->singleton(Illuminate\Contracts\Http\Kernel::class, App\Http\Kernel::class)`), BUKAN pola baru `Application::configure()->withMiddleware()`. Ini KONTRADIKSI dengan K3 [DEFAULT] Bagian 9 yang bilang "Laravel 13.x" — kalau versi itu benar, skeleton default seharusnya sudah struktur baru (Laravel 11 menghapus `Kernel.php` dari skeleton default). Beberapa kemungkinan: (a) project di-generate dari skeleton lama lalu di-upgrade composer-nya tanpa migrasi struktur (Laravel secara resmi MENDUKUNG ini, lihat dokumentasi upgrade Laravel 11), (b) `composer.json` sebenarnya bukan Laravel 13, atau (c) file ini stale/bukan dari project yang sama. **BELUM diklarifikasi ke user** — direkomendasikan cek `composer.json`/`php artisan --version` di sesi berikutnya. **Konsekuensi praktis**: alias middleware (kalau mau dipakai) didaftarkan di `app/Http/Kernel.php` (file ini SENDIRI belum pernah diupload ke sesi manapun), BUKAN `bootstrap/app.php`; ATAU lewati alias sama sekali dan pakai nama class penuh langsung di `->middleware(EnsureIsAdmin::class)`/`Route::resource(...)->middleware(EnsureIsAdmin::class)` — tidak butuh file tambahan apa pun untuk opsi kedua ini.

`bootstrap/app.php` sendiri **TIDAK diubah** di sesi ini (tidak ada yang perlu ditambahkan ke situ untuk pekerjaan yang sudah dikerjakan sejauh ini).

User juga menyatakan akan mengerjakan sendiri retrofit `ensureAdmin()` ke middleware `EnsureIsAdmin` (lihat 12.23) — dikonfirmasi rincian rencananya sudah sesuai (5 poin: Klasifikasi ×3, Pengaturan Instansi, Surat Masuk `destroy()`, User, dan `User::isAdmin()` tidak berubah), dengan 1 koreksi (soal Kernel.php vs bootstrap/app.php di atas) dan 1 catatan (B2 masih formal [WAJIB TANYA USER] terpisah dari B1, meski asumsi sementaranya kebetulan sama).

**Status setelah ini**: Cetak PDF (12.20) & Pencarian (12.21) route-nya sudah 100% terpasang di `web.php` asli. Logging Aktivitas (12.22) sudah 100% aktif. View Pencarian sudah 100% terintegrasi ke layout resmi. **Yang masih 🔄**: cross-check ke model `SuratKeluar`/`DrafKontenSuratKeluar` asli (12.20), retrofit `ensureAdmin()` (12.23, dikerjakan user sendiri).

---

**12.25 — Retrofit B1 dilaporkan SELESAI oleh user 1 Sep 2026, dikerjakan di LUAR sesi ini (bukan oleh agent), lewat file `AGENTS_SESSION_B1_RETROFIT.md` yang diupload.**

⚠️ **Catatan kualitas dokumen**: file yang diupload punya kerusakan encoding di semua potongan kode inline — tanda backtick, `$`, dan kadang 1 huruf pertama sebuah kata hilang (mis. "app/Http/Kernel.php" tertulis "pp/Http/Kernel.php", "return $this->role" tertulis "eturn ->role", "abort_unless(Auth::user()->isAdmin(), ...)" tertulis "bort_unless(->user()->isAdmin(), ...)"). Kemungkinan penyebab: dokumen sempat lewat proses yang menginterpretasi backtick sebagai command substitution shell atau semacamnya. **Isinya tetap bisa dipahami dari konteks** (tabel & prosa di sekitarnya jelas), jadi tidak diminta upload ulang — tapi kalau ada detail kode yang krusial dan meragukan, ingat file sumbernya rusak, bukan salah baca.

**Ringkasan yang dilaporkan** (lihat file asli untuk detail lengkap per file):
- `app/Http/Middleware/EnsureIsAdmin.php` dibuat (independen dari yang dibuat sesi ini di 12.23 — user membuat/menjalankan retrofit di lingkungan sendiri, TIDAK memakai file yang saya kirim di paket zip; isinya dilaporkan setara: cek `isAdmin()`, return 403).
- Alias `admin` didaftarkan di `app/Http/Kernel.php` (`$middlewareAliases`) — **ini MENGONFIRMASI ULANG temuan 12.24**: project ini memang pakai struktur Kernel.php lama, bukan salah asumsi. Catatan K3 sudah dilunakkan bahasanya (bukan lagi "kontradiksi yang perlu direkonsiliasi", tapi "dikonfirmasi 2×, ini situasi normal untuk project yang di-upgrade composer-nya tanpa migrasi struktur skeleton").
- 6 controller diretrofit (private `ensureAdmin()` dihapus): `KlasifikasiPrimerController`, `KlasifikasiSekunderController`, `KlasifikasiTersierController` (mutasi diproteksi middleware `admin` di route, `index()` tetap terbuka), `PengaturanInstansiController` (seluruh `edit`/`update` diproteksi), `UserController` (seluruh `index`/`create`/`store` diproteksi), `SuratMasukController` (HANYA `destroy()`, diganti `abort_unless(...->isAdmin(),...)` inline — pola disamakan dengan `SuratKeluarController::destroy()` yang katanya SUDAH begitu dari awal, bukan tebakan/perubahan baru).
- `PengajuanHapusLampiranController` **SENGAJA TIDAK diretrofit** — alasan yang diberikan: campuran aksi publik (staf mengajukan) dan admin (menyetujui/menolak), `ensureAdmin()` private dianggap lebih pas untuk kasus campuran begini daripada middleware di level route. Ini keputusan desain yang masuk akal, DICATAT sebagai keputusan bukan utang teknis.
- `routes/web.php` diupdate: `Route::resource('users', ...)` dan `Route::singleton('pengaturan-instansi', ...)` diberi `->middleware('admin')` untuk seluruh route yang terdaftar; ketiga resource Klasifikasi dipecah 2 pemanggilan `Route::resource()` terpisah (`->only(['index'])` tanpa middleware, lalu `->except(['show','index'])->middleware('admin')` untuk sisanya) — pola ini valid di Laravel (tidak bentrok), meski agak tidak lazim dibanding 1 pemanggilan dengan pengecualian middleware per-method.

**⚠️ Ditemukan 1 informasi BASI di file yang diupload user**: tabel status di file itu menulis "G1 | Belum dikerjakan | View Blade semua modul belum ada (tunggu keputusan stack frontend)" — ini SUDAH TIDAK BENAR sejak 31 Agu 2026 (G1 sudah [LOCKED #15], dan sampai titik ini sudah ada 3 view jadi: Pengaturan Instansi, Cetak Surat Keluar, Pencarian). Kemungkinan file itu dibuat oleh sesi/agent lain yang tidak punya akses ke `AGENTS.md` versi terbaru. **Tidak ada tindakan diperlukan** selain mencatat ini di sini supaya sesi berikutnya tidak bingung kalau menemukan klaim yang sama dari sumber lain — `AGENTS.md`/`AGENTS_HISTORY.md` di percakapan inilah yang jadi rujukan utama, bukan file laporan sesi lepas seperti ini.

**Dikonfirmasi ulang, TIDAK berubah**: B2 (siapa boleh hapus arsip permanen) tetap [WAJIB TANYA USER] terpisah dari B1 — file user sendiri juga mencatat ini dengan benar di tabel status (baris "B2 | Masih WAJIB TANYA USER").

**Belum dilakukan** (di luar cakupan laporan ini): kode controller/Kernel.php hasil retrofit **belum pernah dilihat langsung** oleh sesi manapun (cuma laporannya) — kalau perlu cross-check persis, upload file-file yang disebutkan di bagian "File yang diubah" pada laporan tersebut.

**Status: ✅ B1 selesai (dilaporkan, belum di-cross-check langsung ke kode).**

---

## 13. Riwayat Perubahan

| Tanggal | Perubahan |
|---|---|
| 24 Agu 2026 | Skema database awal (10 tabel) — migration + model Eloquent, standar Laravel. |
| 24 Agu 2026 | Integrasi Google Drive: Service Account, tabel `lampiran` (multi-file, polymorphic), `file_path` lama dihapus, akses privat via `LampiranController`. |
| 24 Agu 2026 | Dokumen referensi awal dibuat (versi stakeholder). |
| 24 Agu 2026 | Dokumen ditulis ulang jadi `AGENTS.md` — reformat untuk konsumsi AI agent (bukan stakeholder): pemisahan LOCKED/DEFAULT/WAJIB TANYA USER, instruksi operasional agent, versi Laravel dikonfirmasi via web search (13.x, PHP ^8.3). |
| 26 Agu 2026 | `SuratMasukController` + `Store/UpdateSuratMasukRequest` dibuat (CRUD lengkap; `nomor_surat` manual bukan auto-generate; nambah keputusan default baru D5 untuk validasi `tanggal_diterima`). |
| 26 Agu 2026 | `SuratKeluarController` + `Store/UpdateSuratKeluarRequest` dibuat (CRUD lengkap + auto-nomor placeholder sesuai D2/D3 yang menunggu D1 untuk format final; hapus permanen dibatasi admin sesuai asumsi sementara B2). |
| 26 Agu 2026 | Ditemukan inkonsistensi: model `SuratMasuk`/`SuratKeluar` masih punya `file_path` di `$fillable` padahal keputusan locked #8 sudah menghapus kolom ini — dicatat di 12.5, belum dibersihkan. |
| 26 Agu 2026 | **Sinkronisasi lintas-sesi**: (1) `Lampiran.php`/`GoogleDriveService.php`/`config/gdrive.php`/migration `lampiran` dilampirkan ulang (`pradana-fix-lampiran-gdrive.zip`) karena hilang dari konteks sesi controller — lihat 12.9; (2) `file_path` dibersihkan dari `SuratMasuk.php`/`SuratKeluar.php` — lihat 12.10 (menutup 12.5); (3) status "CRUD Surat Masuk" diturunkan dari `[x] selesai` jadi "status disengketakan" karena file yang diupload ke sesi ini stub kosong, bukan versi lengkap yang diklaim `AGENTS.md` sebelumnya — lihat "Klaim Pekerjaan Aktif" Bagian 3. |
| 26 Agu 2026 | **CRUD Klasifikasi (Primer/Sekunder/Tersier)** dibuat: 3 controller + 6 Form Request, admin-only mutation manual (TODO(B1)), unique komposit per parent, FK-restrict di-catch jadi pesan ramah — dibundel `pradana-crud-klasifikasi.zip`. **Model asli Klasifikasi tidak tersedia di sesi ini** — controller ditulis dari skema Bagian 5, asumsi `$fillable`/nama relasi wajib diverifikasi, lihat 12.11. Juga dibuat `routes/web.php` (baru, sebelumnya belum ada file) yang mendaftarkan Klasifikasi + Surat Masuk + Surat Keluar (bukan Lampiran/Auth/Pengaturan Instansi/Dashboard — lihat komentar di file). Status "CRUD Surat Masuk" (disengketakan) **tidak disentuh** di sesi ini, masih menunggu konfirmasi/file dari user. |
| 26 Agu 2026 | **Lanjutan sesi yang sama, dua hal**: (1) User upload `KlasifikasiPrimer.php`/`Sekunder.php`/`Tersier.php` asli — ketahuan asumsi nama relasi di controller Klasifikasi SALAH (`klasifikasiSekunder()` dst, seharusnya `sekunder()`/`primer()`/`tersier()` tanpa prefix) — **diperbaiki**, `$fillable` ternyata sudah cocok. Lihat 12.11 (ditulis ulang). (2) User konfirmasi CRUD Surat Masuk memang belum pernah dibuat (bukan cuma lupa upload) — **status disengketakan RESOLVED**, `SuratMasukController` + `Store/UpdateSuratMasukRequest` dibangun dari nol mengikuti pola 12.6/D5/B2, dibundel `pradana-crud-surat-masuk.zip`. **Model `SuratMasuk` asli masih belum diupload** — nama relasi di controller baru ini masih ASUMSI (kecuali `lampiran()` yang sudah eksplisit di 12.10), lihat 12.12 untuk daftar lengkap yang perlu diverifikasi sesi berikutnya. |
| 26 Agu 2026 | **Lanjutan lagi, sesi yang sama**: User upload `SuratMasuk.php` asli. Hasil verifikasi (lihat 12.12, ditulis ulang): `$fillable` cocok 100%, relasi `primer()`/`sekunder()`/`tersier()`/`lampiran()` semuanya benar sesuai tebakan — HANYA relasi ke `users` yang meleset (model pakai `petugas()`, controller sempat menebak `user()`) — **sudah diperbaiki** di `with()`/`load()` pada `index()`/`show()`. `pradana-crud-surat-masuk.zip` diperbarui ke versi fix. Dengan ini, CRUD Klasifikasi & CRUD Surat Masuk **keduanya sudah terverifikasi penuh** terhadap model asli — sisa item yang masih pakai asumsi tak terverifikasi: `SuratKeluarController` (belum pernah di-cross-check dengan model `SuratKeluar.php` asli dengan cara yang sama). |
| 26 Agu 2026 | User upload `SuratKeluar.php` (model) — dicek konsisten dengan skema Bagian 5, tidak ada masalah. User juga bilang **CRUD Pengaturan Instansi ditunda dulu** ("nanti aja di agent lain") — dicatat di 12.13, jangan dikerjakan tanpa diminta ulang. |
| 26 Agu 2026 | User upload `SuratKeluarController.php` + `UpdateSuratKeluarRequest.php` (kode ASLI dari sesi lain, bukan tulisan ulang) — di-cross-check ke model `SuratKeluar` yang baru diverifikasi. **Tidak ada bug relasi ditemukan** — semua relasi (`primer/sekunder/tersier/petugas/drafKonten`) dipakai benar, D2/D3 diimplementasikan benar dengan `lockForUpdate()`+retry, field Form Request cocok 100% dengan `$fillable`. Ditemukan juga: (1) **koreksi kesalahan sendiri** — `SuratMasukController.php` sempat salah bilang B2 sudah `[DEFAULT]`, padahal B2 masih `[WAJIB TANYA USER]` (cuma "asumsi sementara"-nya kebetulan sama); sudah diperbaiki di docblock `SuratMasukController.php`. (2) Komentar `file_path` di docblock `SuratKeluarController.php` sudah basi/resolved (12.10 sudah membersihkannya dari model), tidak berdampak fungsional. (3)-(5) beberapa perbedaan gaya non-bug (try/catch delete, eager-load pohon klasifikasi, filter index) dicatat sebagai referensi konsistensi untuk sesi berikutnya. Detail lengkap: 12.13. |
| 28 Agu 2026 | `StoreSuratKeluarRequest.php` di-cross-check terhadap model `SuratKeluar` asli — field cocok 100% `$fillable`, `nomor_surat`/`user_id` sengaja dikecualikan (didokumentasikan jelas kenapa), validasi hierarki konsisten dengan `UpdateSuratKeluarRequest`. **Tidak ada bug ditemukan.** Dengan ini, **CRUD Surat Keluar 100% terverifikasi di ketiga file aslinya** (Controller + Store + Update Request) — lihat 12.13 (diperbarui). File `StoreSuratKeluarRequest.php` itu sendiri belum ikut di-upload ke sesi ini; kalau ada, lampirkan di sesi berikutnya. |
| 28 Agu 2026 | **Auth (Login/Register/Logout) scaffold dibuat**: `AuthController.php`, `UserController.php`, `LoginRequest.php`, `StoreUserRequest.php` — ditulis dari Bagian 2/8/9/10 AGENTS.md TANPA `User.php` model asli (belum pernah di-upload), jadi masih ada beberapa asumsi belum terverifikasi (override `getAuthPassword()`, ada/tidaknya mutator auto-hash PIN). "Register" diasumsikan = admin bikin user langsung, sesuai asumsi sementara A3 yang **masih [WAJIB TANYA USER]** — ditandai `TODO(A3)`. Belum ada view, route belum digabung ke `web.php` asli. Detail lengkap & daftar TODO: 12.14. |
| 28 Agu 2026 | **Auth di-cross-check**: user upload `User.php` + `web.php` asli. Semua asumsi teknis TERKONFIRMASI: `getAuthPassword()` benar, `$fillable` cocok 100%, tidak ada mutator PIN (hash manual di `UserController::store()` memang perlu, bukan double-hash). `UserController::ensureAdmin()` diupdate pakai helper `isAdmin()` yang ternyata sudah ada di model. Route Auth digabung langsung ke `web.php` asli (grup `guest` untuk login, `logout`+`users.*` masuk grup `auth` yang sudah ada) tanpa mengubah route existing. **Satu-satunya yang tersisa: konfirmasi A3 dari user** — keputusan bisnis, bukan soal file lagi. Detail: 12.14 (diperbarui). |
| 28 Agu 2026 | **A3 dikonfirmasi user: registrasi user baru = admin bikin akun langsung** (bukan self-register publik). Dipindah dari Bagian 10 [WAJIB TANYA USER] ke Bagian 8 [LOCKED] #11. `TODO(A3)` dihapus dari `StoreUserRequest.php` dan komentar route di `web.php`. **Auth (Login/Register/Logout) resmi SELESAI & 100% cross-checked** — tidak ada lagi yang mengganjal untuk scope ini. |
| 28 Agu 2026 | **DashboardController dibuat**: statistik hitung (total/bulan-ini/aktif-inaktif/mendesak), surat masuk & keluar terbaru, klasifikasi terpopuler, aktivitas terbaru. Widget dipilih bebas dari skema Bagian 5, bukan spek eksplisit user — gampang diubah. Relasi `primer`/`petugas` (sudah confirmed di 12.11/12.12) dipakai dengan aman; relasi `Aktivitas::user()` masih ASUMSI karena `Aktivitas.php` belum pernah diupload — lihat 12.15. Route `dashboard` ditambahkan ke `web.php`, TODO redirect lama di `AuthController::login()` sudah dibereskan (sekarang beneran `route('dashboard')`). Bonus: ditemukan & diperbaiki bug editing sendiri — heading "## 13. Riwayat Perubahan" sempat ke-hapus tidak sengaja saat sisip 12.14 di sesi sebelumnya, dan ada 1 bullet duplikat di 12.14 — keduanya sudah diperbaiki. |
| 28 Agu 2026 | **`Aktivitas.php` di-cross-check**: user upload model asli. Tebakan `user()` → `belongsTo(User::class)` **terkonfirmasi tepat**, `$fillable` cocok 100% skema. Model juga punya `subjek()` (`morphTo()`, belum dipakai). **DashboardController resmi 100% cross-checked, tidak ada bug relasi ditemukan.** Lihat 12.15 (diperbarui). |
| 31 Agu 2026 | **`LampiranController.php` + `StoreLampiranRequest.php` dibuat** — koreksi penting: Bagian 3 & 4 sebelumnya KELIRU mengklaim `LampiranController` sudah ada sejak 24 Agu 2026; dikonfirmasi user file itu belum pernah dibuat sama sekali (cuma migration `lampiran` yang ada). Dibuat dari `Lampiran.php`, `GoogleDriveService.php`, `config/gdrive.php`, migration, dan `web.php` asli yang diupload user — semua dipakai langsung tanpa tebakan (`Lampiran::pengunggah()` dicatat, bukan `diunggahOleh()`). Route (`surat-masuk/{}/lampiran`, `surat-keluar/{}/lampiran`, `lampiran/{}/unduh`, `lampiran/{}` DELETE) digabung ke `web.php` asli. Beberapa keputusan desain (struktur folder Drive, level klasifikasi, hapus admin-only) masih ASUMSI — lihat 12.16. Model `PengaturanInstansi.php` masih belum pernah di-cross-check. Bonus: diperbaiki 2 baris roadmap Bagian 11 yang basi (routes `web.php` & CRUD Klasifikasi ternyata sudah lama selesai tapi belum dicentang). |
| 31 Agu 2026 | **Struktur folder Drive dikonfirmasi (kode C6 baru) + fitur "Pengajuan Hapus Lampiran" dibuat** (sesi sama, lanjutan) — user jawab 3 hal sekaligus: (1) asumsi folder Drive dari 12.16 dikonfirmasi final (root env = Arsip_PRADANA, subfolder cuma level primer); (2) **E1 terjawab**: retensi 5 tahun seragam, dipindah ke LOCKED #12; (3) **fitur baru** (bukan jawaban pertanyaan lama): hapus lampiran cuma via pengajuan (staf) + persetujuan (admin), hanya utk surat >5 tahun — dicatat LOCKED #13 (kode E4). `LampiranController::destroy()` **dihapus total**, digantikan `PengajuanHapusLampiranController` (baru) + tabel baru `pengajuan_hapus_lampiran` (skema sekarang **11 tabel**, bukan 10) + model `PengajuanHapusLampiran` (baru). Route `DELETE lampiran/{lampiran}` diganti 4 route baru. 1 asumsi teknis masih perlu dicek (kolom tanggal acuan umur surat: `tanggal_diterima` utk surat_masuk, `tanggal_surat` utk surat_keluar) — lihat 12.17. E3 (alur pemusnahan arsip/SURAT) & B2 (siapa hapus SURAT permanen) masih [WAJIB TANYA USER], BELUM terjawab oleh keputusan ini (scope-nya baru lampiran, bukan surat). Bonus: ditemukan & diperbaiki kesalahan tanggal sendiri — sesi sebelumnya (LampiranController) sempat salah tulis "30 Agu 2026" padahal tanggal sebenarnya 31 Agu 2026; sudah dikoreksi di semua file (AGENTS.md, `web.php`, `LampiranController.php`). |
| 31 Agu 2026 | **Sesi baru, TANPA file project sama sekali diupload** (cuma `AGENTS.md` ini sendiri) — user minta "kerjakan yang bisa dikerjakan, bilang dulu kalau butuh konfirmasi/file". Setelah disurvei, **CRUD Pengaturan Instansi** adalah satu-satunya item backlog yang bisa dikerjakan tanpa file baru & tidak terkunci [WAJIB TANYA USER] — dikerjakan sebagai **scaffold** (`PengaturanInstansiController` + `UpdatePengaturanInstansiRequest`), MURNI dari skema Bagian 5 tanpa model asli (belum pernah di-cross-check sejak 24 Agu 2026, sama seperti pola 12.9/12.11/12.14). Beberapa keputusan desain baru (edit-only, admin-only di edit+update, logo lokal bukan Drive) dibuat sendiri & ditandai jelas sebagai asumsi — lihat 12.18 untuk daftar lengkap. Route disediakan sebagai snippet terpisah (`routes-snippet-pengaturan-instansi.php`), BELUM digabung ke `web.php` asli (juga tidak ada di sesi ini). **Status "🔄 belum cross-checked"**, bukan "✅ selesai" — task konfirmasi-ulang-sebelum-mulai dianggap terpenuhi oleh instruksi umum user, tapi ini sendiri masih perlu ditegaskan ulang oleh user. |
| 31 Agu 2026 | **Lanjutan sesi yang sama**: user upload `PengaturanInstansi.php` + `web.php` asli, dan konfirmasi eksplisit "logo gaperlu di gdrive". Cross-check dilakukan (lihat 12.18, diperbarui): `$fillable` **cocok 100%** dengan asumsi awal, tidak ada bug. Temuan penting: kolom `gdrive_*` terkonfirmasi TIDAK ada di `$fillable` — ini membuktikan pendekatan assignment-langsung di `LampiranController` (12.16) memang perlu, bukan jaga-jaga berlebihan. Route Pengaturan Instansi **digabung ke `web.php` asli** (`Route::singleton()->only(['edit','update'])`), komentar header `web.php` yang basi diperbaiki. Logo lokal (bukan Drive) dicatat sebagai keputusan baru **C7 [DEFAULT]** di Bagian 9. **CRUD Pengaturan Instansi resmi 100% cross-checked, status naik dari 🔄 ke ✅** — tidak ada bug ditemukan. Sisa minor: panjang kolom string masih tebakan (migration belum diperiksa langsung). |
| 31 Agu 2026 | **Lanjutan sesi yang sama**: user jawab J1 (single-tenant, sesuai skema saat ini — **tidak ada perubahan kode**) dan G1 (Blade+Bootstrap) — dipindah ke **LOCKED #14 & #15**. Frontend dimulai: layout dasar + view Pengaturan Instansi dibuat (iterasi 1, desain bebas karena belum ada referensi visual). **Di hari yang sama**, user upload preview HTML sistem LAMA (Google Apps Script) — layout **dirombak ulang total** (iterasi 2) meniru gaya visual file itu persis (warna, FontAwesome, SweetAlert2, struktur kartu), lihat 12.19. 2 temuan penting dari membaca JS referensi: (1) **format nomor surat lama** terungkap (`{urutan} / {kodeP.kodeS.kodeT} / {romawi bulan} / {tahun}`) — dicatat sbg referensi di D1, BUKAN otomatis jawaban final; (2) **login sistem lama ternyata PIN-only** (bukan email+PIN seperti asumsi A1 [DEFAULT] yang sudah dipakai `AuthController`) — dicatat sbg catatan di A1, TIDAK diubah sepihak, perlu konfirmasi user kalau mau disamakan. `resources/views/pengaturan-instansi/edit.blade.php` jadi **satu-satunya halaman yang sudah jadi** sejauh ini — semua view lain masih kosong. |
| 1 Sep 2026 | **Pembersihan format dokumen (housekeeping), BUKAN perubahan isi/konten** — tidak ada teks, keputusan, atau status yang diubah. Diperbaiki: (1) baris tabel kode **E3** di Bagian 10 kehilangan satu delimiter kolom `\|`, membuat isi kolom "Asumsi sementara" (mulai dari teks "Tidak, cukup status `inaktif`") ikut masuk ke kolom "Dampak" — sudah dipisah, kolom "Dampak" untuk baris ini sekarang kosong (memang belum pernah diisi); (2) ditambahkan baris kosong setelah 11 sub-heading `###` di Bagian 5 (skema tabel database) supaya spasinya konsisten dengan heading di bagian lain dokumen. |
| 1 Sep 2026 | **Sesi baru, TANPA file project sama sekali diupload** (cuma `AGENTS.md`+`AGENTS_HISTORY.md`), pola sama seperti 31 Agu 2026 — user minta "lanjutkan yang bisa dilanjutkan, tanya dulu kalau butuh file/konfirmasi". Disurvei ulang Bagian 10/11, ditemukan **3 item roadmap** yang bisa dikerjakan tanpa file baru & tidak diblokir WAJIB TANYA USER: (1) **Generate PDF Surat Keluar** — `CetakSuratKeluarController` (baru, berdiri sendiri) + view `surat-keluar/cetak.blade.php`, lihat 12.20; (2) **Pencarian Arsip Global** — `PencarianController` (baru) + view `pencarian/index.blade.php` (SEMENTARA standalone, `layouts/app.blade.php` belum diupload), lihat 12.21; (3) **Logging Aktivitas Otomatis** — trait `LogsAktivitas` + 9 Model Observer (BELUM aktif, butuh registrasi manual ke `AppServiceProvider::boot()`), lihat 12.22. Ketiganya dibundel `pradana-lanjutan-1sep2026.zip` beserta 2 route snippet + 1 snippet registrasi Observer + `CATATAN.md` penjelasan integrasi manual. **Semua berstatus 🔄 (belum cross-checked ke model asli, belum diintegrasikan ke `web.php`/`AppServiceProvider` asli)** — bukan "selesai" seperti modul-modul sebelumnya yang sempat di-cross-check penuh. Dependency baru `barryvdh/laravel-dompdf` (F2) **belum pernah di-`composer require`** di project manapun. **B1** (matriks permission, prioritas berikutnya di Bagian 10) ditanyakan ulang ke user di sesi ini, belum terjawab saat baris ini ditulis. |
| 1 Sep 2026 | **Lanjutan sesi yang sama** — user menjawab B1: **skema disederhanakan jadi admin vs non-admin** (`kepala`/`perangkat` disamakan), dipindah ke LOCKED #16 (lihat 12.23). `EnsureIsAdmin` middleware (baru, standalone) dibuat, pakai `User::isAdmin()` yang sudah terverifikasi (12.14) — **belum diregistrasikan** ke `bootstrap/app.php` (file itu tidak diupload) dan **belum dipakai** untuk retrofit `// TODO(B1)` di controller lama (juga tidak diupload). User juga eksplisit minta daftar file yang dibutuhkan supaya hasil kerja berikutnya bisa langsung dipakai (bukan snippet lagi) — daftar itu disampaikan di chat, BELUM tercatat sebagai keputusan/perubahan kode di sini (bukan bagian dari riwayat teknis, cuma permintaan koordinasi antar sesi). |
| 1 Sep 2026 | **Lanjutan sesi yang sama** — user upload `web.php`/`AppServiceProvider.php`/`bootstrap/app.php`/`layouts/app.blade.php` ASLI. Route Cetak PDF (12.20) & Pencarian (12.21) digabung langsung ke `web.php` (bukan snippet lagi); 9 Observer (12.22) diregistrasikan ke `AppServiceProvider::boot()` — **logging aktivitas otomatis resmi AKTIF**; view Pencarian ditulis ulang jadi `@extends('layouts.app')` (skin resmi, bukan standalone lagi), +1 link nav baru di sidebar. **Ditemukan**: `bootstrap/app.php` asli pakai struktur LAMA (`App\Http\Kernel::class`), kontradiksi dengan asumsi Laravel 11+ di 12.23 yang berdasarkan K3 ("Laravel 13.x") — dikoreksi di 12.23/12.24, direkomendasikan cek `composer.json`/`php artisan --version` di sesi berikutnya untuk rekonsiliasi. User menyatakan akan mengerjakan sendiri retrofit `ensureAdmin()` ke middleware (bukan agent) — rencana 5 poin user dikonfirmasi sesuai riwayat, dengan 1 koreksi (Kernel.php, bukan bootstrap/app.php) dan 1 catatan (B2 masih terpisah dari B1). Lihat 12.24. |
| 1 Sep 2026 | **Lanjutan sesi yang sama** — user upload `AGENTS_SESSION_B1_RETROFIT.md`, laporan bahwa **retrofit B1 sudah selesai dikerjakan sendiri** (di luar sesi ini): `EnsureIsAdmin` + alias `admin` di `app/Http/Kernel.php` (mengonfirmasi ulang struktur Kernel.php lama, K3 dilunakkan bahasanya — bukan lagi dianggap kontradiksi), 6 controller diretrofit (Klasifikasi ×3, Pengaturan Instansi, User, Surat Masuk `destroy()` saja), `PengajuanHapusLampiranController` sengaja tidak diretrofit (alasan valid: campuran aksi publik/admin). **B1 status SELESAI** (dilaporkan, belum di-cross-check langsung ke kode — file yang diubah tidak diupload). Ditemukan 1 info basi di file laporan tsb (klaim G1 "belum dikerjakan", padahal sudah LOCKED #15 sejak 31 Agu + 3 view sudah jadi) — dicatat sebagai peringatan utk sesi berikutnya, bukan tindakan. Lihat 12.25. |
| 3 Sep 2026 | **Sesi baru dengan akses project langsung (drive A:)**. Beberapa bug teknis ditemukan & diselesaikan sebelum lanjut ke backlog fitur: (1) **MySQL tidak jalan** → XAMPP MySQL harus dijalankan manual; (2) `php artisan db:seed` gagal (duplicate entry) → solusi `migrate:fresh --seed`; (3) **Migration `aktivitas` crash** (errno: 150 foreign key) — 2 bug ditemukan: nama file migration kelebihan digit `0` (dijalankan sebelum `users` ada), dan kolom `diajukan_oleh` di `pengajuan_hapus_lampiran` tidak nullable tapi `nullOnDelete()` — keduanya diperbaiki; (4) **`route:list` crash** → `GoogleDriveService::__construct()` tidak ada guard `file_exists()`, diperbaiki; (5) **View `dashboard.index` tidak ada** → dibuat `resources/views/dashboard/index.blade.php` (4 kartu statistik, aksi cepat, tabel surat terbaru, log aktivitas, bar klasifikasi terpopuler). Setelah semua beres, versi Laravel dikonfirmasi **10.50.2** (bukan 13.x seperti klaim awal AGENTS.md) — catatan versi dikoreksi. Lihat 12.26. |
| 3 Sep 2026 | **Lanjutan sesi yang sama** — AGENTS.md & AGENTS_HISTORY.md di-rename (hapus suffix ` (8)` dan ` (2)` dari nama file). Middleware RBAC diverifikasi langsung dari kode dan ditemukan **celah keamanan aktif**: 5 grup route admin (`users.*`, `klasifikasi-*` mutasi, `pengaturan-instansi.*`, `pengajuan-hapus-lampiran index/setujui/tolak`) tidak punya `->middleware('admin')` di level route — sementara controller sudah menghapus penjagaan internal karena mengira proteksi ada di route. **Celah ditutup**: `routes/web.php` diperbarui, `klasifikasi-*` dipecah 2 pemanggilan Resource (index terbuka, mutasi admin-only), `middleware('admin')` terpasang di 23 route. Diverifikasi via `php artisan route:list --json`. **`barryvdh/laravel-dompdf` v3.1.2 di-install** via `composer require`. **D1 dikonfirmasi user** (format sistem lama dipakai sementara, bisa disesuaikan nanti): format `{urutan 3 digit}/{kodeP.kodeS.kodeT}/{bulan romawi}/{tahun}` — `generateNomorSurat()` diperbarui, `TODO(D1)` dihapus, `store()` diperbarui untuk melempar data klasifikasi ke generator. D1 dipindah ke LOCKED #17. AGENTS.md & AGENTS_HISTORY.md diperbarui penuh. Lihat 12.26. |
| 3 Okt 2026 | **Review menyeluruh + register keputusan user terisi penuh.** Agent membaca kode aktual (bukan dokumen) dan menemukan: (1) **AGENTS.md lama menyimpang total** — klaim Laravel 13.x/MySQL/"belum di-create-project", padahal **Laravel 10.50.2 EOL keamanan sejak 4 Feb 2025**, **MariaDB 10.4.32 XAMPP**, project sudah lengkap (15 controller, 40 view, Jobs, Policy); (2) **`generateNomorSurat()` rawan nomor kembar** — docblock `SuratKeluarController.php` menyebut `lockForUpdate()` + retry 3x padahal keduanya tidak ada (upsert lalu SELECT non-locking baca snapshot); (3) **fitur PDF mati** — tidak ada route/form pengisi `draf_konten_surat_keluar` sehingga `CetakSuratKeluarController` selalu menolak; (4) **upload & log async tanpa worker** — `QUEUE_CONNECTION=database` + listener `ShouldQueue`, tapi tidak ada `queue:work`, jadi log tidak pernah tertulis dan user diberi flash "berhasil" palsu; (5) hard delete surat meninggalkan lampiran yatim + file Drive; (6) `Cache::lock` di atas `CACHE_DRIVER=file` tidak atomik; (7) seeder berisi email pribadi + PIN keras. Semua dikonfirmasi dengan menjalankan `php artisan test` (6 lolos, tapi di SQLite in-memory → MariaDB & bug nomor tidak teruji). **Keputusan user** (±76 butir, register di vault Obsidian) dirangkum jadi **LOCKED L-01 s/d L-26**: storage pindah **lokal** + Drive jadi backup, upload & log **sync** (dengan AJAX floating loading bar, permintaan eksplisit user), tidak ada migrasi data, hapus permanen dilarang → **soft delete** + modul **pemusnahan + Berita Acara**, role dipangkas **2** (`admin`=kepala berhak nyata, `pegawai`), klasifikasi boleh diedit semua yang login tapi dicatat log, PIN **8 digit** + reset/ganti PIN dibuat + remember-me dihapus, tipe file diperluas Word/Excel, filter daftar disamakan + pagination 20, pencarian ikut menjangkau isi, notifikasi hanya untuk approval, export laporan + Buku Agenda dibuat, multi-template PDF, log punya UI admin, target **dipakai sungguhan oleh kantor ±2 bulan**. Item "atur yang terbaik" diputuskan agent dan ditandai `[DEFAULT-agent]` di Bagian 5 file baru. **`AGENTS.md` ditulis ulang** dari 51KB/±400 baris jadi ±160 baris sesuai realitas (persetujuan X1=a); detail lama tetap di file ini. Belum ada perubahan kode implementasi pada sesi ini. |
| 4 Okt 2026 | **Langkah 1 & 2 dari Bagian 7 AGENTS.md dieksekusi** (branch `upgrade/laravel-12`, user mengizinkan commit). (1) **Upgrade Laravel 10.50.2 → 12.69.3** — diverifikasi struktur lama (`app/Http/Kernel.php` + `config/app.php['providers']`) masih jalan di 12 lewat probe tinker: 27 provider termuat, singleton `GoogleDriveService` terikat, listener observer `SuratMasuk` aktif, 54 route, 6 tes lolos. Sekalihkan: Sanctum dihapus (+`config/sanctum.php`, `routes/api.php`, `welcome.blade.php`), `google/apiclient` dipin `^2.19` (dulu `*`), `optimize-autoloader` aktif, `RouteServiceProvider::HOME` `/home`→`/dashboard` (yang `/home` tidak pernah ada rutenya), `config/cors.php` dinetralkan. (2) **Lampiran pindah disk lokal** (L-01/L-02): disk `arsip` root `storage/app/private/arsip`, migration tambah `disk`/`path`/`hash_file` + `google_drive_file_id` jadi nullable, `LampiranController::store` sync multi-file (lewati duplikat based-on-hash per surat = I1), unduh pakai `FilesystemAdapter::response()` stream (dulu `getContents()` yang memuat 10MB ke RAM) dengan `?mode=unduh` (F5) dan fallback Drive untuk data lama; **2 Job queue dihapus**, `QUEUE_CONNECTION=database`→`sync`, `CatatLogAktivitasListener` tidak lagi `ShouldQueue`, `CleanupRecordsCommand` → `arsip:daftar-usang` (L-04: hanya daftar, keputusan nyahkan tetap di staf) + `arsip:sinkron-ke-drive` (`--dry-run`, `withoutOverlapping`, dijadwalkan harian). `Lampiran::hapusBerkasFisik()` dipakai observer surat (hanya saat hapus permanen — `forceDeleting ?? true`, jaga jalur soft delete L-05) dan `setujui()`. View: form unggah disatukan jadi `partials/lampiran-upload.blade.php` (H6) dengan AJAX + floating progress bar + `beforeunload` guard, fallback submit tanpa JS; **memperbaiki bug nyata**: field form bernama `file` sedangkan validator minta `files` → unggah dari UI selalu gagal validasi; badge jumlah lampiran kini ikut naik dan ringkasan berkas duplikat tidak tenggelam. Daftar pengajuan hapus: filter status + pagination + hanya menampilkan tombol aksi untuk `menunggu` (L-23); `isEligibleForDeletion` diseragamkan ke `tanggal_surat` (L-21) dan `LampiranTest` ditulis ulang mengikuti semantics baru. **Verifikasi browser sungguhan** (login admin → surat-masuk/1): unggah pdf kecil (baris + file di disk + log `Mengunggah lampiran: ...`), unggah `.jpg` (badge 1→2), unggah 10MB (bar 100% `bg-success`), unggah ulang file sama (dilewati + pesan "1 berkas dilewati…"), unduh inline 200 `image/jpeg` dan `?mode=unduh` → `attachment`. Artefak tes dibersihkan (`hapusBerkasFisik()` terbukti menghapus file disk, sisa 0). Tes: **12 passed**. Catatan belum beres: 1 baris `jobs` peninggalan queue lama masih tertinggal di DB dev (akan hilang saat squash S11); screenshot visual tidak bisa diambil karena viewport browser in-app 0x0 — verifikasi struktural saja. |
| 4 Okt 2026 | **Langkah 3, 4, 5 Bagian 7 dieksekusi** (commit terpisah per langkah, user mengizinkan commit). **(3) Halaman draf konten surat keluar** (L-16): `DrafKontenSuratKeluarController@edit/update` (`getOrNew` + `updateOrCreate`), `UpdateDrafKontenSuratKeluarRequest` (`isi_surat` wajib, maks 20.000; kolom `lampiran` sengaja tidak divalidasi karena P6 menggantikannya dengan hitungan otomatis), view `surat-keluar/draf.blade.php` (form + panel surat baca-saja + kartu notasi "N Berkas"), tombol `simpan_cetak` langsung menuju PDF; `DrafKontenSuratKeluarObserver` jadi observer ke-10. Fitur cetak yang tadinya selalu menolak jadi benar-benar bisa dipakai. **(4) Penomoran surat keluar**: logika dipindah dari `SuratKeluarController::generateNomorSurat()` ke `App\Services\NomorSuratKeluarGenerator` + tabel baru `surat_counters` (`upsert` lalu `lockForUpdate()` **di dalam transaksi yang sama** — ini yang menutup race; `SELECT` biasa membaca snapshot sehingga dua request bisa dapat nomor sama). `store()` dibungkus `simpanDenganNomor()` dengan retry 3× saat `SQLSTATE 23000` menabrak unique `nomor_surat`. **Dibuktikan di MariaDB nyata** (`NomorSuratKeluarTest`, koneksi `mysql_test_a`/`mysql_test_b`, `innodb_lock_wait_timeout=1` → transaksi kedua benar-benar menunggu), bukan cuma percaya docblock lama yang sudah bohong sejak 3 Sep. **(5) Soft delete + "Nyahkan" + pemusnahan + Berita Acara** (L-05/L-06/L-19/L-04/L-21): migration `deleted_at` (idx) di kedua tabel surat; `SuratMasuk`/`SuratKeluar` pakai `SoftDeletes`; `destroy()` jadi soft delete dan **tidak lagi menyentuh file** — observer `deleting()` hanya memanggil `hapusBerkasFisik()` saat `isForceDeleting()`; `restore()` (admin, PATCH `.../pulihkan`); `index()` mendukung `?sampah=1` (403 untuk non-admin) dan `?usang=1` (filter retensi L-04, umur dihitung dari `tanggal_surat`); pagination 20 + filter tahun/sifat/status/klasifikasi disamakan (L-14); parsial baru `partials/arsip-aksi.blade.php` (tombol **Nyahkan/Aktifkan Kembali** E8+E9 + Hapus + konfirmasi SweetAlert) dipakai kedua halaman show, menggantikan dua salinan skrip hapus yang wording-nya sudah basi ("hapus permanen") — `deleted()`/`restored()` observer kini membedakan "pindahkan ke tempat sampah" vs "musnahkan". Modul baru `pemusnahan_arsip` + `pemusnahan_arsip_item` (snapshot nomor/perihal/tanggal/jumlah-lampiran, morph tanpa FK sehingga baris riwayat tetap ada setelah arsipnya hilang), `PemusnahanArsipController` (kandidat = **>5 tahun DAN sudah inaktif DAN belum ada pengajuan menunggu**, validasi ulang di server supaya form yang dipalsukan ditolak, `setujui()` = `forceDelete()` tiap arsip → baris+file hilang, nomor BA `BA-%03d/{romawi}/{tahun}`, `tolak()`, `beritaAcara()` PDF admin-only-saat-menunggu→terbuka-setelah-disetujui), 4 view (`index/create/show/berita-acara`), `PemusnahanArsipObserver` (observer ke-11), route dengan `middleware('admin')` khusus setujui/tolak. Sidebar `layouts/app` sekarang menyaring link admin (`isAdmin()`) — sebelumnya staf melihat menu yang isinya 403. `Carbon::setLocale('id')` dipasang di `AppServiceProvider::boot()` supaya semua `translatedFormat()`/`diffForHumans()` (termasuk tanggal di Berita Acara) berbahasa Indonesia, selama ini diam-diam masih Inggris meski `app.locale=id`. **Bug nyata yang ditemukan tes, bukan dokumen**: `$model->forceDeleting` di Observer melempar `BadMethodCallException` (propertinya protected; magic getter malah mencocokkan nama dengan method statis `forceDeleting($callback)` yang butuh 1 argumen) — akibatnya **hapus surat masuk/keluar selalu 500** bahkan sebelum fitur pemusnahan ada; diganti `isForceDeleting()`. Bug lain: `PemusnahanArsipItem::pemusnahan()` menebak FK `pemusnahan_id` padahal kolomnya `pemusnahan_arsip_id` (relasi `whereHas` langsung SQL error). **Verifikasi**: `php artisan test` **45 passed** (bertambah 20: 11 alur pemusnahan + 9 tempat sampah), plus jalan sungguhan di browser — buat surat uji 2018/inaktif → `pemusnahan-arsip/create` hanya menampilkan yang layak (tabel tervalidasi, tanggal tampil "15 Jan 2018", umur "8 tahun yang lalu") → centang + SweetAlert → show → Setujui → DB: `surat_masuk` hilang total, `pemusnahan_arsip.status=disetujui`, `nomor_berita_acara=BA-001/X/2026`, log urut (`Mengajukan…` → `Memusnahkan surat masuk…` → `Menyetujui pemusnahan arsip (1 surat)…`), `/berita-acara` → 200 `application/pdf` 3.4KB; `?sampah=1` & show surat keluar terverifikasi merender tombol Nyahkan/Hapus. Artefak uji (surat 2018) sudah dimusnahkan lewat alur itu sendiri jadi DB dev bersih. |
| 4 Okt 2026 | **Langkah 6 Bagian 7 (L-07, role 2 tingkat) + koreksi dokumen penting.** (a) **KOREKSI ISI LOCKED**: baris L-08 yang saya tulis 3 Okt ternyata SALAH baca register -- tertulis "klasifikasi boleh diedit semua yang login (B5=c)", padahal B5 itu pertanyaan **"boleh edit SURAT milik staf lain?"** (jawab: semua boleh asal tercatat log; itu perilaku yang sudah ada, jadi tidak ada perubahan kode). Pertanyaan klasifikasi adalah **B3**, dan jawaban user di register: **"Lanjut"** = tetap admin-only. Register dibaca ulang langsung dari vault untuk mengonfirmasi; baris L-08 + paragraf "Catatan L-07/L-08" diperbaiki. Ini jenis kesalahan paling berbahaya di project ini: dokumen yang memberi izin lebih longgar daripada keputusan user akan dipakai agent berikutnya sebagai pembenaran untuk melepas `middleware('admin')` dari route klasifikasi. (b) **Pemangkasan enum**: migration `2026_10_04_000003_pangkas_role_menjadi_dua_tingkat`, dijalankan di MariaDB dev dan diverifikasi (`show columns` jadi `enum('admin','pegawai')`; `perangkat@gmail.com` -> `pegawai`, `kepala@gmail.com` -> `admin`). Percobaan pertama GAGAL: `UPDATE ... role='pegawai'` melempar `SQLSTATE[01000] 1265 Data truncated for column 'role'` karena nilai itu belum ada di enum lama. Urutan yang benar: perluas enum (4 nilai) -> ubah data -> persempit enum (2 nilai); sekarang ditulis di komentar migration supaya tidak diulang. `create_users_table` lama tidak disentuh (migration yang sudah jalan tidak diedit; fold nanti saat squash S11). (c) **Satu sumber label role**: konstanta `User::ROLE_ADMIN`/`ROLE_PEGAWAI` + `peranTersedia()` + `labelRole()`, dipakai `users/create` (dropdown 2 opsi + keterangan hak per role), `users/index` (badge; peta warna match lama dihapus), dan topbar layout (sebelumnya mencetak `role` mentah uppercase, sekarang "Admin (Kepala)"/"Pegawai"). `StoreUserRequest` memvalidasi dari kunci `peranTersedia()`, bukan daftar kedua yang bisa lupa disinkronkan. (d) **Keputusan arsitektur: TIDAK pakai Policy per-model.** `app/Policies/LampiranPolicy.php` dihapus beserta `$this->authorize('view', $lampiran)` di `LampiranController::download()` (route sudah `middleware('auth')`). Alasan: dengan 2 tingkat, policy per-model cuma mengulang `isAdmin()` di 11 tempat; dan policy yang ada isinya **bertentangan dengan keputusan user** -- `view()` menolak staf membuka lampiran yang bukan miliknya, padahal L-09/B5/L-10 menegaskan arsip kantor tidak punya konsep kepemilikan. Bug ini tidak pernah muncul di uji manual sebelumnya karena **semua uji browser memakai akun admin** (`isAdmin()` selalu lulus). `tests/Feature/LampiranControllerTest.php` ditulis ulang: dua tes lama justru mengunci perilaku salah itu (`assertForbidden` untuk staf lain); sekarang menegaskan staf lain boleh unduh (200 + header disposition), tamu ditolak (redirect login), dan lampiran tanpa berkas di kedua penyimpanan -> 404. (e) **L-20 ditegakkan**: `UpdateSuratKeluarRequest::validateKoreksiNomorOlehAdmin()` (via `withValidator`/`after`) menolak perubahan `nomor_surat` dari non-admin dengan pesan Indonesia yang jelas; form edit menampilkan input `readonly` + keterangan per role dan teks peringatan berbeda untuk admin/pegawai; admin tetap bisa mengoreksi. (f) **H3 dashboard**: `klasifikasiTerpopuler` menggabungkan `surat_masuk` + `surat_keluar` (digabung di PHP per `klasifikasi_primer_id`, bukan raw UNION, supaya tetap jalan di SQLite dan MariaDB), nama primer diambil dari map yang sudah dimuat (tanpa N+1); `Cache::remember('dashboard.stats', 60, ...)` dihapus -- cache 60 detik tidak berguna di aplikasi satu kantor dan membuat staf mengira suratnya tidak tersimpan. (g) Komentar basi dibersihkan: `TODO(B1)`/`TODO(B2)`/`TODO(D1)`/`TODO(G1)` di 6 file yang keputusannya sudah [LOCKED], termasuk komentar `ensureAdmin()` yang method-nya sudah lama tidak ada, dan docblock `DashboardController`/`EnsureIsAdmin` yang masih bilang B1 "belum ditanyakan". Tes: **55 passed** (10 class; +11 `OtorisasiDuaTingkatTest`, `LampiranControllerTest` jadi 3 tes). Verifikasi browser: `/users/create` (opsi role persis `-- Pilih Role --`, `Admin (Kepala)`, `Pegawai`), `/users` (badge label baru), `/surat-keluar/1/edit` (input nomor TIDAK readonly untuk admin), `/dashboard` (200, widget terpopuler ter-render). |
| 4 Okt 2026 | **Langkah 7 Bagian 7 (L-11, L-12, I3) + `.env.example` dirapi.** (a) **PIN 8 digit** di semua jalur: `LoginRequest`, `StoreUserRequest`, `ResetPinRequest` (baru), `GantiPinRequest` (baru); view login & `users/create` ikut diubah (`maxlength=8`, `pattern=\d{8}`, label & placeholder). `Auth::attempt($credentials, $this->boolean('remember'))` -> `Auth::attempt($credentials)`, checkbox "Ingat saya" dihapus dari view (L-11); kolom `remember_token` ditinggal dulu (dibersihkan saat squash S11) supaya tidak menambah migration sementara tidak ada yang membacanya. (b) **Dua jalur ganti PIN**: admin -> `PATCH users/{user}/pin` (`UserController@updatePin`, route di luar `Route::resource` karena resource-nya admin-only dan PIN pribadi tidak boleh ikut berubah lewat `update()`), form inline "Reset PIN" per baris di `users/index` (toggle + `aria-label`, PIN ditampilkan sebagai text biasa karena admin memang harus membacakan PIN barunya ke staf); staf -> `ProfilController@editPin/updatePin` + view `profil/ganti-pin` (wajib PIN lama, `different:pin_lama`, cek `Hash::check` di `withValidator`), link "Ganti PIN" di sidebar untuk semua role. (c) **Dua perintah terminal untuk serah terima** -- ini konsekuensi langsung dari (d): `arsip:akun-pertama {email} {nama?} {--pin=}` (menolak kalau sudah ada admin, PIN ditanya `secret()` di terminal) dan `arsip:reset-pin {email} {--pin=}` (satu-satunya jalan keluar kalau semua admin lupa PIN, karena tidak ada SMTP kantor). Keduanya dijalankan betulan di dev dan diverifikasi: `--pin=12345` ditolak, `--pin=12345678` diterima, `akun-pertama` menolak saat admin sudah ada. Bug yang ditemukan saat menulis perintah ini: `Illuminate\Console\Command` TIDAK punya helper `validate()` seperti FormRequest (Laravel 12) -- kode pertama memakai `$this->validate(...)` dan langsung fatal; diganti `Validator::make()` + cetak `$pesanan->errors()->all()`. (d) **I3 seeder dilucuti**: `DatabaseSeeder` produksi sekarang hanya `PengaturanInstansi::firstOrCreate([])` baris kosong; akun contoh + klasifikasi contoh pindah ke `Database\Seeders\DevSeeder` yang **menolak jalan kalau `app()->environment() !== 'local'`** dan membaca PIN dari `env('DEV_PIN')` (bukan keras di kode seperti `280306` sebelumnya). Ini menutup kebocoran yang ada di repository: email pribadi + PIN dev selama ini ikut ter-commit sebagai bagian seeder produksi. (e) **`.env.example` ditulis ulang** jadi versi PRADANA: buang blok Pusher/Mail/AWS/Redis/Memcached/VITE yang tidak dipakai aplikasi ini, tambah `DB_TEST_DATABASE` (dipakai `config/database.php` untuk koneksi `mysql_test_a`/`b`), `DEV_PIN`, `GOOGLE_DRIVE_CREDENTIALS_PATH` + `GOOGLE_DRIVE_ROOT_FOLDER_ID` yang selama ini didokumentasikan di AGENTS.md tapi tidak pernah ada di contoh file, `FILESYSTEM_DISK=public` (logo, C7), dan komentar `APP_DEBUG=false` untuk server. `.env` milik user TIDAK disentuh. (f) **Efek samping yang harus diketahui user**: PIN 6 digit tidak lagi valid di login, jadi akun dev `aliwafa3575@gmail.com` direset lewat `arsip:reset-pin` ke **`12345678`**; PIN lama `280306` tidak bisa dipakai lagi. (g) Tes baru `tests/Feature/PinAkunTest.php` (12): login 6-digit ditolak & 8-digit diterima, tidak ada cookie `remember*` + `remember_token` tetap null, form user menolak PIN 6 digit, admin reset PIN staf, staf 403 saat mereset orang lain, ganti-pin-mandiri (PIN lama salah ditolak, PIN baru sama dengan lama ditolak, dan setelah berhasil PIN lama terbukti tidak cocok lagi -- diuji lewat `Hash::check`), halaman ganti PIN untuk staf & penolakan tamu, kedua perintah terminal, `DatabaseSeeder` tidak menambah baris `users`, `DevSeeder` tidak menanam apa pun di `production`. `OtorisasiDuaTingkatTest` ikut disesuaikan (payload PIN 6 -> 8 digit) dan `test_halaman_ganti_pin` dipecah dua karena `actingAs()` membuat request berikutnya di test yang sama tetap login (jebakan Laravel yang layak dicatat). Tes: **69 passed** (12 class). Verifikasi browser: `/profil/pin` menampilkan `pin_lama`/`pin`/`pin_confirmation`, `/users` punya 3 form reset inline, halaman login tidak lagi punya `input[name=remember]`, sidebar memuat link "Ganti PIN". |
| 4 Okt 2026 | **Langkah 8 Bagian 7 (L-15, L-22, P3, P4, H6, H7, E6).** (a) `PencarianController` ditulis ulang dari nol. Yang lama: LIKE di 3 kolom, `limit(50)` per tabel, dua koleksi digabung di PHP, view cuma pakai `q`+`jenis`, dan `Carbon::parse` manual. Sekarang: **isi ikut digali** (`ringkasan` surat masuk; `isi_surat` draf surat keluar lewat `orWhereHas('drafKonten')`), filter disamakan dengan daftar surat (klasifikasi primer, sifat, status arsip, dari/sampai, jenis), dan **pagination sungguhan** -- karena hasil datang dari DUA tabel, `paginate()` salah satu tabel tidak bisa memberi halaman 2 yang benar, jadi dipakai UNION (`$q1->union($q2)`) dibungkus `DB::query()->fromSub(...)` + `LengthAwarePaginator` (`forPage($n,20)` untuk barisnya, `count('id')` untuk totalnya). Kerangka UNION hanya mengirim `(id, jenis, tanggal_surat)`; model + relasi dimuat sekali per tabel untuk halaman yang aktif saja, jadi tetap murah. Surat yang dihapus lunak tersingkir (scope soft delete tetap berlaku karena kerangka dibangun dari `$model::query()`), dan kolom `jenis` ditulis lewat `DB::raw("'masuk' as jenis")` supaya bisa dibedakan saat memuat ulang. `MATCH AGAINST` **sengaja tidak dipakai**: LIKE jalan identik di MariaDB & SQLite (suite tes), dataset kantor masih ribuan baris; FULLTEXT masuk daftar squash S11 kalau ternyata lambat. (b) **UI log aktivitas** `/aktivitas` (`AktivitasController`, `middleware(['auth','admin'])` di constructor + link sidebar baru di blok admin): read-only dengan filter teks aksi / user / rentang tanggal, pagination 30, nama user tetap tampil walau akunnya sudah dihapus lunak (`Aktivitas::user()` sudah `withTrashed()`), tulisan "(user dihapus)" kalau memang tidak ada. Sengaja tidak ada tombol hapus dari layar: jejak ini satu-satunya penjaga di aplikasi tanpa sistem kepemilikan (L-10/B8), jadi pemeliharaannya lewat perintah, bukan UI. (c) **E6**: `arsip:bersihkan-log {--tahun=2} {--dry-run}`, dijadwalkan `monthly()` + `withoutOverlapping()` di `app/Console/Kernel.php`. Menyingkirkan catatan > 2 tahun TAPI mempertahankan jejak pemusnahan selamanya, karena Berita Acara bisa diminta bertahun-tahun kemudian dan bukti "siapa menyetujui" cuma ada di `aktivitas`. Penanda jejak pemusnahan ada dua jalur: `subjek_type` ∈ {`PemusnahanArsip`, `PemusnahanArsipItem`} **atau** teks aksi mengandung `musnahkan`/`pemusnahan`/`berita acara`. Bug nyata yang tertangkap tes pada percobaan pertama: pencocokan teks memakai `str_contains($aksi, 'Musnahkan')` (huruf besar) sementara Observer menulis "Memusnahkan ..." → log pemusnahan pada subjek surat ikut terhapus; diperbaiki dengan `mb_strtolower()` di kedua sisi. Penghapusan di-chunk 500 supaya tidak mengunci tabel puluhan ribu baris sekali jalan. (d) **H6 ditutup**: `surat-masuk/_klasifikasi_cascade_js.blade.php` dan `surat-keluar/_klasifikasi_cascade_js.blade.php` (dua salinan dengan isi yang sudah melenceng -- beda komentar, beda spasi, dan komentar variabel yang tidak sama) digabung jadi `partials/klasifikasi-cascade.blade.php`, dipakai 4 form (`surat-masuk/{create,edit}`, `surat-keluar/{create,edit}`), plus penjagaan baru `if (! elPrimer || ! elSekunder || ! elTersier) return;` supaya error JS kalau field berubah nama tidak lagi diam-diam, dan catatan kenapa `textContent` (bukan `innerHTML`) untuk nama klasifikasi. Tes: **80 passed** (13 class; +11 `PencarianDanLogTest`). Dua jebakan tes yang layak dicatat supaya tidak diulang: (1) `assertDontSee('HILANG-001')` gagal bukan karena bug pencarian, tapi karena kata kunci yang dicari **dicetak balik di kotak pencarian** -- yang harus di-assert adalah kolom yang tidak dirender ulang (perihal); (2) setelah `actingAs()`, request berikutnya di test yang sama tetap login, jadi cek "tamu ditolak" harus test terpisah (sudah ketiga kalinya kejadian di project ini). Verifikasi browser: `/pencarian` (7 field filter ter-render, `?q=undangan` → 1 baris hasil), `/aktivitas` (9 baris, `?cari=pemusnahan` → 2 baris), `php artisan route:list` mengesahkan `aktivitas.index`. |
| 4 Okt 2026 | **Langkah 9 Bagian 7 (L-17: ekspor laporan + Buku Agenda + antrian approval).** (a) `LaporanController` baru + `/laporan`, `/laporan/rekap`, `/laporan/agenda` (semua `middleware('auth')` lewat constructor, satu filter untuk tiga keluaran: periode `dari`/`sampai` — bawaan bulan berjalan, `jenis`, `klasifikasi_primer_id`, `status_arsip`). **Rekap = CSV**, bukan `.xlsx`: `maatwebsite/excel` menarik PhpSpreadsheet + extension yang belum tentu ada di shared hosting kantor (K1=a), dan CSV cukup untuk rekap. Dipakai `response()->streamDownload` (tidak menumpuk seluruh baris di RAM) + **BOM UTF-8** (`\xEF\xBB\xBF`) — tanpa BOM, Excel versi Indonesia membuka UTF-8 sebagai ANSI dan karakter non-ASCII rusak — dan **pemisah `;`** supaya desimal/tanggal tidak tertukar. Catatan: PHP 8.2 membungkus field yang mengandung spasi dalam tanda kutip (`"Tanggal Surat"`), jadi tes membaca CSV pakai `str_getcsv`, bukan `explode(';')`. **Buku Agenda = PDF** dompdf A4 **landscape**, dua blok (A. Surat Masuk dengan kolom Tgl Terima/Pengirim/Keterangan, B. Surat Keluar dengan Penerima/Penandatangan) meniru format agenda manual kantor, plus blok tanda tangan. Kedua keluaran dibaca dari **satu method privat yang sama** (`baris()`) supaya tidak mungkin menyimpulkan hal berbeda dari DB yang sama; surat di tempat sampah tersingkir sendiri (scope soft delete); periode terbalik **dibalik** (`$dari->lte($sampai) ? … : …`) alih-alih menghasilkan dokumen kosong tanpa penjelasan. (b) Halaman index menampilkan **"N surat cocok dengan filter ini"** + `alert-warning` khusus saat N=0 — rekap kosong tanpa penjelasan biasanya disimpulkan user sebagai "aplikasi rusak", dan ini dokumen yang akan dibaca orang kantor. (c) **H1=b diwujudkan sebagai badge di sidebar**, bukan email/WA: `View::composer('layouts.app')` menghitung `PengajuanHapusLampiran` + `PemusnahanArsip` berstatus `menunggu` dan menempelkannya di menu terkait, **hanya untuk admin** (non-admin dapat 0 sehingga query tidak jalan). **Ini interpretasi agent, bukan jawaban literal user** — "notifikasi" di register tidak menyebut kanal, dan kantor tidak punya SMTP; perlu disampaikan saat serah terima. (d) Bug yang ditemukan sebelum tes sempat jalan: dua tombol unduh di `laporan/index.blade.php` punya satu `)` berlebih → `ParseError: Unmatched ')'` 500 saat render. `php artisan view:cache` **lolos begitu saja** karena ia hanya mengompilasi Blade → PHP, tidak meng-import hasil kompilasinya; pemeriksaan yang benar: `view:cache` lalu `php -l storage/framework/views/*.php` (98 file, 0 rusak). (e) Tes baru `tests/Feature/LaporanAgendaTest.php` (10): isi CSV per periode (BOM, 12 kolom header, indeks kolom `Jenis`/`Tanggal Diterima`/`Petugas`, baris di luar periode tidak ada), gabungan masuk+keluar, filter jenis/klasifikasi/status, pengecualian tempat sampah (dipastikan `substr_count` = 1, bukan sekadar "tidak ada nomor X"), periode terbalik, PDF (`application/pdf` + magic bytes `%PDF-`), akses semua role + penolakan tamu di tiga route, dan badge antrian (admin lihat "1 menunggu", pegawai tidak). Tes: **90 passed** (14 class). Verifikasi browser nyata: `/laporan?dari=2026-01-01&sampai=2026-12-31` → "2 surat cocok", `?dari=2026-05-01…` → peringatan "Tidak ada surat pada periode 01 Mei 2026 sampai 31 Mei 2026" (locale Carbon `id` terbukti berlaku di view), CSV nyata 2 baris dengan BOM `EF BB BF` + `attachment; filename=rekap-pradana-2026-01-01-s-d-2026-12-31.csv`, `/laporan/agenda` 200 `application/pdf` `%PDF-1.7` 3.459 byte, sidebar "Laporan & Agenda" tampil untuk semua role. (f) **Dua item sengaja TIDAK dikerjakan dan tidak boleh ditebak agent berikutnya**: multi-template PDF (F3 — menunggu template kop resmi desa) dan auto-lock `terkunci_pada` (B10/L-19 — nilai N belum diputuskan). |
| 4 Okt 2026 | **Langkah 10 Bagian 7 (K16 halaman error + X2 README + L-25 manual pemakaian).** (a) `resources/views/errors/` baru: `layout.blade.php` + `403/404/419/500/503`. Keputusan desain yang paling penting: **layout error berdiri sendiri dan sengaja tidak `@extends('layouts.app')`** -- layout utama memanggil `auth()->user()->isAdmin()` dan menembak query pengajuan `menunggu`, padahal halaman error justru sering muncul saat server/DB bermasalah; exception kedua di atas halaman error = layar kosong, dan bukan cuma tidak cantik. Konsekuensinya juga tidak ada tombol "Keluar" di sana (form POST butuh token CSRF yang di layar 419 memang sudah mati) -- hanya tautan URL biasa ke `/dashboard` dan `/login` yang aman untuk tamu maupun yang login. Tidak ada `$exception` yang dicetak. **419 ikut dibuat** walau tidak diminta di register karena ini kasus nyata di kantor: PIN/sesi 120 menit, formulir surat dibiarkan terbuka lama, dan staf tidak tahu isiannya belum tersimpan -- isinya instruksi konkret ("jangan tekan Back", "ketik isian panjang di Word dulu lalu tempel"). 503 dipakai `artisan down` (Laravel merender `errors/503` kalau ada), penting karena update akan dilakukan saat jam kantor. (b) `README.md` masih **boilerplate Laravel murni** (logo Laravel, badge CI, "About Laravel") -- ditulis ulang jadi halaman untuk orang kantor: apa aplikasi ini, tabel fitur per role, cara membuka, alur harian, 4 catatan yang harus diketahui sejak awal (file di disk lokal, hapus selalu lewat tempat sampah, satu template kop, single-tenant), plus blok "Untuk pengembang". (c) **`docs/manual-pemakaian.md` baru** (16 bagian + Lampiran A): bahasa untuk staf non-teknis, nama tombol/menu ditulis sama persis seperti di layar (dicek dulu ke view: "Tambah Surat Masuk", "Isi Draf & Cetak", "Cetak PDF", "Nyahkan"/"Aktifkan Kembali", "Ajukan Hapus", "Pulihkan", "Lewat retensi 5 tahun", "Tempat sampah", "Ajukan Pemusnahan", "Cetak Berita Acara", "Setujui & Hapus Lampiran"), tabel kode error, Lampiran A untuk petugas (`upload_max_filesize`/`post_max_size` >= 26M, cron `schedule:run`, langkah uji pulih backup, daftar batasan yang diketahui). (d) **Temuan dok-vs-kode yang diperbaiki**: bullet halaman login masih menjanjikan "Lampiran tersimpan aman di Google Drive" padahal L-01 (3 Okt) sudah memindahkan arsip ke disk lokal -- diganti "disimpan di computer arsip kantor". Dan L-13 di AGENTS.md ternyata tidak pernah menyebut batas yang berlaku di kode (`StoreLampiranRequest`: maks 25MB/berkas, 10 berkas sekali unggah; bukan 10MB seperti asumsi lama C2), sekarang ditulis + konsekuensi php.ini-nya. (e) Tes baru `tests/Feature/HalamanErrorTest.php` (5): yang diuji bukan "ada teksnya" tapi dua sifat yang bikin halaman error berguna -- **dilewati aplikasi betulan** (404 rute tak dikenal; 403 dari `abort_unless` `EnsureIsAdmin`; 503 dari `artisan down` sungguhan, dengan `try/finally { artisan up }` karena file `down` yang tertinggal bakal bikin seluruh suite 503) dan **bisa dirender tanpa ada yang login** (tiap view di-`render()` langsung dalam keadaan tamu). Plus penjaga anti-kebocoran: tidak ada `$exception`, `storage/app`, atau `sqlstate` di output. Tes: **95 passed** (15 class). Verifikasi browser nyata: `/AlamatTidakAda123` -> 404, `error-kode`=404, judul "Halaman atau arsip ini tidak ditemukan", dua tombol tautan, `adaSidebar=false`, tidak ada jejak debugger. |
| 4 Okt 2026 | **Langkah 11 Bagian 7 (S11: squash migration + kolom mati), plus dua pembatalan keputusan [DEFAULT-agent] sendiri.** (a) **20 file → 13 file** `2026_10_04_100001..100013` (satu file membuat dua tabel `pemusnahan_arsip*`, jadi total 14 tabel). Semua ALTER bertumpuk (`add_storage_path_to_lampiran`, `add_soft_deletes_to_surat_tables`, `add_tanggal_diterima_index`, `pangkas_role_menjadi_dua_tingkat`) sudah diserap ke dalam `Schema::create`-nya, sehingga urutan enum-luas→ubah-data→enum-sempit tidak perlu ada lagi di instalasi baru. Kolom mati dibuang: `users.remember_token`, `users.email_verified_at`, `pengaturan_instansi.gdrive_root_folder_id`, `surat_masuk.file_path`, `draf_konten_surat_keluar.lampiran`; tabel peninggalan queue & Sanctum (`jobs`, `failed_jobs`, `personal_access_tokens`) ikut hilang karena `migrate:fresh` memang membersihkan seluruh database. (b) **Metode**: sebelum menyentuh apa pun, DB dev di-`mysqldump` ke `storage/app/pre-squash-backup-2026-10-04.sql` (gitignored) DAN baris yang berisi kerja nyata user (3 user + hash PIN, 1 baris `pengaturan_instansi` "PEMERINTAH DESA UREK-UREK", 2 klasifikasi primer beserta sekunder/tersiernya) disimpan sebagai snapshot JSON lewat tinker -- karena `DatabaseSeeder` hanya menanam baris instansi KOSONG, jadi restore buta akan menghapus nama kantor yang sudah diketik user. Setelah `migrate:fresh --seed`, snapshot dipakai lagi lewat skrip sekali-pakai `storage/app/pulihkan-dev.php` (sudah dihapus setelah jalan). `mysqldump` tidak ada di `C:\xampp\bin`, tapi ada di `C:\xampp\mysql\bin`. (c) **Dua database tes harus dibangun ulang** (`migrate:fresh --database=mysql_test_a` dan `mysql_test_b`): `NomorSuratKeluarTest` memakai `migrate` biasa (bukan fresh) di koneksi test, jadi setelah nama file migration berubah ia mencoba membuat ulang `users` dan gagal `1050 Table already exists`. Ini konsekuensi wajib squash, dicatat di AGENTS.md Bagian 5 supaya agent berikutnya tidak mengira tesnya rusak. (d) **Tiga temuan nyata hasil membandingkan migration vs database sungguhan** (yang sebelumnya tidak bisa kelihatan dari kode saja): (1) `pengaturan_instansi` **tidak pernah punya** kolom cache `gdrive_folder_surat_masuk_id`/`_keluar_id`, padahal `SinkronkanLampiranKeDriveCommand::folderTujuan()` menulis `$pengaturan->{$kolomCache} = …; $pengaturan->save()` → backup pertama ke Drive akan langsung `Unknown column` begitu Drive dikonfigurasi di kantor; kolomnya sekarang benar-benar dibuat (root folder tetap dari `.env`, hanya `gdrive_root_folder_id` yang dibuang). (2) Migration `2026_09_04_174904_drop_unused_columns_from_surat_keluar_table` bermaksud membuang `draf_konten_surat_keluar.lampiran` tapi menargetkan tabel `surat_masuk`/`surat_keluar`, sehingga kolom itu selamat 1 bulan -- tidak ada yang membacanya sejak P6, jadi hilangnya tidak mengubah perilaku. (3) `database/factories/UserFactory.php` masih murni boilerplate (`name`, `password`, `email_verified_at`, `remember_token`, state `unverified()`) sementara kolom riilnya `nama_lengkap`/`pin` -> `User::factory()->create()` dijamin gagal; ditulis ulang (+ state `admin()`). Sekalian: docblock `PengaturanInstansiController` dibersihkan dari arkeologi sesi yang sudah terjawab ("MODEL ASLI TIDAK ADA", "$fillable DIASUMSIKAN / WAJIB verifikasi", "logo lokal itu ASUMSI DESAIN saya bukan keputusan user" padahal C7 sudah dikonfirmasi, "logging belum disambungkan ke controller" padahal observer aktif sejak 1 Sep), dan komentar cache Drive sekarang menyebut perintah sinkronisasi, bukan `LampiranController`. `User::$hidden`/`$casts` ikut dirampingkan; satu assertion `assertNull($admin->remember_token)` di `PinAkunTest` diganti `Schema::hasColumn('users','remember_token') === false` supaya menguji keputusan, bukan kolom yang sudah tidak ada. (e) **Dua keputusan [DEFAULT-agent] saya sendiri dibatalkan, dengan alasan, bukan diam-diam**: S10 (pindah session/cache ke `database`) TIDAK dikerjakan -- satu server satu kantor, driver file sudah pakai `flock` untuk lock/rate-limiter, dan memindah ke DB cuma menambah round-trip per request sekaligus membuat login ikut mati saat DB bermasalah; karena itu tabel `sessions`/`cache` juga tidak dibuat. FULLTEXT juga tidak dibuat (alasan LIKE ada di langkah 8). Keduanya ditulis di Bagian 5 supaya agent berikutnya tidak menganggapnya kelupaan. (f) **Verifikasi**: `php artisan test` 95 lulus di skema baru (SQLite membangun dari file yang sama = bukti portabel), `migrate:fresh --seed` jalan bersih 13/13 di MariaDB, lalu lewat browser nyata: login, 11 halaman 200 (`/dashboard`, `/surat-masuk`, `/surat-keluar`, `/pencarian`, `/laporan`, `/aktivitas`, `/users`, `/klasifikasi-primer`, `/pengaturan-instansi/edit`, `/profil/pin`, `/pemusnahan-arsip`), POST surat masuk (`UJI-001` **dua kali** -> terbukti duplikat boleh, D9=a) dan surat keluar (`001/01/X/2026` lalu `002/01/X/2026`, baris `surat_counters` bertambah normal), 4 baris `aktivitas` tertulis. Artefak tes di-`forceDelete` sehingga DB dev kembali kosong (0 surat, 0 log) sambil tetap menyimpan 3 user + 2 klasifikasi + nama instansi. |
| 4 Okt 2026 | **L-24/Q2=a ditutup: Pint + Larastan.** (a) `composer require --dev laravel/pint nunomaduro/larastan:^3.0` **menggantung tanpa output sama sekali** sampai 2× timeout 10 menit: composer interaktif menunggu konfirmasi "trust this composer.json?" di lingkungan yang tidak punya TTY, jadi tidak ada satu byte pun ke stdout. Diagnosa yang benar: jalankan ulang dengan **`--no-interaction`** dan `timeout <detik>` di shell (bukan mengandalkan background job). Proses kedua (`dump-autoload`) juga ikut menggantung karena **lock composer dari proses pertama masih dipegang** -- dua `composer` tidak boleh jalan paralel di project yang sama; akibatnya `vendor/nunomaduro/larastan` sudah ada di disk tapi tidak terdaftar di `vendor/composer/autoload_psr4.php`. Beres setelah proses pertama dibunuh lalu `composer install --no-interaction` (rekonsiliasi `installed.php` + autoload). Catatan lingkungan lain: `mysqldump` ada di `C:\xampp\mysql\bin`, BUKAN `C:\xampp\bin`. (b) **Pint**: 34 dari 142 file perlu dirapikan (FQCN → `use`, spasi array, `ordered_imports`); dua commit terpisah -- style dulu, baru logika -- supaya diff-nya bisa dibaca. Sekarang `vendor/bin/pint --test` PASS semua file. (c) **Larastan level 5** (`phpstan.neon`, path `app`/`database`/`routes`): pertama **84 error**, dan polanya satu akar -- method relasi di model TIDAK punya return type, jadi `ModelHelper` Larastan tidak menganggapnya relasi sama sekali (`larastan.relationExistence` + `property.notFound` meledak untuk relasi yang sebenarnya ada). Diperbaiki dengan menambah return type ke **27 method di 10 model** (skrip sekali-pakai yang membaca `$this->belongsTo|hasMany|morphMany|...` dari body, lalu pint merapikan importnya) -- error langsung turun ke 35, dan IDE/analyzer apa pun sekarang tahu tipe `$surat->primer`. Skripnya dibuang setelah jalan; 95 tes tetap lulus (transformasi murni deklaratif). (d) **3 temuan nyata yang ikut diperbaiki, bukan di-baseline**: `PencarianController::kerangka()` menandai closure dalamnya dengan `Illuminate\Contracts\Database\Query\Builder` padahal memanggil `orWhereHas()` (yang cuma ada di builder Eloquent -- jalan di runtime, tapi annotation-nya salah); `DevSeeder` membaca `env('DEV_PIN')` yang **mengembalikan null begitu `php artisan config:cache` dijalankan**, diganti `getenv()`; dan `LampiranPolicy`/`UserFactory` sudah dibereskan lebih awal di langkah 11. (e) **Sisa 29 temuan masuk `phpstan-baseline.neon`** setelah dibaca satu-satu, bukan dibungkam massal: `morphTo` memang mengembalikan `Model` (jadi `$lampiran->lampiranable?->primer`, `hapusBerkasFisik()`, `arsipable_id` tak terbuktukan secara statis), `catch (QueryException)` yang dilempar PDO saat runtime tapi tidak dideklarasikan, 3 `nullsafe.neverNull`, dan 3 ketidakpresisian generic `Collection<int, ...>`. Alasan baseline ditulis di komentar `phpstan.neon` supaya agent berikutnya tahu itu pilihan sadar dan cara regenerasinya. `vendor/bin/phpstan analyse` sekarang **[OK] No errors**. (f) `AGENTS.md` Bagian 2 ("kenyataan sekarang") ikut diperbarui -- kolom itu masih menyebut Laravel 10 / Drive+queue sebagai kondisi berjalan padahal keduanya sudah diganti langkah 1&2; rujukan "AGENTS.md Bagian 5/9/10/11" dan "AGENTS.md 12.x" di 5 file kode yang sekarang menggantung ke file lama diperbaiki, dan catatan di kepala AGENTS.md menjelaskan cara membaca rujukan lama itu. |
| 4 Okt 2026 | **Permintaan user: aksi akun jadi popup di menu "Akun", + pratinjau logo instansi.** (a) **Desain**: topbar yang sebelumnya cuma "nama + tombol Keluar" sekarang punya dropdown **Akun** (nama, email, role, dan item **Ganti PIN**). Item itu membuka modal Bootstrap yang berisi form PIN lama/PIN baru/ulangi. Halaman `/profil/pin` **dihapus total** (route GET `profil.pin.edit`, `ProfilController@editPin`, view `profil/ganti-pin.blade.php`, dan link "Ganti PIN" di sidebar) -- bukan disembunyikan, karena satu aksi tidak boleh punya dua pintu; `route:list` sekarang cuma menampilkan `PATCH profil/pin`. (b) Yang dipertahankan justru **form `<form method=POST>` sungguhan** di dalam modal: JS mengirim `fetch` `PATCH` + `X-CSRF-TOKEN` + `Accept: application/json` dan controller (`ProfilController@updatePin`) membalas `JsonResponse` kalau `expectsJson()`, tapi kalau JavaScript mati tombol Simpan tetap bekerja lewat POST biasa dan controller membalas redirect + flash. Aturan yang sama dipakai `partials/lampiran-upload.blade.php`, dan alasan CSRF-nya: token dibaca dari `input[name=_token]` milik form itu sendiri (bukan `<meta>` global yang tidak ada di layout). 419 (token kedaluwarsa) ditangani eksplisit di popup dengan saran memuat ulang, karena itu layar yang paling sering muncul kalau form ditinggal. (c) **Bug kecil yang ikut kelihatan**: `updatePin` dulu melakukan `->with('status', ...)`, padahal `layouts/app.blade.php` hanya merender `session('success')` dan `session('error')` -- jadi pesan "PIN Anda berhasil diganti" **tidak pernah tampil** sejak fitur itu dibuat. Kunci flash dipindah ke `success`. (d) **Pratinjau logo** di `pengaturan-instansi/edit.blade.php`: setelah **Edit** ditekan dan berkas dipilih, `URL.createObjectURL(berkas)` langsung menampilkan gambar + nama + ukuran (`logo-baru.png (123 KB)`), dengan `URL.revokeObjectURL` sebelum penggantian berikutnya dan catatan bahwa logo lama masih dipakai sampai disimpan. Tidak ada perubahan controller yang diperlukan -- pratinjau murni sisi browser. (e) **Temuan lain di file yang sama**: satu karakter `a` nyasar di antara `--}}` dan `@section('content')` sehingga huruf itu ter-render di atas kartu pengaturan. Sudah dibuang. (f) **Tes**: `tests/Feature/PinAkunTest.php` -- dua tes lama yang menyerang `route('profil.pin.edit')` ditulis ulang jadi (1) popup ditemukan lewat halaman biasa (`data-bs-target="#modalGantiPin"`, `id="formGantiPin"`, `name="pin_lama"`, baik staf maupun admin) dan (2) `patchJson` `profil/pin` -> 422 + `assertJsonValidationErrors('pin_lama')` untuk PIN lama salah, lalu 200 `{"status": "..."}` untuk yang benar dan terbukti `Hash::check` berubah; tes tamu dipecah sendiri karena `actingAs()` membuat request berikutnya tetap login. **`tests/Feature/PengaturanInstansiTest.php` baru (3 tes)**: halaman menampilkan logo yang ada + elemen pratinjau + `input[name=logo]`, staf 403 di edit dan update, dan unggah logo baru menyimpan berkas baru sekaligus membuang berkas lama. Suite: **99 passed** (16 class). pint PASS 143 file, Larastan `[OK] No errors`. (g) **Verifikasi browser nyata** (bukan cuma tes): navigasi sungguhan ke `/pengaturan-instansi/edit` sebagai admin, klik item "Ganti PIN" di dropdown -> `#modalGantiPin` `.show` = true dengan 3 field; klik tombol Simpan dengan PIN lama sengaja salah -> kolom `pin_lama` dapat `is-invalid` + tulisan "PIN lama tidak cocok.", popup **tidak** menutup, tombol kembali aktif; setelah popup ditutup semua field kosong. Pratinjau logo diuji dengan `DataTransfer` + `File` palsu -> blok pratinjau terlihat, `src` diawali `blob:`, keterangan "logo-baru.png (0 KB)". Sesi dev sempat hilang setelah `migrate:fresh` (session file lama menunjuk id yang sudah dibangun ulang) dan login ulang diperlukan -- bukan bug kode. |

---

## 12.26 — Catatan Teknis Sesi 3 Sep 2026

### Konteks
Sesi pertama dengan akses langsung ke file project di drive A:\Pradana. Laravel 10.50.2, PHP 8.2.12, MySQL XAMPP.

### Bug yang Ditemukan dan Diperbaiki

| File | Bug | Perbaikan |
|---|---|---|
| `database/migrations/2026_07_01_0000010_create_aktivitas_table.php` | Kelebihan digit `0` di nama file → migration dieksekusi sebelum tabel `users` ada → foreign key gagal (errno: 150) | Renamed ke `2026_07_01_000010_create_aktivitas_table.php` via `Copy-Item` + `Remove-Item` |
| `database/migrations/2026_08_31_000001_create_pengajuan_hapus_lampiran_table.php` | Baris 28: kolom `diajukan_oleh` tidak `nullable()` tapi `->nullOnDelete()` → MySQL menolak | Ditambahkan `->nullable()` sebelum `->constrained('users')->nullOnDelete()` |
| `app/Services/GoogleDriveService.php` | `__construct()` langsung panggil `setAuthConfig()` tanpa cek file → crash di dev (file kredensial tidak ada) | Ditambahkan guard `if (file_exists($credentialsPath))` sebelum `setAuthConfig()` |

### File Dibuat / Diubah 3 Sep 2026

| File | Status | Keterangan |
|---|---|---|
| `resources/views/dashboard/index.blade.php` | **Baru** | View dashboard: 4 stat cards (total surat masuk/keluar, surat bulan ini, surat mendesak); grid 2 kolom (tabel surat masuk terbaru & surat keluar terbaru); tabel log aktivitas; bar chart visual klasifikasi primer terpopuler; tombol aksi cepat (tambah surat masuk/keluar) |
| `routes/web.php` | **Diperbarui** | Middleware `admin` dipasang di 23 route: `users.*` (semua), `klasifikasi-*` (dipecah: index terbuka, mutasi admin), `pengaturan-instansi.*` (semua), `pengajuan-hapus-lampiran index/setujui/tolak`. Route `pengajuan-hapus store` tetap terbuka (semua user login boleh ajukan) |
| `app/Http/Controllers/SuratKeluarController.php` | **Diperbarui** | `store()` diperbarui untuk lempar `bulan`, `klasifikasiPrimerId`, `klasifikasiSekonderId`, `klasifikasiTersierId` ke `generateNomorSurat()`. Method `generateNomorSurat()` diimplementasikan format D1: `%03d/%s/%s/%d` dengan tabel bulan romawi, kode klasifikasi diambil dari model, sekunder/tersier nullable. `TODO(D1)` dihapus. |
| `AGENTS.md` | **Diperbarui** | Rename dari `AGENTS (8).md`. Bagian 3 (status pengerjaan), Bagian 8 (LOCKED #16 diperbarui + #17 baru D1), Bagian 10 (D1 dicoret/terjawab), Bagian 11 (roadmap diperbarui) |
| `AGENTS_HISTORY.md` | **Diperbarui** | Rename dari `AGENTS_HISTORY (2).md`. Ditambah catatan sesi 3 Sep 2026 (baris ini) + seksi 12.26 |

### Versi Laravel yang Benar
- `php artisan --version` → **Laravel Framework 10.50.2** (bukan 13.x seperti klaim awal)
- PHP 8.2.12
- Database: MySQL (XAMPP `C:\xampp\mysql`)

### Celah Keamanan yang Ditutup (RBAC)
Sebelum sesi ini, controller-controller admin (Klasifikasi, PengaturanInstansi, User, sebagian PengajuanHapusLampiran) sudah menghapus penjagaan internal karena mengira route-level sudah diproteksi. Tapi `routes/web.php` belum pernah mendapat `->middleware('admin')` — menyebabkan siapa saja yang login bisa mengakses route admin. **Ditutup 3 Sep 2026.**

### Format Nomor Surat Keluar (D1 [LOCKED #17])
```
{urutan 3 digit}/{kodeP.kodeS.kodeT}/{bulan romawi}/{tahun}
Contoh: 001/01.01.01/IX/2026
```
- Urutan: global per tahun (D2), reset tiap tahun (D3)
- Kode: diambil dari model `KlasifikasiPrimer/Sekunder/Tersier` → jika sekunder/tersier null, bagiannya dilewati
- Implementasi: `SuratKeluarController::generateNomorSurat()` (lihat baris 154 dst)
- Bisa disesuaikan user saat serah terima jika ada Perbup/Permendagri yang berbeda

---

### Tambahan Catatan 4 Sep 2026 — View Yang Dibuat di Riwayat 13 (lihat baris 3–4 Sep 2026)

Semua view yang dibuat hari ini sudah melewati compile Blade (view:cache tanpa error). Relasi yang dipakai di view semua mengikuti nama relasi yang sudah diverifikasi di catatan 12.11/12.12.

| 4 Sep 2026 | **View Surat Masuk** — 4 file (index, create, edit, show) + 1 partial JS cascade. `SuratMasukController::index()` diperbarui: filter cari/sifat/klasifikasi_primer_id, kirim `$klasifikasiPrimer` ke view. `create()`/`edit()` diperbarui: eager-load `sekunder.tersier` untuk cascade dropdown. View `show` menampilkan lampiran (upload baru + tombol ajukan hapus jika >5 tahun). Lihat 12.27. |
| 4 Sep 2026 | **View Surat Keluar** — 4 file (index, create, edit, show) + 1 partial JS cascade. `SuratKeluarController::show()` diperbarui: tambah `lampiran` ke load(). View index memiliki filter tambahan: tahun & status_arsip. View edit: nomor surat bisa dikoreksi manual (warning banner). View show: tampilkan draf isi surat jika ada + tombol cetak PDF. Lihat 12.27. |
| 4 Sep 2026 | **View Klasifikasi** (Primer/Sekunder/Tersier) — 9 file total (3 index + 3 create + 3 edit). Index Primer: kode+nama+jumlah-sekunder. Index Sekunder: kode+nama+primer-induk+jumlah-tersier. Index Tersier: kode+nama+sekunder-induk+primer-induk (hierarki lengkap terbaca sekaligus). Semua index: tombol edit/hapus hanya tampil untuk admin (blade `@if(isAdmin())`), SweetAlert2 konfirmasi hapus. Form create/edit: sederhana, cuma 2–3 field, card centered. Lihat 12.27. |

---

## 12.27 — Catatan Teknis Sesi 4 Sep 2026

### Konteks
Sesi lanjutan 4 Sep 2026 — fokus pembuatan view (frontend). Stack tetap sama (Laravel 10.50.2, PHP 8.2.12).

### File Dibuat / Diubah 4 Sep 2026

| File | Status | Keterangan |
|---|---|---|
| `resources/views/surat-masuk/index.blade.php` | **Baru** | Tabel + filter cari/sifat/klasifikasi, badge berwarna per sifat/status, hapus admin-only via SweetAlert2 |
| `resources/views/surat-masuk/create.blade.php` | **Baru** | Form 4 seksi, cascade dropdown via partial JS |
| `resources/views/surat-masuk/edit.blade.php` | **Baru** | Form pre-filled, cascade restore state dari existing model |
| `resources/views/surat-masuk/show.blade.php` | **Baru** | Layout 2 kolom: detail + lampiran (upload + ajukan hapus >5thn) + klasifikasi sidebar |
| `resources/views/surat-masuk/_klasifikasi_cascade_js.blade.php` | **Baru** | Partial JS Primer→Sekunder→Tersier cascade, dipakai create & edit |
| `resources/views/surat-keluar/index.blade.php` | **Baru** | Tabel + filter cari/tahun/klasifikasi/status-arsip, tombol cetak PDF per baris |
| `resources/views/surat-keluar/create.blade.php` | **Baru** | Form + banner info nomor auto-generate |
| `resources/views/surat-keluar/edit.blade.php` | **Baru** | Nomor surat bisa dikoreksi manual, warning banner, status_arsip bisa diubah |
| `resources/views/surat-keluar/show.blade.php` | **Baru** | Draf isi surat + tombol cetak PDF, lampiran |
| `resources/views/surat-keluar/_klasifikasi_cascade_js.blade.php` | **Baru** | Partial JS cascade (identik polanya dengan surat-masuk) |
| `resources/views/klasifikasi-primer/index.blade.php` | **Baru** | Tabel kode/nama/jml-sekunder, tombol admin-only |
| `resources/views/klasifikasi-primer/create.blade.php` | **Baru** | Form 2 field: kode + nama |
| `resources/views/klasifikasi-primer/edit.blade.php` | **Baru** | Form pre-filled |
| `resources/views/klasifikasi-sekunder/index.blade.php` | **Baru** | Tabel kode/nama/primer-induk/jml-tersier |
| `resources/views/klasifikasi-sekunder/create.blade.php` | **Baru** | Dropdown pilih primer + kode + nama |
| `resources/views/klasifikasi-sekunder/edit.blade.php` | **Baru** | Form pre-filled + dropdown primer pre-selected |
| `resources/views/klasifikasi-tersier/index.blade.php` | **Baru** | Tabel 4 kolom: kode/nama/sekunder-induk/primer-induk |
| `resources/views/klasifikasi-tersier/create.blade.php` | **Baru** | Dropdown sekunder (dengan kode primer dalam kurung) + kode + nama |
| `resources/views/klasifikasi-tersier/edit.blade.php` | **Baru** | Form pre-filled + dropdown sekunder pre-selected |
| `app/Http/Controllers/SuratMasukController.php` | **Diperbarui** | `index()`: filter query + kirim `$klasifikasiPrimer`. `create()`/`edit()`: eager-load `sekunder.tersier` |
| `app/Http/Controllers/SuratKeluarController.php` | **Diperbarui** | `show()`: tambah `lampiran` ke `load()` |
| `AGENTS.md` | **Diperbarui** | Roadmap Bagian 11: baris `View/frontend` dipecah jadi sub-item granular dengan status tiap folder |
| `AGENTS_HISTORY.md` | **Diperbarui** | Tambah baris riwayat 4 Sep 2026 + seksi 12.27 ini |

### View yang Masih Belum Dibuat
- `pengajuan-hapus-lampiran/index.blade.php`

### Tambahan — View Users (4 Sep 2026, sesi yang sama)

| File | Status | Keterangan |
|---|---|---|
| `resources/views/users/index.blade.php` | **Baru** | Tabel users: avatar inisial, badge role berwarna (admin=merah, kepala=kuning, perangkat=abu), badge aktif/nonaktif (soft-delete). Tombol hapus tidak muncul untuk diri sendiri. SweetAlert2 konfirmasi hapus. |
| `resources/views/users/create.blade.php` | **Baru** | Form: nama lengkap, email, role (dengan keterangan hak akses), PIN 6 digit + toggle show/hide + konfirmasi PIN. Alert info A3. |
| `app/Http/Controllers/UserController.php` | **Diperbarui** | Ditambah method `destroy()`: soft-delete + guard self-deletion (`abort_if` jika hapus diri sendiri). |
| `routes/web.php` | **Diperbarui** | `users` resource: `only` diperluas dari `['index','create','store']` → `['index','create','store','destroy']`. |

### Tambahan — View Pengajuan Hapus Lampiran (4 Sep 2026, sesi yang sama)

| File | Status | Keterangan |
|---|---|---|
| `resources/views/pengajuan-hapus-lampiran/index.blade.php` | **Baru** | Daftar kartu (bukan tabel) per pengajuan menunggu. Tiap kartu: info file, link surat induk (masuk/keluar) dengan tanggal & diffForHumans, nama pengaju, alasan. Aksi: **Setujui** (SweetAlert2 konfirmasi hapus permanen dari Drive) + **Tolak** (toggle form inline dengan `catatan_admin` opsional). State kosong jika tidak ada pengajuan menunggu. Data: `$pengajuanList` collection dengan relasi `lampiran.lampiranable` dan `pengaju`. |
| `AGENTS.md` | **Diperbarui** | Roadmap `View/frontend` berubah dari `🔄 parsial` ke `[x] selesai penuh`. |
| `AGENTS_HISTORY.md` | **Diperbarui** | Entri ini. |

> **Milestone**: Semua view halaman MVP telah selesai per 4 Sep 2026. Sisa roadmap: Testing & Deployment.

---

## 13.5 Okt — Kop PDF tata naskah dinas, logo tanpa symlink, /pencarian dihapus, baca isi lampiran (5 Okt 2026)

Permintaan user satu kalimat empat bagian ("tingkatkan PDF-nya ke standar perkantoran desa, logo tidak keluar, rapikan layout Pengaturan Instansi, hilangkan halaman pencarian arsip") lalu dua susulan ("hilangkan tombol aksi cepat di dashboard", "baca isi lampiran surat masuk otomatis, tampilkan di form untuk divalidasi"). Rujukan standar yang dipakai: **Permendagri 1/2023 tentang Tata Naskah Dinas** + contoh kop pemerintah desa (halaman JDIH / website kecamatan). PDF peraturan negara tidak bisa diekstrak teksnya dari sisi agent, jadi struktur dibangun dari sumber HTML + kebiasaan kop desa yang terdokumentasi; **angka margin tidak dinyatakan sebagai kutipan resmi** — dipakai pola lazim `@page { margin: 2.5cm 2.5cm 2.5cm 3cm }` (kiri 3 cm) yang tinggal digeser begitu kop resmi desa masuk (F3).

### File dibuat / diubah

| File | Status | Keterangan |
|---|---|---|
| `database/migrations/2026_10_05_000001_tambah_bagian_kop_pengaturan_instansi.php` | **Baru** | `nama_kabupaten`, `nama_kecamatan`, `kode_pos` (nullable). Additive supaya `php artisan migrate` biasa cukup — file squash `2026_10_04_100002` TIDAK diedit, jadi database kantor tidak perlu `migrate:fresh`. |
| `database/migrations/2026_10_05_000002_tambah_hasil_baca_isi_surat_masuk.php` | **Baru** | `isi_hasil_baca` (longText), `isi_dibaca_dari`, `isi_dibaca_pada`, `isi_terverifikasi_pada`. |
| `app/Models/PengaturanInstansi.php` | **Dirombak** | Helper kop satu sumber: `kopBarisAtas()`, `kopAlamat()`, `tempatSurat()` (buang prefix Pemerintah/Sekretariat/Kantor), `sebutanPemimpin()` ("Pemerintah Desa" jadi "Kepala Desa"), `logoUrl()` (route + cache-buster dari nama berkas), `logoPathUntukPdf()` (path disk, null kalau berkas hilang). |
| `app/Http/Controllers/LogoInstansiController.php` | **Baru** | `GET /instansi/logo` di luar grup `auth` (halaman login butuh), baca disk `public` langsung, MIME dari ekstensi, `Cache-Control: immutable`. Pengganti `Storage::url()` yang mati diam-diam kalau `storage:link` belum ada. |
| `routes/web.php` | **Diubah** | + `instansi.logo`; − route `pencarian` + `use PencarianController`; + `PATCH surat-masuk/{surat_masuk}/isi`; catatan header #9 ditandai dihapus, #10 baru. |
| `app/Support/Terbilang.php` | **Baru** | Angka ke kata, murni PHP tanpa dependency. Dipakai `0 (nol) berkas` (P6) dan hari/tanggal Berita Acara. |
| `resources/views/partials/kop-pdf.blade.php` | **Baru** | Kop dipakai 3 dokumen PDF; semua gaya inline supaya tidak tertimpa style pemanggil; `?? null` defensif karena tidak semua controller mengirim `$instansi`/`$logoPath` (variabel tak ada = ErrorException, bukan null). |
| `resources/views/surat-keluar/cetak.blade.php` | **Ditulis ulang** | Struktur naskah dinas lengkap (rincian di AGENTS.md Bagian 3, "Perubahan 5 Okt" no. 1). |
| `resources/views/pemusnahan-arsip/berita-acara.blade.php` | **Diubah** | Ikut partial kop; blok "Yang bertanda tangan" + hari/tanggal in huruf; tempat & tanggal di atas tanda tangan; jumlah berkas terbilang. |
| `resources/views/laporan/agenda.blade.php` | **Diubah** | Kop lokal dibuang, ikut partial; `@page` margin. |
| `app/Http/Controllers/CetakSuratKeluarController.php` | **Diubah** | `logoPathUntukPdf()`; notasi lampiran terbilang; `kodeKlasifikasi` 3 level; return type eksplisit. ⚠️ `$pdf->stream()` mengembalikan `Illuminate\Http\Response`, BUKAN `StreamedResponse` — return type yang salah memberi 500 `TypeError`, dan ini yang menangkapnya. |
| `app/Http/Controllers/PemusnahanArsipController.php`, `LaporanController.php` | **Diubah** | Logo dari disk + variabel terbilang/tempat. |
| `app/Http/Requests/UpdatePengaturanInstansiRequest.php` | **Diubah** | +3 field kop; `no_telp`/`email` jadi `nullable`; panjang kolom dicocokkan ke migration hasil squash (komentar "asumsi panjang" sudah basi). |
| `resources/views/pengaturan-instansi/edit.blade.php` | **Dirombak** | Dua kolom: isian per seksi + **pratinjau kop hidup** (mengikuti ketikan, bukan data tersimpan) dan preview logo; pola readonly-sampai-Edit dipertahankan. |
| `resources/views/auth/login.blade.php` | **Diubah** | `logoUrl()`; baris Kabupaten/Kecamatan di panel kiri; komentar soal `storage:link` dibuang. |
| `app/Services/PembacaIsiLampiran.php` | **Baru** | PDF (`smalot/pdfparser`), DOCX/XLSX (`ZipArchive` + XML). JPG/PNG/DOC/XLS ditolak dengan `catatan` (tidak ada OCR di server kantor). Batas 15 MB / 20.000 karakter. Tidak pernah melempar. |
| `app/Http/Controllers/LampiranController.php` | **Diubah** | `cobaBacaIsi()` khusus `SuratMasuk`; hasil di respons JSON (`baca.teks`, `baca.catatan`) dan flash non-JS; tulis lewat `forceFill` karena kolomnya di luar `$fillable`. |
| `app/Http/Controllers/SuratMasukController.php`, `SuratKeluarController.php` | **Diubah** | `updateIsi()` + perluasan filter `cari` (L-15 dipindah ke daftar). |
| `app/Http/Requests/UpdateIsiSuratMasukRequest.php` | **Baru** | Endpoint terpisah supaya tidak kena validasi "semua field wajib" dari `UpdateSuratMasukRequest`. |
| `app/Models/SuratMasuk.php` | **Diubah** | 4 kolom hasil baca sengaja di luar `$fillable` (alasannya ditulis di file) + cast 2 timestamp. |
| `resources/views/surat-masuk/show.blade.php`, `edit.blade.php`, `partials/lampiran-upload.blade.php` | **Diubah** | Kartu "Hasil Baca Isi Lampiran" (badge belum/sudah diverifikasi + checkbox "jadikan ringkasan"), panel + tombol "Masukkan ke Ringkasan" (dengan konfirmasi) di form edit, hook `window.pradanaHasilBacaIsi` dipanggil pengunggah AJAX tanpa reload. |
| `resources/views/dashboard/index.blade.php` | **Diubah** | Blok "Aksi Cepat" dihapus. |
| `resources/views/layouts/app.blade.php` | **Diubah** | Link sidebar "Pencarian Arsip" dihapus. |
| `resources/views/errors/404.blade.php` | **Diubah** | Arahkan ke daftar surat (kotak cari), bukan `/pencarian`. |
| `app/Http/Controllers/PencarianController.php`, `resources/views/pencarian/` | **DIHAPUS** | Lihat L-15 di AGENTS.md Bagian 4: penggalian isi pindah ke filter daftar. |
| `tests/Feature/CetakSuratKeluarTest.php`, `tests/Feature/IsiLampiranSuratMasukTest.php` | **Baru** | 7 + 13 tes. Struktur PDF diuji dari HTML hasil render (byte PDF dikompres, tidak bisa dipakai membuktikan urutan baris); ekstraksi PDF diuji dengan PDF sungguhan buatan dompdf. |
| `tests/Feature/PencarianDanLogTest.php`, `PengaturanInstansiTest.php`, `HalamanErrorTest.php` | **Diubah** | 6 tes `/pencarian` jadi 7 tes filter daftar; +3 tes logo/helper kop; 404 assert arah baru. |
| `phpstan-baseline.neon` | **Diregenerasi** | 31 temuan (kategori sama seperti sebelumnya); komentar alasan sekarang ada di kepala file. |
| `docs/manual-pemakaian.md`, `README.md` | **Diubah** | Bagian 2, 3, 5, 6, 7, 13.3, 15 + A.2 (tidak perlu `storage:link`) + A.8 (bukan OCR) + jumlah tes. |

### Keputusan yang diambil agent ([DEFAULT-agent], boleh direvisi tanpa tanya user)

- `no_telp`/`email` jadi opsional: kantor tanpa email tidak boleh terkunci tidak bisa menyimpan kop.
- Baris tempat tanggal surat memakai `tempatSurat()` (prefix lembaga dibuang dari `nama_instansi`) daripada menambah kolom baru untuk satu kata.
- Hasil baca mesin **tidak pernah** otomatis mengisi `ringkasan`; hanya lewat centangan eksplisit user, dan `isi_terverifikasi_pada` menjadi penanda manusia sudah memeriksa.
- Surat keluar tidak ikut dibacakan (permintaan user menyebut surat masuk, dan `draf.isi_surat` memang buatan orang). Layanannya sudah umum, tinggal panggil dari jalur keluar kalau nanti diminta.

### Catatan lingkungan

`composer require smalot/pdfparser` butuh **±6,5 menit** untuk tahap `dump-autoload -o` di laptop user (classmap 44.295 kelas di bawah Windows/Defender). Perintah yang tampak "menggantung tanpa output" sebenarnya sedang dipotong timeout; akibatnya paket ada di disk + `installed.json` tapi TIDAK terdaftar di autoloader (`class_exists` false, `autoload_namespaces.php` kosong). Pemulihannya satu kali: `composer dump-autoload -o --no-scripts` dengan timeout panjang — bukan `composer install` berulang kali. Ini pengulangan jebakan yang sudah dicatat 4 Okt.

## 13.9 Okt — Pencarian arsip dibenahi: multi-kata, isi lampiran tergali, peringkat relevansi, tab "Semua arsip" (9 Okt 2026)

Permintaan user: "berikan aku plan peningkatan project ini, misal search engine optimization dan lain lain". Dua hal diluruskan sebelum kode ditulis: (1) SEO klasik **bukan** target — aplikasi ini arsip kantor di belakang login, membuatnya ter-index Google justru melanggar L-01; yang benar adalah anti-indexing (`public/robots.txt` bawaan Laravel isinya `Disallow:` kosong = boleh crawl), dan itu **belum dikerjakan** (masuk track keamanan, belum dieksekusi). (2) "search engine" yang bisa dioptimasi adalah pencarian arsip di dalam aplikasi. User memilih **Track 1a+1b** dan memutuskan **FULLTEXT ditahan** ("Tahan LIKE dulu, FULLTEXT nanti") → keputusan S11 tidak direvisi, **tidak ada migration baru sama sekali**.

### File dibuat / diubah

| File | Status | Keterangan |
|---|---|---|
| `app/Support/CariArsip.php` | **Baru** | Split kata (maks 8, tiap kata maks 60 char), escape LIKE, `terapkan()` (OR antar kolom + `whereHas`, AND antar kata), `peringkat()`/`ekspresiSkor()` (CASE 4 tier), `sorot()` (escape dulu, baru `<mark>`). |
| `app/Support/FilterArsip.php` | **Baru** | Isi kotak filter satu sumber (L-14): cari, sifat, klasifikasi primer, status arsip, tahun (interval terbuka), usang + `gabungan()` penanda `?jenis=semua`. |
| `app/Services/DaftarArsipGabungan.php` | **Baru** | Kerangka UNION (id, jenis, tanggal_surat, skor) + `LengthAwarePaginator` 20/halaman, model + relasi dimuat hanya untuk baris halaman itu. Pola diwarisi dari `PencarianController` lama (git `b244cff~1`). |
| `app/Http/Controllers/Concerns/MenampilkanArsipGabungan.php` | **Baru** | Cabang `?jenis=semua` dipakai dua controller — menyalinnya dua kali adalah sumber perbedaan perilaku yang sudah terbukti di project ini. |
| `app/Models/SuratMasuk.php`, `SuratKeluar.php` | **Diubah** | `scopeCari()` + `scopePalingRelevan()`, daftar kolom & relasi pencarian jadi konstanta di model. `isi_hasil_baca` masuk daftar — kolomnya sudah ada sejak 5 Okt tapi tidak pernah dicari. |
| `app/Http/Controllers/SuratMasukController.php`, `SuratKeluarController.php` | **Diubah** | `index()` tinggal `FilterArsip::terapkan()` + `palingRelevan()`; blok filter tersalin (~50 baris per controller) dibuang; `whereYear` → interval terbuka. |
| `resources/views/partials/tab-arsip.blade.php` | **Baru** | Surat Masuk / Surat Keluar / **Semua arsip**. Tab ketiga cuma menambah `?jenis=semua` di halaman asal — **tidak ada route baru**, lihat L-15. |
| `resources/views/partials/daftar-gabung.blade.php` | **Baru** | Tabel gabungan; sengaja tidak ada tombol Nyahkan/destroy di sini (aksi destruktif butuh halaman suratnya, L-05). |
| `resources/views/surat-masuk/index.blade.php`, `surat-keluar/index.blade.php` | **Diubah** | `$suratMasuk`/`$suratKeluar` → `$arsip`; +tab; hidden `jenis` di form filter (supaya filter tidak diam-diam mengeluarkan user dari mode gabungan); `<mark>` di nomor/pengirim-penerima/perihal; teks bantuan filter ditulis ulang. |
| `tests/Feature/CariArsipTest.php` | **Baru** | 25 tes SQLite. Helper `nomorDiLayar()` mengambil nomor dari tautan detail, bukan seluruh HTML, supaya `assertNotContains` tidak lolos kena teks lain. |
| `tests/Feature/CariArsipMariaDbTest.php` | **Baru** | 10 tes di MariaDB asli (`mysql_test_a`), skip dengan pesan jelas kalau XAMPP mati. Membersihkan barisnya sendiri: `PENANDA='MJT'`, klasifikasi `ZC`. |
| `docs/manual-pemakaian.md` | **Diubah** | Bagian 7 ditulis ulang: multi-kata, yang paling mirip dulu, tab Semua arsip, `%`/`_` huruf biasa, trik "kurangi kata". |
| `AGENTS.md` | **Diubah** | Header tanggal; Bagian 3 "Perubahan 9 Okt" + jumlah tes 123 → 158; baris L-15 diperluas. |

### Bug nyata yang ditemukan (dua-duanya lewat tes, bukan lewat membaca kode)

- **`orWhereHas()` menempel OR ke constraint relasi.** `whereHas('drafKonten', fn ($d) => $d->where('isi_surat', $like)->orWhere('tembusan', $like))` memberi `WHERE fk = surat.id AND isi_surat LIKE ? OR tembusan LIKE ?`; AND menang, jadi `tembusan LIKE ?` berdiri sendiri dan satu baris draf milik surat lain membuat SEMUA surat cocok. Lampiran punya lubang yang sama (`... type = ? OR nama_file LIKE ?`). Ini **cacat kode lama** (5 Okt) yang tidak kelihatan sampai ada tes dengan dua surat dan hanya satu berdraf. Perbaikan: bungkus `$rel->where(fn ($d) => ...)` di dalam callback relasi.
- **Kolom `date` disimpan beda oleh dua engine.** Eloquent menulis cast `date` pakai format `Y-m-d H:i:s`. MariaDB (kolom DATE) memotong jam; SQLite menyimpan string utuh. Jadi `whereBetween('tanggal_surat', ['2026-01-01','2026-12-31'])` **membuang surat tertanggal 31 Desember di SQLite saja** — persis di engine tempat suite berjalan. Yang menangkap: tes yang membandingkan hasil baru dengan `whereYear` lama. Solusi: interval setengah terbuka `>= 1 Jan` dan `< 1 Jan tahun depan`, benar di dua engine dan tetap memakai index `tanggal_surat`.

### Keputusan yang diambil agent ([DEFAULT-agent], boleh direvisi tanpa tanya user)

- **Karakter escape LIKE = `!`, bukan `\`.** Satu-satunya pilihan yang sintaksnya identik di MariaDB dan SQLite: `ESCAPE '\'` dimakan parser stringliteral MariaDB, `'\\'` di SQLite berarti dua karakter dan ESCAPE menuntut satu. Ditulis di kepala kelas `CariArsip` supaya tidak "dirapikan" jadi `\` oleh orang/kemudian hari.
- **Mode gabungan tidak dapat route sendiri**, dan `?sampah=1` selalu menang atasnya: tempat sampah memang per-jenis, dan tombol Pulihkan dari daftar campuran tidak jelas arahnya.
- **Peringkat relevansi cuma 4 tier berbasis CASE**, tanpa bobot TF/IDF: tujuannya "nomor yang orang ketik muncul pertama", bukan mesin peringkat. Naik level = pindah FULLTEXT, itu keputusan user.
- **`provinsi_*`, `kota_*`, `lokasi_fisik` ikut digali** — pencarian rak/box fisik adalah pemakaian nyata arsip desa, biayanya cuma beberapa `LIKE` tambahan.
- **Daftar gabungan sengaja tidak punya tombol aksi** (Nyahkan/hapus/status arsip); hanya Detail. Menyatukan tombol per-jenis ke satu baris butuh logika otorisasi dua tabel di satu loop — tidak sepadan untuk kenyamanan kecil.

### Susulan 9 Okt — anti-index + header keamanan (robots.txt, X-Robots-Tag, CSP)

Dipilih user setelah plan disajikan ("jalankan saja semua"). Bukan SEO — arsip kantor di belakang login justru tidak boleh ter-index (L-01). Yang mengejutkan adalah kondisi awalnya: `public/robots.txt` bawaan Laravel berisi `Disallow:` KOSONG, dan nilai kosong berarti "silakan jelajahi semua halaman".

| File | Status | Keterangan |
|---|---|---|
| `public/robots.txt` | **Diubah** | `Disallow: /` + komentar alasan (jangan tambah `sitemap.xml`; `noindex` dijamin header, bukan berkas ini). |
| `app/Http/Middleware/TandaiArsipPrivat.php` | **Baru** | Dipasang di AWAL grup `web` (`Kernel.php`), bukan `bootstrap/app.php` — project ini masih struktur bootstrap lama. |
| `app/Support/TandaiPrivat.php` | **Baru** | Isi header satu sumber, dipakai middleware DAN exception handler. |
| `app/Exceptions/Handler.php` | **Diubah** | Override `render()`. Tanpa ini header tidak menempel di 404/419/500/503, karena exception di-render di luar pipeline middleware — dan halaman error itulah yang paling sering ditemui crawler. |
| `config/security.php` | **Baru** | `csp_aktif` (dari `SECURITY_CSP`) + isi CSP. Lewat config karena `env()` mengembalikan null setelah `config:cache` (jebakan yang sama dengan `DEV_PIN`). |
| `.env.example` | **Diubah** | +`SECURITY_CSP=true` dengan alasan singkat. Sekalian: komentar sesi yang masih menawarkan `SESSION_DRIVER=database` (S10) diperbaiki — S10 dibatalkan 4 Okt, tabel sessions/cache memang tidak dibuat. |
| `resources/views/layouts/app.blade.php`, `auth/login.blade.php`, `errors/layout.blade.php` | **Diubah** | `<meta name="robots" content="noindex, nofollow, noarchive">` sebagai lapisan kedua. |
| `tests/Feature/HeaderKeamananTest.php` | **Baru** | 7 tes: login/daftar/route logo publik/halaman error, isi CSP yang rawan patah (`'unsafe-inline'` untuk `@push(scripts)` & `onclick=`, `blob:` untuk pratinjau logo, dua host CDN, `form-action 'self'`), sakelar CSP, dan `robots.txt` benar-benar melarang (`Disallow: /`, tanpa direktif `Sitemap:`). |

Isi CSP: `'unsafe-inline'` pada script & style itu SENGAJA dan tercatat alasannya di file — seluruh Blade memakai `@push('scripts')`/`onclick=` dan tiap dokumen PDF menulis `<style>` inline; menggantinya dengan hash/nonce berarti merombak semua view. Yang ditahan CSP adalah sumber daya host tak dikenal, form keluar, dan frame. `img-src ... blob:` dibutuhkan pratinjau logo (4 Okt); `font-src` ke cdnjs untuk Font Awesome.

Verifikasi nyata (bukan hanya tes): server dev dijalankan atas izin user, login akun dev, lalu dicek lewat browser + `curl -I`. Header muncul di `/login`, daftar surat, `/instansi/logo`, `/AlamatTidakAda` (404), dan `/surat-keluar/3/cetak` yang bertipe `application/pdf`. Console browser kosong di `/surat-masuk/create` (halaman paling banyak JS) → CSP tidak memblok apa pun; `window.Swal` dan `window.bootstrap` tetap terdefinisi. Data berpemarka `UJI-CARI-*`/`UJI-SORTIR-1` (+ lampiran, draf, log-nya) dibuat untuk uji lalu dibuang lewat skrip sekali pakai yang ikut dihapus; satu surat keluar `001/02.01.01/X/2026` buatan user 3 Okt tersisa dan tidak disentuh.

Yang masih terbuka dari track keamanan (sengaja belum dikerjakan, bukan kelupaan): cek MIME `finfo` untuk unggah lampiran (sekarang whitelist ekstensi saja), rate limit di jalur unduh lampiran & cetak PDF (yang ada baru login 5/menit), dan `SESSION_SECURE_COOKIE` yang baru berarti setelah HTTPS diputuskan (S14).

### Perapian kode & alur data — Fase 1 (9 Okt 2026)

Permintaan user: "rapikan semua code dan alur data nya dan lainnya yang bisa di
rapikan/optimalkan, analisis, buat plan lalu analisis lagi, baru dijalankan".
Rencana + tabel pengukuran ada di `docs/plan-perapian-kode.md`; bagian ini riwayat
keputusannya. Fase 2–5 belum dikerjakan.

| File | Status | Keterangan |
|---|---|---|
| `app/Support/RentangTanggal.php` | **Baru** | `satuHari`/`rentang`/`bulan`/`terapkan` — interval setengah terbuka `[awal, akhir+1hari)` untuk semua filter tanggal. `self::` (bukan `static::`) untuk method private: PHPStan menemukannya, PHP sendiri memanggil versi kelas induk diam-diam. |
| `app/Http/Controllers/LaporanController.php` | **Diubah** | `tanyakan()` (`@template TModel of Model`), `barisMasuk()`/`barisKeluar()`/`isiUmum()`, `jumlah()` = COUNT di database. Nama kolom dinamis `$kolomLawan` dibuang. |
| `app/Http/Controllers/DashboardController.php` | **Diubah** | 9 `count()` → 2 agregat `SUM(CASE WHEN …)`; `whereMonth`+`whereYear` → `RentangTanggal::bulan()`; `KlasifikasiPrimer::all()` → `get(['id','kode','nama'])`; `maksTotalKlasifikasi` dihitung di controller. |
| `app/Http/Controllers/AktivitasController.php` | **Diubah** | Cari lewat `CariArsip::terapkan()` (escape + multi-kata), rentang tanggal setengah terbuka satu/dua sisi, periode terbalik dibalik, `latest('created_at')+latest('id')`, `BARIS_PER_HALAMAN = 30` dengan alasan kenapa TIDAK 20. |
| `app/Providers/AppServiceProvider.php` | **Diubah** | Badge antrian `layouts.app`: dua `count()` → satu `UNION ALL` lewat builder model. Composer ini jalan di SETIAP halaman. |
| `app/Models/*.php` (12 file) | **Diubah** | 31 method relasi sekarang `@return BelongsTo<Related, $this>` / `HasMany<…>` / `MorphMany<…>`; `SuratMasuk`/`SuratKeluar` dapat `@property-read int $lampiran_count`; `$with` global di `KlasifikasiPrimer`/`KlasifikasiSekunder` dibuang (terukur: `klasifikasi_sekunder` dibaca 3× per muat dashboard). |
| `app/Console/Kernel.php` | **Diubah** | `arsip:daftar-usang` mingguan + `withoutOverlapping()`. |
| `phpstan-baseline.neon` | **Diubah** | **26 → 7 entri**, hasil analisis ulang, bukan pembungkaman. |
| `tests/MariaDbHarness.php` | **Baru** | Plumbing tes engine-asli: skip dengan pesan kalau MariaDB mati, migrate `mysql_test_a`, bersih-bersih lewat prefix `PENANDA` + kode klasifikasi. `NomorSuratKeluarTest` sengaja tidak ikut (butuh dua koneksi). |
| `tests/Feature/{JumlahQueryLayar,BatasTanggalLaporan,StatistikDashboard,LogAktivitasFilter,FlashKonsisten,AgregatMariaDb}Test.php` | **Baru** | 28 tes; rinciannya di AGENTS.md Bagian 3. `CariArsipMariaDbTest` kehilangan ±90 baris plumbing yang kini dipakai bersama. |

Yang dibuktikan, bukan diasumsikan:

- **Surat 31 Desember hilang dari rekap & Buku Agenda.** Sebelum perbaikan, CSV dari fixture 4 surat hanya memuat `UKU-DEPAN` dan `UKU-TENGAH`. Penyebabnya `whereBetween` + cast `date` yang ditulis Eloquent sebagai `Y-m-d H:i:s` (MariaDB memotong jam, SQLite tidak).
- **Angka dashboard identik sebelum/sesudah** (`StatistikDashboardTest`) — refactor agregat tidak boleh mengubah satu pun kartu.
- **`SUM(CASE …)` dan `UNION ALL` diterima MariaDB** lewat HTTP, dengan assertion SELISIH (`AgregatMariaDbTest`), karena `mysql_test_a` juga dipakai `NomorSuratKeluarTest` dan dashboard menghitung seluruh tabel.
- **Semua kunci flash dirender layout** (`FlashKonsistenTest` memindai `->with('kunci',` di `app/` vs `session('kunci')` di `layouts/app.blade.php`).

Keputusan yang diambil agent (boleh direvisi, alasannya ada di kode): pagination log tetap 30; `isiUmum()` mempertahankan `?-> … ?? '-'` yang oleh PHPStan dianggap berlebihan (defensif untuk baris yatim, masuk baseline); ambang guardrail = angka terukur + 2.

Kesalahan selama pengerjaan yang dicatat supaya tidak diulang: (1) menyebut fixture pengukuran "selesai" sebelum jalur kodenya benar-benar tereksplorasi — 40 surat lama harus dibuat `inaktif` + tertanggal 2019 supaya `kandidatPemusnahan()` jalan; (2) skrip penyisip docblock yang variabelnya salah nama (`$type` alih-alih `$tipe`) sehingga docblock satu-baris ketimpa jadi dua baris rusak — ketahuan oleh `php -l` sebelum ada tes yang sempat lulus semu, dan hasilnya tetap dibaca baris per baris.

### Perapian kode & alur data — Fase 2 (9 Okt 2026, hari yang sama)

Lanjutan langsung dari Fase 1 dalam hari yang sama; rinciannya di `docs/plan-perapian-kode.md`. Fokus: alur data pemusnahan arsip dan halaman show surat.

| File | Status | Keterangan |
|---|---|---|
| `app/Models/Concerns/UmurArsip.php` | **Baru** | `lewatRetensi()` + `umurTahun()`, dipakai `SuratMasuk` & `SuratKeluar`. Menutup pelanggaran L-21: view surat masuk dulu menghitung umur dari `tanggal_diterima`. |
| `app/Support/RetensiArsip.php` | **Baru** | Satu sumber angka retensi (E1/L-04 [LOCKED], 5 tahun) + `batas()`. Dipakai trait, `PemusnahanArsipController`, dan `arsip:daftar-usang`. |
| `app/Http/Controllers/PemusnahanArsipController.php` | **Diubah** | `kandidatPemusnahan()`: `withCount('lampiran')`, `with('primer')` dibuang, antrean dicek lewat satu query + `whereNotIn` (bukan memuat semua item lalu `in_array`). `store()`: `idLayak()` membatasi validasi ke id yang dikirim form; snapshot item dari satu query per jenis lewat `dataItem()`. 45 → 4 query, dan tidak tumbuh lagi. |
| `app/Models/Lampiran.php` | **Diubah** | `isEligibleForDeletion()` → `layakDihapus()` (kosakata Indonesia konsisten) dan isinya mendelegasikan ke `UmurArsip::lewatRetensi()` induk, dengan guard `instanceof` karena `lampiranable` itu morph. Import `Carbon\Carbon` yang jadi mati dibuang. |
| `app/Http/Controllers/SuratMasukController.php`, `SuratKeluarController.php` | **Diubah** | `show()` eager-load `lampiran.pengajuanHapus` + `lampiran.pengunggah`. |
| `resources/views/surat-masuk/show.blade.php`, `surat-keluar/show.blade.php` | **Diubah** | Blok `@php $umurTahun = … @endphp` + `$lamp->pengajuanHapus()->exists()` (satu query per berkas) diganti `$surat->lewatRetensi()` + cek koleksi yang sudah dimuat. |
| `app/Console/Commands/DaftarSuratUsangCommand.php` | **Diubah** | Default `--tahun` diambil dari `RetensiArsip::TAHUN`; opsi manual tetap bisa diisi (E5: keputusan di tangan staf). Dijalankan sungguhan dua-duanya (`arsip:daftar-usang` dan `--tahun=3`). |
| `tests/Feature/AjukanHapusLampiranTampilTest.php` | **Baru** | 5 tes perilaku tombol "Ajukan Hapus", termasuk kasus L-21 (diterima 2019, dibuat 2025 → tidak boleh tampil) dan badge "Menunggu" per berkas. |
| `tests/Feature/JumlahQueryLayarTest.php` | **Diubah** | Fixture layar show diganti ke surat BERBERKAS (sebelumnya surat id 1 tanpa lampiran, jadi N+1 tidak pernah terukur); ambang `/pemusnahan-arsip/create` 47 → 6; tes baru menggandakan kandidat 40 → 80 dan menuntut jumlah statement yang SAMA. |
| `phpstan-baseline.neon` | **Diubah** | 7 → 6 entri (`Model::$tanggal_surat` di `Lampiran` hilang karena delegasi ke model bertipe konkret). |

Kesalahan yang dibuat dan ditangkap suite (dicatat karena berharga): konstanta retensi pertama-tama ditaruh di trait lalu diakses sebagai `UmurArsip::BATAS_RETENSI_TAHUN` dari controller — PHP melarang akses konstanta trait dari luar kelas pemakainya, seluruh layar pemusnahan langsung 500 dan 10 tes gagal dalam sekali jalan. Perbaikannya bukan menambal satu tempat: angkanya pindah ke kelas konstanta biasa `App\Support\RetensiArsip`, yang memang bisa dibaca dari model, controller, maupun perintah artisan.

Verifikasi: `php artisan test` 198 passed / 0 skipped (MariaDB hidup, jadi 15 tes engine-asli ikut jalan), `Pint` 171 file lolos, `Larastan` level 5 bersih dengan 6 entri baseline yang masing-masing sudah dibaca satu-satu. Dev server tidak dijalankan untuk fase ini — buktinya lewat HTTP di feature test, bukan lewat browser.

### Perapian kode & alur data — Fase 3 (9 Okt 2026, subset bedah)

Fase 3 di rencana (`docs/plan-perapian-kode.md`) juga berisi deduplikasi controller; yang
dikerjakan hari ini hanya bagian bedah — bagian perombakan bentuk sengaja ditunda supaya
tiap layar punya tes sendiri.

| File | Status | Keterangan |
|---|---|---|
| `app/Http/Controllers/SuratMasukController.php` | **Diubah** | `destroy()`: `catch (QueryException)` yang promises "masih direferensikan data lain" dibuang — soft delete itu UPDATE `deleted_at`, FK restrict tidak pernah tersentuh, jadi pesan itu tidak bisa muncul; kalau pun muncul, itu kesalahan lain yang jadi tersamar. Import `QueryException` yang jadi mati ikut hilang. `SuratKeluarController::destroy()` memang tidak pernah punya catch begitu. |
| `app/Http/Controllers/PengajuanHapusLampiranController.php` | **Diubah** | `setujui()`: `lampiran->delete()` + update status pengajuan sekarang dalam SATU `DB::transaction`, dan `hapusBerkasFisik()` dijalankan SETELAH commit. Alasan: file tidak bisa di-rollback sedangkan baris bisa. |
| `app/Http/Controllers/LampiranController.php` | **Diubah** | `simpanLampiran()`: kalau `Lampiran::create()` melempar, berkas yang barusan ditulis ke disk ikut dihapus sebelum exception diteruskan — tidak ada lagi file yatim. `cobaBacaIsi()`: kegagalan baca sekarang `Log::warning` (upload tetap tidak pernah gagal — L-02). |
| `app/Services/PembacaIsiLampiran.php` | **Diubah** | `dariPdf()`: `catch (Throwable) { return null; }` yang diam total jadi `Log::warning` dengan path + pesan error. |
| `app/Observers/*.php` (7), `app/Traits/LogsAktivitas.php`, `app/Providers/AppServiceProvider.php` | **Diubah (komentar)** | Semua masih menyebut "BARU — 1 Sep 2026, belum diregistrasikan, lihat `CATATAN.md`" padahal tujuh observer itu terdaftar di `AppServiceProvider::boot()` dan file `CATATAN.md` tidak pernah ada di repo. Trait juga menyebut dirinya "belum ada implementasi apa pun". Rujukan nomor bagian lama diarahkan ke `AGENTS_HISTORY.md`. |
| `app/Http/Controllers/SuratKeluarController.php` | **Diubah** | Dropdown klasifikasi di `create()`/`edit()`: `orderBy('nama')` → `orderBy('kode')`, sama seperti 8 pemanggil lain; kode-lah urutan domain yang dipakai form cascade. |
| `app/Models/Lampiran.php` | **Diubah** | Import `Carbon\Carbon` dibuang (tidak dipakai lagi sejak `layakDihapus()` mendelegasikan ke induk). |
| `tests/Feature/PengajuanHapusLampiranSetujuiTest.php` | **Baru** | 5 tes jalur approval yang sebelumnya TIDAK punya tes sama sekali: berkas + baris hilang & pengajuan `disetujui` (dengan `lampiran_id` jadi NULL + snapshot tetap terbaca), approval kedua → 422, staf → 403, lampiran sudah hilang → 410 dan status tidak berubah, dan **kasus kegagalan DB: file harus masih ada** (dibuat dengan mendaftarkan observer penyamar yang melempar saat `updating`). Tes terakhir ini gagal pada urutan lama — itu bukti perubahannya nyata. |
| `phpstan-baseline.neon` | **Diubah** | 6 → **5** entri. |

Dibaca ulang dan ternyata TIDAK perlu diubah: `SinkronkanLampiranKeDriveCommand` tidak
memiliki `DB::transaction`, jadi "panggilan HTTP Drive di dalam transaksi" yang tercatat di
rencana tidak pernah ada. Empat `catch (QueryException)` di controller klasifikasi
dipertahankan (FK `restrictOnDelete` sungguhan melemparnya di MariaDB; yang di-baseline
hanya karena PHPStan tidak bisa melihat PDO).

Verifikasi: `php artisan test` **203 passed / 0 skipped**, `Pint` 172 file lolos, `Larastan`
level 5 bersih dengan 5 entri baseline yang masing-masing sudah dibaca. Dev server tidak
dijalankan (tidak ada izin eksplisit pada sesi ini); bukti perilaku lewat HTTP di feature test.

### Perapian kode & alur data — Fase 4 (9 Okt 2026, sampah & metadata)

Butir 16–18 rencana (`docs/plan-perapian-kode.md`). Tidak ada perubahan perilaku yang
direncanakan; yang dihapus adalah sisa generator Laravel yang tidak pernah dibaca PRADANA.

**Dihapus** (setelah grep, dan `@vite` memang nol di semua view): `package.json`,
`vite.config.js`, `resources/js/app.js`, `resources/js/bootstrap.js`, `resources/css/app.css`
(kedua direktorinya jadi kosong dan ikut dihapus), `routes/channels.php`,
`app/Providers/BroadcastServiceProvider.php`, `config/broadcasting.php`, satu baris komentar
`// App\Providers\BroadcastServiceProvider::class` di `config/app.php`, lima baris Node/npm/yarn
di `.gitignore`.
**Diperbaiki isinya**: `EventServiceProvider` — pasangan `Registered => SendEmailVerificationNotification`
dibuang (tidak ada registrasi publik; akun dibuat lewat `arsip:akun-pertama`, dan kantor tanpa
SMTP), dokumentasinya sekarang menjelaskan kenapa; `config/services.php` jadi `return []` +
komentar (Mailgun/Postmark/SES tidak dipakai, Drive hidup di `config/gdrive.php`).
**Diverifikasi tidak tersenggol**: `config/cors.php` sudah `'paths' => []` sejak lama sehingga
`HandleCors` di grup `web` tidak pernah cocok — dibiarkan (menghapusnya dari Kernel bukan
perbaikan apa pun); `storage/app/.gitignore` (baris `*`) tetap melindungi
`storage/app/google/service-account.json` — dicek `git check-ignore -v` sebelum commit.

**Metadata composer**: `name` → `pradana/arsip-surat`, deskripsi + keyword Indonesia,
`php: ^8.1` → `^8.2` (stack terkunci), `laravel/pint: "*"` → `^1.30` (terkunci v1.30.4), skrip
`post-update-cmd` (`vendor:publish --tag=laravel-assets`) dihapus karena tidak ada aset yang
bisa dipublikasikan. `license: MIT` **dibiarkan** — itu keputusan user, bukan agent.

**Resep lingkungan yang layak dicatat**: perubahan `require` membuat `composer.lock` *stale*,
dan `composer dump-autoload -o` di laptop ini pernah ±6,5 menit (sudah tercatat sebagai jebakan).
Yang dipakai: `composer update --lock --no-scripts --no-autoloader --no-plugins` — 9 detik, diff
lock hanya 2 baris (`content-hash`, `platform.php`), nol perubahan versi paket, autoloader tidak
disentuh. Diverifikasi: `composer validate` → "valid" tanpa peringatan lock,
`composer install --dry-run` → "Nothing to install, update or remove", `config:cache` +
`config:clear` jalan (bukti tidak ada yang membaca `config('broadcasting')` saat boot maupun
saat caching).

**Sengaja ditunda ke pass tersendiri** (menyentuh graf dependency = resolusi + jejaring): buang
`laravel/sail` (v1.68.0 terkunci, dev-nya XAMPP) dan `nunomaduro/larastan` → `larastan/larastan`;
composer sendiri sudah memperingatkan paket itu *abandoned*. `.env` lokal (gitignored) masih
menyimpan `BROADCAST_DRIVER=log` + enam baris `VITE_*` — tidak disentuh, tidak lagi dibaca kode
mana pun.

Verifikasi: **203 passed / 0 skipped**, `Pint` **169** file (172 − persis tiga file PHP yang
dihapus), `Larastan` level 5 bersih, baseline tetap 5. Dev server tetap tidak dijalankan (tidak
ada izin); buktinya suite + `config:cache` sungguhan.

### Perapian kode & alur data — Fase 5 (9 Okt 2026, skema: satu index, bukan lima)

Butir 19 rencana. Langkah pertamanya **membaca `SHOW INDEX` semua tabel**, bukan menulis
migration — dan ternyata empat dari lima kandidat sudah terpasang sejak squash S11: index morph
`(arsipable_type, arsipable_id)` / `(lampiranable_type, lampiranable_id)` dibuat oleh `morphs`,
`draf_konten_surat_keluar.surat_keluar_id` bahkan UNIQUE, `status` di `pemusnahan_arsip` dan
`pengajuan_hapus_lampiran` sudah di-index, dan InnoDB wajib membuat index untuk tiap FK (jadi
`klasifikasi_*_id` + `user_id` di kedua tabel surat sudah tertutup). Yang benar-benar hilang cuma
`aktivitas.created_at`.

Diukur di DB tanding `pradana_test` (40.000 baris log + 8.000 surat masuk + 8.000 surat keluar),
`ANALYZE TABLE` sebelum setiap perubahan, waktu terbaik dari beberapa pengulangan:

| Query nyata | Sebelum | Sesudah `(created_at, id)` |
|---|---|---|
| `/aktivitas` halaman 1 | 22,53 ms `type=ALL` + filesort 40k | **1,02 ms** `type=index`, rows=30 |
| `/aktivitas` filter periode | 28,45 ms ALL + filesort | **1,42 ms** `range` |
| `arsip:bersihkan-log` ambil batch | 23,76 ms scan PRIMARY | **1,00 ms** `range` + `Using index` |
| `/aktivitas` halaman 500 (`OFFSET 14970`) | 75 ms | 72,9 ms — **tidak** membaik (sifat `LIMIT…OFFSET`, dicatat) |
| daftar surat masuk polos | 2,16 ms (sudah `tanggal_diterima` terbalik) | komposit `(deleted_at, tanggal_diterima)` 1,93 ms → **ditolak, di dalam noise** |
| kandidat pemusnahan | 9,04 ms | komposit `(status_arsip, tanggal_surat)` 6,13 ms → **ditolak** (satu layar admin vs index ke-4 di tabel tersibuk) |

Tiga hal yang hanya bisa diketahui dengan menjalankan, dan sekarang tercatat di docblock
migration/tes:

1. **Index satu kolom `created_at` tidak terpakai.** Run pertama memakai `(created_at)` dan
   halaman log tetap `type=ALL + filesort` (23,9 ms). Urutan layar `created_at DESC, id DESC`
   butuh `id` ditulis **eksplisit** — MariaDB 10.4 tidak memanfaatkan suffix PK implisit pada
   secondary index untuk ORDER BY dua kolom.
2. **Titik balik optimizer diukur, bukan diasumsikan.** 1.000 baris → filesort 2,34 ms (dan itu
   keputusan yang *benar*); 5.000 → 4,16 ms filesort; 8.000 → index, 0,97 ms; 30.000 → 1,31 ms.
   Karena itu `UrutAktivitasMariaDbTest` menanam 12.000 baris: di bawah ±8.000 index ini belum
   seharusnya dipakai, dan tes yang menanam 500 akan gagal tanpa kesalahan kode.
3. **Protokol ukur: `ANALYZE TABLE` wajib di kedua sisi.** Run pertama (tanpa ANALYZE) memberi
   angka yang menipu: daftar surat "percepatan 6,7 → 1,5 ms" tampak seperti hasil index komposit,
   padahal setelah statistik disamakan di run kedua baseline-nya **sudah** 2,16 ms tanpa index baru
   — yang berubah hanyalah statistik yang dihitung ulang saat `CREATE INDEX`. Kalau run pertama
   langsung dipercaya, Fase 5 akan menambah index yang tidak memberi apa-apa. Kesimpulan soal
   index hanya sah kalau statnya disamakan lebih dulu.

Isi perubahan: migration additive `2026_10_09_000001_tambah_index_urut_aktivitas`
(nama index eksplisit `aktivitas_created_at_id_index`; `down()` drop dengan nama yang sama),
`tests/Feature/SchemaIndexAktivitasTest.php` (2 tes, engine-agnostic — `PRAGMA index_list`/
`index_info` di SQLite vs `SHOW INDEX` di MariaDB; assertion index bantu FK `aktivitas_user_id_foreign`
digating per engine karena SQLite membuat constraint tanpa index terpisah — ini kegagalan pertama
yang ditangkap suite sebelum commit), `tests/Feature/UrutAktivitasMariaDbTest.php` (1 tes lewat
`MariaDbHarness`, PENANDA `MIX`, bersih-bersih baris log sendiri di `tearDown`). `migrate`
dijalankan sungguhan di `pradana` development: 30 ms, index muncul di `SHOW INDEX`, 3 baris log
asli tidak tersentuh. DB tanding disapu dari index eksperimen + baris tanaman (`UIDX`/`MIX`/`PROBE`),
skrip pengukuran (4 file `tmp-*.php`) dihapus dan tidak ikut ter-commit.

Verifikasi: **206 passed / 0 skipped** (819 assertion), `Pint` 172 file lolos, `Larastan` bersih,
baseline tetap 5.

### Perapian kode & alur data — Fase 6 (9 Okt 2026, item "kosmetik" yang berisi bug)

Rencana menandai `UpdateStatusArsipRequest` sebagai "perubahan bentuk tanpa perubahan perilaku".
Salah. Begitu method-nya disentuh, ketemu bug yang kelihatan untuk petugas:

- `SuratMasukController::updateStatusArsip()` **dan** `SuratKeluarController::updateStatusArsip()`
  menulis konfirmasi lewat `back()->with('status', …)` — kunci yang TIDAK dirender
  `resources/views/layouts/app.blade.php` (layout hanya `session('success')` / `session('error')`).
  Efeknya: menekan "Nyahkan" mengubah `status_arsip` di database, tapi layar tidak memberi
  respons apa pun, jadi tombol terasa mati dan petugas menekan ulang.
- `FlashKonsistenTest` (Fase 1) dibangun persis untuk mencegah kelas bug ini dan tetap hijau.
  Sebabnya: polanya `/->with\('([a-z_]+)'\s*,/` menuntut kutip pada **baris yang sama** dengan
  `->with(`, sedangkan kedua method ini menulis argumennya di baris berikut (sudah diformat Pint).

Perubahan:
1. `app/Http/Requests/UpdateStatusArsipRequest.php` baru — `required` + `Rule::in(['aktif','inaktif'])`,
   pesan berbahasa Indonesia, helper `dinonaktifkan()`. `authorize()` true karena L-08 (arsip kantor,
   semua yang login boleh mengubah; observer yang mencatat). "musnah" sengaja bukan nilai sah:
   pemusnahan punya jalurnya sendiri (L-06).
2. Kedua controller pakai request itu, flash pindah ke `success`, impor `Rule` dibuang (tidak ada
   lagi pemakaian `Rule::` di keduanya).
3. `FlashKonsistenTest` diperkuat: pola mengizinkan newline (`/->with\(\s*'([a-z_]+)'\s*,/s`);
   sumber dibaca lewat `token_get_all()` dengan `T_COMMENT`/`T_DOC_COMMENT`/`T_INLINE_HTML` dibuang —
   tanpa itu, docblock request baru yang MENULIS `->with('status', ...)` sebagai penjelasan bug
   dianggap flash sungguhan (ini benar-benar terjadi pada percobaan pertama, lalu tesnya gagal);
   daftar kunci yang dirender layout juga dibaca setelah komentar Blade/HTML (`{{-- --}}`, `{# #}`,
   `<!-- -->`) disingkirkan, supaya komentar tidak bisa melegalkan kunci liar.
4. Tes perilaku baru `test_petugas_lihat_konfirmasi_setelah_menyahkan_surat`: PATCH status-arsip →
   `status_arsip` berubah **dan** `session('success')` tidak null **dan** pesannya muncul di HTML
   halaman show. Guardrail-nya sendiri diverifikasi terhadap file versi `7641d5f`:
   pola lama → `success, status`; pola baru → `success`.
5. Docblock `UserController` ditulis ulang: masih mengklaim "edit/delete/ganti-PIN SENGAJA belum
   dibuat" dan merujuk "Bagian 10" AGENTS versi lama, padahal L-07/L-12 selesai 4 Okt 2026. Yang
   benar-benar tersisa dan sekarang dicatat jujur: tidak ada route `edit`/`update` untuk user, jadi
   mengubah peran = hapus + buat ulang (diperbolehkan L-10).

Tetap ditunda, sekarang dengan alasan yang sudah diverifikasi: `{!! sorot() !!}` → `{{ }}` **tidak
mungkin** (keluarannya memang markup `<mark>`; keamanan ditegakkan di dalam `CariArsip::sorot()`
yang meng-escape sebelum menyisipkan tag, dan tes XSS-nya ada), deduplikasi `SuratMasuk`↔`SuratKeluar`
+ tiga controller klasifikasi (butuh `@template`/dynamic class-string yang akan menambah baseline
Larastan; nilai praktisnya rendah karena perilaku keduanya sudah dikunci tes per layar), tanggal
hard-coded di JS pratinjau kop (kosmetik, layar sudah diverifikasi mata 5 Okt).

Verifikasi: **207 passed / 0 skipped** (824 assertion), `Pint` 173 file, `Larastan` bersih,
baseline tetap 5.

### Perapian kode & alur data — Fase 7 (9 Okt 2026, dependency + deduplikasi + layar)

Tiga hal yang sebelumnya dipisah sekarang dikerjakan setelah user menyetujui eksplisit
("kerjakan sekarang" untuk composer; "dedup + jaga baseline tetap 5" untuk controller;
"Ya, jalankan & verifikasi" untuk dev server).

**Dependency.** `laravel/sail` keluar; `nunomaduro/larastan` → `larastan/larastan` v3.13.0
(dari itu ikut `phpstan/phpstan` 2.2.16 → 2.3.1 dan `iamcal/sql-parser`; `vendor/nunomaduro/`
kini hanya berisi collision + termwind). Urutan perintah supaya autoloader tidak dibongkar dua
kali: `composer remove --dev ... --no-scripts --no-install` (5,5 s) → `composer require --dev
"larastan/larastan:^3.12" --no-scripts --no-install` (6,5 s) → satu `composer install`.
Yang terakhir ini makan **51 menit 10 detik** — jauh di atas catatan lama "dump-autoload ±6,5
menit", karena install harus menulis ulang paket + classmap di disk Windows, bukan cuma dump.
`phpstan.neon` ikut diubah (include `vendor/larastan/larastan/extension.neon`) + komentarnya
diperbarui (dulu menyebut "29 temuan", padahal baseline sudah menyusut ke 5).
Gejala kalau include tidak diganti: `Invalid configuration: Service 'sqlParser': Class or
interface 'Larastan\Larastan\SQL\SqlParser' not found` — dan menghapus cache phpstan di temp
TIDAK menolong, karena yang salah adalah path include, bukan hasil analisis yang basi.
`composer validate` tetap "valid"; `laravel/pint` tidak tersentuh perubahannya.

**Deduplikasi.** Trait baru `Concerns/MengelolaArsipSurat` (daftar + paginasi + susun data view,
pohon klasifikasi, "Nyahkan", soft delete, restore) dipakai `SuratMasukController` (247 → 213
baris) dan `SuratKeluarController` (221 → 196); trait baru `Concerns/MengelolaKlasifikasi`
(daftar, redirect+flash, hapus dengan catch FK) dipakai tiga controller klasifikasi.
Yang SENGAJA tinggal di controller: kelas model, relasi eager load per tingkat, nama view,
kolom urut (`tanggal_diterima` vs `tanggal_surat`), dan kalimat flash yang menyebut nomor surat.

Dua penemuan tipe yang tidak bisa ditebak dari kode:
1. Helper dengan param `Model` polos langsung menghasilkan dua error nyata
   `Call to an undefined method Illuminate\Database\Eloquent\Model::restore()` — `restore()`
   datang dari trait `SoftDeletes`. Diperbaiki dengan union `SuratMasuk|SuratKeluar` pada param
   model (dan itu memang pernyataan yang benar: helper ini khusus dua model arsip), bukan lewat
   baseline. `daftarArsip()` memakai `@template TModel of Model`; scope `palingRelevan` dan
   `onlyTrashed` sengaja ditinggal di controller karena pada `Builder<Model>` Larastan tidak
   mengenalinya — itu persis sebab pola `@template` yang sama sudah dipakai sejak Fase 1.
2. Menyatukan kode TIDAK menyatukan laporan PHPStan: error dalam trait dilaporkan *"in context of
   class"* pemakainya, jadi tiga entri `dead catch` controller klasifikasi tetap tiga. Karena itu
   `--generate-baseline` dijalankan dan hasilnya dibandingkan: **byte-identik** dengan baseline
   sebelumnya (`diff -q` lolos) — jadi "baseline 5" kali ini berarti, bukan kebetulan.
   (Bug kecil yang dibuat dan ditangkap sendiri pada tahap ini: trait ditulis dengan
   `use App\Models\Model;` yang salah — yang benar `Illuminate\Database\Eloquent\Model` —
   dan PHPStan langsung memberi 26 error tipe `argument.type`. Fiksanya satu baris impor.)

**Tes.** `grep route('klasifikasi-` di `tests/` = **nol** hasil: tiga controller itu akan
direfactor tanpa jaring sama sekali. Ditulis `KlasifikasiCrudTest` (5 tes): daftar urut `kode`
dibaca dari urutan badge kode di HTML tabel (bukan dari query), `store`/`update`/`destroy`
menulis flash pada kunci yang dirender layout + redirect ke index tingkatnya, `destroy` pada kode
yang masih dipakai surat TIDAK menghapus baris dan memberi pesan ramah — bukti hidup bahwa
`catch (QueryException)` bukan dead code (SQLite menegakkan FK karena `foreign_key_constraints`
= true), staf boleh membaca tapi 403 saat mutasi, dan unique kode berlaku per induk (kode sama di
bawah induk berbeda diterima). Ditambah `SuratTempatSampahTest::test_surat_keluar_mengikuti_jalur_
nyahkan_yang_sama_dan_memberi_konfirmasi` untuk jalur kedua trait (asserts `session('success')`
berisi kalimat "dinonaktifkan sebagai arsip aktif" dan muncul di HTML halaman show keluar).
Tiga kesalahan fixture/asersi milik sendiri ditangkap suite sebelum commit: mengharapkan baris
dari tes lain (`RefreshDatabase` memisahkan tiap tes), loop yang menuntut string dua tingkat pada
satu halaman, dan `url()->previous()` yang mengembalikan 404 di tes.

**Verifikasi layar (dev server diizinkan).** `php artisan serve` dijalankan di 127.0.0.1:8000.
Login lewat Browser Connector TIDAK berhasil tiga kali: field terisi, tidak ada error ter-render,
halaman tetap `/login` — sedangkan POST kredensial yang sama lewat `curl` menghasilkan
`302 → /dashboard`, jadi ini kegagalan otomasi browser, bukan aplikasi; aku catat apa adanya dan
melanjutkan verifikasi lewat HTTP nyata ke server yang sama (kode render yang diuji identik).
Yang dibuktikan di HTML sungguhan: flash "Surat masuk berhasil ditambahkan." tampil sesudah
store; `UJI-DEDUP-1` muncul di daftar (jalur `daftarArsip`); halaman show terisi; **PATCH
status-arsip → kalimat "Surat masuk dinonaktifkan sebagai arsip aktif." benar-benar ada di
respons** — ini konfirmasi ujung-ke-ujung perbaikan bug Fase 6 melalui trait baru, yang sebelumnya
mustahil terlihat karena suite hanya mengecek data, bukan jawaban layar; "Aktifkan Kembali" muncul
lagi; soft delete → tempat sampah (`?sampah=1`) → pulihkan, tiga-tiganya memberi flash yang
terbaca; `/klasifikasi-primer`, `-sekunder`, `-tersier`, `/surat-keluar`, `/surat-keluar/create`,
`/aktivitas`, `/laporan`, `/dashboard` semuanya 200 dengan layout utuh.
Artefak dibuang (`UJI-DEDUP-1` di-`forceDelete` + 10 baris `aktivitas` yang merujuknya);
`pradana` kembali seperti saat diterima: 0 surat masuk, 1 surat keluar milik user
(`001/02.01.01/X/2026`), 3 baris log, 3 user, 2 klasifikasi primer. Skrip sekali-pakai dihapus,
dev server dihentikan.

Gerbang akhir: **213 passed / 0 skipped** (871 assertion) · `Pint` 176 file · `Larastan` [OK] No
errors · baseline 5 entri (identik dengan sebelum).

### P0 dari daftar peningkatan — 10 Okt 2026 (aset lokal, laporan hemat, plafon agenda, CI, tanpa-JS)

`docs/daftar-peningkatan.md` (9 Okt) menghasilkan empat paket P0; semuanya dikerjakan hari ini
dalam tujuh commit. Tidak ada migration baru, tidak ada dependency baru.

**1. `9a453e6` docs** — daftar peningkatan itu ikut ter-commit (isinya angka, bukan tebakan).

**2. `7012062` test: skip "XAMPP mati" tidak lagi jadi merah.** Session ini dibuka dengan
`php artisan test` yang melaporkan **13 FAILED** (`no such table: surat_masuk`, connection
`sqlite :memory:`) padahal yang salah hanya server yang belum nyala. Rantainya:
`markTestSkipped()` melempar → `MariaDbHarness::setUp()` berhenti SEBELUM
`config(['database.default' => 'mysql_test_a'])` → PHPUnit tetap memanggil `tearDown()` →
bersih-bersih menghantam SQLite milik kelas lain. Ditutup dengan flag `engineSiap` di harness
dan di dua `tearDown()` anak (`AgregatMariaDbTest`, `UrutAktivitasMariaDbTest`), bukan dengan
try/catch. `NomorSuratKeluarTest` — satu-satunya yang TIDAK memakai harness — justru skip dengan
benar, dan itulah yang membuktikan diagnosisnya. Sekarang: 16 skipped dengan pesan cara menyalakan.

**3. `ffa0fd0` perf: laporan hanya membaca kolom yang dipakai.** `barisMasuk()`/`barisKeluar()`
mengangkat `isi_hasil_baca` LONGTEXT (batas writer 20.000 karakter) dan `ringkasan` TEXT yang
tidak dicetak satu kolom pun. Ukuran 9 Okt @16.005 surat: 1.674 ms + 56 MB → 383 ms + 2 MB.
Perbaikan: konstanta `KOLOM_ARSIP` + `->select([...])` + eager load berkolom terbatas
(`primer:id,kode,nama`, `petugas:id,nama_lengkap` — `user_id`/`klasifikasi_primer_id` wajib ada
di daftar induk supaya relasinya tidak senyap jadi "-"). `chunk()`/streaming CSV **tidak**
dikerjakan: `baris()` harus menggabungkan dan mengurutkan dua tabel sebelum baris pertama boleh
dicetak, jadi seluruh baris tetap dibutuhkan; setelah pemangkasan sisanya ±2 MB.
Guardrail-nya (`LaporanHematKolomTest`) **lolos pada kode lama** di percobaan pertama: saya cek
`assertStringNotContainsString('isi_hasil_baca', $sql)` dan `select * from`, padahal `withCount()`
membuat Eloquent menulis `select "surat_masuk".*, (…) as "lampiran_count"`. Bentuk yang benar
mengigit adalah assert POSITIF (daftar kolom eksplisit ada + wildcard tabel tidak). Diverifikasi
dua arah dengan menghapus/mengembalikan baris `select()`.

**4. `416aed5` fix: aset frontend dilokalkan.** 9 tag CDN (`layouts/app` 4, `auth/login` 3,
`errors/layout` 2) → `asset('vendor/…')?v=<versi>`; Bootstrap 5.3.3 + Font Awesome 6.4.0 (hanya
4 `.woff2`, tanpa `.ttf`) + SweetAlert2 **11.26.25** di-commit di `public/vendor/` (772 KB).
Yang berubah bukan pilihan frontend (X1/G1/G2/M2/V2 tetap) tapi **asal** aset — tidak ada keputusan
di register user yang menyebut CDN, dicek langsung ke `Daftar Keputusan PRADANA.md`. Tiga hal yang
tidak bisa ditebak dari kode: `sweetalert2@11` adalah tag MENGAMBANG (browser staf bisa dapat rilis
terbaru tanpa diff di repo ini), CSP ikut dipersempit menjadi hanya `'self'`+`'unsafe-inline'`,
dan catatan versi/lisensi sengaja ditaruh di `docs/aset-vendor.md` dan BUKAN `public/vendor/`
(folder publik tidak boleh menyajikan nomor versi pihak ketiga). `AsetLokalTest` (4 tes) diverifikasi
dua arah dengan menyisipkan link CDN sungguhan. Dua jebakan ditangkap suite: `getExtension()` pada
`index.blade.php` memberi `php` sehingga scan pertama menemukan NOL file dan "bersih" terbaca sukses
(→ guard `assertGreaterThan(20, $jumlah)`), dan `blade()` adalah method milik `TestCase` Laravel
(fatal "must be protected or weaker").

**5. `7fee81c` ci: GitHub Actions.** Satu job PHP 8.2: `composer install` → `cp .env.example .env`
→ `key:generate` → `php artisan test` → `pint --test` → `phpstan analyse`. Batasannya ditulis di
dalam file itu sendiri: 13 tes engine-asli MariaDB di-SKIP di CI (tidak ada service MySQL dipasang),
jadi suite lokal dengan XAMPP nyala tetap wajib sebelum serah terima. Workflow belum pernah dieksekusi
— repo ini punya remote `github.com/a-ali-wafa/Pradana` tapi tidak ada push pada session ini.

**6. `633e9c0` fix: konfirmasi tidak lagi hidup di JavaScript.** `pradanaConfirmHapus()` disalin
TUJUH kali dan tiap salinan merakit form dari JS untuk tombol `type="button"`; tanpa JS atau tanpa
SweetAlert2 (tidak ada satu pun salinan yang memeriksa `typeof Swal`) tombolnya diam total. Jadi:
helper satu tempat `public/js/pradana-arsip.js` + form POST sungguhan di Blade dengan
`data-konfirmasi`/`-judul`/`-catatan`/`-ya`; `window.Swal` tidak ada → `window.confirm()`; submit
lewat `requestSubmit()` kalau tersedia supaya validasi HTML5 tidak dilompati. Pesan masuk ke `text`,
BUKAN `html`/`footer`: nilai `data-*` yang di-escape Blade menjadi teks mentah saat dibaca
`getAttribute()`, jadi jalur markup adalah XSS dari `nomor_surat`/`nama_file` (bentuk lama benar-benar
menulis `html: \`File <strong>${namaFile}</strong>…\``). Pola blok dibuka-tutup dibalik
(`data-pradana-tertutup` ditutup OLEH script): form **Tolak** pengajuan, form **Tolak** pemusnahan,
dan form **Reset PIN** di daftar user (jalur pemulihan L-12) sekarang tercapai tanpa JavaScript.
`pemusnahan-arsip/create` tetap punya skrip sendiri tapi sekarang hanya menulis angkanya ke
`data-konfirmasi`. Dua show-surat yang memakai `onsubmit="return confirm(...)"` ikut diseragamkan.
`KonfirmasiDestruktifTest` (6 tes): semua form `@method('DELETE')` membawa pesannya, tidak ada
`function pradanaConfirm*`/`setujuiPemusnahan`/`Swal.fire` di view, helper punya fallback + tanpa
`innerHTML`/`html:`, tidak ada `<div id="form-…" style="display:none">`. Komentar JS di `<script>`
TIDAK dibuang oleh scanner — komentar sejarah yang saya tulis sendiri membuat tes gagal, dan itu
dibiarkan berlaku karena membedakan komentar JS dari kode butuh parser.

**7. `fe171e8` perf: plafon Buku Agenda.** 1.000 baris agenda = 8,36 s + 52 MB dalam satu request
(L-02 tanpa queue); `max_execution_time`/`memory_limit` hosting kantor tidak bisa dinaikkan dari
kode. `agenda()` menghitung `jumlah()` dan menolak sebelum satu baris pun dihidrasi kalau melewati
`config('laporan.agenda_batas_baris')` (bawaan 2.000; `AGENDA_BATAS_BARIS` di `.env.example`), dan
`/laporan` memperingatkan lebih dulu (merah: akan ditolak, kuning: di atas setengah plafon). Plafon
di **config baru** `config/laporan.php` dan dibaca lewat `config()` — `env()` di controller akan
null setelah `config:cache` dan `(int) null = 0` menolak SEMUA agenda di server sementara di laptop
kelihatan normal. CSV sengaja tanpa plafon (keluaran murah, justru untuk rekap tahunan) dan ada tes
yang mengunci perbedaan itu. `AgendaBatasBarisTest` (5 tes) diverifikasi mengigit dengan mematikan
kondisi guard (`if (false && …)`). Percobaan pertama justru mengosongkan file controller (preg_replace
saya mengembalikan null dan hasilnya ditulis apa adanya) — backup `/tmp` menyelamatkan, dan itu
mengingatkan untuk selalu memverifikasi ukuran berkas setelah skrip penekan.

**Guardrail tambahan: `tests/Unit/TeksSumberBersihTest.php`.** Dalam dua session, karakter CJK/Han
empat kali menyisip di tengah kalimat Indonesia pada komentar dan dokumen; Pint dan Larastan tidak
peduli karena mereka melihat byte. Tesnya memindai direktori kode + dokumen kantor dan langsung
terbukti bekerja dua kali: docblock-nya sendiri sempat memakai huruf Han sebagai contoh (yang
seharusnya tidak ditulis dengan huruf asli), dan ia menuntut pesan gagal yang terbaca (path Windows
bercampur separator → dinormalisasi). Kelasnya `PHPUnit\Framework\TestCase` polos, dan itu menemukan
fakta bahwa `base_path()` melempar `Container::basePath()` tanpa aplikasi.

**Dokumentasi.** Manual kantor ikut diubah: A.1 menambah "tidak mengambil apa pun dari internet" +
apa yang terjadi kalau JavaScript dimatikan (ditegaskan: dengan JS mati **tidak ada kotak konfirmasi
sama sekali**, aksi langsung jalan), §12 dan §14 mencatat cetak agenda per semester. `README.md` tidak
lagi menyebut "Bootstrap 5 via CDN". AGENTS.md Bagian 2 (baris Frontend) dan Bagian 3 (bullet
"Susulan 10 Okt") diperbarui.

**Gerbang akhir session:** 217 passed + **16 skipped** (XAMPP mati; total 233 tes / 36 kelas) ·
`Pint` 182 file · `Larastan` [OK] No errors · baseline tetap 5 entri. Tidak ada perubahan skema.
Verifikasi layar lewat dev server TIDAK dilakukan pada session ini (butuh izin user; yang belum
terbukti di browser sungguhan: kotak Swal muncul, confirm() bawaan muncul saat Swal dihapus, dan
form Tolak/Reset PIN terlihat tanpa JavaScript).

### 10 Okt sore — push pertama, CI hijau, MariaDB dev korup lalu dipulihkan, dan bug klik-dobel di helper JS

User menyalakan XAMPP dan mengizinkan push ("aku mendukung semua tindakan yang membantu project
ini"). Urutan kejadian sebenarnya, termasuk yang gagal:

1. **Push pertama** `ce5f134..ac92029` → workflow `CI` run #38035333559 **success dalam 42 detik**,
   8 langkah semuanya OK. Tidak perlu `gh` (tidak terpasang): status dibaca lewat API publik repo
   `actions/runs`. Ini juga bukti bahwa `cp .env.example .env` + `key:generate --force` cukup untuk
   boot Laravel di mesin bersih.
2. **`mysqladmin ping` yang menjawab "Access denied" membuatku menyimpulkan server hidup** — dan
   memang hidup, tapi mati sedetik kemudian. Run suite pertama melaporkan 13 FAILED (`2002 ...
   actively refused`). Kesimpulan yang benar hanya bisa datang dari `tasklist` + `netstat` +
   `Test-NetConnection`, bukan dari satu ping.
3. **Akar kerusakan:** `mysqld --console` menghasilkan `[FATAL] InnoDB: Trying to read page number
   32769 in space 0 (innodb_system), which is outside the tablespace bounds` — redo log menuntut
   `ibdata1` ±512 MB, filenya 79,7 MB; `ib_logfile0` (mtime 14:40) dan `ib_logfile1` (mtime 00:49)
   jelas dari generasi berbeda. Setelah redo log diganti, server sempat "ready for connections"
   lalu crash pada `btr0cur.cc:342` (`btr_page_get_prev(...) == page_get_page_no(page)`) — index
   rusak, dan `information_schema.INNODB_SYS_TABLESPACES` menunjukkan `space=912` = **`pradana_test`**
   (gudang pengukuranku 9 Okt), bukan `pradana` milik kantor. Ada juga folder `mysql_corrupted`
   di datadir: kerusakan seperti ini pernah terjadi sebelumnya di laptop ini dan tidak dicatat.
4. **Pemulihan (izin user, dengan salinan utuh 172 MB di `C:\Users\asus\prd-mysql-data-backup-20261010`
   sebelum apa pun disentuh):** `--innodb-force-recovery=2` membuat server hidup → `mysqldump`
   semua database (`pradana` 26,6 KB selamat termasuk surat asli `001/02.01.01/X/2026`; `pradana_test`
   mati di `aktivitas`) → `mysqladmin shutdown` → pindahkan folder `pradana_test` → start normal =
   **masih gagal** (`1813 Tablespace for table pradana_test.migrations exists. Please DISCARD the
   tablespace before IMPORT`) → `DROP DATABASE IF EXISTS pradana_test` melepas entri dictionary →
   server normal. Setelah itu **16 tes engine-asli lolos** dan suite penuh **233 passed / 0 skipped**
   (1029 assertion, 33 detik).
5. **Pelajaran yang dibawa ke desain project:** database scratch untuk pengukuran tidak boleh tinggal
   di datadir yang sama dengan database kantor, dan `arsip:backup-db` + uji pulih (X5) berubah dari
   formalitas jadi kebutuhan yang barusan terbukti. Kredensial tidak pernah lewat baris perintah —
   semuanya lewat `--defaults-extra-file` yang dibuat dari config lalu dihapus.
6. **Bug nyata di helper JS yang hanya kelihatan lewat alat uji Node.** `bin/uji-pradana-arsip.js`
   (stub DOM minimal, 18 pemeriksaan, tanpa dependency; Node di sini alat uji, bukan langkah build —
   `package.json` tetap tidak ada) menemukan bahwa **guard anti-dua-klik tidak bekerja**: flag
   `pradanaDikonfirmasi` baru ditulis SETELAH dialog dijawab, jadi dua klik cepat = dua dialog =
   **dua kali submit** untuk aksi DELETE. Diperbaiki jadi state machine `dataset.pradanaStatus`
   (`ditable` → abaikan klik tambahan; `dikirim` → biarkan lolos tanpa dialog; kosong lagi saat
   dibatalkan atau saat validasi HTML5 menahan pengiriman, dengan reset `setTimeout(…, 0)`).
   Guardrail-nya diverifikasi dua arah: guard dibuka lewat `sed` → skrip gagal dengan exit code 1,
   dan sekarang CI menjalankan `node bin/uji-pradana-arsip.js` di setiap push.

### 10 Okt malam — P1: `arsip:backup-db` + uji pulih (X5) yang benar-benar dijalankan

Bekerja langsung setelah insiden di atas, atas izin user "langsung benerin aja selama kamu yakin
bakal bener tidak perlu ngambil jalan memutar". Yang dibuat: `config/backup.php`,
`app/Console/Commands/BackupDatabaseCommand.php` (`arsip:backup-db [--connection=] [--retain=]
[--dry-run] [--no-rotate]`), jadwal `dailyAt('02:10')->withoutOverlapping()`, dan tiga kelas tes
baru (10 + 1 + 1). Dump pertama yang nyata di mesin ini: `prd-2026-10-10-103939.sql`, 26,6 KB, dari
database dev kantor `pradana`, ditulis ke `storage/app/private/db-backup` (di luar `public/`).

Empat keputusan desain yang tidak bisa ditebak dari kode:

1. **Kredensial tidak pernah masuk baris perintah.** Di Windows maupun Linux argumen proses yang
   sedang berjalan bisa dibaca siapa pun (`tasklist /v`, `ps aux`, detail Task Manager). Password
   ditulis ke file `[client]` sementara — `--defaults-extra-file`, `0600`, dihapus di `finally` —
   dan hanya PATH file itu yang jadi argumen. Ada tes yang membaca ARRAY ARGUMEN sungguhan
   (`perintahDump()` dipisah jadi method public justru untuk ini) dan menuntut
   `--defaults-extra-file` ada di indeks 1, tidak ada `-p`/`--password`, dan (kalau koneksi punya
   password) string password itu tidak muncul di baris gabungan.
2. **`--defaults-extra-file` harus opsi PERTAMA** — di posisi lain mysqldump memperlakukannya
   sebagai argumen biasa, dan kegagalan yang muncul adalah "Access denied" yang terlihat seperti
   salah password, bukan salah urutan.
3. **`--single-transaction --quick`** supaya InnoDB tidak dikunci selama dump (kantor boleh tetap
   input surat) dan baris di-stream. `--lock-tables`/`--master-data` tidak dipakai: tidak ada
   replikasi.
4. **`--routines`/`--events` dibuat bisa dimatikan** (`BACKUP_PROSEDUR`/`BACKUP_EVENT`), bukan
   hard-coded. Alasannya spesifik ke target K1=a: di MariaDB 10.4 `--routines` butuh hak baca ke
   tabel sistem dan `--events` butuh privilege EVENT, dan pengguna database tunggal di shared
   hosting sering tidak punya keduanya — mysqldump-nya keluar dengan error, bukan dengan cadangan.
   `grep` atas `database/` + `app/` (10 Okt) membuktikan skema PRADANA punya NOL prosedur dan NOL
   event, jadi flag itu hari ini hanya menambah cara gagal. `--triggers` (default mysqldump, tidak
   butuh privilege tambahan) tetap diminta eksplisit.

**Verifikasi hasil, bukan percaya exit code 0.** `periksaDump()` membaca berkas baris demi baris
(dibagi per 64 KB, `CREATE TABLE` dihitung, bukan sekadar dicari) dan menuntut: ukuran ≥ 2 KB,
baris `CREATE DATABASE` untuk database yang BENAR, jumlah `CREATE TABLE` tidak kurang dari jumlah
tabel sungguhan di information_schema, dan footer `-- Dump completed` di 2 KB terakhir. Salah satu
gagal → perintah keluar `FAILURE` dan berkasnya dibuang. Alasannya dicatat di kode: "cadangan" yang
disangka sah lebih berbahaya daripada tidak ada cadangan, karena orang berhenti memeriksa.

**Dua bug nyata, keduanya hanya kelihatan dengan menjalankan perintahnya terhadap MariaDB
sungguhan — keduanya lolos dari sepuluh tes sintetis yang hijau:**

- Pemeriksaan lama mencocokkan teks `CREATE DATABASE IF NOT EXISTS` gaya MySQL 8. MariaDB 10.4
  membungkus "IF NOT EXISTS" di dalam komentar versi, jadi SETIAP cadangan sah dari server kantor
  akan dilaporkan gagal — dan (lebih buruk lagi) tes sintetisku sendiri memakai bentuk MySQL
  sehingga semuanya terlihat benar. Sekarang polanya menerima dua bentuk dan menuntut nama database
  yang cocok; keduanya dikunci tes.
- `SchemaBuilder::getTables()` **tanpa argumen** tidak dibatasi ke database koneksi: di laptop ini
  ia mengembalikan 79 tabel (termasuk `mysql_corrupted`, `phpmyadmin`, `mysql`) sementara
  `pradana_test` cuma 15 — dump yang lengkap dituduh "tidak lengkap", dan di hosting yang melayani
  lebih dari satu database ini akan menggagalkan SETIAP backup malam. `jumlahTabel()` sekarang
  selalu menyebut nama database secara eksplisit, dan tes dump nyata mengunci angkanya (≥ 14).

**X5 jadi punya jalur yang dijalankan.** `UjiPulihBackupMariaDbTest` mengulang prosedur DR yang
ditulis di Lampiran A.6: buat dump → ganti nama skema menjadi `pradana_test_pulih` (tidak pernah
menimpa sumber) → import lewat `mysql --defaults-extra-file` → bandingkan DAFTAR tabel dan JUMLAH
BARIS PER TABEL dengan sumbernya → pastikan surat `MJD-PULIH1` terbaca di skema hasil pulih → lalu
kontrol negatif: dump yang sama dipotong di 60%, dan salinan dari potongan itu HARUS punya lebih
sedikit tabel daripada yang utuh. Tanpa kontrol negatif, tes "berhasil" itu bisa saja hanya
membuktikan skema yang sudah terlanjur ada di server. Bersih-bersih (`DROP DATABASE` dua skema
latihan + hapus berkas + hapus cnf) dijaga `$engineSiap` yang sama seperti kelas harness lain, dan
dibuktikan: tidak ada skema `*_pulih`/`*_cacat` yang tertinggal setelah run.

**Teeth guardrail diverifikasi dua arah** (aturan project): sabotase `DELETE FROM
pradana_test_pulih.surat_masuk` disisipkan sebelum perbandingan → tes merah dengan pesan
"Jumlah baris `surat_masuk` tidak sama setelah pemulihan"; sabotase dibuang → hijau. Sabotase
pertama (menghapus baris `users`) malah gagal membuktikan apa pun karena ditolak FK
`surat_masuk_user_id_foreign` — efek sampingnya yang kebetulan justru mengonfirmasi bahwa hasil
pulih membawa constraint, bukan cuma tabel kosong.

Dua jebakan penulisan yang tercatat karena benar-benar terjadi: mengutip bentuk `... IF NOT
EXISTS*/` di dalam docblock PHP **menutup docblock itu sendiri** (PHP melihat sisanya sebagai kode →
fatal sintaks), sehingga bentuk aslinya harus dijelaskan dengan kata-kata; dan sebuah edit dokumen
yang old_string-nya kepanjangan sempat menghapus kalimat pembuka bullet "Perapian kode" di
AGENTS.md — terbaca dari `sed -n` sesudahnya, langsung diperbaiki.

Gerbang setelah paket ini: **245 tes / 39 class lolos, 0 skipped (1.111 assertion, 79 s)**,
`pint --test` 187 file (satu gaya diperbaiki di command baru), Larastan level 5 bersih dan
`phpstan-baseline.neon` **tidak berubah** (tetap 5), `node bin/uji-pradana-arsip.js` OK. Dokumen yang
diikuti: Lampiran A.3 (cron jadi empat tugas), A.5 (perintah + dua flag escape-hatch hosting), A.6
(ditulis ulang: apa yang dicadangkan, apa yang diperiksa, prosedur uji pulih, tabel log uji, dan
peringatan jujur bahwa folder `db-backup` ada di disk yang sama dengan database-nya), A.7 (jumlah
tes), `docs/daftar-peningkatan.md` (butir #3.4 ditandai SELESAI + dua bug yang ditemukan), header +
Bagian 3 + Bagian 6 AGENTS.md, dan catatan "yang tidak dibuktikan CI" diperbaiki dari 16 → 18.

### 10 Okt malam (susulan) — zona waktu aplikasi: `UTC` bawaan Laravel salah untuk dokumen legal

Ketemu bukan karena dicari, tapi karena nama berkas dump `arsip:backup-db` memakai `date()` dan
keluar sebagai `prd-2026-10-10-103939.sql` pada jam dinding 17:39. `config('app.php')` ternyata masih
`'timezone' => 'UTC'` bawaan framework, dan aplikasi ini menghitung SEMUA tanggal yang dibaca petugas
dari `now()`. Yang salah di lapangan:

- `PemusnahanArsipController` nomor Berita Acara = `BA-%03d/<romawi bulan now()>/<tahun now()>`;
  persetujuan yang ditekan 01 Januari 02:30 WIB (= 31 Desember 19:30 UTC) bernomor **XII/2025**.
- `tanggal_pelaksanaan` default = `now()->toDateString()` → Berita Acara menyatakan **31 Desember
  2025** untuk peristiwa yang terjadi 1 Januari. Ini dokumen resmi; tanggalnya tidak boleh meleset.
- `LaporanController` periode default `now()->startOfMonth()`/`endOfMonth()` → laporan yang dibuka
  pagi-pagi sekali bulan Januari isinya Desember.
- Dashboard "bulan ini" dan `FilterArsip` `now()->subYears(5)` ikut bergeser satu arah yang sama.

Diverifikasi dulu, bukan diasumsikan aman: `grep` atas `app/` + `database/` menemukan **nol**
`DB::raw('NOW()')` dan **nol** `->useCurrent()`, jadi tidak ada timestamp yang dihasilkan database —
semua ditulis PHP dan disimpan naive (tidak ada konversi saat baca). Artinya mengganti zona membuat
seluruh sistem konsisten, dan satu-satunya jejak lama adalah baris yang terlanjur ditulis saat zona
masih UTC (terbaca 7 jam lebih awal) — di laptop pengembangan, dan aplikasi belum pernah dipakai di
server kantor, jadi ini murah sekarang dan mahal setelah serah terima.

Keputusan: `config/app.php` sekarang `env('APP_TIMEZONE', 'Asia/Jakarta')` (WIB; kantor WITA/WIT cukup
ganti `.env`, dan `docs/manual-pemakaian.md` A.2 menulis langkah cek `php artisan tinker --execute
="echo now();"`). Kenapa tidak hard-code: satu baris register keputusan tidak menyebut zona, dan
menaruhnya di `.env` membuat pertanyaan "kantor ini di zona mana" jadi pertanyaan konfigurasi, bukan
perubahan kode.

Tes (dua arah, pola yang sama dipakai guardrail lain di project ini supaya tidak lulus kebetulan):
`Carbon::parse('2025-12-31 19:30:00', 'UTC')` membekukan satu **instan absolut**, lalu
- `PemusnahanArsipTest::test_berita_acara_memakai_jam_dinding_kantor_bukan_utc` menuntut
  `BA-###/I/2026` + `2026-01-01` di zona kantor, lalu mengulang adegan yang sama dengan
  `date_default_timezone_set('UTC')` dan menuntut `XII/2025` + `2025-12-31`. Kontrol kedua yang
  membuat yang pertama berarti.
- `BatasTanggalLaporanTest::test_periode_default_laporan_ikut_jam_dinding_kantor` membaca
  `viewData('periode')` dan menuntut `[2026-01-01, 2026-01-31]`, lalu `[2025-12-01, 2025-12-31]` di UTC.

Tiga hal teknis yang muncul saat membuatnya: (1) mengganti `config(['app.timezone' => ...])` saja
TIDAK mengubah `now()` — yang dibaca Carbon adalah `date_default_timezone_get()`, dan Laravel hanya
menyetelnya saat bootstrap, jadi runtime harus ikut disetel manual; karena itu tes juga menuntut
`config('app.timezone') === date_default_timezone_get()` supaya keduanya tidak bisa lari sendiri;
(2) `Carbon::setTestNow('2026-01-01 02:30')` (string lokal) justru TIDAK memperlihatkan perbedaan
zona — yang dibutuhkan adalah instan absolut, jadi string-nya di-parse dengan eksplisit `'UTC'`;
(3) `Query::sole()` mengabaikan `take(1)` milikku (dia memang mengambil 2 baris untuk mendeteksi
ambiguitas), sehingga penelusuran pengajuan kedua dipakai lewat `whereHas(items.nomor_surat_snapshot)`
— lebih tegas daripada "yang terbaru".

Gerbang: **247 tes / 39 class (1.121 assertion) lolos, 0 skipped**, `pint --test` 187 file, Larastan
bersih tanpa baseline berubah. Dua sisipan karakter Han (aksara Cina, bentuknya satu-dua huruf di
tengah kalimat Indonesia) sempat masuk lagi ke AGENTS.md dan manual pada malam yang panjang ini, dan
ditangkap `TeksSumberBersihTest` — guardrail teks itu bekerja persis seperti yang didokumentasikan,
termasuk menangkap dokumentasi milik agent sendiri. Mereka sengaja TIDAK dikutip di sini: menuliskan
hurufnya sebagai contoh membuat berkas ini melanggar aturan yang dicatatkannya, dan tesnya memang
membaca berkas ini.

### 10 Okt malam (susulan kedua) — dokumen ternyata lebih basi daripada kode: `lokasi_fisik`

`docs/daftar-peningkatan.md` butir #4 (ditulis 9 Okt) masih mengklaim "`lokasi_fisik` tidak bisa
dicari". Dicek ke kode dulu, sebelum dikerjakan: kolom itu **sudah** ikut digali sejak refactor
`App\Support\CariArsip` hari sebelumnya — ada di `KOLOM_CARI` `SuratMasuk` dan `SuratKeluar`. Yang
tidak ada adalah tes, jadi tidak ada yang bisa membuktikan kolom itu masih di daftar sampai seseorang
tidak sengaja memangkasnya.

Ditambah `CariArsipTest::test_lokasi_fisik_ikut_digali` (dua assertion: surat masuk lewat
`cari=lemari 07`, surat keluar lewat `cari=gudang c-03`), lalu digigit sesuai aturan project —
`lokasi_fisik` dikeluarkan dari `KOLOM_CARI` → tes merah dengan pesannya sendiri "Lokasi fisik surat
masuk tidak tergali." → kolom dikembalikan → hijau. Dokumen dikoreksi: yang benar-benar belum ada
adalah FILTER per lokasi di daftar dan kolom "Rak/Box" di tabel, bukan pencariannya.

Dua hal yang layak diingat sesi berikutnya: klaim "belum ada" di dokumen perlu dicek ke kode sebelum
dikerjakan (biaya salah kerja = satu sesi penuh untuk fitur yang sudah jalan), dan fitur yang tidak
punya tes bisa kehilangan barisnya tanpa ada yang sadar — kelas kegagalan yang sama dengan guardrail
flash yang buta terhadap `->with(` multi-line (Fase 6, 9 Okt).

### 10 Okt malam (susulan ketiga) — ekspor CSV log aktivitas + satu kalimat manual yang ketahuan salah sebelum terlanjur dibaca orang kantor

Paket P2 terakhir yang tidak butuh keputusan user: `GET /aktivitas/rekap`. Bentuknya sengaja mengikuti
`LaporanController`: satu method pembangun query (`AktivitasController::terapkanFilter()`) dipakai
`index()` dan `rekap()`, sehingga tidak mungkin lagi ada dua daftar filter yang lari sendiri — kelas
bug yang sudah pernah menyerang project ini (dua controller surat, dua partial cascade, tiga template
kop).

Keputusan teknis yang membedakan dari rekap surat, dan alasannya tertulis di kode: **di sini `chunk(500)`
dipakai, di laporan sengaja tidak**. Laporan harus menggabungkan dua tabel dan mengurutkan
`(tanggal_surat, jenis)` sebelum baris pertama boleh ditulis, jadi streaming tidak membantu; log adalah
satu tabel dengan urutan tetap (`created_at DESC, id DESC`), dan ia satu-satunya tabel yang bertambah
setiap aksi di semua modul. Eager load `user:id,nama_lengkap` dipindah ke dalam `terapkanFilter()`
sehingga layar dan CSV memakai muatan relasi yang sama.

`LogAktivitasRekapTest` (7 tes) mengunci: 35 catatan = 35 baris CSV meskipun layar berhalaman 30;
`sampai=2026-10-05` tetap mencakup 23:30 di hari itu; `user_id` dan `cari` ikut ke berkas; staf kena
403 di kedua route; tautan unduhan membawa filter aktif dan tidak membawa yang kosong; dan BOM +
header diurai dengan `str_getcsv`. Guardrail-nya digigit: satu `where('created_at', '>=', ...)`
diselipkan hanya di jalur `rekap()` → tes "angka layar = baris CSV" merah ("Failed asserting that
actual size 1 matches expected 2") → sabotage dibuang → hijau.

Bagian yang paling layak diingat sesi berikutnya: **saya menulis lebih dulu di
`docs/manual-pemakaian.md` bahwa nama petugas yang akunnya sudah dihapus tidak akan muncul di log —
lalu menulis tes untuk klaim itu, dan tesnya gagal.** Kenyataannya `Aktivitas::user()` memang
`withTrashed()` (keputusan 9 Okt yang juga menjaga nama petugas di laporan), jadi namanya TETAP
terbaca setelah akun dihapus — dan untuk audit itu justru yang benar. Manualnya dikoreksi, dan
perilaku sekarang dikunci tes (`test_nama_petugas_yang_akunnya_dihapus_tetap_terbaca_di_layar_dan_csv`)
supaya "perapian" berikutnya tidak membuang `withTrashed()` tanpa membaca alasannya. Pelajarannya
identik dengan aturan guardrail teks yang sudah ada di project ini: **jangan mempercayai kalimat
dokumentasi — termasuk yang baru ditulis sepuluh menit yang lalu — sebelum ada yang menjalankannya.**

Satu temuan kecil PHP yang berulang: `fputcsv()` membungkus setiap field yang mengandung spasi dengan
tanda kutip (perilaku standarnya, juga ada di rekap surat yang sudah dipakai kantor), sehingga assert
header yang memakai `explode(';')` menghasilkan `"Waktu (jam kantor)"` dan baris berikutnya ikut
terseret. Yang benar: pecah per baris dulu, lalu `str_getcsv($baris, ';')`.

Gerbang: **255 tes / 40 class lolos, 0 skipped (1.174 assertion)**, `pint --test` 188 file, Larastan
bersih (satu temuan nyata diperbaiki, bukan dibaseline: nullsafe `?->` di sisi kiri `??` redundan),
`node bin/uji-pradana-arsip.js` OK. Dokumen: manual §13.4 ditulis ulang (cara memakai tombol, arti
kolom, batasan yang jujur), `docs/daftar-peningkatan.md` butir §2#5 ditandai SELESAI, Bagian 3 + angka
suite AGENTS.md mengikuti kenyataan.



### Catatan lingkungan (terakhir diperbarui 10 Okt malam, sesudah zona waktu)

Suite sekarang **247 tes dalam 39 kelas**; yang tercapai saat MariaDB **mati** hanyalah
**229 passed + 18 skipped** — dan sejak 10 Okt skip itu benar-benar skip, bukan merah
(lihat bullet `7012062` di atas). Yang lama tercatat sebagai "206 passed, 0 skipped"
hanya tercapai saat MariaDB hidup: `CariArsipMariaDbTest` (10) + `AgregatMariaDbTest` (2) + `UrutAktivitasMariaDbTest` (1) + `NomorSuratKeluarTest` (3) + `BackupDatabaseMariaDbTest` (1) + `UjiPulihBackupMariaDbTest` (1) memakai koneksi `mysql_test_a`/`mysql_test_b` (`DB_TEST_DATABASE`, default `pradana_test`) dan di-skip dengan pesan kalau XAMPP mati — skip itu bukan kegagalan, tapi berarti bukti portabilitasnya belum ada; dua tes cadangan tambahan lagi butuh biner `mysqldump`/`mysql` dan hak `CREATE DATABASE`, jadi keduanya juga skip di CI. `Pint` 187 file per 10 Okt malam (176 sesudah Fase 7; +6 tes P0; +5 aset/JS/agenda/teks; +3 kelas cadangan + command + config) — dulu tercatat 172 (169 setelah Fase 4 membuang tiga file PHP boilerplate; 172 karena dua file tes baru + satu migration); `Larastan` level 5 bersih dengan baseline menyusut **26 → 5** (return type generik di 31 method relasi + `@property-read $lampiran_count` + `self::` untuk method private + delegasi umur arsip ke model bertipe konkret + catch yang ternyata tidak pernah bisa terjadi dibuang). Dev server tidak dijalankan untuk fase-fase perapian ini — verifikasinya lewat HTTP di dalam suite (feature test) dan di MariaDB asli, bukan lewat browser.
