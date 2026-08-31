<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draf_konten_surat_keluar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_keluar_id')->unique()->constrained('surat_keluar')->cascadeOnDelete();
            $table->string('lampiran', 100)->nullable();
            $table->string('alamat_tujuan')->nullable();
            $table->string('salam_pembuka', 100)->nullable();
            $table->longText('isi_surat')->nullable();
            $table->string('salam_penutup', 100)->nullable();
            $table->string('atas_nama', 150)->nullable();
            $table->string('jabatan_penandatangan', 100)->nullable();
            $table->string('nip_nik', 50)->nullable();
            $table->text('tembusan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draf_konten_surat_keluar');
    }
};
