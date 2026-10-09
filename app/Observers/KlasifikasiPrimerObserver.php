<?php

namespace App\Observers;

use App\Models\KlasifikasiPrimer;
use App\Traits\LogsAktivitas;

/**
 * Dibuat 1 Sep 2026, sekarang SUDAH diregistrasikan di AppServiceProvider::boot()
 * (lihat `AGENTS.md`). Aksi dicatat lewat trait LogsAktivitas → LogAktivitasEvent.
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
