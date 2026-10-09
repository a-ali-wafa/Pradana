<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Aktivitas extends Model
{
    protected $table = 'aktivitas';

    protected $fillable = ['user_id', 'aksi', 'subjek_type', 'subjek_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function subjek(): MorphTo
    {
        return $this->morphTo();
    }
}
