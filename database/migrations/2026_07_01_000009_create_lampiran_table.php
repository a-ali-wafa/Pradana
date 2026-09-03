<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lampiran', function (Blueprint $table) {
            $table->id();

            // Relasi polymorphic: satu lampiran milik satu surat_masuk ATAU satu surat_keluar
            $table->morphs('lampiranable'); // lampiranable_id, lampiranable_type

            $table->string('google_drive_file_id')->unique();
            $table->string('google_drive_folder_id')->nullable();
            $table->string('nama_file', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('ukuran')->nullable(); // bytes

            $table->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran');
    }
};
