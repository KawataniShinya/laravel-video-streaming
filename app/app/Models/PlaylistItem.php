<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaylistItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['video_id', 'position'];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
