<?php

namespace App\Services {
    function config(string $key, $default = null)
    {
        return $GLOBALS['cookie_test_config'][$key] ?? $default;
    }
}

namespace {
    require __DIR__ . '/../../app/Services/CloudFrontCookieSigner.php';

    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    if (!$key || !openssl_pkey_export($key, $privateKey)) {
        throw new RuntimeException('Could not create test key');
    }
    $keyPath = tempnam(sys_get_temp_dir(), 'cookie-key-');
    file_put_contents($keyPath, $privateKey);

    try {
        $GLOBALS['cookie_test_config'] = [
            'services.cloudfront.url' => 'https://cdn.example.test',
            'services.cloudfront.key_pair_id' => 'TESTKEY',
            'services.cloudfront.private_key_path' => $keyPath,
            'services.cloudfront.cookie_ttl_seconds' => 300,
        ];
        $result = (new \App\Services\CloudFrontCookieSigner())->issue(148);
        $decode = static fn (string $value): string => base64_decode(strtr($value, '-~_', '+/='), true);
        $policy = $decode($result['cookies']['CloudFront-Policy']);
        $signature = $decode($result['cookies']['CloudFront-Signature']);
        $statement = json_decode($policy, true, 512, JSON_THROW_ON_ERROR)['Statement'][0];

        if ($result['file_id'] !== 148 || $statement['Resource'] !== 'https://cdn.example.test/hls/148/*') {
            throw new RuntimeException('Cookie policy is not scoped to the requested file');
        }
        $publicKey = openssl_pkey_get_details($key)['key'];
        if (openssl_verify($policy, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Cookie signature did not verify');
        }
        if ($statement['Condition']['DateLessThan']['AWS:EpochTime'] !== $result['expires_at']) {
            throw new RuntimeException('Cookie expiry did not match policy');
        }
        try {
            (new \App\Services\CloudFrontCookieSigner())->issue(0);
            throw new RuntimeException('Invalid file ID was accepted');
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Invalid file ID was accepted') {
                throw $e;
            }
        }
        echo "Scoped CloudFront cookie passed\n";
    } finally {
        unlink($keyPath);
    }
}
