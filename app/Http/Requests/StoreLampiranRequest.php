<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi upload lampiran (dipakai untuk surat_masuk maupun surat_keluar).
 *
 * Aturan sesuai AGENTS.md Bagian 9 [DEFAULT]:
 * - C1: tipe file diizinkan pdf, jpg, jpeg, png.
 * - C2: maksimum 10MB per file.
 *
 * ASUMSI (lihat AGENTS.md 12.16): mendukung banyak file sekaligus dalam
 * satu submit (`files[]`) untuk mendukung "multi-lampiran per surat"
 * (keputusan locked #8). Kalau ternyata UI yang diinginkan cuma 1 file per
 * submit, tinggal ganti field jadi `file` tunggal — LampiranController
 * tidak perlu diubah banyak (tinggal bungkus jadi array [$file] sebelum loop).
 */
class StoreLampiranRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi "siapa boleh upload" dicek di route/controller
        // (middleware('auth')), bukan di sini.
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // max dalam KB -> 10MB (C2)
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Pilih minimal satu file untuk diunggah.',
            'files.*.mimes' => 'File harus berformat PDF, JPG, JPEG, atau PNG.',
            'files.*.max' => 'Ukuran file maksimal 10MB.',
        ];
    }
}
