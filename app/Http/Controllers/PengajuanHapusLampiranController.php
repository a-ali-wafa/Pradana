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
            $lampiran->isEligibleForDeletion(),
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
        $pengajuanList = PengajuanHapusLampiran::query()
            ->where('status', 'menunggu')
            ->with(['lampiran', 'pengaju'])
            ->latest()
            ->get();

        return view('pengajuan-hapus-lampiran.index', compact('pengajuanList'));
    }

    public function setujui(PengajuanHapusLampiran $pengajuan_hapus_lampiran): RedirectResponse
    {
        abort_unless($pengajuan_hapus_lampiran->status === 'menunggu', 422, 'Pengajuan ini sudah diproses sebelumnya.');

        $lampiran = $pengajuan_hapus_lampiran->lampiran;

        abort_if(! $lampiran, 410, 'Lampiran yang diajukan sudah tidak ada (mungkin sudah terhapus lewat pengajuan lain).');

        \App\Jobs\HapusLampiranDariDriveJob::dispatch($lampiran->google_drive_file_id);

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
}
