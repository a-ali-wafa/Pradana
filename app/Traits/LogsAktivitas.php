<?php

namespace App\Traits;

use App\Events\LogAktivitasEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Dicatat otomatis untuk setiap aksi model (dibuat 1 Sep 2026, dipakai 11 Observer
 * di `app/Observers/` yang diregistrasikan di `AppServiceProvider::boot()`).
 *
 * Dipakai oleh Model Observer supaya tiap Observer tidak menulis ulang logika
 * penerbitan event log. Yang mengisi tabel `aktivitas` adalah listener
 * `App\Listeners\CatatLogAktivitasListener` atas event `LogAktivitasEvent`.
 *
 * ATURAN PENTING: kalau tidak ada user yang sedang login (dipanggil dari seeder,
 * perintah console, atau jadwal terjadwal), log DILEWATI, bukan dipaksa. Alasannya
 * `aktivitas.user_id` tidak nullable dengan FK `restrictOnDelete` — insert tanpa
 * user akan gagal, dan memberi user_id karangan sendiri berarti memalsukan jejak
 * "siapa mengubah apa" (L-22: log ini satu-satunya jejak itu; L-10: arsip kantor
 * tidak punya pemilik).
 */
trait LogsAktivitas
{
    protected function catatAktivitas(string $aksi, Model $subjek): void
    {
        if (! Auth::check()) {
            return;
        }

        LogAktivitasEvent::dispatch(
            Auth::id(),
            $aksi,
            $subjek->getMorphClass(),
            $subjek->getKey()
        );
    }
}
