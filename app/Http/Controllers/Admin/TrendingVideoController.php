<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TrendingVideo;

class TrendingVideoController extends Controller
{
    //
    public function index()
    {
        $trendingVideos = TrendingVideo::latest()->get();
        return view('admin.trending.index', compact('trendingVideos'));
    }

    public function create()
    {
        return view('admin.trending.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'thumbnail' => 'required|string',
            'video_url' => 'required|string',
        ]);

        TrendingVideo::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Trending video uploaded successfully!'
        ]);
    }

    public function destroy($id)
    {
        $video = TrendingVideo::findOrFail($id);
        $video->delete();

        return redirect()->back()->with('success', 'Trending video deleted!');
    }
}
