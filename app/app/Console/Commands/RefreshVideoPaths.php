<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Models\UserAllowedPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RefreshVideoPaths extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refresh-video-paths {--dry-run : Only log missing items without deleting anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh video paths by removing database records and HLS cache for non-existent files/folders or redundant VOBs';

    private string $videoRoot = '/videos';
    private string $hlsCachePath;

    public function __construct()
    {
        parent::__construct();
        $this->hlsCachePath = storage_path('hls');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $modeStr = $dryRun ? 'RUNNING IN DRY-RUN MODE - NO DELETIONS' : 'RUNNING IN ACTIVE MODE - DELETIONS WILL BE PERFORMED';
        
        $this->info($modeStr);
        Log::info('[app:refresh-video-paths] Starting cleanup process. ' . $modeStr);
        
        $this->refreshVideos(!$dryRun);
        $this->refreshAllowedPaths(!$dryRun);

        $this->info('Cleanup process completed.');
        Log::info('[app:refresh-video-paths] Cleanup process completed.');
    }

    private function refreshVideos($force)
    {
        $this->newLine();
        $this->info('--- Checking Videos Master Table ---');
        
        $videos = Video::all();
        $count = 0;

        foreach ($videos as $video) {
            $fullPath = $this->videoRoot . '/' . $video->path;
            $filename = basename($video->path);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            $isMissing = !File::exists($fullPath);
            $isRedundantVob = ($ext === 'vob' && strtoupper($filename) !== 'VTS_01_1.VOB');

            if ($isMissing || $isRedundantVob) {
                $count++;
                $reason = $isMissing ? 'MISSING' : 'REDUNDANT VOB';
                $msg = "{$reason}: {$video->path} (Hash: {$video->hash})";
                
                $this->warn($msg);
                Log::warning('[app:refresh-video-paths] ' . $msg);
                
                // Check HLS cache
                $cacheDir = $this->hlsCachePath . '/' . $video->hash;
                if (File::exists($cacheDir)) {
                    $cacheMsg = "  -> HLS Cache found: {$cacheDir}";
                    $this->line($cacheMsg);
                    
                    if ($force) {
                        $this->stopAndRemoveCache($video->hash);
                        $this->info("  -> [DELETED] HLS Cache removed.");
                        Log::info("[app:refresh-video-paths] Deleted HLS Cache: {$cacheDir}");
                    }
                }

                if ($force) {
                    $video->delete();
                    $this->info("  -> [DELETED] Video record removed.");
                    Log::info("[app:refresh-video-paths] Deleted Video record: {$video->path}");
                } else {
                    $this->line("  -> [DRY-RUN] Video record would be removed.");
                }
            }
        }

        $summary = "Checked " . count($videos) . " videos. Found {$count} items to refresh.";
        $this->info($summary);
        Log::info('[app:refresh-video-paths] ' . $summary);
    }

    private function refreshAllowedPaths($force)
    {
        $this->newLine();
        $this->info('--- Checking User Allowed Paths ---');
        
        $allowedPaths = UserAllowedPath::all();
        $count = 0;

        foreach ($allowedPaths as $allowed) {
            // Root access ('') is always valid if it exists
            if ($allowed->path === '') continue;

            $fullPath = $this->videoRoot . '/' . $allowed->path;
            
            if (!File::exists($fullPath)) {
                $count++;
                $msg = "MISSING ALLOWED PATH: {$allowed->path} (User ID: {$allowed->user_id})";
                
                $this->warn($msg);
                Log::warning('[app:refresh-video-paths] ' . $msg);
                
                if ($force) {
                    $allowed->delete();
                    $this->info("  -> [DELETED] Allowed path record removed.");
                    Log::info("[app:refresh-video-paths] Deleted Allowed path: {$allowed->path}");
                } else {
                    $this->line("  -> [DRY-RUN] Allowed path record would be removed.");
                }
            }
        }

        $summary = "Checked " . count($allowedPaths) . " allowed paths. Found {$count} invalid items.";
        $this->info($summary);
        Log::info('[app:refresh-video-paths] ' . $summary);
    }

    private function stopAndRemoveCache($hash)
    {
        $cacheDir = $this->hlsCachePath . '/' . $hash;
        if (File::exists($cacheDir)) {
            $pidFile = $cacheDir . '/ffmpeg.pid';
            if (File::exists($pidFile)) {
                $pid = trim(File::get($pidFile));
                if (is_numeric($pid)) {
                    shell_exec("kill -9 $pid > /dev/null 2>&1");
                }
            }
            File::deleteDirectory($cacheDir);
        }
    }
}
