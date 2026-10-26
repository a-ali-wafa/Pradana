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
        // Backup arsip ke Google Drive. tanpaOverlapping: kalau sinkron
        // sebelumnya masih jalan (file besar/jaringan lambat), jadwal tidak menimpa.
        $schedule->command('arsip:sinkron-ke-drive')
            ->daily()
            ->withoutOverlapping();

        // Cadangan DATABASE harian. Drive hanya mengurus lampiran; isi
        // database kantor belum pernah punya jalur otomatis sampai hari ini — dan
        // hari ini juga MariaDB dev korup, dan terbukti tanpa cadangan tidak ada yang
        // bisa dilakukan selain menyalin datadir manual. Jam 02:10: `daily()` di
        // atasnya jalan 00:00, jadi keduanya tidak berebut jaringan yang sama;
        // tanpaOverlapping dipasang supaya dump yang panjang tidak pernah ditimpa
        // dump berikutnya (dua mysqldump bersamaan di hosting shared = dua-duanya
        // bisa mati di tengah).
        $schedule->command('arsip:backup-db')
            ->dailyAt('02:10')
            ->withoutOverlapping();

        // Sekali seminggu: daftar arsip yang sudah lewat retensi. Sistem hanya
        // memberi DAFTAR — keputusan men-*inaktif*-kan tetap di tangan petugas.
        // withoutOverlapping: kalau tabel arsip membesar dan pemindaian itu makan
        // waktu lebih dari seminggu, tanpa penggunci ini dua instances bisa jalan
        // bersamaan.
        $schedule->command('arsip:daftar-usang')
            ->weekly()
            ->withoutOverlapping();

        // Retensi log aktivitas: bulanan. Jejak pemusnahan tidak pernah dibuang.
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
