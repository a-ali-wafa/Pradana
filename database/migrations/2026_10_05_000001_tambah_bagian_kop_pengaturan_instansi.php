<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris kop surat versi 5 Okt 2026 (permintaan user: PDF "sesuaikan dengan standar
 * perkantoran desa umumnya").
 *
 * Kop naskah dinas desa yang benar bertingkat tiga — Kabupaten → Kecamatan → Desa —
 * lihat Permendagri 1/2023 tentang Tata Naskah Dinas dan contoh kop pemerintah desa
 * (mis.ajibarangkec.banyumaskab.go.id). Skema lama cuma punya `nama_instansi` satu
 * baris, jadi dua tingkat di atasnya tidak pernah bisa ditulis; alamat juga belum
 * punya kode pos padahal itu bagian standar baris alamat kop.
 *
 * Semuanya NULLABLE dan additive: kantor yang mau kop satu baris ("PRADANA KEC. X")
 * cukup mengosongkan kolomnya, dan template PDF mencetak baris yang terisi saja.
 * Ini migration BARU setelah squash S11 (bukan mengubah file 2026_10_04_100002) —
 * jadi database yang sudah ada tinggal `php artisan migrate`, tidak perlu
 * `migrate:fresh` dan data user/instansi tidak hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_instansi', function (Blueprint $table) {
            $table->string('nama_kabupaten', 100)->nullable()->after('nama_instansi');
            $table->string('nama_kecamatan', 100)->nullable()->after('nama_kabupaten');
            $table->string('kode_pos', 10)->nullable()->after('alamat_instansi');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_instansi', function (Blueprint $table) {
            $table->dropColumn(['nama_kabupaten', 'nama_kecamatan', 'kode_pos']);
        });
    }
};
