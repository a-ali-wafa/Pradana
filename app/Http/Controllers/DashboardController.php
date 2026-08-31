<?php

namespace App\Http\Controllers;

use App\Models\Aktivitas;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard statistik. Item roadmap Bagian 11 cuma bilang "dashboard
 * statistik" tanpa rincian widget — pilihan di bawah ini keputusan saya
 * berdasarkan skema yang tersedia (Bagian 5), BUKAN spesifikasi eksplisit
 * dari user. Kalau mau widget lain/beda, gampang disesuaikan.
 *
 * Tidak ada pembatasan role — mengikuti pola versi lama ("semua role akses
 * semua menu"), karena B1 (matriks permission detail) masih [WAJIB TANYA
 * USER] tanpa default aman. Cukup middleware('auth') polos di route.
 *
 * Nama relasi yang dipakai (`primer`, `petugas`, `user`) SUDAH dikonfirmasi
 * benar terhadap model asli — lihat AGENTS.md 12.11/12.12/12.13/12.15.
 * `Aktivitas::user()` sempat jadi satu-satunya relasi belum terverifikasi
 * (ditebak `belongsTo(User::class)`, BUKAN `petugas()` seperti di
 * surat_masuk/surat_keluar) — dicek 28 Agu 2026 terhadap `Aktivitas.php`
 * asli, tebakan tepat.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_surat_masuk' => SuratMasuk::count(),
            'total_surat_keluar' => SuratKeluar::count(),
            'surat_masuk_bulan_ini' => SuratMasuk::whereMonth('tanggal_diterima', now()->month)
                ->whereYear('tanggal_diterima', now()->year)
                ->count(),
            'surat_keluar_bulan_ini' => SuratKeluar::whereMonth('tanggal_surat', now()->month)
                ->whereYear('tanggal_surat', now()->year)
                ->count(),
            'surat_masuk_aktif' => SuratMasuk::where('status_arsip', 'aktif')->count(),
            'surat_masuk_inaktif' => SuratMasuk::where('status_arsip', 'inaktif')->count(),
            'surat_keluar_aktif' => SuratKeluar::where('status_arsip', 'aktif')->count(),
            'surat_keluar_inaktif' => SuratKeluar::where('status_arsip', 'inaktif')->count(),
            // "Perlu perhatian": surat sifat mendesak yang arsipnya masih aktif
            'surat_mendesak_aktif' => SuratMasuk::where('sifat', 'mendesak')->where('status_arsip', 'aktif')->count()
                + SuratKeluar::where('sifat', 'mendesak')->where('status_arsip', 'aktif')->count(),
        ];

        $suratMasukTerbaru = SuratMasuk::with(['primer', 'petugas'])
            ->latest('tanggal_diterima')
            ->take(5)
            ->get();

        $suratKeluarTerbaru = SuratKeluar::with(['primer', 'petugas'])
            ->latest('tanggal_surat')
            ->take(5)
            ->get();

        $aktivitasTerbaru = Aktivitas::with('user')->latest()->take(10)->get();

        $klasifikasiTerpopuler = SuratMasuk::select('klasifikasi_primer_id', DB::raw('count(*) as total'))
            ->groupBy('klasifikasi_primer_id')
            ->with('primer')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        return view('dashboard.index', compact(
            'stats',
            'suratMasukTerbaru',
            'suratKeluarTerbaru',
            'aktivitasTerbaru',
            'klasifikasiTerpopuler',
        ));
    }
}
