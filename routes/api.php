<?php

use App\Http\Controllers\ServerController;
use App\Models\Application;
use App\Models\Server;
use App\Services\ServerMetricsService;
use App\Services\SSHService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/servers/{server}/connection-details', function (Server $server) {
    return response()->json([
        'username' => $server->agentConnection->username,
        'port' => $server->agentConnection->port,
    ]);
})->middleware('auth:sanctum'); // Or your auth middleware

Route::get('/servers/{server}/{service_name}/service-details', function (
    ServerMetricsService $service,
    Server               $server,
    string               $service_name
) {
    $server = $server->load('agentConnection');
    $details = $service->getServiceDetails($server, $service_name);

    return $details['details'];
})->middleware('auth:sanctum');


Route::post('/servers/{server}/execute', [ServerController::class, 'execute'])
    ->name('servers.execute')
    ->middleware('auth:sanctum');


Route::get('db-credentials/{application}', function (SSHService $sshService, Application $application) {

    $path = $application->path . '/';
    // Load the full .env file contents
    $command = "cd $path && type .env || cat .env";

    $output = $sshService->executeCommand(Server::find($application->server_id), $command, true);
// Normalize line endings
    $lines = preg_split('/\r\n|\n|\r/', trim($output));

    $wantedKeys = ['DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CONNECTION'];
    $envVars = [];

    foreach ($lines as $line) {
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (in_array($key, $wantedKeys)) {
                $envVars[$key] = trim($value, " \"");
            }
        }
    }
    return response()->json($envVars);
});

Route::get('env-variables/{application}', function (SSHService $sshService, Application $application) {

    $path = $application->path . '/';
    // Load the full .env file contents
    $command = "cd $path && type .env || cat .env";

    $output = $sshService->executeCommand(Server::find($application->server_id), $command, true);
// Normalize line endings
    $lines = preg_split('/\r\n|\n|\r/', trim($output));

    $envVars = [];

    foreach ($lines as $line) {
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $envVars[$key] = trim($value, " \"");
        }
    }
    return response()->json($envVars);
});


