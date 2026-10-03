<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuratKeluarRequest;
use App\Http\Requests\UpdateSuratKeluarRequest;
use App\Models\KlasifikasiPrimer;
use App\Models\SuratKeluar;
use App\Services\NomorSuratKeluarGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD arsip surat keluar.
 *
 * Penomoran delegasi ke NomorSuratKeluarGenerator (WAJIB di dalam transaksi —
 * lihat komentar kelas itu). Cetak PDF ada di CetakSuratKeluarController,
 * isi kontennya di DrafKontenSuratKeluarController, lampiran di
 * LampiranController; logging otomatis lewat SuratKeluarObserver.
 */
class SuratKeluarController extends Controller
{
    public function __construct(private readonly NomorSuratKeluarGenerator $nomorSurat)
    {
        $this->middleware('auth');
    }

    /**
     * Daftar surat keluar dengan filter tahun, status arsip, klasifikasi
     * primer, dan pencarian teks bebas (perihal/penerima/nomor surat).
     * `?sampah=1` (admin saja) menampilkan surat yang dihapus lunak.
     */
    public function index(Request $request): View
    {
        $melihatSampah = $request->query('sampah') === '1';

        abort_unless(! $melihatSampah || $request->user()->isAdmin(), 403);

        $query = SuratKeluar::query()->with(['primer', 'sekunder', 'tersier', 'petugas']);

        if ($melihatSampah) {
            $query->onlyTrashed();
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_surat', $request->integer('tahun'));
        }

        if ($request->filled('status_arsip')) {
            $query->where('status_arsip', $request->input('status_arsip'));
        }

        if ($request->filled('klasifikasi_primer_id')) {
            $query->where('klasifikasi_primer_id', $request->integer('klasifikasi_primer_id'));
        }

        if ($request->filled('sifat')) {
            $query->where('sifat', $request->input('sifat'));
        }

        if ($request->filled('cari')) {
            $kataKunci = $request->input('cari');

            $query->where(function ($q) use ($kataKunci) {
                $q->where('perihal', 'like', "%{$kataKunci}%")
                    ->orWhere('penerima', 'like', "%{$kataKunci}%")
                    ->orWhere('nomor_surat', 'like', "%{$kataKunci}%");
            });
        }

        $usang = $request->query('usang') === '1';

        // L-04: hanya menampilkan daftar arsip lewat retensi 5 tahun; keputusan
        // menonaktifkan tetap milik user (umur dihitung dari tanggal_surat, L-21).
        if ($usang) {
            $query->where('tanggal_surat', '<', now()->subYears(5));
        }

        $suratKeluar = $query->orderByDesc('tanggal_surat')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('surat-keluar.index', [
            'suratKeluar' => $suratKeluar,
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('kode')->get(),
            'melihatSampah' => $melihatSampah,
            'usang' => $usang,
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
        $suratKeluar = $this->simpanDenganNomor($request->validated(), $request->user()->id);

        return redirect()
            ->route('surat-keluar.show', $suratKeluar)
            ->with('status', "Surat keluar {$suratKeluar->nomor_surat} berhasil disimpan.");
    }

    /**
     * Simpan surat keluar dengan nomor yang baru di-generate, dicoba ulang
     * maksimal 3 kali kalau ternyata menabrak unique constraint `nomor_surat`
     * (jaring pengaman di atas lock counter, bukan pengganti lock).
     */
    private function simpanDenganNomor(array $data, int $userId, int $sisaPercobaan = 3): SuratKeluar
    {
        try {
            return DB::transaction(function () use ($data, $userId) {
                $data['nomor_surat'] = $this->nomorSurat->generate(
                    $data['tanggal_surat'],
                    (int) $data['klasifikasi_primer_id'],
                    isset($data['klasifikasi_sekunder_id']) ? (int) $data['klasifikasi_sekunder_id'] : null,
                    isset($data['klasifikasi_tersier_id']) ? (int) $data['klasifikasi_tersier_id'] : null,
                );
                $data['status_arsip'] = $data['status_arsip'] ?? 'aktif';
                $data['user_id'] = $userId;

                return SuratKeluar::create($data);
            });
        } catch (QueryException $e) {
            if ($sisaPercobaan <= 1 || ! $this->nomorSuratTabrakan($e)) {
                throw $e;
            }

            return $this->simpanDenganNomor($data, $userId, $sisaPercobaan - 1);
        }
    }

    /**
     * 1062 = duplicate key (MySQL/MariaDB); SQLite memakai teks error sendiri.
     */
    private function nomorSuratTabrakan(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            || str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'surat_keluar_nomor_surat_unique');
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
     * Hapus lunak (L-05): tabel ini sekarang punya soft delete, jadi surat tidak
     * pernah hilang sungguhan dari layar ini — lampiran & file fisiknya tetap
     * utuh dan bisa dipulihkan admin. Pemusnahan resmi lewat modul Pemusnahan.
     */
    public function destroy(Request $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        abort_unless(
            $request->user()->isAdmin(),
            403,
            'Hanya admin yang boleh menghapus arsip surat keluar.'
        );

        $nomorSurat = $surat_keluar->nomor_surat;
        $surat_keluar->delete();

        return redirect()
            ->route('surat-keluar.index')
            ->with('status', "Surat keluar {$nomorSurat} dipindahkan ke tempat sampah (masih bisa dipulihkan).");
    }

    /**
     * L-19 / E8+E9: "Nyahkan" = status_arsip inaktif. Semua user login boleh,
     * perubahannya tercatat lewat observer.
     */
    public function updateStatusArsip(Request $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        $data = $request->validate([
            'status_arsip' => ['required', Rule::in(['aktif', 'inaktif'])],
        ]);

        $surat_keluar->update($data);

        return back()->with(
            'status',
            $data['status_arsip'] === 'inaktif'
                ? 'Surat keluar dinonaktifkan sebagai arsip aktif.'
                : 'Surat keluar diaktifkan kembali.'
        );
    }

    public function restore(Request $request, int $suratKeluar): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang bisa memulihkan arsip.');

        $surat = SuratKeluar::withTrashed()->findOrFail($suratKeluar);
        $surat->restore();

        return redirect()
            ->route('surat-keluar.show', $surat)
            ->with('status', 'Surat keluar dipulihkan dari tempat sampah.');
    }
}

