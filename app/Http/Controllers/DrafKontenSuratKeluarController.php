<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDrafKontenSuratKeluarRequest;
use App\Models\DrafKontenSuratKeluar;
use App\Models\SuratKeluar;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Isi konten surat keluar untuk generator PDF (keputusan L-16).
 *
 * Sebelum controller ini ada, tidak ada satu pun tempat untuk mengisi tabel
 * `draf_konten_surat_keluar`, sedangkan CetakSuratKeluarController menolak
 * mencetak kalau drafnya kosong — fitur cetak PDF (F1, fitur inti versi lama)
 * karena itu tidak bisa dipakai sama sekali.
 *
 * Satu surat = satu draf (relasi 1-1), jadi hanya edit/update, tanpa create/
 * store terpisah: form yang sama dipakai untuk draf baru dan draf lama.
 */
class DrafKontenSuratKeluarController extends Controller
{
    public function edit(SuratKeluar $surat_keluar): View
    {
        $draf = $surat_keluar->drafKonten
            ?? new DrafKontenSuratKeluar(['surat_keluar_id' => $surat_keluar->id]);

        return view('surat-keluar.draf', [
            'suratKeluar' => $surat_keluar,
            'draf' => $draf,
            'jumlahLampiran' => $surat_keluar->lampiran()->count(),
        ]);
    }

    public function update(UpdateDrafKontenSuratKeluarRequest $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        $draf = DrafKontenSuratKeluar::updateOrCreate(
            ['surat_keluar_id' => $surat_keluar->id],
            $request->validated(),
        );

        // "Simpan & pratinjau PDF" dari form; tanpa tanda itu kembali ke form.
        if ($request->input('aksi') === 'simpan_cetak') {
            return redirect()->route('surat-keluar.cetak', $surat_keluar);
        }

        return redirect()
            ->route('surat-keluar.draf.edit', $surat_keluar)
            ->with('success', 'Draf konten surat disimpan.');
    }
}
