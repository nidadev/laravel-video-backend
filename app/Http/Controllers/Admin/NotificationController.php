<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use App\Helpers\FirebaseHelper;

class NotificationController extends Controller
{
    public function index()
    {
        return view('admin.notifications.index');
    }

    public function send(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $projectId = env('FIREBASE_PROJECT_ID');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $accessToken = FirebaseHelper::getAccessToken();

        $users = User::whereNotNull('device_token')->get();

        if ($users->isEmpty()) {
            return back()->with('error', 'No users with device tokens found.');
        }

        foreach ($users as $user) {
            // Store in DB
            Notification::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'message' => $request->message,
            ]);

            $payload = [
                'message' => [
                    'token' => $user->device_token,
                    'notification' => [
                        'title' => $request->title,
                        'body' => $request->message,
                    ],
                    'data' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'user_id' => (string) $user->id,
                    ],
                ],
            ];

            Http::withToken($accessToken)
                ->post($url, $payload);
        }

        return back()->with('success', 'Notification sent successfully!');
    }

  
}
