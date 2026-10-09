<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\PengajuanHapusLampiran;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Jalur persetujuan "hapus lampiran" (`pengajuan-hapus-lampiran.setujui`).
 *
 * Ini satu-satunya tempat di aplikasi yang MENGHAPUS PERMANEN baris `lampiran`
 * (L-05 melarang hapus permanen arsip; L-06 membuat pengecualian lewat alur
 * pengajuan → approval). Sebelum tes ini dibuat (9 Okt 2026), tidak ada satu tes
 * pun yang menjalankan method itu — padahal urutannya baru saja diubah: transaksi
 * database lebih dulu, berkas fisik setelah commit.
 */
class PengajuanHapusLampiranSetujuiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pegawai;

    private SuratMasuk $surat;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('arsip');

        $this->admin = User::forceCreate([
            'nama_lengkap' => 'Kepala Desa',
            'email' => 'kepala@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $klasifikasi = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);

        // Surat TUA: pengajuan hanya bisa dibuat kalau lewat retensi (L-04/L-21),
        // dan `setujui()` tidak memeriksa ulang umur surat — status "menunggu" yang
        // sudah divalidasi saat pengajuan itulah izinnya.
        $this->surat = SuratMasuk::forceCreate([
            'user_id' => $this->pegawai->id,
            'pengirim' => 'Kecamatan',
            'klasifikasi_primer_id' => $klasifikasi->id,
            'nomor_surat' => 'TUAK/1',
            'perihal' => 'Laporan tahunan',
            'tanggal_surat' => '2018-01-10',
            'tanggal_diterima' => '2018-01-15',
            'status_arsip' => 'inaktif',
            'sifat' => 'biasa',
        ]);
    }

    /**
     * @return array{0: Lampiran, 1: PengajuanHapusLampiran, 2: string}
     */
    private function ajukan(string $nama = 'laporan-tahunan.pdf'): array
    {
        $path = 'surat-masuk/01-umum/'.$this->surat->id.'/'.$nama;
        Storage::disk('arsip')->put($path, 'isi palsu');

        $lampiran = Lampiran::forceCreate([
            'lampiranable_type' => SuratMasuk::class,
            'lampiranable_id' => $this->surat->id,
            'disk' => 'arsip',
            'path' => $path,
            'nama_file' => $nama,
            'mime_type' => 'application/pdf',
            'ukuran' => 9,
            'diunggah_oleh' => $this->pegawai->id,
        ]);

        $pengajuan = PengajuanHapusLampiran::forceCreate([
            'lampiran_id' => $lampiran->id,
            'nama_file_snapshot' => $nama,
            'diajukan_oleh' => $this->pegawai->id,
            'status' => 'menunggu',
        ]);

        return [$lampiran, $pengajuan, $path];
    }

    public function test_persetujuan_menghapus_baris_dan_berkas_fisik(): void
    {
        [$lampiran, $pengajuan, $path] = $this->ajukan();

        $this->actingAs($this->admin)
            ->post(route('pengajuan-hapus-lampiran.setujui', $pengajuan))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('lampiran', ['id' => $lampiran->id]);
        Storage::disk('arsip')->assertMissing($path);

        $ini = $pengajuan->fresh();

        $this->assertSame('disetujui', $ini->status);
        $this->assertSame($this->admin->id, $ini->diproses_oleh);
        $this->assertNotNull($ini->diproses_pada);

        // L-23: riwayat pengajuan tidak boleh hilang dari audit — barisnya tetap
        // ada, `nama_file_snapshot` menyimpan jejak berkas yang sudah tidak ada.
        // `lampiran_id` ikut jadi NULL (nullOnDelete), jadi snapshot itu satu-satunya
        // yang masih menyebut berkasnya.
        $this->assertSame('laporan-tahunan.pdf', $ini->nama_file_snapshot);
        $this->assertNull($ini->lampiran_id);
    }

    public function test_pengajuan_yang_sudah_diproses_tidak_bisa_disetujui_lagi(): void
    {
        [, $pengajuan] = $this->ajukan();

        $this->actingAs($this->admin)->post(route('pengajuan-hapus-lampiran.setujui', $pengajuan));

        $this->actingAs($this->admin)
            ->post(route('pengajuan-hapus-lampiran.setujui', $pengajuan))
            ->assertStatus(422);
    }

    public function test_staf_tidak_bisa_menyetujui(): void
    {
        [$lampiran, $pengajuan, $path] = $this->ajukan();

        $this->actingAs($this->pegawai)
            ->post(route('pengajuan-hapus-lampiran.setujui', $pengajuan))
            ->assertForbidden();

        $this->assertDatabaseHas('lampiran', ['id' => $lampiran->id]);
        Storage::disk('arsip')->assertExists($path);
    }

    public function test_lampiran_yang_sudah_hilang_menghasilkan_410_tanpa_mengubah_status(): void
    {
        [$lampiran, $pengajuan] = $this->ajukan();

        // Kasus nyata: dua admin menekan "setujui" hampir bersamaan, atau berkasnya
        // sudah hilang lewat jalur lain. 410, dan pengajuannya TIDAK ditandai
        // disetujui — kalau ikut berubah, antrian admin hilang tanpa ada yang
        // benar-benar dieksekusi.
        $lampiran->delete();

        $this->actingAs($this->admin)
            ->post(route('pengajuan-hapus-lampiran.setujui', $pengajuan))
            ->assertStatus(410);

        $this->assertSame('menunggu', $pengajuan->fresh()->status);
    }

    public function test_berkas_tetap_ada_kalau_penulisan_database_gagal(): void
    {
        // Inilah alasan urutannya dibalik (Fase 3, 9 Okt 2026). Berkas fisik dihapus
        // SETELAH transaksi DB commit; kegagalan di tengah transaksi me-rollback
        // baris & status, dan file-nya masih utuh sehingga aksinya bisa diulang.
        // Bentuk lama: file sudah hilang duluan, `update()` gagal, pengajuan tetap
        // "menunggu" untuk berkas yang tidak ada lagi.
        [$lampiran, $pengajuan, $path] = $this->ajukan('gagal-di-simpan.pdf');

        $penghambat = new class
        {
            public function updating(Model $model): void
            {
                throw new \RuntimeException('simulasi kegagalan penulisan');
            }
        };

        PengajuanHapusLampiran::observe($penghambat);

        try {
            $this->actingAs($this->admin)
                ->post(route('pengajuan-hapus-lampiran.setujui', $pengajuan));
        } catch (\RuntimeException) {
            // Sengaja ditelan: yang diuji adalah STATE sesudah kegagalan, bukan
            // bentuk responsnya. Tanpa catch ini tes berhenti di baris ini.
        } finally {
            PengajuanHapusLampiran::flushEventListeners();
        }

        $this->assertDatabaseHas('lampiran', ['id' => $lampiran->id]);
        Storage::disk('arsip')->assertExists($path);
        $this->assertSame('menunggu', $pengajuan->fresh()->status);
    }
}
