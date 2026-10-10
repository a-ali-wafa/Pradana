<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plafon jumlah baris Buku Agenda PDF (temuan #3 di docs/daftar-peningkatan.md,
 * dikerjakan 10 Okt 2026).
 *
 * Angka yang mendasarinya, diukur 9 Okt di MariaDB tanding: 1.000 baris agenda =
 * 8,36 detik dan puncak memori 52 MB. Agenda Dibuat dalam SATU permintaan (L-02
 * melarang queue), dan `max_execution_time`/`memory_limit` shared hosting kantor
 * (K1=a) tidak bisa dinaikkan dari kode ini. Tanpa plafon, permintaan agenda
 * tahunan bukan menghasilkan dokumen besar — ia mati di tengah jalan dan
 * meninggalkan PDF separuh jadi yang tidak bisa dipakai siapa pun.
 *
 * Yang diuji di sini bukan hanya "ditolak", tapi juga bahwa penolakannya terjadi
 * sebelum satu baris pun dihidrasi (`jumlah()` memakai kerangka query yang sama
 * dengan `baris()`), dan bahwa layarnya sudah memperingatkan sebelum tombol ditekan.
 */
class AgendaBatasBarisTest extends TestCase
{
    use RefreshDatabase;

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Agenda',
            'email' => 'staf-agenda@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);
    }

    private function tanam(int $jumlah, string $awalan = 'AGD'): void
    {
        $primer = KlasifikasiPrimer::forceCreate(['kode' => '99', 'nama' => 'Uji Plafon Agenda']);

        for ($i = 1; $i <= $jumlah; $i++) {
            SuratMasuk::forceCreate([
                'user_id' => $this->pegawai->id,
                'pengirim' => 'Kecamatan',
                'klasifikasi_primer_id' => $primer->id,
                'nomor_surat' => $awalan.'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'perihal' => 'Perihal uji plafon agenda',
                'tanggal_surat' => '2026-05-01',
                'tanggal_diterima' => '2026-05-01',
                'sifat' => 'biasa',
                'status_arsip' => 'aktif',
            ]);
        }
    }

    public function test_agenda_dibuat_selama_masih_di_bawah_plafon(): void
    {
        config(['laporan.agenda_batas_baris' => 10]);
        $this->tanam(4);

        $isi = $this->actingAs($this->pegawai)
            ->get(route('laporan.agenda', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->getContent();

        $this->assertStringStartsWith('%PDF-', $isi);
    }

    public function test_agenda_ditolak_sebelum_satu_baris_pun_diangkat(): void
    {
        config(['laporan.agenda_batas_baris' => 3]);
        $this->tanam(5);

        $respons = $this->actingAs($this->pegawai)
            ->from(route('laporan.index', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']))
            ->get(route('laporan.agenda', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']));

        // Bukan PDF, dan bukan 500: penolakan yang bisa dibaca orang kantor.
        $respons->assertRedirect(route('laporan.index', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']));
        $respons->assertSessionHas('error');

        $pesan = (string) session('error');
        $this->assertStringContainsString('3 baris', $pesan, 'Pesan harus menyebut batasnya.');
        $this->assertStringContainsString('5 surat', $pesan, 'Pesan harus menyebut jumlah sebenarnya supaya tidak diterka.');
        $this->assertStringContainsString('semester', $pesan, 'Pesan harus memberi jalan keluar, bukan cuma menolak.');

        // Kunci flash harus yang dirender layout — kelas bug yang sama dengan
        // "Nyahkan" di Fase 6; FlashKonsistenTest menjaga ini untuk SEMUA flash,
        // tes ini memastikan pesannya benar-benar sampai ke layar berikutnya.
        $this->followingRedirects()
            ->from(route('laporan.index', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']))
            ->get(route('laporan.agenda', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']))
            ->assertOk()
            ->assertSee('Buku Agenda dibatasi', false);
    }

    public function test_rekap_csv_tidak_mengenal_plafon_in(): void
    {
        // Plafon ini urusan dompdf (waktu + memori render), bukan CSV. Kalau CSV
        // ikut dibatasi, kantor tidak bisa menyalin arsip tahunan ke Excel — dan
        // justru itu keluaran yang murah.
        config(['laporan.agenda_batas_baris' => 3]);
        $this->tanam(5);

        $csv = $this->actingAs($this->pegawai)
            ->get(route('laporan.rekap', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']))
            ->streamedContent();

        $baris = count(array_filter(explode("\n", $csv), fn ($l) => str_contains((string) $l, 'AGD-')));
        $this->assertSame(5, $baris);
    }

    public function test_layar_memperingatkan_sebelum_tombol_ditekan(): void
    {
        config(['laporan.agenda_batas_baris' => 4]);
        $this->tanam(5);

        $ini = $this->actingAs($this->pegawai)
            ->get(route('laporan.index', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']))
            ->assertOk();

        $ini->assertSee('tidak akan dicetak', false);
        $ini->assertSee('5 surat melewati batas 4 baris', false);
    }

    public function test_plafon_dibaca_dari_config_dan_bukan_env(): void
    {
        // Angka bawaan ada di config/laporan.php dengan alasan yang tercatat:
        // `env()` mengembalikan null setelah `config:cache` (jebakan SECURITY_CSP
        // dan DEV_PIN), dan (int) null = 0 akan menolak SEMUA agenda di server
        // kantor sementara di laptop kelihatan normal.
        $berkas = require base_path('config/laporan.php');

        $this->assertSame(2000, $berkas['agenda_batas_baris']);
        $this->assertSame(2000, (int) config('laporan.agenda_batas_baris'));
    }
}
