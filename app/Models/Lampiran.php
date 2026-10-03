<?php

namespace App\Models;

use App\Services\GoogleDriveService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Lampiran extends Model
{
    protected $table = 'lampiran';

    protected $fillable = [
        'lampiranable_id', 'lampiranable_type',
        'google_drive_file_id', 'google_drive_folder_id',
        'disk', 'path', 'hash_file',
        'nama_file', 'mime_type', 'ukuran', 'diunggah_oleh',
    ];

    public function lampiranable(): MorphTo
    {
        return $this->morphTo();
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    /**
     * Riwayat pengajuan hapus file ini (menunggu/disetujui/ditolak).
     * Dipakai di halaman show surat dan daftar pengajuan admin.
     */
    public function pengajuanHapus(): HasMany
    {
        return $this->hasMany(PengajuanHapusLampiran::class, 'lampiran_id');
    }

    /**
     * File fisik sudah tersedia di disk lokal (sumber utama sejak L-01)?
     */
    public function adaDiLokal(): bool
    {
        return filled($this->path) && Storage::disk($this->disk ?? 'arsip')->exists($this->path);
    }

    /**
     * Umur surat induk dihitung dari tanggal_surat untuk kedua jenis surat
     * (keputusan L-21) — sebelumnya surat masuk memakai tanggal_diterima,
     * sehingga aturan retensi 5 tahun punya dua dasar hitung.
     */
    public function isEligibleForDeletion(): bool
    {
        $surat = $this->lampiranable;

        if (! $surat || ! $surat->tanggal_surat) {
            return false;
        }

        return Carbon::parse($surat->tanggal_surat)->lt(now()->subYears(5));
    }

    /**
     * Hapus berkas fisiknya: selalu file lokal, dan file Drive (kalau masih
     * ada) dipakai sebagai usaha terbaik — kegagalan Drive tidak boleh
     * memblokir penghapusan arsip lokal.
     */
    public function hapusBerkasFisik(): void
    {
        if (filled($this->path)) {
            Storage::disk($this->disk ?? 'arsip')->delete($this->path);
        }

        if (filled($this->google_drive_file_id)) {
            try {
                app(GoogleDriveService::class)->delete($this->google_drive_file_id);
            } catch (\Throwable $e) {
                logger()->warning('Gagal hapus salinan Drive saat menghapus lampiran', [
                    'lampiran_id' => $this->id,
                    'google_drive_file_id' => $this->google_drive_file_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
