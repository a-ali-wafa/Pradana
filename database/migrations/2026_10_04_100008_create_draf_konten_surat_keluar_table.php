<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQUASH S11. Kolom `lampiran` (string 100) dibuang: isinya notasi teks formal
 * surat ("1 Berkas") yang sekarang dihitung OTOMATIS dari jumlah baris tabel
 * `lampiran` (P6=a). History 2026_09_04_174904 sebenarnya berniat membuang
 * kolom ini tapi salah sasaran — di-ALTER ke tabel surat_masuk/surat_keluar,
 * sehingga kolomnya tetap ada sampai sekarang. Tidak ada kode yang membacanya
 * lagi sejak P6, jadi hilangnya tidak mengubah perilaku apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draf_konten_surat_keluar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_keluar_id')->unique()->constrained('surat_keluar')->cascadeOnDelete();
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
