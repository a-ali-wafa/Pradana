<?php

namespace App\Http\Controllers;

use App\Models\PemusnahanArsip;
use App\Models\PemusnahanArsipItem;
use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Support\RetensiArsip;
use App\Support\Terbilang;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pemusnahan arsip SURAT + Berita Acara (keputusan L-06 / E3).
 *
 * Ini satu-satunya jalur yang boleh membuat berkas arsip hilang sungguhan:
 * staf mengajukan daftar arsip yang sudah lewat retensi DAN sudah dinonaktifkan,
 * admin menyetujui, lalu sistem menghapus permanen (baris + lampiran + file)
 * dan menerbitkan Berita Acara sebagai bukti.
 *
 * Dua syarat itu bukan hiasan: tanpa "harus inaktif dulu" (L-19) satu klik
 * bisa langsung memusnahkan arsip yang masih dipakai; tanpa retensi 5 tahun
 * (L-04) arsip muda bisa hilang.
 */
class PemusnahanArsipController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $status = $request->query('status', 'semua');

        $pemusnahan = PemusnahanArsip::query()
            ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
            ->with(['pengaju', 'pemroses', 'items'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('pemusnahan-arsip.index', [
            'pemusnahan' => $pemusnahan,
            'statusAktif' => $status,
        ]);
    }

    /**
     * Daftar arsip yang boleh diajukan musnah (L-04 + L-19).
     */
    public function create(): View
    {
        return view('pemusnahan-arsip.create', [
            'kandidat' => $this->kandidatPemusnahan(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Form mengirim pilihan sebagai "masuk:12" / "keluar:7" supaya satu
        // array bisa memuat kedua jenis surat.
        $data = $request->validate([
            'arsip' => ['required', 'array', 'min:1'],
            'arsip.*' => ['required', 'string', 'regex:/^(masuk|keluar):\d+$/'],
            'alasan' => ['nullable', 'string', 'max:2000'],
            'tanggal_pelaksanaan' => ['nullable', 'date'],
        ]);

        $dipilih = collect($data['arsip'])->map(function ($ombol) {
            [$jenis, $id] = explode(':', $ombol);

            return (object) ['jenis' => $jenis, 'id' => (int) $id];
        });

        // Validasi di server: form bisa berisi arsip yang sudah tidak memenuhi
        // syarat sejak halaman ini dibuka (misal belum 5 tahun / masih aktif).
        //
        // Bentuk lama memanggil `kandidatPemusnahan()` di sini — yaitu MEMUAT
        // SELURUH arsip tua kantor (+ hitung lampiran per baris) hanya untuk
        // mengecek id yang dikirim form. Sekarang query-nya dibatasi ke id yang
        // dipilih saja: empat statement, berapa pun isi gudangnya.
        $idMasuk = $dipilih->where('jenis', 'masuk')->pluck('id')->all();
        $idKeluar = $dipilih->where('jenis', 'keluar')->pluck('id')->all();

        $layak = [
            'masuk' => $this->idLayak(SuratMasuk::class, $idMasuk),
            'keluar' => $this->idLayak(SuratKeluar::class, $idKeluar),
        ];

        $tidakLayak = $dipilih->reject(
            fn ($p) => in_array($p->id, $layak[$p->jenis], true)
        );

        if ($tidakLayak->isNotEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'arsip' => 'Ada arsip yang belum memenuhi syarat dimusnahkan '
                        .'(umur di atas 5 tahun dan sudah dinonaktifkan). Muat ulang daftar lalu pilih lagi.',
                ]);
        }

        $pemusnahan = DB::transaction(function () use ($request, $data, $layak) {
            $pemusnahan = PemusnahanArsip::create([
                'alasan' => $data['alasan'] ?? null,
                'status' => 'menunggu',
                'diajukan_oleh' => $request->user()->id,
                'tanggal_pelaksanaan' => $data['tanggal_pelaksanaan'] ?? null,
            ]);

            // SATU query per jenis (bukan `findOrFail` + `lampiran()->count()`
            // per baris yang dipilih). Snapshot dibuat dari data yang sudah ada
            // di tangan, karena pengajuannya nanti bisa disetujui setelah suratnya
            // berubah — Berita Acara harus tetap menceritakan keadaan saat diajukan.
            $masuk = SuratMasuk::query()
                ->withCount('lampiran')
                ->whereIn('id', $layak['masuk'])
                ->get();

            foreach ($masuk as $arsip) {
                PemusnahanArsipItem::create($this->dataItem(
                    $pemusnahan->id,
                    SuratMasuk::class,
                    $arsip->id,
                    $arsip->nomor_surat,
                    $arsip->perihal,
                    $arsip->tanggal_surat,
                    $arsip->lampiran_count,
                ));
            }

            $keluar = SuratKeluar::query()
                ->withCount('lampiran')
                ->whereIn('id', $layak['keluar'])
                ->get();

            foreach ($keluar as $arsip) {
                PemusnahanArsipItem::create($this->dataItem(
                    $pemusnahan->id,
                    SuratKeluar::class,
                    $arsip->id,
                    $arsip->nomor_surat,
                    $arsip->perihal,
                    $arsip->tanggal_surat,
                    $arsip->lampiran_count,
                ));
            }

            return $pemusnahan;
        });

        return redirect()
            ->route('pemusnahan-arsip.show', $pemusnahan)
            ->with('success', 'Pengajuan pemusnahan dikirim, menunggu persetujuan admin.');
    }

    public function show(PemusnahanArsip $pemusnahan_arsip): View
    {
        $pemusnahan_arsip->load(['items', 'pengaju', 'pemroses']);

        return view('pemusnahan-arsip.show', ['pemusnahan' => $pemusnahan_arsip]);
    }

    /**
     * Persetujuan admin = eksekusi pemusnahan permanen (baris + lampiran + file
     * lewat observer SuratMasuk/SuratKeluarObserver saat forceDelete).
     */
    public function setujui(Request $request, PemusnahanArsip $pemusnahan_arsip): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang bisa menyetujui pemusnahan.');
        abort_if($pemusnahan_arsip->sudahDiproses(), 422, 'Pengajuan ini sudah diproses sebelumnya.');

        DB::transaction(function () use ($request, $pemusnahan_arsip) {
            $pemusnahan_arsip->load('items');

            foreach ($pemusnahan_arsip->items as $item) {
                $arsip = $item->arsipable_type::withTrashed()->find($item->arsipable_id);

                if ($arsip) {
                    $arsip->forceDelete();
                }
            }

            $pemusnahan_arsip->update([
                'status' => 'disetujui',
                'diproses_oleh' => $request->user()->id,
                'diproses_pada' => now(),
                'nomor_berita_acara' => $pemusnahan_arsip->nomor_berita_acara ?: $this->buatNomorBeritaAcara($pemusnahan_arsip),
                'tanggal_pelaksanaan' => $pemusnahan_arsip->tanggal_pelaksanaan ?? now()->toDateString(),
            ]);
        });

        return redirect()
            ->route('pemusnahan-arsip.show', $pemusnahan_arsip)
            ->with('success', 'Pemusnahan disetujui. Arsip dan berkasnya sudah dimusnahkan; Berita Acara siap dicetak.');
    }

    public function tolak(Request $request, PemusnahanArsip $pemusnahan_arsip): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang bisa menolak pemusnahan.');
        abort_if($pemusnahan_arsip->sudahDiproses(), 422, 'Pengajuan ini sudah diproses sebelumnya.');

        $data = $request->validate([
            'catatan_admin' => ['nullable', 'string', 'max:2000'],
        ]);

        $pemusnahan_arsip->update([
            'status' => 'ditolak',
            'diproses_oleh' => $request->user()->id,
            'diproses_pada' => now(),
            'catatan_admin' => $data['catatan_admin'] ?? null,
        ]);

        return redirect()
            ->route('pemusnahan-arsip.show', $pemusnahan_arsip)
            ->with('success', 'Pengajuan pemusnahan ditolak.');
    }

    /**
     * Berita Acara (dokumen resmi). Bisa dibuka kapan saja selama pengajuannya
     * sudah disetujui — isinya dibaca dari snapshot, bukan dari arsip yang
     * sudah dimusnahkan.
     */
    public function beritaAcara(PemusnahanArsip $pemusnahan_arsip)
    {
        abort_unless($pemusnahan_arsip->status === 'disetujui', 403, 'Berita Acara hanya tersedia untuk pemusnahan yang sudah disetujui.');

        $pemusnahan_arsip->load(['items', 'pemroses', 'pengaju']);

        $instansi = PengaturanInstansi::first();

        $pelaksanaan = Carbon::parse($pemusnahan_arsip->tanggal_pelaksanaan ?? now());

        $pdf = Pdf::loadView('pemusnahan-arsip.berita-acara', [
            'pemusnahan' => $pemusnahan_arsip,
            'instansi' => $instansi,
            // Logo dibaca dari disk, bukan `public_path('storage/...')` — lihat
            // alasan di PengaturanInstansi::logoPathUntukPdf() (5 Okt 2026).
            'logoPath' => $instansi?->logoPathUntukPdf(),
            'hari' => $pelaksanaan->translatedFormat('l'),
            'tanggalPelaksanaan' => $pelaksanaan->translatedFormat('d F Y'),
            'tanggalHuruf' => Terbilang::kata((int) $pelaksanaan->day),
            'bulanHuruf' => $pelaksanaan->translatedFormat('F'),
            'tahunHuruf' => Terbilang::kata((int) $pelaksanaan->year),
            'tempat' => $instansi?->tempatSurat() ?? '',
        ])->setPaper('a4', 'portrait');

        $namaFile = 'Berita-Acara-'.str_replace(['/', ' '], '-', $pemusnahan_arsip->nomor_berita_acara).'.pdf';

        return $pdf->stream($namaFile);
    }

    /**
     * Arsip yang boleh diajukan: lewat retensi (L-04 + L-21) DAN sudah
     * dinonaktifkan (L-19), belum dihapus lunak, dan belum masuk pengajuan yang
     * menunggu.
     *
     * Bentuk lama (sampai 9 Okt 2026) menghitung lampiran PER BARIS
     * (`$s->lampiran()->count()` di dalam `map`) dan memuat SELURUH item
     * pemusnahan sebagai model hanya untuk mengecek "sudah dipakai atau belum".
     * Terukur di `JumlahQueryLayarTest`: 40 arsip tua = 45 statement, dan angka
     * itu tumbuh linear bersama isi gudang (1.000 arsip tua ≈ 1.000 query —
     * layar ini dulunya satu-satunya yang begitu di seluruh aplikasi).
     * Sekarang jumlah statement-nya tetap berapapun kandidatnya: 1 query item
     * terpakai + 1 query surat masuk (`withCount`) + 1 query surat keluar.
     *
     * `with('primer')` yang lama dibuang: view `pemusnahan-arsip/create` hanya
     * membaca nomor, perihal, tanggal, pengirim/penerima, dan jumlah lampiran,
     * jadi relasi itu dimuat untuk tidak pernah dipakai.
     *
     * Kembalinya array biasa (bukan Collection): view hanya `count()` + `foreach`,
     * dan `Collection<int, T>` itu tidak kovarian di T — bentuk object shape hasil
     * `(object) [...]` tidak bisa dinyatakan lulus tanpa memaksa PHPStan dibungkam.
     *
     * @return list<\stdClass>
     */
    private function kandidatPemusnahan(): array
    {
        /** @var Collection<string, list<int>> $terpakai */
        $terpakai = PemusnahanArsipItem::query()
            ->whereHas('pemusnahan', fn ($q) => $q->where('status', 'menunggu'))
            ->get(['arsipable_type', 'arsipable_id'])
            ->groupBy('arsipable_type')
            ->map(fn ($kelompok) => $kelompok->pluck('arsipable_id')->all());

        $batas = RetensiArsip::batas();

        $masuk = SuratMasuk::query()
            ->where('tanggal_surat', '<', $batas)
            ->where('status_arsip', 'inaktif')
            ->when(
                $terpakai->has(SuratMasuk::class),
                fn ($q) => $q->whereNotIn('id', $terpakai->get(SuratMasuk::class, []))
            )
            ->withCount('lampiran')
            ->get()
            ->map(fn (SuratMasuk $s) => (object) [
                'id' => $s->id,
                'jenis' => 'masuk',
                'nomor_surat' => $s->nomor_surat,
                'perihal' => $s->perihal,
                'tanggal_surat' => $s->tanggal_surat,
                'pengirim' => $s->pengirim,
                'lampiran' => $s->lampiran_count,
            ]);

        $keluar = SuratKeluar::query()
            ->where('tanggal_surat', '<', $batas)
            ->where('status_arsip', 'inaktif')
            ->when(
                $terpakai->has(SuratKeluar::class),
                fn ($q) => $q->whereNotIn('id', $terpakai->get(SuratKeluar::class, []))
            )
            ->withCount('lampiran')
            ->get()
            ->map(fn (SuratKeluar $s) => (object) [
                'id' => $s->id,
                'jenis' => 'keluar',
                'nomor_surat' => $s->nomor_surat,
                'perihal' => $s->perihal,
                'tanggal_surat' => $s->tanggal_surat,
                'pengirim' => $s->penerima,
                'lampiran' => $s->lampiran_count,
            ]);

        return $masuk->concat($keluar)->sortByDesc('tanggal_surat')->values()->all();
    }

    /**
     * Dari daftar id yang dikirim form, mana yang MASIH memenuhi syarat
     * pemusnahan saat ini (lewat retensi, inaktif, belum diantrikan).
     *
     * Batas umurnya dari `App\Support\RetensiArsip` — sumber yang sama dengan
     * `UmurArsip::lewatRetensi()` (yang dipakai view & jalur hapus lampiran) dan
     * perintah `arsip:daftar-usang`. Kalau nanti kantor mengubah angka 5 tahun
     * itu, tidak ada satu pun dari ketiganya yang boleh tertinggal.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  list<int>  $id
     * @return list<int>
     */
    private function idLayak(string $model, array $id): array
    {
        if ($id === []) {
            return [];
        }

        $terpakai = PemusnahanArsipItem::query()
            ->where('arsipable_type', $model)
            ->whereIn('arsipable_id', $id)
            ->whereHas('pemusnahan', fn ($q) => $q->where('status', 'menunggu'))
            ->pluck('arsipable_id')
            ->all();

        return $model::query()
            ->whereIn('id', $id)
            ->where('tanggal_surat', '<', RetensiArsip::batas())
            ->where('status_arsip', 'inaktif')
            ->when($terpakai !== [], fn ($q) => $q->whereNotIn('id', $terpakai))
            ->pluck('id')
            ->all();
    }

    /**
     * Isi satu baris item pengajuan (snapshot — lihat komentar `store()`).
     *
     * @return array<string, mixed>
     */
    private function dataItem(
        int $pemusnahanId,
        string $modelArsip,
        int $arsipId,
        string $nomorSurat,
        ?string $perihal,
        ?\DateTimeInterface $tanggalSurat,
        int $jumlahLampiran
    ): array {
        return [
            'pemusnahan_arsip_id' => $pemusnahanId,
            'arsipable_type' => $modelArsip,
            'arsipable_id' => $arsipId,
            'nomor_surat_snapshot' => $nomorSurat,
            'perihal_snapshot' => $perihal,
            'tanggal_surat_snapshot' => $tanggalSurat,
            'jumlah_lampiran_snapshot' => $jumlahLampiran,
        ];
    }

    private function buatNomorBeritaAcara(PemusnahanArsip $pemusnahan): string
    {
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $bulan = (int) now()->month;

        return sprintf('BA-%03d/%s/%d', $pemusnahan->id, $romawi[$bulan - 1], now()->year);
    }
}
