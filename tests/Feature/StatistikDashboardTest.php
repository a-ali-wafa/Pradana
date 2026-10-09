<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Angka dashboard setelah statistik diringkas jadi dua query (Fase 1, 9 Okt 2026).
 *
 * Yang diuji di sini adalah KESETARAAN dengan bentuk lama (sembilan `count()`
 * terpisah + `whereMonth()/whereYear()`), bukan angka baru: refactor yang
 * mengubah bentuk query tidak boleh mengubah satu pun angka di layar.
 *
 * Batas bulan dipilih yang paling mudah salah di refactor mana pun: surat yang
 * diterima pada hari TERAKHIR bulan berjalan harus tetap masuk "bulan ini",
 * dan surat tanggal 1 bulan berikutnya harus tetap keluar.
 */
class StatistikDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $pegawai;

    private KlasifikasiPrimer $klasifikasi;

    private int $nomor = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $this->klasifikasi = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);
    }

    private function berikutnya(): string
    {
        return 'DM-'.(++$this->nomor);
    }

    private function masuk(string $tanggalDiterima, string $status = 'aktif', string $sifat = 'biasa'): SuratMasuk
    {
        return SuratMasuk::forceCreate([
            'user_id' => $this->pegawai->id,
            'pengirim' => 'Kecamatan',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'nomor_surat' => $this->berikutnya(),
            'perihal' => 'Perihal uji dashboard',
            'tanggal_surat' => $tanggalDiterima,
            'tanggal_diterima' => $tanggalDiterima,
            'status_arsip' => $status,
            'sifat' => $sifat,
        ]);
    }

    private function keluar(string $tanggalSurat, string $status = 'aktif', string $sifat = 'biasa'): SuratKeluar
    {
        return SuratKeluar::forceCreate([
            'user_id' => $this->pegawai->id,
            'penerima' => 'Warga',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'nomor_surat' => $this->berikutnya(),
            'perihal' => 'Surat keterangan',
            'tanggal_surat' => $tanggalSurat,
            'status_arsip' => $status,
            'sifat' => $sifat,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function stats(): array
    {
        $respons = $this->actingAs($this->pegawai)->get(route('dashboard'));

        $respons->assertOk()->assertViewIs('dashboard.index');

        /** @var array<string, int> $stats */
        $stats = $respons->viewData('stats');

        return $stats;
    }

    public function test_bulan_ini_mencakup_hari_terakhir_bulan_berjalan(): void
    {
        $akhirBulan = Carbon::now()->endOfMonth()->toDateString();
        $awalBulanBerikutnya = Carbon::now()->startOfMonth()->addMonthNoOverflow()->toDateString();
        $bulanLalu = Carbon::now()->startOfMonth()->subDay()->toDateString();

        $this->masuk($bulanLalu);
        $this->masuk($akhirBulan);
        $this->masuk($awalBulanBerikutnya);
        $this->keluar($akhirBulan);
        $this->keluar($awalBulanBerikutnya);

        $stats = $this->stats();

        $this->assertSame(1, $stats['surat_masuk_bulan_ini'], 'Surat hari terakhir bulan harus ikut, hari pertama bulan depan tidak.');
        $this->assertSame(1, $stats['surat_keluar_bulan_ini']);

        // Total tetap menghitung semuanya (bulan lalu & bulan depan masuk hitungan
        // seluruh arsip, hanya "bulan ini" yang dipotong periode).
        $this->assertSame(3, $stats['total_surat_masuk']);
        $this->assertSame(2, $stats['total_surat_keluar']);
    }

    public function test_status_arsip_dan_sifat_terhitung_sama_seperti_sebelum_diringkas(): void
    {
        $hariIni = Carbon::now()->toDateString();

        $this->masuk($hariIni);                       // aktif, biasa
        $this->masuk($hariIni, 'inaktif');            // inaktif
        $this->masuk($hariIni, 'aktif', 'mendesak');  // mendesak + aktif
        $this->keluar($hariIni, 'aktif', 'mendesak'); // mendesak + aktif
        $this->keluar($hariIni, 'inaktif', 'rahasia'); // inaktif

        $stats = $this->stats();

        $this->assertSame(2, $stats['surat_masuk_aktif']);
        $this->assertSame(1, $stats['surat_masuk_inaktif']);
        $this->assertSame(1, $stats['surat_keluar_aktif']);
        $this->assertSame(1, $stats['surat_keluar_inaktif']);

        // Gabungan kedua tabel (kartu "mendesak" di dashboard memang satu angka).
        $this->assertSame(2, $stats['surat_mendesak_aktif']);
    }

    public function test_surat_di_tempat_sampah_tidak_memengaruhi_statistik(): void
    {
        $hariIni = Carbon::now()->toDateString();

        $sudahDihapus = $this->masuk($hariIni);
        $this->masuk($hariIni);

        $sudahDihapus->delete();

        $stats = $this->stats();

        $this->assertSame(1, $stats['total_surat_masuk']);
        $this->assertSame(1, $stats['surat_masuk_bulan_ini']);
        $this->assertSame(1, $stats['surat_masuk_aktif']);
    }

    public function test_grafik_klasifikasi_menggabungkan_masuk_dan_keluar(): void
    {
        // H3: dulu hanya surat masuk. Klasifikasi yang cuma dipakai surat keluar
        // tidak muncul sama sekali di dashboard.
        $hariIni = Carbon::now()->toDateString();

        $keuangan = KlasifikasiPrimer::forceCreate(['kode' => '02', 'nama' => 'Keuangan']);

        $suratKeluar = $this->keluar($hariIni);
        $suratKeluar->klasifikasi_primer_id = $keuangan->id;
        $suratKeluar->save();

        $respons = $this->actingAs($this->pegawai)->get(route('dashboard'))->assertOk();

        $populer = $respons->viewData('klasifikasiTerpopuler');

        $this->assertCount(1, $populer);
        $this->assertSame('Keuangan', $populer[0]->primer->nama);
        $this->assertSame(1, (int) $populer[0]->total);

        // Panjang batang dihitung di controller sekarang, bukan di dalam loop view.
        $this->assertSame(1, (int) $respons->viewData('maksTotalKlasifikasi'));
    }
}
