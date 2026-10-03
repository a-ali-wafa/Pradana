<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L-01 + L-02 + L-13: lampiran disimpan sinkron ke disk lokal `arsip`,
 * bukan lagi dikirim ke Google Drive lewat queue.
 */
class UploadLampiranTest extends TestCase
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

        $klasifikasi = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Keuangan']);

        $this->surat = SuratMasuk::forceCreate([
            'user_id' => $this->petugas->id,
            'pengirim' => 'Dinas Terkait',
            'klasifikasi_primer_id' => $klasifikasi->id,
            'nomor_surat' => '470/1/KEU/X/2026',
            'kota_asal' => 'Malang',
            'perihal' => 'Rencana anggaran',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
        ]);
    }

    public function test_unggah_lampiran_menyimpan_file_lokal_dan_baris_database(): void
    {
        $response = $this->actingAs($this->petugas)->post(
            route('surat-masuk.lampiran.store', $this->surat),
            ['files' => [UploadedFile::fake()->create('scan-surat.pdf', 64)]],
        );

        $response->assertRedirect();

        $lampiran = Lampiran::sole();
        $this->assertSame('arsip', $lampiran->disk);
        $this->assertSame('scan-surat.pdf', $lampiran->nama_file);
        $this->assertNotNull($lampiran->path);
        $this->assertLength($lampiran->hash_file);
        Storage::disk('arsip')->assertExists($lampiran->path);

        // Tidak ada baris Drive: upload lokal tidak boleh menyentuh Google Drive.
        $this->assertNull($lampiran->google_drive_file_id);
    }

    public function test_file_word_dan_excel_diterima(): void
    {
        // L-13: dulu hanya pdf/jpg/png yang lolos, padahal surat antar-instansi
        // sering datang sebagai .docx — file itu tidak bisa diarsipkan sama sekali.
        // Isi sengaja beda: UploadedFile::fake()->create() menghasilkan byte identik
        // untuk ukuran sama, dan duplikat seperti itu memang dilewati (I1).
        $this->actingAs($this->petugas)->post(
            route('surat-masuk.lampiran.store', $this->surat),
            ['files' => [
                UploadedFile::fake()->createWithContent('ringkasan.docx', 'isi ringkasan anggaran'),
                UploadedFile::fake()->createWithContent('rekap.xlsx', 'isi rekap pendapatan'),
            ]],
        )->assertSessionMissing('errors');

        $this->assertSame(2, Lampiran::count());
    }

    public function test_file_isi_sama_di_surat_sama_dilewati(): void
    {
        // Keputusan I1: duplikat di surat yang sama tidak disimpan dua kali.
        $isi = 'isi-yang-sama';

        foreach ([$isi, $isi] as $_) {
            $this->actingAs($this->petugas)->post(
                route('surat-masuk.lampiran.store', $this->surat),
                ['files' => [UploadedFile::fake()->createWithContent('scan.pdf', $isi)]],
            );
        }

        $this->assertSame(1, Lampiran::count());
    }

    public function test_unduh_lampiran_dari_disk_lokal(): void
    {
        $this->actingAs($this->petugas)->post(
            route('surat-masuk.lampiran.store', $this->surat),
            ['files' => [UploadedFile::fake()->createWithContent('bukti.pdf', 'konsep berkas')] ],
        );

        $lampiran = Lampiran::sole();

        $response = $this->actingAs($this->petugas)->get(route('lampiran.download', $lampiran));

        $response->assertOk();
        $this->assertSame('konsep berkas', $response->streamedContent());
    }

    private function assertLength(?string $hash): void
    {
        $this->assertNotNull($hash, 'hash_file harus terisi untuk deteksi duplikat (I1).');
        $this->assertSame(64, strlen($hash));
    }
}
