<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;
use App\Models\VideoView;
use App\Models\TrendingVideo;
use App\Models\Category;
use App\Models\Season;
use App\Models\Plan;
use App\Models\GooglePayPurchase;






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

        // Check ads
        $ads_enabled = true;
        if ($user && method_exists($user, 'hasActiveSubscription') && $user->hasActiveSubscription()) {
            $ads_enabled = false;
        }

        // Filter by season_id if provided
        $seasonId = $request->query('season_id');
        $episodes = $video->files;

        if ($seasonId) {
            $episodes = $episodes->where('season_id', $seasonId);
        }

        // Get seasons of this video's episodes
        $seasonIds = $video->files->pluck('season_id')->unique()->filter();

        $seasons = Season::whereIn('id', $seasonIds)
            ->get(['id', 'name']);

        // Format response
        $data = [
            'video' => [
                'title' => $video->title,
                'description' => $video->description,
                'thumbnail' => $video->thumbnail,
                'year_of_published' => $video->year_of_published,
            ],
            'ads_enabled' => $ads_enabled,
            'seasons' => $seasons,

            'episodes' => $episodes->map(function ($file) {
                return [
                    'episode_id' => $file->id,
                    'title' => $file->variant,
                    'season_id' => $file->season_id,
                    'url' => $file->file_url,
                    'thumbnail' => $file->image,   // <-- episode image added
                ];
            }),
        ];

        return response()->json([
            'message' => 'Fetch video details successfully',
            'data' => $data,
            'response' => 200,
            'success' => true,
        ]);

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

