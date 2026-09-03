<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Field & panjang kolom sesuai skema Bagian 5 AGENTS.md (klasifikasi_primer):
 * kode string(5) unique, nama string(100). Dikonfirmasi cocok dengan
 * KlasifikasiPrimer.php asli 26 Agu 2026 (lihat AGENTS_HISTORY.md 12.11).
 */
class StoreKlasifikasiPrimerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pengecekan admin dilakukan manual di controller (ensureAdmin()), lihat 12.11.
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
