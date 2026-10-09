<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MengelolaKlasifikasi;
use App\Http\Requests\StoreKlasifikasiTersierRequest;
use App\Http\Requests\UpdateKlasifikasiTersierRequest;
use App\Models\KlasifikasiSekunder;
use App\Models\KlasifikasiTersier;
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
 * middleware('admin') di routes/web.php (retrofit B1, 1 Sep 2026). Daftar, redirect,
 * dan hapus-FK-restrict lewat trait `MengelolaKlasifikasi` (9 Okt 2026).
 */
class KlasifikasiTersierController extends Controller
{
    use MengelolaKlasifikasi;

    public function index(): View
    {
        return $this->daftarKlasifikasi(
            KlasifikasiTersier::query()->with('sekunder.primer'),
            'klasifikasi-tersier',
            'klasifikasiTersier',
        );
    }

    public function create(): View
    {
        return view('klasifikasi-tersier.create', [
            'klasifikasiSekunder' => KlasifikasiSekunder::with('primer')->orderBy('kode')->get(),
        ]);
    }

    public function store(StoreKlasifikasiTersierRequest $request): RedirectResponse
    {
        KlasifikasiTersier::create($request->validated());

        return $this->selesaiKlasifikasi('klasifikasi-tersier', 'Klasifikasi tersier berhasil ditambahkan.');
    }

    public function edit(KlasifikasiTersier $klasifikasi_tersier): View
    {
        return view('klasifikasi-tersier.edit', [
            'klasifikasiTersier' => $klasifikasi_tersier,
            'klasifikasiSekunder' => KlasifikasiSekunder::with('primer')->orderBy('kode')->get(),
        ]);
    }

    public function update(UpdateKlasifikasiTersierRequest $request, KlasifikasiTersier $klasifikasi_tersier): RedirectResponse
    {
        $klasifikasi_tersier->update($request->validated());

        return $this->selesaiKlasifikasi('klasifikasi-tersier', 'Klasifikasi tersier berhasil diperbarui.');
    }

    public function destroy(KlasifikasiTersier $klasifikasi_tersier): RedirectResponse
    {
        return $this->hapusKlasifikasi(
            $klasifikasi_tersier,
            'klasifikasi-tersier',
            'Klasifikasi tersier',
            'surat masuk/keluar',
        );
    }
}
