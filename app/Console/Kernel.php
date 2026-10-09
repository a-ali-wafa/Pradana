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
        // withoutOverlapping: klaim lama di AGENTS.md bilang jadwal ini sudah
        // memilikinya, padahal tidak — dua jadwal lain saja. Kalau tabel arsip
        // membesar dan pemindaian itu makan waktu lebih dari seminggu, tanpa
        // penggunci ini dua instances bisa jalan bersamaan.
        $schedule->command('arsip:daftar-usang')
            ->weekly()
            ->withoutOverlapping();

        // Retensi log aktivitas (E6): bulanan, jejak pemusnahan tidak pernah dibuang.
        $schedule->command('arsip:bersihkan-log')->monthly()->withoutOverlapping();
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
