<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Satu cara menyusun batas tanggal untuk query — dipakai laporan, log aktivitas,
 * dan dashboard.
 *
 * Kenapa perlu kelas sendiri (bukan `whereBetween`/`whereDate`/`whereYear` ditulis
 * di masing-masing controller): tiga bentuk itu semuanya salah dengan caranya
 * sendiri, dan project ini sudah dua kali kena:
 *
 * 1. `whereBetween('tanggal_surat', [$dari, $sampai])` dengan string tanggal.
 *    Eloquent menulis cast `date` sebagai `Y-m-d H:i:s`. Di MariaDB kolom DATE
 *    memotong jam sehingga masih cocok; di SQLite string `2026-12-31 00:00:00`
 *    tersimpan utuh dan `<= '2026-12-31'` jadi FALSE. Efeknya surat tertanggal
 *    hari TERAKHIR hilang dari rekap CSV dan Buku Agenda — dibuktikan
 *    BatasTanggalLaporanTest sebelum perapian 9 Okt 2026.
 * 2. `whereDate('created_at', '<=', X)` → `date(created_at) <= ?`, dan
 *    `whereMonth()/whereYear()` → `month(...) = ? and year(...) = ?`. Hasilnya
 *    benar, tapi fungsi di atas kolom membuat index `tanggal_surat` /
 *    `tanggal_diterima` / `created_at` tidak terpakai.
 *
 * Bentuk yang selalu dipakai di sini: interval SETENGAH TERBUKA
 * `[awal, akhir+1hari)` dengan pembandingan langsung ke kolom. "Sampai" berarti
 * "selesai sampai akhir hari itu" — sama seperti yang dibaca orang kantor saat
 * menulis periode di buku agenda.
 *
 * CATATAN PHP: Carbon itu mutable. `->addDay()` mengubah objek yang sama, jadi
 * setiap batas atas memakai `->copy()` — tanpa itu indeks bawah dan atas menunjuk
 * objek yang sama dan kedua batas nilainya identik.
 *
 * `self::` dipakai (bukan `static::`) untuk pemanggilan method private internal:
 * `static::` mencari method itu di kelas anak kalau kelas ini pernah di-extent,
 * dan method private tidak terlihat di sana — PHPStan menemukannya, PHP sendiri
 * memanggil versi kelas induk diam-diam.
 */
class RentangTanggal
{
    /**
     * Batas `[>=, <)` untuk satu hari kalender.
     *
     * @return array{0: string, 1: string}
     */
    public static function satuHari(string|Carbon $tanggal): array
    {
        $hari = self::carbon($tanggal)->startOfDay();

        return [$hari->toDateString(), $hari->copy()->addDay()->toDateString()];
    }

    /**
     * Batas `[>=, <)` untuk rentang dua tanggal. Periode terbalik TIDAK ditukar
     * di sini — itu tanggung jawab pemanggil (lihat LaporanController::periode()).
     *
     * @return array{0: string, 1: string}
     */
    public static function rentang(string|Carbon $dari, string|Carbon $sampai): array
    {
        $awal = self::carbon($dari)->startOfDay();
        $akhir = self::carbon($sampai)->startOfDay();

        return [$awal->toDateString(), $akhir->copy()->addDay()->toDateString()];
    }

    /**
     * Batas `[>=, <)` untuk satu bulan kalender (default: bulan berjalan).
     *
     * @return array{0: string, 1: string}
     */
    public static function bulan(?int $tahun = null, ?int $bulan = null): array
    {
        $mulai = Carbon::create(
            $tahun ?? (int) now()->year,
            $bulan ?? (int) now()->month,
            1
        )->startOfMonth();

        return [$mulai->toDateString(), $mulai->copy()->addMonthNoOverflow()->toDateString()];
    }

    /**
     * Pasang batas `[>=, <)` ke sebuah kolom tanggal pada query.
     *
     * @param  Builder  $query
     * @param  array{0: string, 1: string}  $batas
     * @return Builder
     */
    public static function terapkan($query, string $kolom, array $batas)
    {
        $query->where($kolom, '>=', $batas[0])->where($kolom, '<', $batas[1]);

        return $query;
    }

    private static function carbon(string|Carbon $nilai): Carbon
    {
        return $nilai instanceof Carbon ? $nilai : Carbon::parse($nilai);
    }
}
