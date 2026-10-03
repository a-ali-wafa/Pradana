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
        // `isForceDeleting()` (bukan `$surat->forceDeleting`): properti aslinya
        // protected, dan lewat magic getter Laravel nama itu malah cocok dengan
        // method statis `forceDeleting($callback)` yang butuh 1 argumen — akses
        // langsung melempar Error, bukan mengembalikan null.
        // Saat surat cuma di-nyahkan (soft delete, L-05), berkas & baris
        // lampiran sengaja dibiarkan utuh supaya masih bisa dipulihkan.
        $hapusPermanen = $suratMasuk->isForceDeleting();

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
        // Bedakan "baru di-nyahkan" dari "benar-benar dimusnahkan": dua-duanya
        // memicu event `deleted`, tapi only yang kedua tidak bisa dibatalkan.
        $aksi = $suratMasuk->isForceDeleting()
            ? "Memusnahkan surat masuk: {$suratMasuk->perihal}"
            : "Memindahkan surat masuk ke tempat sampah: {$suratMasuk->perihal}";

        $this->catatAktivitas($aksi, $suratMasuk);
    }

    public function restored(SuratMasuk $suratMasuk): void
    {
        $this->catatAktivitas("Memulihkan surat masuk dari tempat sampah: {$suratMasuk->perihal}", $suratMasuk);
    }
}
