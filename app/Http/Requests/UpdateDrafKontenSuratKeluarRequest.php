<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Isi konten surat keluar untuk generator PDF (keputusan L-16, F1/F3).
 *
 * Kolom `lampiran` SENGAJA tidak diminta dari form: notasi jumlah berkas di
 * kop PDF dihitung otomatis dari lampiran yang benar-benar terunggah
 * (keputusan P6) supaya tidak bisa menulis "3 Berkas" padahal file-nya satu.
 */
class UpdateDrafKontenSuratKeluarRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Semua user login boleh mengisi draf — arsip kantor, tanpa sistem
        // kepemilikan (L-08), perubahannya tercatat di log aktivitas.
        return true;
    }

    public function rules(): array
    {
        return [
            'alamat_tujuan' => ['nullable', 'string', 'max:255'],
            'salam_pembuka' => ['nullable', 'string', 'max:100'],
            'isi_surat' => ['required', 'string', 'max:20000'],
            'salam_penutup' => ['nullable', 'string', 'max:100'],
            'atas_nama' => ['nullable', 'string', 'max:150'],
            'jabatan_penandatangan' => ['nullable', 'string', 'max:100'],
            'nip_nik' => ['nullable', 'string', 'max:50'],
            'tembusan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'isi_surat.required' => 'Isi surat wajib diisi sebelum surat bisa dicetak.',
        ];
    }
}
