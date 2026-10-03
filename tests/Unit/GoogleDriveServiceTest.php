<?php

namespace Tests\Unit;

use App\Services\GoogleDriveService;
use Google\Service\Drive;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_get_or_create_folder_uses_cache_lock()
    {
        Cache::shouldReceive('lock')
            ->once()
            ->with('gdrive_folder_create_parent123_test_folder', 10)
            ->andReturnSelf();

        Cache::shouldReceive('block')
            ->once()
            ->with(5, \Mockery::type('Closure'))
            ->andReturn('folder_id_123');

        $driveMock = \Mockery::mock(Drive::class);
        $service = new GoogleDriveService($driveMock);

        $result = $service->getOrCreateFolder('test_folder', 'parent123');
        $this->assertEquals('folder_id_123', $result);
    }
}
