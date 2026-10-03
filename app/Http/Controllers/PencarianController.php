<?php

namespace App\Http\Controllers;

use App\Models\KlasifikasiPrimer;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pencarian arsip gabungan surat masuk + surat keluar.
 *
 * L-15: pencarian ikut menjangkau ISI (`ringkasan` surat masuk dan `isi_surat`
 * draf surat keluar), bukan cuma nomor & perihal.
 * L-14/H1: filternya sama dengan daftar surat (klasifikasi, sifat, status
 * arsip, tahun) supaya satu bahasa di dua tempat.
 * H7: hasilnya di-pagination 20 per halaman, tidak lagi `limit(50)` yang
 * memotong diam-diam.
 *
 * Kenapa pakai UNION + LengthAwarePaginator dan bukan `->paginate()` biasa:
 * hasilnya datang dari DUA tabel, jadi `paginate()` di salah satunya tidak
 * bisa memberi urutan gabungan yang benar di halaman 2 dan seterusnya.
 * Kerangka UNION hanya mengambil (id, jenis, tanggal) — halaman ke-20 dari
 * gabungan itu baru dibaca sebagai model + relasinya, jadi tetap murah.
 *
 * LIKE, bukan MATCH AGAINST: dataset kantor kecil (ribuan baris) dan LIKE
 * jalan identik di MariaDB maupun SQLite yang dipakai suite tes. Index
 * FULLTEXT masuk daftar squash S11 kalau volume ternyata bikin lambat.
 */
class PencarianController extends Controller
{
    public function index(Request $request): View
    {
        $kataKunci = trim((string) $request->query('q', ''));
        $jenis = in_array($request->query('jenis'), ['masuk', 'keluar'], true) ? $request->query('jenis') : 'semua';

        $filter = [
            'klasifikasi_primer_id' => $request->query('klasifikasi_primer_id'),
            'sifat' => $request->query('sifat'),
            'status_arsip' => $request->query('status_arsip'),
            'dari' => $request->query('dari'),
            'sampai' => $request->query('sampai'),
        ];

        $adaFilter = $kataKunci !== '' || collect($filter)->filter()->isNotEmpty();

        $hasil = $adaFilter
            ? $this->cari($request, $kataKunci, $jenis, $filter)
            : null;

        return view('pencarian.index', [
            'hasil' => $hasil,
            'kataKunci' => $kataKunci,
            'jenis' => $jenis,
            'filter' => $filter,
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('kode')->get(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function cari(Request $request, string $kataKunci, string $jenis, array $filter): LengthAwarePaginator
    {
        $kerangka = collect();

        if ($jenis !== 'keluar') {
            $kerangka->push($this->kerangka(SuratMasuk::class, 'masuk', 'pengirim', $kataKunci, $filter));
        }

        if ($jenis !== 'masuk') {
            $kerangka->push($this->kerangka(SuratKeluar::class, 'keluar', 'penerima', $kataKunci, $filter));
        }

        $union = $kerangka->shift();

        foreach ($kerangka as $bagian) {
            $union = $union->union($bagian);
        }

        $halaman = max(1, (int) $request->query('page', 1));
        $total = DB::query()->fromSub($union, 'hasil_pencarian')->count('id');

        $baris = DB::query()
            ->fromSub($union, 'hasil_pencarian')
            ->orderByDesc('tanggal_surat')
            ->orderByDesc('id')
            ->forPage($halaman, 20)
            ->get();

        return new LengthAwarePaginator(
            $this->muatModel($baris),
            $total,
            20,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * Satu kaki UNION: id + jenis + tanggal, sudah dengan semua filter.
     *
     * @param  array<string, mixed>  $filter
     */
    private function kerangka(string $model, string $jenis, string $kolomLawan, string $kataKunci, array $filter): Builder
    {
        /** @var Builder $q */
        $q = $model::query()
            ->select(['id', 'tanggal_surat'])
            ->addSelect(DB::raw("'{$jenis}' as jenis"))
            ->whereNull('deleted_at');

        if ($kataKunci !== '') {
            $q->where(function (Builder $dalam) use ($kataKunci, $kolomLawan, $jenis) {
                $dalam->where('nomor_surat', 'like', "%{$kataKunci}%")
                    ->orWhere('perihal', 'like', "%{$kataKunci}%")
                    ->orWhere($kolomLawan, 'like', "%{$kataKunci}%");

                // L-15: isi surat juga ikut digali.
                if ($jenis === 'masuk') {
                    $dalam->orWhere('ringkasan', 'like', "%{$kataKunci}%");
                } else {
                    $dalam->orWhereHas('drafKonten', fn ($d) => $d->where('isi_surat', 'like', "%{$kataKunci}%"));
                }
            });
        }

        return $q
            ->when($filter['klasifikasi_primer_id'], fn ($b) => $b->where('klasifikasi_primer_id', (int) $filter['klasifikasi_primer_id']))
            ->when($filter['sifat'], fn ($b) => $b->where('sifat', $filter['sifat']))
            ->when($filter['status_arsip'], fn ($b) => $b->where('status_arsip', $filter['status_arsip']))
            ->when($filter['dari'], fn ($b) => $b->whereDate('tanggal_surat', '>=', $filter['dari']))
            ->when($filter['sampai'], fn ($b) => $b->whereDate('tanggal_surat', '<=', $filter['sampai']));
    }

    /**
     * Baris hasil UNION (id + jenis) dibaca sebagai model lengkap, sekali query
     * per tabel, urutan gabungan dipertahankan.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $baris
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function muatModel($baris): \Illuminate\Support\Collection
    {
        $idMasuk = $baris->where('jenis', 'masuk')->pluck('id');
        $idKeluar = $baris->where('jenis', 'keluar')->pluck('id');

        $masuk = SuratMasuk::with(['primer', 'petugas'])->whereIn('id', $idMasuk)->get()->keyBy('id');
        $keluar = SuratKeluar::with(['primer', 'petugas', 'drafKonten'])->whereIn('id', $idKeluar)->get()->keyBy('id');

        return $baris
            ->map(function ($baris) use ($masuk, $keluar) {
                $surat = $baris->jenis === 'masuk' ? $masuk->get($baris->id) : $keluar->get($baris->id);

                return $surat ? $this->keSatuBaris($surat, $baris->jenis) : null;
            })
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function keSatuBaris($surat, string $jenis): array
    {
        return [
            'jenis' => $jenis,
            'nomor_surat' => $surat->nomor_surat,
            'perihal' => $surat->perihal,
            'lawan' => $jenis === 'masuk' ? $surat->pengirim : $surat->penerima,
            'tanggal_surat' => $surat->tanggal_surat,
            'status_arsip' => $surat->status_arsip,
            'klasifikasi' => $surat->primer?->nama ?? '-',
            'ringkasan' => $jenis === 'masuk'
                ? $surat->ringkasan
                : ($surat->drafKonten?->isi_surat ?? null),
            'route' => $jenis === 'masuk'
                ? route('surat-masuk.show', $surat->id)
                : route('surat-keluar.show', $surat->id),
        ];
    }
}
