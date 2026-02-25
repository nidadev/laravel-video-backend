<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use App\Models\Season;
use App\Models\VideoFile;
use App\Services\MediaConvertService;


class VideoController extends Controller
{
    //
    public function index()
{
    // Fetch videos with categories
    $videos = Video::with('category')->latest()->paginate(20);;

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
    $seasons = Season::all(); 

    return view('admin.videos.edit', compact('video', 'categories','seasons'));
}


public function createPresigned()
{
    $categories = Category::all();
    $seasons = Season::all();
    return view('admin.videos.upload_presigned', compact('categories','seasons'));
}



public function generatePresignedUrl(Request $request)
{
    $request->validate([
        'filename' => 'required|string',
        'content_type' => 'required|string',
        'type' => 'nullable|string|in:video,thumbnail,video_image', // added video_image
    ]);

    try {
        $s3 = Storage::disk('s3')->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        // Determine folder based on upload type
        $folder = match ($request->type) {
            'thumbnail' => 'thumbnails/',
            'video_image' => 'videos/images/',
            default => 'videos/',
        };

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
            'success' => true,
            'url' => $presignedUrl,
            'file_url' => Storage::disk('s3')->url($key),
        ]);
    } catch (\Exception $e) {
        \Log::error('Presigned URL generation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Failed to generate presigned URL',
            'details' => $e->getMessage(),
        ], 500);
    }
}

