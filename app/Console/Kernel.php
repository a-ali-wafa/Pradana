<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Backup arsip ke Google Drive (L-01). tanpaOverlapping: kalau sinkron
        // sebelumnya masih jalan (file besar/jaringan lambat), jadwal tidak menimpa.
        $schedule->command('arsip:sinkron-ke-drive')
            ->daily()
            ->withoutOverlapping();

        // Sekali seminggu ke staf, daftar arsip yang sudah lewat retensi (L-04).
        $schedule->command('arsip:daftar-usang')->weekly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
