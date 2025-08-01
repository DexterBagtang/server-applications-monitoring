<?php

namespace App\Http\Controllers;

use App\Jobs\UploadFileJob;
use App\Models\UploadProgress;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use phpseclib3\Net\SFTP;

// Define all SFTP type constants that phpseclib3 expects
if (!defined('NET_SFTP_TYPE_REGULAR')) {
    define('NET_SFTP_TYPE_REGULAR', 1);
}
if (!defined('NET_SFTP_TYPE_DIRECTORY')) {
    define('NET_SFTP_TYPE_DIRECTORY', 2);
}
if (!defined('NET_SFTP_TYPE_SYMLINK')) {
    define('NET_SFTP_TYPE_SYMLINK', 3);
}
if (!defined('NET_SFTP_TYPE_SPECIAL')) {
    define('NET_SFTP_TYPE_SPECIAL', 4);
}
if (!defined('NET_SFTP_TYPE_UNKNOWN')) {
    define('NET_SFTP_TYPE_UNKNOWN', 5);
}
if (!defined('NET_SFTP_TYPE_SOCKET')) {
    define('NET_SFTP_TYPE_SOCKET', 6);
}
if (!defined('NET_SFTP_TYPE_CHAR_DEVICE')) {
    define('NET_SFTP_TYPE_CHAR_DEVICE', 7);
}
if (!defined('NET_SFTP_TYPE_BLOCK_DEVICE')) {
    define('NET_SFTP_TYPE_BLOCK_DEVICE', 8);
}
if (!defined('NET_SFTP_TYPE_FIFO')) {
    define('NET_SFTP_TYPE_FIFO', 9);
}

