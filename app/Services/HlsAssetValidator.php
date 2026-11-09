<?php

namespace App\Services;

use App\Models\VideoFile;
use Aws\S3\S3Client;
use RuntimeException;

class HlsAssetValidator
{
    private S3Client $s3;

    public function __construct(?S3Client $s3 = null)
    {
        $this->s3 = $s3 ?? new S3Client([
            'version' => 'latest',
            'region' => config('filesystems.disks.s3.region', 'us-east-1'),
        ]);
    }

    /**
     * Confirm the complete HLS tree exists before the catalog exposes it.
     */
    public function assertComplete(VideoFile $file): void
    {
        $manifestUrl = (string) $file->manifest_url;
        $parsed = parse_url($manifestUrl);
        $bucket = $parsed['host'] ?? null;
        $manifestKey = ltrim($parsed['path'] ?? '', '/');
        $expectedPrefix = "hls/{$file->id}/";
        $processedBucket = config('services.media.processed_bucket');

        if (($parsed['scheme'] ?? null) !== 's3' || !$bucket ||
            !$processedBucket || $bucket !== $processedBucket ||
            !str_starts_with($manifestKey, $expectedPrefix) ||
            !str_ends_with(strtolower($manifestKey), '.m3u8')) {
            throw new RuntimeException("Invalid HLS manifest location for video file {$file->id}.");
        }

        $objectKeys = [];
        foreach ($this->s3->getPaginator('ListObjectsV2', [
            'Bucket' => $bucket,
            'Prefix' => $expectedPrefix,
        ]) as $page) {
            foreach ($page['Contents'] ?? [] as $object) {
                $objectKeys[$object['Key']] = true;
            }
        }

        if (!isset($objectKeys[$manifestKey])) {
            throw new RuntimeException("HLS master playlist is missing for video file {$file->id}.");
        }

        $visited = [];
        $this->validatePlaylist($bucket, $manifestKey, $expectedPrefix, $objectKeys, $visited, 0, $file->id);
    }

    /**
     * @param array<string, bool> $objectKeys
     * @param array<string, bool> $visited
     */
    private function validatePlaylist(
        string $bucket,
        string $playlistKey,
        string $expectedPrefix,
        array $objectKeys,
        array &$visited,
        int $depth,
        int $fileId,
    ): void {
        if ($depth > 8) {
            throw new RuntimeException("HLS playlist nesting is too deep for video file {$fileId}.");
        }

        if (isset($visited[$playlistKey])) {
            return;
        }
        $visited[$playlistKey] = true;

        try {
            $body = (string) $this->s3->getObject([
                'Bucket' => $bucket,
                'Key' => $playlistKey,
            ])['Body'];
        } catch (\Throwable $exception) {
            throw new RuntimeException("Unable to read an HLS playlist for video file {$fileId}.", 0, $exception);
        }

        if (!str_starts_with(ltrim($body), '#EXTM3U')) {
            throw new RuntimeException("Invalid HLS playlist for video file {$fileId}.");
        }

        $references = [];
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($line[0] !== '#') {
                $references[] = $line;
            }

            if (preg_match_all('/URI="([^"]+)"/i', $line, $matches)) {
                array_push($references, ...$matches[1]);
            }
        }

        if ($references === []) {
            throw new RuntimeException("HLS playlist has no media references for video file {$fileId}.");
        }

        $hasChildPlaylist = false;
        $hasMediaSegment = false;
        foreach (array_unique($references) as $reference) {
            $referencedKey = $this->resolveReference($playlistKey, $reference, $expectedPrefix, $fileId);
            if (!isset($objectKeys[$referencedKey])) {
                throw new RuntimeException("An HLS playlist references a missing output for video file {$fileId}.");
            }

            if (str_ends_with(strtolower($referencedKey), '.m3u8')) {
                $hasChildPlaylist = true;
                $this->validatePlaylist(
                    $bucket,
                    $referencedKey,
                    $expectedPrefix,
                    $objectKeys,
                    $visited,
                    $depth + 1,
                    $fileId,
                );
            } elseif (preg_match('/\.(?:ts|m4s|mp4|m4v|m4a|cmfv|cmfa|aac|ac3|ec3)$/i', $referencedKey)) {
                $hasMediaSegment = true;
            }
        }

        // Master playlists point to child playlists; leaf playlists must point to media.
        if (!$hasChildPlaylist && !$hasMediaSegment) {
            throw new RuntimeException("HLS media playlist has no segments for video file {$fileId}.");
        }
    }

    private function resolveReference(string $playlistKey, string $reference, string $expectedPrefix, int $fileId): string
    {
        $referenceUrl = parse_url($reference);
        $path = rawurldecode($referenceUrl['path'] ?? $reference);
        $candidate = str_starts_with($path, '/')
            ? ltrim($path, '/')
            : dirname($playlistKey) . '/' . $path;

        $segments = [];
        foreach (explode('/', $candidate) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        $resolved = implode('/', $segments);
        if (!str_starts_with($resolved, $expectedPrefix)) {
            throw new RuntimeException("HLS playlist points outside video file {$fileId}'s output folder.");
        }

        return $resolved;
    }
}
