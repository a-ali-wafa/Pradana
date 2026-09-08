<?php

namespace Tests\Unit;

use Tests\TestCase;

class LampiranTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_is_eligible_for_deletion_when_surat_masuk_is_older_than_5_years()
    {
        $surat = new \App\Models\SuratMasuk(['tanggal_diterima' => now()->subYears(6)]);
        $lampiran = new \App\Models\Lampiran();
        
        $lampiran->setRelation('lampiranable', $surat);

        $this->assertTrue($lampiran->isEligibleForDeletion());
    }

    public function test_is_not_eligible_for_deletion_when_surat_keluar_is_newer_than_5_years()
    {
        $surat = new \App\Models\SuratKeluar(['tanggal_surat' => now()->subYears(4)]);
        $lampiran = new \App\Models\Lampiran();
        
        $lampiran->setRelation('lampiranable', $surat);

        $this->assertFalse($lampiran->isEligibleForDeletion());
    }

    public function test_is_not_eligible_when_surat_is_missing()
    {
        $lampiran = new \App\Models\Lampiran();
        $this->assertFalse($lampiran->isEligibleForDeletion());
    }
}
