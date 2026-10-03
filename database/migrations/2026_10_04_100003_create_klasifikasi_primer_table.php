<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQUASH S11. Klasifikasi 3 level primer -> sekunder -> tersier (L-26/L-08:
 * mutasinya tetap admin-only, jawaban B3 "Lanjut"). `kode` primer jadi bagian
 * nomor surat keluar (D1/L-17), jadi tidak boleh berubah semaunya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klasifikasi_primer', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 5)->unique();
            $table->string('nama', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('klasifikasi_primer');
    }
};
