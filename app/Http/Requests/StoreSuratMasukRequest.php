<?php

namespace App\Http\Requests;

use App\Models\KlasifikasiSekunder;
use App\Models\KlasifikasiTersier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSuratMasukRequest extends FormRequest
{
    /**
     * Semua user login boleh input surat masuk (bukan admin-only) —
     * middleware('auth') di controller sudah cukup. Hanya destroy() yang
     * admin-only (keputusan default B2), dicek di controller, bukan di sini.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pengirim' => ['required', 'string', 'max:255'],
            'jabatan_pengirim' => ['nullable', 'string', 'max:255'],
            'instansi_pengirim' => ['nullable', 'string', 'max:255'],

            'klasifikasi_primer_id' => ['required', 'integer', 'exists:klasifikasi_primer,id'],
            'klasifikasi_sekunder_id' => ['nullable', 'integer', 'exists:klasifikasi_sekunder,id'],
            'klasifikasi_tersier_id' => ['nullable', 'integer', 'exists:klasifikasi_tersier,id'],

            'sifat' => ['required', Rule::in(['mendesak', 'penting', 'rahasia', 'biasa'])],

            // Manual, BUKAN auto-generate (beda dengan surat_keluar) dan
            // TIDAK unique — lihat AGENTS.md 12.3 & riwayat perubahan.
            'nomor_surat' => ['required', 'string', 'max:100'],

            'kota_asal' => ['nullable', 'string', 'max:255'],
            'provinsi_asal' => ['nullable', 'string', 'max:255'],

            'tanggal_surat' => ['required', 'date'],
            // D5 [DEFAULT]: tanggal_diterima tidak boleh lebih awal dari tanggal_surat.
            'tanggal_diterima' => ['required', 'date', 'after_or_equal:tanggal_surat'],

            'perihal' => ['required', 'string', 'max:255'],
            'ringkasan' => ['nullable', 'string'],

            'status_berkas' => ['required', Rule::in(['asli', 'salinan'])],

            'lokasi_fisik' => ['nullable', 'string', 'max:255'],

            // status_arsip SENGAJA tidak divalidasi di sini — di-hardcode
            // 'aktif' oleh controller saat create, dan belum ada jalur
            // update-nya (fitur toggle Inaktif ada di roadmap terpisah,
            // "Manajemen retensi/penyusutan", blocked by E1). Lihat AGENTS.md.
        ];
    }

    /**
     * Validasi hierarki klasifikasi (pola sama seperti Surat Keluar,
     * lihat AGENTS.md 12.6): pastikan klasifikasi_sekunder_id yang dipilih
     * benar anak dari klasifikasi_primer_id, begitu juga tersier ke
     * sekunder — supaya dropdown cascading yang di-tamper di client tidak
     * bikin data nyasar.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('klasifikasi_sekunder_id') && $this->filled('klasifikasi_primer_id')) {
                $sekunder = KlasifikasiSekunder::find($this->input('klasifikasi_sekunder_id'));

                if ($sekunder && (int) $sekunder->klasifikasi_primer_id !== (int) $this->input('klasifikasi_primer_id')) {
                    $validator->errors()->add(
                        'klasifikasi_sekunder_id',
                        'Klasifikasi sekunder yang dipilih bukan anak dari klasifikasi primer yang dipilih.'
                    );
                }
            }

            if ($this->filled('klasifikasi_tersier_id')) {
                $tersier = KlasifikasiTersier::find($this->input('klasifikasi_tersier_id'));

                if ($tersier && (
                    ! $this->filled('klasifikasi_sekunder_id')
                    || (int) $tersier->klasifikasi_sekunder_id !== (int) $this->input('klasifikasi_sekunder_id')
                )) {
                    $validator->errors()->add(
                        'klasifikasi_tersier_id',
                        'Klasifikasi tersier yang dipilih bukan anak dari klasifikasi sekunder yang dipilih.'
                    );
                }
            }
        });
    }
}