class UploadController extends Controller
{
    /**
     * Upload a file to a server
     */
    public function uploadFile(Server $server, Request $request): JsonResponse
    {
//        dd($request->all());
        $validator = Validator::make($request->all(), [
//            'file' => 'required|file|max:2048000', // 2GB max
            'remote_path' => 'required|string',
            'overwrite' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->input('file');
        $remotePath = $request->input('remote_path');
        $overwrite = $request->boolean('overwrite', false);

        // Generate unique progress key
        $progressKey = 'upload_' . $server->id . '_' . Str::random(8);

        // Store file temporarily
        $localPath = storage_path("app/".$file['name']);

        $fileSizeMb = round(filesize($localPath) / 1024 / 1024, 2);

        // Ensure remote path includes filename
        $remoteFilePath = rtrim($remotePath, '/') . '/' . $file['name'];

        // Create upload progress record
        $uploadProgress = UploadProgress::create([
            'progress_key' => $progressKey,
            'server_id' => $server->id,
            'local_path' => $file['name'],
            'remote_path' => $remoteFilePath,
            'original_filename' => $file['name'],
            'total_size_mb' => $fileSizeMb,
            'status' => 'pending',
        ]);

        // Dispatch upload job
        UploadFileJob::dispatch($uploadProgress, $overwrite);

        return response()->json([
            'message' => 'Upload started',
            'progress_id' => $uploadProgress->id,
            'progress_key' => $progressKey,
            'job_id' => Str::uuid(),
            'progress_endpoint' => route('upload.progress', ['key' => $progressKey])
        ], 202);
    }

    /**
     * Upload multiple files to a server
     */
    public function uploadMultipleFiles(Server $server, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|max:10',
            'files.*' => 'file|max:2048000',
            'remote_path' => 'required|string',
            'overwrite' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $files = $request->file('files');
        $remotePath = $request->input('remote_path');
        $overwrite = $request->boolean('overwrite', false);
        $uploads = [];

        foreach ($files as $file) {
            $progressKey = 'upload_' . $server->id . '_' . Str::random(8);
            $localPath = $file->store('temp-uploads');
            $fileSizeMb = round($file->getSize() / 1024 / 1024, 2);
            $remoteFilePath = rtrim($remotePath, '/') . '/' . $file->getClientOriginalName();

            $uploadProgress = UploadProgress::create([
                'progress_key' => $progressKey,
                'server_id' => $server->id,
                'local_path' => $localPath,
                'remote_path' => $remoteFilePath,
                'original_filename' => $file->getClientOriginalName(),
                'total_size_mb' => $fileSizeMb,
                'status' => 'pending',
            ]);

            $uploads[] = [
                'progress_key' => $progressKey,
                'upload_id' => $uploadProgress->id,
                'filename' => $file->getClientOriginalName(),
                'progress_endpoint' => route('upload.progress', ['key' => $progressKey])
            ];

            // Dispatch upload job for each file
            UploadFileJob::dispatch($uploadProgress, $overwrite);
        }

        return response()->json([
            'message' => count($uploads) . ' uploads started',
            'uploads' => $uploads
        ], 202);
    }

    /**
     * Browse remote directory structure via SFTP
     */
    public function browseRemoteDirectory(Server $server, Request $request): JsonResponse
    {
        $path = $request->get('path', '/');

        try {
            $sftp = new SFTP($server->ip_address, $server->agentConnection->port ?? 22);

            $login = $sftp->login($server->agentConnection->username, $server->agentConnection->password);

            if (!$login) {
                return response()->json(['error' => 'Failed to authenticate with server'], 401);
            }

            // Get directory listing with attributes in a single call
            $rawFiles = $sftp->rawlist($path);

            if ($rawFiles === false) {
                return response()->json(['error' => 'Failed to read directory or path does not exist'], 404);
            }

            $fileList = [];

            foreach ($rawFiles as $filename => $attributes) {
                // Skip . and .. entries
                if ($filename === '.' || $filename === '..') {
                    continue;
                }

                $fullPath = rtrim($path, '/') . '/' . $filename;

                // Check if it's a directory using the type attribute or mode
                $isDirectory = false;
                if (isset($attributes['type'])) {
                    // Use type attribute if available (more reliable)
                    $isDirectory = $attributes['type'] === NET_SFTP_TYPE_DIRECTORY;
                } elseif (isset($attributes['mode'])) {
                    // Fallback to mode check
                    $isDirectory = ($attributes['mode'] & 0040000) !== 0;
                }

                // Check if directory is writable (approximate check)
                $isWritable = true; // Default to true, could be enhanced with actual permission check

                $fileList[] = [
                    'name' => $filename,
                    'path' => $fullPath,
                    'is_directory' => $isDirectory,
                    'is_writable' => $isWritable,
                    'size' => $attributes['size'] ?? 0,
                    'modified' => isset($attributes['mtime']) ? date('Y-m-d H:i:s', $attributes['mtime']) : null,
                    'permissions' => isset($attributes['mode']) ? substr(sprintf('%o', $attributes['mode']), -4) : null,
                ];
            }

            // Sort: directories first, then files, both alphabetically
            usort($fileList, function ($a, $b) {
                if ($a['is_directory'] === $b['is_directory']) {
                    return strcasecmp($a['name'], $b['name']);
                }
                return $a['is_directory'] ? -1 : 1;
            });

            // Add parent directory entry if not at root
            if ($path !== '/' && $path !== '') {
                $parentPath = dirname($path);

                // Fix: Ensure we always use forward slashes for Unix/Linux paths
                $parentPath = str_replace('\\', '/', $parentPath);
                if ($parentPath === '.' || $parentPath === '') {
                    $parentPath = '/';
                }

                array_unshift($fileList, [
                    'name' => '..',
                    'path' => $parentPath,
                    'is_directory' => true,
                    'is_writable' => true,
                    'size' => 0,
                    'modified' => null,
                    'permissions' => null,
                    'is_parent' => true
                ]);
            }

            return response()->json([
                'current_path' => $path,
                'files' => $fileList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to connect to server: ' . $e->getMessage()
            ], 500);
        } finally {
            // Ensure SFTP connection is properly closed
            if (isset($sftp)) {
                $sftp->disconnect();
            }
        }
    }

    /**
     * Validate if remote path exists and is writable
     */
    public function validateRemotePath(Request $request, Server $server): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $path = $request->input('path');

        try {
            $sftp = new SFTP($server->ip_address, $server->agentConnection->port ?? 22);

            $login = $sftp->login($server->agentConnection->username, $server->agentConnection->password);

            if (!$login) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to authenticate with server'
                ], 401);
            }

            // Check if path exists
            $exists = $sftp->file_exists($path);
            $isDirectory = false;
            $isWritable = false;

            if ($exists) {
                $stat = $sftp->stat($path);
                $isDirectory = ($stat['mode'] & 0040000) !== 0;

                // Basic writability check - try to create a temp file
                if ($isDirectory) {
                    $testFile = rtrim($path, '/') . '/.upload_test_' . time();
                    $isWritable = $sftp->put($testFile, 'test');
                    if ($isWritable) {
                        $sftp->delete($testFile); // Clean up test file
                    }
                }
            }

