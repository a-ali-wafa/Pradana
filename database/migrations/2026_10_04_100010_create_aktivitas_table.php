<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ditulis oleh 11 Model Observer, sinkron tanpa queue. Read-only dari layar
 * (halaman /aktivitas, admin only); tidak ada tombol
 * hapus di UI. `arsip:bersihkan-log` membuang yang >2 tahun TAPI tidak pernah
 * menyentuh jejak pemusnahan, karena Berita Acara bisa diminta bertahun-tahun
 * kemudian.
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
