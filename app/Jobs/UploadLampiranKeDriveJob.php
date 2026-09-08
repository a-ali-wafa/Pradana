<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UploadLampiranKeDriveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        public string $localFilePath,
        public string $safeName,
        public string $mimeType,
        public string $folderId,
        public $surat,
        public int $userId
    ) {}

    public function handle(\App\Services\GoogleDriveService $drive): void
    {
        if (! \Illuminate\Support\Facades\Storage::exists($this->localFilePath)) {
            return;
        }

        $content = \Illuminate\Support\Facades\Storage::get($this->localFilePath);
        
        $uploaded = $drive->upload(
            $this->folderId,
            $this->safeName,
            $this->mimeType,
            $content
        );

        \Illuminate\Support\Facades\Storage::delete($this->localFilePath);

        try {
            $this->surat->lampiran()->create([
                'google_drive_file_id' => $uploaded['id'],
                'google_drive_folder_id' => $this->folderId,
                'nama_file' => $uploaded['name'],
                'mime_type' => $uploaded['mime_type'],
                'ukuran' => $uploaded['size'],
                'diunggah_oleh' => $this->userId,
            ]);
        } catch (\Throwable $e) {
            $drive->delete($uploaded['id']);
            throw $e;
        }
    }
}
