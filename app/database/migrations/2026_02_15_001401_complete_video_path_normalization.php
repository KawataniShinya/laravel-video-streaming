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

        // 1. Ensure videos table exists
        if (!Schema::hasTable('videos')) {
            Schema::create('videos', function (Blueprint $table) {
                $table->id();
                $table->string('path')->unique();
                $table->string('hash')->unique()->index();
                $table->string('type');
                $table->timestamps();
            });
        }

        // 2. Backup current data to memory
        $oldViews = DB::table('video_views')->get();
        $oldFavs = DB::table('favorites')->get();

        // 3. Extract and insert unique paths into videos table
        $allPaths = collect();
        foreach ($oldViews as $v) {
            $allPaths->push(['path' => $v->video_path, 'type' => 'file']);
        }
        foreach ($oldFavs as $f) {
            $allPaths->push(['path' => $f->path, 'type' => $f->type]);
        }

        $uniquePaths = $allPaths->filter(fn($i) => !empty($i['path']))->unique('path');
        foreach ($uniquePaths as $item) {
            DB::table('videos')->updateOrInsert(
                ['path' => $item['path']],
                ['hash' => md5($item['path']), 'type' => $item['type'], 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $videos = DB::table('videos')->pluck('id', 'path');

        // 4. DROP and RECREATE tables (Cleanest way to handle indexes/constraints)
        Schema::dropIfExists('video_views');
        Schema::create('video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->integer('last_position')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'video_id']);
        });

        Schema::dropIfExists('favorites');
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();
            $table->unique(['user_id', 'video_id']);
        });

        // 5. Restore data with new mapping
        foreach ($oldViews as $v) {
            $videoId = $videos[$v->video_path] ?? null;
            if ($videoId) {
                DB::table('video_views')->insert([
                    'user_id' => $v->user_id,
                    'video_id' => $videoId,
                    'last_position' => $v->last_position,
                    'created_at' => $v->created_at,
                    'updated_at' => $v->updated_at,
                ]);
            }
        }

        foreach ($oldFavs as $f) {
            $videoId = $videos[$f->path] ?? null;
            if ($videoId) {
                DB::table('favorites')->insert([
                    'user_id' => $f->user_id,
                    'video_id' => $videoId,
                    'type' => $f->type,
                    'created_at' => $f->created_at,
                    'updated_at' => $f->updated_at,
                ]);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        // 戻す際も同様に DROP & RECREATE が安全
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $videos = DB::table('videos')->pluck('path', 'id');
        $normViews = DB::table('video_views')->get();
        $normFavs = DB::table('favorites')->get();

        Schema::dropIfExists('video_views');
        Schema::create('video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('video_path');
            $table->integer('last_position')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'video_path']);
        });

        Schema::dropIfExists('favorites');
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('type');
            $table->timestamps();
            $table->unique(['user_id', 'path']);
        });

        foreach ($normViews as $v) {
            $path = $videos[$v->video_id] ?? null;
            if ($path) {
                DB::table('video_views')->insert([
                    'user_id' => $v->user_id,
                    'video_path' => $path,
                    'last_position' => $v->last_position,
                    'created_at' => $v->created_at,
                    'updated_at' => $v->updated_at,
                ]);
            }
        }

        foreach ($normFavs as $f) {
            $path = $videos[$f->video_id] ?? null;
            if ($path) {
                DB::table('favorites')->insert([
                    'user_id' => $f->user_id,
                    'path' => $path,
                    'type' => $f->type,
                    'created_at' => $f->created_at,
                    'updated_at' => $f->updated_at,
                ]);
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
