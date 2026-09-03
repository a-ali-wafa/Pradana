<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * klasifikasi_sekunder: klasifikasi_primer_id FK wajib, kode+nama unique KOMPOSIT per
 * primer (bukan unique global). Panjang kolom kode(5)/nama(100) BELUM diverifikasi ke
 * migration asli — diasumsikan sama seperti klasifikasi_primer, lihat AGENTS_HISTORY.md 12.11.
 */
class StoreKlasifikasiSekunderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pengecekan admin dilakukan manual di controller (ensureAdmin()), lihat 12.11.
        return true;
    }

    public function rules(): array
    {
        return [
            'klasifikasi_primer_id' => ['required', 'exists:klasifikasi_primer,id'],
            'kode' => [
                'required', 'string', 'max:5',
                Rule::unique('klasifikasi_sekunder')
                    ->where(fn ($query) => $query->where('klasifikasi_primer_id', $this->input('klasifikasi_primer_id'))),
            ],
            'nama' => ['required', 'string', 'max:100'],
        ];
    }
}
