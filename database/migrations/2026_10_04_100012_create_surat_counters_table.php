<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQUASH S11. Pencatat nomor surat keluar otomatis (L-20/D1/D2/D3). Barisnya
 * satu per (jenis surat, tahun) supaya `NomorSuratKeluarGenerator` bisa
 * `upsert` lalu `lockForUpdate()` **di dalam transaksi yang sama** — itu yang
 * menutup celah nomor kembar kalau dua staf menyimpan pada detik yang sama.
 * Tanpa tabel ini, penghitung harus membaca MAX(urutan) dari tabel surat, dan
 * itu bacaan snapshot biasa yang tidak saling mengunci.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_counters', function (Blueprint $table) {
            $table->id();
            $table->string('jenis_surat');
            $table->integer('tahun');
            $table->integer('current_value')->default(0);
            $table->timestamps();

            $table->unique(['jenis_surat', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_counters');
    }
};
