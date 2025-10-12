<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    //
  public function index(Request $request)
    {
        /*return Video::with(['files','category'])
            ->where('status', 'ready')
            ->latest()
            ->get();*/
            $query = Video::with('files', 'category')
        ->where('status', 'ready');

    if ($request->category_id) {
        $query->where('category_id', $request->category_id);
    }

    return $query->latest()->get();
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
    $video = Video::with('files')->findOrFail($id);
    $user = $request->user();

    $ads_enabled = true;

    if ($user && $user->hasActiveSubscription()) {
        $ads_enabled = false;
    }

    return response()->json([
        'video_title' => $video->title, // optional
        'ads_enabled' => $ads_enabled,
        'episodes' => $video->files->map(function ($file) {
            return [
                'episode_id' => $file->id,
                'title' => $file->variant, // optional
                'url' => $file->file_url,
            ];
        }),
    ]);
}



}
