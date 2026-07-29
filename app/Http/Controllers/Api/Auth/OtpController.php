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
    $request->validate([
        'email' => 'nullable|email|required_without:phone',
        'phone' => 'nullable|string|required_without:email',
    ]);
    $email = $request->email;
    $phone = $this->normalizePhone($request->phone);
    if ($email === 'demo@gmail.com' || $phone === '1234567890') {
        Otp::where(function ($q) use ($email, $phone) {
            if ($email) {
                $q->orWhere('email', $email);
            }
            if ($phone) {
                $q->orWhere('phone', $phone);
            }
        })->delete();
        Otp::create([
            'email' => $email ?: 'demo@gmail.com',
            'phone' => $phone,
            'otp_code' => 1234,
            'expires_at' => now()->addYears(1),
        ]);
        return $this->responseJson('Demo OTP generated', [
            'email' => $email ?: 'demo@gmail.com',
            'phone' => $phone,
            'otp_debug' => 1234,
        ]);
    }
    $recent = Otp::where(function ($q) use ($email, $phone) {
        if ($email) {
            $q->where('email', $email);
        } else {
            $q->where('phone', $phone);
        }
    })->where('created_at', '>=', now()->subMinutes(2))->count();
    if ($recent >= 3) {
        return $this->responseJson('Too many OTP requests. Please wait a moment.', [], 429, false);
    }
    $otp = rand(1000, 9999);
    Otp::create([
        'email' => $email,
        'phone' => $phone,
        'otp_code' => $otp,
        'expires_at' => now()->addMinutes(15),
    ]);
    try {
        if ($email) {
            Mail::to($email)->send(new SendOtpMail($otp));
            return $this->responseJson('OTP sent successfully to your email', [
                'email' => $email,
                'otp_debug' => $otp,
            ]);
        }
        $this->sendSmsOtp($phone, $otp);
        return $this->responseJson('OTP sent successfully to your phone', [
            'phone' => $phone,
            'otp_debug' => $otp,
        ]);
    } catch (\Exception $e) {
        return $this->responseJson('Failed to send OTP.', ['error' => $e->getMessage()], 500, false);
    }
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
    if ($email == 'demo@gmail.com') {
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
                'otp_debug' => 1234
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

private function sendSmsOtp(string $phone, int $otp): void
{
    $client = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
    $client->messages->create($phone, [
        'from' => env('TWILIO_FROM'),
        'body' => 'Your BytDrama verification code is ' . $otp,
    ]);
}
private function responseJson(string $message, array $data, int $status = 200, bool $success = true)
{
    return response()->json([
        'message' => $message,
        'data' => $data,
        'response' => $status,
        'success' => $success,
    ], $status);
}




public function verifyOtp(Request $request)
{
    $request->validate([
        'email' => 'nullable|email|required_without:phone',
        'phone' => 'nullable|string|required_without:email',
        'otp' => 'required',
    ]);
    $email = $request->email;
    $phone = $this->normalizePhone($request->phone);
    $otpRecord = Otp::where('otp_code', $request->otp)
        ->when($email, fn ($q) => $q->where('email', $email))
        ->when(!$email && $phone, fn ($q) => $q->where('phone', $phone))
        ->latest()
        ->first();
    if (!$otpRecord || $otpRecord->isExpired()) {
        return $this->responseJson('Invalid or expired OTP', [], 401, false);
    }
    if ($email) {
        $user = User::where('email', $email)->first();
    } else {
        $user = User::where('phone', $phone)->first();
    }
    if (!$user) {
        $user = User::create([
            'email' => $email ?: ($phone === '1234567890' ? 'demo@gmail.com' : null),
            'phone' => $phone,
            'name' => 'User_' . Str::random(5),
            'password' => bcrypt(Str::random(10)),
        ]);
    } else {
        $updates = [];
        if ($email && !$user->email) {
            $updates['email'] = $email;
        }
        if ($phone && !$user->phone) {
            $updates['phone'] = $phone;
        }
        if ($updates) {
            $user->update($updates);
        }
    }
    Otp::where(function ($q) use ($email, $phone) {
        if ($email) {
            $q->orWhere('email', $email);
        }
        if ($phone) {
            $q->orWhere('phone', $phone);
        }
    })->delete();
    JWTAuth::factory()->setTTL(10080);
    $token = JWTAuth::fromUser($user);
    $activeSubscription = Subscription::with('plan')
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->where('end_date', '>=', now())
        ->latest('end_date')
        ->first();
    if (!$activeSubscription) {
        $freePlan = Plan::where('name', 'Free')->first();
        if ($freePlan) {
            $endDate = $freePlan->duration_days > 0 ? now()->addDays($freePlan->duration_days) : now()
>addYears(20);
            $activeSubscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $freePlan->id,
                'start_date' => now(),
                'end_date' => $endDate,
                'status' => 'active',
            ]);
            $activeSubscription->load('plan');
        }
    }
    return $this->responseJson('OTP verified successfully', [
        'token' => $token,
        'token_type' => 'bearer',
        'expires_in' => JWTAuth::factory()->getTTL() * 60,
        'user' => $user->fresh(),
        'subscription' => $activeSubscription,
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


private function normalizePhone(?string $phone): ?string
{
   if (!$phone) {
       return null;
   }
   $phone = preg_replace('/[^0-9+]/', '', trim($phone));
    if ($phone === '1234567890') {
        return '1234567890';
    }
    if (str_starts_with($phone, '+')) {
        return $phone;
    }
    if (str_starts_with($phone, '0')) {
        return '+92' . ltrim($phone, '0');
    }
    if (strlen($phone) === 10) {
        return '+1' . $phone;
    }
    return '+' . $phone;
}

}
