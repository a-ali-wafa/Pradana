<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KlasifikasiSekunder extends Model
{
    protected $table = 'klasifikasi_sekunder';

    protected $with = ['tersier'];

    protected $fillable = ['klasifikasi_primer_id', 'kode', 'nama'];

    public function primer(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiPrimer::class, 'klasifikasi_primer_id');
    }

    public function tersier(): HasMany
    {
        return $this->hasMany(KlasifikasiTersier::class);
    }
}
