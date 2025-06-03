<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\ServerMetricsService;
use Illuminate\Console\Command;

class UpdateServerMetrics extends Command
{
    protected $signature = 'servers:update-metrics {serverId? : The ID of a specific server to update}';
    protected $description = 'Fetch server metrics via SSH and update database';

    public function handle(ServerMetricsService $metricsService)
    {
        $serverId = $this->argument('serverId');

        if ($serverId) {
            $server = Server::with('agentConnection')
//                ->where('is_active', true)
                ->find($serverId);

            if (!$server) {
                $this->error("No active server found with ID: {$serverId}");
                return;
            }

            $this->updateSingleServer($server, $metricsService);
            return;
        }

        $servers = Server::with('agentConnection')
            ->where('is_active', true)
            ->get();

        if ($servers->isEmpty()) {
            $this->error('No active servers found.');
            return;
        }

        foreach ($servers as $server) {
            $this->updateSingleServer($server, $metricsService);
        }
    }

    private function updateSingleServer(Server $server, ServerMetricsService $metricsService): void
    {
        $this->info("Updating metrics for: {$server->name} ({$server->ip_address})");

        if ($metricsService->updateMetrics($server)) {
            $this->info("✅ Updated successfully.");
        } else {
            $this->error("❌ Failed to update metrics.");
        }
    }
}
