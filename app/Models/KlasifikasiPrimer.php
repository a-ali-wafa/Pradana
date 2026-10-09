<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KlasifikasiPrimer extends Model
{
    protected $table = 'klasifikasi_primer';

    /**
     * SENGAJA tidak ada `$with = ['sekunder']` lagi (dibuang 9 Okt 2026).
     *
     * Eager load global itu membuat SETIAP instantiation KlasifikasiPrimer menarik
     * seluruh pohon klasifikasi: dropdown filter di daftar surat (butuh kode+nama
     * saja) ikut menarik sekunder + tersier, dan `KlasifikasiPrimer::all()` di
     * dashboard menambah 3 query penuh tiap load. Terukur di
     * JumlahQueryLayarTest: `select * from "klasifikasi_sekunder"` muncul 3x di
     * layar yang tidak menampilkan sekunder apa pun.
     *
     * Gantiannya: yang butuh pohon memuat sendiri secara eksplisit —
     * form surat sudah memakai `with('sekunder.tersier')`, halaman daftar
     * klasifikasi memakai `withCount()` + relasi induknya.
     */
    protected $fillable = ['kode', 'nama'];

    /** @return HasMany<KlasifikasiSekunder, $this> */
    public function sekunder(): HasMany
    {
        return $this->hasMany(KlasifikasiSekunder::class);
    }

    /** @return HasMany<SuratMasuk, $this> */
    public function suratMasuk(): HasMany
    {
        return $this->hasMany(SuratMasuk::class);
    }

    /** @return HasMany<SuratKeluar, $this> */
    public function suratKeluar(): HasMany
    {
        return $this->hasMany(SuratKeluar::class);
    }
}
