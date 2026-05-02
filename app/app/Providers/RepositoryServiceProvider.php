<?php

namespace App\Providers;

use App\Repositories\EloquentFavoriteRepository;
use App\Repositories\EloquentUserAllowedPathRepository;
use App\Repositories\EloquentUserRepository;
use App\Repositories\EloquentVideoRepository;
use App\Repositories\EloquentVideoViewRepository;
use App\Repositories\Interfaces\FavoriteRepositoryInterface;
use App\Repositories\Interfaces\UserAllowedPathRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\VideoRepositoryInterface;
use App\Repositories\Interfaces\VideoViewRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VideoRepositoryInterface::class, EloquentVideoRepository::class);
        $this->app->bind(VideoViewRepositoryInterface::class, EloquentVideoViewRepository::class);
        $this->app->bind(FavoriteRepositoryInterface::class, EloquentFavoriteRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(UserAllowedPathRepositoryInterface::class, EloquentUserAllowedPathRepository::class);
    }
}
