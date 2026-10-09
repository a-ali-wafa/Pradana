<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KlasifikasiTersier extends Model
{
    protected $table = 'klasifikasi_tersier';

    protected $fillable = ['klasifikasi_sekunder_id', 'kode', 'nama'];

    /** @return BelongsTo<KlasifikasiSekunder, $this> */
    public function sekunder(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiSekunder::class, 'klasifikasi_sekunder_id');
    }
}
