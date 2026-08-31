<?php

namespace App\Http\Requests;

use App\Models\KlasifikasiSekunder;
use App\Models\KlasifikasiTersier;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSuratKeluarRequest extends FormRequest
{
    /**
     * TODO(B1): sama seperti StoreSuratKeluarRequest — sesuaikan kalau matriks
     * permission per-role sudah dikonfirmasi.
     */
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

            // Beda dari Store: di update nomor_surat boleh dikoreksi manual
            // (misal salah ketik saat generate), tapi tetap wajib unik.
            // TODO(D1): tinjau ulang aturan ini kalau format resmi nomor surat
            // ternyata tidak boleh diedit bebas setelah terbit.
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
        $validator->after(fn (Validator $validator) => $this->validateHierarkiKlasifikasi($validator));
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
