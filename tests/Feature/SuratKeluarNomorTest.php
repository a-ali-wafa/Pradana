<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\KlasifikasiSekunder;
use App\Models\SuratKeluar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jalur HTTP pembuatan surat keluar: memastikan controller memakai
 * NomorSuratKeluarGenerator dan urutannya naik satu per satu, dengan format
 * D1 [LOCKED] {urutan}/{kodeP.kodeS}/{bulan romawi}/{tahun}.
 */
class SuratKeluarNomorTest extends TestCase
{
    use RefreshDatabase;

    private User $petugas;

    private KlasifikasiPrimer $primer;

    private KlasifikasiSekunder $sekunder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->petugas = User::forceCreate([
            'nama_lengkap' => 'Petugas Nomor',
            'email' => 'petugas.nomor@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'perangkat',
        ]);

        $this->primer = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Keuangan']);
        $this->sekunder = $this->primer->sekunder()->create(['kode' => '02', 'nama' => 'Anggaran']);
    }

    private function buatSurat(string $tanggal): array
    {
        return [
            'penerima' => 'Warga',
            'klasifikasi_primer_id' => $this->primer->id,
            'klasifikasi_sekunder_id' => $this->sekunder->id,
            'sifat' => 'biasa',
            'perihal' => 'Undangan',
            'tanggal_surat' => $tanggal,
            'status_berkas' => 'asli',
        ];
    }

    public function test_nomor_naik_berurutan_lewat_http(): void
    {
        $this->actingAs($this->petugas)->post(route('surat-keluar.store'), $this->buatSurat('2026-09-10'));
        $this->actingAs($this->petugas)->post(route('surat-keluar.store'), $this->buatSurat('2026-09-11'));

        $this->assertSame(
            ['001/01.02/IX/2026', '002/01.02/IX/2026'],
            SuratKeluar::orderBy('id')->pluck('nomor_surat')->all(),
        );
    }

    public function test_urutan_mulai_ulang_di_tahun_berikutnya(): void
    {
        // D2: reset tiap tahun, dihitung dari tanggal_surat (bukan tanggal server).
        $this->actingAs($this->petugas)->post(route('surat-keluar.store'), $this->buatSurat('2026-12-30'));
        $this->actingAs($this->petugas)->post(route('surat-keluar.store'), $this->buatSurat('2027-01-04'));

        $this->assertSame(
            ['001/01.02/XII/2026', '001/01.02/I/2027'],
            SuratKeluar::orderBy('id')->pluck('nomor_surat')->all(),
        );
    }

    public function test_nomor_yang_sudah_ada_tidak_dipakai_dua_kali(): void
    {
        $nomor = [];

        foreach (range(1, 4) as $i) {
            $this->actingAs($this->petugas)
                ->post(route('surat-keluar.store'), $this->buatSurat('2028-05-0'.$i));
        }

        $nomor = SuratKeluar::whereYear('tanggal_surat', 2028)->pluck('nomor_surat')->all();

        $this->assertCount(4, $nomor);
        $this->assertCount(4, array_unique($nomor));
    }
}
