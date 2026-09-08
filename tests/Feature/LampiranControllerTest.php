<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LampiranControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_cannot_download_lampiran_unauthorized()
    {
        $user = \App\Models\User::forceCreate(['nama_lengkap' => 'A', 'role' => 'perangkat', 'pin' => '123', 'email' => 'a@b.com']);
        $otherUser = \App\Models\User::forceCreate(['nama_lengkap' => 'B', 'role' => 'perangkat', 'pin' => '123', 'email' => 'b@c.com']);
        $klasPrimer = \App\Models\KlasifikasiPrimer::forceCreate(['kode' => 'A', 'nama' => 'A']);
        
        $surat = \App\Models\SuratMasuk::forceCreate([
            'user_id' => $otherUser->id,
            'pengirim' => 'X',
            'klasifikasi_primer_id' => $klasPrimer->id,
            'nomor_surat' => '123',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'perihal' => 'X',
        ]);
        
        $lampiran = \App\Models\Lampiran::forceCreate([
            'lampiranable_id' => $surat->id,
            'lampiranable_type' => $surat->getMorphClass(),
            'google_drive_file_id' => '123',
            'google_drive_folder_id' => '123',
            'nama_file' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'ukuran' => 100,
            'diunggah_oleh' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)->get(route('lampiran.download', $lampiran));
        $response->assertForbidden();
    }

    public function test_can_download_lampiran_if_uploader()
    {
        $user = \App\Models\User::forceCreate(['nama_lengkap' => 'A', 'role' => 'perangkat', 'pin' => '123', 'email' => 'a2@b.com']);
        $klasPrimer = \App\Models\KlasifikasiPrimer::forceCreate(['kode' => 'A', 'nama' => 'A']);
        
        $surat = \App\Models\SuratMasuk::forceCreate([
            'user_id' => $user->id,
            'pengirim' => 'X',
            'klasifikasi_primer_id' => $klasPrimer->id,
            'nomor_surat' => '123',
            'tanggal_surat' => now(),
            'tanggal_diterima' => now(),
            'perihal' => 'X',
        ]);
        
        $lampiran = \App\Models\Lampiran::forceCreate([
            'lampiranable_id' => $surat->id,
            'lampiranable_type' => $surat->getMorphClass(),
            'google_drive_file_id' => '123',
            'google_drive_folder_id' => '123',
            'nama_file' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'ukuran' => 100,
            'diunggah_oleh' => $user->id,
        ]);

        $this->mock(\App\Services\GoogleDriveService::class, function ($mock) {
            $mock->shouldReceive('getFileContent')->andReturn(['content' => 'abc', 'mime_type' => 'application/pdf', 'name' => 'test.pdf']);
        });

        $response = $this->actingAs($user)->get(route('lampiran.download', $lampiran));
        $response->assertOk();
    }
}
