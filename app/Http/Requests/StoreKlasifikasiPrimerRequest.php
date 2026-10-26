<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Field & panjang kolom sesuai skema `klasifikasi_primer`:
 * kode string(5) unique, nama string(100). Dikonfirmasi cocok dengan
 * `KlasifikasiPrimer` aslinya.
 */
class StoreKlasifikasiPrimerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pengecekan admin dilakukan manual di controller (ensureAdmin()).
        return true;
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:5', 'unique:klasifikasi_primer,kode'],
            'nama' => ['required', 'string', 'max:100'],
        ];
    }
}
