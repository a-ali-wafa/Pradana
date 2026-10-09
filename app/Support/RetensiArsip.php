<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Satu angka untuk satu aturan: berapa lama arsip harus disimpan sebelum boleh
 * diusulkan musnah / hapus.
 *
 * E1 + L-04 [LOCKED]: retensi 5 tahun, SERAGAM untuk semua jenis surat, dan
 * sistem hanya MEMBERI DAFTAR yang sudah lewat — keputusan men-*inaktif*-kan dan
 * menyetujui pemusnahan tetap di tangan orang kantor.
 *
 * Kenapa konstanta kelas biasa dan bukan konstanta di trait `UmurArsip`: PHP
 * melarang akses konstanta trait dari luar kelas pemakainya
 * (`UmurArsip::BATAS_RETENSI_TAHUN` melempar Error "Cannot access trait constant
 * directly" — kesalahan yang dibuat dan ditangkap suite pada 9 Okt 2026). Kelas
 * konstanta bisa dibaca dari model, controller, maupun perintah artisan tanpa
 * pura-pura lewat salah satu model surat.
 *
 * Semua pemakai harus lewat sini: `UmurArsip::lewatRetensi()`,
 * `PemusnahanArsipController` (daftar kandidat + validasi `store()`), dan
 * `arsip:daftar-usang`. Kalau nanti kantor mengubah 5 tahun, satu baris ini yang
 * berubah — bukan tiga tempat yang bisa lupa.
 */
final class RetensiArsip
{
    /** Tahun sejak `tanggal_surat` (L-21: acuan diseragamkan ke tanggal surat). */
    public const TAHUN = 5;

    /**
     * Batas "sudah lewat retensi": tanggal surat di bawah ini berarti usang.
     *
     * `$sekarang` dikirim untuk perhitungan balik/tes; `$tahun` hanya dipakai
     * penyaring manual (`arsip:daftar-usang --tahun=8`) — default keduanya
     * adalah aturan yang berlaku. `copy()` bukan hiasan: Carbon itu mutable,
     * tanpa itu objek milik pemanggil yang ikut berubah.
     */
    public static function batas(?Carbon $sekarang = null, ?int $tahun = null): Carbon
    {
        return ($sekarang ?? Carbon::now())->copy()->subYears($tahun ?? self::TAHUN);
    }

    private function __construct()
    {
        // Kelas konstanta: tidak untuk di-instantiasi.
    }
}
