<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Twilio\Rest\Client;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;


class OtpController extends Controller
{
    //
  public function sendOtp(Request $request)
{
    try {
        $request->validate([
            'phone' => 'required|string|min:10|max:15',
        ]);
    } catch (ValidationException $e) {
        return response()->json([
            'message' => $e->validator->errors()->first(), // first error message
            'data' => [],
            'response' => 422,
            'success' => false,
        ], 422);
    }

    $phone = $request->phone;

    // 📛 Rate limit check
    $recent = Otp::where('phone', $phone)
        ->where('created_at', '>=', now()->subMinutes(2))
        ->count();

    if ($recent > 2) {
        return response()->json([
            'message' => 'Too many OTP requests. Please wait.',
            'data' => [],
            'response' => 429,
            'success' => false,
        ], 429);
    }

    // ✅ Generate OTP
    $otp = rand(100000, 999999);

    Otp::create([
        'phone' => $phone,
        'otp_code' => $otp,
        'expires_at' => now()->addMinutes(15),
    ]);

    return response()->json([
        'message' => 'Otp send successfully',
        'data' => [
            'otp' => $otp,
            'phone' => $phone
        ],
        'response' => 200,
        'success' => true,
    ], 200);
}


    // 🔐 Verify OTP & issue JWT
   public function verifyOtp(Request $request)
{
    $request->validate([
        'phone' => 'required',
        'otp' => 'required',
    ]);

    $otpRecord = Otp::where('phone', $request->phone)
                    ->where('otp_code', $request->otp)
                    ->latest()
                    ->first();

    if (!$otpRecord || $otpRecord->isExpired()) {
        return response()->json([
            'message' => 'Invalid or expired OTP',
            'data' => [],
            'response' => 401,
            'success' => false,
        ], 401);
    }

    // ✅ Create or find user
    $user = User::firstOrCreate(
        ['phone' => $request->phone],
        ['name' => 'User_' . Str::random(5), 'password' => bcrypt(Str::random(10))]
    );

    // 🧹 Delete all OTPs for this phone
    Otp::where('phone', $request->phone)->delete();

    // 🕒 Set JWT lifetime to 7 days
    JWTAuth::factory()->setTTL(10080);

    // 🔐 Generate JWT token
    $token = JWTAuth::fromUser($user);

    return response()->json([
        'message' => 'Otp verified successfully',
        'data' => [
            'token' => $token,
            'user' => $user,
            'expires_in' => JWTAuth::factory()->getTTL() * 60, // in seconds
            'token_type' => 'bearer',
        ],
        'response' => 200,
        'success' => true,
    ], 200);
}

}
