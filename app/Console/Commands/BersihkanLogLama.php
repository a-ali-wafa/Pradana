<?php

namespace App\Console\Commands;

use App\Models\Aktivitas;
use App\Models\PemusnahanArsip;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retensi log aktivitas (E6=b): catatan lebih dari 2 tahun boleh dibuang,
 * TAPI jejak pemusnahan arsip dikecualikan selamanya.
 *
 * Kenapa dikecualikan: Berita Acara pemusnahan bisa diminta diperiksa bertahun
 * tahun kemudian, dan bukti "siapa menyetujui pemusnahan ini, kapan" cuma ada
 * di tabel aktivitas — kalau lognya dibuang, Berita Acara kehilangan pendukungnya.
 *
 * Semua aksi yang menyangkut pemusnahan dikenali lewat `subjek_type`
 * (baris `pemusnahan_arsip` dan itemnya) plus teks aksinya untuk aksi
 * pemusnahan yang tercatat pada subjek surat (mis. "Memusnahkan surat masuk: ...").
 * Baris pemusnahan yang masih ada di database juga selalu dipertahankan.
 */
class BersihkanLogLama extends Command
{
    protected $signature = 'arsip:bersihkan-log
                            {--tahun=2 : Buang catatan yang lebih lama dari ini (tahun)}
                            {--dry-run : Hitung saja, jangan hapus}';

    protected $description = 'Buang log aktivitas lama, kecuali jejak pemusnahan arsip (E6)';

    public function handle(): int
    {
        $batas = now()->subYears((int) $this->option('tahun'));

        $subjekPemusnahan = [
            PemusnahanArsip::class,
            \App\Models\PemusnahanArsipItem::class,
        ];

        $terpilih = DB::table('aktivitas')
            ->where('created_at', '<', $batas)
            ->get(['id', 'aksi', 'subjek_type'])
            ->reject(function ($catatan) use ($subjekPemusnahan) {
                if (in_array($catatan->subjek_type, $subjekPemusnahan, true)) {
                    return true;
                }

                // Aksi pemusnahan yang tercatat pada subjek surat (bukan pada
                // baris pemusnahan) juga harus bertahan. Dicocokkan case-insensitive
                // karena teks aksinya ditulis manual di Observer ("Memusnahkan ...").
                $aksi = mb_strtolower($catatan->aksi);

                return str_contains($aksi, 'musnahkan')
                    || str_contains($aksi, 'pemusnahan')
                    || str_contains($aksi, 'berita acara');
            })
            ->pluck('id');

        if ($this->option('dry-run')) {
            $this->line("Batas: {$batas->toDateString()} — {$terpilih->count()} catatan akan dibuang, sisanya permanen (pemusnahan).");

            return self::SUCCESS;
        }

        // Chunk kecil: tabel aktivitas bisa berisi puluhan ribu baris, dan
        // DELETE ... WHERE id IN (…) raksasa mengunci tabel lebih lama dari
        // yang dibutuhkan.
        $terhapus = 0;

        foreach ($terpilih->chunk(500) as $batch) {
            $terhapus += Aktivitas::whereIn('id', $batch)->delete();
        }

        $this->info("{$terhapus} catatan log lama dibuang (batas {$batas->toDateString()}); jejak pemusnahan dipertahankan.");

        return self::SUCCESS;
    }
}
