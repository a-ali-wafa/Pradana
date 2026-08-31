<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validasi + eksekusi login: email + PIN 6 digit angka, sesuai A1 [DEFAULT]
 * (Auth::attempt(['email' => ..., 'password' => $pin])).
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
            'pin' => ['required', 'digits:6'],
        ];
    }

    /**
     * Coba autentikasi, dibungkus rate limit sederhana per email+IP.
     * PIN 6 digit angka = cuma 1 juta kombinasi, jauh lebih lemah dari password
     * bebas — throttle di sini penting, bukan sekadar nice-to-have.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $this->string('email'),
            'password' => $this->string('pin'), // 'pin' dari form, dicek ke getAuthPassword()
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
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
