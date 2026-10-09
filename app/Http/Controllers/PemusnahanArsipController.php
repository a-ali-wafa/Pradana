<?php

namespace App\Http\Controllers;

use App\Models\PemusnahanArsip;
use App\Models\PemusnahanArsipItem;
use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Support\Terbilang;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pemusnahan arsip SURAT + Berita Acara (keputusan L-06 / E3).
 *
 * Ini satu-satunya jalur yang boleh membuat berkas arsip hilang sungguhan:
 * staf mengajukan daftar arsip yang sudah lewat retensi DAN sudah dinonaktifkan,
 * admin menyetujui, lalu sistem menghapus permanen (baris + lampiran + file)
 * dan menerbitkan Berita Acara sebagai bukti.
 *
 * Dua syarat itu bukan hiasan: tanpa "harus inaktif dulu" (L-19) satu klik
 * bisa langsung memusnahkan arsip yang masih dipakai; tanpa retensi 5 tahun
 * (L-04) arsip muda bisa hilang.
 */
class PemusnahanArsipController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $status = $request->query('status', 'semua');

        $pemusnahan = PemusnahanArsip::query()
            ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
            ->with(['pengaju', 'pemroses', 'items'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('pemusnahan-arsip.index', [
            'pemusnahan' => $pemusnahan,
            'statusAktif' => $status,
        ]);
    }

    /**
     * Daftar arsip yang boleh diajukan musnah (L-04 + L-19).
     */
    public function create(): View
    {
        return view('pemusnahan-arsip.create', [
            'kandidat' => $this->kandidatPemusnahan(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Form mengirim pilihan sebagai "masuk:12" / "keluar:7" supaya satu
        // array bisa memuat kedua jenis surat.
        $data = $request->validate([
            'arsip' => ['required', 'array', 'min:1'],
            'arsip.*' => ['required', 'string', 'regex:/^(masuk|keluar):\d+$/'],
            'alasan' => ['nullable', 'string', 'max:2000'],
            'tanggal_pelaksanaan' => ['nullable', 'date'],
        ]);

        $kandidat = $this->kandidatPemusnahan();

        // Validasi di server: form bisa berisi arsip yang sudah tidak memenuhi
        // syarat sejak halaman ini dibuka (misal belum 5 tahun / masih aktif).
        $dipilih = collect($data['arsip'])->map(function ($ombol) {
            [$jenis, $id] = explode(':', $ombol);

            return (object) ['jenis' => $jenis, 'id' => (int) $id];
        });

        $tidakLayak = $dipilih->reject(
            fn ($p) => $kandidat->contains(fn ($k) => $k->id === $p->id && $k->jenis === $p->jenis)
        );

        if ($tidakLayak->isNotEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'arsip' => 'Ada arsip yang belum memenuhi syarat dimusnahkan '
                        .'(umur di atas 5 tahun dan sudah dinonaktifkan). Muat ulang daftar lalu pilih lagi.',
                ]);
        }

        $pemusnahan = DB::transaction(function () use ($request, $data, $dipilih) {
            $pemusnahan = PemusnahanArsip::create([
                'alasan' => $data['alasan'] ?? null,
                'status' => 'menunggu',
                'diajukan_oleh' => $request->user()->id,
                'tanggal_pelaksanaan' => $data['tanggal_pelaksanaan'] ?? null,
            ]);

            foreach ($dipilih as $pilihan) {
                $model = $pilihan->jenis === 'masuk' ? SuratMasuk::class : SuratKeluar::class;
                $arsip = $model::findOrFail($pilihan->id);

                PemusnahanArsipItem::create([
                    'pemusnahan_arsip_id' => $pemusnahan->id,
                    'arsipable_type' => $arsip::class,
                    'arsipable_id' => $arsip->id,
                    'nomor_surat_snapshot' => $arsip->nomor_surat,
                    'perihal_snapshot' => $arsip->perihal,
                    'tanggal_surat_snapshot' => $arsip->tanggal_surat,
                    'jumlah_lampiran_snapshot' => $arsip->lampiran()->count(),
                ]);
            }

            return $pemusnahan;
        });

        return redirect()
            ->route('pemusnahan-arsip.show', $pemusnahan)
            ->with('success', 'Pengajuan pemusnahan dikirim, menunggu persetujuan admin.');
    }

    public function show(PemusnahanArsip $pemusnahan_arsip): View
    {
        $pemusnahan_arsip->load(['items', 'pengaju', 'pemroses']);

        return view('pemusnahan-arsip.show', ['pemusnahan' => $pemusnahan_arsip]);
    }

    /**
     * Persetujuan admin = eksekusi pemusnahan permanen (baris + lampiran + file
     * lewat observer SuratMasuk/SuratKeluarObserver saat forceDelete).
     */
    public function setujui(Request $request, PemusnahanArsip $pemusnahan_arsip): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang bisa menyetujui pemusnahan.');
        abort_if($pemusnahan_arsip->sudahDiproses(), 422, 'Pengajuan ini sudah diproses sebelumnya.');

        DB::transaction(function () use ($request, $pemusnahan_arsip) {
            $pemusnahan_arsip->load('items');

            foreach ($pemusnahan_arsip->items as $item) {
                $arsip = $item->arsipable_type::withTrashed()->find($item->arsipable_id);

                if ($arsip) {
                    $arsip->forceDelete();
                }
            }

            $pemusnahan_arsip->update([
                'status' => 'disetujui',
                'diproses_oleh' => $request->user()->id,
                'diproses_pada' => now(),
                'nomor_berita_acara' => $pemusnahan_arsip->nomor_berita_acara ?: $this->buatNomorBeritaAcara($pemusnahan_arsip),
                'tanggal_pelaksanaan' => $pemusnahan_arsip->tanggal_pelaksanaan ?? now()->toDateString(),
            ]);
        });

        return redirect()
            ->route('pemusnahan-arsip.show', $pemusnahan_arsip)
            ->with('success', 'Pemusnahan disetujui. Arsip dan berkasnya sudah dimusnahkan; Berita Acara siap dicetak.');
    }

    public function tolak(Request $request, PemusnahanArsip $pemusnahan_arsip): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang bisa menolak pemusnahan.');
        abort_if($pemusnahan_arsip->sudahDiproses(), 422, 'Pengajuan ini sudah diproses sebelumnya.');

        $data = $request->validate([
            'catatan_admin' => ['nullable', 'string', 'max:2000'],
        ]);

        $pemusnahan_arsip->update([
            'status' => 'ditolak',
            'diproses_oleh' => $request->user()->id,
            'diproses_pada' => now(),
            'catatan_admin' => $data['catatan_admin'] ?? null,
        ]);

        return redirect()
            ->route('pemusnahan-arsip.show', $pemusnahan_arsip)
            ->with('success', 'Pengajuan pemusnahan ditolak.');
    }

    /**
     * Berita Acara (dokumen resmi). Bisa dibuka kapan saja selama pengajuannya
     * sudah disetujui — isinya dibaca dari snapshot, bukan dari arsip yang
     * sudah dimusnahkan.
     */
    public function beritaAcara(PemusnahanArsip $pemusnahan_arsip)
    {
        abort_unless($pemusnahan_arsip->status === 'disetujui', 403, 'Berita Acara hanya tersedia untuk pemusnahan yang sudah disetujui.');

        $pemusnahan_arsip->load(['items', 'pemroses', 'pengaju']);

        $instansi = PengaturanInstansi::first();

        $pelaksanaan = Carbon::parse($pemusnahan_arsip->tanggal_pelaksanaan ?? now());

        $pdf = Pdf::loadView('pemusnahan-arsip.berita-acara', [
            'pemusnahan' => $pemusnahan_arsip,
            'instansi' => $instansi,
            // Logo dibaca dari disk, bukan `public_path('storage/...')` — lihat
            // alasan di PengaturanInstansi::logoPathUntukPdf() (5 Okt 2026).
            'logoPath' => $instansi?->logoPathUntukPdf(),
            'hari' => $pelaksanaan->translatedFormat('l'),
            'tanggalPelaksanaan' => $pelaksanaan->translatedFormat('d F Y'),
            'tanggalHuruf' => Terbilang::kata((int) $pelaksanaan->day),
            'bulanHuruf' => $pelaksanaan->translatedFormat('F'),
            'tahunHuruf' => Terbilang::kata((int) $pelaksanaan->year),
            'tempat' => $instansi?->tempatSurat() ?? '',
        ])->setPaper('a4', 'portrait');

        $namaFile = 'Berita-Acara-'.str_replace(['/', ' '], '-', $pemusnahan_arsip->nomor_berita_acara).'.pdf';

        return $pdf->stream($namaFile);
    }

    /**
     * Arsip yang boleh diajukan: lewat retensi (L-04) DAN sudah dinonaktifkan
     * (L-19), belum dihapus lunak, dan belum masuk pengajuan yang menunggu.
     */
    private function kandidatPemusnahan()
    {
        $batas = now()->subYears(5);

        $terpakai = PemusnahanArsipItem::query()
            ->whereHas('pemusnahan', fn ($q) => $q->where('status', 'menunggu'))
            ->get()
            ->map(fn ($i) => $i->arsipable_type.'#'.$i->arsipable_id)
            ->all();

        $masuk = SuratMasuk::query()
            ->where('tanggal_surat', '<', $batas)
            ->where('status_arsip', 'inaktif')
            ->with('primer')
            ->get()
            ->reject(fn ($s) => in_array(SuratMasuk::class.'#'.$s->id, $terpakai, true))
            ->map(fn ($s) => (object) [
                'id' => $s->id, 'jenis' => 'masuk', 'nomor_surat' => $s->nomor_surat,
                'perihal' => $s->perihal, 'tanggal_surat' => $s->tanggal_surat,
                'pengirim' => $s->pengirim, 'lampiran' => $s->lampiran()->count(),
            ]);

        $keluar = SuratKeluar::query()
            ->where('tanggal_surat', '<', $batas)
            ->where('status_arsip', 'inaktif')
            ->with('primer')
            ->get()
            ->reject(fn ($s) => in_array(SuratKeluar::class.'#'.$s->id, $terpakai, true))
            ->map(fn ($s) => (object) [
                'id' => $s->id, 'jenis' => 'keluar', 'nomor_surat' => $s->nomor_surat,
                'perihal' => $s->perihal, 'tanggal_surat' => $s->tanggal_surat,
                'pengirim' => $s->penerima, 'lampiran' => $s->lampiran()->count(),
            ]);

        return $masuk->concat($keluar)->sortByDesc('tanggal_surat')->values();
    }

    private function buatNomorBeritaAcara(PemusnahanArsip $pemusnahan): string
    {
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $bulan = (int) now()->month;

        return sprintf('BA-%03d/%s/%d', $pemusnahan->id, $romawi[$bulan - 1], now()->year);
    }
}
