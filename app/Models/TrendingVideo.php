<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class TrendingVideo extends Model
{
    //
    use HasFactory;
     protected $fillable = [
        'title',
        'description',
        'thumbnail',
        'video_url',
        'is_active',
    ];

    public function video()
{
    return $this->belongsTo(Video::class);
}
}
