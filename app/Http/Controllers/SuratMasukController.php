<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuratMasukRequest;
use App\Http\Requests\UpdateSuratMasukRequest;
use App\Models\KlasifikasiPrimer;
use App\Models\SuratMasuk;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD Surat Masuk.
 *
 * Relasi sudah DIVERIFIKASI terhadap model SuratMasuk asli (diupload user
 * 26 Agu 2026, lihat AGENTS.md 12.12): primer()/sekunder()/tersier()/
 * lampiran() sesuai tebakan awal, TAPI relasi ke users ternyata bernama
 * petugas() — BUKAN user() seperti tebakan awal, sudah diperbaiki di
 * with()/load() pada index()/show().
 *
 * Auth: middleware('auth') biasa untuk semua action (siapa saja yang login
 * boleh input/edit surat masuk) KECUALI destroy() yang admin-only sesuai
 * ASUMSI SEMENTARA B2 ("siapa yang boleh hapus arsip surat permanen") —
 * B2 masih [WAJIB TANYA USER], BELUM final, cuma asumsi sementaranya
 * kebetulan "admin only". Koreksi 26 Agu 2026: sebelumnya salah ditulis
 * seolah B2 sudah [DEFAULT] di sini, ternyata belum — cross-check dengan
 * SuratKeluarController asli (yang benar menandainya TODO(B2)/WAJIB TANYA
 * USER). Kalau B2 dijawab beda dari "admin only", pengecekan di destroy()
 * di bawah perlu direvisi (atau dihapus kalau ternyata semua role boleh hapus).
 *
 * Pengecekan admin di destroy() pakai abort_unless+isAdmin() inline — konsisten
 * dengan pola SuratKeluarController::destroy() (retrofit B1, 1 Sep 2026).
 */
class SuratMasukController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $suratMasuk = SuratMasuk::with(['primer', 'sekunder', 'tersier', 'petugas'])
            ->orderByDesc('tanggal_diterima')
            ->paginate(20);

        return view('surat-masuk.index', compact('suratMasuk'));
    }

    public function create(): View
    {
        $klasifikasiPrimer = KlasifikasiPrimer::orderBy('kode')->get();

        return view('surat-masuk.create', compact('klasifikasiPrimer'));
    }

    public function store(StoreSuratMasukRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        // Surat baru selalu masuk sebagai arsip aktif. Toggle ke "inaktif"
        // adalah bagian dari fitur retensi terpisah yang belum dikerjakan
        // (blocked by E1) — lihat AGENTS.md Bagian 10 & 11.
        $data['status_arsip'] = 'aktif';

        SuratMasuk::create($data);

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', 'Surat masuk berhasil ditambahkan.');
    }

    public function show(SuratMasuk $surat_masuk): View
    {
        $surat_masuk->load(['primer', 'sekunder', 'tersier', 'petugas', 'lampiran']);

        return view('surat-masuk.show', compact('surat_masuk'));
    }

    public function edit(SuratMasuk $surat_masuk): View
    {
        $klasifikasiPrimer = KlasifikasiPrimer::orderBy('kode')->get();

        return view('surat-masuk.edit', [
            'suratMasuk'       => $surat_masuk,
            'klasifikasiPrimer' => $klasifikasiPrimer,
        ]);
    }

    public function update(UpdateSuratMasukRequest $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        $surat_masuk->update($request->validated());

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', 'Surat masuk berhasil diperbarui.');
    }

    /**
     * TODO(B2): "admin only" di sini masih ASUMSI SEMENTARA — B2 sendiri
     * masih [WAJIB TANYA USER], belum final. Konfirmasi ulang ke user.
     *
     * Pakai abort_unless inline (bukan ensureAdmin() private) agar konsisten
     * dengan SuratKeluarController::destroy() dan retrofit B1 (1 Sep 2026).
     */
    public function destroy(Request $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        abort_unless(
            $request->user()->isAdmin(),
            403,
            'Hanya admin yang boleh menghapus arsip surat.'
        );

        try {
            $surat_masuk->delete();
        } catch (QueryException $e) {
            // Restrict delete kemungkinan dari relasi lain yang mereferensikan
            // baris ini (mis. aktivitas.subjek, kalau logging sudah aktif).
            return back()->with(
                'error',
                'Surat masuk tidak bisa dihapus karena masih direferensikan data lain.'
            );
        }

        return redirect()
            ->route('surat-masuk.index')
            ->with('status', 'Surat masuk berhasil dihapus.');
    }
}
