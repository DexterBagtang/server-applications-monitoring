<?php

namespace App\Jobs;

use App\Events\ServerDetailsFetched;
use App\Models\Server;
use App\Services\ServerMetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateServerMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Server $server) {}

    public function handle(ServerMetricsService $metricsService): void
    {
        $updated = $metricsService->updateMetrics($this->server);

//        if($updated){
            ServerDetailsFetched::dispatch();
//        }else{
//            $this->server->update([
//                'status'
//            ]);
//        }
    }
}
