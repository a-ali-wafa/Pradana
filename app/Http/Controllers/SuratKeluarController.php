<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuratKeluarRequest;
use App\Http\Requests\UpdateSuratKeluarRequest;
use App\Models\KlasifikasiPrimer;
use App\Models\SuratKeluar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CRUD arsip surat keluar.
 *
 * SENGAJA BELUM DITANGANI di controller ini (di luar scope "CRUD Surat Keluar"
 * menurut roadmap Bagian 11 AGENTS.md, jadi ditunda supaya tidak menebak-nebak
 * kode yang belum ada):
 * - Upload lampiran ke Google Drive — Lampiran model & GoogleDriveService belum
 *   dibuat (dikonfirmasi user). Setelah keduanya ada, tambahkan relasi
 *   `lampiran()` (morphMany) di model SuratKeluar lalu sambungkan di sini.
 * - Logging ke tabel `aktivitas` — ini item roadmap terpisah ("Logging aktivitas
 *   otomatis di setiap aksi") dan kemungkinan lebih rapi diimplementasikan lewat
 *   Model Observer/Event daripada dipanggil manual di tiap controller. Belum
 *   saya tambahkan di sini supaya tidak dobel/konflik nanti.
 * - Create/edit isi draf_konten_surat_keluar & generate PDF — juga item roadmap
 *   terpisah ("Generate PDF surat keluar, DomPDF").
 *
 * CATATAN: model SuratKeluar yang di-upload masih punya 'file_path' di
 * $fillable, padahal keputusan locked #8 di AGENTS.md bilang kolom itu sudah
 * dihapus (diganti tabel `lampiran`). Kemungkinan itu sisa sebelum migrasi ke
 * skema baru — controller ini tidak memakai/mengisi 'file_path' sama sekali,
 * tapi baiknya dibersihkan juga dari model & migration.
 */
class SuratKeluarController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Daftar surat keluar dengan filter tahun, status arsip, klasifikasi
     * primer, dan pencarian teks bebas (perihal/penerima/nomor surat).
     */
    public function index(Request $request): View
    {
        $query = SuratKeluar::query()->with(['primer', 'sekunder', 'tersier', 'petugas']);

        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_surat', $request->integer('tahun'));
        }

        if ($request->filled('status_arsip')) {
            $query->where('status_arsip', $request->input('status_arsip'));
        }

        if ($request->filled('klasifikasi_primer_id')) {
            $query->where('klasifikasi_primer_id', $request->integer('klasifikasi_primer_id'));
        }

        if ($request->filled('cari')) {
            $kataKunci = $request->input('cari');

            $query->where(function ($q) use ($kataKunci) {
                $q->where('perihal', 'like', "%{$kataKunci}%")
                    ->orWhere('penerima', 'like', "%{$kataKunci}%")
                    ->orWhere('nomor_surat', 'like', "%{$kataKunci}%");
            });
        }

        $suratKeluar = $query->orderByDesc('tanggal_surat')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('surat-keluar.index', [
            'suratKeluar' => $suratKeluar,
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('nama')->get(),
        ]);
    }

    public function create(): View
    {
        return view('surat-keluar.create', [
            'klasifikasiPrimer' => KlasifikasiPrimer::with('sekunder.tersier')->orderBy('nama')->get(),
        ]);
    }

    public function store(StoreSuratKeluarRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $suratKeluar = DB::transaction(function () use ($data, $request) {
            $tahun = (int) date('Y', strtotime($data['tanggal_surat']));

            $data['nomor_surat'] = $this->generateNomorSurat($tahun);
            $data['status_arsip'] = $data['status_arsip'] ?? 'aktif';
            $data['user_id'] = $request->user()->id;

            return SuratKeluar::create($data);
        });

        return redirect()
            ->route('surat-keluar.show', $suratKeluar)
            ->with('status', "Surat keluar {$suratKeluar->nomor_surat} berhasil disimpan.");
    }

    public function show(SuratKeluar $surat_keluar): View
    {
        $surat_keluar->load(['primer', 'sekunder', 'tersier', 'petugas', 'drafKonten']);

        return view('surat-keluar.show', ['suratKeluar' => $surat_keluar]);
    }

    public function edit(SuratKeluar $surat_keluar): View
    {
        return view('surat-keluar.edit', [
            'suratKeluar' => $surat_keluar,
            'klasifikasiPrimer' => KlasifikasiPrimer::with('sekunder.tersier')->orderBy('nama')->get(),
        ]);
    }

    public function update(UpdateSuratKeluarRequest $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        $surat_keluar->update($request->validated());

        return redirect()
            ->route('surat-keluar.show', $surat_keluar)
            ->with('status', "Surat keluar {$surat_keluar->nomor_surat} berhasil diperbarui.");
    }

    /**
     * Hapus permanen (tabel ini tidak pakai soft delete — lihat Bagian 5 & 12.4
     * AGENTS.md, cuma tabel `users` yang soft delete).
     *
     * TODO(B2): defaultnya cuma admin yang boleh hapus arsip permanen — ini
     * masih [WAJIB TANYA USER] di AGENTS.md, jadi konfirmasi ulang ke user.
     */
    public function destroy(Request $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        abort_unless(
            $request->user()->role === 'admin',
            403,
            'Hanya admin yang boleh menghapus arsip surat keluar secara permanen.'
        );

        $nomorSurat = $surat_keluar->nomor_surat;
        $surat_keluar->delete();

        return redirect()
            ->route('surat-keluar.index')
            ->with('status', "Surat keluar {$nomorSurat} berhasil dihapus.");
    }

    /**
     * Generate nomor urut surat keluar: global per tahun & reset tiap tahun
     * (D2, D3 — sudah [DEFAULT] jadi aman dipakai sekarang), dihitung dari
     * tahun `tanggal_surat` (bukan tanggal input, supaya surat yang di-backdate
     * tetap masuk urutan tahun suratnya).
     *
     * TODO(D1): format "{urutan}/SK/{tahun}" di bawah ini CUMA PLACEHOLDER.
     * Format resmi (mengikuti Tata Naskah Dinas / Perbup / Permendagri, atau
     * mengikuti format sistem lama) belum dikonfirmasi user. Ganti format
     * string ini begitu D1 terjawab — cari semua pemanggil generateNomorSurat()
     * kalau strukturnya berubah total (misal butuh kode klasifikasi di dalamnya).
     *
     * Query di-lock (lockForUpdate) di dalam transaction pemanggil supaya dua
     * surat yang dibuat nyaris bersamaan tidak dapat nomor sama. Untuk jaga-jaga
     * tetap ada retry beberapa kali kalau nomor ternyata sudah kepakai.
     */
    protected function generateNomorSurat(int $tahun): string
    {
        for ($percobaan = 0; $percobaan < 3; $percobaan++) {
            $urutan = SuratKeluar::whereYear('tanggal_surat', $tahun)->lockForUpdate()->count() + 1;
            $nomor = sprintf('%d/SK/%d', $urutan, $tahun);

            if (! SuratKeluar::where('nomor_surat', $nomor)->exists()) {
                return $nomor;
            }
        }

        throw new \RuntimeException('Gagal generate nomor surat keluar yang unik setelah beberapa percobaan.');
    }
}
