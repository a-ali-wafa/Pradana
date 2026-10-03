<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event internal (bukan broadcast) untuk mencatat satu aksi ke tabel `aktivitas`.
 * Dipancarkan oleh trait LogsAktivitas, ditangani CatatLogAktivitasListener.
 */
class LogAktivitasEvent
{
    use Dispatchable;

    public function __construct(
        public int $userId,
        public string $aksi,
        public string $subjekType,
        public int $subjekId
    ) {}
}
