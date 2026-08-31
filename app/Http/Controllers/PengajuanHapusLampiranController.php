<?php

namespace App\Http\Controllers;

use App\Models\Lampiran;
use App\Models\PengajuanHapusLampiran;
use App\Models\SuratMasuk;
use App\Services\GoogleDriveService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Alur pengajuan-persetujuan hapus lampiran — keputusan user 31 Agu 2026
 * (lihat AGENTS.md 12.17), MENGGANTIKAN `LampiranController::destroy()`
 * admin-langsung yang dibuat sebelumnya (lihat 12.16 poin 6, sekarang
 * dihapus/superseded).
 *
 * Aturan bisnis (dikonfirmasi user, BUKAN tebakan):
 * - Hapus lampiran HANYA boleh diajukan kalau surat induknya sudah berumur
 *   LEBIH DARI 5 TAHUN, sesuai E1 [LOCKED] (retensi 5 tahun seragam untuk
 *   semua jenis, dikonfirmasi user 31 Agu 2026 — lihat AGENTS.md Bagian 9).
 * - Siapa saja yang login boleh MENGAJUKAN (`store()`).
 * - Hanya admin yang boleh MENYETUJUI (`setujui()`) atau MENOLAK (`tolak()`).
 *   File & record `lampiran` baru benar-benar terhapus setelah disetujui.
 *
 * Route (grup middleware 'auth', didaftarkan di web.php):
 * - POST lampiran/{lampiran}/pengajuan-hapus                        -> store()
 * - GET  pengajuan-hapus-lampiran                                   -> index()   (admin)
 * - POST pengajuan-hapus-lampiran/{pengajuan_hapus_lampiran}/setujui -> setujui() (admin)
 * - POST pengajuan-hapus-lampiran/{pengajuan_hapus_lampiran}/tolak   -> tolak()   (admin)
 */
class PengajuanHapusLampiranController extends Controller
{
    public function __construct(private readonly GoogleDriveService $drive)
    {
    }

    public function store(Request $request, Lampiran $lampiran): RedirectResponse
    {
        $request->validate([
            'alasan' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless(
            $this->suratSudahLebihDari5Tahun($lampiran),
            403,
            'Lampiran hanya bisa diajukan untuk dihapus kalau suratnya sudah berumur lebih dari 5 tahun.'
        );

        $sudahDiajukan = PengajuanHapusLampiran::query()
            ->where('lampiran_id', $lampiran->id)
            ->where('status', 'menunggu')
            ->exists();

        abort_if($sudahDiajukan, 422, 'Lampiran ini sudah pernah diajukan untuk dihapus dan masih menunggu persetujuan admin.');

        PengajuanHapusLampiran::create([
            'lampiran_id' => $lampiran->id,
            'nama_file_snapshot' => $lampiran->nama_file,
            'diajukan_oleh' => Auth::id(),
            'alasan' => $request->input('alasan'),
            'status' => 'menunggu',
        ]);

        return back()->with('success', 'Pengajuan hapus lampiran terkirim, menunggu persetujuan admin.');
    }

    public function index(): View
    {
        $this->ensureAdmin();

        $pengajuanList = PengajuanHapusLampiran::query()
            ->where('status', 'menunggu')
            ->with(['lampiran', 'pengaju'])
            ->latest()
            ->get();

        // Belum ada view (`pengajuan-hapus-lampiran.index`) — tergantung G1, sama seperti modul lain.
        return view('pengajuan-hapus-lampiran.index', compact('pengajuanList'));
    }

    public function setujui(PengajuanHapusLampiran $pengajuan_hapus_lampiran): RedirectResponse
    {
        $this->ensureAdmin();

        abort_unless($pengajuan_hapus_lampiran->status === 'menunggu', 422, 'Pengajuan ini sudah diproses sebelumnya.');

        $lampiran = $pengajuan_hapus_lampiran->lampiran;

        abort_if(! $lampiran, 410, 'Lampiran yang diajukan sudah tidak ada (mungkin sudah terhapus lewat pengajuan lain).');

        try {
            $this->drive->delete($lampiran->google_drive_file_id);
        } catch (Throwable $e) {
            // Jangan blokir persetujuan cuma karena file sudah tidak ada di
            // Drive (mis. dihapus manual) atau Drive API error sesaat.
            Log::warning('Gagal hapus file Drive saat menyetujui pengajuan hapus lampiran', [
                'pengajuan_id' => $pengajuan_hapus_lampiran->id,
                'lampiran_id' => $lampiran->id,
                'google_drive_file_id' => $lampiran->google_drive_file_id,
                'error' => $e->getMessage(),
            ]);
        }

        $lampiran->delete();

        $pengajuan_hapus_lampiran->update([
            'status' => 'disetujui',
            'diproses_oleh' => Auth::id(),
            'diproses_pada' => now(),
        ]);

        return back()->with('success', 'Pengajuan disetujui, lampiran berhasil dihapus.');
    }

    public function tolak(Request $request, PengajuanHapusLampiran $pengajuan_hapus_lampiran): RedirectResponse
    {
        $this->ensureAdmin();

        abort_unless($pengajuan_hapus_lampiran->status === 'menunggu', 422, 'Pengajuan ini sudah diproses sebelumnya.');

        $request->validate([
            'catatan_admin' => ['nullable', 'string', 'max:1000'],
        ]);

        $pengajuan_hapus_lampiran->update([
            'status' => 'ditolak',
            'diproses_oleh' => Auth::id(),
            'diproses_pada' => now(),
            'catatan_admin' => $request->input('catatan_admin'),
        ]);

        return back()->with('success', 'Pengajuan hapus lampiran ditolak.');
    }

    /**
     * ASUMSI (lihat AGENTS.md 12.17, belum eksplisit dikonfirmasi user):
     * umur surat dihitung dari `tanggal_diterima` untuk surat_masuk (tanggal
     * masuk ke arsip), dan `tanggal_surat` untuk surat_keluar (tidak punya
     * kolom tanggal_diterima -- lihat skema Bagian 5).
     */
    private function suratSudahLebihDari5Tahun(Lampiran $lampiran): bool
    {
        $surat = $lampiran->lampiranable;

        abort_if(! $surat, 410, 'Surat induk lampiran ini sudah tidak ada.');

        $tanggalAcuan = $surat instanceof SuratMasuk ? $surat->tanggal_diterima : $surat->tanggal_surat;

        return Carbon::parse($tanggalAcuan)->lt(now()->subYears(5));
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->isAdmin(), 403, 'Hanya admin yang dapat mengelola pengajuan hapus lampiran.');
    }
}
