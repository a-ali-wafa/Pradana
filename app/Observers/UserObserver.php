<?php

namespace App\Observers;

use App\Models\User;
use App\Traits\LogsAktivitas;

/**
 * BARU — 1 Sep 2026. Belum diregistrasikan, lihat CATATAN.md.
 * `deleted()` di sini terpicu saat soft delete (users pakai SoftDeletes, 12.4) —
 * teks aksi disesuaikan supaya tidak menyiratkan data hilang permanen.
 */
class UserObserver
{
    use LogsAktivitas;

    public function created(User $user): void
    {
        $this->catatAktivitas("Menambahkan user baru: {$user->nama_lengkap}", $user);
    }

    public function updated(User $user): void
    {
        $this->catatAktivitas("Mengubah data user: {$user->nama_lengkap}", $user);
    }

    public function deleted(User $user): void
    {
        $this->catatAktivitas("Menonaktifkan (hapus) user: {$user->nama_lengkap}", $user);
    }
}
