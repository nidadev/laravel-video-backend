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
    if ($email === 'demo@gmail.com') {
        Otp::where('email', $email)->delete(); // clear old OTPs

        Otp::create([
            'email' => $email,
            'otp_code' => 1234,
            'expires_at' => now()->addYears(1) // never expire for demo
        ]);

        return response()->json([
            'message' => 'Demo OTP generated',
            'data' => [
                'email' => $email,
                'otp' => 1234
            ],
            'response' => 200,
            'success' => true,
        ]);
    }

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

public function sendOtp2(Request $request)
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

    // ✅ DEMO ACCOUNT
    if ($email === 'demo@gmail.com') {
        Otp::where('email', $email)->delete(); // clear old OTPs

        Otp::create([
            'email' => $email,
            'otp_code' => 1234,
            'expires_at' => now()->addYears(1) // never expire for demo
        ]);

        return response()->json([
            'message' => 'Demo OTP generated',
            'data' => [
                'email' => $email,
                'otp' => 1234
            ],
            'response' => 200,
            'success' => true,
        ]);
    }

    // 🔐 Rate limit
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

    // Generate OTP
    $otp = rand(1000, 9999);

    // Delete old OTPs
    Otp::where('email', $email)->delete();

    Otp::create([
        'email' => $email,
        'otp_code' => $otp,
        'expires_at' => now()->addMinutes(10),
    ]);

    // Send email
    try {
        Mail::to($email)->send(new SendOtpMail($otp));
    } catch (\Exception $e) {
        \Log::error("OTP Mail Error: " . $e->getMessage());

        return response()->json([
            'message' => 'OTP created but email failed. Please contact support.',
            'data' => [],
            'response' => 500,
            'success' => false,
        ]);
    }

    return response()->json([
        'message' => 'OTP sent successfully to your email',
        'data' => [
            'email' => $email
        ],
        'response' => 200,
        'success' => true,
    ]);
}






public function verifyOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp'   => 'required'
    ]);

    $email = $request->email;

    // ✅ Validate OTP
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

    /* ------------------------------------
       👤 Find or Create User
    ------------------------------------ */
    $user = User::where('email', $email)->first();
    $isNewUser = false;

    if (!$user) {
        $isNewUser = true;
        $user = User::create([
            'email' => $email,
            'name' => 'User_' . Str::random(5),
            'password' => bcrypt(Str::random(10)),
        ]);
    }

    // ❌ Delete OTPs
    Otp::where('email', $email)->delete();

    /* ------------------------------------
       🔐 JWT Token
    ------------------------------------ */
    JWTAuth::factory()->setTTL(10080); // 7 days
    $token = JWTAuth::fromUser($user);

    /* ------------------------------------
       📌 Subscription Handling
    ------------------------------------ */

    // 1️⃣ Get latest active subscription (paid or free)
    $activeSubscription = Subscription::with('plan')
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->where('end_date', '>=', now())
        ->latest('end_date')
        ->first();

    // 2️⃣ If no active subscription exists → give FREE plan
    if (!$activeSubscription) {
        $freePlan = Plan::where('name', 'Free')->first();
        if ($freePlan) {
            $activeSubscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $freePlan->id,
                'start_date' => now(),
                'end_date' => now()->addDays(7), // Free trial 7 days
                'status' => 'active',
            ]);
            $activeSubscription->load('plan');
        }
    }

    /* ------------------------------------
       ✅ RESPONSE
    ------------------------------------ */
    return response()->json([
        'message' => 'OTP verified successfully',
        'data' => [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => $user,
            'subscription' => $activeSubscription,
        ],
        'response' => 200,
        'success' => true,
    ]);
}


public function verifyOtp2(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'otp'   => 'required'
    ]);

    $email = $request->email;

    // ✅ Validate OTP
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

    /* ------------------------------------
       👤 Find or Create User
    ------------------------------------ */
    $user = User::where('email', $email)->first();
    $isNewUser = false;

    if (!$user) {
        $isNewUser = true;

        $user = User::create([
            'email' => $email,
            'name' => 'User_' . Str::random(5),
            'password' => bcrypt(Str::random(10)),
        ]);
    }

    // ❌ Delete OTPs
    Otp::where('email', $email)->delete();

    /* ------------------------------------
       🔐 JWT Token
    ------------------------------------ */
    JWTAuth::factory()->setTTL(10080); // 7 days
    $token = JWTAuth::fromUser($user);

    /* ------------------------------------
       📌 Subscription Logic (IMPORTANT FIX)
    ------------------------------------ */

    // 1️⃣ Try to get active subscription
    $activeSubscription = Subscription::with('plan')
        ->where('user_id', $user->id)
        ->active()
        ->first();

    // 2️⃣ If NO active subscription → give FREE plan
    if (!$activeSubscription) {

        $freePlan = Plan::where('name', 'Free')->first();

        if ($freePlan) {
            $activeSubscription = Subscription::create([
                'user_id'    => $user->id,
                'plan_id'    => $freePlan->id,
                'start_date' => now(),
                'end_date'   => now()->addDays(7), // or null for lifetime free
                'status'     => 'active',
            ]);

            $activeSubscription->load('plan');
        }
    }

    /* ------------------------------------
       ✅ RESPONSE
    ------------------------------------ */
    return response()->json([
        'message' => 'OTP verified successfully',
        'data' => [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => $user,
            'subscription' => $activeSubscription, // ✅ NEVER NULL NOW
        ],
        'response' => 200,
        'success' => true,
    ]);
}

}
