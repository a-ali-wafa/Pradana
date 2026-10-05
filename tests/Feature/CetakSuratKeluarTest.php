<?php

namespace Tests\Feature;

use App\Models\DrafKontenSuratKeluar;
use App\Models\KlasifikasiPrimer;
use App\Models\KlasifikasiSekunder;
use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Models\User;
use App\Support\Terbilang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Struktur surat keluar versi tata naskah dinas desa (5 Okt 2026).
 *
 * Yang diuji di sini adalah HTML hasil render template, bukan berkas PDF-nya:
 * byte PDF-nya dikompres dan tidak bisa dipakai membuktikan urutan baris kop.
 * Kalau strukturnya benar di HTML dan DomPDF bisa merendernya (tes cetak di
 * DrafKontenSuratKeluarTest membuktikan itu), hasilnya benar di kertas.
 */
class CetakSuratKeluarTest extends TestCase
{
    use RefreshDatabase;

    private User $pegawai;

    private PengaturanInstansi $instansi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $this->instansi = PengaturanInstansi::query()->create([
            'nama_instansi' => 'PEMERINTAH DESA UREK-UREK',
            'nama_kabupaten' => 'Banyumas',
            'nama_kecamatan' => 'Kedung Banteng',
            'jenis_instansi' => 'Pemerintah Desa',
            'alamat_instansi' => 'Jalan Raya Urek-Urek 1',
            'kode_pos' => '53182',
            'no_telp' => '0281-123456',
            'email' => 'desa@urek-urek.test',
        ]);
    }

    private function surat(array $ubah = []): SuratKeluar
    {
        $primer = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);
        $sekunder = KlasifikasiSekunder::forceCreate([
            'klasifikasi_primer_id' => $primer->id,
            'kode' => '02',
            'nama' => 'Surat Dinas',
        ]);

        $surat = SuratKeluar::forceCreate(array_merge([
            'user_id' => $this->pegawai->id,
            'penerima' => 'Iwan Setiawan',
            'jabatan_penerima' => 'Ketua BPD',
            'instansi_penerima' => 'BPD Desa Urek-Urek',
            'klasifikasi_primer_id' => $primer->id,
            'klasifikasi_sekunder_id' => $sekunder->id,
            'nomor_surat' => '001/01/X/2026',
            'perihal' => 'Undangan musyawarah desa',
            'kota_tujuan' => 'Urek-Urek',
            'provinsi_tujuan' => 'Jawa Tengah',
            'tanggal_surat' => '2026-10-05',
            'status_arsip' => 'aktif',
            'sifat' => 'penting',
        ], $ubah));

        DrafKontenSuratKeluar::forceCreate(array_merge([
            'surat_keluar_id' => $surat->id,
            'salam_pembuka' => 'Dengan hormat,',
            'isi_surat' => "Paragraf pertama tentang irigasi.\n\nParagraf kedua tentang jadwal.",
            'salam_penutup' => 'Demikian surat ini kami sampaikan.',
            'jabatan_penandatangan' => 'Kepala Desa Urek-Urek',
            'atas_nama' => 'Bambang Priyanto',
            'nip_nik' => '13640912',
            'tembusan' => "Dinas PMD Kabupaten Banyumas\nArsip",
        ], []));

        return $surat;
    }

    /**
     * Render template dengan data sungguhan (relasi sudah di-load manual supaya
     * partial kop dan blok klasifikasi punya angka untuk dicetak).
     */
    private function render(SuratKeluar $surat): string
    {
        $surat->load(['primer', 'sekunder', 'tersier', 'petugas', 'drafKonten', 'lampiran']);

        return view('surat-keluar.cetak', [
            'surat' => $surat,
            'draf' => $surat->drafKonten,
            'instansi' => $this->instansi,
            'logoPath' => $this->instansi->logoPathUntukPdf(),
            'jumlahLampiran' => 0,
            'notasiLampiran' => Terbilang::denganAngka(0).' berkas',
            'tanggalSurat' => '5 Oktober 2026',
            'kodeKlasifikasi' => trim(collect([
                $surat->primer?->kode,
                $surat->sekunder?->kode,
                $surat->tersier?->kode,
            ])->filter()->implode('/')),
        ])->render();
    }

    public function test_kop_menampilkan_tiga_tingkat_dan_alamat_lengkap(): void
    {
        $html = $this->render($this->surat());

        $this->assertStringContainsString('PEMERINTAH KABUPATEN BANYUMAS', $html);
        $this->assertStringContainsString('KECAMATAN KEDUNG BANTENG', $html);
        $this->assertStringContainsString('PEMERINTAH DESA UREK-UREK', $html);
        $this->assertStringContainsString('Jalan Raya Urek-Urek 1, Kode Pos 53182', $html);
        $this->assertStringContainsString('Telp. 0281-123456', $html);
        // Garis ganda khas kop: satu tebal, satu tipis di bawahnya.
        $this->assertStringContainsString('border-bottom: 3px solid #000', $html);
        $this->assertStringContainsString('border-bottom: 1px solid #000', $html);
    }

    public function test_blok_nomor_sampai_perihal_dan_tempat_tanggal(): void
    {
        $html = $this->render($this->surat());

        $this->assertStringContainsString('>Nomor<', $html);
        $this->assertStringContainsString('001/01/X/2026', $html);
        $this->assertStringContainsString('>Sifat<', $html);
        $this->assertStringContainsString('Penting', $html);
        $this->assertStringContainsString('>Lampiran<', $html);
        // P6=a: notasi dihitung dari berkas, ditulis `0 (nol)` ala surat dinas.
        $this->assertStringContainsString('0 (nol) berkas', $html);
        // Kode klasifikasi 3 level ikut dicetak.
        $this->assertStringContainsString('>Klasifikasi<', $html);
        $this->assertStringContainsString('01/02', $html);
        // Tempat surat = nama instansi tanpa kata "Pemerintah".
        $this->assertStringContainsString('DESA UREK-UREK, 5 Oktober 2026', $html);
    }

    public function test_alamat_tujuan_dan_isi_dipecah_per_paragraf(): void
    {
        $html = $this->render($this->surat());

        $this->assertStringContainsString('Kepada Yth.', $html);
        $this->assertStringContainsString('Ketua BPD', $html);
        $this->assertStringContainsString('Iwan Setiawan', $html);
        $this->assertStringContainsString('BPD Desa Urek-Urek', $html);
        $this->assertStringContainsString('Dengan hormat,', $html);

        // Dua paragraf = dua <p> terpisah (dulu semuanya satu blok nl2br).
        $this->assertStringContainsString('Paragraf pertama tentang irigasi.', $html);
        $this->assertStringContainsString('Paragraf kedua tentang jadwal.', $html);
        $this->assertStringContainsString('</p>', $html);
        $this->assertMatchesRegularExpression('/Paragraf pertama[^<]*<\/p>/', $html);

        $this->assertStringContainsString('Demikian surat ini kami sampaikan.', $html);
    }

    public function test_blok_tanda_tangan_dan_tembusan_bernomor(): void
    {
        $html = $this->render($this->surat());

        $this->assertStringContainsString('Kepala Desa Urek-Urek,', $html);
        $this->assertStringContainsString('Bambang Priyanto', $html);
        $this->assertStringContainsString('NIP/NIK. 13640912', $html);

        $this->assertStringContainsString('Tembusan:', $html);
        // Lebih dari satu baris -> daftar bernomor, bukan teks mentah.
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<li>Dinas PMD Kabupaten Banyumas</li>', $html);
        $this->assertStringContainsString('<li>Arsip</li>', $html);
    }

    public function test_jabatan_penandatangan_kosong_memakai_sebutan_dari_pengaturan(): void
    {
        $surat = $this->surat();
        $surat->drafKonten->update(['jabatan_penandatangan' => null, 'tembusan' => null]);

        $html = $this->render($surat->fresh(['drafKonten']));

        // "Pemerintah Desa" → "Kepala Desa", bukan "Kepala Pemerintah Desa".
        $this->assertStringContainsString('Kepala Desa,', $html);
        // Satu baris tembusan tidak dinomori; kalau kosong whole blok hilang.
        $this->assertStringNotContainsString('Tembusan:', $html);
    }

    public function test_isi_surat_dengan_karakter_html_tidak_diubah_menjadi_markup(): void
    {
        $surat = $this->surat();
        $surat->drafKonten->update(['isi_surat' => 'Rapat <b>penting</b> & dihadiri 30 orang']);

        $html = $this->render($surat->fresh(['drafKonten']));

        // Blade `{!! nl2br(e(...)) !!}`: tag di-escape, line break boleh.
        $this->assertStringContainsString('Rapat &lt;b&gt;penting&lt;/b&gt; &amp; dihadiri 30 orang', $html);
        $this->assertStringNotContainsString('Rapat <b>penting</b>', $html);
    }

    public function test_kop_tetap_dicetak_saat_instansi_belum_dilengkapi(): void
    {
        $kosong = PengaturanInstansi::query()->create([
            'nama_instansi' => 'KANTOR ARSIP DESA',
            'jenis_instansi' => 'Pemerintah Desa',
            'alamat_instansi' => '',
        ]);

        $surat = $this->surat();

        $html = view('surat-keluar.cetak', [
            'surat' => $surat->load(['primer', 'sekunder', 'tersier', 'petugas', 'drafKonten', 'lampiran']),
            'draf' => $surat->drafKonten,
            'instansi' => $kosong,
            'logoPath' => null,
            'jumlahLampiran' => 0,
            'notasiLampiran' => '0 (nol) berkas',
            'tanggalSurat' => '5 Oktober 2026',
            'kodeKlasifikasi' => '',
        ])->render();

        $this->assertStringContainsString('KANTOR ARSIP DESA', $html);
        $this->assertStringNotContainsString('PEMERINTAH KABUPATEN', $html);
        $this->assertStringNotContainsString('Kode Pos', $html);
        $this->assertStringNotContainsString('>Klasifikasi<', $html);
        // Tanpa logo: tidak ada <img> sama sekali, bukan img dengan src kosong.
        $this->assertStringNotContainsString('<img ', $html);
    }
}
