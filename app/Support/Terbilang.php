<?php

namespace App\Support;

/**
 * Angka → kata bahasa Indonesia ("2026" jadi "dua ribu dua puluh enam").
 *
 * Dipakai untuk keluaran PDF yang mengikuti tata naskah dinas:
 * - baris "Lampiran : 2 (dua) berkas" pada surat keluar,
 * - блок hari/tanggal Berita Acara ("tanggal : lima, bulan : Oktober,
 *   tahun : dua ribu dua puluh enam") yang secara turun-temurun ditulis dalam
 *   huruf, bukan angka, di kantor desa/kelurahan.
 *
 * Sengaja ditulis sendiri, bukan dependency pihak ketiga: aturannya pendek,
 * stabil, dan tidak perlu ikut ter-update saat deploy ke shared hosting (K1=a).
 * Batas aman sampai miliar (kurang dari itu tidak ada di data kantor).
 */
class Terbilang
{
    private const SATUAN = [
        '', 'satu', 'dua', 'tiga', 'empat', 'lima',
        'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas',
    ];

    public static function kata(int $angka): string
    {
        if ($angka === 0) {
            return 'nol';
        }

        if ($angka < 0) {
            return 'min '.$angka;
        }

        return trim(self::bagian($angka));
    }

    /**
     * Versi "2 (dua)" yang dipakai baris Lampiran/Nomor di kop surat. Angka besar
     * (di luar miliar) tidak diurai — ditulis apa adanya supaya tidak pernah
     * menghasilkan kalimat ngawur.
     */
    public static function denganAngka(int $angka): string
    {
        return $angka.' ('.self::kata($angka).')';
    }

    private static function bagian(int $angka): string
    {
        if ($angka < 12) {
            return self::SATUAN[$angka];
        }

        if ($angka < 20) {
            return self::bagian($angka - 10).' belas';
        }

        if ($angka < 100) {
            $puluhan = intdiv($angka, 10);
            $sisa = $angka % 10;

            return self::bagian($puluhan).' puluh'.($sisa ? ' '.self::bagian($sisa) : '');
        }

        if ($angka < 1000) {
            $ratusan = intdiv($angka, 100);
            $sisa = $angka % 100;

            return ($ratusan === 1 ? 'seratus' : self::bagian($ratusan).' ratus')
                .($sisa ? ' '.self::bagian($sisa) : '');
        }

        if ($angka < 1000000) {
            $ribuan = intdiv($angka, 1000);
            $sisa = $angka % 1000;

            return ($ribuan === 1 ? 'seribu' : self::bagian($ribuan).' ribu')
                .($sisa ? ' '.self::bagian($sisa) : '');
        }

        foreach ([[1000000000, 'miliar'], [1000000, 'juta']] as [$pembagi, $nama]) {
            if ($angka < $pembagi) {
                continue;
            }

            $bagian = intdiv($angka, $pembagi);
            $sisa = $angka % $pembagi;

            return self::bagian($bagian).' '.$nama.($sisa ? ' '.self::bagian($sisa) : '');
        }

        return (string) $angka;
    }
}
