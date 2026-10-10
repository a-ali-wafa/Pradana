<?php

namespace App\Observers;

use App\Models\PengaturanInstansi;
use App\Traits\LogsAktivitas;
use Illuminate\Support\Facades\Cache;

/**
 * Dibuat 1 Sep 2026, sekarang SUDAH diregistrasikan di AppServiceProvider::boot()
 * (lihat `AGENTS.md`). Aksi dicatat lewat trait LogsAktivitas → LogAktivitasEvent.
 * Cuma updated() — pengaturan_instansi single row, tidak ada create/delete
 * lewat aplikasi (sesuai desain edit-only PengaturanInstansiController, 12.18).
 */
class PengaturanInstansiObserver
{
    use LogsAktivitas;

    public function updated(PengaturanInstansi $pengaturan): void
    {
        $this->catatAktivitas('Mengubah pengaturan instansi', $pengaturan);

        // Brand di sidebar/dashboard dibaca dari cache (PengaturanInstansi::untukTampilan()).
        // Tanpa pembuangan ini, admin yang baru memasang logo atau mengganti nama
        // instansi akan melihat kop lama di seluruh layar sampai cache kedaluwarsa —
        // dan itu persis keluhan "logo tidak muncul" yang sudah pernah dijawab 5 Okt.
        Cache::forget(PengaturanInstansi::KUNCI_MEREK);
    }
}
