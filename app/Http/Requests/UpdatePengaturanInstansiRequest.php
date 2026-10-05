<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form Pengaturan Instansi (kop surat).
 *
 * Panjang kolom SUDAH cocok dengan migration hasil squash S11
 * (`2026_10_04_100002_create_pengaturan_instansi_table.php` +
 * `2026_10_05_000001_tambah_bagian_kop_pengaturan_instansi.php`) — komentar lama di
 * file ini pernah menyebut angkanya tebakan, itu sudah tidak benar sejak squash.
 *
 * 5 Okt 2026:
 * - `nama_kabupaten`, `nama_kecamatan`, `kode_pos` ikut divalidasi (kolom barunya
 *   kop bertingkat ala tata naskah dinas desa). Semuanya BOLEH KOSONG: tidak semua
 *   kantor mau kop tiga baris, dan kantor yang sama sekali tidak punya email/telepon
 *   harus tetap bisa menyimpan kop — makanya `no_telp` dan `email` yang tadinya
 *   `required` diturunkan jadi opsional. Yang tetap wajib cuma nama, jenis, dan
 *   alamat, karena tanpa itu surat tidak punya kepala.
 * - `logo`: 2MB, jpg/jpeg/png (asumsi [DEFAULT-agent], bukan keputusan user — ini
 *   aset kop, bukan dokumen arsip, jadi batas lampiran C1/L-13 tidak berlaku).
 *
 * Otorisasi admin-only TIDAK dicek di authorize(): route `pengaturan-instansi`
 * sudah memakai `->middleware('admin')` (L-07), jadi satu sumber kebenaran saja.
 */
class UpdatePengaturanInstansiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_instansi' => ['required', 'string', 'max:150'],
            'nama_kabupaten' => ['nullable', 'string', 'max:100'],
            'nama_kecamatan' => ['nullable', 'string', 'max:100'],
            'jenis_instansi' => ['required', 'string', 'max:150'],
            'alamat_instansi' => ['required', 'string', 'max:255'],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'no_telp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_instansi' => 'nama instansi',
            'nama_kabupaten' => 'nama kabupaten',
            'nama_kecamatan' => 'nama kecamatan',
            'jenis_instansi' => 'jenis instansi',
            'alamat_instansi' => 'alamat instansi',
            'kode_pos' => 'kode pos',
            'no_telp' => 'nomor telepon',
        ];
    }
}
