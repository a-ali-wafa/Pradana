<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratMasuk extends Model
{
    // L-05: surat tidak pernah hilang benar-benar dari sistem; yang ada hanya
    // disembunyikan dari daftar (pulihkan lewat admin) atau dimusnahkan lewat
    // alur pemusnahan + Berita Acara.
    use SoftDeletes;

    protected $table = 'surat_masuk';

    protected $fillable = [
        'pengirim', 'jabatan_pengirim', 'instansi_pengirim',
        'klasifikasi_primer_id', 'klasifikasi_sekunder_id', 'klasifikasi_tersier_id',
        'sifat', 'nomor_surat', 'kota_asal', 'provinsi_asal',
        'tanggal_surat', 'tanggal_diterima', 'perihal', 'ringkasan',
        'status_berkas', 'status_arsip', 'lokasi_fisik', 'user_id',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
        'tanggal_diterima' => 'date',
    ];

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function primer(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiPrimer::class, 'klasifikasi_primer_id');
    }

    public function sekunder(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiSekunder::class, 'klasifikasi_sekunder_id');
    }

    public function tersier(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiTersier::class, 'klasifikasi_tersier_id');
    }

    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }
}
