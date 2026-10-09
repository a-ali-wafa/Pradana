<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrafKontenSuratKeluar extends Model
{
    protected $table = 'draf_konten_surat_keluar';

    // `lampiran` (notasi teks "1 Berkas") sudah dibuang dari skema: jumlah
    // lampiran di PDF dihitung otomatis dari tabel `lampiran` (P6).
    protected $fillable = [
        'surat_keluar_id', 'alamat_tujuan',
        'salam_pembuka', 'isi_surat', 'salam_penutup',
        'atas_nama', 'jabatan_penandatangan', 'nip_nik', 'tembusan',
    ];

    /** @return BelongsTo<SuratKeluar, $this> */
    public function suratKeluar(): BelongsTo
    {
        return $this->belongsTo(SuratKeluar::class);
    }
}
