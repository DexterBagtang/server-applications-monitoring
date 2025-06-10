<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadFileJob;
use App\Models\DownloadProgress;
use App\Models\Server;
use App\Services\SSHService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    private $sshService;

    public function __construct(SSHService $sshService){
        $this->sshService = $sshService;
    }

    public function databaseDump(Server $server, $database, Request $request)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
            return response()->json(['error' => 'Invalid database name'], 400);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $dumpFileName = "{$database}_dump_{$timestamp}.sql";
        $dumpPath = "/tmp/{$dumpFileName}";

        try {
            $dbUsername = null;
            $dbPassword = null;
            $dbType = $request->input('db_type', 'mysql'); // Default to mysql if not specified

            if ($request && $request->has('db_username') && $request->has('db_password')) {
                $dbUsername = $request->input('db_username');
                $dbPassword = $request->input('db_password');
                if ($request->has('db_name')) {
                    $database = $request->input('db_name');
                }
            }

            $escapedDb = escapeshellarg($database);
            $escapedUsername = escapeshellarg($dbUsername);
            $escapedPath = escapeshellarg($dumpPath);

            if ($dbType === 'postgresql') {
                // PostgreSQL dump command
                $passwordEnv = $dbPassword ? "PGPASSWORD=" . $dbPassword . " " : "";
                $dumpCommand = "{$passwordEnv}pg_dump -U {$escapedUsername} -d {$escapedDb} -f {$escapedPath} 2>&1";
            } else {
                // MySQL dump command (default)
                $passwordPart = $dbPassword ? "-p" . $dbPassword : "";
                $dumpCommand = "mysqldump -u {$escapedUsername} {$passwordPart} {$escapedDb} > {$escapedPath} 2>&1";
            }

            $this->sshService->executeCommand($server, $dumpCommand, true);

            // Check if file exists
            $checkFileCommand = "[ -f {$escapedPath} ] && echo 'EXISTS' || echo 'MISSING'";
            $checkFile = $this->sshService->executeCommand($server, $checkFileCommand);

            if (trim($checkFile) !== 'EXISTS') {
                return response()->json([
                    'error' => 'Database dump failed',
                    'need_credentials' => true
                ], 500);
            }

            $progressKey = 'download_' . $server->id . '_' . Str::random(8);

            $downloadProgress = DownloadProgress::create([
                'progress_key' => $progressKey,
                'server_id' => $server->id,
                'remote_path' => $dumpPath,
                'local_filename' => $dumpFileName,
                'status' => 'pending',
            ]);

            $job = DownloadFileJob::dispatch($server, $dumpPath, $dumpFileName, $progressKey);

            return response()->json([
                'message' => 'Download started',
                'progress_id' => $downloadProgress->id,
                'progress_key' => $progressKey,
                'job_id' => Str::uuid(),
                'progress_endpoint' => route('download.progress', ['key' => $progressKey])
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unexpected error occurred',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
