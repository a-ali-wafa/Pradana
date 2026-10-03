<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLampiranRequest;
use App\Models\Lampiran;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload & unduh lampiran surat masuk/keluar.
 *
 * Keputusan L-01: file fisik disimpan di DISK LOKAL (disk `arsip`, root
 * storage/app/private/arsip — di luar public/), bukan lagi Google Drive.
 * Drive turun jadi target sinkronisasi backup terjadwal
 * (php artisan arsip:sinkron-ke-drive), jadi upload tidak lagi bergantung
 * pada kuota/API pihak ketiga maupun pada queue worker (L-02: semua sync).
 *
 * Kolom `google_drive_file_id` tetap dipertahankan untuk (a) data lama yang
 * cuma punya file Drive supaya masih bisa diunduh, dan (b) catatan hasil
 * backup. Aturan lama tetap berlaku: TIDAK PERNAH simpan/serve link publik —
 * akses selalu lewat controller ber-middleware auth.
 */
class LampiranController extends Controller
{
    public function __construct(private readonly GoogleDriveService $drive)
    {
    }

    public function storeForSuratMasuk(StoreLampiranRequest $request, SuratMasuk $surat_masuk): JsonResponse|RedirectResponse
    {
        return $this->simpanLampiran($request, $surat_masuk, 'surat-masuk');
    }

    public function storeForSuratKeluar(StoreLampiranRequest $request, SuratKeluar $surat_keluar): JsonResponse|RedirectResponse
    {
        return $this->simpanLampiran($request, $surat_keluar, 'surat-keluar');
    }

    /**
     * Stream file ke browser. Default inline (preview), `?mode=unduh`
     * memaksa attachment — keputusan F5 (dua-duanya dipakai).
     *
     * Tidak ada cek kepemilikan di sini: route ini sudah `middleware('auth')`,
     * dan L-09/B5/L-10 memutuskan arsip kantor boleh dibaca/DIUNDUH semua yang
     * login tanpa regard siapa pengunggahnya. `LampiranPolicy` lama (yang
     * melarang staf membuka file milik staf lain) dihapus 4 Okt 2026 karena
     * menabrak keputusan itu — bug-nya tertutup saat dites sebagai pegawai,
     * sebelumnya tidak kelihatan karena semua uji jalan sebagai admin.
     */
    public function download(Request $request, Lampiran $lampiran): \Symfony\Component\HttpFoundation\Response
    {
        $disposition = $request->query('mode') === 'unduh' ? 'attachment' : 'inline';

        if ($lampiran->adaDiLokal()) {
            // $disposition di-handle framework (makeDisposition) jadi nama file
            // dengan karakter aneh di-encode benar, bukan disuntik ke header.
            return Storage::disk($lampiran->disk ?? 'arsip')->response(
                $lampiran->path,
                $lampiran->nama_file,
                ['Content-Type' => $lampiran->mime_type ?: 'application/octet-stream'],
                $disposition,
            );
        }

        // Jalur hanya untuk data lama (sebelum L-01) yang file-nya masih di Drive.
        abort_unless(filled($lampiran->google_drive_file_id), 404, 'Berkas arsip ini tidak ditemukan.');

        $file = $this->drive->getFileContent($lampiran->google_drive_file_id);

        return response($file['content'])
            ->header('Content-Type', $file['mime_type'] ?: ($lampiran->mime_type ?: 'application/octet-stream'))
            ->header('Content-Disposition', $disposition.'; filename="'.$this->namaUntukHeader($file['name'] ?: $lampiran->nama_file).'"');
    }

    /**
     * Simpan semua file dari request ke disk lokal, satu baris `lampiran`
     * per file. File yang isinya identik dengan yang sudah ada di surat yang
     * sama dilewati dan dilaporkan balik sebagai duplikat (keputusan I1).
     */
    private function simpanLampiran(StoreLampiranRequest $request, SuratMasuk|SuratKeluar $surat, string $folderJenis): JsonResponse|RedirectResponse
    {
        $primer = $surat->primer;
        $folderJenisKlasifikasi = $folderJenis.'/'.($primer
            ? Str::slug("{$primer->kode} - {$primer->nama}")
            : 'tanpa-klasifikasi').'/'.$surat->id;

        $tersimpan = [];
        $duplikat = [];

        foreach ($request->file('files') as $file) {
            $hash = hash_file('sha256', $file->getRealPath());

            $sudahAda = Lampiran::query()
                ->where('lampiranable_type', $surat::class)
                ->where('lampiranable_id', $surat->id)
                ->where('hash_file', $hash)
                ->exists();

            if ($sudahAda) {
                $duplikat[] = $file->getClientOriginalName();

                continue;
            }

            $path = $file->store($folderJenisKlasifikasi, ['disk' => 'arsip']);

            if (! $path) {
                continue;
            }

            $tersimpan[] = $lampiran = Lampiran::create([
                'lampiranable_id' => $surat->id,
                'lampiranable_type' => $surat::class,
                'disk' => 'arsip',
                'path' => $path,
                'hash_file' => $hash,
                'nama_file' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'ukuran' => $file->getSize(),
                'diunggah_oleh' => $request->user()->id,
            ]);

            unset($lampiran);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'sukses' => count($tersimpan),
                'lampiran' => collect($tersimpan)->map(fn (Lampiran $l) => [
                    'id' => $l->id,
                    'nama_file' => $l->nama_file,
                    'ukuran' => $l->ukuran,
                    'unduh' => route('lampiran.download', $l),
                ]),
                'duplikat' => $duplikat,
            ]);
        }

        $pesan = count($tersimpan) > 0
            ? count($tersimpan).' lampiran berhasil diunggah.'
            : 'Tidak ada file baru yang diunggah.';

        if (count($duplikat) > 0) {
            $pesan .= ' File berikut sudah ada di surat ini, jadi dilewati: '.implode(', ', $duplikat).'.';
        }

        return back()->with('success', $pesan);
    }

    /**
     * Nama file masuk ke HTTP header, jadi karakter berisiko (quote, newline)
     * harus dibuang — bukan cuma soal tampilan.
     */
    private function namaUntukHeader(string $nama): string
    {
        return str_replace(['"', "\r", "\n", '\\'], '', $nama);
    }
}
