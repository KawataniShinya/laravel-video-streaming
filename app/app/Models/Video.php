<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = ['path', 'hash', 'type'];

    public function views()
    {
        return $this->hasMany(VideoView::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }
}
