<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupRecordsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-records';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Mencari lampiran yatim piatu (tanpa induk surat)...');

        $lampirans = \App\Models\Lampiran::all();
        $count = 0;

        foreach ($lampirans as $lampiran) {
            // Because it's polymorphic, if the parent is hard-deleted, lampiranable will return null
            if (! $lampiran->lampiranable) {
                $this->warn("Menghapus lampiran yatim: {$lampiran->nama_file}");
                
                \App\Jobs\HapusLampiranDariDriveJob::dispatch($lampiran->google_drive_file_id);
                $lampiran->delete();
                
                $count++;
            }
        }

        $this->info("Pembersihan selesai. Total {$count} lampiran yatim dihapus.");
    }
}
