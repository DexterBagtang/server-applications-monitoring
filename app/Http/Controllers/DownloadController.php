<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadFileJob;
use App\Models\DownloadProgress;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
class DownloadController extends Controller
{
    public function downloadZip(Server $server, Request $request)
    {
        $remotePath = $request->get('remote_path', '/tmp/invoices.tar.gz');
        $localFileName = $request->get('local_filename', 'invoices.tar.gz');
        $progressKey = 'download_' . $server->id . '_' . Str::random(8);

        // Create download progress record
        $downloadProgress = DownloadProgress::create([
            'progress_key' => $progressKey,
            'server_id' => $server->id,
            'remote_path' => $remotePath,
            'local_filename' => $localFileName,
            'status' => 'pending',
        ]);

        // Dispatch the job to the queue
        $job = DownloadFileJob::dispatch($server, $remotePath, $localFileName, $progressKey);

        return response()->json([
            'message' => 'Download started',
            'progress_id' => $downloadProgress->id,
            'progress_key' => $progressKey,
            'job_id' => Str::uuid(),
            'progress_endpoint' => route('download.progress', ['key' => $progressKey])
        ], 202);
    }

    /**
     * Browse files on remote server via SFTP
     */
    public function browseFiles(Server $server, Request $request)
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

                $fileList[] = [
                    'name' => $filename,
                    'path' => $fullPath,
                    'is_directory' => $isDirectory,
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
                // Convert backslashes to forward slashes and handle root directory
                $parentPath = str_replace('\\', '/', $parentPath);
                if ($parentPath === '.' || $parentPath === '') {
                    $parentPath = '/';
                }

                array_unshift($fileList, [
                    'name' => '..',
                    'path' => $parentPath,
                    'is_directory' => true,
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
     * Get download progress by progress key
     */
    public function getDownloadProgress(Request $request, string $key)
    {
        $downloadProgress = DownloadProgress::where('progress_key', $key)->first();

        if (!$downloadProgress) {
            return response()->json([
                'error' => 'Download progress not found'
            ], 404);
        }

        return response()->json([
            'id' => $downloadProgress->id,
            'progress_key' => $downloadProgress->progress_key,
            'server_id' => $downloadProgress->server_id,
            'remote_path' => $downloadProgress->remote_path,
            'local_filename' => $downloadProgress->local_filename,
            'downloaded_mb' => $downloadProgress->downloaded_mb,
            'total_size_mb' => $downloadProgress->total_size_mb,
            'progress_percentage' => $downloadProgress->progress_percentage,
            'status' => $downloadProgress->status,
            'error_message' => $downloadProgress->error_message,
            'started_at' => $downloadProgress->started_at,
            'completed_at' => $downloadProgress->completed_at,
            'estimated_time_remaining' => $downloadProgress->estimated_time_remaining,
            'created_at' => $downloadProgress->created_at,
            'updated_at' => $downloadProgress->updated_at,
        ]);
    }

    /**
     * Get download progress by ID
     */
    public function getDownloadProgressById(Request $request, int $id)
    {
        $downloadProgress = DownloadProgress::find($id);

        if (!$downloadProgress) {
            return response()->json([
                'error' => 'Download progress not found'
            ], 404);
        }

        return response()->json([
            'id' => $downloadProgress->id,
            'progress_key' => $downloadProgress->progress_key,
            'server_id' => $downloadProgress->server_id,
            'remote_path' => $downloadProgress->remote_path,
            'local_filename' => $downloadProgress->local_filename,
            'downloaded_mb' => $downloadProgress->downloaded_mb,
            'total_size_mb' => $downloadProgress->total_size_mb,
            'progress_percentage' => $downloadProgress->progress_percentage,
            'status' => $downloadProgress->status,
            'error_message' => $downloadProgress->error_message,
            'started_at' => $downloadProgress->started_at,
            'completed_at' => $downloadProgress->completed_at,
            'estimated_time_remaining' => $downloadProgress->estimated_time_remaining,
            'created_at' => $downloadProgress->created_at,
            'updated_at' => $downloadProgress->updated_at,
        ]);
    }

    /**
     * Download the completed file
     */
    public function downloadFile(Request $request, string $filename)
    {
        $filePath = storage_path("app/{$filename}");

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response()->download($filePath);
    }

    /**
     * List all downloads for a server
     */
    public function listServerDownloads(Server $server, Request $request)
    {
        $query = $server->downloadProgresses();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Order by most recent first
        $downloads = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($downloads);
    }

    /**
     * Cancel a download (if still in progress)
     */
    public function cancelDownload(Request $request, string $key)
    {
        $downloadProgress = DownloadProgress::where('progress_key', $key)->first();

        if (!$downloadProgress) {
            return response()->json(['error' => 'Download not found'], 404);
        }

        if (!$downloadProgress->isInProgress()) {
            return response()->json(['error' => 'Download is not in progress'], 400);
        }

        // Mark as failed to stop the job
        $downloadProgress->markAsFailed('Cancelled by user');

        return response()->json(['message' => 'Download cancelled']);
    }

    /**
     * Delete download record and associated file
     */
    public function deleteDownload(Request $request, string $key)
    {
        $downloadProgress = DownloadProgress::where('progress_key', $key)->first();

        if (!$downloadProgress) {
            return response()->json(['error' => 'Download not found'], 404);
        }

        // Delete the file if it exists
        $filePath = storage_path("app/{$downloadProgress->local_filename}");
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete the progress record
        $downloadProgress->delete();

        return response()->json(['message' => 'Download deleted']);
    }
}
