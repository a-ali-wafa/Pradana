<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi "registrasi" user baru.
 *
 * Sesuai A3 [LOCKED, dikonfirmasi user 28 Agu 2026]: TIDAK ADA self-register
 * publik — akun baru dibuat LANGSUNG oleh admin. Tidak butuh kolom status
 * approval karena bukan alur dua tahap.
 *
 * ✅ Dicek 28 Agu 2026 terhadap User.php asli: rules di bawah cocok 100%
 * dengan $fillable model (`nama_lengkap`, `email`, `pin`, `role`).
 *
 * Otorisasi admin-only dicek manual di UserController::ensureAdmin() (pakai
 * helper User::isAdmin() yang sudah ada di model) — TODO(B1), belum ada
 * middleware/Gate role resmi karena B1 masih blocked.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dicek di controller (ensureAdmin()), konsisten dgn pola controller lain
    }

    public function rules(): array
    {
        return [
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'pin' => ['required', 'digits:6', 'confirmed'],
            'role' => ['required', 'in:admin,perangkat,kepala'], // sesuai enum Bagian 5
        ];
    }

    public function messages(): array
    {
        return [
            'pin.digits' => 'PIN harus 6 digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak cocok.',
        ];
    }
}
