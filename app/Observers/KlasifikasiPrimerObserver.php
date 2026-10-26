<?php

namespace App\Observers;

use App\Models\KlasifikasiPrimer;
use App\Traits\LogsAktivitas;

/**
 * Diregistrasikan di AppServiceProvider::boot(). Aksi dicatat lewat trait
 * LogsAktivitas → LogAktivitasEvent.
 */
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
