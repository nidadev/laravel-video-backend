<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Video;

class VideoFile extends Model
{
    //
    protected $fillable = ['video_id', 'variant', 'manifest_url', 'file_url', 'drm', 'duration','meta'];

    protected $casts = [
        'meta' => 'array',
        'drm' => 'boolean',
    ];


    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}
