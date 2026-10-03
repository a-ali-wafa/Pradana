<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Membuat akun admin pertama sesudah `php artisan migrate --seed` (I3).
 *
 * Seeder produksi sengaja TIDAK menanam akun lagi — email pribadi + PIN keras
 * tidak boleh ikut ke repository. Akun pertama harus dibuat orang yang memang
 * memegang kantor, di terminalnya sendiri, lewat perintah ini.
 *
 * Kalau semua admin sudah terkunci (lupa PIN semua), pakai `arsip:reset-pin`.
 */
class BuatAkunPertama extends Command
{
    protected $signature = 'arsip:akun-pertama
                            {email : Email untuk login}
                            {nama? : Nama lengkap}
                            {--pin= : PIN 8 digit (kalau kosong, ditanya aman di terminal)}';

    protected $description = 'Buat akun admin pertama (dipakai sekali saat pemasangan awal)';

    public function handle(): int
    {
        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            $this->error('Sudah ada akun admin. Tambahkan staf lewat menu Manajemen User setelah login.');

            return self::FAILURE;
        }

        $nama = $this->argument('nama') ?? $this->ask('Nama lengkap');

        // `Illuminate\Console\Command` TIDAK punya helper validate() seperti
        // FormRequest — jadi pakai Validator langsung, dan kesalahannya dicetak
        // ke terminal.
        $pesanan = Validator::make([
            'email' => $this->argument('email'),
            'nama' => $nama,
            'pin' => $this->ambilPin(),
        ], [
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'nama' => ['required', 'string', 'max:150'],
            'pin' => ['required', 'digits:8'],
        ], [
            'email.unique' => 'Email itu sudah dipakai akun lain.',
            'pin.digits' => 'PIN harus 8 digit angka.',
        ]);

        if ($pesanan->fails()) {
            foreach ($pesanan->errors()->all() as $kesalahan) {
                $this->error($kesalahan);
            }

            return self::FAILURE;
        }

        $admin = User::create([
            'nama_lengkap' => $nama,
            'email' => $this->argument('email'),
            'pin' => Hash::make($pesanan->validated()['pin']),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->info("Akun admin #{$admin->id} ({$admin->email}) dibuat. Silakan login lewat halaman masuk.");

        return self::SUCCESS;
    }

    private function ambilPin(): string
    {
        return (string) ($this->option('pin') ?? $this->secret('PIN 8 digit'));
    }
}
