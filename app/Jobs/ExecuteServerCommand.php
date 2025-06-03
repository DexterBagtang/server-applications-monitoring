<?php

// app/Jobs/ExecuteServerCommand.php
namespace App\Jobs;

use App\Events\TerminalOutput;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class ExecuteServerCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Server $server,
        public string $command,
        public string $connectionId
    ) {}

    public function handle(): void
    {
        $ssh = new SSH2($this->server->ip_address,$this->server->agentConnection->port);

        if (!$ssh->login(
            $this->server->agentConnection->username,
            $this->server->agentConnection->password
        )) {
            TerminalOutput::dispatch("SSH login failed", $this->server->id, $this->connectionId);
            return;
        }

        $ssh->exec($this->command, function($str) {
            TerminalOutput::dispatch($str, $this->server->id, $this->connectionId);
        });

        Log::info('received');
    }
}
