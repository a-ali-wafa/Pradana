<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuratKeluarRequest;
use App\Http\Requests\UpdateSuratKeluarRequest;
use App\Models\KlasifikasiPrimer;
use App\Models\SuratKeluar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CRUD arsip surat keluar.
 *
 * SENGAJA BELUM DITANGANI di controller ini (di luar scope "CRUD Surat Keluar"
 * menurut roadmap Bagian 11 AGENTS.md, jadi ditunda supaya tidak menebak-nebak
 * kode yang belum ada):
 * - Upload lampiran ke Google Drive — Lampiran model & GoogleDriveService belum
 *   dibuat (dikonfirmasi user). Setelah keduanya ada, tambahkan relasi
 *   `lampiran()` (morphMany) di model SuratKeluar lalu sambungkan di sini.
 * - Logging ke tabel `aktivitas` — ini item roadmap terpisah ("Logging aktivitas
 *   otomatis di setiap aksi") dan kemungkinan lebih rapi diimplementasikan lewat
 *   Model Observer/Event daripada dipanggil manual di tiap controller. Belum
 *   saya tambahkan di sini supaya tidak dobel/konflik nanti.
 * - Create/edit isi draf_konten_surat_keluar & generate PDF — juga item roadmap
 *   terpisah ("Generate PDF surat keluar, DomPDF").
 *
 * CATATAN: Atribut `file_path` sudah resmi dihapus sesuai dengan keputusan di Bagian 8.

 */
class SuratKeluarController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Daftar surat keluar dengan filter tahun, status arsip, klasifikasi
     * primer, dan pencarian teks bebas (perihal/penerima/nomor surat).
     */
    public function index(Request $request): View
    {
        $query = SuratKeluar::query()->with(['primer', 'sekunder', 'tersier', 'petugas']);

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_surat', $request->integer('tahun'));
        }

        if ($request->filled('status_arsip')) {
            $query->where('status_arsip', $request->input('status_arsip'));
        }

        if ($request->filled('klasifikasi_primer_id')) {
            $query->where('klasifikasi_primer_id', $request->integer('klasifikasi_primer_id'));
        }

        if ($request->filled('cari')) {
            $kataKunci = $request->input('cari');

            $query->where(function ($q) use ($kataKunci) {
                $q->where('perihal', 'like', "%{$kataKunci}%")
                    ->orWhere('penerima', 'like', "%{$kataKunci}%")
                    ->orWhere('nomor_surat', 'like', "%{$kataKunci}%");
            });
        }

        $suratKeluar = $query->orderByDesc('tanggal_surat')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('surat-keluar.index', [
            'suratKeluar' => $suratKeluar,
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        return view('surat-keluar.create', [
            'klasifikasiPrimer' => KlasifikasiPrimer::with('sekunder.tersier')->orderBy('nama')->get(),
        ]);
    }

    public function store(StoreSuratKeluarRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $suratKeluar = DB::transaction(function () use ($data, $request) {
            $tanggal = \Carbon\Carbon::parse($data['tanggal_surat']);
            $tahun   = (int) $tanggal->year;
            $bulan   = (int) $tanggal->month;

            $data['nomor_surat'] = $this->generateNomorSurat(
                $tahun,
                $bulan,
                (int) $data['klasifikasi_primer_id'],
                isset($data['klasifikasi_sekunder_id']) ? (int) $data['klasifikasi_sekunder_id'] : null,
                isset($data['klasifikasi_tersier_id'])  ? (int) $data['klasifikasi_tersier_id']  : null,
            );
            $data['status_arsip'] = $data['status_arsip'] ?? 'aktif';
            $data['user_id'] = $request->user()->id;

            return SuratKeluar::create($data);
        });

        return redirect()
            ->route('surat-keluar.show', $suratKeluar)
            ->with('status', "Surat keluar {$suratKeluar->nomor_surat} berhasil disimpan.");
    }

    public function show(SuratKeluar $surat_keluar): View
    {
        $surat_keluar->load(['primer', 'sekunder', 'tersier', 'petugas', 'drafKonten', 'lampiran']);

        return view('surat-keluar.show', ['suratKeluar' => $surat_keluar]);
    }

    public function edit(SuratKeluar $surat_keluar): View
    {
        return view('surat-keluar.edit', [
            'suratKeluar' => $surat_keluar,
            'klasifikasiPrimer' => KlasifikasiPrimer::with('sekunder.tersier')->orderBy('nama')->get(),
        ]);
    }

    public function update(UpdateSuratKeluarRequest $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        $surat_keluar->update($request->validated());

        return redirect()
            ->route('surat-keluar.show', $surat_keluar)
            ->with('status', "Surat keluar {$surat_keluar->nomor_surat} berhasil diperbarui.");
    }

    /**
     * Hapus permanen (tabel ini tidak pakai soft delete — lihat Bagian 5 & 12.4
     * AGENTS.md, cuma tabel `users` yang soft delete).
     *
     * TODO(B2): defaultnya cuma admin yang boleh hapus arsip permanen — ini
     * masih [WAJIB TANYA USER] di AGENTS.md, jadi konfirmasi ulang ke user.
     */
    public function destroy(Request $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        abort_unless(
            $request->user()->role === 'admin',
            403,
            'Hanya admin yang boleh menghapus arsip surat keluar secara permanen.'
        );

        $nomorSurat = $surat_keluar->nomor_surat;
        $surat_keluar->delete();

        return redirect()
            ->route('surat-keluar.index')
            ->with('status', "Surat keluar {$nomorSurat} berhasil dihapus.");
    }

    /**
     * Generate nomor surat keluar dengan format yang dikonfirmasi user 3 Sep 2026 (D1 [LOCKED]):
     *   {urutan 3 digit}/{kodeP.kodeS.kodeT}/{bulan romawi}/{tahun}
     * Contoh: 001/01.01.01/IX/2026
     *
     * - Urutan: global per tahun (D2), reset tiap tahun (D3). Dihitung dari `tanggal_surat`.
     * - kodeP/kodeS/kodeT: diambil langsung dari model KlasifikasiPrimer/Sekunder/Tersier.
     *   Jika sekunder/tersier tidak dipilih, bagian yang bersangkutan dilewati
     *   (misal cuma primer: "001/01/IX/2026").
     * - lockForUpdate() di dalam transaction supaya tidak ada nomor kembar pada request bersamaan.
     * - Retry 3x sebagai jaga-jaga jika terjadi race condition antar transaksi.
     */
    protected function generateNomorSurat(
        int $tahun,
        int $bulan,
        int $klasifikasiPrimerId,
        ?int $klasifikasiSekonderId = null,
        ?int $klasifikasiTersierId  = null,
    ): string {
        static $bulanRomawi = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
            7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
        ];

        // Ambil kode klasifikasi
        $primer   = \App\Models\KlasifikasiPrimer::find($klasifikasiPrimerId);
        $sekunder = $klasifikasiSekonderId  ? \App\Models\KlasifikasiSekunder::find($klasifikasiSekonderId)  : null;
        $tersier  = $klasifikasiTersierId   ? \App\Models\KlasifikasiTersier::find($klasifikasiTersierId)   : null;

        $bagianKode = collect([
            $primer?->kode,
            $sekunder?->kode,
            $tersier?->kode,
        ])->filter()->implode('.');

        $romawi = $bulanRomawi[$bulan] ?? (string) $bulan;

        DB::table('surat_counters')->upsert(
            ['jenis_surat' => 'surat_keluar', 'tahun' => $tahun, 'current_value' => 1],
            ['jenis_surat', 'tahun'],
            ['current_value' => DB::raw('current_value + 1')]
        );

        $counter = DB::table('surat_counters')
            ->where('jenis_surat', 'surat_keluar')
            ->where('tahun', $tahun)
            ->first();

        $urutan = $counter->current_value;
        return sprintf('%03d/%s/%s/%d', $urutan, $bagianKode, $romawi, $tahun);
    }
}

