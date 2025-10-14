<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{
public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
        ]);
    }

 public function login(Request $request)
    {
        $request->validate([
            //'email' => 'required|email',
            //'password' => 'required',
            'phone' => 'required|string'
        ]);

        //$user = User::where('email', $request->email)->first();
            $user = User::where('phone', $request->phone)->first();


        if (! $user /*|| ! Hash::check($request->password, $user->password)*/) {
            throw ValidationException::withMessages([
                //'email' => ['The provided credentials are incorrect.'],
                                'phone' => ['The provided credentials are incorrect.'],

            ]);
        }

      // Create token
    $tokenResult = $user->createToken('api-token');

    // Access the token model instance to set expiry
    $token = $tokenResult->accessToken;
    $token->expires_at = now()->addDay();
    $token->save();

    return response()->json([
        'message' => 'Login successful',
        'token' => $tokenResult->plainTextToken,  // send the plain token string to client
        'user' => $user,
        'expires_at' => $token->expires_at->toDateTimeString(),
    ]);

}

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    
}
