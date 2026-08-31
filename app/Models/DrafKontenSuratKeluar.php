<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrafKontenSuratKeluar extends Model
{
    protected $table = 'draf_konten_surat_keluar';

    protected $fillable = [
        'surat_keluar_id', 'lampiran', 'alamat_tujuan',
        'salam_pembuka', 'isi_surat', 'salam_penutup',
        'atas_nama', 'jabatan_penandatangan', 'nip_nik', 'tembusan',
    ];

    public function suratKeluar()
    {
        return $this->belongsTo(SuratKeluar::class);
    }
}
