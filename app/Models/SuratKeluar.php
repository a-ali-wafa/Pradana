<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuratKeluar extends Model
{
    protected $table = 'surat_keluar';

    protected $fillable = [
        'penerima', 'jabatan_penerima', 'instansi_penerima',
        'klasifikasi_primer_id', 'klasifikasi_sekunder_id', 'klasifikasi_tersier_id',
        'sifat', 'nomor_surat', 'kota_tujuan', 'provinsi_tujuan',
        'tanggal_surat', 'perihal', 'ringkasan',
        'status_berkas', 'status_arsip', 'lokasi_fisik', 'user_id',
    ];
    // Catatan: 'file_path' SENGAJA tidak ada di sini (lihat AGENTS.md 12.5/12.10) --
    // file fisik sekarang lewat relasi lampiran() di bawah, bukan kolom langsung.

    protected $casts = [
        'tanggal_surat' => 'date',
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

    public function drafKonten()
    {
        return $this->hasOne(DrafKontenSuratKeluar::class);
    }

    public function lampiran()
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }
}
