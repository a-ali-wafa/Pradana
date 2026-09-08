<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HapusLampiranDariDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(public string $googleDriveFileId)
    {}

    public function handle(\App\Services\GoogleDriveService $drive): void
    {
        try {
            $drive->delete($this->googleDriveFileId);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal hapus file Drive via Job', [
                'google_drive_file_id' => $this->googleDriveFileId,
                'error' => $e->getMessage(),
            ]);
            // If it's a 404 (file already deleted), we can ignore it
            if ($e->getCode() === 404) {
                return;
            }
            throw $e;
        }
    }
}
