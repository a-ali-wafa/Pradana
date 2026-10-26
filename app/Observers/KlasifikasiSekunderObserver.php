<?php

namespace App\Observers;

use App\Models\KlasifikasiSekunder;
use App\Traits\LogsAktivitas;

/**
 * Diregistrasikan di AppServiceProvider::boot(). Aksi dicatat lewat trait
 * LogsAktivitas → LogAktivitasEvent.
 */
class KlasifikasiSekunderObserver
{
    use LogsAktivitas;

    public function created(KlasifikasiSekunder $klasifikasi): void
    {
        $this->catatAktivitas("Menambahkan klasifikasi sekunder: {$klasifikasi->nama}", $klasifikasi);
    }

    public function updated(KlasifikasiSekunder $klasifikasi): void
    {
        $this->catatAktivitas("Mengubah klasifikasi sekunder: {$klasifikasi->nama}", $klasifikasi);
    }

    public function deleted(KlasifikasiSekunder $klasifikasi): void
    {
        $this->catatAktivitas("Menghapus klasifikasi sekunder: {$klasifikasi->nama}", $klasifikasi);
    }
}
