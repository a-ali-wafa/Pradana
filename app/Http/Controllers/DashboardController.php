<?php

namespace App\Http\Controllers;

use App\Models\Aktivitas;
use App\Models\KlasifikasiPrimer;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Support\RentangTanggal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard statistik. Item roadmap Bagian 11 cuma bilang "dashboard
 * statistik" tanpa rincian widget — pilihan di bawah ini keputusan saya
 * berdasarkan skema yang tersedia (Bagian 5), BUKAN spesifikasi eksplisit
 * dari user. Kalau mau widget lain/beda, gampang disesuaikan.
 *
 * Tidak ada pembatasan role: dashboard dibaca semua yang login (L-07 — dua
 * tingkat, dan melihat statistik bukan aksi destruktif).
 *
 * Nama relasi yang dipakai (`primer`, `petugas`, `user`) SUDAH dikonfirmasi
 * benar terhadap model asli — lihat AGENTS_HISTORY.md 12.11/12.12/12.13/12.15.
 * `Aktivitas::user()` sempat jadi satu-satunya relasi belum terverifikasi
 * (ditebak `belongsTo(User::class)`, BUKAN `petugas()` seperti di
 * surat_masuk/surat_keluar) — dicek 28 Agu 2026 terhadap `Aktivitas.php`
 * asli, tebakan tepat.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        // Sembilan kartu statistik dulu = sembilan COUNT. Sekarang dua: satu
        // per tabel surat, angka-angkanya diambil sekaligus lewat SUM(CASE …).
        // Dashboard adalah halaman yang PASTI dibuka setiap sesi kerja, jadi
        // query yang dulu 9× round-trip ke MariaDB itu kelihatan sekali.
        $masuk = $this->agregat(SuratMasuk::query(), 'tanggal_diterima');
        $keluar = $this->agregat(SuratKeluar::query(), 'tanggal_surat');

        $stats = [
            'total_surat_masuk' => $masuk['total'],
            'total_surat_keluar' => $keluar['total'],
            'surat_masuk_bulan_ini' => $masuk['bulan_ini'],
            'surat_keluar_bulan_ini' => $keluar['bulan_ini'],
            'surat_masuk_aktif' => $masuk['aktif'],
            'surat_masuk_inaktif' => $masuk['inaktif'],
            'surat_keluar_aktif' => $keluar['aktif'],
            'surat_keluar_inaktif' => $keluar['inaktif'],
            'surat_mendesak_aktif' => $masuk['mendesak_aktif'] + $keluar['mendesak_aktif'],
        ];

        $suratMasukTerbaru = SuratMasuk::with(['primer', 'petugas'])
            ->latest('tanggal_diterima')
            ->latest('id')
            ->take(5)
            ->get();

        $suratKeluarTerbaru = SuratKeluar::with(['primer', 'petugas'])
            ->latest('tanggal_surat')
            ->latest('id')
            ->take(5)
            ->get();

        $aktivitasTerbaru = Aktivitas::with('user')
            ->latest('created_at')
            ->latest('id')
            ->take(10)
            ->get();

        // H3: hitung surat masuk DAN keluar bersama-sama. Sebelumnya hanya
        // surat masuk, sehingga "klasifikasi terbanyak" di dashboard menyesatkan
        // (kantor yang banyak bikin surat keluar melihat grafik yang salah).
        $hitungPerPrimer = fn (string $model) => $model::query()
            ->select('klasifikasi_primer_id', DB::raw('count(*) as total'))
            ->groupBy('klasifikasi_primer_id')
            ->get();

        // Kolom yang dipilih cuma yang dibaca view (kode + nama): `all()` mengambil
        // seluruh baris dengan SELURUH kolomnya sekali per muat dashboard.
        $primerById = KlasifikasiPrimer::query()->get(['id', 'kode', 'nama'])->keyBy('id');

        $klasifikasiTerpopuler = $hitungPerPrimer(SuratMasuk::class)
            ->concat($hitungPerPrimer(SuratKeluar::class))
            ->groupBy('klasifikasi_primer_id')
            ->map(fn ($kelompok) => (object) [
                'primer' => $primerById->get($kelompok->first()->klasifikasi_primer_id),
                'total' => $kelompok->sum('total'),
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        return view('dashboard.index', [
            'stats' => $stats,
            'suratMasukTerbaru' => $suratMasukTerbaru,
            'suratKeluarTerbaru' => $suratKeluarTerbaru,
            'aktivitasTerbaru' => $aktivitasTerbaru,
            'klasifikasiTerpopuler' => $klasifikasiTerpopuler,
            // Panjang batang grafik relatif terhadap nilai terbesar. Dulu
            // dihitung di view dalam sebuah blok @php yang dijalankan ulang
            // untuk tiap baris.
            'maksTotalKlasifikasi' => $klasifikasiTerpopuler->first()->total ?? 1,
        ]);
    }

    /**
     * Semua angka sebuah tabel surat, dalam SATU query.
     *
     * Kenapa `SUM(CASE WHEN … THEN 1 ELSE 0 END)` dan bukan beberapa `count()`:
     * hasilnya identik, tapi yang satu memindai tabel sekali. Bentuk ini jalan
     * identik di MariaDB dan SQLite (engine tes), dan tetap memakai scope soft
     * delete karena query-nya dibangun dari Eloquent Builder, bukan DB::select.
     *
     * "Bulan ini" dulu `whereMonth(kolom, n)->whereYear(kolom, y)`: fungsi di
     * atas kolom membuat index tanggal tidak terpakai. Sekarang interval
     * setengah terbuka dari `RentangTanggal::bulan()` — dan itu juga yang
     * memperbaiki hari terakhir bulan Februari (lihat helper-nya).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array{total: int, bulan_ini: int, aktif: int, inaktif: int, mendesak_aktif: int}
     */
    private function agregat(Builder $query, string $kolomTanggal): array
    {
        if (preg_match('/^[a-z_]+$/', $kolomTanggal) !== 1) {
            throw new \InvalidArgumentException("Nama kolom tanggal tidak sah: {$kolomTanggal}");
        }

        [$awalBulan, $awalBulanBerikutnya] = RentangTanggal::bulan();

        /** @var Builder<TModel> $query */
        $baris = $query
            ->selectRaw(
                'COUNT(*) AS total,'
                .'SUM(CASE WHEN '.$kolomTanggal.' >= ? AND '.$kolomTanggal.' < ? THEN 1 ELSE 0 END) AS bulan_ini,'
                ."SUM(CASE WHEN status_arsip = 'aktif' THEN 1 ELSE 0 END) AS aktif,"
                ."SUM(CASE WHEN status_arsip = 'inaktif' THEN 1 ELSE 0 END) AS inaktif,"
                ."SUM(CASE WHEN sifat = 'mendesak' AND status_arsip = 'aktif' THEN 1 ELSE 0 END) AS mendesak_aktif",
                [$awalBulan, $awalBulanBerikutnya]
            )
            ->first();

        // `getAttributes()` dipakai, bukan `$baris->total`: kolom hasil agregat
        // bukan properti model, dan IDE/PHPStan tidak bisa tahu isinya.
        $mentah = $baris?->getAttributes() ?? [];

        return [
            'total' => (int) ($mentah['total'] ?? 0),
            'bulan_ini' => (int) ($mentah['bulan_ini'] ?? 0),
            'aktif' => (int) ($mentah['aktif'] ?? 0),
            'inaktif' => (int) ($mentah['inaktif'] ?? 0),
            'mendesak_aktif' => (int) ($mentah['mendesak_aktif'] ?? 0),
        ];
    }
}
