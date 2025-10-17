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

            return response()->json([
                'message' => 'Trending Videos Fetch',
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
