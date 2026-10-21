<?php

namespace Tests\Unit\Services\Video;

use App\Services\Video\VideoCacheService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class VideoCacheServiceTest extends TestCase
{
    private VideoCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VideoCacheService();
    }

    public function test_m2ts_keeps_all_audio_tracks_and_safe_language_codes(): void
    {
        Process::fake([
            'ffprobe *-select_streams a*' => Process::result(json_encode(['streams' => [
                ['index' => 1, 'tags' => ['language' => 'eng']],
                ['index' => 4, 'tags' => ['language' => 'jpn']],
                ['index' => 7, 'tags' => ['language' => 'eng; touch /tmp/unwanted']],
            ]])),
            '*' => Process::result(),
        ]);
        $input = $this->makeVideoFile("日本語 ディスク/本編's.m2ts");

        $this->service->ensure($input, 'multi-audio');

        $command = $this->conversionCommand();
        foreach ([0, 1, 2] as $i) {
            $this->assertStringContainsString("-map 0:a:$i", $command);
        }
        $this->assertStringContainsString('a:0,agroup:aud,name:Track1,default:yes,language:eng', $command);
        $this->assertStringContainsString('a:1,agroup:aud,name:Track2,default:no,language:jpn', $command);
        $this->assertStringContainsString('a:2,agroup:aud,name:Track3,default:no', $command);
        $this->assertStringNotContainsString('unwanted', $command);
        $this->assertStringNotContainsString('-map 0:a:4', $command);
        $this->assertStringContainsString('-c:a aac -ac 2 -ar 44100', $command);
    }

    public function test_m2ts_with_one_audio_track_or_no_audio_is_supported(): void
    {
        foreach ([0, 1] as $count) {
            Process::fake([
                'ffprobe *-select_streams a*' => Process::result(json_encode([
                    'streams' => $count ? [['index' => 1]] : [],
                ])),
                '*' => Process::result(),
            ]);
            $this->service->ensure($this->makeVideoFile('single.m2ts'), 'audio-count-' . $count);
            $command = $this->conversionCommand();
            if ($count === 0) {
                $this->assertStringNotContainsString('agroup:', $command);
                $this->assertStringNotContainsString('-map 0:a:', $command);
                $this->assertStringNotContainsString('-c:a aac', $command);
            } else {
                $this->assertStringContainsString('-map 0:a:0', $command);
                $this->assertStringNotContainsString('-map 0:a:1', $command);
                $this->assertStringNotContainsString('language:', $command);
            }
        }
    }

    public function test_failed_or_invalid_m2ts_probe_does_not_start_a_partial_conversion(): void
    {
        foreach ([Process::result('', 'probe failed', 1), Process::result('{}')] as $result) {
            Process::fake(['ffprobe *-select_streams a*' => $result, '*' => Process::result()]);
            try {
                $this->service->ensure($this->makeVideoFile('invalid.m2ts'), 'invalid-probe');
                $this->fail('Audio probe failure should stop conversion.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('M2TS audio', $error->getMessage());
            }
            Process::assertNotRan(fn ($process) => str_starts_with($process->command, 'nohup '));
        }
    }

    public function test_existing_m2ts_cache_is_preserved_until_manually_deleted(): void
    {
        Process::fake();
        $this->makeHlsCache('existing', ['index.m3u8' => '#EXTM3U']);

        $this->service->ensure($this->makeVideoFile('existing.m2ts'), 'existing');

        Process::assertNothingRan();
        $this->assertSame('#EXTM3U', File::get($this->service->playlistPath('existing')));
    }

    public function test_vob_and_avi_keep_their_existing_audio_limits(): void
    {
        foreach (['vob' => 2, 'avi' => 1] as $extension => $count) {
            Process::fake(['ffmpeg *' => Process::result("3\n"), '*' => Process::result()]);
            $this->service->ensure($this->makeVideoFile('movie.' . $extension), $extension);
            $command = $this->conversionCommand();
            for ($i = 0; $i < $count; $i++) {
                $this->assertStringContainsString("-map 0:a:$i", $command);
            }
            $this->assertStringNotContainsString("-map 0:a:$count", $command);
        }
    }

    public function test_vob_audio_timestamps_are_corrected_before_aac_encoding(): void
    {
        Process::fake(['ffmpeg *' => Process::result("2\n"), '*' => Process::result()]);
        $this->service->ensure($this->makeVideoFile('disc/VTS_01_1.VOB'), 'dvd-sync');

        $command = $this->conversionCommand();
        $this->assertStringContainsString('aresample=async=1', $command);
        $this->assertStringContainsString('-map 0:a:0 -map 0:a:1', $command);
        $this->assertStringNotContainsString('first_pts=0', $command);
    }

    public function test_audio_timestamp_correction_is_only_used_for_vob_with_audio(): void
    {
        foreach (['vob' => 0, 'avi' => 1, 'm2ts' => 1] as $extension => $count) {
            Process::fake([
                'ffprobe *-select_streams a*' => Process::result(json_encode(['streams' => [['index' => 1]]])),
                'ffmpeg *' => Process::result("$count\n"),
                '*' => Process::result(),
            ]);
            $this->service->ensure($this->makeVideoFile('no-filter.' . $extension), 'no-filter-' . $extension);
            $this->assertStringNotContainsString('-af ', $this->conversionCommand());
        }
    }

    private function conversionCommand(): string
    {
        $command = '';
        Process::assertRan(function ($process) use (&$command) {
            if (!str_starts_with($process->command, 'nohup ')) {
                return false;
            }
            $command = $process->command;
            return true;
        });

        return $command;
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
