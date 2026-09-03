<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKlasifikasiPrimerRequest;
use App\Http\Requests\UpdateKlasifikasiPrimerRequest;
use App\Models\KlasifikasiPrimer;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD Klasifikasi Primer.
 *
 * Ditulis ulang 1 Sep 2026 — versi sebelumnya (26 Agu 2026, lihat AGENTS_HISTORY.md 12.11)
 * ternyata tidak pernah masuk ke project sebenarnya: routes/web.php sudah mendaftarkan
 * class ini, tapi file controllernya sendiri hilang (kasus sama seperti 12.9). Spek di
 * bawah mengikuti persis hasil cross-check 26 Agu 2026 terhadap KlasifikasiPrimer.php asli:
 * - $fillable: ['kode', 'nama']
 * - Relasi: sekunder() -> hasMany(KlasifikasiSekunder::class)
 *
 * - Route `show` sengaja tidak didaftarkan (->except(['show'])), lihat 12.11.
 * - Admin-only untuk semua mutasi (B3 [DEFAULT]) — ditangani oleh middleware('admin')
 *   yang didaftarkan di routes/web.php (retrofit B1, 1 Sep 2026).
 * - FK restrict (surat_masuk/surat_keluar -> klasifikasi_primer_id) ditangkap jadi
 *   pesan ramah, bukan dibiarkan crash 500 (pola sama di Sekunder & Tersier).
 */
class KlasifikasiPrimerController extends Controller
{
    public function index(): View
    {
        $klasifikasiPrimer = KlasifikasiPrimer::withCount('sekunder')
            ->orderBy('kode')
            ->paginate(20);

        return view('klasifikasi-primer.index', compact('klasifikasiPrimer'));
    }

    public function create(): View
    {
        return view('klasifikasi-primer.create');
    }

    public function store(StoreKlasifikasiPrimerRequest $request): RedirectResponse
    {
        KlasifikasiPrimer::create($request->validated());

        return redirect()
            ->route('klasifikasi-primer.index')
            ->with('success', 'Klasifikasi primer berhasil ditambahkan.');
    }

    public function edit(KlasifikasiPrimer $klasifikasi_primer): View
    {
        return view('klasifikasi-primer.edit', [
            'klasifikasiPrimer' => $klasifikasi_primer,
        ]);
    }

    public function update(UpdateKlasifikasiPrimerRequest $request, KlasifikasiPrimer $klasifikasi_primer): RedirectResponse
    {
        $klasifikasi_primer->update($request->validated());

        return redirect()
            ->route('klasifikasi-primer.index')
            ->with('success', 'Klasifikasi primer berhasil diperbarui.');
    }

    public function destroy(KlasifikasiPrimer $klasifikasi_primer): RedirectResponse
    {
        try {
            $klasifikasi_primer->delete();
        } catch (QueryException $e) {
            return back()->with(
                'error',
                'Klasifikasi primer tidak bisa dihapus karena masih dipakai di surat masuk/keluar.'
            );
        }

        return redirect()
            ->route('klasifikasi-primer.index')
            ->with('success', 'Klasifikasi primer berhasil dihapus.');
    }
}
