<?php

/*
|--------------------------------------------------------------------------
| Batas keluaran laporan
|--------------------------------------------------------------------------
|
| Kenapa file config dan bukan konstanta di controller: `LaporanController` tidak
| boleh menebak-nebak batasnya saat tes — angka ini memang perlu diubah (hosting
| kantor belum diketahui `max_execution_time`/`memory_limit`-nya, K1=a) dan harus
| bisa dinaikkan saat serah terima tanpa menyentuh kode. Nilai dibaca lewat
| `config()`, bukan `env()` di dalam controller, karena deploy memakai
| `config:cache` dan setelah itu `env()` mengembalikan null (jebakan yang sama
| sudah dicatat untuk SECURITY_CSP dan DEV_PIN).
 */

return [
    /*
     * Jumlah baris maksimum satu Buku Agenda PDF.
     *
     * Diukur 9 Okt 2026 di MariaDB tanding (XAMPP laptop, PHP 8.2): 1.000 baris
     * agenda = 8,36 detik dan puncak 52 MB. shared hosting kantor biasanya
     * `max_execution_time` 30 dtk dan `memory_limit` 128 M — jadi 2.000 baris
     * sudah di luar batas masuk akal untuk SATU permintaan, dan itu bukan
     * dokumen yang bisa "ditunggu": PDF-nya tidak ter-bit separuh jalan.
     *
     * 2.000 dipilih sebagai batas yang masih memberi ruang di bawah angka ukur,
     * bukan angka bulat indah. Melewatinya bukan error aplikasi: keluarannya
     * ditolak dengan pesan yang menyuruh mempersempit periode (lihat agenda()).
     */
    'agenda_batas_baris' => (int) env('AGENDA_BATAS_BARIS', 2000),
];
