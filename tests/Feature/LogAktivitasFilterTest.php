<?php

namespace Tests\Feature;

use App\Models\Aktivitas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Filter halaman log aktivitas setelah dirapikan (Fase 1, 9 Okt 2026).
 *
 * Dua hal yang berubah di `AktivitasController`:
 * 1. `whereDate('created_at', …)` dibuang — fungsinya di atas kolom membuat
 *    index tidak terpakai, diganti interval setengah terbuka.
 * 2. Kotak "cari" dulu `like "%$kata%"` polos: `%` dan `_` yang diketik user
 *    diperlakukan sebagai wildcard, bukan sebagai karakter yang dicari.
 *
 * Perilaku yang diuji justru yang TIDAK boleh berubah oleh perbaikan itu:
 * "sampai 5 Oktober" tetap berarti sampai akhir hari 5 Oktober.
 */
class LogAktivitasFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'nama_lengkap' => 'Kepala Desa',
            'email' => 'kepala@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'admin',
        ]);
    }

    private function catat(string $waktu, string $aksi): Aktivitas
    {
        $catatan = Aktivitas::forceCreate([
            'user_id' => $this->admin->id,
            'aksi' => $aksi,
        ]);

        // Diisi eksplisit supaya tes tidak bergantung pada jam saat suite jalan.
        $catatan->created_at = Carbon::parse($waktu);
        $catatan->updated_at = Carbon::parse($waktu);
        $catatan->save();

        return $catatan;
    }

    public function test_sampai_berarti_akhir_hari_itu_bukan_awal_hari(): void
    {
        $this->catat('2026-10-04 12:00:00', 'Masuk 4 Okt siang');
        $this->catat('2026-10-05 07:05:00', 'Masuk 5 Okt pagi');
        $this->catat('2026-10-05 23:59:00', 'Masuk 5 Okt lewat tengah malam');
        $this->catat('2026-10-06 00:01:00', 'Masuk 6 Okt');

        $layar = $this->actingAs($this->admin)
            ->get(route('aktivitas.index', ['dari' => '2026-10-05', 'sampai' => '2026-10-05']))
            ->assertOk();

        $layar->assertSee('Masuk 5 Okt pagi')
            ->assertSee('Masuk 5 Okt lewat tengah malam')
            ->assertDontSee('Masuk 4 Okt siang')
            ->assertDontSee('Masuk 6 Okt');
    }

    public function test_filter_satu_sisi_berfungsi(): void
    {
        $this->catat('2026-10-01 08:00:00', 'Awal Oktober');
        $this->catat('2026-09-30 08:00:00', 'Akhir September');

        $this->actingAs($this->admin)
            ->get(route('aktivitas.index', ['dari' => '2026-10-01']))
            ->assertOk()
            ->assertSee('Awal Oktober')
            ->assertDontSee('Akhir September');

        $this->actingAs($this->admin)
            ->get(route('aktivitas.index', ['sampai' => '2026-09-30']))
            ->assertOk()
            ->assertSee('Akhir September')
            ->assertDontSee('Awal Oktober');
    }

    public function test_periode_terbalik_dibalik(): void
    {
        $this->catat('2026-10-05 10:00:00', 'Catatan pertengahan');
        $this->catat('2026-10-20 10:00:00', 'Catatan belakangan');

        // Kotak "dari" dan "sampai" terisi tertukar: yang dimaksud tetap rentang
        // 5-20 Oktober, bukan hasil kosong (sama seperti perlakuan di /laporan).
        $this->actingAs($this->admin)
            ->get(route('aktivitas.index', ['dari' => '2026-10-20', 'sampai' => '2026-10-05']))
            ->assertOk()
            ->assertSee('Catatan pertengahan')
            ->assertSee('Catatan belakangan');
    }

    public function test_karakter_like_di_kotak_cari_diperlakukan_sebagai_huruf_biasa(): void
    {
        $this->catat('2026-10-05 10:00:00', 'Menyetujui pemusnahan arsip (20 surat)');
        $this->catat('2026-10-05 10:05:00', 'Mengunggah berkas 20% jadi');

        $cocok = $this->actingAs($this->admin)
            ->get(route('aktivitas.index', ['cari' => '20%']))
            ->assertOk();

        // Tanpa ESCAPE, pola `LIKE '%20%%'` membuat `%` kedua jadi wildcard dan
        // baris "(20 surat)" ikut terseret.
        $cocok->assertSee('Mengunggah berkas 20% jadi');
        $cocok->assertDontSee('Menyetujui pemusnahan arsip');
    }

    public function test_urutan_stabil_untuk_catatan_sedetik(): void
    {
        // Log bisa berisi beberapa aksi dalam detik yang sama (satu request
        // mencatat beberapa baris). Tanpa pemecah seri, urutannya tidak menentu
        // dan baris bisa muncul dua kali / hilang di halaman berikutnya.
        $pertama = $this->catat('2026-10-05 10:00:00', 'Aksi pertama sedetik');
        $kedua = $this->catat('2026-10-05 10:00:00', 'Aksi kedua sedetik');

        $baris = $this->actingAs($this->admin)
            ->get(route('aktivitas.index'))
            ->viewData('aktivitas');

        $this->assertSame([$kedua->id, $pertama->id], $baris->pluck('id')->all());
    }
}
