<?php

namespace Tests\Feature;

use App\Models\PengaturanInstansi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * L-11 (remember-me dihapus) + L-12 (PIN 8 digit; reset oleh admin DAN ganti
 * PIN sendiri) + I3 (seeder produksi tidak menanam akun; akun pertama lewat
 * perintah terminal).
 */
class PinAkunTest extends TestCase
{
    use RefreshDatabase;

    private const PIN = '20261004';

    private User $admin;

    private User $pegawai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'nama_lengkap' => 'Kepala',
            'email' => 'kepala@example.test',
            'pin' => Hash::make(self::PIN),
            'role' => 'admin',
        ]);

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf',
            'email' => 'staf@example.test',
            'pin' => Hash::make(self::PIN),
            'role' => 'pegawai',
        ]);
    }

    public function test_login_hanya_mau_pin_delapan_digit(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'pin' => '123456'])
            ->assertSessionHasErrors('pin');

        $this->post('/login', ['email' => $this->admin->email, 'pin' => self::PIN])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_login_tidak_lagi_mengingat_perangkat(): void
    {
        $respons = $this->post('/login', [
            'email' => $this->admin->email,
            'pin' => self::PIN,
            'remember' => '1',
        ]);

        // L-11: tidak ada "remember me" — komputer kantor dipakai bersama.
        $this->assertSame([], array_values(array_filter(
            $respons->headers->getCookies(),
            fn ($c) => str_starts_with($c->getName(), 'remember')
        )));

        // Kolom `remember_token` sudah dibuang dari skema (squash S11), jadi
        // tidak ada tempat untuk menyimpan token login-teringat.
        $this->assertFalse(
            Schema::hasColumn('users', 'remember_token')
        );
    }

    public function test_form_buat_user_menolak_pin_enam_digit(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'nama_lengkap' => 'Staf Baru',
                'email' => 'staf.baru@example.test',
                'pin' => '123456',
                'pin_confirmation' => '123456',
                'role' => 'pegawai',
            ])
            ->assertSessionHasErrors('pin');
    }

    public function test_admin_bisa_mereset_pin_staf(): void
    {
        $lama = $this->pegawai->pin;

        $this->actingAs($this->admin)
            ->patch(route('users.pin.update', $this->pegawai), [
                'pin' => '87654321',
                'pin_confirmation' => '87654321',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('87654321', $this->pegawai->fresh()->pin));
        $this->assertNotSame($lama, $this->pegawai->fresh()->pin);
    }

    public function test_staf_tidak_bisa_mereset_pin_orang_lain(): void
    {
        $lain = User::forceCreate([
            'nama_lengkap' => 'Staf Lain',
            'email' => 'staf.lain@example.test',
            'pin' => Hash::make(self::PIN),
            'role' => 'pegawai',
        ]);

        $this->actingAs($this->pegawai)
            ->patch(route('users.pin.update', $lain), [
                'pin' => '11111111',
                'pin_confirmation' => '11111111',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check(self::PIN, $lain->fresh()->pin));
    }

    public function test_ganti_pin_sendiri_mintakan_pin_lama_yang_benar(): void
    {
        // PIN lama salah -> ditolak, PIN tidak berubah.
        $this->actingAs($this->pegawai)
            ->patch(route('profil.pin.update'), [
                'pin_lama' => '99999999',
                'pin' => '11112222',
                'pin_confirmation' => '11112222',
            ])
            ->assertSessionHasErrors('pin_lama');

        $this->assertTrue(Hash::check(self::PIN, $this->pegawai->fresh()->pin));

        // PIN baru sama dengan PIN lama -> ditolak (mencegah "ganti" palsu).
        $this->actingAs($this->pegawai)
            ->patch(route('profil.pin.update'), [
                'pin_lama' => self::PIN,
                'pin' => self::PIN,
                'pin_confirmation' => self::PIN,
            ])
            ->assertSessionHasErrors('pin');

        // Benar semuanya -> berganti, dan login dengan PIN lama harus gagal.
        $this->actingAs($this->pegawai)
            ->patch(route('profil.pin.update'), [
                'pin_lama' => self::PIN,
                'pin' => '11112222',
                'pin_confirmation' => '11112222',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('11112222', $this->pegawai->fresh()->pin));
    }

    public function test_halaman_ganti_pin_menampilkan_form(): void
    {
        $this->actingAs($this->pegawai)
            ->get(route('profil.pin.edit'))
            ->assertOk()
            ->assertSee('Ganti PIN Saya')
            ->assertSee('name="pin_lama"', false);
    }

    // Sengaja test terpisah: `actingAs()` di atas membuat user tetap login
    // untuk sisa request di test yang sama, jadi cek "tamu ditolak" tidak bisa
    // digabung di situ.
    public function test_tamu_ditolak_di_halaman_ganti_pin(): void
    {
        $this->get(route('profil.pin.edit'))->assertRedirect(route('login'));
        $this->patch(route('profil.pin.update'), ['pin_lama' => '11111111', 'pin' => '22222222'])
            ->assertRedirect(route('login'));
    }

    public function test_perintah_arsip_reset_pin(): void
    {
        $this->artisan('arsip:reset-pin', ['email' => $this->pegawai->email, '--pin' => '12345'])
            ->assertFailed();

        $this->assertTrue(Hash::check(self::PIN, $this->pegawai->fresh()->pin));

        $this->artisan('arsip:reset-pin', ['email' => $this->pegawai->email, '--pin' => '55556666'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('55556666', $this->pegawai->fresh()->pin));

        $this->artisan('arsip:reset-pin', ['email' => 'tidak.ada@example.test', '--pin' => '55556666'])
            ->assertFailed();
    }

    public function test_akun_pertama_hanya_saat_belum_ada_admin(): void
    {
        $this->artisan('arsip:akun-pertama', [
            'email' => 'kandidat@example.test',
            'nama' => 'Calon Admin',
            '--pin' => '11223344',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'kandidat@example.test']);

        // Hapus admin yang ada -> sekarang boleh.
        User::where('role', 'admin')->forceDelete();

        $this->artisan('arsip:akun-pertama', [
            'email' => 'kandidat@example.test',
            'nama' => 'Calon Admin',
            '--pin' => '11223344',
        ])->assertSuccessful();

        $baru = User::where('email', 'kandidat@example.test')->sole();
        $this->assertTrue($baru->isAdmin());
        $this->assertTrue(Hash::check('11223344', $baru->pin));
    }

    public function test_seeder_produksi_tidak_menanam_akun(): void
    {
        // I3: DatabaseSeeder tidak boleh membuat user (email pribadi + PIN
        // keras dulu ikut ter-commit ke repository).
        $sebelum = User::count();
        PengaturanInstansi::query()->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($sebelum, User::count());
        $this->assertDatabaseHas('pengaturan_instansi', ['nama_instansi' => '']);
    }

    public function test_dev_seeder_ditolak_di_luar_local(): void
    {
        config(['app.env' => 'production']);

        $this->seed(DevSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'dev-admin@example.test']);
    }
}
