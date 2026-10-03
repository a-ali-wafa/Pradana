<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuratMasukRequest;
use App\Http\Requests\UpdateSuratMasukRequest;
use App\Models\KlasifikasiPrimer;
use App\Models\SuratMasuk;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD arsip surat masuk.
 *
 * Penghapusan = soft delete (L-05): arsip tidak bisa dimusnahkan dari layar
 * ini; pemusnahan resmi lewat modul Pemusnahan Arsip + Berita Acara (L-06).
 * Penonaktifan arsip lewat tombol "Nyahkan" / updateStatusArsip (L-19).
 */
class SuratMasukController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $melihatSampah = $request->query('sampah') === '1';

        // Hanya admin yang boleh membuka daftar arsip yang sudah dihapus lunak (L-05).
        abort_unless(! $melihatSampah || $request->user()->isAdmin(), 403);

        $query = SuratMasuk::query()->with(['primer', 'sekunder', 'tersier', 'petugas']);

        if ($melihatSampah) {
            $query->onlyTrashed();
        }

        if ($request->filled('cari')) {
            $kw = $request->input('cari');
            $query->where(function ($q) use ($kw) {
                $q->where('perihal', 'like', "%{$kw}%")
                    ->orWhere('nomor_surat', 'like', "%{$kw}%")
                    ->orWhere('pengirim', 'like', "%{$kw}%");
            });
        }

        if ($request->filled('sifat')) {
            $query->where('sifat', $request->input('sifat'));
        }

        if ($request->filled('klasifikasi_primer_id')) {
            $query->where('klasifikasi_primer_id', $request->integer('klasifikasi_primer_id'));
        }

        if ($request->filled('status_arsip')) {
            $query->where('status_arsip', $request->input('status_arsip'));
        }

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_surat', $request->integer('tahun'));
        }

        $usang = $request->query('usang') === '1';

        // L-04: sistem hanya MENYEDIAKAN DAFTAR arsip yang lewat retensi 5 tahun
        // (dihitung dari tanggal_surat — L-21); keputusan menonaktifkan tetap
        // milik user, makanya ini filter manual, bukan layar terpisah yang memaksa.
        if ($usang) {
            $query->where('tanggal_surat', '<', now()->subYears(5));
        }

        $suratMasuk = $query->orderByDesc('tanggal_diterima')->orderByDesc('id')->paginate(20)->withQueryString();
        $klasifikasiPrimer = KlasifikasiPrimer::orderBy('kode')->get();

        return view('surat-masuk.index', compact('suratMasuk', 'klasifikasiPrimer', 'melihatSampah', 'usang'));
    }

    public function create(): View
    {
        $klasifikasiPrimer = KlasifikasiPrimer::with('sekunder.tersier')->orderBy('kode')->get();

        return view('surat-masuk.create', compact('klasifikasiPrimer'));
    }

    public function store(StoreSuratMasukRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        // Surat baru selalu masuk sebagai arsip aktif. Toggle ke "inaktif"
        // adalah bagian dari fitur retensi terpisah yang belum dikerjakan
        // (blocked by E1) — lihat AGENTS.md Bagian 10 & 11.
        $data['status_arsip'] = 'aktif';

        SuratMasuk::create($data);

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', 'Surat masuk berhasil ditambahkan.');
    }

    public function show(SuratMasuk $surat_masuk): View
    {
        $surat_masuk->load(['primer', 'sekunder', 'tersier', 'petugas', 'lampiran']);

        return view('surat-masuk.show', compact('surat_masuk'));
    }

    public function edit(SuratMasuk $surat_masuk): View
    {
        $klasifikasiPrimer = KlasifikasiPrimer::with('sekunder.tersier')->orderBy('kode')->get();

        return view('surat-masuk.edit', [
            'suratMasuk' => $surat_masuk,
            'klasifikasiPrimer' => $klasifikasiPrimer,
        ]);
    }

    public function update(UpdateSuratMasukRequest $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        $surat_masuk->update($request->validated());

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', 'Surat masuk berhasil diperbarui.');
    }

    /**
     * L-05: hapus = soft delete, dan hanya admin (L-07). Baris + lampiran +
     * file fisiknya tetap utuh supaya bisa dipulihkan lewat `restore()`.
     */
    public function destroy(Request $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        abort_unless(
            $request->user()->isAdmin(),
            403,
            'Hanya admin yang boleh menghapus arsip surat.'
        );

        try {
            // L-05: ini soft delete — baris, lampiran, dan file fisiknya tetap
            // ada dan bisa dipulihkan admin. Pemusnahan sungguhan hanya lewat
            // modul Pemusnahan Arsip (dengan Berita Acara).
            $surat_masuk->delete();
        } catch (QueryException $e) {
            return back()->with(
                'error',
                'Surat masuk tidak bisa dihapus karena masih direferensikan data lain.'
            );
        }

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', 'Surat masuk dipindahkan ke tempat sampah (masih bisa dipulihkan).');
    }

    /**
     * L-19 / E8+E9: men-nyahkan arsip = status_arsip jadi "inaktif".
     * Tersedia untuk semua user login (arsip kantor, tanpa kepemilikan),
     * dan tiap perubahan tercatat otomatis lewat observer.
     */
    public function updateStatusArsip(Request $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        $data = $request->validate([
            'status_arsip' => ['required', Rule::in(['aktif', 'inaktif'])],
        ]);

        $surat_masuk->update($data);

        return back()->with(
            'status',
            $data['status_arsip'] === 'inaktif'
                ? 'Surat masuk dinonaktifkan sebagai arsip aktif.'
                : 'Surat masuk diaktifkan kembali.'
        );
    }

    /**
     * Pulihkan surat dari tempat sampah (admin saja). Paramenya bukan model
     * binding karena surat yang sudah dihapus lunak tidak ikut ditemukan oleh
     * route binding default.
     */
    public function restore(Request $request, int $suratMasuk): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang bisa memulihkan arsip.');

        $surat = SuratMasuk::withTrashed()->findOrFail($suratMasuk);
        $surat->restore();

        return redirect()
            ->route('surat-masuk.show', $surat)
            ->with('status', 'Surat masuk dipulihkan dari tempat sampah.');
    }
}
