<?php

use App\Models\Server;
use App\Services\ServerMetricsService;
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
    Server $server,
    string $service_name
) {
    $server = $server->load('agentConnection');
    $details = $service->getServiceDetails($server, $service_name);

    return $details['details'];
})->middleware('auth:sanctum');
