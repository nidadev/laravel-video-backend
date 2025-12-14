<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserApiController extends Controller
{
    /**
     * Fetch all users
     */
    public function index()
    {
        $users = User::all();

        return response()->json([
            'message' => 'Users fetched successfully',
            'data' => $users,
            'response' => 200,
            'success' => true,
        ], 200);
    }

    /**
     * Fetch a single user
     */
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'data' => [],
                'response' => 404,
                'success' => false,
            ], 404);
        }

        return response()->json([
            'message' => 'User fetched successfully',
            'data' => $user,
            'response' => 200,
            'success' => true,
        ], 200);
    }

    /**
     * Create or update user
     */
    public function store(Request $request)
    {
        $request->validate([
            'id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'status' => 'nullable|string|in:active,inactive',
            'password' => $request->id ? 'nullable|string|min:6' : 'required|string|min:6',
        ]);

        $data = $request->only(['name', 'email', 'phone', 'status']);

        // Only hash password if provided
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user = User::updateOrCreate(['id' => $request->id], $data);

        return response()->json([
            'message' => $request->id ? 'User updated successfully' : 'User created successfully',
            'data' => $user,
            'response' => 200,
            'success' => true,
        ], 200);
    }

    /**
     * Delete a user
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'data' => [],
                'response' => 404,
                'success' => false,
            ], 404);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
            'data' => [],
            'response' => 200,
            'success' => true,
        ], 200);
    }

public function updateProfile(Request $request)
{
    $user = $request->user();

    $request->validate([
        'name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
        'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    if ($request->filled('name')) {
        $user->name = $request->name;
    }

    if ($request->filled('email')) {
        $user->email = $request->email;
    }

    /* ----------------------------------
       📸 Handle Profile Image Upload
    ---------------------------------- */
    if ($request->hasFile('profile_image')) {

        // delete old image (optional)
        if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
            Storage::disk('public')->delete($user->profile_image);
        }

        $path = $request->file('profile_image')
                        ->store('profiles', 'public'); // storage/app/public/profiles

        $user->profile_image = $path;
    }

    $user->save();

    return response()->json([
        'message' => 'Profile updated successfully',
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_image' => $user->profile_image
                ? asset('storage/' . $user->profile_image)
                : null,
        ],
        'response' => 200,
        'success' => true,
    ]);
}


    // app/Http/Controllers/Api/UserApiController.php
public function saveDeviceToken(Request $request)
{
    $request->validate([
        'device_token' => 'required|string',
    ]);

    $user = $request->user();
    $user->update(['device_token' => $request->device_token]);

    return response()->json([
        'message' => 'Device token saved successfully',
        'response' => 200,
        'success' => true,
    ]);
}


}
