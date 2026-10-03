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
 * L-07/L-10/L-20: dua tingkat role dan batas nyata antaranya.
 *
 * Tes ini juga menutup dua bug yang selama ini TERSEMBUNYI karena semua uji
 * manual sebelumnya dijalankan sebagai admin:
 * - LampiranPolicy lama melarang staf mengunduh file yang bukan miliknya
 *   (bertentangan dengan L-09/L-10: arsip kantor, tidak ada kepemilikan);
 * - pegawai bisa mengoreksi nomor surat keluar lewat form edit (L-20 melarang).
 */
class OtorisasiDuaTingkatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pegawai;

    private KlasifikasiPrimer $klasifikasi;

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
            'nama_lengkap' => 'Staf Satu',
            'email' => 'staf1@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $this->klasifikasi = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);
    }

    private function suratKeluar(User $pembuat, string $nomor = '001/01/I/2026'): SuratKeluar
    {
        return SuratKeluar::forceCreate([
            'user_id' => $pembuat->id,
            'penerima' => 'Warga',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'nomor_surat' => $nomor,
            'perihal' => 'Pemberitahuan',
            'tanggal_surat' => '2026-01-05',
        ]);
    }

    public function test_database_menolak_nilai_role_lama(): void
    {
        // Dilarang diam-diam: menulis 'perangkat' setelah enum dipangkas harus
        // memecah database, bukan disimpan jadi string kosong.
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::forceCreate([
            'nama_lengkap' => 'Kembali ke Masa Lalu',
            'email' => 'lama@example.test',
            'pin' => bcrypt('123456'),
            'role' => 'perangkat',
        ]);
    }

    public function test_label_role_hanya_dua_dan_admin_identik_dengan_kepala(): void
    {
        $this->assertSame(['admin' => 'Admin (Kepala)', 'pegawai' => 'Pegawai'], User::peranTersedia());
        $this->assertTrue($this->admin->isAdmin());
        $this->assertFalse($this->pegawai->isAdmin());
        $this->assertSame('Pegawai', $this->pegawai->labelRole());
    }

    public function test_form_user_baru_menolak_role_lama(): void
    {
        foreach (['perangkat', 'kepala'] as $roleLama) {
            $this->actingAs($this->admin)
                ->post(route('users.store'), [
                    'nama_lengkap' => 'Korban Role Lama',
                    'email' => $roleLama.'@example.test',
                    'pin' => '123456',
                    'pin_confirmation' => '123456',
                    'role' => $roleLama,
                ])
                ->assertSessionHasErrors('role');
        }

        $this->assertDatabaseCount('users', 2);
    }

    public function test_pegawai_bisa_membuat_pegawai_tapi_admin_hanya_lewat_form_admin(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'nama_lengkap' => 'Staf Dua',
                'email' => 'staf2@example.test',
                'pin' => '123456',
                'pin_confirmation' => '123456',
                'role' => 'pegawai',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('pegawai', User::where('email', 'staf2@example.test')->value('role'));

        // Route users.* memang admin-only (L-07).
        $this->actingAs($this->pegawai)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_staf_lain_boleh_unduh_lampiran_yang_bukan_miliknya(): void
    {
        $surat = $this->suratKeluar($this->admin);

        $path = 'surat-keluar/01 - Umum/'.$surat->id.'/penawaran.pdf';
        Storage::disk('arsip')->put($path, 'isi');

        $lampiran = Lampiran::forceCreate([
            'lampiranable_id' => $surat->id,
            'lampiranable_type' => SuratKeluar::class,
            'disk' => 'arsip',
            'path' => $path,
            'nama_file' => 'penawaran.pdf',
            'mime_type' => 'application/pdf',
            'diunggah_oleh' => $this->admin->id,
        ]);

        $this->actingAs($this->pegawai)
            ->get(route('lampiran.download', $lampiran))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pegawai_tidak_boleh_mengubah_nomor_surat_keluar(): void
    {
        $surat = $this->suratKeluar($this->pegawai);

        $this->actingAs($this->pegawai)
            ->put(route('surat-keluar.update', $surat), $this->dataSurat($surat, [
                'nomor_surat' => '999/01/I/2026',
            ]))
            ->assertSessionHasErrors('nomor_surat');

        $this->assertSame('001/01/I/2026', $surat->fresh()->nomor_surat);
    }

    public function test_pegawai_boleh_mengubah_bagian_lain_tanpa_menyentuh_nomor(): void
    {
        $surat = $this->suratKeluar($this->pegawai);

        $this->actingAs($this->pegawai)
            ->put(route('surat-keluar.update', $surat), $this->dataSurat($surat, [
                'perihal' => 'Pemberitahuan revisi',
            ]))
            ->assertSessionHasNoErrors();

        $surat->refresh();
        $this->assertSame('Pemberitahuan revisi', $surat->perihal);
        $this->assertSame('001/01/I/2026', $surat->nomor_surat);
    }

    public function test_admin_boleh_mengoreksi_nomor_surat_keluar(): void
    {
        $surat = $this->suratKeluar($this->pegawai);

        $this->actingAs($this->admin)
            ->put(route('surat-keluar.update', $surat), $this->dataSurat($surat, [
                'nomor_surat' => '002/01/I/2026',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('002/01/I/2026', $surat->fresh()->nomor_surat);
    }

    public function test_form_user_baru_hanya_menampilkan_dua_role(): void
    {
        $halaman = $this->actingAs($this->admin)
            ->get(route('users.create'))
            ->assertOk();

        // Label panjang dipakai di UI, jadi yang harus muncul di <option>.
        $halaman->assertSee('Admin (Kepala)')->assertSee('Pegawai');

        foreach (['>Perangkat<', '>Kepala<', 'value="perangkat"', 'value="kepala"'] as $sisaRoleLama) {
            $halaman->assertDontSee($sisaRoleLama, false);
        }
    }

    public function test_daftar_user_menampilkan_label_role_yang_baru(): void
    {
        $halaman = $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk();

        $halaman->assertSee('Admin (Kepala)')
            ->assertSee('Pegawai')
            ->assertDontSee('>Perangkat<', false)
            ->assertDontSee('>Kepala<', false);
    }

    public function test_dashboard_menggabungkan_surat_masuk_dan_keluar(): void
    {
        // H3: primer 01 = 1 surat masuk + 1 surat keluar = 2;
        // primer 02 = 3 surat masuk. Kalau hanya surat masuk yang dihitung
        // (perilaku lama), urutannya jadi 02 (3) lalu 01 (1).
        $lain = KlasifikasiPrimer::forceCreate(['kode' => '02', 'nama' => 'Perencanaan']);

        SuratKeluar::forceCreate([
            'user_id' => $this->pegawai->id,
            'penerima' => 'Warga',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'nomor_surat' => '001/01/I/2026',
            'perihal' => 'Keluar satu',
            'tanggal_surat' => '2026-01-05',
        ]);

        foreach (range(1, 3) as $i) {
            SuratMasuk::forceCreate([
                'user_id' => $this->pegawai->id,
                'pengirim' => 'Kecamatan',
                'klasifikasi_primer_id' => $lain->id,
                'nomor_surat' => 'MSK-'.$i,
                'perihal' => 'Masuk '.$i,
                'tanggal_surat' => '2026-01-0'.$i,
                'tanggal_diterima' => '2026-01-0'.$i,
            ]);
        }

        $this->actingAs($this->pegawai)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('02 - Perencanaan')
            ->assertSee('3 surat');
    }

    /**
     * @param  array<string, mixed>  $ubah
     * @return array<string, mixed>
     */
    private function dataSurat(SuratKeluar $surat, array $ubah): array
    {
        return array_merge([
            'penerima' => $surat->penerima,
            'klasifikasi_primer_id' => $surat->klasifikasi_primer_id,
            'sifat' => $surat->sifat ?? 'biasa',
            'tanggal_surat' => '2026-01-05',
            'perihal' => $surat->perihal,
            'status_berkas' => 'asli',
            'status_arsip' => 'aktif',
            'nomor_surat' => $surat->nomor_surat,
        ], $ubah);
    }
}
