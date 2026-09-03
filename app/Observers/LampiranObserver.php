<?php

namespace App\Observers;

use App\Models\Lampiran;
use App\Traits\LogsAktivitas;

/**
 * BARU — 1 Sep 2026. Belum diregistrasikan, lihat CATATAN.md.
 * Sengaja TIDAK ada updated() — lampiran (file) tidak diedit in-place di alur
 * yang sudah ada, cuma diunggah baru atau dihapus (lihat LampiranController, 12.16).
 */
class LampiranObserver
{
    use LogsAktivitas;

    public function created(Lampiran $lampiran): void
    {
        $this->catatAktivitas("Mengunggah lampiran: {$lampiran->nama_file}", $lampiran);
    }

    public function deleted(Lampiran $lampiran): void
    {
        $this->catatAktivitas("Menghapus lampiran: {$lampiran->nama_file}", $lampiran);
    }
}
