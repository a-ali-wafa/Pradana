<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validasi + eksekusi login: email + PIN 8 digit angka (A1 + A11/L-12).
 * Pola tetap `Auth::attempt(['email' => ..., 'password' => $pin])` — kolom
 * `pin` dipakai lewat override `User::getAuthPassword()`, bukan guard khusus.
 *
 * Ini bekerja TANPA perlu ubah config/auth.php — EloquentUserProvider selalu
 * memanggil User::getAuthPassword() untuk ambil hash pembanding, jadi cukup
 * model User yang override method itu (return $this->attributes['pin']),
 * sesuai Bagian 2 AGENTS.md [LOCKED].
 *
 * ✅ Dicek 28 Agu 2026 terhadap User.php asli: override getAuthPassword()
 * SUDAH ada dan return $this->pin, persis sesuai locked #5/Bagian 2 — kode
 * di bawah ini siap jalan tanpa perubahan apa pun di sisi model.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'pin' => ['required', 'digits:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.digits' => 'PIN harus 8 digit angka.',
        ];
    }

    /**
     * Coba autentikasi, dibungkus rate limit sederhana per email+IP.
     * PIN 8 digit angka = 100 juta kombinasi, masih jauh lebih lemah dari
     * password bebas — throttle di sini penting, bukan sekadar nice-to-have.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $this->string('email'),
            'password' => $this->string('pin'), // 'pin' dari form, dicek ke getAuthPassword()
        ];

        // Tidak ada "remember me" (L-11): komputer di kantor dipakai bersama,
        // dan sesi yang tidak pernah kedaluwarsa berarti siapa pun yang duduk
        // di kursi itu bisa membaca arsip.
        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Email atau PIN salah.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
