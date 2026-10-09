<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 perapian (9 Okt 2026): satu index tambahan, dan hanya satu, karena
 * hanya inilah yang terbukti dari pengukuran — bukan dari daftar kandidat.
 *
 * Diukur di MariaDB 10.4 sungguhan (DB tanding `pradana_test`, 40.000 baris log
 * aktivitas + 8.000 surat, `ANALYZE TABLE` sebelum setiap perubahan supaya
 * rencana optimizer dibandingkan dengan statistik yang sama):
 *
 *   /aktivitas tanpa filter    22,5 ms -> 1,0 ms   (type=ALL + filesort 40k baris
 *                                                  -> type=index, baca 30 baris)
 *   /aktivitas filter periode  28,5 ms -> 1,4 ms
 *   arsip:bersihkan-log        23,8 ms -> 1,0 ms
 *
 * Dua hal yang tidak bisa ditebak dari skema dan sengaja dicatat di sini:
 *
 * 1. TABEL SURAT TIDAK PERLU INDEX BARU. `surat_masuk`/`surat_keluar` sudah punya
 *    index di `tanggal_surat`, `tanggal_diterima`, `status_arsip`, `deleted_at`
 *    (dibuat saat squash S11), dan setelah ANALYZE daftar surat memakai index
 *    `tanggal_diterima` secara terbalik: 1,8–2,2 ms. Composite coba
 *    `(deleted_at, tanggal_diterima)` → 1,93 ms vs baseline 2,16 ms: di dalam
 *    noise, jadi tidak dipakai. `(status_arsip, tanggal_surat)` memang dipilih
 *    optimizer untuk daftar kandidat pemusnahan (9,0 → 6,1 ms pada 8.000 surat),
 *    tapi itu satu layar admin dalam milidetik — tidak layak dibayar dengan index
 *    keempat di tabel yang paling sering ditulis.
 *
 * 2. Index `created_at` SAJA tidak cukup. Percobaan pertama memakai satu kolom
 *    dan halaman log tetap `type=ALL + filesort` (23,9 ms): urutan halaman log
 *    adalah `created_at DESC, id DESC`, dan MariaDB 10.4 tidak memakai suffix PK
 *    implisit di secondary index untuk memenuhi ORDER BY dua kolom itu. `id`
 *    harus ditulis eksplisit — makanya composite ini.
 *
 * Additive murni: tidak ada kolom yang berubah, `php artisan migrate` biasa cukup,
 * data kantor tidak tersentuh, `migrate:fresh` tidak diperlukan.
 */
return new class extends Migration
{
    private const NAMA_INDEX = 'aktivitas_created_at_id_index';

    public function up(): void
    {
        Schema::table('aktivitas', function (Blueprint $table) {
            $table->index(['created_at', 'id'], self::NAMA_INDEX);
        });
    }

    public function down(): void
    {
        // Nama index disebut eksplisit supaya tes bisa membuktikannya lewat
        // `SHOW INDEX` (MariaDB) maupun `PRAGMA index_list` (SQLite) tanpa
        // menebak penamaan default.
        Schema::table('aktivitas', function (Blueprint $table) {
            $table->dropIndex(self::NAMA_INDEX);
        });
    }
};
