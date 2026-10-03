<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQUASH S11. Ditulis oleh 11 Model Observer (sync, tanpa queue — L-02).
 * Read-only dari layar (halaman /aktivitas, admin only, L-22); tidak ada tombol
 * hapus di UI. `arsip:bersihkan-log` membuang yang >2 tahun TAPI tidak pernah
 * menyentuh jejak pemusnahan, karena Berita Acara bisa diminta bertahun-tahun
 * kemudian (E6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aktivitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('aksi', 255);
            $table->nullableMorphs('subjek');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aktivitas');
    }
};
