<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use App\Services\MediaUrlService;

class TrendingVideoController extends Controller
{
    //
  public function index()
{
    try {
        $videos = Video::with(['files' => function ($query) {
                $query->orderBy('season_id')->orderBy('id');
            }])
            ->where('status', 'ready')
            ->where('is_trending', true)
            ->latest()
            ->get()
            ->map(function ($video) {
                $firstFile = $video->files->first();

                return [
                    'id' => $video->id,
                    'title' => $video->title,
                    'description' => $video->description,
                    'thumbnail' => MediaUrlService::cdnUrl($video->thumbnail),
                    'video_url' => MediaUrlService::cdnUrl(optional($firstFile)->file_url),
                    'created_at' => $video->created_at,
                ];
            })
            ->filter(fn($video) => !empty($video['video_url']))
            ->values();

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
