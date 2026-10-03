<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQUASH S11. Alur hapus lampiran: staf mengajukan, admin menyetujui, dan hanya
 * untuk surat yang sudah lewat retensi 5 tahun (L-05/E4). `lampiran_id`
 * **null on delete** (bukan cascade) supaya riwayat pengajuan tetap terbaca
 * setelah file-nya benar-benar hilang; nama file diselamatkan lebih dulu ke
 * `nama_file_snapshot` karena itu satu-satunya yang tersisa setelahnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_hapus_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lampiran_id')->nullable()->constrained('lampiran')->nullOnDelete();
            $table->string('nama_file_snapshot', 255);
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alasan')->nullable();
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu')->index();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_pada')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_hapus_lampiran');
    }
};
