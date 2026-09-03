<?php

namespace App\Observers;

use App\Models\KlasifikasiPrimer;
use App\Traits\LogsAktivitas;

/** BARU — 1 Sep 2026. Belum diregistrasikan, lihat CATATAN.md. */
class KlasifikasiPrimerObserver
{
    use LogsAktivitas;

    public function created(KlasifikasiPrimer $klasifikasi): void
    {
        $this->catatAktivitas("Menambahkan klasifikasi primer: {$klasifikasi->nama}", $klasifikasi);
    }

    public function updated(KlasifikasiPrimer $klasifikasi): void
    {
        $this->catatAktivitas("Mengubah klasifikasi primer: {$klasifikasi->nama}", $klasifikasi);
    }

    public function deleted(KlasifikasiPrimer $klasifikasi): void
    {
        $this->catatAktivitas("Menghapus klasifikasi primer: {$klasifikasi->nama}", $klasifikasi);
    }
}
