<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQUASH S11. Modul pemusnahan arsip (L-06): pengajuan -> approval admin ->
 * `forceDelete()` baris surat + file fisiknya, lalu Berita Acara PDF
 * (`BA-003/X/2026`). Syarat diajukan: umur >5 tahun, sudah inaktif, dan belum
 * ada pengajuan menunggu (divalidasi ulang di server, bukan cuma di form).
 *
 * `pemusnahan_arsip_item` SENGAJA tidak memakai foreign key ke surat: baris
 * suratnya memang akan dihapus permanen, jadi identitasnya disimpan sebagai
 * snapshot pada saat pengajuan. Relasi ke arsipnya polymorphic TANPA constraint
 * supaya riwayat tetap terbaca selamanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemusnahan_arsip', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_berita_acara', 100)->nullable();
            $table->text('alasan')->nullable();
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu')->index();
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_pada')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->date('tanggal_pelaksanaan')->nullable();
            $table->timestamps();
        });

        Schema::create('pemusnahan_arsip_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pemusnahan_arsip_id')->constrained('pemusnahan_arsip')->cascadeOnDelete();

            $table->string('arsipable_type', 120);
            $table->unsignedBigInteger('arsipable_id');

            $table->string('nomor_surat_snapshot', 100)->nullable();
            $table->string('perihal_snapshot', 255)->nullable();
            $table->date('tanggal_surat_snapshot')->nullable();
            $table->unsignedInteger('jumlah_lampiran_snapshot')->default(0);

            $table->index(['arsipable_type', 'arsipable_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemusnahan_arsip_item');
        Schema::dropIfExists('pemusnahan_arsip');
    }
};
