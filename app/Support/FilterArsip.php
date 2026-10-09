<?php

namespace App\Support;

use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Satu tempat untuk isi kotak filter daftar arsip (L-14: filter surat masuk dan
 * surat keluar HARUS sama).
 *
 * Kenapa dipindah dari controller: `SuratMasukController::index()` dan
 * `SuratKeluarController::index()` masing-masing menyalin daftar filternya,
 * dan salinan itu sudah terbukti melenceng (yang satu menggali `ringkasan`,
 * yang lain menggali `tembusan` + draf). Mode gabungan "Semua arsip" adalah
 * pemakai ketiga — tanpa kelas ini ia jadi salinan keempat.
 */
class FilterArsip
{
    /**
     * Terapkan semua filter yang ada di layar daftar.
     *
     * @param  Builder<SuratMasuk>|Builder<SuratKeluar>  $query
     * @return Builder<SuratMasuk>|Builder<SuratKeluar>
     */
    public static function terapkan(Builder $query, Request $request): Builder
    {
        $cari = $request->query('cari');

        // scopeCari() milik masing-masing model: multi-kata (AND), lintas kolom,
        // termasuk isi draf surat keluar dan teks hasil baca lampiran (L-15).
        if (filled($cari)) {
            /** @var Builder<SuratMasuk>|Builder<SuratKeluar> $query */
            $query->cari((string) $cari);
        }

        if ($request->filled('sifat')) {
            $query->where('sifat', $request->input('sifat'));
        }

        if ($request->filled('klasifikasi_primer_id')) {
            $query->where('klasifikasi_primer_id', $request->integer('klasifikasi_primer_id'));
        }

        if ($request->filled('status_arsip')) {
            $query->where('status_arsip', $request->input('status_arsip'));
        }

        $tahun = self::tahun($request);

        if ($tahun !== null) {
            // BUKAN whereYear(): ia menghasilkan YEAR(tanggal_surat) = ?, dan
            // fungsi di atas kolom membuat index `tanggal_surat` tidak terpakai.
            //
            // Intervalnya sengaja SETENGAH TERBUKA (1 Jan tahun ini s/d sebelum
            // 1 Jan tahun depan), bukan BETWEEN tanggal sampai 31 Des. BETWEEN
            // dengan batas tanggal murni itu memotong surat tertanggal 31 Desember
            // di SQLite: kolom date-nya menyimpan '2026-12-31 00:00:00' (Eloquent
            // memakai format 'Y-m-d H:i:s' saat menulis), dan string itu lebih
            // besar dari '2026-12-31'. Di MariaDB kolom DATE memang memotong jam,
            // jadi bug-nya cuma muncul di suite tes — interval terbuka benar di
            // keduanya dan tetap memakai index.
            $query->where('tanggal_surat', '>=', sprintf('%04d-01-01', $tahun))
                ->where('tanggal_surat', '<', sprintf('%04d-01-01', $tahun + 1));
        }

        // L-04: hanya MENYEDIAKAN DAFTAR arsip lewat retensi 5 tahun; umur
        // dihitung dari tanggal_surat untuk kedua jenis (L-21).
        if (self::usang($request)) {
            $query->where('tanggal_surat', '<', now()->subYears(5));
        }

        return $query;
    }

    /**
     * Kata kunci aktif, sudah dinormalkan (spasi berlebih dibuang).
     */
    public static function cari(Request $request): ?string
    {
        $frasa = CariArsip::frasa($request->query('cari'));

        return $frasa === '' ? null : $frasa;
    }

    public static function tahun(Request $request): ?int
    {
        $tahun = (int) $request->query('tahun');

        return $tahun > 0 ? $tahun : null;
    }

    public static function usang(Request $request): bool
    {
        return $request->query('usang') === '1';
    }

    /**
     * Mode gabungan diminta? `?jenis=semua` — TIDAK ada route baru: tab ini
     * hidup di dalam kedua halaman daftar yang sudah ada, sesuai alasan
     * halaman `/pencarian` dihapus 5 Okt 2026 (satu kotak cari per daftar).
     */
    public static function gabungan(Request $request): bool
    {
        return $request->query('jenis') === 'semua';
    }
}
