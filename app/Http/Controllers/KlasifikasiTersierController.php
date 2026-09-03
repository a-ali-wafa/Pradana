<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKlasifikasiTersierRequest;
use App\Http\Requests\UpdateKlasifikasiTersierRequest;
use App\Models\KlasifikasiSekunder;
use App\Models\KlasifikasiTersier;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD Klasifikasi Tersier.
 *
 * Ditulis ulang 1 Sep 2026 dari spek yang sudah cross-checked 26 Agu 2026 terhadap
 * KlasifikasiTersier.php asli (lihat AGENTS_HISTORY.md 12.11):
 * - $fillable: ['klasifikasi_sekunder_id', 'kode', 'nama']
 * - Relasi: sekunder() -> belongsTo(KlasifikasiSekunder::class) -- BUKAN klasifikasiSekunder().
 * - unique komposit per parent: (klasifikasi_sekunder_id, kode).
 *
 * Route `show` tidak didaftarkan; admin-only mutasi (B3 [DEFAULT]) ditangani oleh
 * middleware('admin') di routes/web.php (retrofit B1, 1 Sep 2026);
 * FK restrict ditangkap jadi pesan ramah — pola sama seperti 2 controller Klasifikasi lain.
 */
class KlasifikasiTersierController extends Controller
{
    public function index(): View
    {
        $klasifikasiTersier = KlasifikasiTersier::with('sekunder.primer')
            ->orderBy('kode')
            ->paginate(20);

        return view('klasifikasi-tersier.index', compact('klasifikasiTersier'));
    }

    public function create(): View
    {
        $klasifikasiSekunder = KlasifikasiSekunder::with('primer')->orderBy('kode')->get();

        return view('klasifikasi-tersier.create', compact('klasifikasiSekunder'));
    }

    public function store(StoreKlasifikasiTersierRequest $request): RedirectResponse
    {
        KlasifikasiTersier::create($request->validated());

        return redirect()
            ->route('klasifikasi-tersier.index')
            ->with('success', 'Klasifikasi tersier berhasil ditambahkan.');
    }

    public function edit(KlasifikasiTersier $klasifikasi_tersier): View
    {
        $klasifikasiSekunder = KlasifikasiSekunder::with('primer')->orderBy('kode')->get();

        return view('klasifikasi-tersier.edit', [
            'klasifikasiTersier' => $klasifikasi_tersier,
            'klasifikasiSekunder' => $klasifikasiSekunder,
        ]);
    }

    public function update(UpdateKlasifikasiTersierRequest $request, KlasifikasiTersier $klasifikasi_tersier): RedirectResponse
    {
        $klasifikasi_tersier->update($request->validated());

        return redirect()
            ->route('klasifikasi-tersier.index')
            ->with('success', 'Klasifikasi tersier berhasil diperbarui.');
    }

    public function destroy(KlasifikasiTersier $klasifikasi_tersier): RedirectResponse
    {
        try {
            $klasifikasi_tersier->delete();
        } catch (QueryException $e) {
            return back()->with(
                'error',
                'Klasifikasi tersier tidak bisa dihapus karena masih dipakai di surat masuk/keluar.'
            );
        }

        return redirect()
            ->route('klasifikasi-tersier.index')
            ->with('success', 'Klasifikasi tersier berhasil dihapus.');
    }
}
