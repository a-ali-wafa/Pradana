<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KlasifikasiTersier extends Model
{
    protected $table = 'klasifikasi_tersier';

    protected $fillable = ['klasifikasi_sekunder_id', 'kode', 'nama'];

    public function sekunder()
    {
        return $this->belongsTo(KlasifikasiSekunder::class, 'klasifikasi_sekunder_id');
    }
}
