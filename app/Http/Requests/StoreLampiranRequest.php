<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi upload lampiran.
 *
 * L-13: tipe file diperluas ke dokumen kantor (doc/docx/xls/xlsx) — surat
 * dari instansi lain sering datang sebagai .docx, dan dengan daftar lama
 * file itu tidak bisa diarsipkan sama sekali.
 *
 * C2 dinaikkan 10MB -> 25MB (didelegasikan ke agent): scan berkualitas tinggi
 * sering lewat dari 10MB. Aman karena penulisan ke disk lokal memakai
 * $file->store() yang memindahkan file sementara, bukan memuat isinya ke RAM.
 */
class StoreLampiranRequest extends FormRequest
{
    /**
     * Siapa boleh upload dicek di route (middleware auth) — semua user login
     * boleh mengarsipkan surat untuk kantor (L-08, tanpa sistem kepemilikan).
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:25600'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Pilih minimal satu file untuk diunggah.',
            'files.max' => 'Maksimal 10 file dalam satu kali unggah.',
            'files.*.mimes' => 'Format file harus PDF, JPG, PNG, DOC, DOCX, XLS, atau XLSX.',
            'files.*.max' => 'Ukuran file maksimal 25MB.',
        ];
    }
}
