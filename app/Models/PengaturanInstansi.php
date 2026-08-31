<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanInstansi extends Model
{
    protected $table = 'pengaturan_instansi';

    protected $fillable = [
        'nama_instansi',
        'jenis_instansi',
        'alamat_instansi',
        'no_telp',
        'email',
        'logo_path',
    ];
}
