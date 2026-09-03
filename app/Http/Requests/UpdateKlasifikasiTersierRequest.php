<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateKlasifikasiTersierRequest extends StoreKlasifikasiTersierRequest
{
    public function rules(): array
    {
        return [
            'klasifikasi_sekunder_id' => ['required', 'exists:klasifikasi_sekunder,id'],
            'kode' => [
                'required', 'string', 'max:5',
                Rule::unique('klasifikasi_tersier')
                    ->where(fn ($query) => $query->where('klasifikasi_sekunder_id', $this->input('klasifikasi_sekunder_id')))
                    ->ignore($this->route('klasifikasi_tersier')),
            ],
            'nama' => ['required', 'string', 'max:100'],
        ];
    }
}
