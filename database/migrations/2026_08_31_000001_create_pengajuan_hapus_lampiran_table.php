<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel BARU (bukan bagian skema awal 10 tabel di AGENTS.md Bagian 5) —
 * dibuat 31 Agu 2026 sesuai keputusan user (lihat AGENTS.md 12.17):
 * hapus lampiran HANYA lewat alur pengajuan (staf) + persetujuan (admin),
 * dan hanya untuk lampiran pada surat yang sudah berumur > 5 tahun (E1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_hapus_lampiran', function (Blueprint $table) {
            $table->id();

            // nullOnDelete (BUKAN cascade): riwayat pengajuan harus tetap ada
            // untuk audit meskipun lampiran aslinya sudah terhapus setelah disetujui.
            $table->foreignId('lampiran_id')->nullable()->constrained('lampiran')->nullOnDelete();

            // Snapshot nama file saat pengajuan dibuat -- supaya riwayat tetap
            // terbaca jelas walau lampiran_id di atas sudah null (lampiran terhapus).
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
