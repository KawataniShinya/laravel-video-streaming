<?php

namespace App\Repositories\Interfaces;

use App\Models\Video;
use Illuminate\Support\Collection;

interface VideoRepositoryInterface
{
    public function firstOrCreateByPath(string $path, string $type): Video;

    public function findByPath(string $path): ?Video;

    public function all(): Collection;
}
