<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratMasuk extends Model
{
    protected $table = 'surat_masuk';

    protected $fillable = [
        'pengirim', 'jabatan_pengirim', 'instansi_pengirim',
        'klasifikasi_primer_id', 'klasifikasi_sekunder_id', 'klasifikasi_tersier_id',
        'sifat', 'nomor_surat', 'kota_asal', 'provinsi_asal',
        'tanggal_surat', 'tanggal_diterima', 'perihal', 'ringkasan',
        'status_berkas', 'status_arsip', 'lokasi_fisik', 'user_id',
    ];
    // Catatan: 'file_path' SENGAJA tidak ada di sini (lihat AGENTS.md 12.5/12.10) --
    // file fisik sekarang lewat relasi lampiran() di bawah, bukan kolom langsung.

    protected $casts = [
        'tanggal_surat' => 'date',
        'tanggal_diterima' => 'date',
    ];

    public function petugas()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function primer()
    {
        return $this->belongsTo(KlasifikasiPrimer::class, 'klasifikasi_primer_id');
    }

    public function sekunder()
    {
        return $this->belongsTo(KlasifikasiSekunder::class, 'klasifikasi_sekunder_id');
    }

    public function tersier()
    {
        return $this->belongsTo(KlasifikasiTersier::class, 'klasifikasi_tersier_id');
    }

    public function lampiran()
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }
}
