<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bookmark;
use App\Models\Video;
class BookmarkController extends Controller
{
    //
    /**
     * ✅ ADD / REMOVE BOOKMARK
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'video_id' => 'required|exists:videos,id',
            'season_id' => 'nullable|exists:seasons,id',
        ]);

        $user = $request->user();

        $bookmark = Bookmark::where('user_id', $user->id)
                            ->where('video_id', $request->video_id)
                            ->first();

        if ($bookmark) {
            $bookmark->delete();

            return response()->json([
                'message' => 'Bookmark removed',
                'data' => ['bookmarked' => false,
            'video_id' => $request->video_id,
                'season_id' => $request->season_id],
                'success' => true,
            ]);
        }

        Bookmark::create([
            'user_id' => $user->id,
            'video_id' => $request->video_id,
        ]);

        return response()->json([
            'message' => 'Bookmark added',
            'data' => ['bookmarked' => true,
        'video_id' => $request->video_id,
                'season_id' => $request->season_id],
            'success' => true,
        ]);
    }

    /**
     * ✅ GET USER BOOKMARKS
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $bookmarks = Bookmark::with([
                'video:id,title,description,thumbnail,category_id,subcategory_id,season_id'
            ])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($item) {
                return [
                    'video_id' => $item->video->id,
                    'title' => $item->video->title,
                    'description' => $item->video->description,
                    'thumbnail' => $item->video->thumbnail,
                    'category_id' => $item->video->category_id,
                    'subcategory_id' => $item->video->subcategory_id,
                    'season_id' => $item->video->season_id,
                    'bookmarked_at' => $item->created_at->toDateTimeString(),
                ];
            });

        return response()->json([
            'message' => 'Bookmarks fetched successfully',
            'data' => $bookmarks,
            'success' => true,
        ]);
    }
}
