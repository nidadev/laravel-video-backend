<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function createCheckoutSession(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = Auth::user();

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = StripeSession::create([
            'payment_method_types' => ['card', 'apple_pay', 'google_pay'],
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
            'customer_email' => $user->email,
            'success_url' => url('/api/payment/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('/api/payment/cancel'),
        ]);

        return response()->json([
            'checkout_url' => $session->url,
        ]);
    }

    public function paymentSuccess(Request $request)
{
    try {
        $sessionId = $request->query('session_id');
        if (!$sessionId) {
            return response()->json([
                'message' => 'Invalid session ID',
                'data' => [],
                'response' => 400,
                'success' => false,
            ], 400);
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        $session = Session::retrieve($sessionId);

        // 🔹 Extract metadata
        $userId = $session->metadata->user_id ?? null;
        $planId = $session->metadata->plan_id ?? null;

        if (!$userId || !$planId) {
            return response()->json([
                'message' => 'Missing user or plan metadata in session',
                'data' => [],
                'response' => 422,
                'success' => false,
            ], 422);
        }

        $user = User::find($userId);
        $plan = Plan::find($planId);

        if (!$user || !$plan) {
            return response()->json([
                'message' => 'Invalid user or plan',
                'data' => [],
                'response' => 404,
                'success' => false,
            ], 404);
        }

        // ⚙️ Create Subscription (if not already active)
        $existing = Subscription::where('user_id', $userId)
            ->where('plan_id', $planId)
            ->where('status', 'active')
            ->first();

        if (!$existing) {
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'start_date' => now(),
                'end_date' => now()->addDays($plan->duration_days),
                'status' => 'active',
            ]);
        }

        return response()->json([
            'message' => 'Payment successful. Subscription activated!',
            'data' => [
                'session_id' => $session->id,
                'plan' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                ],
                'user_id' => $user->id,
                'payment_status' => $session->payment_status,
            ],
            'response' => 200,
            'success' => true,
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Payment success handler failed: '.$e->getMessage());

        return response()->json([
            'message' => 'Failed to verify payment',
            'data' => ['error' => $e->getMessage()],
            'response' => 500,
            'success' => false,
        ], 500);
    }
}
}
