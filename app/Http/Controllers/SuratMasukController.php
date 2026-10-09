<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MenampilkanArsipGabungan;
use App\Http\Controllers\Concerns\MengelolaArsipSurat;
use App\Http\Requests\StoreSuratMasukRequest;
use App\Http\Requests\UpdateIsiSuratMasukRequest;
use App\Http\Requests\UpdateStatusArsipRequest;
use App\Http\Requests\UpdateSuratMasukRequest;
use App\Models\SuratMasuk;
use App\Support\FilterArsip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
    use MengelolaArsipSurat;

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

        $arsip = SuratMasuk::query()->with(['primer', 'sekunder', 'tersier', 'petugas']);

        if ($melihatSampah) {
            $arsip->onlyTrashed();
        }

        // Isi filter tidak lagi ditulis di sini: FilterArsip dipakai bersama oleh
        // daftar masuk, daftar keluar, dan mode gabungan, supaya ketiganya tidak
        // bisa berbeda kesimpulan (L-14). Pencarian ikut menggali `ringkasan`
        // DAN teks hasil baca lampiran (`isi_hasil_baca`) + nama berkasnya (L-15).
        FilterArsip::terapkan($arsip, $request);

        $cari = FilterArsip::cari($request);

        // Nomor surat yang cocok persis didahulukan; urutan tanggal yang lama
        // tetap berlaku sebagai pemecah seri. Scope ini sengaja dipakai di sini,
        // bukan di dalam helper bersama: `$arsip` masih bertipe konkret
        // Builder<SuratMasuk>, dan di dalam trait helper itu akan menjadi
        // Builder<Model> yang tidak mengenal scope sendiri.
        if ($cari !== null) {
            $arsip->palingRelevan($cari);
        }

        return $this->daftarArsip($request, $arsip, 'surat-masuk', 'tanggal_diterima', $melihatSampah);
    }

    public function create(): View
    {
        return view('surat-masuk.create', [
            'klasifikasiPrimer' => $this->pohonKlasifikasi(),
        ]);
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
        return view('surat-masuk.edit', [
            'suratMasuk' => $surat_masuk,
            'klasifikasiPrimer' => $this->pohonKlasifikasi(),
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
     * L-05: hapus = soft delete, dan hanya admin (L-07). Baris, lampiran, dan
     * file fisiknya tetap ada supaya bisa dipulihkan; pemusnahan sungguhan hanya
     * lewat modul Pemusnahan Arsip + Berita Acara (L-06).
     */
    public function destroy(Request $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        return $this->buangArsip(
            $request,
            $surat_masuk,
            'surat-masuk',
            'Surat masuk dipindahkan ke tempat sampah (masih bisa dipulihkan).',
            'Hanya admin yang boleh menghapus arsip surat.',
        );
    }

    /**
     * L-19 / E8+E9: men-nyahkan arsip = status_arsip jadi "inaktif". Semua user
     * login boleh (L-08: arsip kantor, tanpa kepemilikan) dan observer mencatat
     * perubahannya. Aturannya di `UpdateStatusArsipRequest`, kunci flash 'success'
     * — sejarah bug-nya di trait `MengelolaArsipSurat`.
     */
    public function updateStatusArsip(UpdateStatusArsipRequest $request, SuratMasuk $surat_masuk): RedirectResponse
    {
        return $this->ubahStatusArsip($request, $surat_masuk, 'Surat masuk');
    }

    /**
     * Pulihkan surat dari tempat sampah (admin saja). Paramenya bukan model
     * binding karena surat yang sudah dihapus lunak tidak ikut ditemukan oleh
     * route binding default.
     */
    public function restore(Request $request, int $suratMasuk): RedirectResponse
    {
        return $this->pulihkanArsip(
            $request,
            SuratMasuk::withTrashed()->findOrFail($suratMasuk),
            'surat-masuk',
            'Surat masuk',
        );
    }
}
