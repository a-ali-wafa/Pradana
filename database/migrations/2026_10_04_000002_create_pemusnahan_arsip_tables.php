<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan L-06 (E3): pemusnahan arsip SURAT memakai pola yang sama dengan
 * pengajuan hapus lampiran — staf mengajukan, admin menyetujui — tapi dengan
 * Berita Acara sebagai dokumen resmi. Hanya jalur ini yang boleh menghapus
 * berkas secara permanen (L-05 menutup penghancuran langsung).
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

            // Snapshot nomor & perihal: setelah arsip dimusnahkan (force delete),
            // Berita Acara tetap harus bisa dibaca isinya.
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
