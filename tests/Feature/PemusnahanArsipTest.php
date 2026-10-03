<?php

namespace Tests\Feature;

use App\Models\Lampiran;
use App\Models\PemusnahanArsip;
use App\Models\PemusnahanArsipItem;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L-06 / E3: pemusnahan arsip SURAT hanya boleh terjadi lewat pengajuan →
 * persetujuan admin, dan persetujuan itu HARUS benar-benar menghapus baris,
 * lampiran, dan file fisiknya. Dua syarat kelayakan (lewat retensi 5 tahun
 * L-04/L-21 dan sudah inaktif L-19) diuji dari dua sisi: daftar kandidat
 * (sisi UI) dan validasi server (sisi pemalsuan form).
 */
class PemusnahanArsipTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pegawai;

    private SuratMasuk $layak;

    private SuratMasuk $terlaluMuda;

    private SuratMasuk $masihAktif;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('arsip');

        $this->admin = User::forceCreate([
            'nama_lengkap' => 'Admin Desa',
            'email' => 'admin@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'perangkat',
        ]);

        $this->layak = $this->buatSurat('001/01/I/2019', 'inaktif', '2019-01-10');
        $this->terlaluMuda = $this->buatSurat('002/01/I/2024', 'inaktif', '2024-06-01');
        $this->masihAktif = $this->buatSurat('003/01/I/2018', 'aktif', '2018-03-01');
    }

    private function buatSurat(string $nomor, string $statusArsip, string $tanggal): SuratMasuk
    {
        $primer = \App\Models\KlasifikasiPrimer::firstOrCreate(
            ['kode' => '01'],
            ['nama' => 'Umum']
        );

        return SuratMasuk::forceCreate([
            'user_id' => $this->pegawai->id,
            'pengirim' => 'Kecamatan',
            'klasifikasi_primer_id' => $primer->id,
            'nomor_surat' => $nomor,
            'perihal' => 'Laporan tahunan',
            'tanggal_surat' => $tanggal,
            'tanggal_diterima' => $tanggal,
            'status_arsip' => $statusArsip,
        ]);
    }

    private function tempelLampiran(SuratMasuk $surat): Lampiran
    {
        $path = 'surat-masuk/01 - Umum/'.$surat->id.'/'.'dokumen.pdf';
        Storage::disk('arsip')->put($path, 'isi palsu');

        return Lampiran::forceCreate([
            'lampiranable_id' => $surat->id,
            'lampiranable_type' => SuratMasuk::class,
            'disk' => 'arsip',
            'path' => $path,
            'nama_file' => 'dokumen.pdf',
            'diunggah_oleh' => $this->pegawai->id,
        ]);
    }

    public function test_tamu_ditolak_di_semua_halaman_pemusnahan(): void
    {
        $this->get(route('pemusnahan-arsip.index'))->assertRedirect(route('login'));
        $this->get(route('pemusnahan-arsip.create'))->assertRedirect(route('login'));
    }

    public function test_hanya_arsip_usang_dan_inaktif_yang_diusulkan(): void
    {
        $this->actingAs($this->pegawai)
            ->get(route('pemusnahan-arsip.create'))
            ->assertOk()
            // layak = >5 tahun + inaktif → muncul
            ->assertSee($this->layak->nomor_surat)
            // terlalu muda (2024) dan masih aktif (walau 2018) → tidak muncul
            ->assertDontSee($this->terlaluMuda->nomor_surat)
            ->assertDontSee($this->masihAktif->nomor_surat);
    }

    public function test_mengajukan_arsip_yang_belum_layak_ditolak_server(): void
    {
        // Form dipalsukan: arsip masih aktif & arsip terlalu muda ikut dipilih.
        $this->actingAs($this->pegawai)
            ->post(route('pemusnahan-arsip.store'), [
                'arsip' => [
                    'masuk:'.$this->layak->id,
                    'masuk:'.$this->masihAktif->id,
                    'masuk:'.$this->terlaluMuda->id,
                ],
            ])
            ->assertSessionHasErrors('arsip');

        $this->assertDatabaseCount('pemusnahan_arsip', 0);
        $this->assertDatabaseHas('surat_masuk', ['id' => $this->layak->id, 'deleted_at' => null]);
    }

    public function test_pengajuan_menyimpan_snapshot_sebelum_arsip_hilang(): void
    {
        $this->tempelLampiran($this->layak);

        $this->actingAs($this->pegawai)
            ->post(route('pemusnahan-arsip.store'), [
                'arsip' => ['masuk:'.$this->layak->id],
                'alasan' => 'Sudah lewat jadwal retensi.',
            ])
            ->assertRedirect();

        $pemusnahan = PemusnahanArsip::sole();
        $item = $pemusnahan->items()->sole();

        $this->assertSame('menunggu', $pemusnahan->status);
        $this->assertSame($this->pegawai->id, $pemusnahan->diajukan_oleh);
        $this->assertSame('001/01/I/2019', $item->nomor_surat_snapshot);
        $this->assertSame('Laporan tahunan', $item->perihal_snapshot);
        $this->assertSame(1, (int) $item->jumlah_lampiran_snapshot);
    }

    public function test_arsip_yang_sedang_menunggu_tidak_bisa_diajukan_dua_kali(): void
    {
        $this->ajukan();

        $this->actingAs($this->pegawai)
            ->post(route('pemusnahan-arsip.store'), [
                'arsip' => ['masuk:'.$this->layak->id],
            ])
            ->assertSessionHasErrors('arsip');

        $this->assertDatabaseCount('pemusnahan_arsip', 1);
    }

    public function test_pegawai_tidak_bisa_menyetujui_pemusnahan(): void
    {
        $pemusnahan = $this->ajukan();

        $this->actingAs($this->pegawai)
            ->post(route('pemusnahan-arsip.setujui', $pemusnahan))
            ->assertForbidden();

        $this->assertDatabaseHas('surat_masuk', ['id' => $this->layak->id, 'deleted_at' => null]);
    }

    public function test_persetujuan_memusnahkan_baris_dan_file_fisik(): void
    {
        $lampiran = $this->tempelLampiran($this->layak);
        $pemusnahan = $this->ajukan();

        $this->actingAs($this->admin)
            ->post(route('pemusnahan-arsip.setujui', $pemusnahan))
            ->assertRedirect(route('pemusnahan-arsip.show', $pemusnahan));

        $pemusnahan->refresh();

        $this->assertSame('disetujui', $pemusnahan->status);
        $this->assertSame($this->admin->id, $pemusnahan->diproses_oleh);
        $this->assertNotNull($pemusnahan->nomor_berita_acara);
        $this->assertMatchesRegularExpression('/^BA-\d{3}\/[IVX]+\/\d{4}$/', $pemusnahan->nomor_berita_acara);

        // Baris surat + lampiran hilang total (bukan soft delete).
        $this->assertDatabaseMissing('surat_masuk', ['id' => $this->layak->id]);
        $this->assertDatabaseMissing('lampiran', ['id' => $lampiran->id]);

        // ...dan file fisiknya ikut terhapus.
        Storage::disk('arsip')->assertMissing($lampiran->path);

        // Snapshot tetap terbaca setelah arsip aslinya tidak ada.
        $this->assertSame('001/01/I/2019', $pemusnahan->items()->sole()->nomor_surat_snapshot);
    }

    public function test_pemusnahan_tidak_mengganggu_surat_lain(): void
    {
        $lain = $this->buatSurat('004/01/I/2017', 'inaktif', '2017-05-05');
        $this->tempelLampiran($lain);

        $this->actingAs($this->admin)
            ->post(route('pemusnahan-arsip.setujui', $this->ajukan()));

        $this->assertDatabaseHas('surat_masuk', ['id' => $lain->id, 'deleted_at' => null]);
    }

    public function test_pengajuan_yang_sudah_diproses_tidak_bisa_diproses_lagi(): void
    {
        $pemusnahan = $this->ajukan();

        $this->actingAs($this->admin)->post(route('pemusnahan-arsip.setujui', $pemusnahan));

        $this->actingAs($this->admin)
            ->post(route('pemusnahan-arsip.setujui', $pemusnahan))
            ->assertStatus(422);
    }

    public function test_berita_acara_hanya_terbuka_untuk_pemusnahan_yang_disetujui(): void
    {
        $pemusnahan = $this->ajukan();

        $this->actingAs($this->admin)
            ->get(route('pemusnahan-arsip.berita-acara', $pemusnahan))
            ->assertForbidden();

        $this->actingAs($this->admin)->post(route('pemusnahan-arsip.setujui', $pemusnahan));

        $this->actingAs($this->admin)
            ->get(route('pemusnahan-arsip.berita-acara', $pemusnahan))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_penolakan_tidak_menghapus_apa_pun(): void
    {
        $lampiran = $this->tempelLampiran($this->layak);
        $pemusnahan = $this->ajukan();

        $this->actingAs($this->admin)
            ->post(route('pemusnahan-arsip.tolak', $pemusnahan), [
                'catatan_admin' => 'Belum ada keputusan bersama.',
            ]);

        $pemusnahan->refresh();

        $this->assertSame('ditolak', $pemusnahan->status);
        $this->assertSame('Belum ada keputusan bersama.', $pemusnahan->catatan_admin);
        $this->assertNull($pemusnahan->nomor_berita_acara);
        $this->assertDatabaseHas('surat_masuk', ['id' => $this->layak->id, 'deleted_at' => null]);
        Storage::disk('arsip')->assertExists($lampiran->path);
    }

    private function ajukan(): PemusnahanArsip
    {
        $this->actingAs($this->pegawai)
            ->post(route('pemusnahan-arsip.store'), [
                'arsip' => ['masuk:'.$this->layak->id],
                'alasan' => 'Lewat retensi.',
            ]);

        return PemusnahanArsip::sole();
    }
}
