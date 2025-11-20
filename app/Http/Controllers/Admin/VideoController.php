<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Video;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use App\Models\Season;

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






public function update(Request $request, $id)
{
    $video = Video::with('files')->findOrFail($id);

    $request->validate([
        'title'=>'required|string|max:255',
        'description'=>'nullable|string',
        'category_id'=>'required|exists:categories,id',
        'subcategory_id'=>'nullable|exists:subcategories,id',
        'status'=>'nullable|string',
        'thumbnail'=>'nullable|string',
        'existing_files'=>'nullable|array',
        'delete_files'=>'nullable|array',
    ]);

    try {
        $video->update([
            'title'=>$request->title,
            'description'=>$request->description,
            'category_id'=>$request->category_id,
            'subcategory_id'=>$request->subcategory_id,
            'status'=>$request->status ?? 'ready',
            'thumbnail'=>$request->thumbnail ?? $video->thumbnail,
        ]);

        // Delete removed files
        if($request->filled('delete_files')){
            $video->files()->whereIn('id',$request->delete_files)->delete();
        }

        // Update existing files
        if($request->filled('existing_files')){
            foreach($request->existing_files as $fileData){
                if(!isset($fileData['id'])) continue;
                $file = $video->files()->find($fileData['id']); if(!$file) continue;

                $file->update([
                    'variant'=>$fileData['variant'] ?? $file->variant,
'season_id' => $fileData['season_id'] ?? $file->season_id,
                    'duration'=>$fileData['duration'] ?? $file->duration,
                    'drm'=>$fileData['drm'] ?? $file->drm,
                    'image'=>$fileData['image'] ?? $file->image,
                    'file_url'=>$fileData['file_url'] ?? $file->file_url,
                ]);
            }
        }

        // New video files
        if($request->has('new_videos')){
            foreach($request->file('new_videos') as $idx => $file){
                $path = 'videos/'.uniqid().'-'.$file->getClientOriginalName();
                Storage::disk('s3')->put($path, file_get_contents($file), ['ACL'=>'public-read']);
                $imageUrl = null;
                if($request->hasFile('new_images') && isset($request->file('new_images')[$idx])){
                    $img = $request->file('new_images')[$idx];
                    $imgPath = 'thumbnails/'.uniqid().'-'.$img->getClientOriginalName();
                    Storage::disk('s3')->put($imgPath, file_get_contents($img), ['ACL'=>'public-read']);
                    $imageUrl = Storage::disk('s3')->url($imgPath);
                }

                $video->files()->create([
                    'variant'=>$request->new_variants[$idx] ?? 'Default',
'season_id' => $request->new_seasons[$idx] ?? null,
                    'file_url'=>Storage::disk('s3')->url($path),
                    'image'=>$imageUrl,
                    'drm'=>$request->new_drms[$idx] ?? 0,
                    'duration'=>$request->new_durations[$idx] ?? null,
                ]);
            }
        }

        return response()->json(['success'=>true,'message'=>'Video updated successfully!']);
    } catch(\Exception $e){
        \Log::error($e->getMessage());
        return response()->json(['success'=>false,'error'=>$e->getMessage()]);
    }
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
        'videos.*.image' => 'nullable|string', // ✅ use database field name
        'videos.*.variant' => 'nullable|string|max:255',
'videos.*.season' => 'nullable|exists:seasons,id',
        'videos.*.drm' => 'nullable|boolean',
        'videos.*.duration' => 'nullable|string|max:50',
        'videos.*.original_name' => 'nullable|string',
        'videos.*.size' => 'nullable|numeric',
        'videos.*.mime' => 'nullable|string',
    ]);

    try {
        // ✅ Create main Video record with subcategory_id
        $video = Video::create([
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'subcategory_id' => $request->subcategory_id,
            'thumbnail' => $request->thumbnail,
            'status' => 'ready',
            'created_by' => auth()->id() ?? auth('admin')->id(),
        ]);

        // ✅ Save uploaded file metadata
        foreach ($request->videos as $file) {
            $video->files()->create([
                'variant' => $file['variant'] ?? 'Default',
'season_id' => $file['season'] ?? null,
                'file_url' => $file['file_url'],
                'image' => $file['image'] ?? null, // ✅ save in 'image' column
                'manifest_url' => null,
                'drm' => $file['drm'] ?? false,
                'duration' => $file['duration'] ?? null,
                'meta' => json_encode([
                    'original_name' => $file['original_name'] ?? null,
                    'size' => $file['size'] ?? null,
                    'mime' => $file['mime'] ?? null,
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '✅ Video and metadata (including image and season) saved successfully!',
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
