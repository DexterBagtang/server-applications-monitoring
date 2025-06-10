<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadFileJob;
use App\Models\DownloadProgress;
use App\Models\Server;
use App\Services\SSHService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use phpseclib3\Net\SFTP\Stream;

class TestController extends Controller
{
    /**
     * @var SSHService
     */
    private $sshService;

    /**
     * TestController constructor.
     *
     * @param SSHService $sshService
     */
    public function __construct(SSHService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Test SSH connection
     *
     * @param Server $server
     * @return \Illuminate\Http\JsonResponse
     */
    public function sshTest(Server $server)
    {
        try {
            $password = $server->agentConnection->password;
            $command = "echo '$password' | sudo -S wc -l /var/log/nginx/access.log 2>/dev/null";

            $result = $this->sshService->executeCommand($server, $command);

            return response()->json(['result' => $result]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Test SFTP connection
     *
     * @param Server $server
     * @return \Illuminate\Http\Response
     */
    public function sftpTest(Server $server)
    {
        try {
            $remoteFilePath = '/var/www/projectmanagement/composer.json';

            $sftp = $this->sshService->getSFTPConnection($server);
            $fileContents = $sftp->get($remoteFilePath);

            if ($fileContents === false) {
                return response()->json(['error' => 'Failed to fetch file'], 500);
            }

            return Response::make($fileContents, 200, [
                'Content-Type' => 'text/plain',
                'Content-Disposition' => 'attachment; filename="composer.json"'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * MySQL dump
     *
//     * @param Server $server
//     * @param string $database
//     * @param Request $request
//     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function mysqlDump(Server $server, $database, Request $request)
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

            if ($request && $request->has('db_username') && $request->has('db_password')) {
                $dbUsername = $request->input('db_username');
                $dbPassword = $request->input('db_password');
                if ($request->has('db_name')) {
                    $database = $request->input('db_name');
                }
            }



            // Prepare the mysqldump command
            $escapedDb = escapeshellarg($database);
            $escapedPath = escapeshellarg($dumpPath);
            $escapedUsername = escapeshellarg($dbUsername);

            // Use -p without space followed by password for older MySQL versions
            $passwordPart = $dbPassword ? "-p" . $dbPassword : "";


            $mysqldumpCommand = "mysqldump -u {$escapedUsername} '$passwordPart' {$escapedDb} > {$escapedPath} 2>&1";

//            dd($mysqldumpCommand);


            $this->sshService->executeCommand($server, $mysqldumpCommand,true);

            // Check if file exists
            $checkFileCommand = "[ -f {$escapedPath} ] && echo 'EXISTS' || echo 'MISSING'";
            $checkFile = $this->sshService->executeCommand($server, $checkFileCommand);

            if (trim($checkFile) !== 'EXISTS') {
                return response()->json([
                    'error' => 'MySQL dump failed',
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

//            // Get file contents via SFTP
//            $sftp = $this->sshService->getSFTPConnection($server);
//
//            $fileContents = $sftp->get($dumpPath);
//
//            // Clean up dump file
////            $this->sshService->executeCommand($server, "rm -f {$escapedPath}");
//
//            if ($fileContents === false) {
//                return response()->json(['error' => 'Failed to download dump file'], 500);
//            }

//            return Response::make($fileContents, 200, [
//                'Content-Type' => 'application/sql',
//                'Content-Disposition' => "attachment; filename=\"{$dumpFileName}\"",
//                'Content-Length' => strlen($fileContents)
//            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unexpected error occurred',
                'message' => $e->getMessage()
            ], 500);
        }
    }

//    public function downloadZip(Server $server) {
//        // Disable time limits for large downloads
//        set_time_limit(0);
//
//        $remotePath = '/var/www/prod-billing/public/storage/invoices.tar.gz';
//        $localPath = storage_path('app/invoices.tar.gz');
//
//        $sftp = $this->sshService->getSFTPConnection($server);
//
//        $sftp->setTimeout(7200);
//
//        $fileContents = $sftp->get($remotePath);
//
//        if ($fileContents === false) {
//            return response()->json(['error' => 'Failed to download file'], 500);
//        }
//        //store file in storage localpath
//
//        if (file_put_contents($localPath, $fileContents) === false) {
//            return response()->json(['error' => 'Failed to save file locally'], 500);
//        }
//
//        return response("File downloaded successfully to: $localPath", 200);
//    }

//    public function downloadZip(Server $server)
//    {
//        // Disable time limits for large downloads
//        set_time_limit(0);
//
////        $remotePath = '/var/www/prod-billing/public/storage/invoices.tar.gz';
//        $remotePath = '/tmp/database.sql';
//        $localPath = storage_path('app/database.sql');
//        $progressPath = storage_path('app/invoices_progress.json');
//
//        $sftp = $this->sshService->getSFTPConnection($server);
//        $sftp->setTimeout(7200);
//
//        // Reset progress file
//        file_put_contents($progressPath, json_encode([
//            'downloaded' => 0,
//            'status' => 'downloading',
//        ]));
//
//        // Open local file for writing
//        $localFile = fopen($localPath, 'w');
//        if (!$localFile) {
//            return response()->json(['error' => 'Unable to open local file'], 500);
//        }
//
//        // Use get() with callback and write to file in chunks
//        $result = $sftp->get($remotePath, $localFile, 0, -1, function ($downloaded) use ($progressPath) {
//            file_put_contents($progressPath, json_encode([
//                'downloaded' => $downloaded/1024/1024,
//                'status' => 'downloading',
//            ]));
//        });
//
//        fclose($localFile);
//
//        if ($result === false) {
//            file_put_contents($progressPath, json_encode([
//                'downloaded' => 0,
//                'status' => 'failed',
//            ]));
//            return response()->json(['error' => 'Failed to download file'], 500);
//        }
//
//        file_put_contents($progressPath, json_encode([
//            'downloaded' => filesize($localPath),
//            'status' => 'complete',
//        ]));
//
//        return response("File downloaded successfully to: $localPath", 200);
//    }
//
//
//    public function checkDownloadProgress()
//    {
//        $progressPath = storage_path('app/invoices_progress.json');
//
//        if (!file_exists($progressPath)) {
//            return response()->json(['status' => 'not_started']);
//        }
//
//        $progress = json_decode(file_get_contents($progressPath), true);
//        return response()->json($progress);
//    }

}
