<?php

namespace App\Listeners;

use App\Events\LogAktivitasEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CatatLogAktivitasListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LogAktivitasEvent $event): void
    {
        \App\Models\Aktivitas::create([
            'user_id' => $event->userId,
            'aksi' => $event->aksi,
            'subjek_type' => $event->subjekType,
            'subjek_id' => $event->subjekId,
        ]);
    }
}
