<?php

namespace Tests\Unit;

use Tests\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_get_or_create_folder_uses_cache_lock()
    {
        \Illuminate\Support\Facades\Cache::shouldReceive('lock')
            ->once()
            ->with('gdrive_folder_create_parent123_test_folder', 10)
            ->andReturnSelf();
            
        \Illuminate\Support\Facades\Cache::shouldReceive('block')
            ->once()
            ->with(5, \Mockery::type('Closure'))
            ->andReturn('folder_id_123');

        $driveMock = \Mockery::mock(\Google\Service\Drive::class);
        $service = new \App\Services\GoogleDriveService($driveMock);
        
        $result = $service->getOrCreateFolder('test_folder', 'parent123');
        $this->assertEquals('folder_id_123', $result);
    }
}
