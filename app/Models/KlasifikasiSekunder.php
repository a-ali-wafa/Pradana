<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KlasifikasiSekunder extends Model
{
    protected $table = 'klasifikasi_sekunder';

    protected $with = ['tersier'];

    protected $fillable = ['klasifikasi_primer_id', 'kode', 'nama'];

    public function primer()
    {
        return $this->belongsTo(KlasifikasiPrimer::class, 'klasifikasi_primer_id');
    }

    public function tersier()
    {
        return $this->hasMany(KlasifikasiTersier::class);
    }
}
