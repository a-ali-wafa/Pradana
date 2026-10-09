<?php

namespace App\Models\Concerns;

use App\Support\RetensiArsip;
use Illuminate\Support\Carbon;

/**
 * Satu cara menghitung umur arsip untuk surat masuk & surat keluar.
 *
 * Kenapa perlu (L-21, [LOCKED]): acuan umur arsip DISERAGAMKAN ke `tanggal_surat`
 * untuk kedua jenis surat. Sebelum trait ini ada, halaman show surat masuk
 * menghitung sendiri umurnya dari `tanggal_diterima` di dalam view — jadi
 * tombol "Ajukan Hapus" bisa muncul untuk surat yang menurut sistem (dan menurut
 * `arsip:daftar-usang`, dan menurut form pemusnahan) belum lewat retensi, dan
 * sebaliknya. Aturan yang sama tidak boleh punya dua tempat perhitungan.
 *
 * Angkanya sendiri tinggal di `App\Support\RetensiArsip`, bukan di trait ini:
 * PHP melarang akses konstanta trait dari luar kelas pemakainya.
 */
trait UmurArsip
{
    /**
     * Surat ini sudah lewat masa retensi (lebih dari 5 tahun sejak dibuat)?
     *
     * "LEBIH DARI" — bukan "tepat 5 tahun": keputusan L-04/E1 dan bentuk yang
     * ditegakkan `PengajuanHapusLampiranController::store()` (`< batas`).
     * Surat tanpa `tanggal_surat` dianggap TIDAK layak: tidak ada yang bisa
     * dihapus kalau umurnya tidak diketahui.
     */
    public function lewatRetensi(?Carbon $sekarang = null): bool
    {
        if (! $this->tanggal_surat) {
            return false;
        }

        return $this->tanggal_surat->lt(RetensiArsip::batas($sekarang));
    }

    /** Umur surat dalam tahun (bulat ke bawah) — untuk keterangan di layar. */
    public function umurTahun(): int
    {
        if (! $this->tanggal_surat) {
            return 0;
        }

        return (int) $this->tanggal_surat->diffInYears(now());
    }
}
