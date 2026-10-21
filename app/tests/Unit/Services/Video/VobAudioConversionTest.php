<?php

namespace Tests\Unit\Services\Video;

use App\Services\Video\VideoCacheService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class VobAudioConversionTest extends TestCase
{
    public function test_split_ac3_headers_do_not_produce_audio_timestamp_gaps_in_hls(): void
    {
        if (!Process::run('ffmpeg -version')->successful() || !Process::run('ffprobe -version')->successful()) {
            $this->markTestSkipped('FFmpeg and ffprobe are required for the conversion test.');
        }

        $input = $this->makeSplitHeaderVob();
        $sourcePackets = $this->audioPackets($input);
        $this->assertGreaterThan(0.03, $this->largestTimestampGap($sourcePackets, 1536 / 48000), 'The fixture must reproduce DVD timestamp jitter.');

        $cache = new VideoCacheService();
        $hash = 'split-ac3-header';
        $cache->ensure($input, $hash);
        $deadline = microtime(true) + 30;
        do {
            usleep(100000);
            $complete = File::exists($cache->playlistPath($hash)) && !File::exists($cache->lockPath($hash));
        } while (!$complete && microtime(true) < $deadline);

        $log = File::get($cache->logPath($hash));
        $this->assertTrue($complete, $log);
        $this->assertStringNotContainsString('Queue input is backward in time', $log);
        $this->assertStringNotContainsString('Non-monoton', $log);
        $playlist = $cache->cacheDir($hash) . '/pTrack1.m3u8';
        $this->assertStringContainsString('#EXT-X-ENDLIST', File::get($playlist));
        $this->assertGreaterThanOrEqual(3, substr_count(File::get($playlist), '#EXTINF:'));

        // Inspect all audio packets, including transitions between HLS segments.
        $packets = [];
        foreach (preg_split('/\R/', File::get($playlist)) as $line) {
            if ($line !== '' && !str_starts_with($line, '#')) {
                $packets = array_merge($packets, $this->audioPackets($cache->cacheDir($hash) . '/' . $line));
            }
        }
        $this->assertGreaterThan(900, count($packets));
        $this->assertLessThan(0.0001, $this->largestTimestampGap($packets, 1024 / 44100));
        $duration = (float) end($packets)['pts_time'] + 1024 / 44100 - (float) $packets[0]['pts_time'];
        // AAC adds encoder priming and final frame padding; allow two AAC frames.
        $sourceDuration = count($sourcePackets) * 1536 / 48000;
        $this->assertEqualsWithDelta($sourceDuration, $duration, 2 * 1024 / 44100 + 0.0001);
    }

    private function makeSplitHeaderVob(): string
    {
        $video = $this->videoFullPath('video.mpg');
        $audio = $this->videoFullPath('audio.ac3');
        $result = Process::run(
            'ffmpeg -v error -f lavfi -i testsrc2=size=160x96:rate=10 -t 22 '
            . '-c:v mpeg2video -f vob ' . escapeshellarg($video)
        );
        $this->assertTrue($result->successful(), $result->errorOutput());
        $result = Process::run(
            'ffmpeg -v error -f lavfi -i sine=frequency=440:sample_rate=48000 -t 22 '
            . '-ac 2 -c:a ac3 -b:a 448k -f ac3 ' . escapeshellarg($audio)
        );
        $this->assertTrue($result->successful(), $result->errorOutput());
        $probe = Process::run('ffprobe -v error -select_streams v:0 -show_entries stream=start_pts -of json ' . escapeshellarg($video));
        $this->assertTrue($probe->successful(), $probe->errorOutput());
        $startPts = json_decode($probe->output(), true, flags: JSON_THROW_ON_ERROR)['streams'][0]['start_pts'];

        $videoData = File::get($video);
        $audioPes = [];
        $samples = File::get($audio);
        $offset = 0;
        $payloadSize = 1123;
        while ($offset < strlen($samples)) {
            $payload = substr($samples, $offset, $payloadSize);
            // A 448 kb/s, 48 kHz AC-3 frame is 1792 bytes and lasts 2880 ticks.
            // This DVD packet layout leaves three bytes of an AC-3 header at a
            // PES boundary, reproducing the original VOB's parser timestamp error.
            $firstFrame = (int) ceil($offset / 1792);
            $frameCount = (int) ceil(($offset + strlen($payload)) / 1792) - $firstFrame;
            $firstAccessUnit = $firstFrame * 1792 - $offset + 1;
            $pts = $startPts + $firstFrame * 2880;
            $timestamp = pack('C5',
                0x21 | (($pts >> 29) & 0x0e), ($pts >> 22) & 0xff,
                1 | (($pts >> 14) & 0xfe), ($pts >> 7) & 0xff, 1 | (($pts << 1) & 0xfe)
            );
            $pes = "\x80\x80\x05" . $timestamp . pack('CCn', 0x80, $frameCount, $firstAccessUnit) . $payload;
            $audioPes[] = "\x00\x00\x01\xbd" . pack('n', strlen($pes)) . $pes;
            $offset += strlen($payload);
            $payloadSize = 2016;
        }

        // Keep audio interleaved with the video's 2048-byte program stream packs.
        $output = '';
        $audioIndex = 0;
        $videoPackCount = (int) ceil(strlen($videoData) / 2048);
        for ($i = 0; $i < $videoPackCount; $i++) {
            $output .= substr($videoData, $i * 2048, 2048);
            $audioEnd = (int) ceil(($i + 1) * count($audioPes) / $videoPackCount);
            while ($audioIndex < $audioEnd) {
                $output .= $audioPes[$audioIndex++];
            }
        }

        return $this->makeVideoFile('split-header.vob', $output);
    }

    private function audioPackets(string $path): array
    {
        $result = Process::run(
            'ffprobe -v error -analyzeduration 100M -probesize 100M -select_streams a:0 '
            . '-show_packets -show_entries packet=pts_time -of json ' . escapeshellarg($path)
        );
        $this->assertTrue($result->successful(), $result->errorOutput());

        return json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR)['packets'];
    }

    private function largestTimestampGap(array $packets, float $frameDuration): float
    {
        $largest = 0.0;
        for ($i = 1; $i < count($packets); $i++) {
            $expected = (float) $packets[$i - 1]['pts_time'] + $frameDuration;
            $largest = max($largest, abs((float) $packets[$i]['pts_time'] - $expected));
        }

        return $largest;
    }
}
