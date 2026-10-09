<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MengelolaKlasifikasi;
use App\Http\Requests\StoreKlasifikasiSekunderRequest;
use App\Http\Requests\UpdateKlasifikasiSekunderRequest;
use App\Models\KlasifikasiPrimer;
use App\Models\KlasifikasiSekunder;
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
 * middleware('admin') di routes/web.php (retrofit B1, 1 Sep 2026). Daftar, redirect,
 * dan hapus-FK-restrict lewat trait `MengelolaKlasifikasi` (9 Okt 2026); dropdown
 * induknya tetap di sini karena hanya tingkat ini yang butuh daftar primer.
 */
class KlasifikasiSekunderController extends Controller
{
    use MengelolaKlasifikasi;

    public function index(): View
    {
        return $this->daftarKlasifikasi(
            KlasifikasiSekunder::query()->with('primer')->withCount('tersier'),
            'klasifikasi-sekunder',
            'klasifikasiSekunder',
        );
    }

    public function create(): View
    {
        return view('klasifikasi-sekunder.create', [
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('kode')->get(),
        ]);
    }

    public function store(StoreKlasifikasiSekunderRequest $request): RedirectResponse
    {
        KlasifikasiSekunder::create($request->validated());

        return $this->selesaiKlasifikasi('klasifikasi-sekunder', 'Klasifikasi sekunder berhasil ditambahkan.');
    }

    public function edit(KlasifikasiSekunder $klasifikasi_sekunder): View
    {
        return view('klasifikasi-sekunder.edit', [
            'klasifikasiSekunder' => $klasifikasi_sekunder,
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('kode')->get(),
        ]);
    }

    public function update(UpdateKlasifikasiSekunderRequest $request, KlasifikasiSekunder $klasifikasi_sekunder): RedirectResponse
    {
        $klasifikasi_sekunder->update($request->validated());

        return $this->selesaiKlasifikasi('klasifikasi-sekunder', 'Klasifikasi sekunder berhasil diperbarui.');
    }

    public function destroy(KlasifikasiSekunder $klasifikasi_sekunder): RedirectResponse
    {
        return $this->hapusKlasifikasi(
            $klasifikasi_sekunder,
            'klasifikasi-sekunder',
            'Klasifikasi sekunder',
            'klasifikasi tersier/surat masuk/keluar',
        );
    }
}
