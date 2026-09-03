<?php

namespace App\Observers;

use App\Models\PengaturanInstansi;
use App\Traits\LogsAktivitas;

/**
 * BARU — 1 Sep 2026. Belum diregistrasikan, lihat CATATAN.md.
 * Cuma updated() — pengaturan_instansi single row, tidak ada create/delete
 * lewat aplikasi (sesuai desain edit-only PengaturanInstansiController, 12.18).
 */
class PengaturanInstansiObserver
{
    use LogsAktivitas;

    public function updated(PengaturanInstansi $pengaturan): void
    {
        $this->catatAktivitas('Mengubah pengaturan instansi', $pengaturan);
    }
}
