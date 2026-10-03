<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQUASH S11. `file_path` (sisa satu-file-per-surat jaman Apps Script) dibuang:
 * lampiran sudah pindah ke tabel `lampiran` (polymorphic, L-01).
 * `deleted_at` = tempat sampah admin (L-05); hapus permanen hanya lewat alur
 * Pemusnahan Arsip (L-06) yang meninggalkan Berita Acara.
 * `nomor_surat` sengaja TIDAK unique di sini: satu surat bisa dicatat lebih dari
 * sekali kalau dikirim ke beberapa instansi (D9=a).
 */
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

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('nomor_surat');
            $table->index('tanggal_surat');
            $table->index('tanggal_diterima');
            $table->index('status_arsip');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surat_masuk');
    }
};
