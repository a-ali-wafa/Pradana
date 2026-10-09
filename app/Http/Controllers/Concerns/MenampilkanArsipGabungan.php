<?php

namespace App\Http\Controllers\Concerns;

use App\Models\KlasifikasiPrimer;
use App\Services\DaftarArsipGabungan;
use App\Support\FilterArsip;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cabang "Semua arsip" untuk kedua halaman daftar surat.
 *
 * Ditaruh di trait karena logikanya identik dan yang berbeda cuma nama view
 * asalnya: menyalin `if (gabungan)` ke dua controller adalah pola yang sudah
 * berkali-kali jadi sumber perbedaan perilaku di project ini (lihat catatan
 * parsial cascade JS dan keseragaman filter L-14).
 */
trait MenampilkanArsipGabungan
{
    protected function arsipGabungan(Request $request, string $view): View
    {
        return view($view, [
            'arsip' => DaftarArsipGabungan::halaman($request),
            'gabung' => true,
            'melihatSampah' => false,
            'usang' => FilterArsip::usang($request),
            'cari' => FilterArsip::cari($request),
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('kode')->get(),
        ]);
    }
}
