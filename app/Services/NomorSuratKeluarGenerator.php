<?php

namespace App\Services;

use App\Models\KlasifikasiPrimer;
use App\Models\KlasifikasiSekunder;
use App\Models\KlasifikasiTersier;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat nomor surat keluar dibentuk (format D1 [LOCKED] di
 * AGENTS.md Bagian 8 #17):
 *
 *   {urutan 3 digit}/{kodeP.kodeS.kodeT}/{bulan romawi}/{tahun}  -> 001/01.01.01/IX/2026
 *
 * WAJIB dipanggil di dalam DB::transaction(). Alasannya bukan gaya-gayaan:
 * urutan diambil dari tabel `surat_counters` dengan upsert lalu dibaca pakai
 * lockForUpdate(). SELECT biasa membaca snapshot transaksi, sehingga dua
 * request yang datang bersamaan bisa melihat angka yang sama dan memakai
 * nomor yang sama untuk dua surat resmi berbeda. Locking read memaksa request
 * kedua menunggu sampai transaksi pertama commit.
 *
 * Kelas ini berdiri sendiri (bukan method controller) supaya jalur paling
 * kritis dari arsip bisa dites dengan proses paralel sungguhan — lihat
 * tests/Feature/NomorSuratKeluarTest.php.
 */
class NomorSuratKeluarGenerator
{
    private const BULAN_ROMAWI = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function generate(
        CarbonInterface|string $tanggalSurat,
        int $klasifikasiPrimerId,
        ?int $klasifikasiSekunderId = null,
        ?int $klasifikasiTersierId = null,
    ): string {
        $tanggal = Carbon::parse($tanggalSurat);
        $tahun = (int) $tanggal->year;
        $bulan = (int) $tanggal->month;

        $kode = collect([
            KlasifikasiPrimer::find($klasifikasiPrimerId)?->kode,
            $klasifikasiSekunderId ? KlasifikasiSekunder::find($klasifikasiSekunderId)?->kode : null,
            $klasifikasiTersierId ? KlasifikasiTersier::find($klasifikasiTersierId)?->kode : null,
        ])->filter()->implode('.');

        DB::table('surat_counters')->upsert(
            ['jenis_surat' => 'surat_keluar', 'tahun' => $tahun, 'current_value' => 1],
            ['jenis_surat', 'tahun'],
            ['current_value' => DB::raw('current_value + 1')]
        );

        $counter = DB::table('surat_counters')
            ->where('jenis_surat', 'surat_keluar')
            ->where('tahun', $tahun)
            ->lockForUpdate()
            ->first();

        return sprintf(
            '%03d/%s/%s/%d',
            $counter->current_value,
            $kode,
            self::BULAN_ROMAWI[$bulan] ?? (string) $bulan,
            $tahun,
        );
    }
}
