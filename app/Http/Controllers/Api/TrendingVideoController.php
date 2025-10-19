<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrendingVideo;

class TrendingVideoController extends Controller
{
    //
  public function index()
{
    try {
        $videos = TrendingVideo::latest()->get([
            'id',
            'title',
            'description',
            'thumbnail',
            'video_url',
            'created_at'
        ]);

        // Generate 2-hour temporary URLs for each video and thumbnail
        /*$videos->transform(function ($video) {
            // ✅ Secure video temporary URL
            if ($video->video_url) {
                $path = str_replace(Storage::disk('s3')->url(''), '', $video->video_url);
                $video->video_url = Storage::disk('s3')->temporaryUrl($path, now()->addHours(2));
            }

            // ✅ Secure thumbnail temporary URL
            if ($video->thumbnail) {
                $path = str_replace(Storage::disk('s3')->url(''), '', $video->thumbnail);
                $video->thumbnail = Storage::disk('s3')->temporaryUrl($path, now()->addHours(2));
            }

            return $video;
        });*/

        return response()->json([
            'message' => 'Trending Videos Fetch Successfully',
            'data' => $videos,
            'response' => 200,
            'success' => true
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to fetch trending videos',
            'data' => [],
            'response' => 500,
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}
}
