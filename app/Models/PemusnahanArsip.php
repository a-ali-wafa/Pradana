<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PemusnahanArsip extends Model
{
    protected $table = 'pemusnahan_arsip';

    protected $fillable = [
        'nomor_berita_acara', 'alasan', 'status',
        'diajukan_oleh', 'diproses_oleh', 'diproses_pada',
        'catatan_admin', 'tanggal_pelaksanaan',
    ];

    protected $casts = [
        'diproses_pada' => 'datetime',
        'tanggal_pelaksanaan' => 'date',
    ];

    /** @return HasMany<PemusnahanArsipItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PemusnahanArsipItem::class, 'pemusnahan_arsip_id');
    }

    /** @return BelongsTo<User, $this> */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    /** @return BelongsTo<User, $this> */
    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function sudahDiproses(): bool
    {
        return $this->status !== 'menunggu';
    }
}
