<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\PengajuanHapusLampiran;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tombol "Ajukan Hapus" di halaman show surat (Fase 2 perapian, 9 Okt 2026).
 *
 * View dulu menghitung umurnya SENDIRI:
 *  - surat masuk: `Carbon::parse($surat->tanggal_diterima)->diffInYears(now())`
 *    — padahal L-21 [LOCKED] menyeragamkan acuan umur arsip ke `tanggal_surat`.
 *    Akibatnya tombol bisa muncul untuk surat yang pasti ditolak server
 *    (`PengajuanHapusLampiranController::store()`), dan sebaliknya menghilang
 *    untuk surat yang sebenarnya boleh diajukan.
 *  - kedua view memanggil `$lamp->pengajuanHapus()->exists()` di dalam loop =
 *    satu query per berkas.
 *
 * Sekarang view menanyakan `UmurArsip::lewatRetensi()` ke suratnya dan membaca
 * koleksi `pengajuanHapus` yang sudah di-eager-load. Tes ini mengunci KEDUA-duanya:
 * tanggal_diterima tidak boleh mengubah tampilan, dan status pengajuan harus
 * terbaca dari data yang sudah dimuat.
 */
class AjukanHapusLampiranTampilTest extends TestCase
{
    use RefreshDatabase;

    private User $pegawai;

    private KlasifikasiPrimer $klasifikasi;

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

    /** Surat masuk tua (lewat retensi) dengan satu berkas. */
    private function masuk(string $tanggalSurat, string $tanggalDiterima): SuratMasuk
    {
        $surat = SuratMasuk::forceCreate([
            'user_id' => $this->pegawai->id,
            'pengirim' => 'Kecamatan',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'nomor_surat' => 'TOMBOL-'.$tanggalSurat,
            'perihal' => 'Perihal uji tombol hapus',
            'tanggal_surat' => $tanggalSurat,
            'tanggal_diterima' => $tanggalDiterima,
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ]);

        Lampiran::forceCreate([
            'lampiranable_type' => SuratMasuk::class,
            'lampiranable_id' => $surat->id,
            'nama_file' => 'berkas-'.$surat->id.'.pdf',
            'disk' => 'arsip',
            'path' => 'uji/berkas-'.$surat->id.'.pdf',
        ]);

        return $surat;
    }

    public function test_tombol_muncul_hanya_kalau_tanggal_surat_lewat_retensi(): void
    {
        $lama = $this->masuk('2019-01-10', '2019-01-20');
        $muda = $this->masuk(Carbon::now()->toDateString(), Carbon::now()->toDateString());

        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.show', $lama))
            ->assertOk()
            ->assertSee('Ajukan Hapus');

        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.show', $muda))
            ->assertOk()
            ->assertDontSee('Ajukan Hapus');
    }

    public function test_tanggal_diterima_tidak_lagi_menentukan_tampilan(): void
    {
        // Inti pelanggaran L-21 yang diperbaiki: surat yang DITERIMA tahun 2019
        // tapi DIBUAT tahun 2025 belum boleh diajukan hapus. Bentuk lama
        // (menghitung dari tanggal_diterima) menampilkan tombolnya, lalu server
        // menolaknya — user lihat tombol yang tidak pernah bisa dipakai.
        $surat = $this->masuk('2025-06-01', '2019-01-01');

        $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.show', $surat))
            ->assertOk()
            ->assertDontSee('Ajukan Hapus');
    }

    public function test_berkas_yang_sudah_diantrikan_menampilkan_badge_bukan_form(): void
    {
        $surat = $this->masuk('2019-02-02', '2019-02-05');
        $berkas = $surat->lampiran()->firstOrFail();

        PengajuanHapusLampiran::forceCreate([
            'lampiran_id' => $berkas->id,
            'nama_file_snapshot' => $berkas->nama_file,
            'diajukan_oleh' => $this->pegawai->id,
            'status' => 'menunggu',
        ]);

        $layar = $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.show', $surat))
            ->assertOk();

        $layar->assertSee('Menunggu');
        $layar->assertDontSee('Ajukan Hapus');
    }

    public function test_halaman_surat_keluar_memakai_aturan_yang_sama(): void
    {
        $tua = SuratKeluar::forceCreate([
            'user_id' => $this->pegawai->id,
            'penerima' => 'Warga',
            'klasifikasi_primer_id' => $this->klasifikasi->id,
            'nomor_surat' => 'TOMBOL-KELUAR',
            'perihal' => 'Surat keterangan',
            'tanggal_surat' => '2019-05-05',
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ]);

        Lampiran::forceCreate([
            'lampiranable_type' => SuratKeluar::class,
            'lampiranable_id' => $tua->id,
            'nama_file' => 'salinan-keluar.pdf',
            'disk' => 'arsip',
            'path' => 'uji/salinan-keluar.pdf',
        ]);

        $this->actingAs($this->pegawai)
            ->get(route('surat-keluar.show', $tua))
            ->assertOk()
            ->assertSee('Ajukan Hapus');
    }

    public function test_tombol_pengajuan_hanya_muncul_sekalipun_berkas_banyak(): void
    {
        // Menguji sisi eager load dari sudut perilaku: daftar 3 berkas harus
        // tetap menampilkan satu form per berkas, bukan satu form untuk semua
        // (kalau koleksi pengajuan dibaca salah, badge "Menunggu" bisa muncul
        // di berkas yang belum pernah diajukan).
        $surat = $this->masuk('2019-07-07', '2019-07-09');

        foreach (['b', 'c'] as $huruf) {
            Lampiran::forceCreate([
                'lampiranable_type' => SuratMasuk::class,
                'lampiranable_id' => $surat->id,
                'nama_file' => "berkas-{$huruf}.pdf",
                'disk' => 'arsip',
                'path' => "uji/berkas-{$huruf}.pdf",
            ]);
        }

        $satu = $surat->lampiran()->orderBy('id')->firstOrFail();
        PengajuanHapusLampiran::forceCreate([
            'lampiran_id' => $satu->id,
            'nama_file_snapshot' => $satu->nama_file,
            'diajukan_oleh' => $this->pegawai->id,
            'status' => 'menunggu',
        ]);

        $isi = $this->actingAs($this->pegawai)
            ->get(route('surat-masuk.show', $surat))
            ->assertOk()
            ->getContent();

        // `</i>Ajukan Hapus` = label tombolnya. Teks "Ajukan Hapus" sendiri muncul
        // DUA kali per berkas (sekali di `title=`), jadi menghitung teks polos
        // akan memberi angka yang kelihatan salah tanpa ada yang salah.
        $this->assertSame(2, substr_count((string) $isi, '</i>Ajukan Hapus'), 'Dua berkas boleh diajukan, satu sudah diantrikan.');
        $this->assertSame(1, substr_count((string) $isi, '>Menunggu<'));
    }
}
