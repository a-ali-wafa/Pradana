<?php

namespace App\Http\Controllers;

use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Illuminate\Http\Request;

/**
 * BARU — 1 Sep 2026. Roadmap "Pencarian arsip global" — tidak diblokir item
 * WAJIB TANYA USER mana pun, dikerjakan langsung dari skema Bagian 5 [LOCKED].
 *
 * Desain (BELUM ada spek eksplisit dari user untuk fitur ini — jadi ini keputusan
 * agent, ditandai jelas, gampang direvisi — pola sama seperti DashboardController
 * yang widgetnya juga "dipilih bebas", lihat 12.15):
 * - 1 kotak pencarian tunggal: LIKE di `nomor_surat`, `perihal`, dan pengirim
 *   (surat_masuk) / penerima (surat_keluar), digabung hasil dari kedua tabel,
 *   diurutkan tanggal_surat terbaru dulu.
 * - Filter tambahan opsional: jenis (masuk/keluar/semua), klasifikasi_primer_id,
 *   rentang tanggal (dari/sampai).
 * - TIDAK mencari isi `ringkasan` (surat_masuk) atau `isi_surat` (draf_konten,
 *   longtext) untuk versi MVP ini — gampang ditambah nanti kalau dibutuhkan.
 * - Dibatasi `limit(50)` per jenis surat supaya query tidak berat tanpa index
 *   full-text — cukup untuk MVP, revisit kalau volume data sudah besar.
 *
 * ⚠️ Controller ini BERDIRI SENDIRI (route terpisah, lihat routes-snippet) karena
 * web.php tidak diupload ke sesi ini — sama seperti CetakSuratKeluarController.
 * Middleware: 'auth' polos, tidak ada pembatasan role, konsisten dengan pola
 * index() surat_masuk/surat_keluar yang sudah ada (12.12).
 *
 * Relasi `primer()`/`petugas()` yang dipakai untuk eager-load hasil SUDAH
 * terverifikasi (12.11/12.12/12.13) — aman dipakai tanpa tebakan baru.
 */
class PencarianController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->query('q', ''));
        $jenis = $request->query('jenis', 'semua'); // semua|masuk|keluar
        $klasifikasiPrimerId = $request->query('klasifikasi_primer_id');
        $dari = $request->query('dari');
        $sampai = $request->query('sampai');

        $adaFilter = $kataKunci !== '' || $klasifikasiPrimerId || $dari || $sampai;

        $hasilMasuk = collect();
        $hasilKeluar = collect();

        if ($adaFilter) {
            if (in_array($jenis, ['semua', 'masuk'], true)) {
                $hasilMasuk = SuratMasuk::query()
                    ->with(['primer', 'petugas'])
                    ->when($kataKunci !== '', fn ($q) => $q->where(function ($q2) use ($kataKunci) {
                        $q2->where('nomor_surat', 'like', "%{$kataKunci}%")
                            ->orWhere('perihal', 'like', "%{$kataKunci}%")
                            ->orWhere('pengirim', 'like', "%{$kataKunci}%");
                    }))
                    ->when($klasifikasiPrimerId, fn ($q) => $q->where('klasifikasi_primer_id', $klasifikasiPrimerId))
                    ->when($dari, fn ($q) => $q->whereDate('tanggal_surat', '>=', $dari))
                    ->when($sampai, fn ($q) => $q->whereDate('tanggal_surat', '<=', $sampai))
                    ->latest('tanggal_surat')
                    ->limit(50)
                    ->get()
                    ->map(fn ($s) => $this->keSatuBaris($s, 'masuk'));
            }

            if (in_array($jenis, ['semua', 'keluar'], true)) {
                $hasilKeluar = SuratKeluar::query()
                    ->with(['primer', 'petugas'])
                    ->when($kataKunci !== '', fn ($q) => $q->where(function ($q2) use ($kataKunci) {
                        $q2->where('nomor_surat', 'like', "%{$kataKunci}%")
                            ->orWhere('perihal', 'like', "%{$kataKunci}%")
                            ->orWhere('penerima', 'like', "%{$kataKunci}%");
                    }))
                    ->when($klasifikasiPrimerId, fn ($q) => $q->where('klasifikasi_primer_id', $klasifikasiPrimerId))
                    ->when($dari, fn ($q) => $q->whereDate('tanggal_surat', '>=', $dari))
                    ->when($sampai, fn ($q) => $q->whereDate('tanggal_surat', '<=', $sampai))
                    ->latest('tanggal_surat')
                    ->limit(50)
                    ->get()
                    ->map(fn ($s) => $this->keSatuBaris($s, 'keluar'));
            }
        }

        $hasil = $hasilMasuk->concat($hasilKeluar)->sortByDesc('tanggal_surat')->values();

        return view('pencarian.index', [
            'hasil' => $hasil,
            'kataKunci' => $kataKunci,
            'jenis' => $jenis,
        ]);
    }

    private function keSatuBaris($surat, string $jenis): array
    {
        return [
            'jenis' => $jenis,
            'nomor_surat' => $surat->nomor_surat,
            'perihal' => $surat->perihal,
            'lawan' => $jenis === 'masuk' ? $surat->pengirim : $surat->penerima,
            'tanggal_surat' => $surat->tanggal_surat,
            'klasifikasi' => $surat->primer->nama ?? '-',
            'route' => $jenis === 'masuk'
                ? route('surat-masuk.show', $surat->id)
                : route('surat-keluar.show', $surat->id),
        ];
    }
}
