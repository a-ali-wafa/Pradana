<?php

namespace Tests\Feature;

use App\Models\Aktivitas;
use App\Models\DrafKontenSuratKeluar;
use App\Models\KlasifikasiPrimer;
use App\Models\PemusnahanArsip;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L-15/P4 (pencarian ikut menjangkau ISI), H1/L-14 (filter sama dengan daftar
 * surat), H7 (pagination), L-22 (UI log untuk admin),
 * E6 (retensi log: >2 tahun dibuang KECUALI jejak pemusnahan).
 *
 * CATATAN 5 Okt 2026: halaman `/pencarian` (PencarianController + view-nya) sudah
 * DIHAPUS atas permintaan user — tiap daftar surat sudah punya kotak "cari" sendiri.
 * Bagian pencarian di bawah kini menguji `?cari=` di `surat-masuk.index` dan
 * `surat-keluar.index`, karena L-15 (menggali `ringkasan` dan `isi_surat`) harus
 * tetap hidup meski halaman gabungannya hilang.
 */
class PencarianDanLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pegawai;

    private KlasifikasiPrimer $klasifikasi;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->klasifikasi = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);
    }

    private function masuk(array $ubah = []): SuratMasuk
    {
        return SuratMasuk::forceCreate(array_merge([
            'user_id' => $this->pegawai->id,
            'pengirim' => 'Kecamatan Gondanglegi',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'nomor_surat' => 'MSK-'.uniqid(),
            'perihal' => 'Undangan musyawarah desa',
            'ringkasan' => 'Pembahasan anggaran pembangunan jalan',
            'tanggal_surat' => '2026-02-01',
            'tanggal_diterima' => '2026-02-02',
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ], $ubah));
    }

    private function keluar(array $ubah = []): SuratKeluar
    {
        return SuratKeluar::forceCreate(array_merge([
            'user_id' => $this->pegawai->id,
            'penerima' => 'Warga RT 01',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'nomor_surat' => 'KLR-'.uniqid(),
            'perihal' => 'Pemberitahuan kerja bakti',
            'tanggal_surat' => '2026-03-01',
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ], $ubah));
    }

    public function test_cari_di_daftar_surat_masuk_menjangkau_ringkasan(): void
    {
        $cocok = $this->masuk(['nomor_surat' => 'ISI-1', 'ringkasan' => 'Pembahasan anggaran pembangunan jalan']);
        $tidak = $this->masuk(['nomor_surat' => 'ISI-2', 'ringkasan' => 'Undangan rapat tani']);

        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index', ['cari' => 'pembangunan jalan']))
            ->assertOk()
            ->assertSee($cocok->nomor_surat)
            ->assertDontSee($tidak->nomor_surat);
    }

    public function test_cari_di_daftar_surat_keluar_menjangkau_isi_draf(): void
    {
        $cocok = $this->keluar(['nomor_surat' => 'DRAF-1']);
        $tidak = $this->keluar(['nomor_surat' => 'DRAF-2', 'perihal' => 'Surat keterangan domisili']);

        // "irigasi barat" hanya ada di isi draf — kolom surat keluar tidak
        // menyimpannya sama sekali, jadi tes ini benar-benar menguji whereHas().
        DrafKontenSuratKeluar::forceCreate([
            'surat_keluar_id' => $cocok->id,
            'isi_surat' => 'Sehubungan dengan jadwal pengurasan saluran irigasi barat, kami mengundang warga.',
        ]);

        $this->actingAs($this->pegawai)
            ->get(route('surat-keluar.index', ['cari' => 'irigasi barat']))
            ->assertOk()
            ->assertSee($cocok->nomor_surat)
            ->assertDontSee($tidak->nomor_surat);
    }

    public function test_arsip_yang_dihapus_lunak_tidak_muncul_di_hasil_cari(): void
    {
        $terhapus = $this->masuk(['nomor_surat' => 'HILANG-001', 'perihal' => 'Laporan yang akan dibuang']);
        $terhapus->delete();

        // Soft delete scope membuat ini otomatis, tapi dulunya cuma dibuktikan di
        // halaman pencarian — sekarang di daftar, tempat orang benar-benar mencari.
        $this->actingAs($this->admin)
            ->get(route('surat-masuk.index', ['cari' => 'Laporan yang akan dibuang']))
            ->assertOk()
            ->assertSee('Belum ada surat masuk yang cocok dengan filter');
    }

    public function test_cari_digabung_dengan_filter_lain(): void
    {
        $masuk = $this->masuk(['nomor_surat' => 'GABUNG-1', 'perihal' => 'kata unik']);
        $keluar = $this->keluar(['nomor_surat' => 'GABUNG-2', 'perihal' => 'kata unik']);

        // Klasifikasi lain di daftar surat masuk -> tidak ada hasil.
        $lain = KlasifikasiPrimer::forceCreate(['kode' => '09', 'nama' => 'Lainnya']);

        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index', ['cari' => 'kata unik', 'klasifikasi_primer_id' => $lain->id]))
            ->assertSee('Belum ada surat masuk yang cocok dengan filter');

        // Status arsip: yang inaktif tersaring, yang aktif tetap ada.
        $keluar->update(['status_arsip' => 'inaktif']);

        $this->actingAs($this->pegawai)
            ->get(route('surat-keluar.index', ['cari' => 'kata unik', 'status_arsip' => 'aktif']))
            ->assertSee('kata unik')
            ->assertDontSee($keluar->nomor_surat);

        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index', ['cari' => 'kata unik']))
            ->assertSee($masuk->nomor_surat);
    }

    public function test_hasil_cari_di_daftar_tetap_dipaginate(): void
    {
        foreach (range(1, 25) as $i) {
            $this->masuk([
                'nomor_surat' => 'PAGE-M'.$i,
                'perihal' => 'dokumen berjenjang',
                'tanggal_surat' => (new \DateTime('2026-01-01'))->modify("+{$i} days")->format('Y-m-d'),
            ]);
        }

        $halaman1 = $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index', ['cari' => 'dokumen berjenjang']))
            ->assertOk();

        // Urutan daftar surat masuk = tanggal_diterima desc; yang paling baru dibuat
        // ada di halaman 1, yang tertua (PAGE-M1..M5) terlempar ke halaman 2.
        $halaman1->assertSee('PAGE-M25')->assertDontSee('PAGE-M5');

        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index', ['cari' => 'dokumen berjenjang', 'page' => 2]))
            ->assertOk()
            ->assertSee('PAGE-M5')
            ->assertDontSee('PAGE-M25');
    }

    public function test_halaman_pencarian_lama_sudah_dihapus(): void
    {
        // 5 Okt 2026: `/pencarian` dihapus atas permintaan user. Kalau route ini
        // ternyata masih hidup, berarti ada yang mendaftarkannya lagi tanpa sengaja.
        $this->actingAs($this->pegawai)->get('/pencarian')->assertNotFound();
    }

    public function test_tanpa_kata_kunci_daftar_memperlihatkan_semua(): void
    {
        $this->masuk(['nomor_surat' => 'SEMUA-1']);

        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.index'))
            ->assertOk()
            ->assertSee('SEMUA-1');
    }

    public function test_log_aktivitas_hanya_untuk_admin(): void
    {
        Aktivitas::forceCreate([
            'user_id' => $this->pegawai->id,
            'aksi' => 'Mengunggah lampiran: scan-pembayaran.pdf',
        ]);

        $this->actingAs($this->pegawai)
            ->get(route('aktivitas.index'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('aktivitas.index'))
            ->assertOk()
            ->assertSee('Mengunggah lampiran: scan-pembayaran.pdf')
            ->assertSee('Staf Arsip');
    }

    // Terpisah: setelah actingAs() di atas, user tetap login untuk sisa request
    // di test yang sama, jadi cek "tamu ditolak" tidak bisa digabung.
    public function test_tamu_ditolak_di_halaman_log(): void
    {
        $this->get(route('aktivitas.index'))->assertRedirect(route('login'));
    }

    public function test_log_bisa_disaring_per_user_dan_teks(): void
    {
        Aktivitas::forceCreate(['user_id' => $this->pegawai->id, 'aksi' => 'Mengisi draf surat keluar: A']);
        Aktivitas::forceCreate(['user_id' => $this->admin->id, 'aksi' => 'Menyetujui pemusnahan arsip (2 surat)']);

        $this->actingAs($this->admin)
            ->get(route('aktivitas.index', ['user_id' => $this->pegawai->id]))
            ->assertSee('Mengisi draf surat keluar')
            ->assertDontSee('Menyetujui pemusnahan arsip');

        $this->actingAs($this->admin)
            ->get(route('aktivitas.index', ['cari' => 'Menyetujui']))
            ->assertSee('Menyetujui pemusnahan arsip')
            ->assertDontSee('Mengisi draf surat keluar');
    }

    public function test_retensi_log_membuang_lama_tapi_mempertahankan_jejak_pemusnahan(): void
    {
        $pemusnahan = PemusnahanArsip::forceCreate([
            'status' => 'disetujui',
            'diajukan_oleh' => $this->pegawai->id,
            'nomor_berita_acara' => 'BA-001/I/2020',
        ]);

        // 3 tahun lalu -> melewati batas 2 tahun.
        $lama = Aktivitas::forceCreate([
            'user_id' => $this->pegawai->id,
            'aksi' => 'Menghapus surat masuk: laporan lama',
        ]);
        $lama->forceFill(['created_at' => now()->subYears(3)])->saveQuietly();

        $lamaPemusnahan = Aktivitas::forceCreate([
            'user_id' => $this->admin->id,
            'aksi' => 'Menyetujui pemusnahan arsip (1 surat) — Berita Acara BA-001/I/2020',
            'subjek_type' => PemusnahanArsip::class,
            'subjek_id' => $pemusnahan->id,
        ]);
        $lamaPemusnahan->forceFill(['created_at' => now()->subYears(3)])->saveQuietly();

        // Tanpa subjek pemusnahan tapi teksnya jelas pemusnahan -> tetap bertahan.
        $lamaTeksPemusnahan = Aktivitas::forceCreate([
            'user_id' => $this->admin->id,
            'aksi' => 'Memusnahkan surat masuk: laporan tahunan',
        ]);
        $lamaTeksPemusnahan->forceFill(['created_at' => now()->subYears(3)])->saveQuietly();

        $baru = Aktivitas::forceCreate([
            'user_id' => $this->pegawai->id,
            'aksi' => 'Mengubah surat keluar: perihal',
        ]);

        $this->artisan('arsip:bersihkan-log')->assertSuccessful();

        $this->assertDatabaseMissing('aktivitas', ['id' => $lama->id]);
        $this->assertDatabaseHas('aktivitas', ['id' => $baru->id]);
        $this->assertDatabaseHas('aktivitas', ['id' => $lamaPemusnahan->id]);
        $this->assertDatabaseHas('aktivitas', ['id' => $lamaTeksPemusnahan->id]);
    }

    public function test_dry_run_retensi_tidak_menghapus(): void
    {
        $lama = Aktivitas::forceCreate(['user_id' => $this->pegawai->id, 'aksi' => 'Menghapus surat masuk: x']);
        $lama->forceFill(['created_at' => now()->subYears(3)])->saveQuietly();

        $this->artisan('arsip:bersihkan-log', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('aktivitas', ['id' => $lama->id]);
    }
}
