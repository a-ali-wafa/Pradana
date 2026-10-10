<?php

namespace App\Http\Controllers;

use App\Models\KlasifikasiPrimer;
use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Support\RentangTanggal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan & Buku Agenda (keputusan I1=b + I2=b, 3 Okt 2026).
 *
 * Dua keluaran, satu layar:
 *  - `rekap`  -> CSV (dibuka di Excel/LibreOffice kantor). Bukan .xlsx karena
 *    `maatwebsite/excel` butuh PhpSpreadsheet + extension yang tidak selalu ada
 *    di shared hosting pilihan kantor (K1=a), dan CSV cukup untuk rekap.
 *  - `agenda` -> PDF "Buku Agenda Surat" format cetak (dompdf, sesuai F2=a).
 *
 * Periode selalu rentang tanggal explicit (`dari`/`sampai`) — rekap tanpa batas
 * waktu akan menghasilkan dokumen yang tidak diminta siapa pun.
 */
class LaporanController extends Controller
{
    /**
     * Kolom yang BENAR-BENAR dibaca rekap CSV dan Buku Agenda (Fase P0, 10 Okt 2026).
     *
     * Kenapa daftar eksplisit dan bukan `->get()` apa-adanya: `surat_masuk`
     * menyimpan teks hasil baca lampiran — `isi_hasil_baca` LONGTEXT bisa 20.000
     * karakter per surat, plus `ringkasan` TEXT. Tidak satu pun kolom itu
     * tercetak di keluaran laporan, tapi `->get()` tetap mengangkatnya ke memori
     * dan meng-hidrasi jadi atribut model.
     *
     * Diukur 9 Okt 2026 di MariaDB tanding berisi 16.005 surat:
     *   `->get()`          1.674 ms, puncak 56 MB
     *   `->get([kolom])`     383 ms, puncak  2 MB
     * Rekap setahun penuh kantor adalah jalur yang paling mungkin memakai angka
     * itu, dan target deploy-nya shared hosting (K1=a) dengan `memory_limit`
     * yang tidak bisa kita naikkan sendiri.
     *
     * `user_id` + `klasifikasi_primer_id` wajib ada di sini meski tidak dicetak
     * langsung: keduanya kunci eager load `petugas` dan `primer`. `id` kunci
     * withCount + pemecah seri urutan.
     */
    private const KOLOM_ARSIP = [
        'id',
        'nomor_surat',
        'perihal',
        'sifat',
        'status_arsip',
        'tanggal_surat',
        'klasifikasi_primer_id',
        'user_id',
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        [$dari, $sampai] = $periode = $this->periode($request);

        // Jumlah baris ditampilkan sebelum mengunduh: rekap kosong tanpa
        // penjelasan biasanya disimpulkan sebagai "aplikasinya rusak", bukan
        // "periodenya memang tidak ada surat".
        //
        // ANGKA-nya diambil dari COUNT di database, bukan dari menghitung hasil
        // `baris()` yang sudah dimuat ke memori. `baris()` membentuk satu model
        // + relasi + withCount per surat, jadi untuk periode setahun penuh itu
        // berarti mengangkat seluruh arsip ke PHP hanya untuk satu angka di
        // layar. `jumlah()` dan `baris()` berbagi satu pembangun query
        // (`tanyakan()`), jadi angka di layar dan isi CSV/PDF tetap tidak bisa
        // berbeda kesimpulan.
        return view('laporan.index', [
            'periode' => $periode,
            'jumlah' => $this->jumlah($request, $dari, $sampai),
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('kode')->get(),
            'filter' => $request->only(['jenis', 'klasifikasi_primer_id', 'status_arsip']),
        ]);
    }

