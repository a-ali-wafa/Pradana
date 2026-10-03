<?php

namespace App\Console\Commands;

use App\Models\Lampiran;
use App\Models\PengaturanInstansi;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Salinan arsip ke Google Drive (keputusan L-01): Drive bukan lagi tempat
 * pencatatan utama, hanya backup terjadwal. Perintah ini menaikkan file lokal
 * yang belum punya salinan Drive, dengan struktur folder lama:
 * {root} / Surat Masuk|Keluar / {kode - nama primer} / file.
 *
 * Kegagalan satu file tidak menghentikan sisanya — arsip lokal sudah aman.
 */
class SinkronkanLampiranKeDriveCommand extends Command
{
    protected $signature = 'arsip:sinkron-ke-drive {--dry-run : Hitung saja, jangan upload sebenarnya}';

    protected $description = 'Kirim salinan lampiran lokal yang belum ada di Google Drive ke Drive (backup terjadwal)';

    public function handle(GoogleDriveService $drive): int
    {
        $root = config('gdrive.root_folder_id');

        if (blank($root)) {
            $this->error('GOOGLE_DRIVE_ROOT_FOLDER_ID kosong — sinkronisasi tidak bisa jalan.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $pengaturan = PengaturanInstansi::query()->first();
        $folderCache = [];
        $terkirim = 0;
        $gagal = 0;

        Lampiran::query()
            ->whereNotNull('path')
            ->where(fn ($q) => $q->whereNull('google_drive_file_id')->orWhere('google_drive_file_id', ''))
            ->orderBy('id')
            ->chunk(50, function ($lampiranBatch) use ($drive, $root, $pengaturan, &$folderCache, &$terkirim, &$gagal, $dryRun) {
                foreach ($lampiranBatch as $lampiran) {
                    $disk = Storage::disk($lampiran->disk ?? 'arsip');

                    if (! $disk->exists($lampiran->path)) {
                        $gagal++;
                        $this->warn("File lokal hilang: {$lampiran->nama_file} (id {$lampiran->id})");

                        continue;
                    }

                    try {
                        $folderId = $this->folderTujuan($drive, $root, $pengaturan, $lampiran, $folderCache);

                        if ($dryRun) {
                            $this->line("akan upload: {$lampiran->nama_file}");
                            $terkirim++;

                            continue;
                        }

                        $hasil = $drive->upload(
                            $folderId,
                            $lampiran->nama_file,
                            $lampiran->mime_type ?: 'application/octet-stream',
                            (string) $disk->get($lampiran->path),
                        );

                        $lampiran->google_drive_file_id = $hasil['id'];
                        $lampiran->google_drive_folder_id = $folderId;
                        $lampiran->save();

                        $terkirim++;
                    } catch (\Throwable $e) {
                        $gagal++;
                        $this->error("gagal id {$lampiran->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info(($dryRun ? '[dry-run] ' : '')."Sinkronisasi selesai: {$terkirim} terkirim, {$gagal} gagal.");

        return self::SUCCESS;
    }

    /**
     * Resolusi + cache folder Drive per (jenis surat, klasifikasi primer).
     * Level primer saja, sesuai keputusan C6.
     */
    private function folderTujuan(
        GoogleDriveService $drive,
        string $root,
        ?PengaturanInstansi $pengaturan,
        Lampiran $lampiran,
        array &$cache,
    ): string {
        $adalahMasuk = $lampiran->lampiranable_type === \App\Models\SuratMasuk::class;
        $jenis = $adalahMasuk ? 'Surat Masuk' : 'Surat Keluar';
        $kolomCache = $adalahMasuk ? 'gdrive_folder_surat_masuk_id' : 'gdrive_folder_surat_keluar_id';

        $folderJenis = $cache[$kolomCache] ?? null;

        if (! $folderJenis) {
            $folderJenis = $pengaturan?->{$kolomCache} ?: $drive->getOrCreateFolder($jenis, $root);

            if ($pengaturan && ! $pengaturan->{$kolomCache}) {
                $pengaturan->{$kolomCache} = $folderJenis;
                $pengaturan->save();
            }

            $cache[$kolomCache] = $folderJenis;
        }

        $primer = $lampiran->lampiranable?->primer;

        if (! $primer) {
            return $folderJenis;
        }

        $namaPrimer = Str::slug("{$primer->kode} - {$primer->nama}");

        if (isset($cache[$namaPrimer])) {
            return $cache[$namaPrimer];
        }

        return $cache[$namaPrimer] = $drive->getOrCreateFolder(
            "{$primer->kode} - {$primer->nama}",
            $folderJenis,
        );
    }
}
