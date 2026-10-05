<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Services\PembacaIsiLampiran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

/**
 * Fitur "baca isi lampiran otomatis" surat masuk (permintaan user 5 Okt 2026):
 * berkas yang diunggah dicoba dibaca isinya, hasilnya ditampilkan sebagai DRAF
 * YANG BELUM VERIFIKASI untuk diperiksa manusia — tidak pernah menimpa ringkasan
 * tanpa aksi eksplisit, dan tidak pernah membuat upload gagal.
 */
class IsiLampiranSuratMasukTest extends TestCase
{
    use RefreshDatabase;

    private User $petugas;

    private SuratMasuk $surat;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('arsip');

        $this->petugas = User::forceCreate([
            'nama_lengkap' => 'Petugas Satu',
            'email' => 'petugas1@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $klasifikasi = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);

        $this->surat = SuratMasuk::forceCreate([
            'user_id' => $this->petugas->id,
            'pengirim' => 'Kecamatan',
            'klasifikasi_primer_id' => $klasifikasi->id,
            'nomor_surat' => '470/1/X/2026',
            'perihal' => 'Pemberitahuan jadwal irigasi',
            'ringkasan' => null,
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'sifat' => 'biasa',
            'status_arsip' => 'aktif',
        ]);
    }

    public function test_docx_terbaca_dan_tersimpan_sebagai_draf_belum_verifikasi(): void
    {
        $this->unggah('undangan.docx', $this->docx('Rapat musyawarah desa dijadwalkan ulang.'))
            ->assertSessionMissing('errors');

        $this->surat->refresh();

        $this->assertStringContainsString('Rapat musyawarah desa dijadwalkan ulang', (string) $this->surat->isi_hasil_baca);
        $this->assertSame('undangan.docx', $this->surat->isi_dibaca_dari);
        $this->assertNotNull($this->surat->isi_dibaca_pada);

        // Dua hal yang justru paling penting: ringkasan TIDAK terisi otomatis,
        // dan status verifikasi masih kosong.
        $this->assertNull($this->surat->ringkasan);
        $this->assertNull($this->surat->isi_terverifikasi_pada);
    }

    public function test_pdf_terbaca(): void
    {
        // PDF sungguhan dari dompdf (bukan byte asal), supaya jalur parser-nya
        // yang diuji — sama seperti berkas PDF yang benar-benar diterima kantor.
        $pdf = Pdf::loadHtml(
            '<html><body><p>Halaman atau arsip ini tidak ditemukan</p></body></html>'
        )->output();

        $hasil = app(PembacaIsiLampiran::class)->baca($this->lampiranBuatan('lampiran-pdf.pdf', $pdf));

        $this->assertIsString($hasil['teks'], 'PDF buatan dompdf harus terbaca, catatan: '.$hasil['catatan']);
        $this->assertStringContainsString('Halaman atau arsip ini tidak ditemukan', $hasil['teks']);
    }

    public function test_xlsx_mengambil_teks_sel(): void
    {
        $hasil = app(PembacaIsiLampiran::class)->baca(
            $this->lampiranBuatan('rekap.xlsx', $this->xlsx(['Anggaran irigasi', 'Bantuan sosial']))
        );

        $this->assertNotNull($hasil['teks']);
        $this->assertStringContainsString('Anggaran irigasi', $hasil['teks']);
        $this->assertStringContainsString('Bantuan sosial', $hasil['teks']);
    }

    public function test_hasil_pdf_scan_dilaporkan_jelas_bukan_kosong_diam_diam(): void
    {
        $hasil = app(PembacaIsiLampiran::class)->baca(
            $this->lampiranBuatan('scan-hasil.pdf', $this->docx(''))
        );

        // docx kosong -> zip valid tapi tanpa teks; PDF tidak valid -> null.
        // Yang diuji: pengguna dapat penjelasan, bukan exception.
        $this->assertNull($hasil['teks']);
        $this->assertNotSame('', $hasil['catatan']);
    }

    public function test_foto_tidak_dibaca_tapi_upload_tetap_sukses(): void
    {
        // OCR butuh tesseract yang tidak ada di shared hosting kantor (K1=a),
        // jadi berkas gambar harus tetap tersimpan DAN alasannya disampaikan.
        $response = $this->actingAs($this->petugas)->postJson(
            route('surat-masuk.lampiran.store', $this->surat),
            ['files' => [UploadedFile::fake()->create('scan-surat.jpg', 32, 'image/jpeg')]],
        );

        $response->assertOk();
        $response->assertJsonPath('sukses', 1);
        $response->assertJsonPath('baca.teks', null);

        $this->surat->refresh();
        $this->assertNull($this->surat->isi_hasil_baca);
        $this->assertSame(1, Lampiran::count());

        $catatan = implode(' ', $response->json('baca.catatan'));
        $this->assertStringContainsString('tidak bisa dibaca otomatis', $catatan);
    }

    public function test_format_lama_doc_dijelaskan_untuk_dikonversi(): void
    {
        $this->unggah('surat-lama.doc', 'isi binary lama')
            ->assertSessionMissing('errors');

        $this->surat->refresh();

        $this->assertNull($this->surat->isi_hasil_baca);
        $this->assertSame(1, Lampiran::count(), 'Upload tetap harus tersimpan walau tidak terbaca.');
    }

    public function test_surat_keluar_tidak_ikut_dibaca(): void
    {
        $keluar = SuratKeluar::forceCreate([
            'user_id' => $this->petugas->id,
            'penerima' => 'Warga',
            'klasifikasi_primer_id' => SuratMasuk::sole()->klasifikasi_primer_id,
            'nomor_surat' => '001/X/2026',
            'perihal' => 'Undangan',
            'tanggal_surat' => now(),
            'sifat' => 'biasa',
            'status_arsip' => 'aktif',
        ]);

        $response = $this->actingAs($this->petugas)->postJson(
            route('surat-keluar.lampiran.store', $keluar),
            ['files' => [UploadedFile::fake()->createWithContent('kutipan.docx', $this->docx('Teks tidak diambil di sini'))]],
        );

        $response->assertOk();
        $this->assertNull($response->json('baca.teks'));
    }

    public function test_user_menyimpan_hasil_baca_dan_mencentang_jadikan_ringkasan(): void
    {
        $this->unggah('undangan.docx', $this->docx('Bantuan pupuk cair akan dibagikan pekan depan.'));

        $this->surat->refresh();
        $this->assertNull($this->surat->ringkasan);

        $this->actingAs($this->petugas)
            ->patch(route('surat-masuk.isi.update', $this->surat), [
                'isi_hasil_baca' => 'Bantuan pupuk cair akan dibagikan pekan depan. (diperbaiki petugas)',
                'jadikan_ringkasan' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->surat->refresh();

        $this->assertStringContainsString('diperbaiki petugas', $this->surat->isi_hasil_baca);
        $this->assertStringContainsString('diperbaiki petugas', $this->surat->ringkasan);
        $this->assertNotNull($this->surat->isi_terverifikasi_pada);
    }

    public function test_tanpa_dicentang_ringkasan_tetap_utuh(): void
    {
        $this->surat->update(['ringkasan' => 'Ringkasan buatan petugas, jangan diganti.']);

        $this->actingAs($this->petugas)
            ->patch(route('surat-masuk.isi.update', $this->surat), [
                'isi_hasil_baca' => 'Hasil baca mesin yang panjang.',
            ])
            ->assertSessionHasNoErrors();

        $this->surat->refresh();

        $this->assertSame('Ringkasan buatan petugas, jangan diganti.', $this->surat->ringkasan);
        $this->assertSame('Hasil baca mesin yang panjang.', $this->surat->isi_hasil_baca);
    }

    public function test_hasil_baca_bisa_dikosongkan(): void
    {
        // forceFill(): kolom hasil baca memang sengaja di luar $fillable (lihat
        // SuratMasuk) — hanya controller yang boleh menulisnya.
        $this->surat->forceFill(['isi_hasil_baca' => 'salah baca', 'isi_terverifikasi_pada' => now()])->save();

        $this->actingAs($this->petugas)
            ->patch(route('surat-masuk.isi.update', $this->surat), ['isi_hasil_baca' => ''])
            ->assertSessionHasNoErrors();

        $this->surat->refresh();

        $this->assertNull($this->surat->isi_hasil_baca);
        $this->assertNull($this->surat->isi_terverifikasi_pada);
    }

    public function test_tamu_ditolak_di_endpoint_hasil_baca(): void
    {
        $this->patch(route('surat-masuk.isi.update', $this->surat), ['isi_hasil_baca' => 'x'])
            ->assertRedirect(route('login'));
    }

    public function test_halaman_show_menampilkan_kartu_hasil_baca(): void
    {
        $this->surat->forceFill([
            'isi_hasil_baca' => 'Isi surat yang terbaca mesin.',
            'isi_dibaca_dari' => 'undangan.docx',
            'isi_dibaca_pada' => now(),
        ])->save();

        $this->actingAs($this->petugas)
            ->get(route('surat-masuk.show', $this->surat))
            ->assertOk()
            ->assertSee('Hasil Baca Isi Lampiran')
            ->assertSee('belum diverifikasi')
            ->assertSee('Isi surat yang terbaca mesin')
            // Kartu ada di DOM (hidden) supaya unggah berikutnya bisa mengisi
            // tanpa reload, dan hook JS-nya ikut ter-output.
            ->assertSee('pradanaHasilBacaIsi', false)
            ->assertSee('name="jadikan_ringkasan"', false);
    }

    public function test_form_edit_juga_memperlihatkan_hasil_baca_untuk_divalidasi(): void
    {
        $this->surat->forceFill([
            'isi_hasil_baca' => 'Jadwal pelayanan kantor berubah.',
            'isi_dibaca_dari' => 'pengumuman.docx',
            'isi_dibaca_pada' => now(),
        ])->save();

        $this->actingAs($this->petugas)
            ->get(route('surat-masuk.edit', $this->surat))
            ->assertOk()
            ->assertSee('Hasil baca isi lampiran')
            ->assertSee('Jadwal pelayanan kantor berubah')
            ->assertSee('pradanaSalinHasilBaca', false);
    }

    private function unggah(string $nama, string $isi): TestResponse
    {
        return $this->actingAs($this->petugas)->post(
            route('surat-masuk.lampiran.store', $this->surat),
            ['files' => [UploadedFile::fake()->createWithContent($nama, $isi)]],
        );
    }

    /**
     * Berkas Lampiran yang menunjuk isi tertentu di disk `arsip` palsu — untuk
     * menguji service tanpa perlu lewat HTTP.
     */
    private function lampiranBuatan(string $nama, string $isi): Lampiran
    {
        $path = 'surat-masuk/umum/'.$this->surat->id.'/'.$nama;
        Storage::disk('arsip')->put($path, $isi);

        return Lampiran::create([
            'lampiranable_id' => $this->surat->id,
            'lampiranable_type' => SuratMasuk::class,
            'disk' => 'arsip',
            'path' => $path,
            'nama_file' => $nama,
            'ukuran' => strlen($isi),
            'diunggah_oleh' => $this->petugas->id,
        ]);
    }

    private function docx(string $teks): string
    {
        return $this->zip(['word/document.xml' => $teks === ''
            ? '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body/></w:document>'
            : '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
                .'<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($teks, ENT_XML1).'</w:t></w:r></w:p>'
                .'</w:body></w:document>']);
    }

    /**
     * @param  array<int, string>  $sel
     */
    private function xlsx(array $sel): string
    {
        $shared = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .implode('', array_map(
                fn ($t) => '<si><t xml:space="preserve">'.htmlspecialchars($t, ENT_XML1).'</t></si>',
                $sel
            ))
            .'</sst>';

        return $this->zip(['xl/sharedStrings.xml' => $shared]);
    }

    /**
     * @param  array<string, string>  $berkas
     */
    private function zip(array $berkas): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pradana-tes').'.zip';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($berkas as $nama => $isi) {
            $zip->addFromString($nama, $isi);
        }

        $zip->close();

        $isi = (string) file_get_contents($path);
        @unlink($path);

        return $isi;
    }
}
