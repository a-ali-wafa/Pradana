<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * SQUASH S11 (4 Okt 2026): versi sebelumnya masih milik boilerplate Laravel —
 * mengisi `name` dan `password` (kolom riilnya `nama_lengkap` dan `pin`) plus
 * `email_verified_at`/`remember_token` yang sudah dibuang dari skema, jadi
 * `User::factory()->create()` akan selalu gagal. Diperbaiki supaya benar-benar
 * bisa dipakai; test yang perlu PIN tertentu tetap memakai forceCreate.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_lengkap' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'pin' => Hash::make('12345678'),
            'role' => User::ROLE_PEGAWAI,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
        ]);
    }
}
