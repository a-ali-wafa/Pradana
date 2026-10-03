<?php

namespace Tests\Feature;

use App\Models\DrafKontenSuratKeluar;
use App\Models\KlasifikasiPrimer;
use App\Models\SuratKeluar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L-16: halaman draf konten adalah satu-satunya jalan mengisi tabel
 * draf_konten_surat_keluar. Tanpa itu cetak PDF selalu ditolak
 * (CetakSuratKeluarController::cetak), jadi tes ini menutup fitur inti F1.
 */
class DrafKontenSuratKeluarTest extends TestCase
{
    use RefreshDatabase;

    private User $petugas;

    private SuratKeluar $surat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->petugas = User::forceCreate([
            'nama_lengkap' => 'Petugas Draf',
            'email' => 'petugas.draf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'perangkat',
        ]);

        $klasifikasi = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Keuangan']);

        $this->surat = SuratKeluar::forceCreate([
            'user_id' => $this->petugas->id,
            'penerima' => 'Wali Kota Malang',
            'klasifikasi_primer_id' => $klasifikasi->id,
            'nomor_surat' => '001/01/I/2026',
            'perihal' => 'Undangan rapat',
            'tanggal_surat' => now(),
        ]);
    }

    public function test_tamu_ditolak_di_halaman_draf(): void
    {
        $this->get(route('surat-keluar.draf.edit', $this->surat))->assertRedirect(route('login'));
    }

    public function test_halaman_draf_bisa_dibuka_walau_belum_ada_draf(): void
    {
        $this->actingAs($this->petugas)
            ->get(route('surat-keluar.draf.edit', $this->surat))
            ->assertOk()
            ->assertSee('Isi Draf Surat Keluar')
            ->assertSee('name="isi_surat"', false);
    }

    public function test_menyimpan_draf_lalu_mencetak_pdf(): void
    {
        $this->actingAs($this->petugas)->put(route('surat-keluar.draf.update', $this->surat), [
            'isi_surat' => "Dengan ini kami mengundang Bapak.\nTerima kasih.",
            'salam_pembuka' => 'Dengan hormat,',
            'jabatan_penandatangan' => 'LURAH UREK-UREK',
            'atas_nama' => 'Budi Santoso',
        ])->assertRedirect(route('surat-keluar.draf.edit', $this->surat));

        $draf = DrafKontenSuratKeluar::sole();
        $this->assertSame($this->surat->id, $draf->surat_keluar_id);
        $this->assertStringContainsString('mengundang Bapak', $draf->isi_surat);

        $response = $this->actingAs($this->petugas)->get(route('surat-keluar.cetak', $this->surat));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_menyimpan_draf_lalu_simpan_dan_pratinjau(): void
    {
        $this->actingAs($this->petugas)->put(route('surat-keluar.draf.update', $this->surat), [
            'isi_surat' => 'Isi singkat.',
            'aksi' => 'simpan_cetak',
        ])->assertRedirect(route('surat-keluar.cetak', $this->surat));
    }

    public function test_draf_kedua_menimpa_bukan_menambah_baris(): void
    {
        // Relasi 1-1: satu surat tidak boleh punya dua draf.
        foreach (['Draf pertama.', 'Draf kedua.'] as $isi) {
            $this->actingAs($this->petugas)->put(route('surat-keluar.draf.update', $this->surat), [
                'isi_surat' => $isi,
            ]);
        }

        $this->assertSame(1, DrafKontenSuratKeluar::count());
        $this->assertSame('Draf kedua.', DrafKontenSuratKeluar::sole()->isi_surat);
    }

    public function test_isi_surat_wajib_diisi(): void
    {
        $this->actingAs($this->petugas)
            ->put(route('surat-keluar.draf.update', $this->surat), ['isi_surat' => ''])
            ->assertSessionHasErrors('isi_surat');

        $this->assertSame(0, DrafKontenSuratKeluar::count());
    }

    public function test_cetak_ditolak_saat_draf_belum_diisi(): void
    {
        $this->actingAs($this->petugas)
            ->get(route('surat-keluar.cetak', $this->surat))
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
