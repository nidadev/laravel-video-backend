<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    //
     public function index()
    {
        $videos = Video::with('category')->get();
        return view('admin.videos.index', compact('videos'));
    }

    public function create()
    {
        $categories = Category::all();
        $video = Video::with('category')->get();
        //dd($video);
        return view('admin.videos.create', compact('categories', 'video'));
    }

  /*public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category_id' => 'required|exists:categories,id',

        'thumbnail' => 'nullable|image|max:2048',

        'video_files' => 'required|array|min:1',
        'video_files.*' => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime|max:204800',

        'variants' => 'nullable|array',
        'variants.*' => 'nullable|string|max:255',

        'drms' => 'nullable|array',
        'drms.*' => 'nullable|in:0,1',
    ]);

    // Upload thumbnail to S3 if exists
    $thumbnailPath = null;
    if ($request->hasFile('thumbnail')) {
        $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 's3');
        Storage::disk('s3')->setVisibility($thumbnailPath, 'public');
    }

    // Create video
    $video = Video::create([
        'title' => $request->title,
        'description' => $request->description,
        'category_id' => $request->category_id,
        'thumbnail' => $thumbnailPath ? Storage::disk('s3')->url($thumbnailPath) : null,
        'status' => 'ready',
        'created_by' => auth()->id() ?? auth('admin')->id(), // fallback if using custom guard
    ]);

    // Upload video files (episodes / variants)
    $files = $request->file('video_files');
    foreach ($files as $file) {
    dd($file); // You should see file info here
}
    $variants = $request->input('variants', []);
    $drms = $request->input('drms', []);

    foreach ($files as $index => $file) {
        if (!$file->isValid()) {
            continue; // skip invalid uploads
        }

        $path = $file->store('videos', 's3');
        Storage::disk('s3')->setVisibility($path, 'public');
        //dd($path);

        $variant = $variants[$index] ?? 'episode_' . ($index + 1);
        $drm = isset($drms[$index]) ? (bool) $drms[$index] : false;

        

        $video->files()->create([
            'variant' => $variant,
            'file_url' => Storage::disk('s3')->url($path),
            'manifest_url' => null,
            'drm' => $drm,
            'meta' => json_encode([
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ]),
        ]);
    }

    return redirect()->route('admin.videos')->with('success', 'Video and episodes uploaded successfully.');
}*/

public function store(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category_id' => 'required|exists:categories,id',

        'thumbnail' => 'nullable|image|max:2048',

        'video_files' => 'required|array|min:1',
        'video_files.*' => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime|max:204800',

        'variants' => 'nullable|array',
        'variants.*' => 'nullable|string|max:255',

        'drms' => 'nullable|array',
        'drms.*' => 'nullable|in:0,1',

        'durations' => 'nullable|array',
        'durations.*' => 'nullable|string|max:20', // Accept string duration e.g. "3600" or "01:00:00"
    ]);

    try {
        // Upload thumbnail to S3 if provided
        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 's3');
            if (!$thumbnailPath) {
                throw new \Exception('Failed to upload thumbnail.');
            }
            Storage::disk('s3')->setVisibility($thumbnailPath, 'public');
        }

        // Create the main video record
        $video = Video::create([
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'thumbnail' => $thumbnailPath ? Storage::disk('s3')->url($thumbnailPath) : null,
            'status' => 'ready',
            'created_by' => auth()->id() ?? auth('admin')->id(),
        ]);

        // Handle episodes / variant video uploads
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
            //dd($durations[$index]);

            $video->files()->create([
                'variant' => $variants[$index] ?? 'Episode ' . ($index + 1),
                'file_url' => Storage::disk('s3')->url($path),
                'manifest_url' => null,
                'drm' => isset($drms[$index]) ? (bool) $drms[$index] : false,
                'duration' => $durations[$index] ?? null, // Save duration here
                'meta' => json_encode([
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ]),
            ]);
        }

        return redirect()->route('admin.videos')->with('success', 'Video and episodes uploaded successfully.');
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
        'description' => 'required|string',
        'category_id' => 'filled|exists:categories,id',
        'status' => 'required|in:processing,ready,published,disabled',
        'thumbnail' => 'nullable|image|max:2048',

        'existing_files' => 'nullable|array',
        'existing_files.*.variant' => 'nullable|string|max:255',
        'existing_files.*.duration' => 'nullable|string|max:20',
        'existing_files.*.drm' => 'nullable|in:0,1',
        'existing_files.*.file' => 'nullable|file|mimetypes:video/mp4,video/mpeg,video/quicktime|max:204800',

        'delete_files' => 'nullable|array',

        'video_files' => 'nullable|array',
        'video_files.*' => 'file|mimetypes:video/mp4,video/mpeg,video/quicktime|max:204800',
        'variants' => 'nullable|array',
        'durations' => 'nullable|array',
        'drms' => 'nullable|array',
    ]);

    // 🔹 Thumbnail Upload
    if ($request->hasFile('thumbnail')) {
        $path = $request->file('thumbnail')->store('thumbnails', 's3');
        Storage::disk('s3')->setVisibility($path, 'public');
        $video->thumbnail = Storage::disk('s3')->url($path);
    }

    // 🔹 Delete Files
    if ($request->filled('delete_files')) {
        foreach ($request->delete_files as $fileId) {
            $file = $video->files()->find($fileId);
            if ($file) {
                $file->delete();
            }
        }
    }

    // 🔹 Update Existing Files
    if ($request->filled('existing_files')) {
        foreach ($request->existing_files as $fileId => $fileData) {
            $existing = $video->files()->find($fileId);
            if (!$existing) continue;

            $updateData = [
                'variant' => $fileData['variant'] ?? $existing->variant,
                'duration' => $fileData['duration'] ?? $existing->duration,
                'drm' => isset($fileData['drm']) ? (bool) $fileData['drm'] : $existing->drm,
            ];

            if (isset($fileData['file']) && $fileData['file'] instanceof \Illuminate\Http\UploadedFile) {
                $file = $fileData['file'];
                $path = $file->store('videos', 's3');
                Storage::disk('s3')->setVisibility($path, 'public');

                $updateData['file_url'] = Storage::disk('s3')->url($path);
                $updateData['meta'] = json_encode([
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime' => $file->getMimeType(),
                ]);
            }

            $existing->update($updateData);
        }
    }

    // 🔹 Add New Files
    $newFiles = $request->file('video_files', []);
    $variants = $request->input('variants', []);
    $durations = $request->input('durations', []);
    $drms = $request->input('drms', []);

    foreach ($newFiles as $index => $file) {
        if (!$file->isValid()) continue;

        $path = $file->store('videos', 's3');
        Storage::disk('s3')->setVisibility($path, 'public');

        $video->files()->create([
            'variant' => $variants[$index] ?? 'Episode ' . ($index + 1),
            'duration' => $durations[$index] ?? null,
            'drm' => isset($drms[$index]) ? (bool) $drms[$index] : false,
            'file_url' => Storage::disk('s3')->url($path),
            'manifest_url' => null,
            'meta' => json_encode([
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ]),
        ]);
    }

    // 🔹 Update Video Info
    $video->update([
        'title' => $request->title,
        'description' => $request->description,
        'category_id' => $request->category_id,
        'status' => $request->status,
    ]);

    return redirect()
        ->route('admin.videos.edit', $video->id)
        ->with('success', 'Video updated successfully!');
}

}
