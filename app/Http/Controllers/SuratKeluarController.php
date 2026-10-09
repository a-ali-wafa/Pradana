<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MenampilkanArsipGabungan;
use App\Http\Controllers\Concerns\MengelolaArsipSurat;
use App\Http\Requests\StoreSuratKeluarRequest;
use App\Http\Requests\UpdateStatusArsipRequest;
use App\Http\Requests\UpdateSuratKeluarRequest;
use App\Models\SuratKeluar;
use App\Services\NomorSuratKeluarGenerator;
use App\Support\FilterArsip;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CRUD arsip surat keluar.
 *
 * Penomoran delegasi ke NomorSuratKeluarGenerator (WAJIB di dalam transaksi —
 * lihat komentar kelas itu). Cetak PDF ada di CetakSuratKeluarController,
 * isi kontennya di DrafKontenSuratKeluarController, lampiran di
 * LampiranController; logging otomatis lewat SuratKeluarObserver.
 */
class SuratKeluarController extends Controller
{
    use MenampilkanArsipGabungan;
    use MengelolaArsipSurat;

    public function __construct(private readonly NomorSuratKeluarGenerator $nomorSurat)
    {
        $this->middleware('auth');
    }

    /**
     * Daftar surat keluar. Filternya identik dengan daftar surat masuk (L-14)
     * dan semuanya dikerjakan FilterArsip + SuratKeluar::scopeCari: nomor,
     * perihal, penerima/instansi, ringkasan, ISI draf konten + tembusan, dan
     * nama berkas lampiran (L-15). `?sampah=1` (admin saja) = surat terhapus,
     * `?jenis=semua` = urutan gabungan masuk + keluar.
     */
    public function index(Request $request): View
    {
        $melihatSampah = $request->query('sampah') === '1';

        abort_unless(! $melihatSampah || $request->user()->isAdmin(), 403);

        // Lihat SuratMasukController::index() — cabang gabungan dan daftar biasa
        // hidup di trait `MengelolaArsipSurat`, jadi kedua halaman ini tidak bisa
        // lagi berbeda perilaku.
        if (! $melihatSampah && FilterArsip::gabungan($request)) {
            return $this->arsipGabungan($request, 'surat-keluar.index');
        }

        $arsip = SuratKeluar::query()->with(['primer', 'sekunder', 'tersier', 'petugas']);

        if ($melihatSampah) {
            $arsip->onlyTrashed();
        }

        FilterArsip::terapkan($arsip, $request);

        $cari = FilterArsip::cari($request);

        if ($cari !== null) {
            $arsip->palingRelevan($cari);
        }

        // Surat keluar tidak punya `tanggal_diterima` — urutan daftarnya memang
        // tanggal surat, dan itu satu-satunya perbedaan bentuk dengan masuk.
        return $this->daftarArsip($request, $arsip, 'surat-keluar', 'tanggal_surat', $melihatSampah);
    }

    public function create(): View
    {
        return view('surat-keluar.create', [
            'klasifikasiPrimer' => $this->pohonKlasifikasi(),
        ]);
    }

    public function store(StoreSuratKeluarRequest $request): RedirectResponse
    {
        $suratKeluar = $this->simpanDenganNomor($request->validated(), $request->user()->id);

        return redirect()
            ->route('surat-keluar.show', $suratKeluar)
            ->with('success', "Surat keluar {$suratKeluar->nomor_surat} berhasil disimpan.");
    }

    /**
     * Simpan surat keluar dengan nomor yang baru di-generate, dicoba ulang
     * maksimal 3 kali kalau ternyata menabrak unique constraint `nomor_surat`
     * (jaring pengaman di atas lock counter, bukan pengganti lock).
     */
    private function simpanDenganNomor(array $data, int $userId, int $sisaPercobaan = 3): SuratKeluar
    {
        try {
            return DB::transaction(function () use ($data, $userId) {
                $data['nomor_surat'] = $this->nomorSurat->generate(
                    $data['tanggal_surat'],
                    (int) $data['klasifikasi_primer_id'],
                    isset($data['klasifikasi_sekunder_id']) ? (int) $data['klasifikasi_sekunder_id'] : null,
                    isset($data['klasifikasi_tersier_id']) ? (int) $data['klasifikasi_tersier_id'] : null,
                );
                $data['status_arsip'] = $data['status_arsip'] ?? 'aktif';
                $data['user_id'] = $userId;

                return SuratKeluar::create($data);
            });
        } catch (QueryException $e) {
            if ($sisaPercobaan <= 1 || ! $this->nomorSuratTabrakan($e)) {
                throw $e;
            }

            return $this->simpanDenganNomor($data, $userId, $sisaPercobaan - 1);
        }
    }

    /**
     * 1062 = duplicate key (MySQL/MariaDB); SQLite memakai teks error sendiri.
     */
    private function nomorSuratTabrakan(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            || str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'surat_keluar_nomor_surat_unique');
    }

    public function show(SuratKeluar $surat_keluar): View
    {
        // Lihat SuratMasukController::show(): `lampiran.pengajuanHapus` +
        // `lampiran.pengunggah` dibaca per baris lampiran di view, jadi keduanya
        // harus ikut eager load.
        $surat_keluar->load([
            'primer', 'sekunder', 'tersier', 'petugas', 'drafKonten',
            'lampiran.pengajuanHapus', 'lampiran.pengunggah',
        ]);

        return view('surat-keluar.show', ['suratKeluar' => $surat_keluar]);
    }

    public function edit(SuratKeluar $surat_keluar): View
    {
        return view('surat-keluar.edit', [
            'suratKeluar' => $surat_keluar,
            'klasifikasiPrimer' => $this->pohonKlasifikasi(),
        ]);
    }

    public function update(UpdateSuratKeluarRequest $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        $surat_keluar->update($request->validated());

        return redirect()
            ->route('surat-keluar.show', $surat_keluar)
            ->with('success', "Surat keluar {$surat_keluar->nomor_surat} berhasil diperbarui.");
    }

    /**
     * Hapus lunak (L-05): surat tidak pernah hilang sungguhan dari layar ini —
     * lampiran & file fisiknya tetap utuh dan bisa dipulihkan admin. Yang berbeda
     * dari surat masuk cuma kalimat flash-nya, yang menyebut nomor surat.
     */
    public function destroy(Request $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        return $this->buangArsip(
            $request,
            $surat_keluar,
            'surat-keluar',
            "Surat keluar {$surat_keluar->nomor_surat} dipindahkan ke tempat sampah (masih bisa dipulihkan).",
            'Hanya admin yang boleh menghapus arsip surat keluar.',
        );
    }

    /**
     * L-19 / E8+E9: "Nyahkan" = status_arsip inaktif. Semua user login boleh,
     * perubahannya tercatat lewat observer. Aturannya di `UpdateStatusArsipRequest`,
     * flash 'success' — sejarah bug kuncinya di trait `MengelolaArsipSurat`.
     */
    public function updateStatusArsip(UpdateStatusArsipRequest $request, SuratKeluar $surat_keluar): RedirectResponse
    {
        return $this->ubahStatusArsip($request, $surat_keluar, 'Surat keluar');
    }

    public function restore(Request $request, int $suratKeluar): RedirectResponse
    {
        return $this->pulihkanArsip(
            $request,
            SuratKeluar::withTrashed()->findOrFail($suratKeluar),
            'surat-keluar',
            'Surat keluar',
        );
    }
}
