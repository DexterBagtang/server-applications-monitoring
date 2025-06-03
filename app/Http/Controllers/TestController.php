<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\SSHService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

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
     * @param Server $server
     * @param string $database
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function mysqlDump(Server $server, $database, Request $request = null)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
            return response()->json(['error' => 'Invalid database name'], 400);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $dumpFileName = "{$database}_dump_{$timestamp}.sql";
        $dumpPath = "/tmp/{$dumpFileName}";

        try {
            // Check if this is a Laravel project by looking for .env file
            $checkEnvCommand = "[ -f {$server->application_path}/.env ] && echo 'EXISTS' || echo 'MISSING'";
            $envExists = trim($this->sshService->executeCommand($server, $checkEnvCommand));

            $dbUsername = null;
            $dbPassword = null;

            if ($envExists === 'EXISTS') {
                // Try to get database credentials from .env file
                $getDbUserCommand = "grep -E '^DB_USERNAME=' {$server->application_path}/.env | cut -d '=' -f2";
                $getDbPassCommand = "grep -E '^DB_PASSWORD=' {$server->application_path}/.env | cut -d '=' -f2";

                $dbUsername = trim($this->sshService->executeCommand($server, $getDbUserCommand));
                $dbPassword = trim($this->sshService->executeCommand($server, $getDbPassCommand));
            }

            // If credentials not found in .env or request has credentials, use those from request
            if (($dbUsername === '' || $dbPassword === '') && $request && $request->has('db_username') && $request->has('db_password')) {
                $dbUsername = $request->input('db_username');
                $dbPassword = $request->input('db_password');
                // If database name is provided in request, use it
                if ($request->has('db_name')) {
                    $database = $request->input('db_name');
                    // Validate again
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
                        return response()->json(['error' => 'Invalid database name'], 400);
                    }
                }
            }

            // If still no credentials, return error asking for credentials
            if (empty($dbUsername) || empty($dbPassword)) {
                return response()->json([
                    'error' => 'Database credentials not found',
                    'need_credentials' => true
                ], 400);
            }

            // Run mysqldump with the obtained credentials
            $escapedDb = escapeshellarg($database);
            $escapedPath = escapeshellarg($dumpPath);
            $escapedUsername = escapeshellarg($dbUsername);
            $escapedPassword = escapeshellarg($dbPassword);

            $mysqldumpCommand = "mysqldump -u {$escapedUsername} -p{$escapedPassword} {$escapedDb} > {$escapedPath} 2>&1";

            $this->sshService->executeCommand($server, $mysqldumpCommand);

            // Check if file exists
            $checkFileCommand = "[ -f {$escapedPath} ] && echo 'EXISTS' || echo 'MISSING'";
            $checkFile = $this->sshService->executeCommand($server, $checkFileCommand);

            if (trim($checkFile) !== 'EXISTS') {
                return response()->json([
                    'error' => 'MySQL dump failed',
                    'need_credentials' => true
                ], 500);
            }

            // Get file contents via SFTP
            $sftp = $this->sshService->getSFTPConnection($server);
            $fileContents = $sftp->get($dumpPath);

            // Clean up dump file
            $this->sshService->executeCommand($server, "rm -f {$escapedPath}");

            if ($fileContents === false) {
                return response()->json(['error' => 'Failed to download dump file'], 500);
            }

            return Response::make($fileContents, 200, [
                'Content-Type' => 'application/sql',
                'Content-Disposition' => "attachment; filename=\"{$dumpFileName}\"",
                'Content-Length' => strlen($fileContents)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unexpected error occurred',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
