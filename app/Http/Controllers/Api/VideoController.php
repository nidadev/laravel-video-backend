<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use App\Models\VideoView;
use App\Models\TrendingVideo;


class VideoController extends Controller
{
    //
 public function index(Request $request)
{
    try {
        $query = Video::with(['files', 'category'])
            ->where('status', 'ready');

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // ✅ Use pagination instead of get()
        $videos = $query->latest()->paginate(30);

        return response()->json([
            'message' => 'Videos fetched successfully',
            'data' => [
                'videos' => $videos->items(),
                'current_page' => $videos->currentPage(),
                'per_page' => $videos->perPage(),
                'total' => $videos->total(),
                'last_page' => $videos->lastPage(),
            ],
            'response' => 200,
            'success' => true,
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Video index error: ' . $e->getMessage());

        return response()->json([
            'message' => 'Failed to fetch videos',
            'data' => [],
            'response' => 500,
            'success' => false,
        ], 500);
    }
}



   public function upload(Request $request)
{
    //dd('11');
    // Validate input
    $request->validate([
        'title' => 'nullable|string|max:255',
        'description' => 'nullable|string',
        'duration' => 'nullable|integer',
        'thumbnail' => 'nullable|string',
        'category_id' => 'required|exists:categories,id',
        'video_files' => 'nullable|array',
        'file' => 'nullable|mimetypes:video/mp4,video/mpeg,video/quicktime',
    ]);

    $path = null;

    // 🟢 Case 1: A real file is uploaded
    if ($request->hasFile('file')) {
        $path = $request->file('file')->store('videos', 'public');
    }
    //dd($request->category_id);

    // 🟣 Case 2: No file uploaded but external URLs are provided
    $video = Video::create([
        'title' => $request->title ?? ($path ? basename($path) : 'Untitled Video'),
        'description' => $request->description,
        'created_by' => $request->user()->id,
        'status' => 'ready',
        'duration' => $request->duration,
        'thumbnail' => $request->thumbnail,
                'category_id' => $request->category_id,

    ]);

    // Save files (either from upload or external links)
    if ($path) {
        // store uploaded file
        $video->files()->create([
            'variant' => 'source',
            'file_url' => Storage::url($path),
            'manifest_url' => null,
            'drm' => false,
            'meta' => json_encode(['original_name' => $request->file('file')->getClientOriginalName()]),
        ]);
    } elseif ($request->has('video_files')) {
        // store external file URLs
        foreach ($request->video_files as $file) {
            $video->files()->create([
                'variant' => $file['variant'] ?? 'external',
                'file_url' => $file['file_url'] ?? null,
                'manifest_url' => $file['manifest_url'] ?? null,
                'drm' => $file['drm'] ?? false,
                'meta' => json_encode($file['meta'] ?? []),
            ]);
        }
    }

    return response()->json([
        'message' => 'Video uploaded successfully',
        'video' => $video->load('files'),
    ]);
}

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image',
            'video_files.*' => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime',
        ]);

        $thumbnailPath = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store('thumbnails', 'public')
            : null;

        $video = Video::create([
            'title' => $request->title,
            'description' => $request->description,
            'created_by' => $request->user()->id,
            'status' => 'published',
            'duration' => $request->duration ?? null,
            'thumbnail' => $thumbnailPath ? Storage::url($thumbnailPath) : null,
        ]);

        if ($request->hasFile('video_files')) {
            foreach ($request->file('video_files') as $variant => $file) {
                $path = $file->store('videos', 'public');

                $video->files()->create([
                    'variant' => $variant,
                    'file_url' => Storage::url($path),
                    'manifest_url' => null,
                    'drm' => false,
                    'meta' => json_encode([
                        'size' => $file->getSize(),
                        'format' => $file->getClientOriginalExtension(),
                    ]),
                ]);
            }
        }

