<?php

/*
|--------------------------------------------------------------------------
| Cadangkan database (arsip:backup-db)
|--------------------------------------------------------------------------
|
| Kenapa ini ada dan kenapa mendesak: pada 10 Okt 2026 MariaDB di laptop
| pengembangan korup setelah mati listrik paksa (`InnoDB: page 32769 in space 0
| outside the tablespace bounds`) dan satu-satunya alasan kejadiannya bisa
| dideal-kan adalah salinan manual folder datadir yang kebetulan dibuat sebelumnya.
| Lampiran sudah dicadangkan ke Google Drive (`arsip:sinkron-ke-drive`, L-01), tapi
| isi database — 13 tabel surat, klasifikasi, log aktivitas, Berita Acara — belum
| punya jalur otomatis sama sekali.
|
| Semua nilai dibaca lewat `config()`, BUKAN `env()` di dalam command: deploy
| memakai `config:cache` dan setelah itu `env()` mengembalikan null (jebakan yang
| sama sudah dicatat untuk SECURITY_CSP, DEV_PIN, dan AGENDA_BATAS_BARIS).
 */

return [
    /*
     * Berkas `mysqldump`. Di XAMPP laptop ini: C:\xampp\mysql\bin\mysqldump.exe.
     * Di shared hosting biasanya sudah ada di PATH — kalau tidak, tanyakan path
     * absolutnya ke penyedia hosting dan isi di .env (jangan ditebak).
     */
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),

    /*
     * Tempat dump ditulis. WAJIB di luar `public/`: isinya berisi struktur data
     * kantor dan tidak boleh bisa diambil lewat URL. Default-nya di dalam
     * storage/app/private (disk privat yang sama dengan lampiran).
     */
    'dir' => env('BACKUP_DIR', storage_path('app/private/db-backup')),

    /*
     * Berapa hari dump disimpan. Rotasi hanya menghapus berkas yang polanya
     * dikenali (lihat command), jadi file lain di folder itu tidak pernah disentuh.
     */
    'simpan_hari' => (int) env('BACKUP_SIMPAN_HARI', 30),

    /*
     * `--routines` dan `--events` membuat cadangan ikut membawa prosedur/event
     * kalau suatu saat kantor memakainya. Biayanya tidak nol di target deploy:
     * di MariaDB 10.4 `--routines` menuntut hak baca ke tabel sistem (`mysql.proc`)
     * dan `--events` menuntut privilege EVENT — pemakai database tunggal di
     * shared hosting (K1=a) sering tidak punya keduanya, dan mysqldump-nya keluar
     * dengan error, bukan dengan cadangan.
     *
     * Sekarang skema PRADANA punya NOL prosedur dan NOL event (digerok 10 Okt di
     * seluruh `database/` dan `app/`), jadi kalau perintah ini gagal di server
     * kantor dengan "Access denied", setel dua nilai itu false lewat .env:
     * cadangan tetap lengkap untuk skema yang ada. Kalau nanti ada migration yang
     * menambah prosedur atau event, kembalikan true.
     */
    'sertakan_prosedur' => (bool) env('BACKUP_PROSEDUR', true),

    'sertakan_event' => (bool) env('BACKUP_EVENT', true),
];
