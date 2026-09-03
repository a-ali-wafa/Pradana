<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * extends StoreKlasifikasiPrimerRequest (pola sama seperti UpdateSuratMasukRequest, lihat 12.6),
 * cuma override rules() untuk ignore ID sendiri di unique check `kode` supaya update tanpa
 * ganti kode tidak ikut ditolak.
 */
class UpdateKlasifikasiPrimerRequest extends StoreKlasifikasiPrimerRequest
{
    public function rules(): array
    {
        return [
            'kode' => [
                'required', 'string', 'max:5',
                Rule::unique('klasifikasi_primer', 'kode')->ignore($this->route('klasifikasi_primer')),
            ],
            'nama' => ['required', 'string', 'max:100'],
        ];
    }
}