public function updatePresigned2(Request $request, $id)
{
    $video = Video::with('files')->findOrFail($id);

    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category_id' => 'required|exists:categories,id',
        'subcategory_id' => 'nullable|exists:subcategories,id',
        'thumbnail' => 'nullable|string',
        'videos' => 'required|array|min:1',
        'videos.*.file_url' => 'required|string',
        'videos.*.image' => 'nullable|string',
        'videos.*.variant' => 'nullable|string|max:255',
        'videos.*.season' => 'nullable|exists:seasons,id',
        'videos.*.drm' => 'nullable|boolean',
        'videos.*.duration' => 'nullable|string|max:50',
        'videos.*.original_name' => 'nullable|string',
        'videos.*.size' => 'nullable|numeric',
        'videos.*.mime' => 'nullable|string',
        'year_of_published' => 'nullable|digits:4|integer|min:1900|max:2100',
        'season_id' => 'nullable|exists:seasons,id',
    ]);

    try {
        // ✅ Update main video
        $video->update([
            'title' => $request->title,
            'description' => $request->description,
            'season_id' => $request->season_id,
            'category_id' => $request->category_id,
            'subcategory_id' => $request->subcategory_id,
            'thumbnail' => $request->thumbnail,
            'year_of_published' => $request->year_of_published,
        ]);

        // Delete old files that are no longer in the request
        $incomingFileUrls = array_column($request->videos, 'file_url');
        $video->files()->whereNotIn('file_url', $incomingFileUrls)->delete();

        // Save or update files
        foreach ($request->videos as $fileData) {
            $existingFile = $video->files()->where('file_url', $fileData['file_url'])->first();

            if ($existingFile) {
                // ✅ Update existing file
                $existingFile->update([
                    'variant' => $fileData['variant'] ?? $existingFile->variant,
                    'season_id' => $fileData['season'] ?? $existingFile->season_id,
                    'image' => $fileData['image'] ?? $existingFile->image,
                    'drm' => $fileData['drm'] ?? $existingFile->drm,
                    'duration' => $fileData['duration'] ?? $existingFile->duration,
                    'meta' => json_encode([
                        'original_name' => $fileData['original_name'] ?? null,
                        'size' => $fileData['size'] ?? null,
                        'mime' => $fileData['mime'] ?? null,
                    ]),
                ]);
            } else {
                // ✅ New file
                $video->files()->create([
                    'file_url' => $fileData['file_url'],
                    'variant' => $fileData['variant'] ?? 'Default',
                    'season_id' => $fileData['season'] ?? null,
                    'image' => $fileData['image'] ?? null,
                    'drm' => $fileData['drm'] ?? false,
                    'duration' => $fileData['duration'] ?? null,
                    'meta' => json_encode([
                        'original_name' => $fileData['original_name'] ?? null,
                        'size' => $fileData['size'] ?? null,
                        'mime' => $fileData['mime'] ?? null,
                    ]),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => '✅ Video updated successfully!',
            'video_id' => $video->id,
        ]);

    } catch (\Exception $e) {
        \Log::error('Video update failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function updatePresigned(Request $request, $id)
{
    $video = Video::with('files')->findOrFail($id);

    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category_id' => 'required|exists:categories,id',
        'subcategory_id' => 'nullable|exists:subcategories,id',
        'thumbnail' => 'nullable|string',
        'videos' => 'required|array|min:1',
        'videos.*.file_url' => 'required|string',
        'videos.*.image' => 'nullable|string',
        'videos.*.variant' => 'nullable|string|max:255',
        'videos.*.season' => 'nullable|exists:seasons,id',
        'videos.*.drm' => 'nullable|boolean',
        'videos.*.duration' => 'nullable|string|max:50',
        'year_of_published' => 'nullable|digits:4|integer|min:1900|max:2100',
        'season_id' => 'nullable|exists:seasons,id',
    ]);

    try {

        $video->update([
            'title' => $request->title,
            'description' => $request->description,
            'season_id' => $request->season_id,
            'category_id' => $request->category_id,
            'subcategory_id' => $request->subcategory_id,
            'thumbnail' => $request->thumbnail,
            'year_of_published' => $request->year_of_published,
        ]);

        $mcService = new \App\Services\MediaConvertService();

        foreach ($request->videos as $fileData) {

            $fileUrl = $fileData['file_url'];

            $existingFile = $video->files()
                ->where('file_url', $fileUrl)
                ->orWhere('mp4_url', $fileUrl)
                ->first();

            // 🔥 If MP4 uploaded again → reconvert
            if (str_ends_with($fileUrl, '.mp4')) {

                $parsedUrl = parse_url($fileUrl);
                $bucket = explode('.', $parsedUrl['host'])[0];
                $path = ltrim($parsedUrl['path'], '/');
                $inputS3Url = "s3://{$bucket}/{$path}";

                if (!$existingFile) {
                    $existingFile = $video->files()->create([
                        'variant' => $fileData['variant'] ?? 'Default',
                        'season_id' => $fileData['season'] ?? null,
                        'mp4_url' => $fileUrl,
                        'file_url' => null,
                        'image' => $fileData['image'] ?? null,
                        'drm' => $fileData['drm'] ?? false,
                        'duration' => $fileData['duration'] ?? null,
                    ]);
                } else {
                    $existingFile->update([
                        'mp4_url' => $fileUrl,
                    ]);
                }

                $outputS3Folder = "s3://{$bucket}/hls/{$existingFile->id}/";
                $originalName = pathinfo($fileUrl, PATHINFO_FILENAME);

                $mcService->createHlsJob($inputS3Url, $outputS3Folder, $originalName);

                $hlsUrl = "https://{$bucket}.s3.us-east-1.amazonaws.com/hls/{$existingFile->id}/{$originalName}.m3u8";

                $existingFile->update([
                    'file_url' => $hlsUrl,
                    'manifest_url' => $outputS3Folder . "{$originalName}.m3u8",
                    'variant' => $fileData['variant'] ?? $existingFile->variant,
                    'season_id' => $fileData['season'] ?? $existingFile->season_id,
                    'image' => $fileData['image'] ?? $existingFile->image,
                    'drm' => $fileData['drm'] ?? $existingFile->drm,
                    'duration' => $fileData['duration'] ?? $existingFile->duration,
                ]);

            } else {

                // ✅ If already HLS (.m3u8) just update metadata
                if ($existingFile) {
                    $existingFile->update([
                        'variant' => $fileData['variant'] ?? $existingFile->variant,
                        'season_id' => $fileData['season'] ?? $existingFile->season_id,
                        'image' => $fileData['image'] ?? $existingFile->image,
                        'drm' => $fileData['drm'] ?? $existingFile->drm,
                        'duration' => $fileData['duration'] ?? $existingFile->duration,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => '✅ Video updated and HLS job started!',
            'video_id' => $video->id,
        ]);

    } catch (\Exception $e) {
        \Log::error('Video update failed: '.$e->getMessage());
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}


// public function storePresigned(Request $request)
// {
//     $request->validate([
//         'title' => 'required|string|max:255',
//         'description' => 'nullable|string',
//         'category_id' => 'required|exists:categories,id',
//         'subcategory_id' => 'nullable|exists:subcategories,id',
//         'thumbnail' => 'nullable|string',
//         'videos' => 'required|array|min:1',
//         'videos.*.file_url' => 'required|string',
//         'videos.*.image' => 'nullable|string', // ✅ use database field name
//         'videos.*.variant' => 'nullable|string|max:255',
// 'videos.*.season' => 'nullable|exists:seasons,id',
//         'videos.*.drm' => 'nullable|boolean',
//         'videos.*.duration' => 'nullable|string|max:50',
//         'videos.*.original_name' => 'nullable|string',
//         'videos.*.size' => 'nullable|numeric',
//         'videos.*.mime' => 'nullable|string',
//         'year_of_published' => 'nullable|digits:4|integer|min:1900|max:2100',
//         'season_id' => 'nullable|exists:seasons,id',


//     ]);

//     try {
//         // ✅ Create main Video record with subcategory_id
//         $video = Video::create([
//             'title' => $request->title,
//             'description' => $request->description,
//             'season_id' => $request->season_id,  
//             'category_id' => $request->category_id,
//             'subcategory_id' => $request->subcategory_id,
//             'thumbnail' => $request->thumbnail,
//             'status' => 'ready',
//             'created_by' => auth()->id() ?? auth('admin')->id(),
//                 'year_of_published' => $request->year_of_published,

//         ]);

//         // ✅ Save uploaded file metadata
//         foreach ($request->videos as $file) {
//             $video->files()->create([
//                 'variant' => $file['variant'] ?? 'Default',
// 'season_id' => $file['season'] ?? null,
//                 'file_url' => $file['file_url'],
//                 'image' => $file['image'] ?? null, // ✅ save in 'image' column
//                 'manifest_url' => null,
//                 'drm' => $file['drm'] ?? false,
//                 'duration' => $file['duration'] ?? null,
//                 'meta' => json_encode([
//                     'original_name' => $file['original_name'] ?? null,
//                     'size' => $file['size'] ?? null,
//                     'mime' => $file['mime'] ?? null,
//                 ]),
//             ]);
//         }

//         return response()->json([
//             'success' => true,
//             'message' => '✅ Video and metadata (including image and season) saved successfully!',
//             'video_id' => $video->id,
//         ]);

//     } catch (\Exception $e) {
//         \Log::error('Presigned store failed: ' . $e->getMessage());
//         return response()->json([
//             'success' => false,
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// }

public function storePresigned(Request $request)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'category_id' => 'required|exists:categories,id',
        'subcategory_id' => 'nullable|exists:subcategories,id',
        'thumbnail' => 'nullable|string',
        'videos' => 'required|array|min:1',
        'videos.*.file_url' => 'required|string',
        'videos.*.image' => 'nullable|string',
        'videos.*.variant' => 'nullable|string|max:255',
        'videos.*.season' => 'nullable|exists:seasons,id',
        'videos.*.drm' => 'nullable|boolean',
        'videos.*.duration' => 'nullable|string|max:50',
        'videos.*.original_name' => 'nullable|string',
        'videos.*.size' => 'nullable|numeric',
        'videos.*.mime' => 'nullable|string',
        'year_of_published' => 'nullable|digits:4|integer|min:1900|max:2100',
        'season_id' => 'nullable|exists:seasons,id',
    ]);

    try {
        $video = Video::create([
            'title' => $request->title,
            'description' => $request->description,
            'season_id' => $request->season_id,
            'category_id' => $request->category_id,
            'subcategory_id' => $request->subcategory_id,
            'thumbnail' => $request->thumbnail,
            'status' => 'processing',
            'created_by' => auth()->id() ?? auth('admin')->id(),
            'year_of_published' => $request->year_of_published,
        ]);

        $mcService = new \App\Services\MediaConvertService();

        foreach ($request->videos as $file) {
            $parsedUrl = parse_url($file['file_url']);
            $bucket = explode('.', $parsedUrl['host'])[0];
            $path = ltrim($parsedUrl['path'], '/');
            $inputS3Url = "s3://{$bucket}/{$path}";

            // create DB record first
            $videoFile = $video->files()->create([
                'variant' => $file['variant'] ?? 'Default',
                'season_id' => $file['season'] ?? null,
                'mp4_url' => $file['file_url'], // original MP4
                'file_url' => null,             // will be HLS URL
                'image' => $file['image'] ?? null,
                'manifest_url' => null,
                'drm' => $file['drm'] ?? false,
                'duration' => $file['duration'] ?? null,
                'meta' => json_encode([
                    'original_name' => $file['original_name'] ?? null,
                    'size' => $file['size'] ?? null,
                    'mime' => $file['mime'] ?? null,
                ]),
            ]);

            // output folder per video_file ID
            $outputS3Folder = "s3://{$bucket}/hls/{$videoFile->id}/";

            // use original filename with spaces as NameModifier
            $originalName = pathinfo($file['file_url'], PATHINFO_FILENAME);

            $jobId = $mcService->createHlsJob($inputS3Url, $outputS3Folder, $originalName);

            // HLS URL — DO NOT encode spaces
            $hlsUrl = "https://{$bucket}.s3.us-east-1.amazonaws.com/hls/{$videoFile->id}/{$originalName}.m3u8";

            $videoFile->update([
                'file_url' => $hlsUrl,
                'manifest_url' => $outputS3Folder . "{$originalName}.m3u8"
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '✅ Video uploaded and HLS job started!',
            'video_id' => $video->id,
        ]);

    } catch (\Exception $e) {
        \Log::error('Presigned store failed: ' . $e->getMessage());
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

public function toggleTrending(Request $request, $id)
{
    $video = Video::findOrFail($id);

    // Checkbox sends "on" or nothing
    $video->is_trending = $request->has('is_trending');
    $video->save();

    return back()->with('success', 'Video trending status updated successfully.');
}


}
