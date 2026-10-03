<?php

namespace App\Observers;

use App\Models\SuratMasuk;
use App\Traits\LogsAktivitas;

/**
 * Registrasi observer ada di AppServiceProvider::boot() — aktif sejak 1 Sep 2026.
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
        // `forceDeleting` hanya ada pada model bertingkah SoftDeletes. Untuk
        // model tanpa soft delete hasilnya null -> dianggap hapus permanen.
        // Saat surat cuma di-nyahkan (soft delete, L-05), berkas & baris
        // lampiran sengaja dibiarkan utuh supaya masih bisa dipulihkan.
        $hapusPermanen = $suratMasuk->forceDeleting ?? true;

        if (! $hapusPermanen) {
            return;
        }

        foreach ($suratMasuk->lampiran as $lampiran) {
            $lampiran->hapusBerkasFisik();
            $lampiran->delete();
        }
    }

    public function deleted(SuratMasuk $suratMasuk): void
    {
        $this->catatAktivitas("Menghapus surat masuk: {$suratMasuk->perihal}", $suratMasuk);
    }
}
