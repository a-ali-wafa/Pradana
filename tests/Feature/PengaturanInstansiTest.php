<?php

namespace Tests\Feature;

use App\Models\PengaturanInstansi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengaturan Instansi (kop surat + logo). Kelas ini dibuat 4 Okt 2026 — sebelumnya
 * halaman ini tidak punya tes sama sekali — lalu diperluas 5 Okt 2026 untuk dua hal
 * yang justru bikin user melapor: logo tidak tampil, dan kop tidak bisa bertingkat.
 *
 * Tes logo di bawah SENGAJA tidak memeriksa `Storage::url()`: akar masalahnya dulu
 * bukan kode yang salah, tapi URL publik yang bergantung symlink `public/storage`.
 * Sekarang logo dilayani route `instansi.logo`, dan route itu harus bisa diakses
 * GUEST (halaman login menampilkan kop) — itu yang diuji, bukan cuma "ada gambar".
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
            'nama_kabupaten' => 'Banyumas',
            'nama_kecamatan' => 'Kedung Banteng',
            'jenis_instansi' => 'Pemerintah Desa',
            'alamat_instansi' => 'Jalan Merdeka 1',
            'kode_pos' => '53182',
            'no_telp' => '0341-000000',
            'email' => 'desa@uji.test',
        ]);
    }

    public function test_halaman_menampilkan_logo_lewat_route_bukan_symlink(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logo/logo-lama.png', 'isi-lama');
        PengaturanInstansi::first()->update(['logo_path' => 'logo/logo-lama.png']);

        $this->actingAs($this->admin)
            ->get(route('pengaturan-instansi.edit'))
            ->assertOk()
            ->assertSee('PEMERINTAH DESA UJI')
            ->assertSee('/instansi/logo?', false)
            // Tidak boleh ada lagi `public/storage` di halaman ini: itulah
            // alamat yang dulu mati diam-diam karena symlink belum dibuat.
            ->assertDontSee('storage/logo', false)
            // Pratinjau client-side + panel kop (dua kolom, 5 Okt 2026).
            ->assertSee('id="logoPratinjau"', false)
            ->assertSee('name="logo"', false)
            ->assertSee('Pratinjau Kop Surat', false)
            ->assertSee('Pratinjau logo baru')
            ->assertSee('name="nama_kabupaten"', false)
            ->assertSee('name="kode_pos"', false);
    }

    public function test_logo_bisa_diakses_tamu_dan_404_kalau_belum_ada(): void
    {
        // Halaman login (guest) menampilkan kop, jadi route logo memang sengaja
        // di luar grup auth — ini yang menjaganya tidak ikut dipagari `auth`.
        $this->get(route('instansi.logo'))->assertNotFound();

        Storage::fake('public');
        Storage::disk('public')->put('logo/aktif.png', 'ISI-LOGO');
        PengaturanInstansi::first()->update(['logo_path' => 'logo/aktif.png']);

        $this->get(route('instansi.logo'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // Isi berkas tidak dicek lewat `$response->getContent()`: BinaryFileResponse
        // menulis ke keluaran saat dikirim, bukan saat di-build.
        $this->assertSame('ISI-LOGO', Storage::disk('public')->get('logo/aktif.png'));
    }

    public function test_kop_bertingkat_tersimpan_dan_dipakai_helper(): void
    {
        $pengaturan = PengaturanInstansi::first();

        $this->assertSame(
            ['PEMERINTAH KABUPATEN BANYUMAS', 'KECAMATAN KEDUNG BANTENG'],
            $pengaturan->kopBarisAtas()
        );

        $alamat = $pengaturan->kopAlamat();
        $this->assertStringContainsString('Jalan Merdeka 1, Kode Pos 53182', $alamat[0]);
        $this->assertStringContainsString('Telp. 0341-000000', $alamat[1]);

        // "Pemerintah Desa" jadi "Kepala Desa", bukan "Kepala Pemerintah Desa".
        $this->assertSame('Kepala Desa', $pengaturan->sebutanPemimpin());
        // Kata "Pemerintah" dibuang untuk baris tempat tanggal surat.
        $this->assertSame('DESA UJI', $pengaturan->tempatSurat());
    }

    public function test_kop_satu_baris_tetap_sah_dan_kontak_boleh_kosong(): void
    {
        // Form selalu mengirim semua field; string kosong diubah jadi null oleh
        // middleware ConvertEmptyStringsToNull — jadi "hapus baris Kabupaten"
        // terjadi lewat submit kosong, bukan lewat field yang tidak dikirim.
        $this->actingAs($this->admin)
            ->put(route('pengaturan-instansi.update'), [
                'nama_instansi' => 'SEKRETARIAT PANITIA ARSIP',
                'nama_kabupaten' => '',
                'nama_kecamatan' => '',
                'jenis_instansi' => 'Pemerintah Desa',
                'alamat_instansi' => 'Alamat kantor panitia',
                'kode_pos' => '',
                'no_telp' => '',
                'email' => '',
            ])
            ->assertSessionHasNoErrors();

        $pengaturan = PengaturanInstansi::first();

        $this->assertSame('SEKRETARIAT PANITIA ARSIP', $pengaturan->nama_instansi);
        $this->assertNull($pengaturan->nama_kabupaten);
        $this->assertNull($pengaturan->no_telp);
        $this->assertNull($pengaturan->email);
        // Helper tidak boleh menghasilkan baris hantu untuk kolom yang kosong.
        $this->assertSame([], $pengaturan->kopBarisAtas());
        $this->assertSame(['Alamat kantor panitia'], $pengaturan->kopAlamat());
        $this->assertSame('PANITIA ARSIP', $pengaturan->tempatSurat());
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
                'logo' => UploadedFile::fake()->image('baru.jpg', 200, 200),
            ])
            ->assertSessionHasNoErrors();

        $baru = $pengaturan->fresh()->logo_path;

        $this->assertNotSame('logo/lama.png', $baru);
        Storage::disk('public')->assertExists($baru);
        Storage::disk('public')->assertMissing('logo/lama.png');

        // URL logo harus lewat route dan berubah bersama nama berkasnya (cache
        // buster); kalau tidak, browser tetap menampilkan logo lama sesudah diganti.
        $this->assertStringContainsString('/instansi/logo?v=', $pengaturan->fresh()->logoUrl());
    }
}
