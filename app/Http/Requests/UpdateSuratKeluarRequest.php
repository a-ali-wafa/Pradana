<?php

namespace App\Http\Requests;

use App\Models\KlasifikasiSekunder;
use App\Models\KlasifikasiTersier;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSuratKeluarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Route parameter bernama `surat_keluar` (lihat SuratKeluarController).
        $suratKeluarId = $this->route('surat_keluar')?->id;

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
            'status_arsip' => ['required', Rule::in(['aktif', 'inaktif'])],

            'lokasi_fisik' => ['nullable', 'string', 'max:255'],

            // Beda dari Store: nomor boleh dikoreksi manual (misal salah ketik
            // saat generate), tapi tetap wajib unik.
            'nomor_surat' => [
                'required',
                'string',
                'max:100',
                Rule::unique('surat_keluar', 'nomor_surat')->ignore($suratKeluarId),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateHierarkiKlasifikasi($validator);
            $this->validateKoreksiNomorOlehAdmin($validator);
        });
    }

    /**
     * L-20 (D7=b): hanya admin yang boleh MENGUBAH nomor surat keluar yang sudah
     * terbit. Form tetap mengirim nilai nomor aslinya untuk semua role, jadi
     * yang dilarang adalah deltasnya — bukan field-nya.
     */
    protected function validateKoreksiNomorOlehAdmin(Validator $validator): void
    {
        if ($this->user()?->isAdmin()) {
            return;
        }

        $lama = $this->route('surat_keluar')?->nomor_surat;

        if ($lama !== null && $this->input('nomor_surat') !== $lama) {
            $validator->errors()->add(
                'nomor_surat',
                'Hanya admin yang bisa mengubah nomor surat keluar yang sudah terbit.'
            );
        }
    }

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
            'tanggal_surat.required' => 'Tanggal surat wajib diisi.',
            'perihal.required' => 'Perihal wajib diisi.',
            'status_berkas.required' => 'Status berkas wajib dipilih.',
            'status_arsip.required' => 'Status arsip wajib dipilih.',
            'nomor_surat.required' => 'Nomor surat wajib diisi.',
            'nomor_surat.unique' => 'Nomor surat ini sudah dipakai surat keluar lain.',
        ];
    }
}
