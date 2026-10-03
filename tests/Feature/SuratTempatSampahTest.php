<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L-05 + E8/E9 (L-19): tidak ada penghapusan permanen dari layar arsip.
 * "Nyahkan" = status_arsip inaktif; tempat sampah = soft delete yang bisa
 * dipulihkan admin, berkasnya TIDAK boleh ikut hilang.
 */
class SuratTempatSampahTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pegawai;

    private SuratMasuk $masuk;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('arsip');

        $this->admin = User::forceCreate([
            'nama_lengkap' => 'Admin',
            'email' => 'admin.sampah@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf',
            'email' => 'staf.sampah@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $klasifikasi = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);

        $this->masuk = SuratMasuk::forceCreate([
            'user_id' => $this->pegawai->id,
            'pengirim' => 'Kecamatan',
            'klasifikasi_primer_id' => $klasifikasi->id,
            'nomor_surat' => '001/01/I/2026',
            'perihal' => 'Undangan',
            'tanggal_surat' => '2026-01-05',
            'tanggal_diterima' => '2026-01-06',
        ]);
    }

    private function tempelLampiran(): Lampiran
    {
        $path = 'surat-masuk/01 - Umum/'.$this->masuk->id.'/scan.pdf';
        Storage::disk('arsip')->put($path, 'scan');

        return Lampiran::forceCreate([
            'lampiranable_id' => $this->masuk->id,
            'lampiranable_type' => SuratMasuk::class,
            'disk' => 'arsip',
            'path' => $path,
            'nama_file' => 'scan.pdf',
        ]);
    }

    public function test_nyahkan_mengubah_status_arsip_tanpa_menghapus_baris(): void
    {
        // E9: staf biasa boleh menonaktifkan arsip (tidak ada konsep kepemilikan).
        $this->actingAs($this->pegawai)
            ->patch(route('surat-masuk.status-arsip', $this->masuk), ['status_arsip' => 'inaktif'])
            ->assertRedirect();

        $this->assertSame('inaktif', $this->masuk->fresh()->status_arsip);
        $this->assertNull($this->masuk->fresh()->deleted_at);

        $this->actingAs($this->pegawai)
            ->patch(route('surat-masuk.status-arsip', $this->masuk), ['status_arsip' => 'aktif']);

        $this->assertSame('aktif', $this->masuk->fresh()->status_arsip);
    }

    public function test_status_arsip_hanya_menerima_dua_nilai_yang_sah(): void
    {
        $this->actingAs($this->pegawai)
            ->patch(route('surat-masuk.status-arsip', $this->masuk), ['status_arsip' => 'musnah'])
            ->assertSessionHasErrors('status_arsip');
    }

    public function test_hapus_surat_adalah_soft_delete_dan_berkas_tetap_utuh(): void
    {
        $lampiran = $this->tempelLampiran();

        $this->actingAs($this->admin)
            ->delete(route('surat-masuk.destroy', $this->masuk))
            ->assertRedirect(route('surat-masuk.index'));

        $this->assertSoftDeleted('surat_masuk', ['id' => $this->masuk->id]);

        // Ini inti L-05: baris lampiran & file fisiknya TIDAK boleh ikut hilang,
        // kalau tidak "pulihkan" akan mengembalikan surat tanpa berkasnya.
        $this->assertDatabaseHas('lampiran', ['id' => $lampiran->id]);
        Storage::disk('arsip')->assertExists($lampiran->path);
    }

    public function test_pegawai_tidak_bisa_menghapus_surat(): void
    {
        $this->actingAs($this->pegawai)
            ->delete(route('surat-masuk.destroy', $this->masuk))
            ->assertForbidden();

        $this->assertDatabaseHas('surat_masuk', ['id' => $this->masuk->id, 'deleted_at' => null]);
    }

    public function test_surat_terhapus_tersembunyi_dari_daftar_biasa(): void
    {
        $this->masuk->delete();

        $this->actingAs($this->admin)
            ->get(route('surat-masuk.index'))
            ->assertOk()
            ->assertDontSee($this->masuk->nomor_surat);
    }

    public function test_tempat_sampah_hanya_bisa_dibuka_admin(): void
    {
        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index', ['sampah' => 1]))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('surat-masuk.index', ['sampah' => 1]))
            ->assertOk()
            ->assertSee('Tempat Sampah Surat Masuk');
    }

    public function test_admin_bisa_memulihkan_surat_dari_tempat_sampah(): void
    {
        $this->masuk->delete();

        $this->actingAs($this->admin)
            ->patch(route('surat-masuk.restore', $this->masuk))
            ->assertRedirect(route('surat-masuk.show', $this->masuk));

        $this->assertNull($this->masuk->fresh()->deleted_at);
    }

    public function test_pegawai_tidak_bisa_memulihkan_surat(): void
    {
        $this->masuk->delete();

        $this->actingAs($this->pegawai)
            ->patch(route('surat-masuk.restore', $this->masuk))
            ->assertForbidden();

        $this->assertNotNull($this->masuk->fresh()->deleted_at);
    }

    public function test_filter_usang_hanya_memuat_surat_lewat_retensi(): void
    {
        $klasifikasi = KlasifikasiPrimer::firstOrCreate(['kode' => '02'], ['nama' => 'Perencanaan']);

        $lama = SuratKeluar::forceCreate([
            'user_id' => $this->pegawai->id,
            'penerima' => 'Warga',
            'klasifikasi_primer_id' => $klasifikasi->id,
            'nomor_surat' => '002/02/I/2019',
            'perihal' => 'Pemberitahuan',
            'tanggal_surat' => '2019-02-02',
        ]);

        $this->actingAs($this->admin)
            ->get(route('surat-keluar.index', ['usang' => 1]))
            ->assertOk()
            ->assertSee($lama->nomor_surat);

        // Surat keluar baru (2026) tidak muncul di daftar usang.
        $baru = SuratKeluar::forceCreate([
            'user_id' => $this->pegawai->id,
            'penerima' => 'Warga',
            'klasifikasi_primer_id' => $klasifikasi->id,
            'nomor_surat' => '003/02/I/2026',
            'perihal' => 'Undangan',
            'tanggal_surat' => '2026-02-02',
        ]);

        $this->actingAs($this->admin)
            ->get(route('surat-keluar.index', ['usang' => 1]))
            ->assertDontSee($baru->nomor_surat);
    }
}
