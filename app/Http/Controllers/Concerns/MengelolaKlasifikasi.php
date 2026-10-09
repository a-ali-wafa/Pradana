<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Bagian CRUD klasifikasi bertingkat yang IDENTIK untuk primer, sekunder, dan
 * tersier (tiga controller, 260 baris, dulu tiga salinan yang sudah mulai
 * melenceng bentuk — lihat pola yang sama pada parsial cascade JS [H6] dan
 * filter daftar [L-14]).
 *
 * Yang disengaja TIDAK masuk ke trait: kelas modelnya (`KlasifikasiPrimer::create()`
 * dsb. tetap di controller, karena `class-string<TModel>` membuat Larastan kehilangan
 * tipe konkret dan menambah entri baseline), relasi yang di-eager-load per tingkat,
 * dan daftar induk untuk form. Itu memang berbeda bertiga; memaksanya masuk akan
 * menghasilkan helper yang penuh parameter dan tidak menghapus apa pun.
 *
 * Yang paling berharga untuk disatukan justru `hapusKlasifikasi()`: `catch`
 * QueryException dari FK restrict + pesan ramah + kunci flash. Fase 6 membuktikan
 * bahwa kunci flash yang disalin tangan bisa salah dua kali sekaligus.
 */
trait MengelolaKlasifikasi
{
    /**
     * Daftar satu tingkat klasifikasi, urut kode, 20 per halaman (H2).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $baris
     */
    protected function daftarKlasifikasi(Builder $baris, string $tiket, string $namaVariabel): View
    {
        return view($tiket.'.index', [
            $namaVariabel => $baris->orderBy('kode')->paginate(20),
        ]);
    }

    /**
     * Redirect + flash sukses standar sesudah mutasi klasifikasi.
     */
    protected function selesaiKlasifikasi(string $tiket, string $pesan): RedirectResponse
    {
        return redirect()
            ->route($tiket.'.index')
            ->with('success', $pesan);
    }

    /**
     * Hapus entri klasifikasi.
     *
     * Kolom `klasifikasi_*_id` di surat memakai `restrictOnDelete()`, dan di
     * MariaDB bentuknya berperilaku `NO ACTION` — checked constraint tetap
     * dilempar sebagai QueryException saat baris yang masih dipakai dihapus.
     * Karena itu pesan ramah ini BUKAN dead code (PHPStan menyangka begitu
     * karena tidak bisa melihat PDO runtime; entri baseline-nya sekarang satu, dulu tiga).
     */
    protected function hapusKlasifikasi(
        Model $entri,
        string $tiket,
        string $label,
        string $pemakai,
    ): RedirectResponse {
        try {
            $entri->delete();
        } catch (QueryException) {
            return back()->with(
                'error',
                $label.' tidak bisa dihapus karena masih dipakai di '.$pemakai.'.'
            );
        }

        return $this->selesaiKlasifikasi($tiket, $label.' berhasil dihapus.');
    }
}
