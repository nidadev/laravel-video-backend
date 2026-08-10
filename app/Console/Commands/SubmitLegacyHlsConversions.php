<?php

namespace App\Console\Commands;

use App\Models\VideoFile;
use App\Services\MediaConvertService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SubmitLegacyHlsConversions extends Command
{
    protected $signature = 'mediaconvert:submit-legacy-hls
        {--limit=25 : Maximum number of jobs to submit in this run}
        {--dry-run : List matching records without submitting jobs}
        {--force : Resubmit even if a MediaConvert job already exists}';

    protected $description = 'Submit MediaConvert HLS jobs for legacy MP4 video files without breaking current MP4 playback.';

    public function handle(MediaConvertService $mediaConvert): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $processedBucket = config('services.media.processed_bucket');

        if (!$processedBucket) {
            $this->error('Missing services.media.processed_bucket configuration.');
            return self::FAILURE;
        }

        $query = VideoFile::query()
            ->whereNotNull('file_url')
            ->where('file_url', 'like', '%.mp4%')
            ->where(function ($query) {
                $query->whereNull('job_status')
                    ->orWhereIn('job_status', ['ERROR', 'CANCELED']);
            })
            ->orderBy('id');

        if (!$this->option('force')) {
            $query->whereNull('job_id');
        }

        $files = $query->limit($limit)->get();

        if ($files->isEmpty()) {
            $this->info('No legacy MP4 files found for HLS conversion.');
            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $sourceUrl = $file->mp4_url ?: $file->file_url;
            $sourcePath = $this->pathFromUrl($sourceUrl);

            if (!$sourcePath || !Str::startsWith($sourcePath, 'videos/')) {
                $this->warn("Skipping video_file {$file->id}: unsupported source path {$sourceUrl}");
                continue;
            }

            $inputS3Url = "s3://{$processedBucket}/{$sourcePath}";
            $outputS3Folder = "s3://{$processedBucket}/hls/{$file->id}/";
            $baseName = pathinfo($sourcePath, PATHINFO_FILENAME);
            $nameModifier = '_hls';
            $manifestUrl = $outputS3Folder . $baseName . $nameModifier . '.m3u8';

            if ($this->option('dry-run')) {
                $this->line("video_file {$file->id}: {$inputS3Url} -> {$manifestUrl}");
                continue;
            }

            try {
                $jobId = $mediaConvert->createHlsJob($inputS3Url, $outputS3Folder, $nameModifier);

                $file->update([
                    'mp4_url' => $sourceUrl,
                    'manifest_url' => $manifestUrl,
                    'job_id' => $jobId,
                    'job_status' => 'SUBMITTED',
                    'job_error' => null,
                ]);

                $this->info("Submitted video_file {$file->id}: {$jobId}");
            } catch (\Throwable $e) {
                $file->update([
                    'job_status' => 'ERROR',
                    'job_error' => $e->getMessage(),
                ]);

                $this->error("Failed video_file {$file->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function pathFromUrl(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (Str::startsWith($url, 's3://')) {
            return preg_replace('#^s3://[^/]+/#', '', $url);
        }

        $path = parse_url($url, PHP_URL_PATH);

        return $path ? ltrim(rawurldecode($path), '/') : ltrim($url, '/');
    }
}
