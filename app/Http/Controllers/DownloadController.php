<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadFileJob;
use App\Models\DownloadProgress;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
