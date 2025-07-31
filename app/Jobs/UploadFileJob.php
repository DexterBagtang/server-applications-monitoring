<?php

namespace App\Jobs;

use App\Models\UploadProgress;
use App\Models\Server;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use phpseclib3\Net\SFTP;

class UploadFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $uploadProgress;
    protected $overwrite;

    public $timeout = 7200; // 2 hours
    public $tries = 3;
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(UploadProgress $uploadProgress, bool $overwrite = false)
    {
        $this->uploadProgress = $uploadProgress;
        $this->overwrite = $overwrite;
    }

    /**
     * Execute the job.
     */
    public function handle(SshService $sshService): void
    {
        // Refresh the model to get latest data
        $this->uploadProgress->refresh();

        $localPath = storage_path("app/{$this->uploadProgress->local_path}");
        $remotePath = $this->uploadProgress->remote_path;

        try {
            Log::info("Starting file upload", [
                'progress_id' => $this->uploadProgress->id,
                'server_id' => $this->uploadProgress->server_id,
                'local_path' => $localPath,
                'remote_path' => $remotePath
            ]);

            // Check if local file exists
            if (!file_exists($localPath)) {
                throw new \Exception('Local file not found: ' . $localPath);
            }

            // Update status to uploading
            $this->uploadProgress->updateProgress(0, 'uploading');

            // Get SFTP connection
            $sftp = $sshService->getSFTPConnection($this->uploadProgress->server);
            $sftp->setTimeout(7200);

            // Get local file size for progress calculation
            $localFileSize = filesize($localPath);
            $localFileSizeMb = round($localFileSize / 1024 / 1024, 2);

            // Update total size if not set
            if (!$this->uploadProgress->total_size_mb) {
                $this->uploadProgress->update(['total_size_mb' => $localFileSizeMb]);
            }

            // Check if remote file exists and handle overwrite
            if ($sftp->file_exists($remotePath) && !$this->overwrite) {
                throw new \Exception('Remote file already exists and overwrite is disabled');
            }

            // Ensure remote directory exists
            $remoteDir = dirname($remotePath);
            if ($remoteDir !== '.' && $remoteDir !== '/' && !$sftp->file_exists($remoteDir)) {
                if (!$sftp->mkdir($remoteDir, -1, true)) {
                    throw new \Exception('Failed to create remote directory: ' . $remoteDir);
                }
            }

            // Open local file for reading
            $localFile = fopen($localPath, 'r');
            if (!$localFile) {
                throw new \Exception('Unable to open local file for reading');
            }

            // Upload file with progress tracking
            $result = $sftp->put($remotePath, $localFile, SFTP::SOURCE_LOCAL_FILE, -1, -1, function ($uploaded) {
                $uploadedMb = $uploaded / 1024 / 1024;
                $this->uploadProgress->updateProgress($uploadedMb, 'uploading');
            });

            fclose($localFile);

            if ($result === false) {
                throw new \Exception('Failed to upload file to remote server');
            }

            // Verify upload by checking remote file size
            $remoteFileInfo = $sftp->stat($remotePath);
            $remoteFileSize = $remoteFileInfo['size'] ?? 0;

            if ($remoteFileSize !== $localFileSize) {
                throw new \Exception("Upload verification failed. Local size: {$localFileSize}, Remote size: {$remoteFileSize}");
            }

            // Mark as complete with final file size
            $finalSizeMb = $localFileSize / 1024 / 1024;
            $this->uploadProgress->markAsComplete($finalSizeMb);

            Log::info("File upload completed successfully", [
                'progress_id' => $this->uploadProgress->id,
                'server_id' => $this->uploadProgress->server_id,
                'remote_path' => $remotePath,
                'file_size' => $localFileSize
            ]);

            // Clean up temporary local file
            if (Storage::exists($this->uploadProgress->local_path)) {
                Storage::delete($this->uploadProgress->local_path);
            }

        } catch (\Exception $e) {
            Log::error("File upload failed", [
                'progress_id' => $this->uploadProgress->id,
                'server_id' => $this->uploadProgress->server_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->uploadProgress->markAsFailed($e->getMessage());

            // Clean up temporary local file on failure
            if (Storage::exists($this->uploadProgress->local_path)) {
                Storage::delete($this->uploadProgress->local_path);
            }

            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Upload job failed permanently", [
            'progress_id' => $this->uploadProgress->id,
            'server_id' => $this->uploadProgress->server_id,
            'progress_key' => $this->uploadProgress->progress_key,
            'error' => $exception->getMessage()
        ]);

        // Mark progress as failed
        $this->uploadProgress->markAsFailed($exception->getMessage());

        // Clean up temporary local file
        if (Storage::exists($this->uploadProgress->local_path)) {
            Storage::delete($this->uploadProgress->local_path);
        }
    }

    /**
     * Get the progress key for this job
     */
    public function getProgressKey(): string
    {
        return $this->uploadProgress->progress_key;
    }
}
