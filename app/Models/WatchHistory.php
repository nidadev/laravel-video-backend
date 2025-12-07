<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchHistory extends Model
{
    protected $fillable = ['user_id', 'video_file_id', 'watched_seconds'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function videoFile()
    {
        return $this->belongsTo(VideoFile::class);
    }

    public function video()
{
    return $this->belongsTo(Video::class);
}
}
