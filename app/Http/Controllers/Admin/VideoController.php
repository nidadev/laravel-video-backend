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
    try {

        $video = Video::with('files')->findOrFail($id);

        // Delete main thumbnail
        if ($video->thumbnail) {
            $thumbnailPath = parse_url($video->thumbnail, PHP_URL_PATH);

            if ($thumbnailPath) {
                Storage::disk('s3')->delete(ltrim($thumbnailPath, '/'));
            }
        }


        // Delete all episode files
        foreach ($video->files as $file) {

            // Delete HLS folder: hls/{video_file_id}/
            Storage::disk('s3')->deleteDirectory('hls/' . $file->id);


            // Delete original MP4
            if ($file->mp4_url) {

                $mp4Path = parse_url($file->mp4_url, PHP_URL_PATH);

                if ($mp4Path) {
                    Storage::disk('s3')->delete(ltrim($mp4Path, '/'));
                }
            }


            // Delete episode image if exists
            if ($file->image && $file->image != $video->thumbnail) {

                $imagePath = parse_url($file->image, PHP_URL_PATH);

                if ($imagePath) {
                    Storage::disk('s3')->delete(ltrim($imagePath, '/'));
                }
            }


            // Delete video_files row
            $file->delete();
        }


        // Delete main video row
        $video->delete();


        return redirect()
            ->route('admin.videos')
            ->with('success', 'Video files deleted successfully');


    } catch (\Exception $e) {

        \Log::error('Video delete failed: '.$e->getMessage());

        return redirect()
            ->route('admin.videos')
            ->with('error', 'Delete failed: '.$e->getMessage());
    }
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
        'videos.*.id' => 'nullable|exists:video_files,id',
        'videos.*.file_url' => 'required|string',
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

        $video->refresh();


       /*
|--------------------------------------------------------------------------
| Delete removed episodes from DB + S3
|--------------------------------------------------------------------------
*/

$submittedIds = collect($request->videos)
    ->pluck('id')
    ->filter()
    ->toArray();


$removedFiles = $video->files()
    ->whereNotIn('id', $submittedIds)
    ->get();


foreach ($removedFiles as $removedFile) {

    // Delete original MP4
    if ($removedFile->mp4_url) {
        $this->deleteS3Object($removedFile->mp4_url);
    }


    // Delete HLS folder (m3u8 + segments)
    if ($removedFile->manifest_url) {

        $this->deleteS3Folder(
            $removedFile->manifest_url
        );

    }


    // Delete database record
    $removedFile->delete();
}


        $mcService = new \App\Services\MediaConvertService();


        foreach ($request->videos as $fileData) {

            $fileUrl = $fileData['file_url'];

            $existingFile = null;


            if (!empty($fileData['id'])) {
                $existingFile = $video->files()
                    ->where('id', $fileData['id'])
                    ->first();
            }


            /*
            |--------------------------------------------------------------------------
            | New MP4 upload / Replace existing video
            |--------------------------------------------------------------------------
            */
            if (str_ends_with(strtolower($fileUrl), '.mp4')) {


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
                        'image' => $video->thumbnail,
                        'drm' => $fileData['drm'] ?? 1,
                        'duration' => $fileData['duration'] ?? null,
                    ]);

                } else {

                    $existingFile->update([
                        'mp4_url' => $fileUrl,
                    ]);

                }


                $outputS3Folder = "s3://{$bucket}/hls/{$existingFile->id}/";

                $originalName = pathinfo($fileUrl, PATHINFO_FILENAME);


                $mcService->createHlsJob(
                    $inputS3Url,
                    $outputS3Folder,
                    $originalName
                );


                $hlsUrl = "https://{$bucket}.s3.us-east-1.amazonaws.com/hls/{$existingFile->id}/{$originalName}.m3u8";


                $existingFile->update([
                    'file_url' => $hlsUrl,
                    'manifest_url' => $outputS3Folder . "{$originalName}.m3u8",
                    'variant' => $fileData['variant'] ?? $existingFile->variant,
                    'season_id' => $fileData['season'] ?? $existingFile->season_id,
                    'image' => $video->thumbnail,
                    'drm' => $fileData['drm'] ?? 1,
                    'duration' => $fileData['duration'] ?? $existingFile->duration,
                ]);


            } else {


                /*
                |--------------------------------------------------------------------------
                | Existing HLS file update
                |--------------------------------------------------------------------------
                */
                if ($existingFile) {

                    $existingFile->update([
                        'variant' => $fileData['variant'] ?? $existingFile->variant,
                        'season_id' => $fileData['season'] ?? $existingFile->season_id,
                        'image' => $video->thumbnail,
                        'drm' => $fileData['drm'] ?? 1,
                        'duration' => $fileData['duration'] ?? $existingFile->duration,
                    ]);

                }

            }
        }


        return redirect()
            ->route('admin.videos')
            ->with('success', '✅ Video updated successfully');


    } catch (\Exception $e) {

        \Log::error('Video update failed: '.$e->getMessage());

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}

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
        'videos.*.variant' => 'nullable|string|max:255',
        'videos.*.season' => 'nullable|exists:seasons,id',
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

            // Main thumbnail only
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



            /*
            Create episode record
            */

            $videoFile = $video->files()->create([

                'variant' => $file['variant'] ?? 'Default',

                'season_id' => $file['season'] ?? null,


                // Original uploaded MP4
                'mp4_url' => $file['file_url'],


                // Will update after MediaConvert
                'file_url' => null,


                // SAME MAIN VIDEO THUMBNAIL
                'image' => $video->thumbnail,


                'manifest_url' => null,


                // DEFAULT DRM ENABLED
                'drm' => 1,


                'duration' => $file['duration'] ?? null,


                'meta' => json_encode([

                    'original_name' => $file['original_name'] ?? null,

                    'size' => $file['size'] ?? null,

                    'mime' => $file['mime'] ?? null,

                ]),

            ]);



            /*
            MediaConvert HLS
            */

            $outputS3Folder =
                "s3://{$bucket}/hls/{$videoFile->id}/";



            $originalName =
                pathinfo($file['file_url'], PATHINFO_FILENAME);



            $mcService->createHlsJob(
                $inputS3Url,
                $outputS3Folder,
                $originalName
            );



            $hlsUrl =
            "https://{$bucket}.s3.us-east-1.amazonaws.com/hls/{$videoFile->id}/{$originalName}.m3u8";



            $videoFile->update([

                'file_url' => $hlsUrl,

                'manifest_url' =>
                    $outputS3Folder . "{$originalName}.m3u8",

            ]);

        }



        return response()->json([

            'success' => true,

            'message' => '✅ Video uploaded and HLS job started!',

            'video_id' => $video->id,

        ]);



    } catch (\Exception $e) {


        \Log::error(
            'Presigned store failed: '.$e->getMessage()
        );


        return response()->json([

            'success' => false,

            'error' => $e->getMessage(),

        ],500);

    }
}

/*public function storePresigned(Request $request)
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
}*/



public function mostWatched()
{
    $videos = Video::withCount('views')
        ->orderByDesc('views_count')
        ->paginate(20);

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

private function deleteS3Object($url)
{
    $parsed = parse_url($url);

    $key = ltrim($parsed['path'], '/');

    \Storage::disk('s3')->delete($key);
}


private function deleteS3Folder($url)
{
    $parsed = parse_url($url);

    $path = ltrim($parsed['path'], '/');

    // Example:
    // hls/74/episode1.m3u8

    $folder = dirname($path);


    $files = \Storage::disk('s3')->allFiles($folder);


    if (!empty($files)) {
        \Storage::disk('s3')->delete($files);
    }
}

}
