<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PemusnahanArsipItem extends Model
{
    protected $table = 'pemusnahan_arsip_item';

    protected $fillable = [
        'pemusnahan_arsip_id', 'arsipable_type', 'arsipable_id',
        'nomor_surat_snapshot', 'perihal_snapshot',
        'tanggal_surat_snapshot', 'jumlah_lampiran_snapshot',
    ];

    protected $casts = [
        'tanggal_surat_snapshot' => 'date',
    ];

    /** @return BelongsTo<PemusnahanArsip, $this> */
    public function pemusnahan(): BelongsTo
    {
        // Nama FK eksplisit: tanpa ini Eloquent menebak `pemusnahan_id` dari nama
        // method, padahal kolom aslinya `pemusnahan_arsip_id`.
        return $this->belongsTo(PemusnahanArsip::class, 'pemusnahan_arsip_id');
    }

    /**
     * Arsip aslinya (surat masuk/keluar). Setelah dimusnahkan relasi ini
     * mengembalikan null — itu sebabnya data penting disimpan sebagai snapshot.
     */
    public function arsipable(): MorphTo
    {
        return $this->morphTo();
    }

    public function adalahSuratMasuk(): bool
    {
        return $this->arsipable_type === SuratMasuk::class;
    }
}
