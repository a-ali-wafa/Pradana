<?php

namespace Tests\Feature;

use App\Models\PengaturanInstansi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengaturan Instansi (kop surat + logo). Kelas ini baru dibuat 4 Okt 2026 saat
 * form logo ditambah pratinjau — sebelumnya halaman ini tidak punya tes sama sekali.
 */
class PengaturanInstansiTest extends TestCase
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

        PengaturanInstansi::query()->create([
            'nama_instansi' => 'PEMERINTAH DESA UJI',
            'jenis_instansi' => 'Pemerintah Desa',
            'alamat_instansi' => 'Jalan Merdeka 1',
            'no_telp' => '0341-000000',
            'email' => 'desa@uji.test',
        ]);
    }

    public function test_halaman_menampilkan_logo_yang_sudah_ada_dan_tempat_pratinjau(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logo/logo-lama.png', 'isi-lama');
        PengaturanInstansi::first()->update(['logo_path' => 'logo/logo-lama.png']);

        $this->actingAs($this->admin)
            ->get(route('pengaturan-instansi.edit'))
            ->assertOk()
            ->assertSee('PEMERINTAH DESA UJI')
            ->assertSee('Logo Saat Ini')
            ->assertSee('storage/logo/logo-lama.png', false)
            // Pratinjau client-side: elemen + <input type=file name=logo> harus ada,
            // JS-nya yang mengisi gambarnya saat berkas dipilih.
            ->assertSee('id="logoPratinjau"', false)
            ->assertSee('name="logo"', false)
            ->assertSee('Pratinjau logo baru');
    }

    public function test_pengaturan_hanya_untuk_admin(): void
    {
        $staf = User::forceCreate([
            'nama_lengkap' => 'Staf',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $this->actingAs($staf)->get(route('pengaturan-instansi.edit'))->assertForbidden();
        $this->actingAs($staf)->put(route('pengaturan-instansi.update'), [])->assertForbidden();
    }

    public function test_logo_baru_tersimpan_dan_logo_lama_dibuang(): void
    {
        Storage::fake('public');
        $pengaturan = PengaturanInstansi::first();
        $pengaturan->update(['logo_path' => 'logo/lama.png']);
        Storage::disk('public')->put('logo/lama.png', 'lama');

        $this->actingAs($this->admin)
            ->put(route('pengaturan-instansi.update'), [
                'nama_instansi' => 'PEMERINTAH DESA UJI',
                'jenis_instansi' => 'Pemerintah Desa',
                'alamat_instansi' => 'Jalan Merdeka 1',
                'no_telp' => '0341-000000',
                'email' => 'desa@uji.test',
                'logo' => UploadedFile::fake()->image('baru.jpg', 200, 200),
            ])
            ->assertSessionHasNoErrors();

        $baru = $pengaturan->fresh()->logo_path;

        $this->assertNotSame('logo/lama.png', $baru);
        Storage::disk('public')->assertExists($baru);
        Storage::disk('public')->assertMissing('logo/lama.png');
    }
}
