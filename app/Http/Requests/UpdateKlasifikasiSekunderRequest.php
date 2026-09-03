<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateKlasifikasiSekunderRequest extends StoreKlasifikasiSekunderRequest
{
    public function rules(): array
    {
        return [
            'klasifikasi_primer_id' => ['required', 'exists:klasifikasi_primer,id'],
            'kode' => [
                'required', 'string', 'max:5',
                Rule::unique('klasifikasi_sekunder')
                    ->where(fn ($query) => $query->where('klasifikasi_primer_id', $this->input('klasifikasi_primer_id')))
                    ->ignore($this->route('klasifikasi_sekunder')),
            ],
            'nama' => ['required', 'string', 'max:100'],
        ];
    }
}
