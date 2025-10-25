<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Stripe\StripeClient;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionController extends Controller
{
    //
    // User purchases a subscription plan
   /*public function purchase(Request $request)
{
    try {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($request->plan_id);

        // ✅ Cancel existing active subscriptions (optional)
        $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

        // ✅ Create new subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'start_date' => now(),
            'end_date' => now()->addDays($plan->duration_days),
            'status' => 'active',
        ]);

        $data = [
            'subscription' => $subscription,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'duration_days' => $plan->duration_days,
            ],
        ];

        return response()->json([
            'message' => 'Subscription purchase Successfully',
            'data' => $data,
            'response' => 200,
            'success' => true,
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validation failed',
            'data' => $e->errors(),
            'response' => 422,
            'success' => false,
        ], 422);

    } catch (\Exception $e) {
        \Log::error('Subscription purchase failed: ' . $e->getMessage());

        return response()->json([
            'message' => 'Failed to purchase subscription',
            'data' => [],
            'response' => 500,
            'success' => false,
        ], 500);
    }
}*/



public function purchase(Request $request)
{
    try {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($request->plan_id);

        $stripe = new StripeClient(env('STRIPE_SECRET'));

        // ✅ Create Stripe Checkout Session
        $session = $stripe->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'customer_email' => $user->email ?? null,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $plan->name,
                    ],
                    'unit_amount' => $plan->price * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => env('APP_URL') . '/api/payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => env('APP_URL') . '/payment/cancel',
            'metadata' => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
            ],
        ]);

        return response()->json([
            'message' => 'Checkout session created successfully',
            'data' => [
                'checkout_url' => $session->url,
            ],
            'response' => 200,
            'success' => true,
        ]);

    } catch (\Exception $e) {
        \Log::error('Subscription purchase failed: ' . $e->getMessage());

        return response()->json([
            'message' => 'Failed to initiate payment',
            'data' => ['error' => $e->getMessage()],
            'response' => 500,
            'success' => false,
        ]);
    }
}

public function paymentSuccess(Request $request)
{
    try {
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));

        $sessionId = $request->get('session_id');
        $session = $stripe->checkout->sessions->retrieve($sessionId);

        if (!$session || $session->payment_status !== 'paid') {
            return response()->json([
                'message' => 'Payment not verified or incomplete.',
                'success' => false,
            ], 400);
        }

        $userId = $session->metadata->user_id ?? null;
        $planId = $session->metadata->plan_id ?? null;

        if (!$userId || !$planId) {
            return response()->json([
                'message' => 'Invalid metadata',
                'success' => false,
            ], 400);
        }

        $user = User::findOrFail($userId);
        $plan = Plan::findOrFail($planId);

        // Determine subscription duration
        $startDate = now();
        switch (strtolower($plan->type)) {
            case 'weekly':
                $endDate = $startDate->copy()->addWeek();
                break;
            case 'monthly':
                $endDate = $startDate->copy()->addMonth();
                break;
            case 'yearly':
                $endDate = $startDate->copy()->addYear();
                break;
            default:
                $endDate = $startDate->copy()->addDays(7); // default for free or trial plan
        }

        // Store subscription
        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Subscription created successfully.',
            'data' => [
                'plan' => $plan->name,
                'start_date' => $startDate->toDateTimeString(),
                'end_date' => $endDate->toDateTimeString(),
            ],
            'success' => true,
        ]);
    } catch (\Exception $e) {
        \Log::error('Payment success handler failed: ' . $e->getMessage());

        return response()->json([
            'message' => 'Failed to handle successful payment.',
            'data' => ['error' => $e->getMessage()],
            'success' => false,
        ]);
    }
}


    // Optional: View current user's subscription
   public function current(Request $request)
{
    try {
        $user = $request->user();
        $subscription = $user->activeSubscription();

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
                'data' => [],
                'response' => 404,
                'success' => false,
            ], 404);
        }

        return response()->json([
            'message' => 'Api Call Successfully',
            'data' => [
                'subscription' => $subscription,
                'plan' => optional($subscription->plan)->only(['id', 'name', 'price', 'duration_days']),
            ],
            'response' => 200,
            'success' => true,
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Error fetching current subscription: ' . $e->getMessage());

        return response()->json([
            'message' => 'Something went wrong',
            'data' => [],
            'response' => 500,
            'success' => false,
        ], 500);
    }
}

public function getPlanDetails()
{
    try {
        $plans = Plan::select('id', 'name', 'price', 'duration_days', 'ads_enabled')->get();

        if ($plans->isEmpty()) {
            return response()->json([
                'message' => 'No plans available',
                'data' => [],
                'response' => 404,
                'success' => false,
            ], 404);
        }

        return response()->json([
            'message' => 'Plans retrieved successfully',
            'data' => $plans,
            'response' => 200,
            'success' => true,
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Failed to fetch plan details: ' . $e->getMessage());

        return response()->json([
            'message' => 'Failed to fetch plan details',
            'data' => ['error' => $e->getMessage()],
            'response' => 500,
            'success' => false,
        ], 500);
    }
}

}
