<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoFile;
use App\Services\CloudFrontCookieSigner;
use App\Services\MediaUrlService;
use Illuminate\Http\Request;
use RuntimeException;

class MediaAccessController extends Controller
{
    public function show(Request $request, CloudFrontCookieSigner $signer)
    {
        if (!$request->user() || $request->user()->isBanned()) {
            return response()->json(['message' => 'Viewer access denied.'], 403);
        }

        $validated = $request->validate(['file_id' => 'sometimes|required|integer|min:1']);
        if (!isset($validated['file_id'])) {
            // Older app builds still call this endpoint without a file ID. HLS remains
            // public during the app rollout, so return a compatible empty response.
            return response()->json([
                'cdn_url' => rtrim((string) config('services.cloudfront.url'), '/'),
                'expires_at' => time() + 3600,
                'cookies' => (object) [],
            ]);
        }

        $file = VideoFile::with('video:id,status')->find($validated['file_id']);
        if (!$file || !$file->video || $file->video->status !== 'ready') {
            return response()->json(['message' => 'Video unavailable.'], 404);
        }

        $url = MediaUrlService::cdnUrl($file->file_url);
        $path = parse_url($url ?? '', PHP_URL_PATH);
        $cdnHost = parse_url((string) config('services.cloudfront.url'), PHP_URL_HOST);
        if (!$cdnHost || parse_url($url ?? '', PHP_URL_HOST) !== $cdnHost ||
            !is_string($path) || !preg_match('#^/hls/' . $file->id . '/[^/]+\.m3u8$#', $path)) {
            return response()->json(['message' => 'Signed playback is unavailable for this file.'], 409);
        }

        try {
            return response()->json($signer->issue($file->id));
        } catch (RuntimeException $e) {
            report($e);
            return response()->json(['message' => 'Playback access is temporarily unavailable.'], 503);
        }
    }
}
