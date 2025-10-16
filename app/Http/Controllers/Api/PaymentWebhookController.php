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

class PaymentWebhookController extends Controller
{
    /*public function handleWebhook(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = env('STRIPE_WEBHOOK_SECRET'); // You’ll get this from Stripe Dashboard

        try {
            $event = Webhook::constructEvent(
                $payload, $sigHeader, $endpointSecret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        Log::info('Stripe Webhook received: ' . $event->type);

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;

                $email = $session->customer_email ?? null;
                $user = User::where('email', $email)->first();

                if ($user && isset($session->metadata->plan_id)) {
                    $plan = Plan::find($session->metadata->plan_id);
                    if ($plan) {
                        Subscription::create([
                            'user_id' => $user->id,
                            'plan_id' => $plan->id,
                            'start_date' => Carbon::now(),
                            'end_date' => Carbon::now()->addDays($plan->duration_days),
                            'status' => 'active',
                        ]);
                    }
                }
                break;

            case 'checkout.session.async_payment_failed':
                Log::warning('Stripe payment failed for session: ' . $event->data->object->id);
                break;

            case 'invoice.payment_failed':
                Log::warning('Invoice payment failed for customer: ' . $event->data->object->customer);
                break;
        }

        return response()->json(['status' => 'success']);
    }*/

        public function handleWebhook(Request $request)
{
    $payload = $request->getContent();
    $sig_header = $request->server('HTTP_STRIPE_SIGNATURE');
    $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

    try {
        $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    } catch (\UnexpectedValueException $e) {
        return response('Invalid payload', 400);
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        return response('Invalid signature', 400);
    }

    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;

        $user = User::find($session->metadata->user_id ?? null);
        $plan = Plan::find($session->metadata->plan_id ?? null);

        if ($user && $plan) {
            // Cancel existing active subs
            $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

            // Create new subscription
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'start_date' => now(),
                'end_date' => now()->addDays($plan->duration_days),
                'status' => 'active',
            ]);
        }
    }

    return response('Webhook received', 200);
}
}

