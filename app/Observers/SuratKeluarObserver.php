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
        // Pakai isForceDeleting() — `$model->forceDeleting` tidak bisa diakses
        // dari luar (protected) dan magic getter-nya salah resolve ke method statis.
        $hapusPermanen = $suratKeluar->isForceDeleting();

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
        $aksi = $suratKeluar->isForceDeleting()
            ? "Memusnahkan surat keluar: {$suratKeluar->perihal}"
            : "Memindahkan surat keluar ke tempat sampah: {$suratKeluar->perihal}";

        $this->catatAktivitas($aksi, $suratKeluar);
    }

    public function restored(SuratKeluar $suratKeluar): void
    {
        $this->catatAktivitas("Memulihkan surat keluar dari tempat sampah: {$suratKeluar->perihal}", $suratKeluar);
    }
}