    /**
     * Rekap per periode sebagai CSV.
     */
    public function rekap(Request $request): StreamedResponse
    {
        [$dari, $sampai] = $this->periode($request);
        $baris = $this->baris($request, $dari, $sampai);

        $namaFile = 'rekap-pradana-'.$dari->format('Y-m-d').'-s-d-'.$sampai->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($baris) {
            $keluaran = fopen('php://output', 'w');

            // BOM: tanpa ini Excel Indonesia membuka UTF-8 sebagai ANSI dan
            // karakter non-ASCII (mis. "è", "—") jadi rusak.
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, ['No', 'Jenis', 'Tanggal Surat', 'Tanggal Diterima', 'Nomor Surat', 'Pengirim / Penerima', 'Perihal', 'Sifat', 'Klasifikasi', 'Status Arsip', 'Lampiran', 'Petugas'], ';');

            $nomor = 1;
            foreach ($baris as $item) {
                fputcsv($keluaran, [
                    $nomor++,
                    'Surat '.($item['jenis'] === 'masuk' ? 'Masuk' : 'Keluar'),
                    $item['tanggal_surat'],
                    $item['tanggal_diterima'] ?? '-',
                    $item['nomor_surat'],
                    $item['lawan'],
                    $item['perihal'],
                    $item['sifat'],
                    $item['klasifikasi'],
                    $item['status_arsip'],
                    $item['jumlah_lampiran'],
                    $item['petugas'],
                ], ';');
            }

            fclose($keluaran);
        }, $namaFile, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Buku Agenda Surat (PDF, A4 landscape) — dokumen cetak manual kantor.
     */
    public function agenda(Request $request)
    {
        [$dari, $sampai] = $this->periode($request);

        $instansi = PengaturanInstansi::first();

        $pdf = Pdf::loadView('laporan.agenda', [
            'items' => $this->baris($request, $dari, $sampai),
            'instansi' => $instansi,
            // partials/kop-pdf.blade.php membaca $logoPath (path fisik, bukan URL).
            'logoPath' => $instansi?->logoPathUntukPdf(),
            'dari' => $dari->translatedFormat('d F Y'),
            'sampai' => $sampai->translatedFormat('d F Y'),
            'jenis' => $this->jenis($request),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('buku-agenda-'.$dari->format('Y-m-d').'-s-d-'.$sampai->format('Y-m-d').'.pdf');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periode(Request $request): array
    {
        $dari = $request->filled('dari')
            ? Carbon::parse($request->input('dari'))
            : now()->startOfMonth();

        $sampai = $request->filled('sampai')
            ? Carbon::parse($request->input('sampai'))
            : now()->endOfMonth();

        // Periode terbalik menghasilkan dokumen kosong tanpa penjelasan —
        // dibalik saja, itu yang dimaksud pengguna.
        return $dari->lte($sampai) ? [$dari, $sampai] : [$sampai, $dari];
    }

    private function jenis(Request $request): string
    {
        return in_array($request->query('jenis'), ['masuk', 'keluar'], true) ? $request->query('jenis') : 'semua';
    }

    /**
     * Satu bentuk data untuk CSV dan PDF, jadi kedua keluaran tidak bisa
     * menyimpulkan hal berbeda dari database yang sama.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function baris(Request $request, Carbon $dari, Carbon $sampai): Collection
    {
        $jenis = $this->jenis($request);
        $baris = [];

        if ($jenis !== 'keluar') {
            $baris = array_merge($baris, $this->barisMasuk($request, $dari, $sampai));
        }

        if ($jenis !== 'masuk') {
            $baris = array_merge($baris, $this->barisKeluar($request, $dari, $sampai));
        }

        return collect($baris)
            ->sortBy([['tanggal_surat', 'asc'], ['jenis', 'asc']])
            ->values();
    }

    /**
     * Jumlah surat di periode yang sama, TANPA mengangkat barisnya ke memori.
     * Memakai `tanyakan()` yang sama dengan `baris()`, jadi caption "N surat
     * cocok" di layar tidak bisa berbeda dari isi CSV/PDF.
     */
    private function jumlah(Request $request, Carbon $dari, Carbon $sampai): int
    {
        $jenis = $this->jenis($request);
        $total = 0;

        if ($jenis !== 'keluar') {
            $total += $this->tanyakan(SuratMasuk::class, $request, $dari, $sampai)->count();
        }

        if ($jenis !== 'masuk') {
            $total += $this->tanyakan(SuratKeluar::class, $request, $dari, $sampai)->count();
        }

        return $total;
    }

    /**
     * Kerangka query laporan: periode + filter, satu sumber untuk `barisMasuk()`,
     * `barisKeluar()`, dan `jumlah()`.
     *
     * Batas periodenya interval SETENGAH TERBUKA (lihat App\Support\RentangTanggal).
     * Sebelum ini `whereBetween('tanggal_surat', [$dari, $sampai])` dengan string
     * tanggal: di MariaDB kolom DATE memotong jam jadi masih benar, tapi di SQLite
     * nilainya tersimpan '2026-12-31 00:00:00' dan `<= '2026-12-31'` bernilai
     * FALSE — surat tertanggal hari TERAKHIR hilang dari rekap dan Buku Agenda.
     * Itu dibuktikan tes BatasTanggalLaporanTest sebelum perapian ini.
     *
     * `@template TModel` dipakai supaya pemanggilnya punya tipe MODEL sungguhan:
     * `$s->primer->kode` dan `$s->lampiran_count` hanya bisa diperiksa PHPStan
     * kalau builder-nya `Builder<SuratMasuk>`, bukan `Builder<Model>`.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    private function tanyakan(string $model, Request $request, Carbon $dari, Carbon $sampai): Builder
    {
        [$bawah, $atas] = RentangTanggal::rentang($dari, $sampai);

        return $model::query()
            ->where('tanggal_surat', '>=', $bawah)
            ->where('tanggal_surat', '<', $atas)
            ->when(
                $request->query('klasifikasi_primer_id'),
                fn ($b) => $b->where('klasifikasi_primer_id', (int) $request->query('klasifikasi_primer_id'))
            )
            ->when(
                $request->query('status_arsip'),
                fn ($b) => $b->where('status_arsip', $request->query('status_arsip'))
            )
            ->orderBy('tanggal_surat')
            ->orderBy('id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function barisMasuk(Request $request, Carbon $dari, Carbon $sampai): array
    {
        return $this->tanyakan(SuratMasuk::class, $request, $dari, $sampai)
            ->select([...self::KOLOM_ARSIP, 'pengirim', 'tanggal_diterima'])
            ->with(['primer:id,kode,nama', 'petugas:id,nama_lengkap'])
            ->withCount('lampiran')
            ->get()
            ->map(fn (SuratMasuk $s) => $this->isiUmum($s, 'masuk') + [
                'tanggal_diterima' => $s->tanggal_diterima?->format('Y-m-d'),
                'lawan' => $s->pengirim,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function barisKeluar(Request $request, Carbon $dari, Carbon $sampai): array
    {
        return $this->tanyakan(SuratKeluar::class, $request, $dari, $sampai)
            ->select([...self::KOLOM_ARSIP, 'penerima'])
            ->with(['primer:id,kode,nama', 'petugas:id,nama_lengkap'])
            ->withCount('lampiran')
            ->get()
            ->map(fn (SuratKeluar $s) => $this->isiUmum($s, 'keluar') + [
                // Surat keluar tidak pernah "diterima"; kolomnya tetap ada di
                // baris hasil supaya CSV & agenda punya header yang sama.
                'tanggal_diterima' => null,
                'lawan' => $s->penerima,
            ])
            ->values()
            ->all();
    }

    /**
     * Bagian yang identik dari kedua jenis surat.
     *
     * Dipisah jadi array terpisah (bukan satu method dengan `$model` string +
     * `$kolomLawan` string) karena nama kolom dinamis membuat PHPStan buta dan
     * membuat kedua bentuk surat menyatu jadi `Model` polos — itu persis bentuk
     * yang membuat 9 temuan lama masuk baseline. Yang hanya ada di salah satu
     * tabel (`pengirim`/`penerima`, `tanggal_diterima`) sengaja TIDAK di sini.
     *
     * @template TModel of SuratMasuk|SuratKeluar
     *
     * @param  TModel  $s
     * @return array<string, mixed>
     */
    private function isiUmum(Model $s, string $jenisSurat): array
    {
        return [
            'jenis' => $jenisSurat,
            'tanggal_surat' => $s->tanggal_surat?->format('Y-m-d'),
            'nomor_surat' => $s->nomor_surat,
            'perihal' => $s->perihal,
            'sifat' => $s->sifat,
            'klasifikasi' => $s->primer ? $s->primer->kode.' '.$s->primer->nama : '-',
            'status_arsip' => $s->status_arsip,
            'jumlah_lampiran' => $s->lampiran_count,
            'petugas' => $s->petugas?->nama_lengkap ?? '-',
        ];
    }
}
