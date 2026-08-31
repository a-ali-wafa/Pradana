<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ⚠️ ASUMSI panjang kolom. `nama_instansi`/`jenis_instansi`/`alamat_instansi`/
 * `no_telp`/`email` di skema Bagian 5 AGENTS.md TIDAK dicantumkan panjangnya secara
 * eksplisit (beda dari tabel lain yang selalu pakai notasi string(N), mis.
 * `users.nama_lengkap` string(150)) — migration `pengaturan_instansi` juga tidak ada
 * di sesi ini untuk dicek langsung. Panjang di bawah ini tebakan wajar (varchar 255
 * default Laravel, alamat dilonggarkan ke 500). WAJIB disesuaikan begitu migration/
 * model asli tersedia.
 *
 * `logo`: field upload BARU, tidak ada preseden ukuran/tipe maks di AGENTS.md untuk
 * logo instansi (beda dari lampiran surat yang sudah punya C1/C2 [DEFAULT]:
 * pdf/jpg/jpeg/png, maks 10MB — itu untuk dokumen arsip, bukan logo). Dipakai batas
 * lebih kecil & khusus gambar (2MB, jpg/jpeg/png saja) karena ini cuma gambar kop
 * surat. Ini ASUMSI SAYA, bukan keputusan user — tandai untuk dikonfirmasi.
 *
 * Otorisasi admin-only sengaja TIDAK dicek di authorize() di sini — dilakukan lewat
 * ensureAdmin() manual di PengaturanInstansiController (TODO(B1)), authorize() cuma
 * jaring pengaman tambahan, konsisten dengan pola FormRequest lain di project ini.
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
            'nama_instansi' => ['required', 'string', 'max:255'],
            'jenis_instansi' => ['required', 'string', 'max:255'],
            'alamat_instansi' => ['required', 'string', 'max:500'],
            'no_telp' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
