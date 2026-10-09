<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\UpdateStatusArsipRequest;
use App\Models\KlasifikasiPrimer;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Support\FilterArsip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Bagian arsip surat yang IDENTIK untuk surat masuk dan surat keluar.
 *
 * Kenapa digabung sekarang: Fase 6 (9 Okt 2026) menemukan bug yang hanya mungkin
 * ada karena duplikasi — `updateStatusArsip()` ditulis dua kali dan KEDUANYA
 * salah memilih kunci flash (`status`, tidak dirender layout), jadi "Nyahkan"
 * terasa mati di dua layar sekaligus. Aturan yang sama sudah dipakai di project
 * ini untuk filter daftar (L-14), partial cascade JS (H6), dan kop PDF: kalau
 * dua salinan boleh berbeda, suatu hari mereka pasti berbeda.
 *
 * Yang SENGAJA ditinggal di controller adalah hal yang memang berbeda dan yang
 * tidak bisa diucapkan lewat tipe: kelas model (scope `palingRelevan` dan
 * `onlyTrashed` hanya dikenal Larastan pada Builder bertipe konkret), nama view,
 * kolom urut, dan kalimat flash yang menyebut nomor surat.
 *
 * Tipe param model di bawah SENGAJA union `SuratMasuk|SuratKeluar`, bukan `Model`
 * polos: `restore()` berasal dari trait `SoftDeletes`, jadi pada `Model` umum
 * Larastan melaporkan `method.notFound` — dan itu bukan temuan yang layak
 * dibungkam lewat baseline. Dengan union helper ini memang cuma bisa dipakai dua
 * model arsip, dan itu justru pernyataan yang benar (dibuktikan phpstan 9 Okt:
 * dua error, semuanya karena `Model` polos).
 */
trait MengelolaArsipSurat
{
    /**
     * Paginasi + susun data view daftar surat.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $arsip  query yang sudah di-`with()`, sudah
     *                                  `onlyTrashed()` (kalau perlu), dan sudah
     *                                  lewat `FilterArsip::terapkan()` +
     *                                  `palingRelevan()` di pemanggilnya
     */
    protected function daftarArsip(
        Request $request,
        Builder $arsip,
        string $tiket,
        string $kolomUrut,
        bool $melihatSampah,
    ): View {
        $cari = FilterArsip::cari($request);

        return view($tiket.'.index', [
            'arsip' => $arsip->orderByDesc($kolomUrut)
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('kode')->get(),
            'melihatSampah' => $melihatSampah,
            'usang' => FilterArsip::usang($request),
            'cari' => $cari,
            'gabung' => false,
        ]);
    }

    /**
     * Cascade klasifikasi untuk form create/edit (dulu disalin empat kali).
     *
     * @return Collection<int, KlasifikasiPrimer>
     */
    protected function pohonKlasifikasi(): Collection
    {
        return KlasifikasiPrimer::with('sekunder.tersier')->orderBy('kode')->get();
    }

    /**
     * "Nyahkan" / "Aktifkan kembali" (L-19, E8+E9) — satu tempat untuk kunci
     * flash supaya kesalahan 'status' seperti Fase 6 tidak bisa terulang dua kali.
     */
    protected function ubahStatusArsip(
        UpdateStatusArsipRequest $request,
        SuratMasuk|SuratKeluar $arsip,
        string $label,
    ): RedirectResponse {
        $arsip->update($request->validated());

        return back()->with(
            'success',
            $request->dinonaktifkan()
                ? $label.' dinonaktifkan sebagai arsip aktif.'
                : $label.' diaktifkan kembali.'
        );
    }

    /**
     * Soft delete (L-05) oleh admin saja. Perintahnya sengaja TIDAK dibungkus
     * catch(QueryException): soft delete hanyalah UPDATE `deleted_at`, FK restrict
     * tidak pernah tersentuh, jadi pesan "masih direferensikan" tidak mungkin
     * muncul dan justru menutupi kesalahan lain.
     */
    protected function buangArsip(
        Request $request,
        SuratMasuk|SuratKeluar $arsip,
        string $tiket,
        string $pesanBerhasil,
        string $pesanDitolak,
    ): RedirectResponse {
        abort_unless($request->user()->isAdmin(), 403, $pesanDitolak);

        $arsip->delete();

        return redirect()
            ->route($tiket.'.index')
            ->with('success', $pesanBerhasil);
    }

    /**
     * Pulihkan dari tempat sampah (admin saja). Modelnya sudah dicari pemanggil
     * (`withTrashed()->findOrFail()`) karena surat yang dihapus lunak tidak
     * ditemukan route binding default.
     */
    protected function pulihkanArsip(
        Request $request,
        SuratMasuk|SuratKeluar $arsip,
        string $tiket,
        string $label,
    ): RedirectResponse {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang bisa memulihkan arsip.');

        $arsip->restore();

        return redirect()
            ->route($tiket.'.show', $arsip)
            ->with('success', $label.' dipulihkan dari tempat sampah.');
    }
}
