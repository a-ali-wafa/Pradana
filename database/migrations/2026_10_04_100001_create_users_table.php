<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQUASH S11 (4 Okt 2026) — semua migration lama (20 file, termasuk ALTER dan
 * tabel peninggalan queue/Sanctum) diringkas jadi satu set bersih. Skema di
 * bawah adalah hasil akhir yang sudah dipakai berjalan, BUKAN tebakan.
 *
 * Yang sengaja TIDAK ada di tabel ini:
 * - `remember_token` — login tinggal email+PIN tanpa "ingat saya" (L-11).
 * - `email_verified_at` — tidak ada verifikasi email; kantor tanpa SMTP (A4/L-12).
 * - `role` enum hanya 2 nilai (L-07): `admin` = kepala dengan hak nyata,
 *   `pegawai` = staf. Kalau kantor mau memisahkan "kepala" lagi, itu keputusan
 *   baru — jangan diam-diam mengembalikan enum 3 nilai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap', 150);
            $table->string('email', 150)->unique();
            $table->string('pin'); // disimpan ter-hash lewat Hash::make()
            $table->enum('role', ['admin', 'pegawai'])->default('pegawai');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
