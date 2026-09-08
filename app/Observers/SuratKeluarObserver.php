<?php

namespace App\Observers;

use App\Models\SuratKeluar;
use App\Traits\LogsAktivitas;

/** BARU — 1 Sep 2026. Belum diregistrasikan, lihat CATATAN.md. */
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

    public function deleting(\App\Models\SuratKeluar $suratKeluar): void
    {
        foreach ($suratKeluar->lampiran as $lampiran) {
            \App\Jobs\HapusLampiranDariDriveJob::dispatch($lampiran->google_drive_file_id);
            $lampiran->delete();
        }
    }

    public function deleted(SuratKeluar $suratKeluar): void
    {
        $this->catatAktivitas("Menghapus surat keluar: {$suratKeluar->perihal}", $suratKeluar);
    }
}
