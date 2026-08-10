<?php

namespace App\Console\Commands;

use App\Models\VideoFile;
use App\Services\MediaConvertService;
use App\Services\MediaUrlService;
use Illuminate\Console\Command;

class SyncMediaConvertJobs extends Command
{
    protected $signature = 'mediaconvert:sync-jobs';

    protected $description = 'Sync MediaConvert job status and mark videos ready only after all jobs complete.';

    public function handle(MediaConvertService $mediaConvert): int
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

                $file->update([
                    'job_status' => $status,
                    'job_error' => $errorMessage,
                ]);

                if ($status === 'COMPLETE' && $file->manifest_url) {
                    $file->update([
                        'file_url' => MediaUrlService::cdnUrl($file->manifest_url),
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
