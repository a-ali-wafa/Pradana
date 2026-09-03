<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * klasifikasi_tersier: klasifikasi_sekunder_id FK wajib, kode+nama unique KOMPOSIT per
 * sekunder (bukan unique global). Panjang kolom kode(5)/nama(100) BELUM diverifikasi ke
 * migration asli — diasumsikan sama seperti klasifikasi_primer, lihat AGENTS_HISTORY.md 12.11.
 */
class StoreKlasifikasiTersierRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pengecekan admin dilakukan manual di controller (ensureAdmin()), lihat 12.11.
        return true;
    }

    public function rules(): array
    {
        return [
            'klasifikasi_sekunder_id' => ['required', 'exists:klasifikasi_sekunder,id'],
            'kode' => [
                'required', 'string', 'max:5',
                Rule::unique('klasifikasi_tersier')
                    ->where(fn ($query) => $query->where('klasifikasi_sekunder_id', $this->input('klasifikasi_sekunder_id'))),
            ],
            'nama' => ['required', 'string', 'max:100'],
        ];
    }
}
