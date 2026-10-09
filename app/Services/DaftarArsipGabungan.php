<?php

namespace App\Services;

use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Support\CariArsip;
use App\Support\FilterArsip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daftar "Semua arsip" — surat masuk dan keluar dalam satu urutan.
 *
 * Ini bukan halaman /pencarian yang dihapus 5 Okt 2026, dan SENGAJA tidak punya
 * route sendiri: ia hidup sebagai tab `?jenis=semua` di dalam kedua halaman
 * daftar yang sudah ada, jadi satu kotak cari per daftar tetap utuh. Yang
 * dikembalikan cuma urutan gabungan; filter dan penggalian isi (L-15) dikerjakan
 * oleh FilterArsip + scopeCari, sama persis seperti mode per-jenis.
 *
 * Kenapa UNION dan bukan dua `paginate()` yang digabung di PHP: hasilnya dari
 * dua tabel, jadi pagination tidak bisa benar kalau masing-masing di-paginate
 * sendiri — halaman 2 akan berisi 20 surat masuk + 20 surat keluar, bukan 20
 * teratas dari gabungan (kesalahan yang sama seperti `limit(50)` lama, H7).
 * Kerangka UNION hanya membaca (id, jenis, tanggal, skor); model + relasinya
 * baru dimuat untuk 20 baris di halaman itu saja.
 */
class DaftarArsipGabungan
{
    public const PER_HALAMAN = 20;

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public static function halaman(Request $request): LengthAwarePaginator
    {
        $halaman = max(1, (int) $request->query('page', 1));

        $kerangka = self::kerangka($request);

        $total = DB::query()->fromSub($kerangka, 'hasil_arsip')->count('id');

        $baris = DB::query()
            ->fromSub(self::kerangka($request), 'hasil_arsip')
            ->orderByDesc('skor')
            ->orderByDesc('tanggal_surat')
            ->orderByDesc('id')
            ->forPage($halaman, self::PER_HALAMAN)
            ->get();

        return new LengthAwarePaginator(
            self::muat($baris),
            $total,
            self::PER_HALAMAN,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * Satu query kerangka: kaki surat masuk UNION kaki surat keluar.
     *
     * Dipanggil dua kali (sekali untuk COUNT, sekali untuk baris) karena
     * `fromSub()` mengambil SQL + binding dari builder yang sama; membangun
     * ulang lebih murah daripada menebak state builder setelah dipakai.
     */
    private static function kerangka(Request $request): Builder
    {
        $masuk = self::kaki(SuratMasuk::query(), 'masuk', $request);
        $keluar = self::kaki(SuratKeluar::query(), 'keluar', $request);

        return $masuk->union($keluar);
    }

    /**
     * @param  Builder<SuratMasuk>|Builder<SuratKeluar>  $query
     * @return Builder<SuratMasuk>|Builder<SuratKeluar>
     */
    private static function kaki(Builder $query, string $jenis, Request $request): Builder
    {
        [$skor, $binding] = CariArsip::ekspresiSkor(FilterArsip::cari($request));

        // Urutan binding di dalam satu kaki harus sama dengan urutan di teks
        // SQL: parameter select datang sebelum parameter where. `fromSub()`
        // membungkus seluruh binding sub-query sebagai binding `from`, jadi
        // kaki pertama dan kedua tidak bisa saling bertukar parameter —
        // dibuktikan di tes CariArsipMariaDbTest.
        $query->selectRaw("id, '{$jenis}' as jenis, tanggal_surat, {$skor} as skor", $binding);

        return FilterArsip::terapkan($query, $request);
    }

    /**
     * Baris kerangka (id, jenis, skor) -> model + relasinya, tetap di urutan
     * gabungan. Masing-masing model dapat atribut `jenis_arsip` supaya view
     * tahu harus menunjuk route surat-masuk atau surat-keluar.
     *
     * @param  Collection<int, \stdClass>  $baris
     * @return Collection<int, Model>
     */
    private static function muat(Collection $baris): Collection
    {
        if ($baris->isEmpty()) {
            return collect();
        }

        $idMasuk = $baris->where('jenis', 'masuk')->pluck('id')->all();
        $idKeluar = $baris->where('jenis', 'keluar')->pluck('id')->all();

        $tersedia = collect()
            ->concat(SuratMasuk::query()
                ->with(['primer', 'sekunder', 'tersier', 'petugas'])
                ->whereIn('id', $idMasuk)
                ->get())
            ->concat(SuratKeluar::query()
                ->with(['primer', 'sekunder', 'tersier', 'petugas'])
                ->whereIn('id', $idKeluar)
                ->get())
            ->keyBy(fn (Model $m): string => $m::class.'-'.$m->getKey());

        return $baris
            ->map(function (object $baris) use ($tersedia): ?Model {
                $model = $baris->jenis === 'masuk' ? SuratMasuk::class : SuratKeluar::class;
                $surat = $tersedia->get($model.'-'.$baris->id);

                if (! $surat) {
                    return null;
                }

                $surat->setAttribute('jenis_arsip', $baris->jenis);
                $surat->setAttribute('skor', (int) $baris->skor);

                return $surat;
            })
            ->filter()
            ->values();
    }
}
