<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQUASH S11. Tabelnya single-row (diedit lewat Route::singleton, B4/L-26).
 * `gdrive_root_folder_id` dibuang: folder root arsip dibaca dari `.env`
 * (`GOOGLE_DRIVE_ROOT_FOLDER_ID`), bukan dari database, dan kolomnya tidak
 * pernah ditulis `PengaturanInstansiController` (tidak ada di $fillable).
 * Logo disimpan di disk lokal (`logo_path`), bukan Drive (C7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_instansi', function (Blueprint $table) {
            $table->id();
            $table->string('nama_instansi', 150);
            $table->string('jenis_instansi', 150)->nullable();
            $table->string('alamat_instansi', 255)->nullable();
            $table->string('no_telp', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('gdrive_folder_surat_masuk_id')->nullable();
            $table->string('gdrive_folder_surat_keluar_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_instansi');
    }
};
