<?php

namespace App\Observers;

use App\Models\KlasifikasiSekunder;
use App\Traits\LogsAktivitas;

/** BARU — 1 Sep 2026. Belum diregistrasikan, lihat CATATAN.md. */
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
