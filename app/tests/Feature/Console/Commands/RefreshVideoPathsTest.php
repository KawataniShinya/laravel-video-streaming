<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RefreshVideoPathsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_orphan_cache_directories(): void
    {
        // 1. Create a valid cache (with DB record AND actual file)
        $relativePath = 'movies/valid.avi';
        $this->makeVideoFile($relativePath);
        $video = Video::create([
            'path' => $relativePath,
            'hash' => md5($relativePath),
            'type' => 'file'
        ]);
        $validCacheDir = $this->makeHlsCache($video->hash);

        // 2. Create an orphan cache (no DB record)
        $orphanHash = 'orphan-hash-123';
        $orphanCacheDir = $this->makeHlsCache($orphanHash);

        $this->assertDirectoryExists($validCacheDir);
        $this->assertDirectoryExists($orphanCacheDir);

        // 3. Run the refresh command
        $this->artisan('app:refresh-video-paths')
            ->expectsOutput('RUNNING IN ACTIVE MODE - DELETIONS WILL BE PERFORMED')
            ->expectsOutput('--- Checking Orphan HLS Caches ---')
            ->assertExitCode(0);

        // 4. Verify results
        $this->assertDirectoryExists($validCacheDir);
        $this->assertDirectoryDoesNotExist($orphanCacheDir);
    }

    public function test_it_respects_dry_run_mode_for_orphan_caches(): void
    {
        $orphanHash = 'dry-run-orphan';
        $orphanCacheDir = $this->makeHlsCache($orphanHash);

        $this->artisan('app:refresh-video-paths', ['--dry-run' => true])
            ->expectsOutput('RUNNING IN DRY-RUN MODE - NO DELETIONS')
            ->expectsOutput('ORPHAN CACHE: ' . $orphanHash . ' (No matching record in database)')
            ->assertExitCode(0);

        $this->assertDirectoryExists($orphanCacheDir);
    }
}
