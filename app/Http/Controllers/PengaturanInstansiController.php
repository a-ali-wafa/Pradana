<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePengaturanInstansiRequest;
use App\Models\PengaturanInstansi;
use Illuminate\Support\Facades\Storage;

/**
 * Pengaturan Instansi — kop surat, alamat, kontak, dan logo. Kolomnya mengikuti
 * model `PengaturanInstansi`; satu baris untuk satu kantor.
 *
 * Desain yang disengaja:
 * - Tabel single-row (aplikasi melayani satu kantor) → HANYA edit()+update(),
 *   tidak ada index/show/create/store/destroy. Barisnya dijamin ada oleh
 *   `DatabaseSeeder`, jadi `firstOrFail()` — bukan ID hardcode.
 * - Admin-only lewat middleware `admin` di route, bukan di controller.
 * - Logo disimpan di disk LOKAL (`public`, folder `logo/`), bukan Drive: kop
 *   surat itu aset tampilan, bukan dokumen arsip. Logo lama dihapus saat diganti
 *   supaya tidak menumpuk file yatim.
 * - `PengaturanInstansi::$fillable` sengaja TIDAK memuat kolom cache folder Drive
 *   (`gdrive_folder_surat_masuk_id`/`_keluar_id`): keduanya ditulis oleh perintah
 *   `arsip:sinkron-ke-drive` lewat assignment atribut, bukan lewat form ini.
 *   `gdrive_root_folder_id` sudah dibuang dari skema — root folder
 *   dibaca dari `.env`.
 * - Logging aktivitas tidak dipanggil manual: `PengaturanInstansiObserver` yang
 *   mencatatnya.
 */
class PengaturanInstansiController extends Controller
{
    public function edit()
    {
        $pengaturanInstansi = PengaturanInstansi::firstOrFail();

        return view('pengaturan-instansi.edit', compact('pengaturanInstansi'));
    }

    public function update(UpdatePengaturanInstansiRequest $request)
    {
        $pengaturanInstansi = PengaturanInstansi::firstOrFail();

        $pengaturanInstansi->fill($request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            // Hapus logo lama dulu supaya tidak menumpuk file yatim di storage.
            if ($pengaturanInstansi->logo_path) {
                Storage::disk('public')->delete($pengaturanInstansi->logo_path);
            }

            $pengaturanInstansi->logo_path = $request->file('logo')->store('logo', 'public');
        }

        $pengaturanInstansi->save();

        return redirect()
            ->route('pengaturan-instansi.edit')
            ->with('success', 'Pengaturan instansi berhasil diperbarui.');
    }
}
