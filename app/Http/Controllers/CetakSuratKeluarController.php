<?php

namespace App\Http\Controllers;

use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Support\Terbilang;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Cetak PDF surat keluar (DomPDF, F2=a). Controller ini berdiri sendiri karena
 * mencetak bukan aksi destruktif — middleware `auth` polos, semua role boleh.
 *
 * Catatan yang berubah 5 Okt 2026, saat kop surat dirapikan ke standar tata
 * naskah dinas desa (Permendagri 1/2023):
 * - Logo dibaca dari DISK, bukan `public_path('storage/...')`. Path lama butuh
 *   symlink `storage:link`; tanpa itu PDF tercetak tanpa kop dan tidak ada error
 *   apa pun. `logoPathUntukPdf()` sudah mengembalikan null kalau berkasnya tidak
 *   ada, jadi template tinggal menyesuaikan diri.
 * - Baris "Lampiran" tetap dihitung dari file yang benar-benar ada (P6=a), tapi
 *   formatnya ikut kebiasaan kantor: `0 (nol)` / `2 (dua) berkas`.
 * - Format nomor surat DITAMPILKAN apa adanya dari `nomor_surat` (tidak dihasilkan
 *   ulang di sini) — D1 [LOCKED] hanya soal *pembuatan* nomor.
 * - Multi-template (F3=b) masih menunggu kop resmi dari desa; sekarang satu
 *   template umum yang sudah mengikuti struktur baku.
 */
class CetakSuratKeluarController extends Controller
{
    public function cetak(SuratKeluar $surat_keluar): RedirectResponse|Response
    {
        $surat_keluar->load(['primer', 'sekunder', 'tersier', 'petugas', 'drafKonten', 'lampiran']);

        if (! $surat_keluar->drafKonten) {
            return back()->with(
                'error',
                'Draf konten surat ini belum diisi. Buka menu "Isi Draf & Cetak" pada halaman surat untuk mengisinya.'
            );
        }

        $instansi = PengaturanInstansi::first();
        $draf = $surat_keluar->drafKonten;
        $jumlahLampiran = $surat_keluar->lampiran()->count();

        $pdf = Pdf::loadView('surat-keluar.cetak', [
            'surat' => $surat_keluar,
            'draf' => $draf,
            'instansi' => $instansi,
            'logoPath' => $instansi?->logoPathUntukPdf(),
            'jumlahLampiran' => $jumlahLampiran,
            'notasiLampiran' => $this->notasiLampiran($jumlahLampiran),
            'tanggalSurat' => $this->formatTanggalIndonesia($surat_keluar->tanggal_surat),
            'kodeKlasifikasi' => trim(collect([
                $surat_keluar->primer?->kode,
                $surat_keluar->sekunder?->kode,
                $surat_keluar->tersier?->kode,
            ])->filter()->implode('/')),
        ])->setPaper('a4', 'portrait');

        $namaFile = 'Surat-'.str_replace(['/', ' '], '-', $surat_keluar->nomor_surat).'.pdf';

        return $pdf->stream($namaFile);
    }

    /**
     * P6=a: notasi dihitung dari berkas yang ada, bukan diketik tangan. Kalau
     * kosong tetap ditulis `0 (nol)` — baris Lampiran tidak boleh dihilangkan,
     * karena surat dinas tanpa baris itu justru dianggap tidak lengkap.
     */
    private function notasiLampiran(int $jumlah): string
    {
        return Terbilang::denganAngka($jumlah).' berkas';
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
