<?php

namespace App\Console\Commands;

use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Support\RetensiArsip;
use Illuminate\Console\Command;

/**
 * L-04: sistem TIDAK mengubah status arsip otomatis. Perintah ini hanya
 * menyusun daftar surat yang sudah lewat retensi, supaya staf yang memutuskan
 * mana yang di-nyahkan.
 *
 * Batas tahunnya dibaca dari `RetensiArsip::TAHUN` kalau opsi `--tahun` tidak
 * diisi — angka 5 tahun tidak boleh hidup di tiga tempat (perintah ini, form
 * pemusnahan, dan aturan hapus lampiran) karena salah satunya bisa tertinggal.
 */
class DaftarSuratUsangCommand extends Command
{
    protected $signature = 'arsip:daftar-usang {--tahun= : Batas umur arsip dalam tahun (default: nilai retensi yang berlaku)}';

    protected $description = 'Daftar surat masuk & keluar yang umurnya sudah lewat masa retensi (tidak mengubah apa pun)';

    public function handle(): int
    {
        // Default = angka retensi yang berlaku; `--tahun=N` tetap boleh diisi
        // supaya staf bisa menyaring lebih longgar (E5: keputusan di tangan staf).
        $tahun = $this->option('tahun') !== null
            ? (int) $this->option('tahun')
            : RetensiArsip::TAHUN;

        $batas = RetensiArsip::batas(now(), $tahun);

        $baris = collect([
            SuratMasuk::query()->where('tanggal_surat', '<', $batas)->where('status_arsip', 'aktif')->get()
                ->map(fn ($s) => ['masuk', $s->nomor_surat, (string) $s->tanggal_surat, $s->perihal]),
            SuratKeluar::query()->where('tanggal_surat', '<', $batas)->where('status_arsip', 'aktif')->get()
                ->map(fn ($s) => ['keluar', $s->nomor_surat, (string) $s->tanggal_surat, $s->perihal]),
        ])->flatten(1)->values();

        if ($baris->isEmpty()) {
            $this->info('Tidak ada surat aktif yang umurnya di atas '.$tahun.' tahun.');

            return self::SUCCESS;
        }

        $this->table(['Jenis', 'Nomor Surat', 'Tanggal Surat', 'Perihal'], $baris->all());
        $this->warn("Total {$baris->count()} surat menunggu keputusan staff (nyahkan lewat tombol di halaman surat).");

        return self::SUCCESS;
    }
}
