<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQUASH S11. File arsip disimpan di **disk lokal** `arsip`
 * (storage/app/private/arsip, di luar public/) — L-01. Kolom Drive ditinggalkan
 * sebagai pencatat cadangan: `google_drive_file_id` jadi nullable dan cuma terisi
 * kalau `arsip:sinkron-ke-drive` sudah pernah jalan.
 * `hash_file` (sha256 isi) dipakai mendeteksi berkas yang sama diunggah dua kali
 * ke surat yang sama (I1) — bukan dedup fisik: tiap lampiran tetap punya filenya
 * sendiri, jadi menghapus satu tidak merusak yang lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lampiran', function (Blueprint $table) {
            $table->id();
            $table->morphs('lampiranable'); // lampiranable_type + lampiranable_id

            $table->string('disk', 50)->nullable();
            $table->string('path', 512)->nullable();
            $table->char('hash_file', 64)->nullable();

            $table->string('google_drive_file_id')->nullable()->unique();
            $table->string('google_drive_folder_id')->nullable();

            $table->string('nama_file', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('ukuran')->nullable(); // bytes

            $table->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('hash_file');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran');
    }
};
