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

class OtpController extends Controller
{
    //
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:15',
        ]);

        $phone = $request->phone;

        // 📛 Rate limit check: last 5 mins
        $recent = Otp::where('phone', $phone)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->count();

        if ($recent > 2) {
            return response()->json(['message' => 'Too many OTP requests. Please wait.'], 429);
        }

        // ✅ Generate 6-digit OTP
        $otp = rand(100000, 999999);

        // Save in DB with 5 min expiry
        Otp::create([
            'phone' => $phone,
            'otp_code' => $otp,
            'expires_at' => now()->addMinutes(5),
        ]);

        // ✅ Send via Twilio
        try {
            $twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
            $twilio->messages->create($phone, [
                'from' => env('TWILIO_FROM'), // test sandbox number
                'body' => "Your OTP is: {$otp}"
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send OTP', 'error' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'OTP sent successfully']);
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
            return response()->json(['message' => 'Invalid or expired OTP'], 401);
        }

        // ✅ Create/find user
        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            ['name' => 'User_' . Str::random(5), 'password' => bcrypt(Str::random(10))]
        );

        // Delete all OTPs for this user
        Otp::where('phone', $request->phone)->delete();

       // 🕒 Token lifetime = 7 days (in minutes)
    JWTAuth::factory()->setTTL(10080);

    // 🔐 Generate JWT
    $token = JWTAuth::fromUser($user);

    return response()->json([
        'token' => $token,
        'user' => $user,
        'expires_in' => JWTAuth::factory()->getTTL() * 60, // seconds
        'token_type' => 'bearer',
    ]);
    }
}
