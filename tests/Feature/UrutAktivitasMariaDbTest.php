<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\MariaDbHarness;

/**
 * Bukti bahwa index `(created_at, id)` di tabel `aktivitas` SUNGGUHAN dipakai
 * optimizer MariaDB — bukan sekadar "migration berhasil".
 *
 * Pola yang sama dipakai `CariArsipMariaDbTest` (L-24 / Q1=b): bentuk yang cuma
 * ada di engine produksi diuji di engine itu, dan DI-SKIP dengan pesan kalau
 * XAMPP mati.
 *
 * Titik balik diukur (probe 9 Okt, LIMIT 30, ANALYZE sebelum setiap baca):
 *   1.000 baris -> key=NULL filesort 2,34 ms   (filesort memang lebih murah!)
 *   5.000 baris -> key=NULL filesort 4,16 ms
 *   8.000 baris -> key=indeks terurut, 30 baris dibaca, 0,97 ms
 *  30.000 baris -> key=indeks terurut          1,31 ms
 * Jadi tes ini menanam DI ATAS titik balik (12.000). Di bawah 8.000 baris office
 * memang tidak butuh index ini dan MariaDB benar memilih filesort — itu bukan
 * kegagalan, itu sebabnya angka tanamannya 12.000 dan bukan 500.
 */
class UrutAktivitasMariaDbTest extends MariaDbHarness
{
    protected const PENANDA = 'MIX';

    private const NAMA_INDEX = 'aktivitas_created_at_id_index';

    private const BARIS = 12000;

    protected function tearDown(): void
    {
        // Guard dari MariaDbHarness — tanpa ini, "MariaDB mati" menjadi FAILED
        // `no such table: aktivitas` di SQLite alih-alih skip yang dimaksud.
        if ($this->engineSiap) {
            DB::table('aktivitas')->where('aksi', 'like', '%'.static::PENANDA.'%')->delete();
        }

        parent::tearDown();
    }

    /** Baris log sintetis tersebar 2 tahun; kolom `aksi` membawa PENANDA. */
    private function tanam(): void
    {
        $batch = [];

        for ($i = 0; $i < self::BARIS; $i++) {
            $waktu = now()->subSeconds(random_int(0, 730 * 86400))->format('Y-m-d H:i:s');
            $batch[] = [
                'user_id' => $this->userId,
                'aksi' => static::PENANDA.' mengubah surat masuk '.$i,
                'subjek_type' => null,
                'subjek_id' => null,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ];

            if (count($batch) === 1000) {
                DB::table('aktivitas')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('aktivitas')->insert($batch);
        }

        DB::statement('ANALYZE TABLE aktivitas');
    }

    /**
     * Rencana EXPLAIN untuk satu query.
     *
     * @return array<string, mixed>
     */
    private function rencana(string $sql, array $binding = []): array
    {
        $plan = DB::select('EXPLAIN '.$sql, $binding);

        $this->assertNotEmpty($plan, 'EXPLAIN tidak mengembalikan baris — query-nya tidak jalan?');

        return (array) $plan[0];
    }

    public function test_index_urut_dipakai_oleh_halaman_log_yang_paling_sering_dibuka(): void
    {
        $this->tanam();

        $this->assertSame(self::BARIS, DB::table('aktivitas')->where('aksi', 'like', '%'.static::PENANDA.'%')->count());

        // 1) Persis bentuk AktivitasController::index(): urutan stabil + paginate 30.
        $plan = $this->rencana('SELECT * FROM aktivitas ORDER BY created_at DESC, id DESC LIMIT 30');

        $this->assertSame(
            self::NAMA_INDEX,
            $plan['key'],
            'Ordering halaman log tidak memakai index (key='.var_export($plan['key'], true)
                .', extra="'.($plan['Extra'] ?? '').'"). Yang harus dicek duluan kalau ini gagal: '
                .'urutan kolom index — (id, created_at) tidak akan terpakai, dan satu kolom '
                .'created_at saja juga tidak cukup untuk ORDER BY dua kolom.'
        );

        $this->assertStringNotContainsString(
            'filesort',
            strtolower((string) ($plan['Extra'] ?? '')),
            'Masih ada filesort: index tidak mengembalikan baris dalam urutan yang diminta. Extra="'
                .($plan['Extra'] ?? '').'"'
        );

        $this->assertLessThan(
            1000,
            (int) $plan['rows'],
            'Optimizer menaksir membaca sebagian besar tabel, bukan 30 baris pertama (rows='
                .$plan['rows'].').'
        );

        // 2) Bentuk berfilter periode (RentangTanggal::terapkan di halaman log).
        $terfilter = $this->rencana(
            'SELECT * FROM aktivitas WHERE created_at >= ? ORDER BY created_at DESC, id DESC LIMIT 30',
            [now()->startOfYear()->format('Y-m-d H:i:s')]
        );

        $this->assertSame(self::NAMA_INDEX, $terfilter['key'], 'Filter periode tidak lewat index urut.');
        $this->assertContains($terfilter['type'], ['range', 'index'], 'Tipe akses tidak memakai index: '.$terfilter['type']);

        // 3) arsip:bersihkan-log — yang ditugaskan hanya "index ini DIPERTIMBANGKAN",
        //    bukan pasti dipakai: ORDER BY id bisa membuat optimizer memilih PRIMARY,
        //    dan itu keputusan biaya yang sah, bukan regresi.
        $bersih = $this->rencana(
            'SELECT id FROM aktivitas WHERE created_at < ? ORDER BY id LIMIT 500',
            [now()->subYears(2)->format('Y-m-d H:i:s')]
        );

        $this->assertStringContainsString(
            self::NAMA_INDEX,
            strtolower((string) ($bersih['possible_keys'] ?? '')),
            'Index urut tidak masuk kandidat untuk penyaringan created_at. possible_keys='
                .var_export($bersih['possible_keys'] ?? null, true)
        );
    }
}
