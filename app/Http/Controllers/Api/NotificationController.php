<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    //
      public function getnotify(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
                                    ->latest()
                                    ->get();

        return response()->json([
            'message' => 'Notifications fetched successfully',
            'data' => $notifications,
            'response' => 200,
            'success' => true,
        ]);
    }
}
