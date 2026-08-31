<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KlasifikasiPrimer extends Model
{
    protected $table = 'klasifikasi_primer';

    protected $fillable = ['kode', 'nama'];

    public function sekunder()
    {
        return $this->hasMany(KlasifikasiSekunder::class);
    }

    public function suratMasuk()
    {
        return $this->hasMany(SuratMasuk::class);
    }

    public function suratKeluar()
    {
        return $this->hasMany(SuratKeluar::class);
    }
}