        return response()->json([
            'message' => 'Video created successfully',
            'video' => $video->load('files'),
        ]);
    }

    public function update(Request $request, $id)
{
    //dd('123');
    $video = Video::with('files')->findOrFail($id);

    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'required|string',
          'category_id' => 'filled|exists:categories,id',
        'status' => 'required|in:processing,ready,published,disabled',
        'thumbnail' => 'nullable|image',
        'video_files.*' => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime'
    ]);

     if ($request->hasFile('thumbnail')) {
        $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
        $video->thumbnail = Storage::url($thumbnailPath);
    }

    // 🔹 Handle video file uploads (update/add new variants)
    if ($request->hasFile('video_files')) {
        foreach ($request->file('video_files') as $variant => $file) {
            $path = $file->store('videos', 'public');

            // Check if variant already exists
            $existingFile = $video->files()->where('variant', $variant)->first();

            if ($existingFile) {
                // Update existing variant file
                $existingFile->update([
                    'file_url' => Storage::url($path),
                    'manifest_url' => null,
                    'meta' => json_encode([
                        'size' => $file->getSize(),
                        'format' => $file->getClientOriginalExtension(),
                    ]),
                ]);
            } else {
                // Create new variant file entry
                $video->files()->create([
                    'variant' => $variant,
                    'file_url' => Storage::url($path),
                    'manifest_url' => null,
                    'drm' => false,
                    'meta' => json_encode([
                        'size' => $file->getSize(),
                        'format' => $file->getClientOriginalExtension(),
                    ]),
                ]);
            }
        }
    }

    // 🔹 Update video basic info
    $video->update([
        'title' => $request->title ?? $video->title,
        'category_id' => $request->category_id,
        'description' => $request->description ?? $video->description,
        'status' => $request->status ?? $video->status,
    ]);

    return response()->json([
        'message' => 'Video updated successfully',
        'video' => $video->fresh('files'),
    ]);
}
public function destroy($id)
{
    $video = Video::with('files')->findOrFail($id);

    // Delete files from storage (if stored locally)
    foreach ($video->files as $file) {
        if ($file->file_url && str_contains($file->file_url, '/storage/')) {
            $relativePath = str_replace('/storage/', '', $file->file_url);
            Storage::disk('public')->delete($relativePath);
        }
    }

    // Delete the video itself
    $video->delete();

    return response()->json([
        'message' => 'Video deleted successfully'
    ]);
}

 public function changeStatus(Request $request, $id)
    {
        $video = Video::findOrFail($id);

        $request->validate([
            'status' => 'required|in:ready,published,disabled',
        ]);

        $video->status = $request->status;
        $video->save();

        return response()->json([
            'message' => 'Video status updated successfully',
            'video' => $video,
        ]);
    }

  public function show(Request $request, $id)
{
    try {
        $video = Video::with('files')->findOrFail($id);
        $user = $request->user();

        $ads_enabled = true;

        if ($user && method_exists($user, 'hasActiveSubscription') && $user->hasActiveSubscription()) {
            $ads_enabled = false;
        }

        $data = [
            'video_title' => $video->title,
            'ads_enabled' => $ads_enabled,
            'episodes' => $video->files->map(function ($file) {
                return [
                    'episode_id' => $file->id,
                    'title' => $file->variant,
                    'url' => $file->file_url,
                ];
            }),
        ];

        return response()->json([
            'message' => 'fetch video details Successfully',
            'data' => $data,
            'response' => 200,
            'success' => true,
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Video show error: ' . $e->getMessage());

        return response()->json([
            'message' => 'Failed to fetch video details',
            'data' => [],
            'response' => 500,
            'success' => false,
        ], 500);
    }
}

public function fetchByCategory(Request $request)
{
    try {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'subcategory_id' => 'nullable|exists:subcategories,id',
        ]);

        // Base query
        $query = Video::with(['category', 'subcategory', 'files'])
            ->where('status', 'ready')
            ->where('category_id', $request->category_id);

        // Filter by subcategory if provided
        if ($request->filled('subcategory_id')) {
            $query->where('subcategory_id', $request->subcategory_id);
        }

        $videos = $query->latest()->get();

        if ($videos->isEmpty()) {
            return response()->json([
                'message' => 'No videos found for this category/subcategory',
                'data' => [],
                'response' => 404,
                'success' => false,
            ], 404);
        }

        // Format response
        $formatted = $videos->map(function ($video) {
            return [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'thumbnail' => $video->thumbnail_url ?? null,
                'category' => $video->category ? [
                    'id' => $video->category->id,
                    'name' => $video->category->name,
                ] : null,
                'subcategory' => $video->subcategory ? [
                    'id' => $video->subcategory->id,
                    'name' => $video->subcategory->name,
                ] : null,
                'files' => $video->files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'variant' => $file->variant,
                        'url' => $file->file_url,
                    ];
                }),
            ];
        });

        return response()->json([
            'message' => 'Api Call Successfully',
            'data' => $formatted,
            'response' => 200,
            'success' => true,
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Error fetching videos by category: ' . $e->getMessage());

        return response()->json([
            'message' => 'Something went wrong',
            'data' => [],
            'response' => 500,
            'success' => false,
        ], 500);
    }
}


