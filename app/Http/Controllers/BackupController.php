<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadFileJob;
use App\Models\Application;
use App\Models\DownloadProgress;
use App\Models\Server;
use App\Services\SSHService;
use Dflydev\DotAccessData\Data;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    private $sshService;

    public function __construct(SSHService $sshService)
    {
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
            $dbPort = null;
            $dbType = $request->input('db_type', 'mysql'); // Default to mysql if not specified

            if ($request && $request->has('db_username') && $request->has('db_password')) {
                $dbUsername = $request->input('db_username');
                $dbPassword = $request->input('db_password');
                if ($request->has('db_name')) {
                    $database = $request->input('db_name');
                }
            }

            // Handle custom port
            if ($request && $request->has('db_port')) {
                $dbPort = $request->input('db_port');
                // Validate port number
                if (!is_numeric($dbPort) || $dbPort < 1 || $dbPort > 65535) {
                    return response()->json(['error' => 'Invalid port number'], 400);
                }
            }

            $escapedDb = escapeshellarg($database);
            $escapedUsername = escapeshellarg($dbUsername);
            $escapedPath = escapeshellarg($dumpPath);

            if ($dbType === 'postgresql') {
                // PostgreSQL dump command
                $passwordEnv = $dbPassword ? "PGPASSWORD=" . escapeshellarg($dbPassword) . " " : "";
                $portPart = $dbPort ? "-p " . escapeshellarg($dbPort) : "-p 5432"; // Default PostgreSQL port
                $hostPart = "-h localhost"; // You might want to make this configurable too
                $dumpCommand = "{$passwordEnv}pg_dump {$hostPart} {$portPart} -U {$escapedUsername} -d {$escapedDb} -f {$escapedPath} 2>&1";
            } else {
                // MySQL dump command (default)
                $passwordPart = $dbPassword ? "-p" . escapeshellarg($dbPassword) : "";
                $portPart = $dbPort ? "-P " . escapeshellarg($dbPort) : "-P 3306"; // Default MySQL port
                $dumpCommand = "mysqldump -u {$escapedUsername} {$passwordPart} {$portPart} {$escapedDb} > {$escapedPath} 2>&1";
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

//    public function databaseBackup(Application $application)
//    {
//        $path = $application->path;
//        $command = "cd $path && type .env || cat .env";
//
//        $server = Server::find($application->server_id);
//
//        $output = $this->sshService->executeCommand($server, $command, true);
//// Normalize line endings
//        $lines = preg_split('/\r\n|\n|\r/', trim($output));
//
//        $wantedKeys = ['DB_PORT', 'DB_DATABASE', 'DB_USERNAME','DB_PASSWORD','DB_CONNECTION'];
//        $envVars = [];
//
//        foreach ($lines as $line) {
//            if (strpos($line, '=') !== false) {
//                [$key, $value] = explode('=', $line, 2);
//                $key = trim($key);
//                if (in_array($key, $wantedKeys)) {
//                    $envVars[$key] = trim($value);
//                }
//            }
//        }
//        $database = $envVars['DB_DATABASE'];
//
//        $timestamp = date('Y-m-d_H-i-s');
//        $dumpFileName = "{$database}_dump_{$timestamp}.sql";
//        $dumpPath = "/tmp/{$dumpFileName}";
//
//        try {
//            $dbUsername = $envVars['DB_USERNAME'];
//            $dbPassword = $envVars['DB_PASSWORD'];
//            $dbType = $envVars['DB_CONNECTION'] ?? 'mysql'; // Default to mysql if not specified
//
//
//            $escapedDb = escapeshellarg($database);
//            $escapedUsername = escapeshellarg($dbUsername);
//            $escapedPath = escapeshellarg($dumpPath);
//
//            if ($dbType === 'postgresql') {
//                // PostgreSQL dump command
//                $passwordEnv = $dbPassword ? "PGPASSWORD=" . $dbPassword . " " : "";
//                $dumpCommand = "{$passwordEnv}pg_dump -U {$escapedUsername} -d {$escapedDb} -f {$escapedPath} 2>&1";
//            } else {
//                // MySQL dump command (default)
//                $passwordPart = $dbPassword ? "-p" . $dbPassword : "";
//                $dumpCommand = "mysqldump -u {$escapedUsername} {$passwordPart} {$escapedDb} > {$escapedPath} 2>&1";
//            }
//
//            dd($dumpCommand);
//
//            $this->sshService->executeCommand($server, $dumpCommand, true);
//
//            // Check if file exists
//            $checkFileCommand = "[ -f {$escapedPath} ] && echo 'EXISTS' || echo 'MISSING'";
//            $checkFile = $this->sshService->executeCommand($server, $checkFileCommand);
//
//            if (trim($checkFile) !== 'EXISTS') {
//                return response()->json([
//                    'error' => 'Database dump failed',
//                    'need_credentials' => true
//                ], 500);
//            }
//
//            $progressKey = 'download_' . $server->id . '_' . Str::random(8);
//
//            $downloadProgress = DownloadProgress::create([
//                'progress_key' => $progressKey,
//                'server_id' => $server->id,
//                'remote_path' => $dumpPath,
//                'local_filename' => $dumpFileName,
//                'status' => 'pending',
//            ]);
//
//            $job = DownloadFileJob::dispatch($server, $dumpPath, $dumpFileName, $progressKey);
//
//            return response()->json([
//                'message' => 'Download started',
//                'progress_id' => $downloadProgress->id,
//                'progress_key' => $progressKey,
//                'job_id' => Str::uuid(),
//                'progress_endpoint' => route('download.progress', ['key' => $progressKey])
//            ], 202);
//
//        } catch (\Exception $e) {
//            return response()->json([
//                'error' => 'Unexpected error occurred',
//                'message' => $e->getMessage()
//            ], 500);
//        }
//    }


    public function databaseBackup(Application $application)
    {
        $path = $application->path;
        $command = "cd $path && type .env || cat .env";

        $server = Server::find($application->server_id);

        $output = $this->sshService->executeCommand($server, $command, true);

        // Normalize line endings
        $lines = preg_split('/\r\n|\n|\r/', trim($output));

        $wantedKeys = ['DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CONNECTION'];
        $envVars = [];

        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                if (in_array($key, $wantedKeys)) {
                    $envVars[$key] = trim($value);
                }
            }
        }

        $database = $envVars['DB_DATABASE'];
        $dbUsername = $envVars['DB_USERNAME'];
        $dbPassword = $envVars['DB_PASSWORD'];
        $dbType     = $envVars['DB_CONNECTION'] ?? 'mysql';

        $timestamp     = date('Y-m-d_H-i-s');
        $dumpFileName  = "{$database}_dump_{$timestamp}.sql";
        $dumpPath      = "/tmp/{$dumpFileName}";

        try {
            if ($dbType === 'pgsql' || $dbType === 'postgres' || $dbType === 'postgresql') {
                // PostgreSQL command
                $escapedUsername = escapeshellarg($dbUsername);
                $escapedDatabase = escapeshellarg($database);
                $escapedPath     = escapeshellarg($dumpPath);
                $escapedPassword = escapeshellarg($dbPassword);

                $dumpCommand = "PGPASSWORD={$escapedPassword} pg_dump -U {$escapedUsername} -d {$escapedDatabase} -f {$escapedPath} 2>&1";
            } else {
                $escapedPath     = escapeshellarg($dumpPath);

                $dbUser = escapeshellarg($dbUsername);         // e.g., 'billing_admin'
                $dbName = escapeshellarg($database);           // e.g., 'mailmyinvoice2'
                $dumpFile = escapeshellarg($dumpPath);         // e.g., '/tmp/mailmyinvoice2_dump_2025-08-05_13-58-26.sql'

                // ❗ DO NOT use escapeshellarg on password after -p (MySQL doesn’t allow space after -p)
                $dbPasswordSafe = str_replace('"', '\"', $dbPassword);  // escape double quotes
                $dumpCommand = "mysqldump -u {$dbUser} -p\"{$dbPasswordSafe}\" {$dbName} > {$dumpFile} 2>&1";

            }

            // Optional: inspect the command
//             dd($dumpCommand);

            $this->sshService->executeCommand($server, $dumpCommand, true);

            // Confirm the dump file exists
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
                'progress_key'    => $progressKey,
                'server_id'       => $server->id,
                'remote_path'     => $dumpPath,
                'local_filename'  => $dumpFileName,
                'status'          => 'pending',
            ]);

            DownloadFileJob::dispatch($server, $dumpPath, $dumpFileName, $progressKey);

            return response()->json([
                'message'           => 'Download started',
                'progress_id'       => $downloadProgress->id,
                'progress_key'      => $progressKey,
                'job_id'            => Str::uuid(),
                'progress_endpoint' => route('download.progress', ['key' => $progressKey])
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Unexpected error occurred',
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
