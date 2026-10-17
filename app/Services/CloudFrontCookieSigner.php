<?php

namespace App\Services;

use RuntimeException;

class CloudFrontCookieSigner
{
    public function issue(): array
    {
        $cdn = rtrim((string) config('services.cloudfront.url'), '/');
        $keyId = (string) config('services.cloudfront.key_pair_id');
        $keyPath = (string) config('services.cloudfront.private_key_path');
        $ttl = (int) config('services.cloudfront.cookie_ttl_seconds', 3600);

        if (!str_starts_with($cdn, 'https://') || !$keyId || !$keyPath || !is_readable($keyPath) || $ttl < 60 || $ttl > 7200) {
            throw new RuntimeException('CloudFront viewer signing is not configured.');
        }

        $key = openssl_pkey_get_private(file_get_contents($keyPath));
        if (!$key) {
            throw new RuntimeException('CloudFront signing key is invalid.');
        }

        $expires = time() + $ttl;
        $policy = json_encode([
            'Statement' => [[
                'Resource' => $cdn . '/*',
                'Condition' => ['DateLessThan' => ['AWS:EpochTime' => $expires]],
            ]],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (!openssl_sign($policy, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('CloudFront signing failed.');
        }

        $encode = static fn (string $value): string => strtr(base64_encode($value), '+/=', '-~_');

        return [
            'cdn_url' => $cdn,
            'expires_at' => $expires,
            'cookies' => [
                'CloudFront-Policy' => $encode($policy),
                'CloudFront-Signature' => $encode($signature),
                'CloudFront-Key-Pair-Id' => $keyId,
                'CloudFront-Hash-Algorithm' => 'SHA256',
            ],
        ];
    }
}
