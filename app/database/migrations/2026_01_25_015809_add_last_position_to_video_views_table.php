<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('video_views', function (Blueprint $table) {
            $table->integer('last_position')->default(0)->after('video_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_views', function (Blueprint $table) {
            $table->dropColumn('last_position');
        });
    }
};
