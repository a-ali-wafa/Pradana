<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kontrak skema dari migration `2026_10_09_000001_tambah_index_urut_aktivitas`.
 *
 * Kenapa index ini ada (diukur, bukan diduga — lihat docblock migration): halaman
 * `/aktivitas` mengurutkan `created_at DESC, id DESC` dan `arsip:bersihkan-log`
 * menyaring `created_at`; tanpa index, MariaDB memindai + filesort SELURUH log
 * (22,5 ms pada 40.000 baris), dan satu kolom `created_at` saja tidak cukup karena
 * MariaDB 10.4 tidak memakai suffix PK implisit untuk ORDER BY dua kolom.
 *
 * Bukti bahwa optimizer sungguh memakainya ada di `UrutAktivitasMariaDbTest`
 * (butuh MariaDB). Tes di kelas ini sengaja mesin-agnostic — katalog index dibaca
 * lewat `SHOW INDEX` atau `PRAGMA index_list` — supaya kontrak "index ada dan
 * urutannya (created_at, id)" tetap terjaga bahkan waktu XAMPP mati.
 */
class SchemaIndexAktivitasTest extends TestCase
{
    use RefreshDatabase;

    private const NAMA_INDEX = 'aktivitas_created_at_id_index';

    private function sqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /**
     * Nama-nama index pada tabel aktivitas, dalam urutan penemuan engine.
     *
     * @return list<string>
     */
    private function daftarIndex(): array
    {
        if ($this->sqlite()) {
            return collect(DB::select("PRAGMA index_list('aktivitas')"))
                ->map(fn ($r) => (array) $r)
                ->pluck('name')
                ->values()
                ->all();
        }

        return collect(DB::select('SHOW INDEX FROM aktivitas'))
            ->map(fn ($r) => (array) $r)
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Kolom index dalam urutan (posisi 1-based di MariaDB, `seqno` di SQLite).
     *
     * @return list<string>
     */
    private function kolomIndex(): array
    {
        $this->assertContains(
            self::NAMA_INDEX,
            $this->daftarIndex(),
            'Index '.self::NAMA_INDEX.' tidak ada — migration additive tidak jalan?'
        );

        if ($this->sqlite()) {
            return collect(DB::select("PRAGMA index_info('".self::NAMA_INDEX."')"))
                ->map(fn ($r) => (array) $r)
                ->sortBy('seqno')
                ->pluck('name')
                ->values()
                ->all();
        }

        return collect(DB::select('SHOW INDEX FROM aktivitas'))
            ->map(fn ($r) => (array) $r)
            ->filter(fn ($r) => $r['Key_name'] === self::NAMA_INDEX)
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->values()
            ->all();
    }

    public function test_index_urut_aktivitas_mencakup_created_at_lalu_id(): void
    {
        // Urutan kolom bukan detail gaya: (id, created_at) tidak akan dipakai
        // untuk ORDER BY created_at DESC, id DESC.
        $this->assertSame(['created_at', 'id'], $this->kolomIndex());
    }

    public function test_index_lama_yang_masih_dipakai_tidak_hilang(): void
    {
        // (subjek_type, subjek_id) dipakai riwayat satu surat. Di dua engine namanya
        // sama karena dibuat `$table->index()` eksplisit (nullableMorphs) di migration.
        $nama = $this->daftarIndex();

        $this->assertContains('aktivitas_subjek_type_subjek_id_index', $nama);
        $this->assertContains(self::NAMA_INDEX, $nama);

        // Index pembantu FK (`aktivitas_user_id_foreign`) HANYA ada di MySQL/MariaDB:
        // InnoDB wajib punya index untuk kolom foreign key, sedangkan SQLite membuat
        // constraint tanpa index terpisah. Dikunci di sini supaya squash berikutnya
        // tidak menghilangkan index yang di produksi dibutuhkan untuk `restrictOnDelete`.
        if (! $this->sqlite()) {
            $this->assertContains('aktivitas_user_id_foreign', $nama);
        }
    }
}
