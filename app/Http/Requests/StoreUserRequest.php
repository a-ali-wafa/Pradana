<?php

namespace App\Http\Requests;

use App\Models\User;
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
 * Otorisasi: route `users.*` sudah memakai `->middleware('admin')` sejak
 * 3 Sep 2026 (lihat AGENTS_HISTORY.md 12.26), jadi `authorize()` di sini
 * sengaja `true` — jangan dipindah ke FormRequest supaya satu sumber kebenaran
 * (middleware) tidak bersaing dengan sumber kedua.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'pin' => ['required', 'digits:8', 'confirmed'],
            // L-07: hanya role yang dikenal model — jangan tulis daftar kedua
            // di sini supaya pemangkasan enum tidak bisa terlewat di validasi.
            'role' => ['required', 'in:'.implode(',', array_keys(User::peranTersedia()))],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.digits' => 'PIN harus 8 digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak cocok.',
        ];
    }
}
