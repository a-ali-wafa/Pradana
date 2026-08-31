<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_masuk', function (Blueprint $table) {
            $table->id();
            $table->string('pengirim', 150);
            $table->string('jabatan_pengirim', 100)->nullable();
            $table->string('instansi_pengirim', 150)->nullable();

            $table->foreignId('klasifikasi_primer_id')->constrained('klasifikasi_primer')->restrictOnDelete();
            $table->foreignId('klasifikasi_sekunder_id')->nullable()->constrained('klasifikasi_sekunder')->nullOnDelete();
            $table->foreignId('klasifikasi_tersier_id')->nullable()->constrained('klasifikasi_tersier')->nullOnDelete();

            $table->enum('sifat', ['mendesak', 'penting', 'rahasia', 'biasa'])->default('biasa');
            $table->string('nomor_surat', 100);
            $table->string('kota_asal', 100)->nullable();
            $table->string('provinsi_asal', 100)->nullable();
            $table->date('tanggal_surat');
            $table->date('tanggal_diterima');
            $table->string('perihal', 255);
            $table->text('ringkasan')->nullable();

            $table->enum('status_berkas', ['asli', 'salinan'])->default('asli');
            $table->enum('status_arsip', ['aktif', 'inaktif'])->default('aktif');
            $table->string('lokasi_fisik', 150)->nullable();
            $table->string('file_path')->nullable();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('nomor_surat');
            $table->index('tanggal_surat');
            $table->index('status_arsip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_masuk');
    }
};
