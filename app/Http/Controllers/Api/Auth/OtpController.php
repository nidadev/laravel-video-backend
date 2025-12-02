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
use App\Models\Subscription;
use App\Models\Plan;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail; 

class OtpController extends Controller
{
    //
public function sendOtp(Request $request)
{
    try {
        $request->validate([
            'email' => 'required|email'
        ]);
    } catch (ValidationException $e) {
        return response()->json([
            'message' => $e->validator->errors()->first(),
            'data' => [],
            'response' => 422,
            'success' => false,
        ], 422);
    }

    $email = $request->email;

    // Rate limit: max 3 OTP in last 2 minutes
    $recent = Otp::where('email', $email)
        ->where('created_at', '>=', now()->subMinutes(2))
        ->count();

    if ($recent >= 3) {
        return response()->json([
            'message' => 'Too many OTP requests. Please wait a moment.',
            'data' => [],
            'response' => 429,
            'success' => false,
        ]);
    }

    $otp = rand(1000, 9999);

    // Save OTP
    Otp::create([
        'email' => $email,
        'otp_code' => $otp,
        'expires_at' => now()->addMinutes(15),
    ]);

    // Send Email
    try {
        Mail::to($email)->send(new SendOtpMail($otp));
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to send OTP email.',
            'data' => ['error' => $e->getMessage()],
            'response' => 500,
            'success' => false,
        ]);
    }

    return response()->json([
        'message' => 'OTP sent successfully to your email',
        'data' => [
            'email' => $email,
            'otp_debug' => $otp, // REMOVE IN PRODUCTION
        ],
        'response' => 200,
        'success' => true,
    ]);
}




public function verifyOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required'
    ]);

    $email = $request->email;

    $otpRecord = Otp::where('email', $email)
                    ->where('otp_code', $request->otp)
                    ->latest()
                    ->first();

    if (!$otpRecord || $otpRecord->isExpired()) {
        return response()->json([
            'message' => 'Invalid or expired OTP',
            'data' => [],
            'response' => 401,
            'success' => false,
        ]);
    }

    // Create / get user by email
    $user = User::firstOrCreate(
        ['email' => $email],
        ['name' => 'User_' . Str::random(5), 'password' => bcrypt(Str::random(10))]
    );

    // Delete all OTPs for this email
    Otp::where('email', $email)->delete();

    // Generate JWT
    JWTAuth::factory()->setTTL(10080); // 7 days
    $token = JWTAuth::fromUser($user);

    return response()->json([
        'message' => 'OTP verified successfully',
        'data' => [
            'token' => $token,
            'user' => $user,
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'token_type' => 'bearer'
        ],
        'response' => 200,
        'success' => true,
    ]);
}


}
