<?php

namespace Database\Seeders;

use App\Models\PengaturanInstansi;
use Illuminate\Database\Seeder;

/**
 * Seeder PRODUKSI. Isinya cuma satu baris: identitas instansi (dipakai kop
 * surat & halaman login) — tanpa akun, tanpa contoh klasifikasi.
 *
 * Alasan (keputusan I3, 3 Okt 2026): versi sebelumnya menanam akun admin
 * dengan email pribadi + PIN keras, dan itu ikut ter-deploy ke server kantor.
 * Kredensial pribadi tidak boleh jadi bagian skema aplikasi, dan kantor harus
 * menentukan sendiri akun pertamanya lewat `php artisan arsip:akun-pertama`
 * (perintah eksplisit yang meminta PIN di terminal), bukan mewarisi PIN yang
 * terbaca di riwayat GitHub.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        PengaturanInstansi::firstOrCreate([], [
            'nama_instansi' => '',
            'jenis_instansi' => '',
            'alamat_instansi' => '',
            'no_telp' => '',
            'email' => '',
        ]);
    }
}
