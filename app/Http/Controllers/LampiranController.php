<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLampiranRequest;
use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * LampiranController — upload & unduh (stream) lampiran untuk surat_masuk &
 * surat_keluar. File fisik disimpan di Google Drive lewat Service Account;
 * DB HANYA menyimpan `google_drive_file_id` (keputusan locked #8/#9,
 * Bagian 2 "Aturan file di DB") — tidak pernah link publik.
 *
 * ⚠️ DIBUAT 31 Agu 2026 — file ini BENAR-BENAR BARU, bukan revisi.
 * AGENTS.md sebelumnya (keliru) sempat mengklaim controller ini sudah ada
 * bareng GoogleDriveService — dikonfirmasi langsung oleh user file ini
 * belum pernah dibuat sama sekali, yang ada cuma migration `lampiran`.
 * Lihat AGENTS.md Bagian 3 & 12.16 untuk detail koreksi.
 *
 * ⚠️ **Update 31 Agu 2026**: method `destroy()` (hapus langsung admin-only)
 * yang sebelumnya ada di sini SUDAH DIHAPUS — digantikan alur pengajuan
 * & persetujuan di `PengajuanHapusLampiranController` sesuai keputusan user
 * (lihat AGENTS.md 12.17). Controller ini sekarang HANYA urus upload+unduh.
 *
 * Route (didaftarkan di web.php, grup middleware 'auth'):
 * - POST   surat-masuk/{surat_masuk}/lampiran   -> storeForSuratMasuk()
 * - POST   surat-keluar/{surat_keluar}/lampiran -> storeForSuratKeluar()
 * - GET    lampiran/{lampiran}/unduh            -> download()
 */
class LampiranController extends Controller
{
    public function __construct(private readonly GoogleDriveService $drive)
    {
    }

    public function storeForSuratMasuk(StoreLampiranRequest $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        $this->simpanLampiran($request, $surat_masuk, 'surat_masuk');

        return back()->with('success', 'Lampiran berhasil diunggah.');
    }

    public function storeForSuratKeluar(StoreLampiranRequest $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        $this->simpanLampiran($request, $surat_keluar, 'surat_keluar');

        return back()->with('success', 'Lampiran berhasil diunggah.');
    }

    /**
     * Stream isi file dari Drive API lewat backend — TIDAK PERNAH lewat
     * link publik Drive (keputusan locked #9). Middleware 'auth' sudah
     * dipasang di route group, jadi cukup diandalkan di sini.
     */
    public function download(Lampiran $lampiran): Response
    {
        $this->authorize('view', $lampiran);

        $file = $this->drive->getFileContent($lampiran->google_drive_file_id);

        return response($file['content'])
            ->header('Content-Type', $file['mime_type'] ?: ($lampiran->mime_type ?: 'application/octet-stream'))
            ->header('Content-Disposition', 'inline; filename="'.($file['name'] ?: $lampiran->nama_file).'"');
    }

    /**
     * Upload semua file dalam request ke folder Drive yang sesuai, lalu
     * simpan masing-masing sebagai baris `lampiran` polymorphic milik $surat
     * (pakai relasi lampiran() yang sudah dikonfirmasi ada di kedua model,
     * lihat AGENTS.md 12.9/12.10).
     */
    private function simpanLampiran(StoreLampiranRequest $request, SuratMasuk|SuratKeluar $surat, string $jenisSurat): void
    {
        // Relasi primer() sudah terverifikasi untuk SuratMasuk (12.12) & SuratKeluar (12.13).
        $primer = $surat->primer;
        $folderId = $this->resolveFolderId($jenisSurat, $primer);

        foreach ($request->file('files') as $file) {
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $safeName = \Illuminate\Support\Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;

            // Save temporarily to local storage
            $localPath = $file->store('temp_lampiran');

            \App\Jobs\UploadLampiranKeDriveJob::dispatch(
                $localPath,
                $safeName,
                $file->getMimeType(),
                $folderId,
                $surat,
                Auth::id()
            );
        }
    }

    /**
     * Resolusi folder Drive tujuan, sesuai AGENTS.md Bagian 7 & C3/C6 [LOCKED]
     * ("Arsip_PRADANA / Surat Masuk|Keluar / [Klasifikasi Primer] / file").
     *
     * ✅ **Dikonfirmasi user 31 Agu 2026** (lihat AGENTS.md 12.17, kode C6 baru
     * di Bagian 9 [DEFAULT]) — sebelumnya ini masih asumsi (12.16), sekarang final:
     * 1. `config('gdrive.root_folder_id')` DIPERLAKUKAN SAMA DENGAN folder
     *    "Arsip_PRADANA" itu sendiri — TIDAK ada folder "Arsip_PRADANA"
     *    terpisah dibuat di dalamnya.
     * 2. Level klasifikasi untuk subfolder CUKUP PRIMER saja
     *    ("{kode} - {nama}"), TIDAK turun ke sekunder/tersier.
     *
     * Masih belum jelas / belum disentuh: kolom `pengaturan_instansi.gdrive_root_folder_id`
     * (di skema Bagian 5) tidak dipakai di sini, cuma dua kolom cache
     * `gdrive_folder_surat_masuk_id`/`gdrive_folder_surat_keluar_id` sesuai
     * instruksi Bagian 7 langkah 4. Model `PengaturanInstansi.php` belum
     * pernah di-cross-check (lihat Bagian 4) — cache folder ID ditulis lewat
     * assignment atribut langsung + save(), BUKAN update([...]), supaya
     * tidak bergantung pada $fillable yang belum pasti.
     */
    private function resolveFolderId(string $jenisSurat, ?KlasifikasiPrimer $primer): string
    {
        $rootFolderId = config('gdrive.root_folder_id');

        abort_if(blank($rootFolderId), 500, 'GOOGLE_DRIVE_ROOT_FOLDER_ID belum diisi di .env.');

        $pengaturan = PengaturanInstansi::query()->first();
        $kolomCache = $jenisSurat === 'surat_masuk' ? 'gdrive_folder_surat_masuk_id' : 'gdrive_folder_surat_keluar_id';
        $namaFolder = $jenisSurat === 'surat_masuk' ? 'Surat Masuk' : 'Surat Keluar';

        $folderJenisId = $pengaturan?->{$kolomCache};

        if (! $folderJenisId) {
            $folderJenisId = $this->drive->getOrCreateFolder($namaFolder, $rootFolderId);

            if ($pengaturan) {
                $pengaturan->{$kolomCache} = $folderJenisId;
                $pengaturan->save();
            }
        }

        if (! $primer) {
            return $folderJenisId;
        }

        // Tidak di-cache — tidak ada kolom di skema untuk folder per-klasifikasi.
        return $this->drive->getOrCreateFolder(
            "{$primer->kode} - {$primer->nama}",
            $folderJenisId
        );
    }
}
