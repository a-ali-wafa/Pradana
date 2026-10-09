<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MenampilkanArsipGabungan;
use App\Http\Requests\StoreSuratMasukRequest;
use App\Http\Requests\UpdateIsiSuratMasukRequest;
use App\Http\Requests\UpdateSuratMasukRequest;
use App\Models\KlasifikasiPrimer;
use App\Models\SuratMasuk;
use App\Support\FilterArsip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD arsip surat masuk.
 *
 * Penghapusan = soft delete (L-05): arsip tidak bisa dimusnahkan dari layar
 * ini; pemusnahan resmi lewat modul Pemusnahan Arsip + Berita Acara (L-06).
 * Penonaktifan arsip lewat tombol "Nyahkan" / updateStatusArsip (L-19).
 */
class SuratMasukController extends Controller
{
    use MenampilkanArsipGabungan;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request): View
    {
        $melihatSampah = $request->query('sampah') === '1';

        // Hanya admin yang boleh membuka daftar arsip yang sudah dihapus lunak (L-05).
        abort_unless(! $melihatSampah || $request->user()->isAdmin(), 403);

        // Tab "Semua arsip" (`?jenis=semua`, 9 Okt 2026) — urutan gabungan surat
        // masuk + keluar TANPA route baru, sesuai alasan halaman /pencarian
        // dihapus. `?sampah=1` selalu menang: tempat sampah memang per-jenis,
        // dan mencampur surat terhapus ke daftar gabungan membuat arah tombol
        // Pulihkan jadi tidak jelas.
        if (! $melihatSampah && FilterArsip::gabungan($request)) {
            return $this->arsipGabungan($request, 'surat-masuk.index');
        }

        $query = SuratMasuk::query()->with(['primer', 'sekunder', 'tersier', 'petugas']);

        if ($melihatSampah) {
            $query->onlyTrashed();
        }

        // Isi filter tidak lagi ditulis di sini: FilterArsip dipakai bersama oleh
        // daftar masuk, daftar keluar, dan mode gabungan, supaya ketiganya tidak
        // bisa berbeda kesimpulan (L-14). Pencarian ikut menggali `ringkasan`
        // DAN teks hasil baca lampiran (`isi_hasil_baca`) + nama berkasnya (L-15).
        FilterArsip::terapkan($query, $request);

        $cari = FilterArsip::cari($request);

        // Nomor surat yang cocok persis didahulukan; urutan tanggal yang lama
        // tetap berlaku sebagai pemecah seri.
        if ($cari !== null) {
            $query->palingRelevan($cari);
        }

        $arsip = $query->orderByDesc('tanggal_diterima')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('surat-masuk.index', [
            'arsip' => $arsip,
            'klasifikasiPrimer' => KlasifikasiPrimer::orderBy('kode')->get(),
            'melihatSampah' => $melihatSampah,
            'usang' => FilterArsip::usang($request),
            'cari' => $cari,
            'gabung' => false,
        ]);
    }

    public function create(): View
    {
        $klasifikasiPrimer = KlasifikasiPrimer::with('sekunder.tersier')->orderBy('kode')->get();

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
            ->with('success', 'Surat masuk berhasil ditambahkan.');
    }

    public function show(SuratMasuk $surat_masuk): View
    {
        // `lampiran.pengajuanHapus` + `lampiran.pengunggah` ikut dimuat karena
        // kedua hal itu dibaca untuk SETIAP baris lampiran di view; tanpa eager
        // load, halaman show dengan 10 berkas menjalankan 20 query tambahan.
        $surat_masuk->load([
            'primer', 'sekunder', 'tersier', 'petugas',
            'lampiran.pengajuanHapus', 'lampiran.pengunggah',
        ]);

        return view('surat-masuk.show', compact('surat_masuk'));
    }

    public function edit(SuratMasuk $surat_masuk): View
    {
        $klasifikasiPrimer = KlasifikasiPrimer::with('sekunder.tersier')->orderBy('kode')->get();

        return view('surat-masuk.edit', [
            'suratMasuk' => $surat_masuk,
            'klasifikasiPrimer' => $klasifikasiPrimer,
        ]);
    }

    public function update(UpdateSuratMasukRequest $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        $surat_masuk->update($request->validated());

        return redirect()
            ->route('surat-masuk.index')
            ->with('success', 'Surat masuk berhasil diperbarui.');
    }

    /**
     * Menyimpan hasil baca otomatis yang SUDAH diperiksa user (5 Okt 2026).
     *
     * Kolom `isi_terverifikasi_pada` diisi di sini, bukan saat upload: teks dari
     * mesin belum boleh dianggap diverifikasi hanya karena berhasil dibaca.
     * `ringkasan` hanya berubah kalau user mencentang "jadikan ringkasan" —
     * permintaan user jelas bahwa hasilnya ditampilkan untuk divalidasi, bukan
     * langsung menimpa isian orang. Batas 5.000 karakter: kolom `ringkasan`
     * bertipe TEXT (65.535 byte) dan isi hasil baca bisa jauh lebih panjang.
     */
    public function updateIsi(UpdateIsiSuratMasukRequest $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        $teks = $request->validated('isi_hasil_baca');
        $dipotong = false;

        $surat_masuk->isi_hasil_baca = filled($teks) ? $teks : null;
        $surat_masuk->isi_terverifikasi_pada = filled($teks) ? now() : null;

        if (filled($teks) && $request->boolean('jadikan_ringkasan')) {
            $ringkas = trim($teks);

            if (mb_strlen($ringkas) > 5000) {
                $ringkas = Str::limit($ringkas, 5000, ' …');
                $dipotong = true;
            }

            $surat_masuk->ringkasan = $ringkas;
        }

        $surat_masuk->save();

        return back()->with(
            'success',
            filled($teks)
                ? 'Hasil baca isi surat disimpan'
                    .($request->boolean('jadikan_ringkasan')
                        ? ' dan disalin ke ringkasan'.($dipotong ? ' (5.000 karakter pertama)' : '').'.'
                        : '.')
                : 'Hasil baca isi surat dikosongkan.'
        );
    }

    /**
     * L-05: hapus = soft delete, dan hanya admin (L-07). Baris + lampiran +
     * file fisiknya tetap utuh supaya bisa dipulihkan lewat `restore()`.
     */
    public function destroy(Request $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        abort_unless(
            $request->user()->isAdmin(),
            403,
            'Hanya admin yang boleh menghapus arsip surat.'
        );

        // L-05: ini soft delete — UPDATE kolom `deleted_at`, bukan DELETE baris.
        // Karena itu TIDAK dibungkus catch(QueryException): bentuk lama menjanjikan
        // "tidak bisa dihapus karena masih direferensikan data lain", padahal FK
        // restrict tidak pernah tersentuh oleh soft delete — pesan itu tidak bisa
        // muncul, dan kalau muncul berarti kesalahan lain yang justru jadi tersamar.
        // SuratKeluarController::destroy() memang sudah tidak punya catch begitu.
        //
        // Baris, lampiran, dan file fisiknya tetap ada dan bisa dipulihkan admin.
        // Pemusnahan sungguhan hanya lewat modul Pemusnahan Arsip (Berita Acara).
        $surat_masuk->delete();

        return redirect()
            ->route('surat-masuk.index')
            ->with('success', 'Surat masuk dipindahkan ke tempat sampah (masih bisa dipulihkan).');
    }

    /**
     * L-19 / E8+E9: men-nyahkan arsip = status_arsip jadi "inaktif".
     * Tersedia untuk semua user login (arsip kantor, tanpa kepemilikan),
     * dan tiap perubahan tercatat otomatis lewat observer.
     */
    public function updateStatusArsip(Request $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        $data = $request->validate([
            'status_arsip' => ['required', Rule::in(['aktif', 'inaktif'])],
        ]);

        $surat_masuk->update($data);

        return back()->with(
            'status',
            $data['status_arsip'] === 'inaktif'
                ? 'Surat masuk dinonaktifkan sebagai arsip aktif.'
                : 'Surat masuk diaktifkan kembali.'
        );
    }

    /**
     * Pulihkan surat dari tempat sampah (admin saja). Paramenya bukan model
     * binding karena surat yang sudah dihapus lunak tidak ikut ditemukan oleh
     * route binding default.
     */
    public function restore(Request $request, int $suratMasuk): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Hanya admin yang bisa memulihkan arsip.');

        $surat = SuratMasuk::withTrashed()->findOrFail($suratMasuk);
        $surat->restore();

        return redirect()
            ->route('surat-masuk.show', $surat)
            ->with('success', 'Surat masuk dipulihkan dari tempat sampah.');
    }
}
