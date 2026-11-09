<?php

namespace App\Console\Commands;

use App\Models\VideoFile;
use App\Services\HlsAssetValidator;
use App\Services\MediaConvertService;
use App\Services\MediaUrlService;
use Illuminate\Console\Command;

class SyncMediaConvertJobs extends Command
{
    protected $signature = 'mediaconvert:sync-jobs';

    protected $description = 'Sync MediaConvert job status and mark videos ready only after all jobs complete.';

    public function handle(MediaConvertService $mediaConvert, HlsAssetValidator $hlsAssetValidator): int
    {
        $files = VideoFile::with('video')
            ->whereNotNull('job_id')
            ->whereNotIn('job_status', ['COMPLETE', 'ERROR', 'CANCELED'])
            ->get();

        foreach ($files as $file) {
            try {
                $job = $mediaConvert->getJob($file->job_id);
                $status = $job['Job']['Status'] ?? null;
                $errorMessage = $job['Job']['ErrorMessage'] ?? null;

                if ($status === 'COMPLETE') {
                    if (!$file->manifest_url) {
                        throw new \RuntimeException("MediaConvert completed without an HLS manifest for video file {$file->id}.");
                    }

                    $hlsAssetValidator->assertComplete($file);
                    $file->update([
                        'job_status' => 'COMPLETE',
                        'job_error' => null,
                        'file_url' => MediaUrlService::cdnUrl($file->manifest_url),
                    ]);
                } else {
                    $file->update([
                        'job_status' => $status,
                        'job_error' => $errorMessage,
                    ]);
                }

                if ($file->video) {
                    $hasPendingJobs = $file->video->files()
                        ->whereNotNull('job_id')
                        ->where('job_status', '!=', 'COMPLETE')
                        ->exists();

                    $hasFailedJobs = $file->video->files()
                        ->whereIn('job_status', ['ERROR', 'CANCELED'])
                        ->exists();

                    if ($hasFailedJobs) {
                        $file->video->update(['status' => 'disabled']);
                    } elseif (!$hasPendingJobs) {
                        $file->video->update(['status' => 'ready']);
                    }
                }
            } catch (\Throwable $e) {
                $file->update([
                    'job_status' => 'ERROR',
                    'job_error' => $e->getMessage(),
                ]);

                if ($file->video) {
                    $file->video->update(['status' => 'disabled']);
                }
            }
        }

        $this->info("Synced {$files->count()} MediaConvert job(s).");

        return self::SUCCESS;
    }
}
