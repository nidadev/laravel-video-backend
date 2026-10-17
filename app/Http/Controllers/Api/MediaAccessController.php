<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CloudFrontCookieSigner;
use Illuminate\Http\Request;
use RuntimeException;

class MediaAccessController extends Controller
{
    public function show(Request $request, CloudFrontCookieSigner $signer)
    {
        if (!$request->user() || $request->user()->isBanned()) {
            return response()->json(['message' => 'Viewer access denied.'], 403);
        }

        try {
            return response()->json($signer->issue());
        } catch (RuntimeException $e) {
            report($e);
            return response()->json(['message' => 'Playback access is temporarily unavailable.'], 503);
        }
    }
}
