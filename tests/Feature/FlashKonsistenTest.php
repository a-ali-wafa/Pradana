<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Penjaga struktur: semua pesan flash yang ditulis controller HARUS benar-benar
 * ditampilkan layout.
 *
 * Ini bukan tes fitur, tapi tes konsistensi dua lapis kode yang tidak saling
 * mengetahui. Bug nyatanya ada 12 kali di project ini: `->with('status', ...)`
 * dipakai di daftar surat masuk/keluar, manajemen user, dan penolakan pemusnahan,
 * sementara `resources/views/layouts/app.blade.php` hanya merender
 * `session('success')` dan `session('error')`. Akibatnya petugas mengira aksinya
 * gagal (tidak ada konfirmasi) padahal data sudah tersimpan. AGENTS.md mencatat
 * bug yang sama untuk form Ganti PIN dan hanya memperbaikinya di satu tempat.
 *
 * Tes ini gagal lagi begitu ada yang menulis `->with('pesan', ...)` baru tanpa
 * menambah rendernya — persis tipe regresi yang diam-diam membuat aplikasi
 * terasa "rusak" untuk orang kantor.
 */
class FlashKonsistenTest extends TestCase
{
    use RefreshDatabase;

    /** Kunci flash yang memang bukan pesan untuk petugas. */
    private const DIIZINKAN = ['errors', 'inputs', 'old'];

    public function test_setiap_flash_pesan_dirender_oleh_layout(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));

        preg_match_all("/session\('([a-z_]+)'\)/", $layout, $cocok);
        $dirender = array_unique($cocok[1] ?? []);

        $this->assertNotEmpty($dirender, 'Layout tidak merender flash apa pun — tes ini kehilangan artinya.');

        $dipakai = [];

        foreach ($this->fileApp() as $path) {
            $isi = (string) file_get_contents($path);

            // `->with('kunci', ...)` = flash. `->with('relasi')` (eager load
            // Eloquent) tidak punya koma setelah kurung tutup, jadi tidak ikut.
            if (preg_match_all("/->with\('([a-z_]+)'\s*,/", $isi, $hit)) {
                foreach ($hit[1] as $kunci) {
                    if (! in_array($kunci, self::DIIZINKAN, true)) {
                        $dipakai[$kunci][] = basename($path);
                    }
                }
            }
        }

        $liar = array_diff(array_keys($dipakai), $dirender);

        $contoh = [];

        foreach ($liar as $kunci) {
            $contoh[] = "'".$kunci."' di ".implode(', ', array_slice(array_unique($dipakai[$kunci]), 0, 3));
        }

        $this->assertSame(
            [],
            array_values($liar),
            'Flash ditulis tapi tidak pernah dirender layout: '.implode('; ', $contoh).
            '. Layout hanya merender: '.implode(', ', $dirender).
            '. Pindahkan pesannya ke salah satu kunci itu, atau tambahkan rendernya di layout.'
        );
    }

    public function test_petugas_lihat_konfirmasi_setelah_menyimpan_surat_masuk(): void
    {
        // Bukti perilaku, bukan cuma pola teks: pesan ini dulu ditulis ke kunci
        // 'status' dan hilang sebelum sempat dibaca petugas.
        $user = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $primer = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);

        $this->actingAs($user)
            ->post(route('surat-masuk.store'), [
                'pengirim' => 'Kecamatan Gondanglegi',
                'klasifikasi_primer_id' => $primer->id,
                'sifat' => 'biasa',
                'nomor_surat' => 'FLASH-1',
                'perihal' => 'Undangan musyawarah desa',
                'tanggal_surat' => '2026-05-05',
                'tanggal_diterima' => '2026-05-06',
                'status_berkas' => 'asli',
            ])
            ->assertRedirect(route('surat-masuk.index'));

        $pesan = session('success');

        $this->assertNotNull($pesan, 'Simpan surat masuk tidak menulis flash pada kunci yang dirender layout.');

        $this->actingAs($user)
            ->get(route('surat-masuk.index'))
            ->assertOk()
            ->assertSee($pesan, false);
    }

    /**
     * @return list<string>
     */
    private function fileApp(): array
    {
        $files = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }
}
