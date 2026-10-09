<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas tanggal pada laporan & agenda (Fase 1 perapian, 9 Okt 2026).
 *
 * Ini bug kelas yang sama dengan yang sudah diperbaiki di FilterArsip: Eloquent
 * menulis cast `date` sebagai `Y-m-d H:i:s`. Di MariaDB kolom DATE memotong jam
 * sehingga `BETWEEN ... '2026-12-31'` masih benar; di SQLite string utuh
 * tersimpan dan surat tertanggal 31 Desember TERBUANG. Suite jalan di SQLite,
 * jadi tes ini menangkap versi engine tes — dan bentuk perbaikannya (interval
 * setengah terbuka) benar di dua engine, tetap memakai index, serta membuat
 * makna "sampai" yang dibaca orang kantor ("selesai sampai akhir hari itu")
 * jadi eksplisit, bukan ikut-ikutan pemotongan kolom.
 *
 * `LaporanController::index()` menampilkan jumlah baris yang akan diunduh, jadi
 * angka di layar dan isi CSV/PDF harus selalu sama — itu alasan keduanya diuji
 * di sini sekaligus.
 */
class BatasTanggalLaporanTest extends TestCase
{
    use RefreshDatabase;

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $primer = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);

        foreach ([
            ['UKU-DEPAN', '2026-01-01'],
            ['UKU-TENGAH', '2026-06-15'],
            ['UKU-BATAS', '2026-12-31'],
            ['UKU-LUAR', '2027-01-01'],
        ] as [$nomor, $tanggal]) {
            SuratMasuk::forceCreate([
                'user_id' => $this->pegawai->id,
                'pengirim' => 'Kecamatan',
                'klasifikasi_primer_id' => $primer->id,
                'nomor_surat' => $nomor,
                'perihal' => 'Perihal uji batas tanggal',
                'tanggal_surat' => $tanggal,
                'tanggal_diterima' => $tanggal,
                'status_arsip' => 'aktif',
                'sifat' => 'biasa',
            ]);
        }

        SuratKeluar::forceCreate([
            'user_id' => $this->pegawai->id,
            'penerima' => 'Warga',
            'klasifikasi_primer_id' => $primer->id,
            'nomor_surat' => 'UKU-KELUARAN',
            'perihal' => 'Surat keterangan',
            'tanggal_surat' => '2026-12-31',
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ]);
    }

    public function test_rekap_mencakup_hari_terakhir_di_periode(): void
    {
        $csv = $this->actingAs($this->pegawai)
            ->get(route('laporan.rekap', ['dari' => '2026-01-01', 'sampai' => '2026-12-31']))
            ->streamedContent();

        $this->assertStringContainsString('UKU-DEPAN', $csv);
        $this->assertStringContainsString('UKU-TENGAH', $csv);
        $this->assertStringContainsString('UKU-BATAS', $csv, 'Surat tertanggal 31 Desember terbuang dari rekap.');
        $this->assertStringContainsString('UKU-KELUARAN', $csv);
        $this->assertStringNotContainsString('UKU-LUAR', $csv, '1 Januari tahun berikutnya tidak boleh ikut.');
    }

    public function test_angka_di_layar_sama_dengan_isi_rekap(): void
    {
        // 4 surat: 3 masuk tertanggal 2026 + 1 keluar 31 Des (UKU-LUAR 2027 di luar).
        $layar = $this->actingAs($this->pegawai)
            ->get(route('laporan.index', ['dari' => '2026-01-01', 'sampai' => '2026-12-31']))
            ->assertOk();

        $csv = $this->actingAs($this->pegawai)
            ->get(route('laporan.rekap', ['dari' => '2026-01-01', 'sampai' => '2026-12-31']))
            ->streamedContent();

        $barisCsv = count(array_filter(explode("\n", $csv), fn ($l) => str_contains($l, 'UKU-')));

        $this->assertSame(4, $barisCsv);
        $layar->assertSee('4 surat cocok dengan filter ini.', false);
    }

    public function test_periode_hanya_satu_hari_tetap_memuat_surat_hari_itu(): void
    {
        $csv = $this->actingAs($this->pegawai)
            ->get(route('laporan.rekap', ['dari' => '2026-12-31', 'sampai' => '2026-12-31']))
            ->streamedContent();

        // "sampai" = sampai akhir hari itu, bukan sebelum jam nol hari itu.
        $this->assertStringContainsString('UKU-BATAS', $csv);
        $this->assertStringContainsString('UKU-KELUARAN', $csv);
        $this->assertStringNotContainsString('UKU-TENGAH', $csv);
    }

    public function test_periode_terbalik_dibalik_bukan_dikosongkan(): void
    {
        $csv = $this->actingAs($this->pegawai)
            ->get(route('laporan.rekap', ['dari' => '2026-12-31', 'sampai' => '2026-01-01']))
            ->streamedContent();

        $this->assertStringContainsString('UKU-BATAS', $csv);
        $this->assertStringContainsString('UKU-DEPAN', $csv);
    }

    public function test_agenda_pdf_mengikuti_batas_yang_sama(): void
    {
        // Agenda dibaca dari method baris() yang sama dengan CSV; dokumen harus
        // ter-bit dan berisi surat batas, bukan senyap kehilangan satu hari.
        $respons = $this->actingAs($this->pegawai)
            ->get(route('laporan.agenda', ['dari' => '2026-01-01', 'sampai' => '2026-12-31']));

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'application/pdf');

        // Pdf::stream() menghasilkan respons berisi PDF lengkap, bukan
        // StreamedResponse seperti unduhan CSV — karena itu isinya dibaca lewat
        // getContent(). Teks di dalam PDF tidak bisa dicari apa-adanya (stream-nya
        // ditekan), jadi yang dibuktikan di sini adalah dokumen benar-benar
        // ter-bit dan bukan layar kosong; batas tanggalnya sendiri sudah dibuktikan
        // tes CSV di atas karena keduanya membaca method query yang sama.
        $isi = $respons->getContent();
        $this->assertStringStartsWith('%PDF-', $isi);
        $this->assertStringContainsString('%%EOF', $isi, 'PDF agenda terpotong di tengah.');
    }
}
