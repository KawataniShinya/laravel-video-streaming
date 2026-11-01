<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Playlist extends Model
{
    protected $fillable = ['user_id', 'name'];

    public function items()
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('position')->orderBy('id');
    }
}
