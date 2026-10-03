<?php

namespace App\Listeners;

use App\Events\LogAktivitasEvent;
use App\Models\Aktivitas;

/**
 * Pencatatan log aktivitas SENGAJA sinkron (keputusan L-22 / S4): arsip resmi
 * harus menjamin riwayat tercatat, dan sistem ini berjalan di shared hosting
 * tanpa queue worker — lewat queue berarti log bisa hilang tanpa suara.
 */
class CatatLogAktivitasListener
{
    public function handle(LogAktivitasEvent $event): void
    {
        Aktivitas::create([
            'user_id' => $event->userId,
            'aksi' => $event->aksi,
            'subjek_type' => $event->subjekType,
            'subjek_id' => $event->subjekId,
        ]);
    }
}
