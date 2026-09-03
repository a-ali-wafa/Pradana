<?php

namespace App\Http\Controllers;

use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * BARU — 1 Sep 2026. Roadmap "Generate PDF surat keluar (DomPDF, sesuai F2)".
 * F1–F4 (Bagian 9 AGENTS.md, semua [DEFAULT]) TIDAK diblokir WAJIB TANYA USER
 * apa pun, jadi fitur ini langsung dikerjakan tanpa perlu konfirmasi tambahan.
 *
 * ⚠️ Controller ini BERDIRI SENDIRI (bukan nambah method ke SuratKeluarController
 * yang sudah ada) karena SuratKeluarController.php TIDAK diupload ke sesi ini —
 * mengikuti prinsip 12.9: jangan mengedit/menebak isi file yang tidak tersedia.
 * Kalau lebih suka method ini dipindah jadi bagian dari SuratKeluarController,
 * upload file itu di sesi berikutnya, tinggal dipindah manual (logic-nya sama).
 *
 * ASUMSI yang masih perlu diperhatikan (model SuratKeluar & DrafKontenSuratKeluar
 * ASLI tidak diupload ke sesi ini):
 * - Relasi `drafKonten()`, `primer()`, `sekunder()`, `tersier()`, `petugas()` di
 *   SuratKeluar SUDAH terverifikasi 28 Agu 2026 (lihat 12.13 di AGENTS_HISTORY.md)
 *   — dipakai langsung, bukan tebakan baru.
 * - Kolom-kolom `draf_konten_surat_keluar` diambil dari skema Bagian 5 [LOCKED].
 *   Model aslinya sendiri belum pernah di-cross-check langsung, tapi karena
 *   migration/skema sudah final risikonya jauh lebih kecil dibanding risiko nama
 *   RELASI (yang pernah salah tebak di 12.11) — bukan berarti nol risiko.
 * - Middleware: 'auth' polos (BUKAN admin-only) — dianalogikan ke show()/index()
 *   yang juga auth polos (12.12), karena mencetak surat bukan aksi destruktif.
 *   Kalau ternyata harus dibatasi role tertentu, itu balik ke pertanyaan B1.
 * - Logo (`pengaturan_instansi.logo_path`, disimpan LOKAL sesuai C7) diakses lewat
 *   `public_path('storage/'.$logoPath)` (path filesystem langsung), BUKAN URL —
 *   dompdf lebih andal baca file lokal daripada fetch HTTP. Butuh
 *   `php artisan storage:link` sudah dijalankan (sama seperti catatan di 12.19).
 * - Format nomor surat DITAMPILKAN apa adanya dari `nomor_surat` (tidak dihasilkan
 *   ulang di sini) — jadi TIDAK terpengaruh status D1 [WAJIB TANYA USER] yang masih
 *   soal *pembuatan* nomor, bukan soal menampilkannya.
 */
class CetakSuratKeluarController extends Controller
{
    public function cetak(SuratKeluar $surat_keluar)
    {
        $surat_keluar->load(['primer', 'sekunder', 'tersier', 'petugas', 'drafKonten']);

        if (! $surat_keluar->drafKonten) {
            return back()->with(
                'error',
                'Draf konten surat ini belum diisi. Lengkapi draf konten dulu sebelum mencetak.'
            );
        }

        $instansi = PengaturanInstansi::first();

        $logoPath = $instansi?->logo_path ? public_path('storage/'.$instansi->logo_path) : null;

        $pdf = Pdf::loadView('surat-keluar.cetak', [
            'surat' => $surat_keluar,
            'draf' => $surat_keluar->drafKonten,
            'instansi' => $instansi,
            'logoPath' => ($logoPath && file_exists($logoPath)) ? $logoPath : null,
            'tanggalSurat' => $this->formatTanggalIndonesia($surat_keluar->tanggal_surat),
        ])->setPaper('a4', 'portrait');

        $namaFile = 'Surat-'.str_replace(['/', ' '], '-', $surat_keluar->nomor_surat).'.pdf';

        return $pdf->stream($namaFile);
        // Ganti ke ->download($namaFile) kalau maunya langsung unduh (bukan preview tab baru).
    }

    private function formatTanggalIndonesia($tanggal): ?string
    {
        if (! $tanggal) {
            return null;
        }

        $tanggal = Carbon::parse($tanggal);

        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $tanggal->day.' '.$bulan[(int) $tanggal->month].' '.$tanggal->year;
    }
}
