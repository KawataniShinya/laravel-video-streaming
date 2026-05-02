<?php

namespace Tests\Unit\Services\Video;

use App\Services\Video\VideoPathService;
use Tests\TestCase;

class VideoPathServiceTest extends TestCase
{
    public function test_resolve_path_and_breadcrumbs_for_nested_directories(): void
    {
        $this->makeVideoDirectory('VIVANT/vol_01');

        $service = app(VideoPathService::class);

        $resolved = $service->resolvePath('VIVANT/vol_01');
        $this->assertNotNull($resolved);
        $this->assertStringEndsWith('VIVANT/vol_01', $resolved);

        $breadcrumbs = $service->breadcrumbs('VIVANT/vol_01');
        $this->assertCount(2, $breadcrumbs);
        $this->assertSame('VIVANT', $breadcrumbs[0]->name);
        $this->assertSame('VIVANT', $breadcrumbs[0]->path);
        $this->assertSame('vol_01', $breadcrumbs[1]->name);
        $this->assertSame('VIVANT/vol_01', $breadcrumbs[1]->path);
    }

    public function test_normalize_primary_vob_path_redirects_to_main_entry(): void
    {
        $this->makeVideoDirectory('VIVANT/vol_01');
        $this->makeVideoFile('VIVANT/vol_01/VTS_01_1.VOB');
        $this->makeVideoFile('VIVANT/vol_01/VTS_01_2.VOB');

        $service = app(VideoPathService::class);

        $this->assertSame(
            'VIVANT/vol_01/VTS_01_1.VOB',
            $service->normalizePrimaryVobPath('VIVANT/vol_01/VTS_01_2.VOB')
        );
    }

    public function test_parent_path_is_resolved_from_video_path_value_object(): void
    {
        $service = app(VideoPathService::class);

        $this->assertSame('VIVANT/vol_01', $service->parentPath('VIVANT/vol_01/VTS_01_1.VOB'));
    }
}