public function trendingAndMostWatched(Request $request)
{
    try {
        // ✅ Base queries
        $trendingQuery = Video::with(['files', 'category', 'subcategory'])
            ->where('status', 'ready')
            ->where('is_trending', true);

        $mostWatchedQuery = Video::with(['files', 'category', 'subcategory'])
            ->withCount('views')
            ->where('status', 'ready');

        // ✅ Apply filters (only if not empty)
        if (!empty($request->category_id)) {
            $trendingQuery->where('category_id', $request->category_id);
            $mostWatchedQuery->where('category_id', $request->category_id);
        }

        if (!empty($request->subcategory_id)) {
            $trendingQuery->where('subcategory_id', $request->subcategory_id);
            $mostWatchedQuery->where('subcategory_id', $request->subcategory_id);
        }

        // ✅ Fetch videos
        $trendingVideos = $trendingQuery->latest()->take(10)->get();
        $mostWatchedVideos = $mostWatchedQuery->orderBy('views_count', 'desc')->take(10)->get();

        // ✅ Format each video
        $formatVideo = fn($v) => [
            'id' => $v->id,
            'title' => $v->title,
            'thumbnail' => $v->thumbnail,
            'category' => optional($v->category)->only('id', 'name'),
            'subcategory' => optional($v->subcategory)->only('id', 'name'),
            'views_count' => $v->views_count ?? 0,
            'files' => $v->files->map(fn($f) => [
                'id' => $f->id,
                'variant' => $f->variant,
                'url' => $f->file_url,
            ]),
        ];

        // ✅ Prepare data
        $data = [
            'filters_applied' => $request->only(['category_id', 'subcategory_id']) ?: 'none',
            'trending_videos' => $trendingVideos->map($formatVideo)->values(),
            'most_watched_videos' => $mostWatchedVideos->map($formatVideo)->values(),
        ];

        // ✅ Success response
        return response()->json([
            'message' => 'fetch video details Successfully',
            'data' => $data,
            'response' => 200,
            'success' => true,
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Trending & Most Watched Error: ' . $e->getMessage());

        return response()->json([
            'message' => 'Failed to fetch video details',
            'data' => [],
            'response' => 500,
            'success' => false,
        ], 500);
    }
}

public function seeall(Request $request)
{
    try {
        // POST parameters instead of query params
        $listType = $request->input('list_type');  
        $cat = $request->input('cat');             
        $subcat = $request->input('subcat');

        $query = Video::withCount('views')
            ->with(['category:id,name', 'subcategory:id,name', 'files:id,video_id,season_id']);

        // Category Filter
        if ($cat) {
            $query->where('category_id', $cat);
        }

        // Subcategory Filter
        if ($subcat) {
            $query->where('subcategory_id', $subcat);
        }

        // Trending Videos
        if ($listType == 1) {
            $query->where('is_trending', 1)
                  ->orderBy('created_at', 'desc');
        }
        // Most Watched Videos
        else {
            $query->orderBy('views_count', 'desc');
        }

        // Pagination
        $videos = $query->paginate(12);

        // FORMAT RESPONSE
        $videos->getCollection()->transform(function ($video) {

            $seasonIds = $video->files->pluck('season_id')->unique()->filter()->values();
            $seasonId = $seasonIds->first(); // single season_id

            return [
                'id' => $video->id,
                'title' => $video->title,
                'description' => $video->description,
                'thumbnail' => $video->thumbnail,
                'year_of_published' => $video->year_of_published,
                'views' => $video->views_count,
                'category' => $video->category->name ?? null,
                'subcategory' => $video->subcategory->name ?? null,
                'season_id' => $seasonId,
            ];
        });

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
        \Log::error($e->getMessage());

        return response()->json([
            'message' => 'Failed to fetch videos',
            'data' => [],
            'response' => 500,
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}






public function dashboard(Request $request)
{
    $categoryId = $request->category_id;
    $subcategoryId = $request->subcategory_id;

    /* =====================================================
       1. BANNER VIDEOS (3 RANDOM) — AS ARRAY OBJECTS
    ===================================================== */
    $bannerQuery = Video::query();

    if ($categoryId) {
        $bannerQuery->where('category_id', $categoryId);
    }

    if ($subcategoryId) {
        $bannerQuery->where('subcategory_id', $subcategoryId);
    }

    $bannerVideos = $bannerQuery
        ->inRandomOrder()
        ->take(3)
        ->get([
            'id',
            'title',
            'thumbnail',
            'description',
            'season_id',
            'year_of_published',
            'category_id',
            'subcategory_id'
        ]);


    /* =====================================================
       2. TRENDING LIST
    ===================================================== */
    $trendingQuery = Video::where('is_trending', true)
        ->orderBy('created_at', 'desc');

    if ($categoryId) {
        $trendingQuery->where('category_id', $categoryId);
    }

    if ($subcategoryId) {
        $trendingQuery->where('subcategory_id', $subcategoryId);
    }

    $trending = $trendingQuery->get([
        'id',
        'title',
        'thumbnail',
        'description',
        'season_id',
        'year_of_published',
        'category_id',
        'subcategory_id'
    ]);


    /* =====================================================
       3. MOST WATCHED LIST
    ===================================================== */
    $mostWatchedQuery = Video::withCount('views')
        ->orderBy('views_count', 'desc');

    if ($categoryId) {
        $mostWatchedQuery->where('category_id', $categoryId);
    }

    if ($subcategoryId) {
        $mostWatchedQuery->where('subcategory_id', $subcategoryId);
    }

    $mostWatched = $mostWatchedQuery->get([
        'id',
        'title',
        'thumbnail',
        'season_id',
        'description',
        'year_of_published',
        'category_id',
        'subcategory_id'
    ]);


    /* =====================================================
       FINAL RESPONSE
    ===================================================== */
    return response()->json([
        'message' => 'Videos fetched successfully',
        'data' => [
            'banner_videos' => $bannerVideos,   // <-- ARRAY OF 3 OBJECTS
            'trending' => $trending,
            'most_watched' => $mostWatched,
        ],
        'response' => 200,
        'success' => true,
    ], 200);
}




public function search(Request $request)
{
    $request->validate([
        'keyword' => 'required|string|min:2',
        'category_id' => 'nullable|exists:categories,id',
        'subcategory_id' => 'nullable|exists:subcategories,id',
    ]);

    $keyword = strtolower($request->keyword);
    $categoryId = $request->category_id;
    $subcategoryId = $request->subcategory_id;

    // Base query with filters
    $baseQuery = Video::query()
        ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
        ->when($subcategoryId, fn($q) => $q->where('subcategory_id', $subcategoryId))
        ->where(function ($q) use ($keyword) {
            $q->where('title', 'LIKE', "%{$keyword}%")
              ->orWhere('description', 'LIKE', "%{$keyword}%");
        })
        ->withCount('views');

    $videos = $baseQuery->get();

    // Apply search scoring algorithm
    $videos = $videos->map(function ($video) use ($keyword) {
        $title = strtolower($video->title);
        $desc  = strtolower($video->description ?? "");

        $score = 0;
        if ($title === $keyword) $score += 50;
        if (str_contains($title, $keyword)) $score += 30;
        if (str_contains($desc, $keyword)) $score += 10;
        if ($video->is_trending) $score += 20;

        $score += ($video->views_count / 10);
        $daysOld = now()->diffInDays($video->created_at);
        $score += max(0, (30 - $daysOld));

        $video->search_score = $score;

        // Return all required fields without 'files'
        return [
            'id' => $video->id,
            'title' => $video->title,
            'description' => $video->description,
            'year_of_published' => $video->year_of_published,
            'created_by' => $video->created_by,
            'status' => $video->status,
            'duration' => $video->duration,
            'thumbnail' => $video->thumbnail,
            'created_at' => $video->created_at,
            'updated_at' => $video->updated_at,
            'category_id' => $video->category_id,
            'subcategory_id' => $video->subcategory_id,
            'season_id' => $video->season_id,
            'is_trending' => $video->is_trending,
            'views_count' => $video->views_count,
            'search_score' => $video->search_score,
        ];
    });

    // Sort by search score descending
    $sortedVideos = $videos->sortByDesc('search_score')->values();

    return response()->json([
        'message' => 'Videos fetched successfully',
        'data' => [
            'top_result' => $sortedVideos, // array of cleaned video objects
        ],
        'response' => 200,
        'success' => true
    ]);
}





public function googlePayPurchase(Request $request)
{
    $request->validate([
        'plan_id' => 'required|exists:plans,id',
        'googlepay_transaction_id' => 'required|string',
        'googlepay_email' => 'required|email',
        'payment_response' => 'required|array',
    ]);

    $user = $request->user();
    $plan = Plan::findOrFail($request->plan_id);

    try {
        // ✅ Save Google Pay purchase
        $payment = \App\Models\GooglePayPurchase::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'googlepay_transaction_id' => $request->googlepay_transaction_id,
            'googlepay_email' => $request->googlepay_email,
            'payment_response' => json_encode($request->payment_response),
            'status' => 'completed', // make sure column type supports this value
        ]);

        // ✅ Determine subscription duration
        $startDate = now();
        switch (strtolower($plan->type)) {
            case 'weekly':
                $endDate = $startDate->copy()->addWeek();
                break;
            case 'monthly':
                $endDate = $startDate->copy()->addMonth();
                break;
            case 'yearly':
                $endDate = $startDate->copy()->addYear();
                break;
            default:
                $endDate = $startDate->copy()->addDays(7); // default for free/trial plan
        }

        // ✅ Store subscription
        \App\Models\Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Google Pay purchase recorded and subscription created successfully',
            'data' => [
                'payment' => $payment,
                'plan' => $plan->name,
                'start_date' => $startDate->toDateTimeString(),
                'end_date' => $endDate->toDateTimeString(),
            ],
            'response' => 200,
            'success' => true,
        ]);

    } catch (\Exception $e) {
        \Log::error('Google Pay purchase or subscription failed: ' . $e->getMessage());

        return response()->json([
            'message' => 'Failed to record Google Pay purchase or create subscription',
            'data' => ['error' => $e->getMessage()],
            'response' => 500,
            'success' => false,
        ]);
    }
}













}
