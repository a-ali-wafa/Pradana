<?php

namespace App\Observers;

use App\Models\SuratKeluar;
use App\Traits\LogsAktivitas;

/** Baru 1 Sep 2026; diregistrasikan di AppServiceProvider::boot(). */
class SuratKeluarObserver
{
    use LogsAktivitas;

    public function created(SuratKeluar $suratKeluar): void
    {
        $this->catatAktivitas("Menambahkan surat keluar: {$suratKeluar->perihal}", $suratKeluar);
    }

    public function updated(SuratKeluar $suratKeluar): void
    {
        $this->catatAktivitas("Mengubah surat keluar: {$suratKeluar->perihal}", $suratKeluar);
    }

    public function deleting(SuratKeluar $suratKeluar): void
    {
        // Sama seperti SuratMasukObserver: berkas fisik hanya ikut hilang kalau
        // penghapusan benar-benar permanen, bukan saat surat di-nyahkan (L-05).
        $hapusPermanen = $suratKeluar->forceDeleting ?? true;

        if (! $hapusPermanen) {
            return;
        }

        foreach ($suratKeluar->lampiran as $lampiran) {
            $lampiran->hapusBerkasFisik();
            $lampiran->delete();
        }
    }

    public function deleted(SuratKeluar $suratKeluar): void
    {
        $this->catatAktivitas("Menghapus surat keluar: {$suratKeluar->perihal}", $suratKeluar);
    }
}
