<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'nama_lengkap',
        'email',
        'pin',
        'role',
    ];

    protected $hidden = [
        'pin',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // PIN dipakai sebagai kredensial login, menggantikan kolom "password" bawaan Laravel
    public function getAuthPassword()
    {
        return $this->pin;
    }

    public function suratMasuk()
    {
        return $this->hasMany(SuratMasuk::class);
    }

    public function suratKeluar()
    {
        return $this->hasMany(SuratKeluar::class);
    }

    public function aktivitas()
    {
        return $this->hasMany(Aktivitas::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
