<?php

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Video;

class VideoUploadController extends Controller
{
    // ✅ Generate presigned URL
    public function generatePresignedUrl(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'content_type' => 'required|string',
        ]);

        $folder = 'videos';
        $filePath = "{$folder}/" . uniqid() . '-' . $request->filename;

        // 🔹 Local mode fallback
        if (config('filesystems.default') !== 's3') {
            $localUrl = asset('storage/' . $filePath);

            return response()->json([
                'upload_url' => $localUrl,
                'file_url' => $localUrl,
                'message' => 'Local mode active — presigned URL skipped.'
            ]);
        }

        // 🔹 S3 presigned
        $client = Storage::disk('s3')->getClient();
        $expiry = '+10 minutes';

        $command = $client->getCommand('PutObject', [
            'Bucket' => env('AWS_BUCKET'),
            'Key' => $filePath,
            'ACL' => 'public-read',
            'ContentType' => $request->content_type,
        ]);

        $presigned = $client->createPresignedRequest($command, $expiry);

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'file_url' => Storage::disk('s3')->url($filePath),
            'expires_in' => '10 minutes',
        ]);
    }

    // ✅ Store info in DB after upload
    public function storePresigned(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'subcategory' => 'nullable|string|max:100',
            'thumbnail_url' => 'nullable|string',
            'video_files' => 'required|array|min:1',
            'video_files.*.file_url' => 'required|string',
            'video_files.*.variant' => 'nullable|string',
            'video_files.*.duration' => 'nullable|string|max:20',
            'video_files.*.drm' => 'nullable|in:0,1',
        ]);

        $video = Video::create([
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'subcategory' => $request->subcategory,
            'thumbnail' => $request->thumbnail_url,
            'status' => 'ready',
            'created_by' => auth()->id() ?? auth('admin')->id(),
        ]);

        foreach ($request->video_files as $file) {
            $video->files()->create([
                'variant' => $file['variant'] ?? 'Episode',
                'file_url' => $file['file_url'],
                'manifest_url' => null,
                'drm' => isset($file['drm']) ? (bool)$file['drm'] : false,
                'duration' => $file['duration'] ?? null,
                'meta' => json_encode([
                    'original_name' => basename($file['file_url']),
                    'mime' => 'video/mp4',
                ]),
            ]);
        }

        return redirect()->route('admin.videos')->with('success', 'Video uploaded successfully via presigned URL.');
    }
}
