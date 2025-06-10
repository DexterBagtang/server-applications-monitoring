<?php

namespace App\Jobs;

use App\Models\DownloadProgress;
use App\Models\Server;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DownloadFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $server;
    protected $remotePath;
    protected $localFileName;
    protected $progressKey;

    public $timeout = 7200; // 2 hours
    public $tries = 3;
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(Server $server, string $remotePath = '/tmp/database.sql', string $localFileName = 'database.sql', string $progressKey = null)
    {
        $this->server = $server;
        $this->remotePath = $remotePath;
        $this->localFileName = $localFileName;
        $this->progressKey = $progressKey ?: 'download_' . $server->id . '_' . Str::random(8);
    }

    /**
     * Execute the job.
     */
    public function handle(SshService $sshService): void
    {
        $localPath = storage_path("app/{$this->localFileName}");

        // Create or get existing download progress record
        $downloadProgress = DownloadProgress::firstOrCreate(
            ['progress_key' => $this->progressKey],
            [
                'server_id' => $this->server->id,
                'remote_path' => $this->remotePath,
                'local_filename' => $this->localFileName,
                'status' => 'pending',
            ]
        );

        try {
            Log::info("Starting file download", [
                'progress_id' => $downloadProgress->id,
                'server_id' => $this->server->id,
                'remote_path' => $this->remotePath,
                'local_path' => $localPath
            ]);

            // Update status to downloading
            $downloadProgress->updateProgress(0, 'downloading');

            // Get SFTP connection
            $sftp = $sshService->getSFTPConnection($this->server);
            $sftp->setTimeout(7200);

            $info = $sftp->stat($this->remotePath);

            // Get remote file size for progress calculation
            $remoteFileSize = $info['size'] ?? false;

            if ($remoteFileSize !== false) {
                $downloadProgress->update(['total_size_mb' => round($remoteFileSize / 1024 / 1024, 2)]);
            }

            // Open local file for writing
            $localFile = fopen($localPath, 'w');
            if (!$localFile) {
                throw new \Exception('Unable to open local file for writing');
            }

            // Download file with progress tracking
            $result = $sftp->get($this->remotePath, $localFile, 0, -1, function ($downloaded) use ($downloadProgress) {
                $downloadedMb = $downloaded / 1024 / 1024;
                $downloadProgress->updateProgress($downloadedMb, 'downloading');
            });

            fclose($localFile);

            if ($result === false) {
                throw new \Exception('Failed to download file from remote server');
            }

            // Mark as complete with final file size
            $finalSize = filesize($localPath);
            $finalSizeMb = $finalSize / 1024 / 1024;
            $downloadProgress->markAsComplete($finalSizeMb);

            Log::info("File download completed successfully", [
                'progress_id' => $downloadProgress->id,
                'server_id' => $this->server->id,
                'local_path' => $localPath,
                'file_size' => $finalSize
            ]);

        } catch (\Exception $e) {
            Log::error("File download failed", [
                'progress_id' => $downloadProgress->id,
                'server_id' => $this->server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $downloadProgress->markAsFailed($e->getMessage());

            // Clean up partial file if it exists
            if (file_exists($localPath)) {
                unlink($localPath);
            }

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Download job failed permanently", [
            'server_id' => $this->server->id,
            'progress_key' => $this->progressKey,
            'error' => $exception->getMessage()
        ]);

        // Mark progress as failed if it exists
        $downloadProgress = DownloadProgress::where('progress_key', $this->progressKey)->first();
        if ($downloadProgress) {
            $downloadProgress->markAsFailed($exception->getMessage());
        }
    }

    /**
     * Get the progress key for this job
     */
    public function getProgressKey(): string
    {
        return $this->progressKey;
    }
}
