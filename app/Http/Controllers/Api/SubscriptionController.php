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
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($request->plan_id);

        // Cancel existing active subscriptions (optional)
        $user->subscriptions()->active()->update(['status' => 'cancelled']);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'start_date' => now(),
            'end_date' => now()->addDays($plan->duration_days),
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Subscription purchased successfully',
            'subscription' => $subscription,
            'price' => $plan->price,
        ]);
    }

    // Optional: View current user's subscription
    public function current(Request $request)
    {
        $user = $request->user();
        $subscription = $user->activeSubscription();

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        return response()->json($subscription);
    }
}
