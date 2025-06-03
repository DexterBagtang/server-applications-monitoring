<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Services\ServerMetricsService;
use Illuminate\Console\Command;

class TestServiceDetails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service:details {serverID} {service_name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ServerMetricsService $service)
    {
        $serverId = $this->argument('serverID');
        $serviceName = $this->argument('service_name');

        $server = Server::with('agentConnection')->find($serverId);

        $details = $service->getServiceDetails($server,$serviceName);

        dd($details);

    }
}
