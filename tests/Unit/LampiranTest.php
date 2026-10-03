<?php

namespace Tests\Unit;

use App\Models\Lampiran;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Tests\TestCase;

/**
 * Aturan retensi 5 tahun (L-04/L-21). Sejak L-21 umur arsip untuk KEDUA jenis
 * surat diukur dari `tanggal_surat` — sebelumnya surat masuk memakai
 * `tanggal_diterima`, sehingga satu aturan punya dua dasar hitung.
 */
class LampiranTest extends TestCase
{
    private function lampiranUntuk($surat): Lampiran
    {
        $lampiran = new Lampiran;
        $lampiran->setRelation('lampiranable', $surat);

        return $lampiran;
    }

    public function test_surat_masuk_dinuksi_umurnya_dari_tanggal_surat(): void
    {
        $surat = new SuratMasuk([
            'tanggal_surat' => now()->subYears(6),
            'tanggal_diterima' => now(), // sengaja baru: tidak boleh dipakai sebagai acuan
        ]);

        $this->assertTrue($this->lampiranUntuk($surat)->isEligibleForDeletion());
    }

    public function test_surat_masuk_baru_belum_boleh_dihapus(): void
    {
        $surat = new SuratMasuk(['tanggal_surat' => now()->subYears(2)]);

        $this->assertFalse($this->lampiranUntuk($surat)->isEligibleForDeletion());
    }

    public function test_surat_keluar_dinuksi_umurnya_dari_tanggal_surat(): void
    {
        $surat = new SuratKeluar(['tanggal_surat' => now()->subYears(6)]);

        $this->assertTrue($this->lampiranUntuk($surat)->isEligibleForDeletion());
    }

    public function test_tidak_eligible_kalau_surat_induk_hilang(): void
    {
        $this->assertFalse((new Lampiran)->isEligibleForDeletion());
    }

    public function test_tidak_eligible_kalau_tanggal_surat_kosong(): void
    {
        $this->assertFalse($this->lampiranUntuk(new SuratMasuk)->isEligibleForDeletion());
    }
}
