<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan akses berkas arsip (L-09 / L-10 / locked lama #9):
 * yang penting adalah "sudah login", BUKAN "milik siapa".
 *
 * Dua tes di file ini dulu mengunci perilaku yang berlawanan — staf lain diberi
 * 403 untuk file yang bukan miliknya. Perilaku itu bertentangan dengan keputusan
 * user ("tidak ada sistem kepemilikan, semua milik kantor") dan dulu lolos hanya
 * karena policy-nya memang begitu; sekarang diganti ke aturan yang benar.
 * Yang tetap wajib: tamu (belum login) tidak boleh bisa mengunduh.
 */
class LampiranControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $pengunggah;

    private Lampiran $lampiran;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengunggah = User::forceCreate([
            'nama_lengkap' => 'Yang Mengunggah',
            'email' => 'pengunggah@example.test',
            'pin' => bcrypt('123456'),
            'role' => 'pegawai',
        ]);

        $lain = User::forceCreate([
            'nama_lengkap' => 'Pemilik Surat',
            'email' => 'pemilik@example.test',
            'pin' => bcrypt('123456'),
            'role' => 'pegawai',
        ]);

        $klasifikasi = KlasifikasiPrimer::forceCreate(['kode' => 'A', 'nama' => 'Umum']);

        // Surat dibuat user lain, lampirannya diunggah user lain lagi — jadi
        // tidak ada satu pun kaitan "kepemilikan" dengan si pengunduh nanti.
        $surat = SuratMasuk::forceCreate([
            'user_id' => $lain->id,
            'pengirim' => 'Kecamatan',
            'klasifikasi_primer_id' => $klasifikasi->id,
            'nomor_surat' => '123',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'perihal' => 'Laporan',
        ]);

        $this->lampiran = Lampiran::forceCreate([
            'lampiranable_id' => $surat->id,
            'lampiranable_type' => $surat->getMorphClass(),
            'google_drive_file_id' => 'id-lama-di-drive',
            'nama_file' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'ukuran' => 100,
            'diunggah_oleh' => $this->pengunggah->id,
        ]);
    }

    public function test_staf_yang_login_boleh_unduh_arsip_kantor_meski_bukan_miliknya(): void
    {
        $orangLain = User::forceCreate([
            'nama_lengkap' => 'Staf Lain',
            'email' => 'staf-lain@example.test',
            'pin' => bcrypt('123456'),
            'role' => 'pegawai',
        ]);

        $this->mock(\App\Services\GoogleDriveService::class)
            ->shouldReceive('getFileContent')
            ->andReturn([
                'content' => 'isi file',
                'mime_type' => 'application/pdf',
                'name' => 'test.pdf',
            ]);

        $this->actingAs($orangLain)
            ->get(route('lampiran.download', $this->lampiran))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="test.pdf"');
    }

    public function test_tamu_tidak_bisa_unduh_lampiran(): void
    {
        $this->get(route('lampiran.download', $this->lampiran))
            ->assertRedirect(route('login'));
    }

    public function test_lampiran_tanpa_isi_di_kedua_penyimpanan_menghasilkan_404(): void
    {
        $kosong = Lampiran::forceCreate([
            'lampiranable_id' => $this->lampiran->lampiranable_id,
            'lampiranable_type' => SuratMasuk::class,
            'nama_file' => 'hantu.pdf',
        ]);

        $this->actingAs($this->pengunggah)
            ->get(route('lampiran.download', $kosong))
            ->assertNotFound();
    }
}
