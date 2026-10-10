<?php

namespace Tests\Feature;

use App\Models\Aktivitas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Rekap CSV log aktivitas (`docs/daftar-peningkatan.md` §2 butir 5, P2 — dikerjakan
 * 10 Okt 2026) untuk keperluan audit.
 *
 * Yang dijamin di sini bukan bentuk berkasnya saja, tapi SATU hal yang membuat
 * ekspor bisa dipercaya orang kantor: isi CSV = apa yang sedang ditampilkan layar.
 * Itu ditegakkan lewat `AktivitasController::terapkanFilter()` yang dipakai
 * `index()` dan `rekap()` bersama-sama — kalau nanti ada yang menyalin kerangka
 * filternya lagi ke tempat lain, tes "angka layar = jumlah baris CSV" yang merah.
 *
 * `chunk(500)` (bukan `get()`) dipakai karena `aktivitas` satu-satunya tabel yang
 * tumbuh tanpa batas: setiap aksi di semua modul menulis satu baris.
 */
class LogAktivitasRekapTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'nama_lengkap' => 'Kepala Desa',
            'email' => 'kepala@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'admin',
        ]);
    }

    private function catat(string $waktu, string $aksi, ?int $userId = null): Aktivitas
    {
        $catatan = Aktivitas::forceCreate([
            'user_id' => $userId ?? $this->admin->id,
            'aksi' => $aksi,
            'subjek_type' => null,
            'subjek_id' => null,
        ]);

        // Diisi eksplisit supaya tes tidak bergantung pada jam saat suite jalan.
        $catatan->created_at = Carbon::parse($waktu);
        $catatan->updated_at = Carbon::parse($waktu);
        $catatan->save();

        return $catatan;
    }

    /**
     * @return list<string> baris data (tanpa header), sudah di-split dari CSV
     */
    private function unduh(array $query = []): array
    {
        $csv = $this->actingAs($this->admin)
            ->get(route('aktivitas.rekap', $query))
            ->assertOk()
            ->streamedContent();

        $baris = array_values(array_filter(explode("\n", $csv), static fn (string $l): bool => trim($l) !== ''));

        $this->assertNotEmpty($baris, 'CSV tanpa satu baris pun bukan rekap.');

        // Header dibuang dari hasil; BOM hanya menempel di baris pertama.
        return array_slice($baris, 1);
    }

    public function test_bentuk_csv_bisa_dibuka_excel_kantor(): void
    {
        $this->catat('2026-10-05 09:00:00', 'Memusnahkan surat masuk: Laporan tahunan');

        $csv = $this->actingAs($this->admin)
            ->get(route('aktivitas.rekap'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv,
            'Tanpa BOM, Excel Indonesia membuka UTF-8 sebagai ANSI dan karakter non-ASCII rusak.');

        // `fputcsv` membungkus setiap field yang mengandung spasi (perilaku PHP,
        // dan sama seperti rekap surat yang sudah dipakai kantor), jadi barisnya
        // diurai dengan parser CSV, bukan `explode(';')`.
        $baris = array_values(array_filter(explode("\n", $csv), static fn (string $l): bool => trim($l) !== ''));
        $header = str_getcsv(substr($baris[0], 3), ';');

        $this->assertSame(
            ['Waktu (jam kantor)', 'Petugas', 'Aksi', 'Subjek'],
            $header,
            'Pemisahnya `;` sesuai locale kantor — dengan `,` Excel menaruh semua kolom dalam satu sel.'
        );

        $this->assertStringContainsString('Memusnahkan surat masuk: Laporan tahunan', $csv);
        $this->assertStringContainsString('Kepala Desa', $csv, 'Nama petugas harus terbaca, bukan id mentah.');
        $this->assertStringContainsString('2026-10-05 09:00', $csv);
    }

    public function test_rekap_mengandung_semua_catatan_pada_filter_bukan_hanya_halaman(): void
    {
        // Layar menampilkan 30 per halaman (keputusan sendiri di BARIS_PER_HALAMAN);
        // ekspor menjanjikan SEMUA. Bedanya 35 vs 30 inilah yang diuji.
        for ($i = 0; $i < 35; $i++) {
            $this->catat('2026-09-01 '.sprintf('%02d', intdiv($i, 4)).':'.sprintf('%02d', ($i % 4) * 15).':00',
                'Catatan audit nomor '.($i + 1));
        }

        $layar = $this->actingAs($this->admin)
            ->get(route('aktivitas.index'))
            ->assertOk();

        $layar->assertSee('35 catatan');

        $data = $this->unduh();
        $this->assertCount(35, $data, 'Rekap memotong diam-diam seperti `limit(50)` zaman dulu.');

        // Halaman 1 layar memang hanya 30 — bukti bahwa angka di tombol bukan angka halaman.
        $this->assertStringContainsString('Unduh CSV (35)', $layar->getContent());
    }

    public function test_filter_aktif_ikut_ke_rekap_dan_sampai_berarti_akhir_hari(): void
    {
        $staf = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $this->catat('2026-10-04 12:00:00', 'Mengunggah lampiran: scan.pdf');
        $this->catat('2026-10-05 07:05:00', 'Mengunggah lampiran: kwitansi.pdf');
        $this->catat('2026-10-05 23:30:00', 'Memusnahkan surat masuk: Laporan');
        $this->catat('2026-10-06 00:01:00', 'Mengunggah lampiran: besok.pdf');
        $this->catat('2026-10-05 10:00:00', 'Mengunggah lampiran: punya staf.pdf', $staf->id);

        // Periode saja: "sampai 5 Oktober" harus mencakup 23:30 di hari itu.
        $hanyaPeriode = $this->unduh(['dari' => '2026-10-05', 'sampai' => '2026-10-05']);
        $gabungan = implode("\n", $hanyaPeriode);
        $this->assertStringContainsString('kwitansi.pdf', $gabungan);
        $this->assertStringContainsString('Memusnahkan surat masuk: Laporan', $gabungan);
        $this->assertStringNotContainsString('scan.pdf', $gabungan);
        $this->assertStringNotContainsString('besok.pdf', $gabungan);

        // + filter petugas: yang muncul hanya milik staf itu.
        $milikStaf = implode("\n", $this->unduh(['user_id' => $staf->id]));
        $this->assertStringContainsString('punya staf.pdf', $milikStaf);
        $this->assertStringNotContainsString('kwitansi.pdf', $milikStaf);
        $this->assertCount(1, $this->unduh(['user_id' => $staf->id]));

        // + cari teks: kata harus ada, dan tetap menghormati periode.
        $cari = implode("\n", $this->unduh(['cari' => 'mengunggah kwitansi']));
        $this->assertStringContainsString('kwitansi.pdf', $cari);
        $this->assertStringNotContainsString('punya staf.pdf', $cari);
        $this->assertCount(1, $this->unduh(['cari' => 'mengunggah kwitansi']));
    }

    public function test_angka_di_layar_sama_dengan_baris_di_csv(): void
    {
        // Jaminan "satu kerangka filter" — ini yang bikin perbedaan diam-diam
        // antara dokumen audit dan layar tidak bisa masuk lagi.
        $this->catat('2026-10-01 08:00:00', 'Masuk');
        $this->catat('2026-10-02 08:00:00', 'Ubah');
        $this->catat('2026-10-03 08:00:00', 'Hapus');

        $layar = $this->actingAs($this->admin)
            ->get(route('aktivitas.index', ['dari' => '2026-10-02']))
            ->assertOk();

        $this->assertSame(1, preg_match('/(\d+) catatan/', $layar->getContent(), $m),
            'Layar harus menyebut jumlah catatan untuk filter ini.');
        $this->assertSame(2, (int) $m[1], 'Layar harus menunjukkan 2 catatan untuk filter ini.');
        $this->assertCount(2, $this->unduh(['dari' => '2026-10-02']));
    }

    /**
     * `Aktivitas::user()` sengaja `withTrashed()` (sama seperti relasi `petugas`
     * di laporan): akun yang dihapus lunak tidak boleh membuat jejaknya kehilangan
     * nama pelakunya — untuk audit, itu justru baris yang paling mungkin ditanya.
     * Dijtested supaya keputusan itu tidak hilang oleh orang yang "merapikan"
     * relasi tanpa membaca alasannya.
     */
    public function test_nama_petugas_yang_akunnya_dihapus_tetap_terbaca_di_layar_dan_csv(): void
    {
        $staf = User::forceCreate([
            'nama_lengkap' => 'Staf Pergi',
            'email' => 'staf3@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $this->catat('2026-10-05 08:00:00', 'Mengunggah lampiran: penting.pdf', $staf->id);

        $staf->delete();

        $csv = implode("\n", $this->unduh());
        $this->assertStringContainsString('Staf Pergi', $csv,
            'Nama petugas yang akunnya dihapus hilang dari dokumen audit.');
        $this->assertStringContainsString('Mengunggah lampiran: penting.pdf', $csv);
        $this->assertStringNotContainsString('(user dihapus)', $csv);

        $this->actingAs($this->admin)
            ->get(route('aktivitas.index'))
            ->assertOk()
            ->assertSee('Staf Pergi');
    }

    public function test_staf_tidak_bisa_mengunduh_rekap_log(): void
    {
        // Halaman log dan ekspor-nya ada di grup middleware yang sama (`auth`+`admin`).
        $staf = User::forceCreate([
            'nama_lengkap' => 'Staf',
            'email' => 'staf2@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $this->catat('2026-10-05 08:00:00', 'Rahasia kantor');

        $this->actingAs($staf)->get(route('aktivitas.rekap'))->assertForbidden();
        $this->actingAs($staf)->get(route('aktivitas.index'))->assertForbidden();
    }

    public function test_tautan_unduh_membawa_filter_yang_sedang_dipakai(): void
    {
        $this->catat('2026-10-05 08:00:00', 'Mengunggah lampiran: rapor.pdf');

        $layar = $this->actingAs($this->admin)
            ->get(route('aktivitas.index', ['cari' => 'rapor', 'sampai' => '2026-10-05']))
            ->assertOk();

        $html = $layar->getContent();

        $this->assertStringContainsString('cari=rapor', $html, 'Tombol unduh kehilangan kata kunci yang sedang dipakai.');
        $this->assertStringContainsString('sampai=2026-10-05', $html);
        $this->assertStringNotContainsString('user_id=&', $html, 'Filter kosong tidak perlu ikut di tautan.');
    }
}
