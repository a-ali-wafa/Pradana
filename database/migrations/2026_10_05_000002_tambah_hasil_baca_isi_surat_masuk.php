<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil baca otomatis isi lampiran surat masuk (permintaan user 5 Okt 2026).
 *
 * Kenapa SATU kolom teks + dua kolom catatan waktu, bukan menimpa `ringkasan`:
 * teks yang diambil mesin bisa salah (PDF hasil scan, tabel yang urutan selnya
 * acak, karakter yang hilang). Keputusan user jelas — "ditampilkan isinya di
 * dalam form untuk divalidasi" — jadi hasil mesin disimpan terpisah dan
 * `isi_terverifikasi_pada` menandai kapan manusia memeriksa & menyimpannya.
 * Ringkasan tetap kolom buatan orang; ia hanya boleh terisi dari hasil baca
 * lewat aksi eksplisit di form.
 *
 * Hanya `surat_masuk` yang ditambah (permintaan user menyebut surat masuk).
 * Surat keluar sudah punya tempat sendiri untuk isi (`draf.isi_surat`) dan
 * dibuat orang, bukan diterima.
 *
 * Additive + nullable: `php artisan migrate` cukup, data lama tidak tersentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_masuk', function (Blueprint $table) {
            $table->longText('isi_hasil_baca')->nullable()->after('ringkasan');
            $table->string('isi_dibaca_dari', 255)->nullable()->after('isi_hasil_baca');
            $table->timestamp('isi_dibaca_pada')->nullable()->after('isi_dibaca_dari');
            $table->timestamp('isi_terverifikasi_pada')->nullable()->after('isi_dibaca_pada');
        });
    }

    public function down(): void
    {
        Schema::table('surat_masuk', function (Blueprint $table) {
            $table->dropColumn(['isi_hasil_baca', 'isi_dibaca_dari', 'isi_dibaca_pada', 'isi_terverifikasi_pada']);
        });
    }
};