public function recordView(Request $request, $videoId)
{
    $user = $request->user();

    if (!$user) {
        return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    try {
        // ✅ Fetch video with its files
        $video = Video::with('files')->findOrFail($videoId);

        // ✅ Record unique view (optional duplicate prevention within 10 min)
        $alreadyViewed = VideoView::where('video_id', $video->id)
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if (!$alreadyViewed) {
            VideoView::create([
                'video_id' => $video->id,
                'user_id' => $user->id,
            ]);
        }

        // ✅ Get updated total views
        $totalViews = VideoView::where('video_id', $video->id)->count();

        return response()->json([
            'success' => true,
            'message' => 'View recorded successfully',
            'video_id' => $video->id,
            'total_views' => $totalViews,
        ]);

    } catch (\Exception $e) {
        \Log::error('View record failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to record view',
        ], 500);
    }
}

public function trendingAndMostWatched()
{
    \Log::info('Trending API hit'); // log entry

    try {
        $trendingVideos = Video::with(['files', 'category', 'subcategory'])
            ->where('status', 'ready')
            ->where('is_trending', true)
            ->latest()
            ->take(10)
            ->get();

        \Log::info('Trending Videos Count: '. $trendingVideos->count());

        $mostWatchedVideos = Video::with(['files', 'category', 'subcategory'])
            ->withCount('views')
            ->where('status', 'ready')
            ->orderBy('views_count', 'desc')
            ->take(10)
            ->get();

        \Log::info('Most Watched Videos Count: '. $mostWatchedVideos->count());

        return response()->json([
            'success' => true,
            'trending_videos' => $trendingVideos->map(fn($v) => [
                'id' => $v->id,
                'title' => $v->title,
                'thumbnail' => $v->thumbnail,
                'category' => optional($v->category)->only('id','name'),
                'subcategory' => optional($v->subcategory)->only('id','name'),
                'files' => $v->files->map(fn($f) => [
                    'id' => $f->id,
                    'variant' => $f->variant,
                    'url' => $f->file_url,
                ]),
            ]),
            'most_watched_videos' => $mostWatchedVideos->map(fn($v) => [
                'id' => $v->id,
                'title' => $v->title,
                'thumbnail' => $v->thumbnail,
                'category' => optional($v->category)->only('id','name'),
                'subcategory' => optional($v->subcategory)->only('id','name'),
                'views_count' => $v->views_count,
                'files' => $v->files->map(fn($f) => [
                    'id' => $f->id,
                    'variant' => $f->variant,
                    'url' => $f->file_url,
                ]),
            ]),
        ]);

    } catch (\Exception $e) {
        \Log::error('Trending & Most Watched Error: '.$e->getMessage().' at '.$e->getFile().' line '.$e->getLine());

        return response()->json([
            'message' => 'Failed to fetch video details',
            'data' => [],
            'response' => 500,
            'success' => false,
        ], 500);
    }
}









}
