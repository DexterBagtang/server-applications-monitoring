<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\ServerMetricsService;
use Illuminate\Console\Command;

class DiscoverServerServices extends Command
{
    protected $signature = 'servers:discover-services {--server=}';
    protected $description = 'Discover services running on servers';

    public function handle(ServerMetricsService $serverMetricsService)
    {
        $serverId = $this->option('server');

        if ($serverId) {
            // Discover services for a specific server
            $server = Server::find($serverId);
            if (!$server) {
                $this->error("Server with ID {$serverId} not found.");
                return;
            }

            $success = $serverMetricsService->discoverServices($server);
            if ($success) {
                $this->info("Successfully discovered services for server: {$server->name}");
            } else {
                $this->error("Failed to discover services for server: {$server->name}");
            }
        } else {
            // Discover services for all servers
            $servers = Server::all();

            foreach ($servers as $server) {
                $success = $serverMetricsService->discoverServices($server);
                if ($success) {
                    $this->info("Successfully discovered services for server: {$server->name}");
                } else {
                    $this->error("Failed to discover services for server: {$server->name}");
                }
            }
        }
    }
}
