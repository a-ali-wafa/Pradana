<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/**
 * User mengganti PIN miliknya sendiri (L-12 / A5=a).
 *
 * PIN lama WAJIB diisi dan dicocokkan: tanpa itu, sesi yang ditinggal terbuka
 * di komputer kantor cukup satu kali klik untuk mengambil alih akun.
 */
class GantiPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin_lama' => ['required', 'digits:8'],
            'pin' => ['required', 'digits:8', 'confirmed', 'different:pin_lama'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! Hash::check($this->string('pin_lama'), $this->user()->pin)) {
                $validator->errors()->add('pin_lama', 'PIN lama tidak cocok.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'pin_lama.required' => 'PIN lama wajib diisi.',
            'pin_lama.digits' => 'PIN lama harus 8 digit angka.',
            'pin.required' => 'PIN baru wajib diisi.',
            'pin.digits' => 'PIN baru harus 8 digit angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak sama dengan PIN baru.',
            'pin.different' => 'PIN baru harus berbeda dari PIN lama.',
        ];
    }
}
