<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin menetapkan PIN baru untuk user lain (L-12 / A4=a) — inilah jalan yang
 * dipakai kantor saat staf lupa PIN, karena tidak ada alur reset lewat email.
 *
 * Sengaja TIDAK ada field email/role di sini: yang boleh diubah cuma PIN,
 * supaya "reset PIN" tidak diam-diam jadi "edit user" (termasuk menaikkan
 * role seseorang).
 */
class ResetPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route users.* sudah middleware('admin')
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'digits:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.required' => 'PIN baru wajib diisi.',
            'pin.digits' => 'PIN harus 8 digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak sama dengan PIN baru.',
        ];
    }
}
