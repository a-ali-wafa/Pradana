<?php

namespace App\Observers;

use App\Models\DrafKontenSuratKeluar;
use App\Traits\LogsAktivitas;

/**
 * Draf konten ikut dicatat karena isinya adalah badan surat resmi —
 * perubahan redaksional perlu bisa ditelusuri (L-22).
 */
class DrafKontenSuratKeluarObserver
{
    use LogsAktivitas;

    public function created(DrafKontenSuratKeluar $draf): void
    {
        $this->catatAktivitas("Mengisi draf surat keluar: {$draf->suratKeluar?->nomor_surat}", $draf);
    }

    public function updated(DrafKontenSuratKeluar $draf): void
    {
        $this->catatAktivitas("Mengubah draf surat keluar: {$draf->suratKeluar?->nomor_surat}", $draf);
    }

    public function deleted(DrafKontenSuratKeluar $draf): void
    {
        $this->catatAktivitas("Menghapus draf surat keluar: {$draf->suratKeluar?->nomor_surat}", $draf);
    }
}
