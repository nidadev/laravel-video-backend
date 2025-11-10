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


class OtpController extends Controller
{
    //
    public function sendOtp(Request $request)
{
    try {
        $request->validate([
            'prefix' => 'nullable|string|max:5', // example: +92
            'phone' => 'required|string|min:6|max:15',
        ]);
    } catch (ValidationException $e) {
        return response()->json([
            'message' => $e->validator->errors()->first(),
            'data' => [],
            'response' => 422,
            'success' => false,
        ], 422);
    }

    // ✅ Combine prefix and phone
    $prefix = $request->prefix ?? '+92'; // default +92 if not provided
    $phone = $prefix . ltrim($request->phone, '+');

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

    // ✅ Generate 4-digit OTP
    $otp = rand(1000, 9999);

    // Save OTP to DB
    Otp::create([
        'phone' => $phone,
        'otp_code' => $otp,
        'expires_at' => now()->addMinutes(15),
    ]);

    // ✅ (Optional) Twilio sending logic commented
    /*
    try {
        $twilio = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
        $twilio->messages->create($phone, [
            'from' => env('TWILIO_FROM'),
            'body' => "🔐 Your verification code is: {$otp}. It expires in 15 minutes."
        ]);
    } catch (\Exception $e) {
        \Log::error('Twilio SMS failed: ' . $e->getMessage());
        return response()->json([
            'message' => 'Failed to send OTP via SMS',
            'data' => ['error' => $e->getMessage()],
            'response' => 500,
            'success' => false,
        ], 500);
    }
    */

    // ✅ Success response
    return response()->json([
        'message' => 'OTP sent successfully via SMS',
        'data' => [
            'phone' => $phone,
            'otp' => $otp, // ⚠️ remove in production
        ],
        'response' => 200,
        'success' => true,
    ], 200);
}


 public function verifyOtp(Request $request)
{
    $request->validate([
        'prefix' => 'nullable|string|max:5',
        'phone' => 'required|string',
        'otp' => 'required',
    ]);

    // ✅ Combine prefix and phone
    $prefix = $request->prefix ?? '+92';
    $phone = $prefix . ltrim($request->phone, '+');

    $otpRecord = Otp::where('phone', $phone)
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
        ['phone' => $phone],
        ['name' => 'User_' . Str::random(5), 'password' => bcrypt(Str::random(10))]
    );

    // 🧹 Delete all OTPs for this phone
    Otp::where('phone', $phone)->delete();

    // 🕒 JWT lifetime 7 days
    JWTAuth::factory()->setTTL(10080);
    $token = JWTAuth::fromUser($user);

    // ✅ Subscription (if exists)
    $subscription = $user->subscriptions()
        ->active()
        ->with('plan')
        ->latest('end_date')
        ->first();

    $subscriptionData = $subscription ? [
        'plan_name' => $subscription->plan->name ?? null,
        'plan_price' => $subscription->plan->price ?? null,
        'duration_days' => $subscription->plan->duration_days ?? null,
        'ads_enabled' => $subscription->plan->ads_enabled ?? null,
        'start_date' => $subscription->start_date,
        'end_date' => $subscription->end_date,
        'status' => $subscription->status,
    ] : null;

    return response()->json([
        'message' => 'OTP verified successfully',
        'data' => [
            'token' => $token,
            'user' => $user,
            'subscription_status' => $subscription ? 'active' : 'inactive',
            'subscription' => $subscriptionData,
            'expires_in' => JWTAuth::factory()->getTTL() * 60, // seconds
            'token_type' => 'bearer',
        ],
        'response' => 200,
        'success' => true,
    ], 200);
}

}
