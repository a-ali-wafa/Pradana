<?php

namespace App\Observers;

use App\Models\PengajuanHapusLampiran;
use App\Traits\LogsAktivitas;

/**
 * BARU — 1 Sep 2026. Belum diregistrasikan, lihat CATATAN.md.
 * updated() sengaja hanya bereaksi kalau kolom `status` yang berubah — supaya
 * perubahan `catatan_admin` sendirian (kalau ada alur edit catatan terpisah nanti)
 * tidak ikut membuat baris log yang membingungkan.
 */
class PengajuanHapusLampiranObserver
{
    use LogsAktivitas;

    public function created(PengajuanHapusLampiran $pengajuan): void
    {
        $this->catatAktivitas(
            "Mengajukan hapus lampiran: {$pengajuan->nama_file_snapshot}",
            $pengajuan
        );
    }

    public function updated(PengajuanHapusLampiran $pengajuan): void
    {
        if (! $pengajuan->wasChanged('status')) {
            return;
        }

        $aksi = match ($pengajuan->status) {
            'disetujui' => "Menyetujui pengajuan hapus lampiran: {$pengajuan->nama_file_snapshot}",
            'ditolak' => "Menolak pengajuan hapus lampiran: {$pengajuan->nama_file_snapshot}",
            default => "Mengubah status pengajuan hapus lampiran: {$pengajuan->nama_file_snapshot}",
        };

        $this->catatAktivitas($aksi, $pengajuan);
    }
}
