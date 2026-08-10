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
    public function sendOtp2(Request $request)
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
            'otp' => 1234,
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
                'phone' => null,
                'otp' => $otp,
                'otp_debug' => $otp,
            ]);
        }
        $this->sendSmsOtp($phone, $otp);
        return $this->responseJson('OTP sent successfully to your phone', [
            'email' => null,
            'phone' => $phone,
            'otp' => $otp,
            'otp_debug' => $otp,
        ]);
    } catch (\Exception $e) {
        return $this->responseJson('Failed to send OTP.', ['error' => $e->getMessage()], 500, false);
    }
}

public function sendOtp(Request $request)
{
    $input = $request->all();

    if (($input['phone'] ?? null) === '1234567890') {
        $input['phone'] = '+11234567890';
    }

    $validator = Validator::make($input, [
        'email' => [
            'nullable',
            'email',
            'required_without:phone',
        ],
        'phone' => [
            'nullable',
            'string',
            'required_without:email',
            'regex:/^\+[1-9]\d{7,14}$/',
        ],
    ]);

    if ($validator->fails()) {
        return $this->responseJson(
            $validator->errors()->first(),
            [],
            422,
            false
        );
    }

    $email = $input['email'] ?? null;
    $phone = $input['phone'] ?? null;

    /*
    |--------------------------------------------------------------------------
    | Demo phone
    |--------------------------------------------------------------------------
    | Supported:
    | +11234567890
    | 1234567890
    |
    | But store/use the demo phone internally as E.164.
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Demo Email
    |--------------------------------------------------------------------------
    */

    if ($email === 'demo@gmail.com') {

        Otp::where('email', $email)->delete();

        Otp::create([
            'email' => $email,
            'phone' => null,
            'otp_code' => 1234,
            'expires_at' => now()->addYears(1),
        ]);

        return $this->responseJson(
            'Demo OTP generated',
            [
                'email' => $email,
                'phone' => null,
                'otp' => 1234,
                'otp_debug' => 1234,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Demo Phone
    |--------------------------------------------------------------------------
    */

    if ($phone === '+11234567890') {

        Otp::where('phone', $phone)->delete();

        Otp::create([
            'email' => null,
            'phone' => $phone,
            'otp_code' => 1234,
            'expires_at' => now()->addYears(1),
        ]);

        return $this->responseJson(
            'Demo OTP generated',
            [
                'email' => null,
                'phone' => $phone,
                'otp' => 1234,
                'otp_debug' => 1234,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */

    $recentQuery = Otp::where(
        'created_at',
        '>=',
        now()->subMinutes(2)
    );

    if ($email) {
        $recentQuery->where('email', $email);
    } else {
        $recentQuery->where('phone', $phone);
    }

    if ($recentQuery->count() >= 3) {
        return $this->responseJson(
            'Too many OTP requests. Please wait a moment.',
            [],
            429,
            false
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Generate OTP
    |--------------------------------------------------------------------------
    */

    $otp = random_int(1000, 9999);

    Otp::create([
        'email' => $email,
        'phone' => $phone,
        'otp_code' => $otp,
        'expires_at' => now()->addMinutes(15),
    ]);

    try {

        /*
        |--------------------------------------------------------------------------
        | Email OTP
        |--------------------------------------------------------------------------
        */

        if ($email) {

            Mail::to($email)->send(
                new SendOtpMail($otp)
            );

            return $this->responseJson(
                'OTP sent successfully',
                [
                    'email' => $email,
                    'phone' => null,
                    'otp' => $otp,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Phone OTP
        |--------------------------------------------------------------------------
        */

        $this->sendSmsOtp($phone, $otp);

        return $this->responseJson(
            'OTP sent successfully',
            [
                'email' => null,
                'phone' => $phone,
                'otp' => $otp,
            ]
        );

    } catch (\Throwable $e) {

        \Log::error(
            'OTP sending failed',
            [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]
        );

        return $this->responseJson(
            'Failed to send OTP. Please try again later.',
            [],
            500,
            false
        );
    }
}


private function sendSmsOtp(string $phone, int $otp): void
{
    $sid = env('TWILIO_SID');
    $token = env('TWILIO_AUTH_TOKEN');
    $from = env('TWILIO_FROM') ?: env('TWILIO_PHONE_NUMBER');
    $messagingServiceSid = env('TWILIO_MESSAGING_SERVICE_SID');

    if (!$sid || !$token || (!$from && !$messagingServiceSid)) {
        throw new \RuntimeException('Twilio SMS configuration is incomplete.');
    }

    $params = [
        'body' => 'Your BytDrama verification code is ' . $otp,
    ];

    if ($messagingServiceSid) {
        $params['messagingServiceSid'] = $messagingServiceSid;
    } else {
        $params['from'] = $from;
    }

    $client = new Client($sid, $token);
    $client->messages->create($phone, $params);
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




public function verifyOtp2(Request $request)
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
            $endDate = $freePlan->duration_days > 0 ? now()->addDays($freePlan->duration_days) : now()->addYears(20);
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

public function verifyOtp(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => [
            'nullable',
            'email',
            'required_without:phone',
        ],

        'phone' => [
            'nullable',
            'string',
            'required_without:email',
            'regex:/^\+[1-9]\d{7,14}$/',
        ],

        'otp' => [
            'required',
            'digits:4',
        ],
    ]);

    if ($validator->fails()) {
        return $this->responseJson(
            $validator->errors()->first(),
            [],
            422,
            false
        );
    }

    $email = $request->input('email');
    $phone = $request->input('phone');

    /*
    |--------------------------------------------------------------------------
    | Demo phone
    |--------------------------------------------------------------------------
    */

    if ($phone === '1234567890') {
        $phone = '+11234567890';
    }

    /*
    |--------------------------------------------------------------------------
    | Find OTP
    |--------------------------------------------------------------------------
    */

    $otpQuery = Otp::where('otp_code', $request->otp);

    if ($email) {
        $otpQuery->where('email', $email);
    } else {
        $otpQuery->where('phone', $phone);
    }

    $otpRecord = $otpQuery
        ->latest()
        ->first();

    if (!$otpRecord || $otpRecord->isExpired()) {

        return $this->responseJson(
            'Invalid or expired OTP',
            [],
            401,
            false
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Find or create user
    |--------------------------------------------------------------------------
    */

    if ($email) {

        $user = User::where('email', $email)->first();

    } else {

        // IMPORTANT:
        // Exact E.164 lookup
        $user = User::where('phone', $phone)->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Create user
    |--------------------------------------------------------------------------
    */

    if (!$user) {

        $user = User::create([
            'email' => $email,
            'phone' => $phone,
            'name' => 'User_' . Str::random(5),
            'password' => bcrypt(Str::random(20)),
        ]);

    } else {

        /*
        |--------------------------------------------------------------------------
        | Update missing email/phone
        |--------------------------------------------------------------------------
        */

        $updates = [];

        if ($email && !$user->email) {
            $updates['email'] = $email;
        }

        if ($phone && !$user->phone) {
            $updates['phone'] = $phone;
        }

        if (!empty($updates)) {
            $user->update($updates);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Delete used OTP
    |--------------------------------------------------------------------------
    */

    if ($email) {

        Otp::where('email', $email)
            ->delete();

    } else {

        Otp::where('phone', $phone)
            ->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | JWT
    |--------------------------------------------------------------------------
    */

    JWTAuth::factory()->setTTL(10080);

    $token = JWTAuth::fromUser($user);

    /*
    |--------------------------------------------------------------------------
    | Subscription
    |--------------------------------------------------------------------------
    */

    $activeSubscription = Subscription::with('plan')
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->where('end_date', '>=', now())
        ->latest('end_date')
        ->first();

    if (!$activeSubscription) {

        $freePlan = Plan::where('name', 'Free')->first();

        if ($freePlan) {

            $endDate = $freePlan->duration_days > 0
                ? now()->addDays($freePlan->duration_days)
                : now()->addYears(20);

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

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    return $this->responseJson(
        'OTP verified successfully',
        [
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => $user->fresh(),
            'subscription' => $activeSubscription,
        ]
    );
}




private function normalizePhone(?string $phone): ?string
{
    if (!$phone) {
        return null;
    }

    $phone = trim($phone);

    // Demo phone
    if ($phone === '1234567890') {
        return '+11234567890';
    }

    // Real phones must already be E.164
    return $phone;
}

}
