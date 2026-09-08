<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aktivitas extends Model
{
    protected $table = 'aktivitas';

    protected $fillable = ['user_id', 'aksi', 'subjek_type', 'subjek_id'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function subjek()
    {
        return $this->morphTo();
    }
}
