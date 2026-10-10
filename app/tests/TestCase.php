<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected string $videoTestRoot;
    protected string $hlsTestRoot;
    protected string $viewTestRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = str_replace('\\', '_', static::class);
        $this->videoTestRoot = storage_path('framework/testing/videos/' . $suffix);
        $this->hlsTestRoot = storage_path('framework/testing/hls/' . $suffix);
        $this->viewTestRoot = storage_path('framework/testing/views/' . $suffix);

        File::deleteDirectory($this->videoTestRoot);
        File::deleteDirectory($this->hlsTestRoot);
        File::ensureDirectoryExists($this->videoTestRoot);
        File::ensureDirectoryExists($this->hlsTestRoot);
        File::ensureDirectoryExists($this->viewTestRoot);

        config([
            'video.root' => $this->videoTestRoot,
            'video.hls_cache_path' => $this->hlsTestRoot,
            // CLI tests must not overwrite the web user's compiled Blade files.
            'view.compiled' => $this->viewTestRoot,
        ]);
        $this->app['view.engine.resolver']->forget('blade');
        $this->app->forgetInstance('blade.compiler');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->videoTestRoot);
        File::deleteDirectory($this->hlsTestRoot);
        File::deleteDirectory($this->viewTestRoot);

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

    protected function getOrCreateVideo($path, $type)
    {
        return \App\Models\Video::firstOrCreate(
            ['path' => $path],
            ['hash' => md5($path), 'type' => $type]
        );
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
