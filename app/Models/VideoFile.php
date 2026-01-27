<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Video;

class VideoFile extends Model
{
    //
    protected $fillable = ['video_id', 'variant', 'manifest_url', 'file_url','mp4_url','image', 'drm', 'duration','meta','season_id'];

    protected $casts = [
        'meta' => 'array',
        'drm' => 'boolean',
        'duration' => 'string',
    ];


    public function video()
    {
        return $this->belongsTo(Video::class);
    }
     public function season()
    {
        return $this->belongsTo(Season::class);
    }
}
