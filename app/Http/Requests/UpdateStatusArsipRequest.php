<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tombol "Nyahkan" / "Aktifkan kembali" di halaman show surat (L-19, E8+E9).
 *
 * SATU request untuk kedua controller (surat masuk & surat keluar) justru karena
 * sebuah bug nyata yang ditemukan 9 Okt 2026: dulu kedua controller menulis
 * validasi yang sama persis di dalam method-nya masing-masing, dan keduanya
 * secara identik salah memilih kunci flash — `->with('status', ...)` yang tidak
 * pernah dirender layout, jadi konfirmasi "sudah di-nyahkan" hilang dan petugas
 * mengira tombolnya tidak jalan. Duplikasi tidak membuat bug itu dua kali lebih
 * kecil, tapi dua kali lebih mudah terlewat.
 *
 * Nilai yang sah = enum `status_arsip` di kedua tabel surat (aktif/inaktif).
 * "Musnah" SENGAJA bukan nilai di sini: pemusnahan bukan perubahan status, tapi
 * jalur sendiri (pengajuan → approval admin → forceDelete + Berita Acara, L-06),
 * dan tes menolak nilai 'musnah' di endpoint ini.
 */
class UpdateStatusArsipRequest extends FormRequest
{
    /** Status arsip yang bisa dipilih lewat tombol di layar. */
    public const NILAI = ['aktif', 'inaktif'];

    public function authorize(): bool
    {
        // Semua yang login boleh (L-08: arsip kantor, tidak ada sistem kepemilikan;
        // perubahan tercatat otomatis oleh observer). Admin-only hanya untuk
        // memulihkan dari tempat sampah & pemusnahan permanen.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status_arsip' => ['required', Rule::in(self::NILAI)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status_arsip.required' => 'Pilih status arsip: aktif atau inaktif.',
            'status_arsip.in' => 'Status arsip hanya boleh aktif atau inaktif.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status_arsip' => 'status arsip',
        ];
    }

    /**
     * Tombol mana yang dipencet: "Nyahkan" (inaktif) atau "Aktifkan kembali".
     * Dipakai controller untuk memilih kalimat flash, supaya `=== 'inaktif'`
     * tidak perlu ditulis dua kali di dua controller.
     */
    public function dinonaktifkan(): bool
    {
        return $this->validated()['status_arsip'] === 'inaktif';
    }
}
