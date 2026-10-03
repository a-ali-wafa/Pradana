<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L-07: role dipangkas jadi 2 tingkat — `admin` (= kepala desa, berhak nyata)
 * dan `pegawai`. Pemetaan nilai lama: `kepala` → `admin`, `perangkat` → `pegawai`.
 *
 * Tiga langkah, dan urutannya tidak boleh ditukar. Enum MariaDB menolak nilai
 * di luar daftarnya, jadi:
 *   1) perluas enum dulu supaya 'pegawai' boleh ditulis (belum ada yang hilang);
 *   2) salin nilainya ke skema baru;
 *   3) persempit enum ke 2 nilai final — kalau langkah ini yang duluan, baris
 *      'perangkat' akan di-truncate jadi string kosong (sql_mode longgar) atau
 *      gagal (sql_mode strict). Percobaan pertama migration ini memang gagal
 *      di langkah 2 karena kesalahan urutan itu.
 *
 * File `create_users_table` lama TIDAK diubah: migration yang sudah pernah jalan
 * di suatu environment tidak diedit (aturan Bagian 2 AGENTS.md) — baru digabung
 * saat squash S11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'perangkat', 'kepala', 'pegawai'])
                ->default('perangkat')
                ->change();
        });

        DB::table('users')->where('role', 'kepala')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'perangkat')->update(['role' => 'pegawai']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'pegawai'])->default('pegawai')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'perangkat', 'kepala', 'pegawai'])
                ->default('perangkat')
                ->change();
        });

        DB::table('users')->where('role', 'pegawai')->update(['role' => 'perangkat']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'perangkat', 'kepala'])->default('perangkat')->change();
        });
    }
};
