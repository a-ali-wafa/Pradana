<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKlasifikasiSekunderRequest;
use App\Http\Requests\UpdateKlasifikasiSekunderRequest;
use App\Models\KlasifikasiPrimer;
use App\Models\KlasifikasiSekunder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD Klasifikasi Sekunder.
 *
 * Ditulis ulang 1 Sep 2026 dari spek yang sudah cross-checked 26 Agu 2026 terhadap
 * KlasifikasiSekunder.php asli (lihat AGENTS_HISTORY.md 12.11):
 * - $fillable: ['klasifikasi_primer_id', 'kode', 'nama']
 * - Relasi: primer() -> belongsTo(KlasifikasiPrimer::class), tersier() -> hasMany(KlasifikasiTersier::class)
 *   -- BUKAN klasifikasiPrimer()/klasifikasiTersier(), model asli pakai nama pendek tanpa prefix.
 * - unique komposit per parent: (klasifikasi_primer_id, kode).
 *
 * Route `show` tidak didaftarkan; admin-only mutasi (B3 [DEFAULT]) ditangani oleh
 * middleware('admin') di routes/web.php (retrofit B1, 1 Sep 2026);
 * FK restrict ditangkap jadi pesan ramah — pola sama seperti KlasifikasiPrimerController.
 */
class KlasifikasiSekunderController extends Controller
{
    public function index(): View
    {
        $klasifikasiSekunder = KlasifikasiSekunder::with('primer')
            ->withCount('tersier')
            ->orderBy('kode')
            ->paginate(20);

        return view('klasifikasi-sekunder.index', compact('klasifikasiSekunder'));
    }

    public function create(): View
    {
        $klasifikasiPrimer = KlasifikasiPrimer::orderBy('kode')->get();

        return view('klasifikasi-sekunder.create', compact('klasifikasiPrimer'));
    }

    public function store(StoreKlasifikasiSekunderRequest $request): RedirectResponse
    {
        KlasifikasiSekunder::create($request->validated());

        return redirect()
            ->route('klasifikasi-sekunder.index')
            ->with('success', 'Klasifikasi sekunder berhasil ditambahkan.');
    }

    public function edit(KlasifikasiSekunder $klasifikasi_sekunder): View
    {
        $klasifikasiPrimer = KlasifikasiPrimer::orderBy('kode')->get();

        return view('klasifikasi-sekunder.edit', [
            'klasifikasiSekunder' => $klasifikasi_sekunder,
            'klasifikasiPrimer' => $klasifikasiPrimer,
        ]);
    }

    public function update(UpdateKlasifikasiSekunderRequest $request, KlasifikasiSekunder $klasifikasi_sekunder): RedirectResponse
    {
        $klasifikasi_sekunder->update($request->validated());

        return redirect()
            ->route('klasifikasi-sekunder.index')
            ->with('success', 'Klasifikasi sekunder berhasil diperbarui.');
    }

    public function destroy(KlasifikasiSekunder $klasifikasi_sekunder): RedirectResponse
    {
        try {
            $klasifikasi_sekunder->delete();
        } catch (QueryException $e) {
            return back()->with(
                'error',
                'Klasifikasi sekunder tidak bisa dihapus karena masih dipakai di klasifikasi tersier/surat masuk/keluar.'
            );
        }

        return redirect()
            ->route('klasifikasi-sekunder.index')
            ->with('success', 'Klasifikasi sekunder berhasil dihapus.');
    }
}
