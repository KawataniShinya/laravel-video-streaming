<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected string $videoTestRoot;
    protected string $hlsTestRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('\\', '_', static::class);
        $this->videoTestRoot = storage_path('framework/testing/videos/' . $suffix);
        $this->hlsTestRoot = storage_path('framework/testing/hls/' . $suffix);

        File::deleteDirectory($this->videoTestRoot);
        File::deleteDirectory($this->hlsTestRoot);
        File::ensureDirectoryExists($this->videoTestRoot);
        File::ensureDirectoryExists($this->hlsTestRoot);

        config([
            'video.root' => $this->videoTestRoot,
            'video.hls_cache_path' => $this->hlsTestRoot,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->videoTestRoot);
        File::deleteDirectory($this->hlsTestRoot);

        parent::tearDown();
    }

    protected function makeVideoDirectory(string $relativePath = ''): string
    {
        $fullPath = $this->videoFullPath($relativePath);
        File::ensureDirectoryExists($fullPath);

        return $fullPath;
    }

    protected function makeVideoFile(string $relativePath, string $contents = 'video'): string
    {
        $directory = dirname($relativePath);
        if ($directory !== '.') {
            $this->makeVideoDirectory($directory);
        }

        $fullPath = $this->videoFullPath($relativePath);
        File::put($fullPath, $contents);

        return $fullPath;
    }

    protected function makeHlsCache(string $hash, array $files = ['index.m3u8' => '#EXTM3U']): string
    {
        $cacheDir = $this->hlsTestRoot . '/' . $hash;
        File::ensureDirectoryExists($cacheDir);

        foreach ($files as $name => $contents) {
            File::put($cacheDir . '/' . $name, $contents);
        }

        return $cacheDir;
    }

    protected function videoFullPath(string $relativePath = ''): string
    {
        $relativePath = trim($relativePath, '/');

        return $relativePath === ''
            ? $this->videoTestRoot
            : $this->videoTestRoot . '/' . $relativePath;
    }
}
