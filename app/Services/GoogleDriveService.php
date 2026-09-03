<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleDriveService
{
    protected Drive $service;

    public function __construct()
    {
        $client = new Client();
        $client->setApplicationName('PRADANA Arsip Digital');

        $credentialsPath = config('gdrive.credentials_path');
        if ($credentialsPath && file_exists($credentialsPath)) {
            $client->setAuthConfig($credentialsPath);
        }

        $client->addScope(Drive::DRIVE);

        $this->service = new Drive($client);
    }

    /**
     * Cari subfolder dengan nama tertentu di dalam $parentId.
     * Kalau belum ada, buat baru. Mengembalikan folder ID.
     * (Setara fungsi getOrCreateFolder() di versi Apps Script lama.)
     */
    public function getOrCreateFolder(string $name, string $parentId): string
    {
        $safeName = str_replace("'", "\\'", $name);

        $query = sprintf(
            "name = '%s' and mimeType = 'application/vnd.google-apps.folder' and '%s' in parents and trashed = false",
            $safeName,
            $parentId
        );

        $result = $this->service->files->listFiles([
            'q' => $query,
            'fields' => 'files(id, name)',
            'spaces' => 'drive',
        ]);

        if (count($result->getFiles()) > 0) {
            return $result->getFiles()[0]->getId();
        }

        $folder = new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]);

        $created = $this->service->files->create($folder, ['fields' => 'id']);

        return $created->getId();
    }

    /**
     * Upload file (dari konten binary) ke folder tertentu.
     * Mengembalikan metadata file yang baru dibuat di Drive,
     * siap disimpan ke tabel `lampiran`.
     */
    public function upload(string $folderId, string $filename, string $mimeType, string $content): array
    {
        $fileMetadata = new DriveFile([
            'name' => $filename,
            'parents' => [$folderId],
        ]);

        $file = $this->service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id, name, mimeType, size',
        ]);

        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'mime_type' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ];
    }

    /**
     * Ambil isi file untuk di-stream lewat backend Laravel.
     * Dipakai supaya file TIDAK perlu link publik Drive -- akses
     * cukup dijaga oleh middleware 'auth' di controller pemanggil.
     */
    public function getFileContent(string $fileId): array
    {
        $meta = $this->service->files->get($fileId, ['fields' => 'name, mimeType']);
        $response = $this->service->files->get($fileId, ['alt' => 'media']);

        return [
            'content' => $response->getBody()->getContents(),
            'mime_type' => $meta->getMimeType(),
            'name' => $meta->getName(),
        ];
    }

    public function delete(string $fileId): void
    {
        $this->service->files->delete($fileId);
    }
}
