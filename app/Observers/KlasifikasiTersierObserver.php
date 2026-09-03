<?php

namespace App\Observers;

use App\Models\KlasifikasiTersier;
use App\Traits\LogsAktivitas;

/** BARU — 1 Sep 2026. Belum diregistrasikan, lihat CATATAN.md. */
class KlasifikasiTersierObserver
{
    use LogsAktivitas;

    public function created(KlasifikasiTersier $klasifikasi): void
    {
        $this->catatAktivitas("Menambahkan klasifikasi tersier: {$klasifikasi->nama}", $klasifikasi);
    }

    public function updated(KlasifikasiTersier $klasifikasi): void
    {
        $this->catatAktivitas("Mengubah klasifikasi tersier: {$klasifikasi->nama}", $klasifikasi);
    }

    public function deleted(KlasifikasiTersier $klasifikasi): void
    {
        $this->catatAktivitas("Menghapus klasifikasi tersier: {$klasifikasi->nama}", $klasifikasi);
    }
}