            return response()->json([
                'success' => true,
                'exists' => $exists,
                'is_directory' => $isDirectory,
                'is_writable' => $isWritable,
                'path' => $path
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to validate path: ' . $e->getMessage()
            ], 500);
        } finally {
            if (isset($sftp)) {
                $sftp->disconnect();
            }
        }
    }

    /**
     * Get upload progress by progress key
     */
    public function getUploadProgress(Request $request, string $key): JsonResponse
    {
        $uploadProgress = UploadProgress::where('progress_key', $key)->first();

        if (!$uploadProgress) {
            return response()->json([
                'error' => 'Upload progress not found'
            ], 404);
        }

        return response()->json([
            'id' => $uploadProgress->id,
            'progress_key' => $uploadProgress->progress_key,
            'server_id' => $uploadProgress->server_id,
            'local_path' => $uploadProgress->local_path,
            'remote_path' => $uploadProgress->remote_path,
            'original_filename' => $uploadProgress->original_filename,
            'uploaded_mb' => $uploadProgress->uploaded_mb,
            'total_size_mb' => $uploadProgress->total_size_mb,
            'progress_percentage' => $uploadProgress->progress_percentage,
            'status' => $uploadProgress->status,
            'error_message' => $uploadProgress->error_message,
            'upload_speed' => $uploadProgress->upload_speed,
            'estimated_time_remaining' => $uploadProgress->estimated_time_remaining,
            'started_at' => $uploadProgress->started_at,
            'completed_at' => $uploadProgress->completed_at,
            'created_at' => $uploadProgress->created_at,
            'updated_at' => $uploadProgress->updated_at,
        ]);
    }

    /**
     * Get upload progress by ID
     */
    public function getUploadProgressById(Request $request, int $id): JsonResponse
    {
        $uploadProgress = UploadProgress::find($id);

        if (!$uploadProgress) {
            return response()->json([
                'error' => 'Upload progress not found'
            ], 404);
        }

        return $this->getUploadProgress($request, $uploadProgress->progress_key);
    }

    /**
     * List all uploads for a server
     */
    public function listServerUploads(Server $server, Request $request): JsonResponse
    {
        $query = $server->uploadProgress();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Order by most recent first
        $uploads = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($uploads);
    }

    /**
     * Cancel an upload (if still in progress)
     */
    public function cancelUpload(Request $request, string $key): JsonResponse
    {
        $uploadProgress = UploadProgress::where('progress_key', $key)->first();

        if (!$uploadProgress) {
            return response()->json(['error' => 'Upload not found'], 404);
        }

        if (!$uploadProgress->isInProgress()) {
            return response()->json(['error' => 'Upload is not in progress'], 400);
        }

        // Mark as failed to stop the job
        $uploadProgress->markAsFailed('Cancelled by user');

        // Clean up temporary file
        if (Storage::exists($uploadProgress->local_path)) {
            Storage::delete($uploadProgress->local_path);
        }

        return response()->json(['message' => 'Upload cancelled']);
    }

    /**
     * Delete upload record and clean up temporary file
     */
    public function deleteUpload(Request $request, string $key): JsonResponse
    {
        $uploadProgress = UploadProgress::where('progress_key', $key)->first();

        if (!$uploadProgress) {
            return response()->json(['error' => 'Upload not found'], 404);
        }

        // Clean up temporary file if it exists
        if (Storage::exists($uploadProgress->local_path)) {
            Storage::delete($uploadProgress->local_path);
        }

        // Delete the progress record
        $uploadProgress->delete();

        return response()->json(['message' => 'Upload deleted']);
    }

    /**
     * Retry a failed upload
     */
    public function retryUpload(Request $request, string $key): JsonResponse
    {
        $uploadProgress = UploadProgress::where('progress_key', $key)->first();

        if (!$uploadProgress) {
            return response()->json(['error' => 'Upload not found'], 404);
        }

        if (!$uploadProgress->isFailed()) {
            return response()->json(['error' => 'Can only retry failed uploads'], 400);
        }

        // Check if local file still exists
        if (!Storage::exists($uploadProgress->local_path)) {
            return response()->json(['error' => 'Local file no longer exists'], 400);
        }

        // Reset upload progress
        $uploadProgress->update([
            'status' => 'pending',
            'uploaded_mb' => 0,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ]);

        // Dispatch upload job again
        UploadFileJob::dispatch($uploadProgress);

        return response()->json(['message' => 'Upload retry initiated successfully']);
    }

    /**
     * Get upload statistics for a server
     */
    public function getUploadStats(Server $server): JsonResponse
    {
        $stats = [
            'total_uploads' => $server->uploadProgress()->count(),
            'pending_uploads' => $server->uploadProgress()->where('status', 'pending')->count(),
            'uploading' => $server->uploadProgress()->where('status', 'uploading')->count(),
            'completed_uploads' => $server->uploadProgress()->where('status', 'complete')->count(),
            'failed_uploads' => $server->uploadProgress()->where('status', 'failed')->count(),
            'total_mb_uploaded' => $server->uploadProgress()->completed()->sum('uploaded_mb'),
            'recent_uploads' => $server->uploadProgress()->recent()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Clean up old completed uploads
     */
    public function cleanupOldUploads(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);

        $oldUploads = UploadProgress::where('status', 'complete')
            ->where('completed_at', '<', now()->subDays($days))
            ->get();

        $deletedCount = 0;
        foreach ($oldUploads as $upload) {
            // Clean up temporary file if it exists
            if (Storage::exists($upload->local_path)) {
                Storage::delete($upload->local_path);
            }
            $upload->delete();
            $deletedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "Cleaned up {$deletedCount} old upload records"
        ]);
    }
}
