<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
            'image_url' => 'nullable|url|starts_with:https://|max:2048',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        if ($request->hasFile('image') && $request->filled('image_url')) {
            return back()->withErrors([
                'image' => 'Upload an image or provide an image URL, not both.',
            ])->withInput();
        }

        $projectId = env('FIREBASE_PROJECT_ID');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $accessToken = FirebaseHelper::getAccessToken();

        $users = User::whereNotNull('device_token')->get();

        if ($users->isEmpty()) {
            return back()->with('error', 'No users with device tokens found.');
        }

        // FCM and APNs must be able to fetch the image over public HTTPS.
        $imageUrl = $request->input('image_url');
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('notifications', 'public');
            $imageUrl = Storage::disk('public')->url($imagePath);
        }

        foreach ($users as $user) {
            // Store in DB
            Notification::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'message' => $request->message,
                'image_url' => $imageUrl,
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
                        'image_url' => (string) $imageUrl,
                    ],
                ],
            ];

            if ($imageUrl) {
                $payload['message']['notification']['image'] = $imageUrl;
                $payload['message']['android'] = [
                    'notification' => [
                        'image' => $imageUrl,
                    ],
                ];
                $payload['message']['apns'] = [
                    'payload' => [
                        'aps' => [
                            'mutable-content' => 1,
                        ],
                    ],
                    'fcm_options' => [
                        'image' => $imageUrl,
                    ],
                ];
            }

            Http::withToken($accessToken)
                ->post($url, $payload);
        }

        return back()->with('success', 'Notification sent successfully!');
    }

  
}
