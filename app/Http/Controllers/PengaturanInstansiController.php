<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePengaturanInstansiRequest;
use App\Models\PengaturanInstansi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * CRUD Pengaturan Instansi — dibuat 31 Agu 2026.
 *
 * ⚠️ KONTEKS SESI: dibuat di sesi yang SAMA SEKALI TIDAK punya file project (tidak ada
 * model/controller/zip lain yang diupload, cuma AGENTS.md itu sendiri). Task ini
 * sebelumnya SENGAJA DITUNDA atas permintaan user (26 Agu 2026, lihat AGENTS.md
 * Bagian 3 "CRUD Pengaturan Instansi" — "nanti aja di agent lain", perlu dikonfirmasi
 * ulang sebelum dikerjakan). Dikerjakan lagi 31 Agu 2026 setelah user secara umum
 * bilang "kerjakan yang bisa dikerjakan" — dianggap sebagai konfirmasi ulang tsb.
 * TODO: user tetap perlu menegaskan ini memang yang dimaksud.
 *
 * ⚠️ MODEL ASLI TIDAK ADA. `PengaturanInstansi.php` (dibuat 24 Agu 2026 di
 * pradana-laravel-schema.zip) TIDAK diupload ke sesi ini dan menurut AGENTS.md
 * Bagian 4 "masih belum pernah di-cross-check" sejak awal dibuat. Controller ini
 * ditulis MURNI dari skema Bagian 5 [LOCKED], $fillable DIASUMSIKAN:
 * ['nama_instansi', 'jenis_instansi', 'alamat_instansi', 'no_telp', 'email', 'logo_path']
 * — SENGAJA TIDAK termasuk gdrive_root_folder_id / gdrive_folder_surat_masuk_id /
 * gdrive_folder_surat_keluar_id, karena 3 kolom itu di-cache oleh LampiranController
 * lewat assignment atribut langsung (bukan lewat form ini) — lihat AGENTS.md 12.16
 * poin 3-4. WAJIB verifikasi $fillable begitu model asli diupload — pola sama persis
 * seperti kasus Klasifikasi (12.11) & Surat Masuk (12.12) yang sempat salah tebak.
 *
 * Keputusan desain BARU (belum tercatat di Bagian 8/9 AGENTS.md — perlu di-lock kalau
 * disetujui user, lihat 12.18 untuk detail lengkap):
 * - Tabel single-row (1 instansi saja) → controller ini HANYA edit()+update(), TIDAK
 *   ada index/show/create/store/destroy. Konsisten dengan pola "route show sengaja
 *   tidak didaftarkan" di Klasifikasi (12.11) untuk tabel referensi sederhana.
 * - Baris pertama diasumsikan sudah dibuat oleh DatabaseSeeder (Bagian 3 menyebut
 *   seeder bikin "1 admin, pengaturan instansi, 2 contoh klasifikasi") → pakai
 *   firstOrFail(), BUKAN find($id) dengan ID hardcode.
 * - Admin-only di edit() DAN update() (mengikuti B4 [DEFAULT] "hanya admin boleh ubah
 *   pengaturan instansi"), lewat ensureAdmin() manual — TODO(B1), pola identik dengan
 *   KlasifikasiPrimerController dkk (12.11), BUKAN middleware/Gate resmi karena B1
 *   masih [WAJIB TANYA USER].
 * - Upload logo disimpan LOKAL (disk `public`, folder `logo/`), BUKAN lewat Google
 *   Drive — beda dari lampiran surat. Alasan: logo kop surat itu aset publik/statis
 *   untuk ditampilkan di header PDF/halaman, bukan dokumen arsip rahasia, jadi aturan
 *   "akses privat lewat Drive" di Bagian 2 tidak relevan untuk file ini. Ini ASUMSI
 *   DESAIN saya, BUKAN keputusan eksplisit user — tandai untuk dikonfirmasi.
 * - Logo lama dihapus dari storage saat diganti logo baru, supaya tidak menumpuk file
 *   yatim di disk.
 * - TIDAK memanggil Aktivitas::create() manual, konsisten dengan keputusan 12.8
 *   (logging aktivitas sengaja belum disambungkan ke controller manapun, menunggu
 *   Observer/Event terpisah supaya tidak tercatat dobel nanti).
 *
 * ⚠️ ROUTE BELUM DIGABUNG. `routes/web.php` asli juga tidak ada di sesi ini — route
 * untuk controller ini disediakan sebagai file snippet TERPISAH
 * (`routes-snippet-pengaturan-instansi.php`), BELUM digabung ke file asli.
 */
class PengaturanInstansiController extends Controller
{
    public function edit()
    {
        $this->ensureAdmin();

        $pengaturanInstansi = PengaturanInstansi::firstOrFail();

        // TODO(G1): view belum dibuat — tergantung keputusan stack frontend
        // (Blade+Bootstrap vs Livewire/Inertia+Vue/React), masih [WAJIB TANYA USER].
        return view('pengaturan-instansi.edit', compact('pengaturanInstansi'));
    }

    public function update(UpdatePengaturanInstansiRequest $request)
    {
        $this->ensureAdmin();

        $pengaturanInstansi = PengaturanInstansi::firstOrFail();

        $pengaturanInstansi->fill($request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            // Hapus logo lama dulu supaya tidak menumpuk file yatim di storage.
            if ($pengaturanInstansi->logo_path) {
                Storage::disk('public')->delete($pengaturanInstansi->logo_path);
            }

            $pengaturanInstansi->logo_path = $request->file('logo')->store('logo', 'public');
        }

        $pengaturanInstansi->save();

        return redirect()
            ->route('pengaturan-instansi.edit')
            ->with('success', 'Pengaturan instansi berhasil diperbarui.');
    }

    /**
     * TODO(B1): pengecekan admin manual, sementara sampai ada middleware/Gate resmi.
     * Pola identik dengan ensureAdmin() di KlasifikasiPrimerController dkk (12.11).
     */
    private function ensureAdmin(): void
    {
        abort_unless(
            Auth::user()?->role === 'admin',
            403,
            'Hanya admin yang dapat mengubah pengaturan instansi.'
        );
    }
}
