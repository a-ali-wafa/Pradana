<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\PemusnahanArsip;
use App\Models\PengajuanHapusLampiran;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * I1=b (ekspor laporan) + I2=b (Buku Agenda) + H1=b (notifikasi antrian
 * approval), keputusan 3 Okt 2026.
 */
class LaporanAgendaTest extends TestCase
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
            'nomor_surat' => 'CSV-M'.uniqid(),
            'perihal' => 'Undangan musyawarah desa',
            'tanggal_surat' => '2026-02-10',
            'tanggal_diterima' => '2026-02-11',
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
            'nomor_surat' => 'CSV-K'.uniqid(),
            'perihal' => 'Pemberitahuan kerja bakti',
            'tanggal_surat' => '2026-02-12',
            'status_arsip' => 'aktif',
            'sifat' => 'penting',
        ], $ubah));
    }

    private function isiRekap(array $query): string
    {
        return $this->actingAs($this->pegawai)
            ->get(route('laporan.rekap', $query))
            ->assertOk()
            ->streamedContent();
    }

    public function test_rekap_csv_hanya_memuat_surat_dalam_periode(): void
    {
        $dalam = $this->masuk(['nomor_surat' => 'REK-001', 'perihal' => 'Undangan musyawarah']);
        $luarPeriode = $this->masuk(['nomor_surat' => 'REK-999', 'perihal' => 'Laporan tahun lalu', 'tanggal_surat' => '2026-01-31']);

        $isi = $this->isiRekap(['dari' => '2026-02-01', 'sampai' => '2026-02-28']);

        // BOM: tanpanya Excel versi Indonesia membuka UTF-8 sebagai ANSI.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi);

        $baris = array_values(array_filter(
            explode("\n", substr($isi, 3)),
            fn ($b) => trim($b) !== ''
        ));

        // `str_getcsv`, bukan explode(';'): PHP membungkus field yang berisi
        // spasi dalam tanda kutip ("Nomor Surat"), jadi perlu di-decode.
        $kepala = str_getcsv($baris[0], ';');
        $data = str_getcsv($baris[1], ';');

        // Pemisah `;` (bukan `,`) supaya angka/tanggal tidak tertukar di Excel ID.
        $this->assertCount(12, $kepala);
        $this->assertContains('Nomor Surat', $kepala);
        $this->assertContains('Petugas', $kepala);

        $this->assertSame('REK-001', $data[4]);
        $this->assertSame('Surat Masuk', $data[1]);
        $this->assertSame('2026-02-11', $data[3]);
        $this->assertSame('Staf Arsip', $data[11]);
        $this->assertCount(2, $baris, 'hanya 1 baris data di luar header');
        $this->assertStringNotContainsString($luarPeriode->nomor_surat, $isi);
        $this->assertStringNotContainsString($luarPeriode->perihal, $isi);
        $this->assertStringContainsString($dalam->pengirim, $isi);
    }

    public function test_rekap_menggabungkan_surat_masuk_dan_keluar(): void
    {
        $this->masuk(['nomor_surat' => 'GAB-M', 'perihal' => 'dokumen gabungan']);
        $this->keluar(['nomor_surat' => 'GAB-K', 'perihal' => 'dokumen gabungan']);

        $isi = $this->isiRekap(['dari' => '2026-02-01', 'sampai' => '2026-02-28']);

        $this->assertStringContainsString('Surat Masuk', $isi);
        $this->assertStringContainsString('Surat Keluar', $isi);
        $this->assertStringContainsString('GAB-M', $isi);
        $this->assertStringContainsString('GAB-K', $isi);
    }

    public function test_rekap_filter_jenis_klasifikasi_dan_status_arsip(): void
    {
        $masuk = $this->masuk(['nomor_surat' => 'F-M', 'perihal' => 'dokumen saring']);
        $keluar = $this->keluar(['nomor_surat' => 'F-K', 'perihal' => 'dokumen saring']);
        $lain = KlasifikasiPrimer::forceCreate(['kode' => '09', 'nama' => 'Lainnya']);
        $this->masuk(['nomor_surat' => 'F-X', 'perihal' => 'dokumen saring', 'klasifikasi_primer_id' => $lain->id]);

        $hanyaMasuk = $this->isiRekap(['dari' => '2026-02-01', 'sampai' => '2026-02-28', 'jenis' => 'masuk']);
        $this->assertStringContainsString($masuk->nomor_surat, $hanyaMasuk);
        $this->assertStringNotContainsString($keluar->nomor_surat, $hanyaMasuk);

        $saringKlasifikasi = $this->isiRekap([
            'dari' => '2026-02-01', 'sampai' => '2026-02-28', 'klasifikasi_primer_id' => $lain->id,
        ]);
        $this->assertStringContainsString('F-X', $saringKlasifikasi);
        $this->assertStringNotContainsString('F-M', $saringKlasifikasi);

        $keluar->update(['status_arsip' => 'inaktif']);
        $hanyaAktif = $this->isiRekap(['dari' => '2026-02-01', 'sampai' => '2026-02-28', 'status_arsip' => 'aktif']);
        $this->assertStringContainsString('F-M', $hanyaAktif);
        $this->assertStringNotContainsString('F-K', $hanyaAktif);
    }

    public function test_rekap_mengecualikan_surat_di_tempat_sampah(): void
    {
        $this->masuk(['nomor_surat' => 'SAM-M', 'perihal' => 'dokumen sampah']);
        $this->masuk(['nomor_surat' => 'SAM-X', 'perihal' => 'dokumen sampah'])->delete();

        $isi = $this->isiRekap(['dari' => '2026-02-01', 'sampai' => '2026-02-28']);

        $this->assertStringContainsString('SAM-M', $isi);
        $this->assertStringNotContainsString('SAM-X', $isi);
        $this->assertSame(1, substr_count($isi, 'dokumen sampah'));
    }

    public function test_periode_terbalik_dibalik_bukan_dokumen_kosong(): void
    {
        $this->masuk(['nomor_surat' => 'BAL-001', 'perihal' => 'dokumen terbalik']);

        $respons = $this->actingAs($this->pegawai)
            ->get(route('laporan.rekap', ['dari' => '2026-02-28', 'sampai' => '2026-02-01']));

        $respons->assertOk();
        $this->assertStringContainsString(
            'rekap-pradana-2026-02-01-s-d-2026-02-28.csv',
            $respons->headers->get('content-disposition')
        );
        $this->assertStringContainsString('BAL-001', $respons->streamedContent());
    }

    public function test_buku_agenda_menghasilkan_pdf(): void
    {
        $this->masuk(['nomor_surat' => 'AGM-001', 'perihal' => 'Undangan musyawarah desa']);
        $this->keluar(['nomor_surat' => 'AGK-001', 'perihal' => 'Pemberitahuan kerja bakti']);

        $respons = $this->actingAs($this->pegawai)
            ->get(route('laporan.agenda', ['dari' => '2026-02-01', 'sampai' => '2026-02-28']))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', $respons->headers->get('content-type'));

        $isi = $respons->getContent();
        $this->assertStringStartsWith('%PDF-', $isi);
        // dompdf menyimpan teks sebagai string di dalam stream yang dikompres,
        // jadi yang bisa dipastikan hanya ukuran dokumen + validitas header-nya.
        $this->assertGreaterThan(2000, strlen($isi));
    }

    public function test_halaman_laporan_terbuka_untuk_semua_role(): void
    {
        foreach ([$this->pegawai, $this->admin] as $u) {
            $this->actingAs($u)
                ->get(route('laporan.index'))
                ->assertOk()
                ->assertSee('Buku Agenda Surat')
                ->assertSee('Unduh Rekap CSV');
        }
    }

    public function test_halaman_laporan_menyebutkan_jumlah_baris_dan_menegur_periode_kosong(): void
    {
        $this->masuk(['nomor_surat' => 'JML-001', 'perihal' => 'dokumen jumlah']);
        $this->keluar(['nomor_surat' => 'JML-002', 'perihal' => 'dokumen jumlah']);

        $this->actingAs($this->pegawai)
            ->get(route('laporan.index', ['dari' => '2026-02-01', 'sampai' => '2026-02-28']))
            ->assertOk()
            ->assertSee('2 surat cocok dengan filter ini.')
            ->assertDontSee('Tidak ada surat pada periode');

        // Periode tanpa surat: harus eksplisit, bukan tombol unduh yang
        // menghasilkan dokumen kosong tanpa penjelasan.
        $this->actingAs($this->pegawai)
            ->get(route('laporan.index', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']))
            ->assertOk()
            ->assertSee('0 surat cocok dengan filter ini.')
            ->assertSee('Tidak ada surat pada periode')
            ->assertSee('01 Mei 2026');
    }

    public function test_tamu_ditolak_di_semua_keluaran_laporan(): void
    {
        $this->get(route('laporan.index'))->assertRedirect(route('login'));
        $this->get(route('laporan.rekap'))->assertRedirect(route('login'));
        $this->get(route('laporan.agenda'))->assertRedirect(route('login'));
    }

    public function test_badge_antrian_approval_hanya_untuk_admin(): void
    {
        PengajuanHapusLampiran::forceCreate([
            'diajukan_oleh' => $this->pegawai->id,
            'nama_file_snapshot' => 'scan-surat.pdf',
            'status' => 'menunggu',
        ]);
        PemusnahanArsip::forceCreate([
            'diajukan_oleh' => $this->pegawai->id,
            'status' => 'menunggu',
        ]);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 menunggu');

        $this->actingAs($this->pegawai)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('1 menunggu');
    }
}
