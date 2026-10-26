<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MengelolaKlasifikasi;
use App\Http\Requests\StoreKlasifikasiPrimerRequest;
use App\Http\Requests\UpdateKlasifikasiPrimerRequest;
use App\Models\KlasifikasiPrimer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CRUD Klasifikasi Primer.
 *
 * Spek di
 * bawah mengikuti persis skema `klasifikasi_primer` dan `KlasifikasiPrimer` aslinya:
 * - $fillable: ['kode', 'nama']
 * - Relasi: sekunder() -> hasMany(KlasifikasiSekunder::class)
 *
 * - Route `show` sengaja tidak didaftarkan (->except(['show'])).
 * - Admin-only untuk semua mutasi — ditangani oleh middleware('admin')
 * yang didaftarkan di routes/web.php.
 * - Daftar, redirect+flash, dan hapus-FK-restrict pindah ke trait
 * `MengelolaKlasifikasi` supaya tidak bisa lagi melenceng bertiga;
 *   yang tertinggal di sini hanya yang khas primer: jumlah sekunder per kode.
 */
class KlasifikasiPrimerController extends Controller
{
    use MengelolaKlasifikasi;

    public function index(): View
    {
        return $this->daftarKlasifikasi(
            KlasifikasiPrimer::query()->withCount('sekunder'),
            'klasifikasi-primer',
            'klasifikasiPrimer',
        );
    }

    public function create(): View
    {
        return view('klasifikasi-primer.create');
    }

    public function store(StoreKlasifikasiPrimerRequest $request): RedirectResponse
    {
        KlasifikasiPrimer::create($request->validated());

        return $this->selesaiKlasifikasi('klasifikasi-primer', 'Klasifikasi primer berhasil ditambahkan.');
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

        return $this->selesaiKlasifikasi('klasifikasi-primer', 'Klasifikasi primer berhasil diperbarui.');
    }

    public function destroy(KlasifikasiPrimer $klasifikasi_primer): RedirectResponse
    {
        return $this->hapusKlasifikasi(
            $klasifikasi_primer,
            'klasifikasi-primer',
            'Klasifikasi primer',
            'surat masuk/keluar',
        );
    }
}
