<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;

class VideoController extends Controller
{
    //
    public function index()
{
    // Fetch videos with categories
    $videos = Video::with('category')->get();

    // ✅ Convert S3 URLs to temporary signed URLs
    /*$videos->transform(function ($video) {
        // Thumbnail temporary URL
        if ($video->thumbnail) {
            $video->thumbnail = Storage::disk('s3')->temporaryUrl(
                str_replace(Storage::disk('s3')->url(''), '', $video->thumbnail),
                now()->addHours(2)
            );
        }

        // Video temporary URL
        if ($video->video_url) {
            $video->video_url = Storage::disk('s3')->temporaryUrl(
                str_replace(Storage::disk('s3')->url(''), '', $video->video_url),
                now()->addHours(2)
            );
        }

        return $video;
    });*/

    return view('admin.videos.index', compact('videos'));
}


    public function create()
    {
        $categories = Category::all();
        $video = Video::with('category')->get();
        //dd($video);
        return view('admin.videos.create', compact('categories', 'video'));
    }



public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category_id' => 'required|exists:categories,id',

        'subcategory' => 'nullable|string|max:100', // ✅ Added subcategory

        'thumbnail' => 'nullable|image|max:2048',

        'video_files' => 'required|array|min:1',
        'video_files.*' => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime|max:204800',

        'variants' => 'nullable|array',
        'variants.*' => 'nullable|string|max:255',

        'drms' => 'nullable|array',
        'drms.*' => 'nullable|in:0,1',

        'durations' => 'nullable|array',
        'durations.*' => 'nullable|string|max:20',
    ]);

    try {
        // ✅ Upload thumbnail to S3 if provided
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 's3');
            if (!$thumbnailPath) {
                throw new \Exception('Failed to upload thumbnail.');
            }
            Storage::disk('s3')->setVisibility($thumbnailPath, 'public');
        }

        // ✅ Create the main video record (includes subcategory)
        $video = Video::create([
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'subcategory' => $request->subcategory, // ✅ Save subcategory
            'thumbnail' => $thumbnailPath ? Storage::disk('s3')->url($thumbnailPath) : null,
            'status' => 'ready',
            'created_by' => auth()->id() ?? auth('admin')->id(),
        ]);

        // ✅ Handle episodes / variant video uploads
        $files = $request->file('video_files');
        $variants = $request->input('variants', []);
        $drms = $request->input('drms', []);
        $durations = $request->input('durations', []);

        foreach ($files as $index => $file) {
            if (!$file->isValid()) {
                \Log::warning("Skipped invalid file at index {$index}.");
                continue;
            }

            $path = $file->store('videos', 's3');
            if (!$path) {
                \Log::error("Failed to upload file at index {$index} to S3.");
                continue;
            }

            Storage::disk('s3')->setVisibility($path, 'public');

            $video->files()->create([
                'variant' => $variants[$index] ?? 'Episode ' . ($index + 1),
                'file_url' => Storage::disk('s3')->url($path),
                'manifest_url' => null,
                'drm' => isset($drms[$index]) ? (bool) $drms[$index] : false,
                'duration' => $durations[$index] ?? null,
                'meta' => json_encode([
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ]),
            ]);
        }

        return redirect()
            ->route('admin.videos')
            ->with('success', 'Video and episodes uploaded successfully.');
    } catch (\Exception $e) {
        \Log::error('Video upload failed: ' . $e->getMessage());

        return back()->withInput()->withErrors([
            'upload_error' => 'Video upload failed. Please try again or check the logs.',
        ]);
    }
}


    public function destroy($id)
{
    $video = Video::findOrFail($id);
    $video->delete();

    return redirect()->route('admin.videos')->with('success', 'Video deleted successfully');
}

public function edit($id)
{
    $video = Video::with('files')->findOrFail($id);
    $categories = Category::all();

    return view('admin.videos.edit', compact('video', 'categories'));
}



