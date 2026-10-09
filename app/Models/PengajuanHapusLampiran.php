<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** @return BelongsTo<Lampiran, $this> */
    public function lampiran(): BelongsTo
    {
        return $this->belongsTo(Lampiran::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
