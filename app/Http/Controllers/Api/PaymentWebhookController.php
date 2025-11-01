<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Webhook;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Helpers\FirebaseHelper;
use App\Models\Notification;

class PaymentWebhookController extends Controller
{
    
public function handleWebhook(Request $request)
{
    \Log::info('🚀 Stripe webhook hit', ['body' => $request->getContent()]);

    $payload = $request->getContent();
    $sig_header = $request->server('HTTP_STRIPE_SIGNATURE');
    $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

    try {
        $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    } catch (\Exception $e) {
        \Log::error('❌ Webhook validation failed: ' . $e->getMessage());
        return response('Invalid payload or signature', 400);
    }

    \Log::info('📦 Stripe event received', ['type' => $event->type]);

    if ($event->type === 'payment_intent.succeeded') {
        $paymentIntent = $event->data->object;

        $userId = $paymentIntent->metadata->user_id ?? null;
        $planId = $paymentIntent->metadata->plan_id ?? null;

        \Log::info('🔍 Metadata', ['userId' => $userId, 'planId' => $planId]);

        $user = User::find($userId);
        $plan = Plan::find($planId);

        if ($user && $plan) {
            // Cancel existing active subscriptions
            $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

            // Create new subscription
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'start_date' => now(),
                'end_date' => now()->addDays($plan->duration_days),
                'status' => 'active',
            ]);

            // ✅ Create database notification
    Notification::create([
        'user_id' => $user->id,
        'title' => 'Payment Successful 🎉',
        'message' => 'Your plan "' . $plan->name . '" has been activated.',
    ]);

    // ✅ Send push notification if device token exists
    if (!empty($user->device_token)) {
        FirebaseHelper::sendPushNotification(
            $user->device_token,
            'Payment Successful 🎉',
            'Your plan "' . $plan->name . '" is now active.'
        );
    }


            \Log::info('✅ Subscription created successfully', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ]);
        } else {
            \Log::warning('⚠️ Missing user or plan', compact('userId', 'planId'));
        }
    }

    return response('Webhook received', 200);
}
}

