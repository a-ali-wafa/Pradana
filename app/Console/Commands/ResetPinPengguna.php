<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Ganti PIN dari terminal — jalan keluar kalau satu-satunya admin lupa PINnya
 * (L-12 hanya menyediakan reset lewat UI admin, yang justru tidak bisa dibuka
 * kalau tidak ada yang ingat PIN admin).
 *
 * Pemakaian: `php artisan arsip:reset-pin nama@email.co`
 */
class ResetPinPengguna extends Command
{
    protected $signature = 'arsip:reset-pin
                            {email : Email akun yang mau direset}
                            {--pin= : PIN 8 digit (kalau kosong, ditanya aman di terminal)}';

    protected $description = 'Reset PIN akun dari terminal (untuk kondisi admin terkunci)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Tidak ada akun dengan email itu. Cek daftar: php artisan tinker');

            return self::FAILURE;
        }

        $pin = (string) ($this->option('pin') ?? $this->secret("PIN baru untuk {$user->nama_lengkap} (8 digit)"));

        if (! preg_match('/^\d{8}$/', $pin)) {
            $this->error('PIN harus tepat 8 digit angka. Tidak ada yang diubah.');

            return self::FAILURE;
        }

        $user->update(['pin' => Hash::make($pin)]);

        // Soft delete tidak menghalangi reset: kalau akun nonaktif, perlu
        // diaktifkan dulu lewat UI admin supaya bisa login.
        if ($user->trashed()) {
            $this->warn("PIN {$user->email} diganti, tapi akun ini masih dihapus (nonaktif) — pulihkan lewat menu Manajemen User.");
        } else {
            $this->info("PIN {$user->email} sudah diganti.");
        }

        return self::SUCCESS;
    }
}
