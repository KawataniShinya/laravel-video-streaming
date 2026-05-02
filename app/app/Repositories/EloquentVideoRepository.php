<?php

namespace App\Repositories;

use App\Models\Video;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentVideoRepository implements VideoRepositoryInterface
{
    public function firstOrCreateByPath(string $path, string $type): Video
    {
        return Video::firstOrCreate(
            ['path' => $path],
            ['hash' => md5($path), 'type' => $type]
        );
    }

    public function findByPath(string $path): ?Video
    {
        return Video::where('path', $path)->first();
    }

    public function all(): Collection
    {
        return Video::all();
    }
}
