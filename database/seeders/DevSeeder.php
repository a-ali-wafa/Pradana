<?php

namespace Database\Seeders;

use App\Models\KlasifikasiPrimer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder DATA UJI — hanya untuk `APP_ENV=local` (dijaga di `run()`), dan
 * hanya boleh dijalankan di mesin pengembangan sendiri.
 *
 * Isinya: satu admin + satu pegawai dengan PIN dari `.env` (dev PIN dipakai
 * bersama, jadi tidak keras di kode) dan beberapa baris klasifikasi contoh
 * supaya form surat bisa dicoba. Klasifikasi contoh ini BUKAN daftar resmi —
 * D4 masih dibuka (user: "asumsikan dulu, nanti diputuskan saat presentasi"),
 * jadi kantor menggantinya sendiri lewat menu Klasifikasi.
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->error('DevSeeder hanya untuk APP_ENV=local — dibatalkan.');

            return;
        }

        $pin = (string) env('DEV_PIN', '12345678');

        foreach ([
            ['Abdullah Ali Wafa', 'dev-admin@example.test', 'admin'],
            ['Staf Contoh', 'dev-pegawai@example.test', 'pegawai'],
        ] as [$nama, $email, $role]) {
            User::updateOrCreate(['email' => $email], [
                'nama_lengkap' => $nama,
                'pin' => Hash::make($pin),
                'role' => $role,
            ]);
        }

        $keuangan = KlasifikasiPrimer::firstOrCreate(['kode' => '01'], ['nama' => 'Keuangan']);
        $anggaran = $keuangan->sekunder()->firstOrCreate(['kode' => '01'], ['nama' => 'Anggaran']);
        $anggaran->tersier()->firstOrCreate(['kode' => '01'], ['nama' => 'Rutin']);

        $kepegawaian = KlasifikasiPrimer::firstOrCreate(['kode' => '02'], ['nama' => 'Kepegawaian']);
        $mutasi = $kepegawaian->sekunder()->firstOrCreate(['kode' => '01'], ['nama' => 'Mutasi']);
        $mutasi->tersier()->firstOrCreate(['kode' => '01'], ['nama' => 'PNS']);
    }
}
