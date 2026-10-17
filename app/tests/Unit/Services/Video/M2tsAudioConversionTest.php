<?php

namespace Tests\Unit\Services\Video;

use App\Services\Video\VideoCacheService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class M2tsAudioConversionTest extends TestCase
{
    public function test_generated_m2ts_preserves_three_distinct_audio_tracks_in_hls(): void
    {
        if (!Process::run('ffmpeg -version')->successful() || !Process::run('ffprobe -version')->successful()) {
            $this->markTestSkipped('FFmpeg and ffprobe are required for the conversion test.');
        }

        $this->makeVideoDirectory('disc');
        $input = $this->videoFullPath('disc/00001.m2ts');
        $fixture = Process::run(
            'ffmpeg -v error -f lavfi -i testsrc2=size=160x90:rate=10 '
            . '-f lavfi -i sine=frequency=440:sample_rate=48000 '
            . '-f lavfi -i sine=frequency=880:sample_rate=48000 '
            . '-f lavfi -i sine=frequency=1320:sample_rate=48000 '
            . '-map 0:v -map 1:a -map 2:a -map 3:a -t 2 '
            . '-c:v libx264 -preset ultrafast -c:a ac3 '
            . '-metadata:s:a:0 language=eng -metadata:s:a:1 language=jpn -metadata:s:a:2 language=fra '
            . '-f mpegts -mpegts_m2ts_mode 1 ' . escapeshellarg($input)
        );
        $this->assertTrue($fixture->successful(), $fixture->errorOutput());

        $link = $this->videoFullPath('本編.m2ts');
        symlink('disc/00001.m2ts', $link);
        $cache = new VideoCacheService();
        $hash = md5('本編.m2ts');
        $cache->ensure($link, $hash);

        $deadline = microtime(true) + 30;
        do {
            usleep(100000);
            $complete = File::exists($cache->playlistPath($hash))
                && File::exists($cache->cacheDir($hash) . '/pTrack3.m3u8')
                && !File::exists($cache->lockPath($hash));
        } while (!$complete && microtime(true) < $deadline);

        $this->assertTrue($complete, File::get($cache->logPath($hash)));
        $master = File::get($cache->playlistPath($hash));
        $this->assertSame(3, substr_count($master, '#EXT-X-MEDIA:TYPE=AUDIO'));
        foreach (['eng', 'jpn', 'fra'] as $language) {
            $this->assertStringContainsString('LANGUAGE="' . $language . '"', $master);
        }
        $this->assertSame(1, substr_count($master, 'DEFAULT=YES'));

        $checksums = [];
        foreach ([1, 2, 3] as $track) {
            $playlist = $cache->cacheDir($hash) . "/pTrack$track.m3u8";
            $this->assertStringContainsString('#EXT-X-ENDLIST', File::get($playlist));
            $decoded = Process::run('ffmpeg -v error -i ' . escapeshellarg($playlist) . ' -map 0:a:0 -f md5 -');
            $this->assertTrue($decoded->successful(), $decoded->errorOutput());
            $this->assertStringStartsWith('MD5=', $decoded->output());
            $checksums[] = trim($decoded->output());
        }
        $this->assertCount(3, array_unique($checksums), 'Each selectable track must contain its own audio.');
    }
}
