<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AppleAppStorePurchaseVerifier
{
    public function verifySubscription(array $payload): array
    {
        if (!empty($payload['purchase_token'])) {
            return $this->verifyReceipt($payload);
        }

        if (!empty($payload['transaction_id'])) {
            return $this->verifyTransaction($payload);
        }

        throw new RuntimeException('Apple purchase token or transaction id is required.');
    }

    private function verifyReceipt(array $payload): array
    {
        $sharedSecret = config('services.apple.shared_secret');

        if (!$sharedSecret) {
            throw new RuntimeException('Apple shared secret is not configured.');
        }

        $response = $this->postVerifyReceipt(
            $this->receiptUrl($payload['environment'] ?? null),
            $payload['purchase_token'],
            $sharedSecret
        );

        if (($response['status'] ?? null) === 21007) {
            $response = $this->postVerifyReceipt(
                'https://sandbox.itunes.apple.com/verifyReceipt',
                $payload['purchase_token'],
                $sharedSecret
            );
        }

        if (($response['status'] ?? null) !== 0) {
            throw new RuntimeException('Apple receipt verification failed with status ' . ($response['status'] ?? 'unknown') . '.');
        }

        $item = $this->findLatestReceiptItem($response, $payload);

        if (!$item) {
            throw new RuntimeException('Apple receipt does not contain the requested subscription.');
        }

        $expiryTimeMillis = isset($item['expires_date_ms']) ? (int) $item['expires_date_ms'] : null;

        if ($expiryTimeMillis && $expiryTimeMillis < now()->getTimestampMs()) {
            throw new RuntimeException('Apple subscription is expired.');
        }

        return [
            'source' => 'verify_receipt',
            'environment' => $response['environment'] ?? ($payload['environment'] ?? null),
            'product_id' => $item['product_id'] ?? $payload['product_id'],
            'transaction_id' => $item['transaction_id'] ?? $payload['transaction_id'] ?? null,
            'original_transaction_id' => $item['original_transaction_id'] ?? $payload['original_transaction_id'] ?? null,
            'expiry_time_millis' => $expiryTimeMillis,
            'raw_response' => $response,
        ];
    }

    private function postVerifyReceipt(string $url, string $receiptData, string $sharedSecret): array
    {
        $response = Http::timeout(20)->post($url, [
            'receipt-data' => $receiptData,
            'password' => $sharedSecret,
            'exclude-old-transactions' => true,
        ]);

        if (!$response->ok()) {
            throw new RuntimeException('Apple receipt verification HTTP request failed.');
        }

        return $response->json() ?: [];
    }

    private function verifyTransaction(array $payload): array
    {
        $transactionId = $payload['transaction_id'];
        $baseUrl = $this->serverApiBaseUrl($payload['environment'] ?? null);
        $response = Http::withToken($this->serverApiJwt())
            ->timeout(20)
            ->get($baseUrl . '/inApps/v1/transactions/' . rawurlencode($transactionId));

        if (!$response->ok()) {
            throw new RuntimeException('Apple transaction verification failed.');
        }

        $data = $response->json() ?: [];
        $transaction = $this->decodeSignedPayload($data['signedTransactionInfo'] ?? null);

        if (($transaction['bundleId'] ?? null) && config('services.apple.bundle_id') && $transaction['bundleId'] !== config('services.apple.bundle_id')) {
            throw new RuntimeException('Apple transaction bundle id does not match.');
        }

        if (($transaction['productId'] ?? null) && !empty($payload['product_id']) && $transaction['productId'] !== $payload['product_id']) {
            throw new RuntimeException('Apple transaction product id does not match.');
        }

        $expiryTimeMillis = isset($transaction['expiresDate']) ? (int) $transaction['expiresDate'] : null;

        if ($expiryTimeMillis && $expiryTimeMillis < now()->getTimestampMs()) {
            throw new RuntimeException('Apple subscription is expired.');
        }

        return [
            'source' => 'app_store_server_api',
            'environment' => $transaction['environment'] ?? ($payload['environment'] ?? null),
            'product_id' => $transaction['productId'] ?? $payload['product_id'] ?? null,
            'transaction_id' => $transaction['transactionId'] ?? $transactionId,
            'original_transaction_id' => $transaction['originalTransactionId'] ?? $payload['original_transaction_id'] ?? null,
            'expiry_time_millis' => $expiryTimeMillis,
            'raw_response' => $data,
            'transaction' => $transaction,
        ];
    }

    private function findLatestReceiptItem(array $response, array $payload): ?array
    {
        $items = $response['latest_receipt_info'] ?? [];
        $productId = $payload['product_id'] ?? null;
        $transactionId = $payload['transaction_id'] ?? null;

        $matches = array_values(array_filter($items, function ($item) use ($productId, $transactionId) {
            if ($transactionId && ($item['transaction_id'] ?? null) === $transactionId) {
                return true;
            }

            return $productId && ($item['product_id'] ?? null) === $productId;
        }));

        if (!$matches) {
            return null;
        }

        usort($matches, fn ($a, $b) => (int) ($b['expires_date_ms'] ?? 0) <=> (int) ($a['expires_date_ms'] ?? 0));

        return $matches[0];
    }

    private function receiptUrl(?string $environment): string
    {
        return strtolower((string) $environment) === 'sandbox'
            ? 'https://sandbox.itunes.apple.com/verifyReceipt'
            : 'https://buy.itunes.apple.com/verifyReceipt';
    }

    private function serverApiBaseUrl(?string $environment): string
    {
        return strtolower((string) $environment) === 'sandbox'
            ? 'https://api.storekit-sandbox.itunes.apple.com'
            : 'https://api.storekit.itunes.apple.com';
    }

    private function serverApiJwt(): string
    {
        $issuerId = config('services.apple.issuer_id');
        $keyId = config('services.apple.key_id');
        $bundleId = config('services.apple.bundle_id');
        $privateKey = $this->privateKey();

        if (!$issuerId || !$keyId || !$bundleId || !$privateKey) {
            throw new RuntimeException('Apple App Store Server API credentials are not configured.');
        }

        return JWT::encode([
            'iss' => $issuerId,
            'iat' => now()->timestamp,
            'exp' => now()->addMinutes(20)->timestamp,
            'aud' => 'appstoreconnect-v1',
            'bid' => $bundleId,
        ], $privateKey, 'ES256', $keyId);
    }

    private function privateKey(): ?string
    {
        $keyPath = config('services.apple.private_key_path');

        if ($keyPath && file_exists($keyPath)) {
            return file_get_contents($keyPath) ?: null;
        }

        return config('services.apple.private_key');
    }

    private function decodeSignedPayload(?string $jws): array
    {
        if (!$jws || substr_count($jws, '.') < 2) {
            return [];
        }

        $payload = explode('.', $jws)[1] ?? '';
        $payload = strtr($payload, '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);

        return json_decode(base64_decode($payload), true) ?: [];
    }
}
