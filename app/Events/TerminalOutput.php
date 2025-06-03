<?php

namespace App\Events;
// app/Events/TerminalOutput.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TerminalOutput implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $output,
        public int $serverId,
        public string $connectionId
    ) {}

    public function broadcastOn(): Channel|array
    {
        return new Channel("server.{$this->serverId}.terminal");
    }

    public function broadcastAs()
    {
        return 'terminal.output';
    }
}
