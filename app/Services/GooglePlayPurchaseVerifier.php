<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\AndroidPublisher;
use RuntimeException;

class GooglePlayPurchaseVerifier
{
    public function verifySubscription(string $purchaseToken, string $productId, ?string $packageName = null): array
    {
        $credentialsPath = config('services.google_play.service_account_json');
        $packageName = $packageName ?: config('services.google_play.package_name');

        if (!$credentialsPath || !file_exists($credentialsPath)) {
            throw new RuntimeException('Google Play service account JSON is not configured.');
        }

        if (!$packageName || !$productId) {
            throw new RuntimeException('Google Play package name and product id are required.');
        }

        $client = new GoogleClient();
        $client->setAuthConfig($credentialsPath);
        $client->addScope(AndroidPublisher::ANDROIDPUBLISHER);

        $publisher = new AndroidPublisher($client);
        $purchase = $publisher->purchases_subscriptions->get(
            $packageName,
            $productId,
            $purchaseToken
        );

        $data = $purchase->toSimpleObject();
        $paymentState = $data->paymentState ?? null;
        $expiryTimeMillis = isset($data->expiryTimeMillis) ? (int) $data->expiryTimeMillis : null;

        if ($expiryTimeMillis && $expiryTimeMillis < now()->getTimestampMs()) {
            throw new RuntimeException('Google Play subscription is expired.');
        }

        if ($paymentState !== null && !in_array((int) $paymentState, [1, 2], true)) {
            throw new RuntimeException('Google Play subscription payment is not complete.');
        }

        return [
            'package_name' => $packageName,
            'product_id' => $productId,
            'purchase_token' => $purchaseToken,
            'expiry_time_millis' => $expiryTimeMillis,
            'raw_response' => $data,
        ];
    }
}
