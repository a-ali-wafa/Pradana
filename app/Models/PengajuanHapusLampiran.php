<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Alur pengajuan-persetujuan hapus lampiran (keputusan user, 31 Agu 2026,
 * lihat AGENTS.md 12.17): staf mengajukan hapus lampiran pada surat yang
 * berumur lebih dari 5 tahun (E1 [LOCKED]), admin menyetujui/menolak
 * sebelum file benar-benar dihapus dari DB & Google Drive.
 */
class PengajuanHapusLampiran extends Model
{
    protected $table = 'pengajuan_hapus_lampiran';

    protected $fillable = [
        'lampiran_id', 'nama_file_snapshot', 'diajukan_oleh', 'alasan',
        'status', 'diproses_oleh', 'diproses_pada', 'catatan_admin',
    ];

    protected $casts = [
        'diproses_pada' => 'datetime',
    ];

    public function lampiran()
    {
        return $this->belongsTo(Lampiran::class);
    }

    public function pengaju()
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function pemroses()
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
