<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KlasifikasiSekunder extends Model
{
    protected $table = 'klasifikasi_sekunder';

    /** Lihat alasan di KlasifikasiPrimer: `$with = ['tersier']` dibuang 9 Okt 2026. */
    protected $fillable = ['klasifikasi_primer_id', 'kode', 'nama'];

    /** @return BelongsTo<KlasifikasiPrimer, $this> */
    public function primer(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiPrimer::class, 'klasifikasi_primer_id');
    }

    /** @return HasMany<KlasifikasiTersier, $this> */
    public function tersier(): HasMany
    {
        return $this->hasMany(KlasifikasiTersier::class);
    }
}
