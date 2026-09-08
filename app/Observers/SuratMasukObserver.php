<?php

namespace App\Observers;

use App\Models\SuratMasuk;
use App\Traits\LogsAktivitas;

/**
 * BARU — 1 Sep 2026. Belum otomatis aktif hanya dengan membuat file ini —
 * WAJIB diregistrasikan manual (lihat CATATAN.md di paket ini / routes-snippet).
 */
class SuratMasukObserver
{
    use LogsAktivitas;

    public function created(SuratMasuk $suratMasuk): void
    {
        $this->catatAktivitas("Menambahkan surat masuk: {$suratMasuk->perihal}", $suratMasuk);
    }

    public function updated(SuratMasuk $suratMasuk): void
    {
        $this->catatAktivitas("Mengubah surat masuk: {$suratMasuk->perihal}", $suratMasuk);
    }

    public function deleting(SuratMasuk $suratMasuk): void
    {
        foreach ($suratMasuk->lampiran as $lampiran) {
            \App\Jobs\HapusLampiranDariDriveJob::dispatch($lampiran->google_drive_file_id);
            $lampiran->delete();
        }
    }

    public function deleted(SuratMasuk $suratMasuk): void
    {
        $this->catatAktivitas("Menghapus surat masuk: {$suratMasuk->perihal}", $suratMasuk);
    }
}
