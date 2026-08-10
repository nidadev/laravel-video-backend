<?php

namespace App\Services;

class MediaUrlService
{
    public static function cdnUrl(?string $pathOrUrl): ?string
    {
        if (!$pathOrUrl) {
            return null;
        }

        $cdn = rtrim(config('services.cloudfront.url'), '/');

        if (str_starts_with($pathOrUrl, 's3://')) {
            $path = preg_replace('#^s3://[^/]+/#', '', $pathOrUrl);
            return $cdn . '/' . ltrim($path, '/');
        }

        $parsed = parse_url($pathOrUrl);

        if (!empty($parsed['host']) && (
            str_contains($parsed['host'], 's3.') ||
            str_contains($parsed['host'], 's3.amazonaws.com') ||
            str_contains($parsed['host'], 'cloudfront.net')
        )) {
            return $cdn . '/' . ltrim($parsed['path'] ?? '', '/');
        }

        if (str_starts_with($pathOrUrl, 'http')) {
            return $pathOrUrl;
        }

        return $cdn . '/' . ltrim($pathOrUrl, '/');
    }

    public static function s3Path(?string $pathOrUrl): ?string
    {
        if (!$pathOrUrl) {
            return null;
        }

        if (str_starts_with($pathOrUrl, 's3://')) {
            return preg_replace('#^s3://[^/]+/#', '', $pathOrUrl);
        }

        $parsed = parse_url($pathOrUrl);

        if (!empty($parsed['path'])) {
            return ltrim($parsed['path'], '/');
        }

        return ltrim($pathOrUrl, '/');
    }
}