public function update(Request $request, $id)
{
    $video = Video::with('files')->findOrFail($id);

    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category_id' => 'required|exists:categories,id',
        'subcategory' => 'nullable|string|max:100',
        'status' => 'required|string',
        'thumbnail' => 'nullable|string', // presigned S3 URL
        'videos' => 'nullable|json',
        'delete_files' => 'nullable|array',
    ]);

    try {
        // ✅ Update main video info
        $video->update([
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'subcategory' => $request->subcategory,
            'status' => $request->status,
            'thumbnail' => $request->thumbnail ?: $video->thumbnail,
        ]);

        // ✅ Delete marked files
        if ($request->filled('delete_files')) {
            $video->files()->whereIn('id', $request->delete_files)->delete();
        }

        // ✅ Update existing file data (variants/duration/drm)
        if ($request->has('existing_files')) {
            foreach ($request->existing_files as $fileData) {
                if (isset($fileData['id'])) {
                    $file = $video->files()->find($fileData['id']);
                    if ($file) {
                        $file->update([
                            'variant' => $fileData['variant'] ?? $file->variant,
                            'duration' => $fileData['duration'] ?? $file->duration,
                            'drm' => $fileData['drm'] ?? $file->drm,
                        ]);
                    }
                }
            }
        }

        // ✅ Handle new uploaded videos (from S3)
        if ($request->filled('videos')) {
            $newVideos = json_decode($request->videos, true);
            foreach ($newVideos as $file) {
                $video->files()->create([
                    'variant' => $file['variant'] ?? 'Default',
                    'file_url' => $file['file_url'],
                    'manifest_url' => null,
                    'drm' => $file['drm'] ?? false,
                    'duration' => $file['duration'] ?? null,
                    'meta' => [
                        'original_name' => $file['original_name'] ?? null,
                        'size' => $file['size'] ?? null,
                        'mime' => $file['mime'] ?? null,
                    ],
                ]);
            }
        }

        return redirect()->route('admin.videos.edit', $video->id)
            ->with('success', '✅ Video updated successfully!');
    } catch (\Exception $e) {
        \Log::error('Video update failed: ' . $e->getMessage());
        return back()->with('error', 'Failed to update video: ' . $e->getMessage());
    }
}


public function createPresigned()
{
    $categories = Category::all();
    return view('admin.videos.upload_presigned', compact('categories'));
}

public function generatePresignedUrl(Request $request)
{
    $request->validate([
        'filename' => 'required|string',
        'content_type' => 'required|string',
        'type' => 'nullable|string|in:video,thumbnail', // 👈 NEW
    ]);

    try {
        $s3 = Storage::disk('s3')->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        // 👇 Determine folder based on upload type
        $folder = $request->type === 'thumbnail' ? 'thumbnails/' : 'videos/';
        $key = $folder . uniqid() . '-' . $request->filename;

        $cmd = $s3->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $request->content_type,
        ]);

        // Presigned URL valid for 20 minutes
        $presignedRequest = $s3->createPresignedRequest($cmd, '+20 minutes');
        $presignedUrl = (string) $presignedRequest->getUri();

        return response()->json([
            'url' => $presignedUrl,
            'file_url' => Storage::disk('s3')->url($key),
        ]);
    } catch (\Exception $e) {
        \Log::error('Presigned URL generation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'error' => 'Failed to generate presigned URL',
            'details' => $e->getMessage(),
        ], 500);
    }
}

public function storePresigned(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category_id' => 'required|exists:categories,id',
        'subcategory' => 'nullable|string|max:100',
        'thumbnail' => 'nullable|string',
        'videos' => 'required|array|min:1',
        'videos.*.file_url' => 'required|string',
        'videos.*.variant' => 'nullable|string|max:255',
        'videos.*.drm' => 'nullable|boolean',
        'videos.*.duration' => 'nullable|string|max:50',
        'videos.*.original_name' => 'nullable|string',
        'videos.*.size' => 'nullable|numeric',
        'videos.*.mime' => 'nullable|string',
    ]);

    try {
        // ✅ Create main Video record
        $video = Video::create([
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'subcategory' => $request->subcategory,
             'thumbnail' => $request->thumbnail, // ✅ added here
            'status' => 'ready',
            'created_by' => auth()->id() ?? auth('admin')->id(),
        ]);

        // ✅ Save uploaded file metadata
        foreach ($request->videos as $file) {
            $video->files()->create([
                'variant' => $file['variant'] ?? 'Default',
                'file_url' => $file['file_url'],
                'manifest_url' => null,
                'drm' => $file['drm'] ?? false,
                'duration' => $file['duration'] ?? null,
                'meta' => [
                    'original_name' => $file['original_name'] ?? null,
                    'size' => $file['size'] ?? null,
                    'mime' => $file['mime'] ?? null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '✅ Video and metadata saved successfully!',
            'video_id' => $video->id,
        ]);

    } catch (\Exception $e) {
        \Log::error('Presigned store failed: '.$e->getMessage());
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function mostWatched()
{
    $videos = Video::withCount('views')
        ->orderByDesc('views_count')
        ->take(20)
        ->get();

    return view('admin.videos.most_watched', compact('videos'));
}

}
