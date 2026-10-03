<?php

namespace App\Console\Commands;

use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Illuminate\Console\Command;

/**
 * L-04: sistem TIDAK mengubah status arsip otomatis. Perintah ini hanya
 * menyusun daftar surat yang sudah lewat retensi 5 tahun, supaya staf yang
 * memutuskan mana yang di-nyahkan.
 */
class DaftarSuratUsangCommand extends Command
{
    protected $signature = 'arsip:daftar-usang {--tahun=5 : Batas umur arsip dalam tahun}';

    protected $description = 'Daftar surat masuk & keluar yang umurnya sudah lewat masa retensi (tidak mengubah apa pun)';

    public function handle(): int
    {
        $batas = now()->subYears((int) $this->option('tahun'));

        $baris = collect([
            SuratMasuk::query()->where('tanggal_surat', '<', $batas)->where('status_arsip', 'aktif')->get()
                ->map(fn ($s) => ['masuk', $s->nomor_surat, (string) $s->tanggal_surat, $s->perihal]),
            SuratKeluar::query()->where('tanggal_surat', '<', $batas)->where('status_arsip', 'aktif')->get()
                ->map(fn ($s) => ['keluar', $s->nomor_surat, (string) $s->tanggal_surat, $s->perihal]),
        ])->flatten(1)->values();

        if ($baris->isEmpty()) {
            $this->info('Tidak ada surat aktif yang umurnya di atas '.$this->option('tahun').' tahun.');

            return self::SUCCESS;
        }

        $this->table(['Jenis', 'Nomor Surat', 'Tanggal Surat', 'Perihal'], $baris->all());
        $this->warn("Total {$baris->count()} surat menunggu keputusan staff (nyahkan lewat tombol di halaman surat).");

        return self::SUCCESS;
    }
}
