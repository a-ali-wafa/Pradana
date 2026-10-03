<?php

namespace App\Http\Controllers;

use App\Models\KlasifikasiPrimer;
use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan & Buku Agenda (keputusan I1=b + I2=b, 3 Okt 2026).
 *
 * Dua keluaran, satu layar:
 *  - `rekap`  -> CSV (dibuka di Excel/LibreOffice kantor). Bukan .xlsx karena
 *    `maatwebsite/excel` butuh PhpSpreadsheet + extension yang tidak selalu ada
 *    di shared hosting pilihan kantor (K1=a), dan CSV cukup untuk rekap.
 *  - `agenda` -> PDF "Buku Agenda Surat" format cetak (dompdf, sesuai F2=a).
 *
 * Periode selalu rentang tanggal explicit (`dari`/`sampai`) — rekap tanpa batas
 * waktu akan menghasilkan dokumen yang tidak diminta siapa pun.
 */
class LaporanController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        [$dari, $sampai] = $periode = $this->periode($request);

        // Jumlah baris ditampilkan sebelum mengunduh: rekap kosong tanpa
        // penjelasan biasanya disimpulkan sebagai "aplikasinya rusak", bukan
        // "periodenya memang tidak ada surat".
        return view('laporan.index', [
            'periode' => $periode,
            'jumlah' => $this->baris($request, $dari, $sampai)->count(),
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('kode')->get(),
            'filter' => $request->only(['jenis', 'klasifikasi_primer_id', 'status_arsip']),
        ]);
    }

    /**
     * Rekap per periode sebagai CSV.
     */
    public function rekap(Request $request): StreamedResponse
    {
        [$dari, $sampai] = $this->periode($request);
        $baris = $this->baris($request, $dari, $sampai);

        $namaFile = 'rekap-pradana-'.$dari->format('Y-m-d').'-s-d-'.$sampai->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($baris) {
            $keluaran = fopen('php://output', 'w');

            // BOM: tanpa ini Excel Indonesia membuka UTF-8 sebagai ANSI dan
            // karakter non-ASCII (mis. "è", "—") jadi rusak.
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, ['No', 'Jenis', 'Tanggal Surat', 'Tanggal Diterima', 'Nomor Surat', 'Pengirim / Penerima', 'Perihal', 'Sifat', 'Klasifikasi', 'Status Arsip', 'Lampiran', 'Petugas'], ';');

            $nomor = 1;
            foreach ($baris as $item) {
                fputcsv($keluaran, [
                    $nomor++,
                    'Surat '.($item['jenis'] === 'masuk' ? 'Masuk' : 'Keluar'),
                    $item['tanggal_surat'],
                    $item['tanggal_diterima'] ?? '-',
                    $item['nomor_surat'],
                    $item['lawan'],
                    $item['perihal'],
                    $item['sifat'],
                    $item['klasifikasi'],
                    $item['status_arsip'],
                    $item['jumlah_lampiran'],
                    $item['petugas'],
                ], ';');
            }

            fclose($keluaran);
        }, $namaFile, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Buku Agenda Surat (PDF, A4 landscape) — dokumen cetak manual kantor.
     */
    public function agenda(Request $request)
    {
        [$dari, $sampai] = $this->periode($request);

        $pdf = Pdf::loadView('laporan.agenda', [
            'items' => $this->baris($request, $dari, $sampai),
            'instansi' => PengaturanInstansi::first(),
            'dari' => $dari->translatedFormat('d F Y'),
            'sampai' => $sampai->translatedFormat('d F Y'),
            'jenis' => $this->jenis($request),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('buku-agenda-'.$dari->format('Y-m-d').'-s-d-'.$sampai->format('Y-m-d').'.pdf');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periode(Request $request): array
    {
        $dari = $request->filled('dari')
            ? Carbon::parse($request->input('dari'))
            : now()->startOfMonth();

        $sampai = $request->filled('sampai')
            ? Carbon::parse($request->input('sampai'))
            : now()->endOfMonth();

        // Periode terbalik menghasilkan dokumen kosong tanpa penjelasan —
        // dibalik saja, itu yang dimaksud pengguna.
        return $dari->lte($sampai) ? [$dari, $sampai] : [$sampai, $dari];
    }

    private function jenis(Request $request): string
    {
        return in_array($request->query('jenis'), ['masuk', 'keluar'], true) ? $request->query('jenis') : 'semua';
    }

    /**
     * Satu bentuk data untuk CSV dan PDF, jadi kedua keluaran tidak bisa
     * menyimpulkan hal berbeda dari database yang sama.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function baris(Request $request, $dari, $sampai)
    {
        $jenis = $this->jenis($request);
        $klasifikasi = $request->query('klasifikasi_primer_id');
        $statusArsip = $request->query('status_arsip');

        $susun = function (string $model, string $jenisSurat, string $kolomLawan) use ($dari, $sampai, $klasifikasi, $statusArsip) {
            /** @var Builder $q */
            $q = $model::query()
                ->with(['primer', 'petugas'])
                ->withCount('lampiran')
                ->whereBetween('tanggal_surat', [$dari->toDateString(), $sampai->toDateString()])
                ->when($klasifikasi, fn ($b) => $b->where('klasifikasi_primer_id', (int) $klasifikasi))
                ->when($statusArsip, fn ($b) => $b->where('status_arsip', $statusArsip));

            return $q->orderBy('tanggal_surat')->orderBy('id')->get()
                ->map(fn ($s) => [
                    'jenis' => $jenisSurat,
                    'tanggal_surat' => $s->tanggal_surat?->format('Y-m-d'),
                    'tanggal_diterima' => $jenisSurat === 'masuk' ? $s->tanggal_diterima?->format('Y-m-d') : null,
                    'nomor_surat' => $s->nomor_surat,
                    'lawan' => $s->{$kolomLawan},
                    'perihal' => $s->perihal,
                    'sifat' => $s->sifat,
                    'klasifikasi' => $s->primer ? $s->primer->kode.' '.$s->primer->nama : '-',
                    'status_arsip' => $s->status_arsip,
                    'jumlah_lampiran' => $s->lampiran_count,
                    'petugas' => $s->petugas?->nama_lengkap ?? '-',
                ]);
        };

        $hasil = collect();

        if ($jenis !== 'keluar') {
            $hasil = $hasil->concat($susun(SuratMasuk::class, 'masuk', 'pengirim'));
        }

        if ($jenis !== 'masuk') {
            $hasil = $hasil->concat($susun(SuratKeluar::class, 'keluar', 'penerima'));
        }

        return $hasil->sortBy([['tanggal_surat', 'asc'], ['jenis', 'asc']])->values();
    }
}
