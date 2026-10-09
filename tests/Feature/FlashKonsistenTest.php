<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\SuratMasuk;
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

        // Komentar Blade/HTML dibuang dulu: `{{-- session('status') --}}` di layout
        // bukan render, dan kalau dihitung bisa membuat kunci liar terasa "diizinkan".
        $layout = (string) preg_replace(
            ['/{{--.*?--}}/s', '/\{\{\/\*.*?\*\/\}\}/s', '/\{\#.*?\#\}/s', '/<!--.*?-->/s'],
            '',
            $layout
        );

        preg_match_all("/session\('([a-z_]+)'\)/", $layout, $cocok);
        $dirender = array_unique($cocok[1] ?? []);

        $this->assertNotEmpty($dirender, 'Layout tidak merender flash apa pun — tes ini kehilangan artinya.');

        $dipakai = [];

        foreach ($this->fileApp() as $path) {
            // `->with('kunci', ...)` = flash. `->with('relasi')` (eager load
            // Eloquent) tidak punya koma setelah kurung tutup, jadi tidak ikut.
            // `\s*` sebelum kutip itu BUKAN kosmetik: versi pertama pola ini hanya
            // membaca `->with(` yang kutipnya di baris yang SAMA, dan karena itu
            // meleset dari dua flash `status` di updateStatusArsip() — persis bug
            // yang tes ini harus tangkap. 9 Okt 2026.
            if (preg_match_all("/->with\(\s*'([a-z_]+)'\s*,/s", $this->kode($path), $hit)) {
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

    public function test_petugas_lihat_konfirmasi_setelah_menyahkan_surat(): void
    {
        // Jalur "Nyahkan" adalah bug yang lolos dari tes pola di atas: kedua
        // controller menulis flash multi-line ke kunci 'status', jadi tombolnya
        // terasa mati padahal status_arsip sudah berubah. Yang diuji di sini
        // bukan cuma datanya berubah (itu sudah ada di SuratTempatSampahTest),
        // tapi bahwa petugas sungguh melihat jawabannya.
        $user = User::forceCreate([
            'nama_lengkap' => 'Staf Nyahkan',
            'email' => 'staf.nyahkan@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $primer = KlasifikasiPrimer::forceCreate(['kode' => '02', 'nama' => 'Perencanaan']);

        $surat = SuratMasuk::forceCreate([
            'user_id' => $user->id,
            'pengirim' => 'Bappeda',
            'klasifikasi_primer_id' => $primer->id,
            'nomor_surat' => 'FLASH-2',
            'perihal' => 'Rencana kerja tahun depan',
            'tanggal_surat' => '2026-04-04',
            'tanggal_diterima' => '2026-04-05',
            'status_arsip' => 'aktif',
        ]);

        $this->actingAs($user)
            ->patch(route('surat-masuk.status-arsip', $surat), ['status_arsip' => 'inaktif'])
            ->assertRedirect();

        $surat->refresh();
        $this->assertSame('inaktif', $surat->status_arsip, 'Data berubah tapi itu bukan satu-satunya yang diuji di sini.');

        $pesan = session('success');

        $this->assertNotNull(
            $pesan,
            'Men-nyahkan surat tidak menulis flash pada kunci yang dirender layout — petugas tidak diberi tahu apa pun.'
        );

        $this->actingAs($user)
            ->get(route('surat-masuk.show', $surat))
            ->assertOk()
            ->assertSee($pesan, false);
    }

    /**
     * Isi file tanpa komentar — hasil `token_get_all()`, bukan regex hapus komentar
     * (yang bisa salah memotong string berisi `//`).
     *
     * Perlu karena pola `->with(` dibaca dari teks: docblock `UpdateStatusArsipRequest`
     * MENULIS `->with('status', ...)` sebagai penjelasan bug, dan prosa di dalam
     * komentar tidak boleh dianggap flash sungguhan. Bukti bahwa ini bekerja: tes
     * ini tetap hijau padahal komentar itu ada.
     *
     * String literal TETAP dipertahankan — kunci flash justru hidup di dalam string.
     */
    private function kode(string $path): string
    {
        $kode = '';

        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML], true)) {
                    continue;
                }
                $kode .= $token[1];
            } else {
                $kode .= $token;
            }
        }

        return $kode;
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
