<?php

namespace Database\Seeders;

use App\Models\KlasifikasiPrimer;
use App\Models\PengaturanInstansi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'nama_lengkap' => 'Abdullah Ali Wafa',
            'email' => 'aliwafa3575@gmail.com',
            'pin' => Hash::make('280306'),
            'role' => 'admin',
        ]);

        PengaturanInstansi::create([
            'nama_instansi' => 'PEMERINTAH DESA UREK-UREK',
            'jenis_instansi' => 'KANTOR DESA',
            'alamat_instansi' => 'Desa Urek-Urek, Gondanglegi, Kabupaten Malang',
            'no_telp' => '085730177635',
            'email' => 'kominfo@malangkota.go.id',
        ]);

        $keuangan = KlasifikasiPrimer::create(['kode' => '01', 'nama' => 'Keuangan']);
        $anggaran = $keuangan->sekunder()->create(['kode' => '01', 'nama' => 'Anggaran']);
        $anggaran->tersier()->create(['kode' => '01', 'nama' => 'Rutin']);

        $kepegawaian = KlasifikasiPrimer::create(['kode' => '02', 'nama' => 'Kepegawaian']);
        $mutasi = $kepegawaian->sekunder()->create(['kode' => '01', 'nama' => 'Mutasi']);
        $mutasi->tersier()->create(['kode' => '01', 'nama' => 'PNS']);
    }
}
