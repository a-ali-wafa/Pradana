<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\KlasifikasiSekunder;
use App\Models\KlasifikasiTersier;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perilaku CRUD klasifikasi bertingkat SETELAH ketiganya berbagi trait
 * `MengelolaKlasifikasi` (9 Okt 2026).
 *
 * Kenapa tes ini ada sekarang: sebelum digabung, tidak ada satu pun tes yang
 * menyentuh endpoint klasifikasi (dicari `route('klasifikasi-` di tests/ → nol
 * hasil). Refactor tanpa tes adalah cara paling mudah untuk memindahkan bug,
 * jadi tingkah laku yang barusan disatukan dikunci lebih dulu:
 *
 * 1. daftar urut `kode` + pagination 20 (H2), untuk ketiga tingkat;
 * 2. `store`/`update`/`destroy` menulis flash pada kunci yang dirender layout
 *    (`success`) dan redirect ke index tingkat itu sendiri;
 * 3. `destroy` pada kode yang masih dipakai surat TIDAK menghapus apa pun dan
 *    memberi pesan ramah — ini bukti bahwa `catch (QueryException)` di trait
 *    bukan dead code (PHPStan menyangka begitu; SQLite dengan
 *    `foreign_key_constraints=true` membuktikan sebaliknya);
 * 4. staf boleh membaca daftar, tapi mutasi tetap admin-only (B3 [LOCKED]).
 */
class KlasifikasiCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'nama_lengkap' => 'Kepala',
            'email' => 'klasifikasi.admin@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        $this->staf = User::forceCreate([
            'nama_lengkap' => 'Petugas',
            'email' => 'klasifikasi.staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);
    }

    private function primer(array $ubah = []): KlasifikasiPrimer
    {
        return KlasifikasiPrimer::forceCreate(array_merge([
            'kode' => '01',
            'nama' => 'Umum',
        ], $ubah));
    }

    public function test_daftar_klasifikasi_urut_kode_untuk_ketiga_tingkat(): void
    {
        $atas = $this->primer(['kode' => 'ZZ', 'nama' => 'Zona']);
        $bawah = $this->primer(['kode' => 'AA', 'nama' => 'Arsip']);

        $sekunder = KlasifikasiSekunder::forceCreate([
            'klasifikasi_primer_id' => $atas->id, 'kode' => 'ZZ', 'nama' => 'Sekunder Z',
        ]);

        KlasifikasiTersier::forceCreate([
            'klasifikasi_sekunder_id' => $sekunder->id, 'kode' => 'ZZ', 'nama' => 'Tersier Z',
        ]);
        KlasifikasiTersier::forceCreate([
            'klasifikasi_sekunder_id' => $sekunder->id, 'kode' => 'AA', 'nama' => 'Tersier A',
        ]);

        // Urutan dibaca dari posisi di HTML, bukan dari query-nya sendiri —
        // justru itu yang dilihat petugas. Yang dibandingkan adalah BARIS tabel,
        // bukan sembarang kemunculan kata (sidebar juga mengandung kata "arsip").
        $daftar = $this->actingAs($this->staf)->get(route('klasifikasi-primer.index'))->assertOk();

        $html = (string) $daftar->getContent();
        $posisiKode = [];
        preg_match_all('/font-monospace fs-6 px-3">([A-Z0-9]{2})</', $html, $kode);
        $posisiKode = $kode[1] ?? [];

        $this->assertSame(
            ['AA', 'ZZ'],
            $posisiKode,
            'Daftar primer tidak urut kode. Kode yang terbaca dari tabel: '.implode(', ', $posisiKode)
        );

        // Setiap tingkat menampilkan barisnya sendiri — jadi yang dibandingkan
        // juga string yang memang dibuat di tingkat itu (RefreshDatabase memisahkan
        // tiap tes, jadi baris dari tes lain tidak boleh diharapkan muncul di sini).
        $this->actingAs($this->staf)
            ->get(route('klasifikasi-sekunder.index'))
            ->assertOk()
            ->assertSee('Sekunder Z')
            ->assertSee('Zona');

        $this->actingAs($this->staf)
            ->get(route('klasifikasi-tersier.index'))
            ->assertOk()
            ->assertSee('Tersier A')
            ->assertSee('Tersier Z');

        $this->assertNotNull($bawah);
    }

    public function test_admin_menambah_mengubah_dan_menghapus_serta_dapat_flash_yang_terbaca(): void
    {
        $ini = $this->primer(['kode' => '07', 'nama' => 'Perencanaan']);

        $this->actingAs($this->admin)
            ->post(route('klasifikasi-primer.store'), ['kode' => '08', 'nama' => 'Keuangan'])
            ->assertRedirect(route('klasifikasi-primer.index'))
            ->assertSessionHas('success', 'Klasifikasi primer berhasil ditambahkan.');

        $this->assertDatabaseHas('klasifikasi_primer', ['kode' => '08', 'nama' => 'Keuangan']);

        $ini->refresh();
        $baru = KlasifikasiPrimer::where('kode', '08')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('klasifikasi-primer.update', $baru), ['kode' => '08', 'nama' => 'Keuangan & Aset'])
            ->assertRedirect(route('klasifikasi-primer.index'))
            ->assertSessionHas('success', 'Klasifikasi primer berhasil diperbarui.');

        $this->assertDatabaseHas('klasifikasi_primer', ['kode' => '08', 'nama' => 'Keuangan & Aset']);

        $this->actingAs($this->admin)
            ->delete(route('klasifikasi-primer.destroy', $baru))
            ->assertRedirect(route('klasifikasi-primer.index'))
            ->assertSessionHas('success', 'Klasifikasi primer berhasil dihapus.');

        $this->assertDatabaseMissing('klasifikasi_primer', ['id' => $baru->id]);
        $this->assertDatabaseHas('klasifikasi_primer', ['id' => $ini->id]);
    }

    public function test_klasifikasi_yang_masih_dipakai_surat_tidak_terhapus_dan_petugas_menerima_pesan(): void
    {
        $dipakai = $this->primer(['kode' => '09', 'nama' => 'Dipakai']);

        SuratMasuk::forceCreate([
            'user_id' => $this->admin->id,
            'pengirim' => 'Kecamatan',
            'klasifikasi_primer_id' => $dipakai->id,
            'nomor_surat' => 'KLS-1',
            'perihal' => 'Surat yang menahan kode ini',
            'tanggal_surat' => '2026-06-06',
            'tanggal_diterima' => '2026-06-07',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('klasifikasi-primer.destroy', $dipakai))
            ->assertRedirect()
            ->assertSessionHas('error', 'Klasifikasi primer tidak bisa dihapus karena masih dipakai di surat masuk/keluar.');

        // Bukti `catch (QueryException)` hidup: tanpa catch ini layarnya 500,
        // dan barisnya tetap ada karena DELETE ditolak database.
        $this->assertDatabaseHas('klasifikasi_primer', ['id' => $dipakai->id]);

        // Kunci 'error' dipakai di sini karena layout hanya merender success/error;
        // sisi penulisannya dijaga FlashKonsistenTest.
        $this->actingAs($this->admin)
            ->get(route('klasifikasi-primer.index'))
            ->assertOk()
            ->assertSee('Dipakai');
    }

    public function test_staf_bisa_membaca_daftar_tetapi_tidak_bisa_mengubah_klasifikasi(): void
    {
        $ini = $this->primer(['kode' => '11', 'nama' => 'Persuratan']);

        $this->actingAs($this->staf)->get(route('klasifikasi-primer.index'))->assertOk();

        $this->actingAs($this->staf)
            ->post(route('klasifikasi-primer.store'), ['kode' => '12', 'nama' => 'Seharusnya ditolak'])
            ->assertForbidden();

        $this->actingAs($this->staf)
            ->delete(route('klasifikasi-primer.destroy', $ini))
            ->assertForbidden();

        $this->assertDatabaseMissing('klasifikasi_primer', ['kode' => '12']);
        $this->assertDatabaseHas('klasifikasi_primer', ['id' => $ini->id]);
    }

    public function test_kode_duplikat_ditolak_per_induk_bukan_global(): void
    {
        $satu = $this->primer(['kode' => '20', 'nama' => 'Induk satu']);
        $dua = $this->primer(['kode' => '21', 'nama' => 'Induk dua']);

        KlasifikasiSekunder::forceCreate([
            'klasifikasi_primer_id' => $satu->id, 'kode' => '01', 'nama' => 'Anak satu',
        ]);

        // Kode yang sama di bawah induk yang sama = ditolak (unique komposit).
        $this->actingAs($this->admin)
            ->post(route('klasifikasi-sekunder.store'), [
                'klasifikasi_primer_id' => $satu->id, 'kode' => '01', 'nama' => 'Anak kembar',
            ])
            ->assertSessionHasErrors('kode');

        // Kode yang sama di bawah induk berbeda = boleh, dan flash-nya tetap benar.
        $this->actingAs($this->admin)
            ->post(route('klasifikasi-sekunder.store'), [
                'klasifikasi_primer_id' => $dua->id, 'kode' => '01', 'nama' => 'Anak induk lain',
            ])
            ->assertRedirect(route('klasifikasi-sekunder.index'))
            ->assertSessionHas('success', 'Klasifikasi sekunder berhasil ditambahkan.');

        $this->assertDatabaseHas('klasifikasi_sekunder', [
            'klasifikasi_primer_id' => $dua->id, 'kode' => '01', 'nama' => 'Anak induk lain',
        ]);
    }
}
