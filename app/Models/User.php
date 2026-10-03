<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PEGAWAI = 'pegawai';

    protected $fillable = [
        'nama_lengkap',
        'email',
        'pin',
        'role',
    ];

    protected $hidden = [
        'pin',
    ];

    // `remember_token` dan `email_verified_at` sudah dibuang dari skema (L-11 +
    // squash S11): login tinggal email+PIN tanpa "ingat saya", dan tidak ada
    // verifikasi email karena kantor tanpa SMTP.

    // PIN dipakai sebagai kredensial login, menggantikan kolom "password" bawaan Laravel
    public function getAuthPassword()
    {
        return $this->pin;
    }

    public function suratMasuk(): HasMany
    {
        return $this->hasMany(SuratMasuk::class);
    }

    public function suratKeluar(): HasMany
    {
        return $this->hasMany(SuratKeluar::class);
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(Aktivitas::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * L-07: hanya 2 tingkat. `admin` adalah kepala desa/lurah dan punya hak
     * nyata (hapus user, approval pemusnahan, koreksi nomor, pengaturan);
     * `pegawai` adalah staf. Label dipakai bersama oleh form user, daftar
     * user, dan topbar supaya tidak ada dua tempat yang bisa tidak cocok.
     *
     * @return array<string, string> nilai enum => label untuk manusia
     */
    public static function peranTersedia(): array
    {
        return [
            self::ROLE_ADMIN => 'Admin (Kepala)',
            self::ROLE_PEGAWAI => 'Pegawai',
        ];
    }

    public function labelRole(): string
    {
        return self::peranTersedia()[$this->role] ?? ucfirst($this->role);
    }
}
