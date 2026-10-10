<?php

namespace App\Http\Controllers;

use App\Models\Aktivitas;
use App\Models\User;
use App\Support\CariArsip;
use App\Support\RentangTanggal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman log aktivitas untuk admin (L-22 / P3=a).
 *
 * Log ini satu-satunya jejak "siapa mengubah apa" di aplikasi tanpa sistem
 * kepemilikan (L-10: user boleh dihapus walau punya surat; B8: "log aktivitas
 * hanya membantu untuk berjaga jika terjadi sesuatu"). Karena itu halamannya
 * read-only — tidak ada edit/hapus dari UI. Baris lama dibersihkan lewat
 * perintah `arsip:bersihkan-log` (E6), bukan dari layar.
 */
class AktivitasController extends Controller
{
    /**
     * 30 baris per halaman, BUKAN 20 seperti daftar surat (H2/L-14).
     *
     * Sengaja, dan di luar jangkauan H2: keputusan itu dibuat untuk daftar surat
     * yang satu barisnya berat (klasifikasi, sifat, tombol aksi). Baris log cuma
     * tiga kolom teks dan satu relasi `user` yang sudah di-eager-load, jadi
     * layar bisa memuat lebih banyak tanpa biaya query tambahan — berguna justru
     * karena layar ini dipakai untuk memindai jejak, bukan membuka satu arsip.
     */
    private const BARIS_PER_HALAMAN = 30;

    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request): View
    {
        return view('aktivitas.index', [
            'aktivitas' => $this->terapkanFilter($request)
                ->latest('created_at')
                ->latest('id')
                ->paginate(self::BARIS_PER_HALAMAN)
                ->withQueryString(),
            'pilihanUser' => User::orderBy('nama_lengkap')->get(['id', 'nama_lengkap']),
            'filter' => [
                'cari' => $request->input('cari'),
                'user_id' => $request->input('user_id'),
                'dari' => $request->input('dari'),
                'sampai' => $request->input('sampai'),
            ],
        ]);
    }

    /**
     * Rekap CSV dari log, dengan FILTER YANG SAMA PERSIS dengan layar
     * (`docs/daftar-peningkatan.md` §2 butir 5, keperluan audit).
     *
     * Satu `terapkanFilter()` untuk dua keluaran bukan gaya-gayaan: ini pola yang
     * sudah dipakai `LaporanController` (`baris()`), dan alasan yang sama —
     * kalau ekspor dan layar punya kerangka query masing-masing, suatu hari angka
     * di CSV berbeda dari angka di layar dan tidak ada yang tahu yang mana yang
     * benar. Untuk dokumen audit, perbedaan diam-diam seperti itu lebih buruk
     * daripada tidak ada ekspor sama sekali.
     *
     * Dialirkan per 500 baris (`chunk`), bukan `get()`: tabel `aktivitas` adalah
     * satu-satunya tabel yang tumbuh tanpa batas (setiap aksi di semua modul
     * menulis satu baris) dan tidak ada plafon yang membuat "semua" selalu kecil.
     * Chunking di sini benar-benar mungkin — tidak seperti laporan surat — karena
     * log hanya satu tabel dengan urutan tetap, jadi tidak perlu menggabungkan dua
     * sumber sebelum baris pertama boleh ditulis.
     */
    public function rekap(Request $request): StreamedResponse
    {
        $namaFile = 'log-aktivitas-pradana-'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($request) {
            $keluaran = fopen('php://output', 'w');

            // BOM + pemisah `;` — sama seperti rekap surat: Excel Indonesia
            // membuka UTF-8 tanpa BOM sebagai ANSI, dan `;` adalah pemisah default
            // di locale kantor (`,` membuat semuanya masuk satu kolom).
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, ['Waktu (jam kantor)', 'Petugas', 'Aksi', 'Subjek'], ';');

            $this->terapkanFilter($request)
                ->select(['id', 'user_id', 'aksi', 'subjek_type', 'subjek_id', 'created_at'])
                ->latest('created_at')
                ->latest('id')
                ->chunk(500, function ($baris) use ($keluaran) {
                    foreach ($baris as $satu) {
                        $subjek = $satu->subjek_type === null
                            ? '-'
                            : class_basename($satu->subjek_type).' #'.$satu->subjek_id;

                        fputcsv($keluaran, [
                            $satu->created_at?->format('Y-m-d H:i') ?? '-',
                            // `->` + `??` (bukan `?->`): relasi `user` boleh null dan
                            // `??` sudah menangani keduanya — Larastan menandai nullsafe
                            // di sisi kiri `??` sebagai redundan, dan dia benar.
                            $satu->user->nama_lengkap ?? '(user dihapus)',
                            $satu->aksi,
                            $subjek,
                        ], ';');
                    }
                });

            fclose($keluaran);
        }, $namaFile, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Kerangka query log + semua filter yang sedang aktif. Dipakai `index()` dan
     * `rekap()` — jangan menyalin isinya ke tempat lain.
     *
     * @return Builder<Aktivitas>
     */
    private function terapkanFilter(Request $request): Builder
    {
        // Eager load untuk kedua keluaran: layar menampilkan `nama_lengkap` dan
        // CSV juga. Kolom dibatasi — `user` cuma dipakai untuk satu nama.
        $query = Aktivitas::query()->with('user:id,nama_lengkap');

        if ($request->filled('cari')) {
            // Lewat `CariArsip::terapkan()`: karakter `%` dan `_` di-escape dan
            // diberi `ESCAPE '!'` (bentuk lama `like "%$kata%"` memperlakukan
            // keduanya sebagai wildcard), dan beberapa kata yang diketik admin
            // berarti "semuanya harus ada" alih-alih "harus persis beruntun".
            CariArsip::terapkan($query, $request->input('cari'), ['aksi']);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $batas = $this->batasPeriode($request);

        if ($batas !== null) {
            if ($batas[0] !== null) {
                $query->where('created_at', '>=', $batas[0]);
            }

            if ($batas[1] !== null) {
                $query->where('created_at', '<', $batas[1]);
            }
        }

        return $query;
    }

    /**
     * Rentang `created_at` sebagai interval setengah terbuka `[awal, akhir+1hari)`.
     *
     * Dulu `whereDate('created_at', '<=', $sampai)` — hasilnya benar, tapi
     * `date()` di atas kolom membuat index tidak terpakai, dan maknanya
     * "sampai jam nol hari itu" kalau kolomnya dibaca sebagai datetime.
     *
     * Sisi yang tidak diisi dibiarkan null (bukan dipasang batas palsu), jadi
     * filter "Dari" saja atau "Sampai" saja tetap berfungsi.
     *
     * Periode terbalik dibalik, bukan dibiarkan kosong — sama seperti
     * `LaporanController::periode()`: orang yang tertukar mengisi dua kotak
     * tanggal maksudnya jelas.
     *
     * @return array{0: ?string, 1: ?string}|null null kalau tidak ada filter tanggal
     */
    private function batasPeriode(Request $request): ?array
    {
        $dari = $request->filled('dari') ? $request->input('dari') : null;
        $sampai = $request->filled('sampai') ? $request->input('sampai') : null;

        if ($dari === null && $sampai === null) {
            return null;
        }

        if ($dari !== null && $sampai !== null && $dari > $sampai) {
            // Perbandingan string aman di sini: kotaknya `input type=date`, jadi
            // isinya selalu YYYY-MM-DD dan urutan leksikografis = urutan kronologis.
            [$dari, $sampai] = [$sampai, $dari];
        }

        return [
            $dari === null ? null : RentangTanggal::satuHari($dari)[0],
            $sampai === null ? null : RentangTanggal::satuHari($sampai)[1],
        ];
    }
}
