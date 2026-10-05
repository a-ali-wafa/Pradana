<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi panel "Hasil baca isi lampiran" di halaman surat masuk (5 Okt 2026).
 *
 * Form request TERPISAH dari UpdateSuratMasukRequest dengan alasan sengaja:
 * yang diubah di sini hanya teks hasil baca (+ opsional salinan ke ringkasan),
 * sedangkan UpdateSuratMasukRequest memvalidasi seluruh field surat (nomor,
 * tanggal, klasifikasi, ...) dan akan menolak permintaan parsial ini dengan
 * error "wajib diisi" pada field yang tidak disentuh user.
 *
 * `jadikan_ringkasan` bukan field teks: checkbox HTML yang dicentang datang
 * sebagai "1"/"on", yang tidak dicentang tidak ikut terkirim sama sekali.
 */
class UpdateIsiSuratMasukRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Semua role yang login boleh (L-08: surat kantor boleh diubah siapa pun,
        // tercatat di log aktivitas). Route-nya sudah `middleware('auth')`.
        return true;
    }

    public function rules(): array
    {
        return [
            // longText di DB; batas ini cuma pelindung dari tempelan raksasa.
            'isi_hasil_baca' => ['nullable', 'string', 'max:60000'],
            'jadikan_ringkasan' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'isi_hasil_baca' => 'hasil baca isi surat',
        ];
    }
}
