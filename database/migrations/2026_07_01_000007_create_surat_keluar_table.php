<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_keluar', function (Blueprint $table) {
            $table->id();
            $table->string('penerima', 150);
            $table->string('jabatan_penerima', 100)->nullable();
            $table->string('instansi_penerima', 150)->nullable();

            $table->foreignId('klasifikasi_primer_id')->constrained('klasifikasi_primer')->restrictOnDelete();
            $table->foreignId('klasifikasi_sekunder_id')->nullable()->constrained('klasifikasi_sekunder')->nullOnDelete();
            $table->foreignId('klasifikasi_tersier_id')->nullable()->constrained('klasifikasi_tersier')->nullOnDelete();

            $table->enum('sifat', ['mendesak', 'penting', 'rahasia', 'biasa'])->default('biasa');
            $table->string('nomor_surat', 100)->unique();
            $table->string('kota_tujuan', 100)->nullable();
            $table->string('provinsi_tujuan', 100)->nullable();
            $table->date('tanggal_surat');
            $table->string('perihal', 255);
            $table->text('ringkasan')->nullable();

            $table->enum('status_berkas', ['asli', 'salinan'])->default('asli');
            $table->enum('status_arsip', ['aktif', 'inaktif'])->default('aktif');
            $table->string('lokasi_fisik', 150)->nullable();
            $table->string('file_path')->nullable();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('tanggal_surat');
            $table->index('status_arsip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_keluar');
    }
};
