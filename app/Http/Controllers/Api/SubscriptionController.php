<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Subscription;

class SubscriptionController extends Controller
{
    //
    // User purchases a subscription plan
   public function purchase(Request $request)
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

}
