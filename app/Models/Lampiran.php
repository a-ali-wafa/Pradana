<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lampiran extends Model
{
    protected $table = 'lampiran';

    protected $fillable = [
        'lampiranable_id', 'lampiranable_type',
        'google_drive_file_id', 'google_drive_folder_id',
        'nama_file', 'mime_type', 'ukuran', 'diunggah_oleh',
    ];

    public function lampiranable()
    {
        return $this->morphTo();
    }

    public function pengunggah()
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function isEligibleForDeletion(): bool
    {
        $surat = $this->lampiranable;
        if (! $surat) {
            return false;
        }

        $tanggalAcuan = $surat instanceof SuratMasuk ? $surat->tanggal_diterima : $surat->tanggal_surat;
        return \Carbon\Carbon::parse($tanggalAcuan)->lt(now()->subYears(5));
    }
}
