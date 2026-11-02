<?php

namespace App\Http\Controllers;

use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Support\RentangTanggal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dashboard: identitas kantor, angka agregat surat, dan lima surat terakhir di
 * kedua arah. Yang boleh tampil di sini hanya angka yang dibaca orang kantor
 * sambil bekerja — log aktivitas dan sebaran klasifikasi punya halamannya sendiri
 * (`/aktivitas`, `/laporan`) dan tidak perlu menumpuk di layar pertama.
 *
 * Tidak ada pembatasan role: dashboard dibaca semua yang login (dua tingkat, dan
 * melihat statistik bukan aksi destruktif).
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
            // Angka status arsip diambil dari pemindaian yang sama dengan total,
            // jadi `inaktif` tetap ikut terbawa walau layarnya sekarang hanya
            // menampilkan yang aktif.
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

        return view('dashboard.index', [
            'stats' => $stats,
            'suratMasukTerbaru' => $suratMasukTerbaru,
            'suratKeluarTerbaru' => $suratKeluarTerbaru,
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
