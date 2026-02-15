<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Collect and create master videos
        $allPaths = collect();
        if (Schema::hasTable('video_views_old') && Schema::hasColumn('video_views_old', 'video_path')) {
            $allPaths = $allPaths->concat(DB::table('video_views_old')->select('video_path as path')->distinct()->get()->map(fn($i) => ['path' => $i->path, 'type' => 'file']));
        } elseif (Schema::hasTable('video_views') && Schema::hasColumn('video_views', 'video_path')) {
            $allPaths = $allPaths->concat(DB::table('video_views')->select('video_path as path')->distinct()->get()->map(fn($i) => ['path' => $i->path, 'type' => 'file']));
        }

        if (Schema::hasTable('favorites_old') && Schema::hasColumn('favorites_old', 'path')) {
            $allPaths = $allPaths->concat(DB::table('favorites_old')->select('path', 'type')->distinct()->get()->map(fn($i) => ['path' => $i->path, 'type' => $i->type]));
        } elseif (Schema::hasTable('favorites') && Schema::hasColumn('favorites', 'path')) {
            $allPaths = $allPaths->concat(DB::table('favorites')->select('path', 'type')->distinct()->get()->map(fn($i) => ['path' => $i->path, 'type' => $i->type]));
        }

        $uniquePaths = $allPaths->filter(fn($i) => !empty($i['path']))->unique('path');
        foreach ($uniquePaths as $item) {
            DB::table('videos')->updateOrInsert(
                ['path' => $item['path']],
                ['hash' => md5($item['path']), 'type' => $item['type'], 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // 2. Drop potential incomplete tables
        Schema::dropIfExists('video_views');
        Schema::dropIfExists('favorites');

        // 3. Create tables without foreign key constraints initially to avoid name conflicts
        Schema::create('video_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('video_id');
            $table->integer('last_position')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'video_id']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('video_id');
            $table->string('type');
            $table->timestamps();
            $table->unique(['user_id', 'video_id']);
        });

        // 4. Data Migration
        $videos = DB::table('videos')->pluck('id', 'path');

        if (Schema::hasTable('video_views_old')) {
            $oldViews = DB::table('video_views_old')->get();
            foreach ($oldViews as $view) {
                $path = $view->video_path ?? null;
                $videoId = $path ? ($videos[$path] ?? null) : ($view->video_id ?? null);
                if ($videoId) {
                    DB::table('video_views')->insert([
                        'user_id' => $view->user_id,
                        'video_id' => $videoId,
                        'last_position' => $view->last_position ?? 0,
                        'created_at' => $view->created_at,
                        'updated_at' => $view->updated_at,
                    ]);
                }
            }
        }

        if (Schema::hasTable('favorites_old')) {
            $oldFavs = DB::table('favorites_old')->get();
            foreach ($oldFavs as $fav) {
                $path = $fav->path ?? null;
                $videoId = $path ? ($videos[$path] ?? null) : ($fav->video_id ?? null);
                if ($videoId) {
                    DB::table('favorites')->insert([
                        'user_id' => $fav->user_id,
                        'video_id' => $videoId,
                        'type' => $fav->type,
                        'created_at' => $fav->created_at,
                        'updated_at' => $fav->updated_at,
                    ]);
                }
            }
        }

        // 5. Cleanup old tables (this removes the old constraints)
        Schema::dropIfExists('video_views_old');
        Schema::dropIfExists('favorites_old');

        // 6. NOW add constraints (names will be clean)
        Schema::table('video_views', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('video_id')->references('id')->on('videos')->cascadeOnDelete();
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('video_id')->references('id')->on('videos')->cascadeOnDelete();
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void {}
};
