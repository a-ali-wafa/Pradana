<?php

namespace App\Http\Requests;

use App\Models\KlasifikasiSekunder;
use App\Models\KlasifikasiTersier;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSuratKeluarRequest extends FormRequest
{
    /**
     * Otorisasi dasar cukup "sudah login" (middleware `auth` di controller).
     * TODO(B1): matriks permission per-role belum dikonfirmasi user. Kalau nanti
     * ternyata cuma role tertentu yang boleh input surat keluar, tambahkan
     * pengecekan role di sini atau pindah ke Policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'penerima' => ['required', 'string', 'max:255'],
            'jabatan_penerima' => ['nullable', 'string', 'max:255'],
            'instansi_penerima' => ['nullable', 'string', 'max:255'],

            'klasifikasi_primer_id' => ['required', 'integer', 'exists:klasifikasi_primer,id'],
            'klasifikasi_sekunder_id' => ['nullable', 'integer', 'exists:klasifikasi_sekunder,id'],
            'klasifikasi_tersier_id' => ['nullable', 'integer', 'exists:klasifikasi_tersier,id'],

            'sifat' => ['required', Rule::in(['mendesak', 'penting', 'rahasia', 'biasa'])],

            'kota_tujuan' => ['nullable', 'string', 'max:255'],
            'provinsi_tujuan' => ['nullable', 'string', 'max:255'],

            'tanggal_surat' => ['required', 'date'],

            'perihal' => ['required', 'string', 'max:255'],
            'ringkasan' => ['nullable', 'string'],

            'status_berkas' => ['required', Rule::in(['asli', 'salinan'])],
            'status_arsip' => ['nullable', Rule::in(['aktif', 'inaktif'])],

            'lokasi_fisik' => ['nullable', 'string', 'max:255'],

            // 'nomor_surat' SENGAJA tidak ada di sini. Nomor di-generate otomatis
            // oleh SuratKeluarController::generateNomorSurat() (lihat D2/D3 di
            // AGENTS.md — nomor global per tahun, reset tiap tahun, tanpa duplikat).
            // Formatnya sendiri masih placeholder, lihat TODO(D1) di controller.

            // 'user_id' juga tidak divalidasi dari input — diisi dari auth()->id()
            // di controller, bukan dari form.
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateHierarkiKlasifikasi($validator));
    }

    /**
     * Pastikan sekunder benar-benar anak dari primer yang dipilih, dan tersier
     * benar-benar anak dari sekunder yang dipilih. Mencegah data "nyasar" akibat
     * dropdown cascading yang di-tamper di sisi client.
     */
    protected function validateHierarkiKlasifikasi(Validator $validator): void
    {
        if ($this->filled('klasifikasi_sekunder_id')) {
            $sekunder = KlasifikasiSekunder::find($this->input('klasifikasi_sekunder_id'));

            if ($sekunder && (int) $sekunder->klasifikasi_primer_id !== (int) $this->input('klasifikasi_primer_id')) {
                $validator->errors()->add(
                    'klasifikasi_sekunder_id',
                    'Klasifikasi sekunder yang dipilih tidak berada di bawah klasifikasi primer yang dipilih.'
                );
            }
        }

        if ($this->filled('klasifikasi_tersier_id')) {
            $tersier = KlasifikasiTersier::find($this->input('klasifikasi_tersier_id'));

            if ($tersier && (int) $tersier->klasifikasi_sekunder_id !== (int) $this->input('klasifikasi_sekunder_id')) {
                $validator->errors()->add(
                    'klasifikasi_tersier_id',
                    'Klasifikasi tersier yang dipilih tidak berada di bawah klasifikasi sekunder yang dipilih.'
                );
            }
        }
    }

    public function messages(): array
    {
        return [
            'penerima.required' => 'Nama penerima wajib diisi.',
            'klasifikasi_primer_id.required' => 'Klasifikasi primer wajib dipilih.',
            'klasifikasi_primer_id.exists' => 'Klasifikasi primer yang dipilih tidak valid.',
            'klasifikasi_sekunder_id.exists' => 'Klasifikasi sekunder yang dipilih tidak valid.',
            'klasifikasi_tersier_id.exists' => 'Klasifikasi tersier yang dipilih tidak valid.',
            'sifat.required' => 'Sifat surat wajib dipilih.',
            'tanggal_surat.required' => 'Tanggal surat wajib diisi.',
            'perihal.required' => 'Perihal wajib diisi.',
            'status_berkas.required' => 'Status berkas wajib dipilih.',
        ];
    }
}
