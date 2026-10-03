<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KlasifikasiPrimer extends Model
{
    protected $table = 'klasifikasi_primer';

    protected $with = ['sekunder'];

    protected $fillable = ['kode', 'nama'];

    public function sekunder(): HasMany
    {
        return $this->hasMany(KlasifikasiSekunder::class);
    }

    public function suratMasuk(): HasMany
    {
        return $this->hasMany(SuratMasuk::class);
    }

    public function suratKeluar(): HasMany
    {
        return $this->hasMany(SuratKeluar::class);
    }
}
