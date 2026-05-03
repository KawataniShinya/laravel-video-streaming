<?php

namespace Tests\Unit\Services\Video;

use App\Services\Video\VideoCacheService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VideoCacheServiceTest extends TestCase
{
    private VideoCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VideoCacheService();
    }

    public function test_progress_returns_null_if_files_missing(): void
    {
        $hash = 'test-hash';
        $this->assertNull($this->service->progress($hash));
    }

    public function test_progress_calculates_correct_percentage(): void
    {
        $hash = 'test-hash';
        $cacheDir = $this->hlsTestRoot . '/' . $hash;
        File::ensureDirectoryExists($cacheDir);

        // Set total duration to 100 seconds
        File::put($cacheDir . '/total_duration.txt', '100.0');

        // Simulate ffmpeg log at 45.5 seconds
        $logContent = "frame= 1234 fps= 30 q=20.0 size= 100kB time=00:00:45.50 bitrate=100.0kbits/s speed=1.0x";
        File::put($cacheDir . '/ffmpeg.log', $logContent);

        $this->assertEquals(45.5, $this->service->progress($hash));
    }

    public function test_progress_handles_multi_hour_logs(): void
    {
        $hash = 'test-hash';
        $cacheDir = $this->hlsTestRoot . '/' . $hash;
        File::ensureDirectoryExists($cacheDir);

        // Total 2 hours (7200 seconds)
        File::put($cacheDir . '/total_duration.txt', '7200.0');

        // Progress at 1 hour 30 minutes (5400 seconds) -> 75%
        $logContent = "time=01:30:00.00";
        File::put($cacheDir . '/ffmpeg.log', $logContent);

        $this->assertEquals(75.0, $this->service->progress($hash));
    }

    public function test_progress_caps_at_100_percent(): void
    {
        $hash = 'test-hash';
        $cacheDir = $this->hlsTestRoot . '/' . $hash;
        File::ensureDirectoryExists($cacheDir);

        File::put($cacheDir . '/total_duration.txt', '10.0');
        File::put($cacheDir . '/ffmpeg.log', "time=00:00:15.00");

        $this->assertEquals(100.0, $this->service->progress($hash));
    }
}
