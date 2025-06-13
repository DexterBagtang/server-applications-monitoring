<?php

namespace App\Console\Commands;

use App\Events\TestReverb;
use Illuminate\Console\Command;

class TestEcho extends Command
{
    protected $signature = 'reverb:test {message=Hello}';
    protected $description = 'Broadcast the TestReverb event with a message';

    public function handle()
    {
        $message = $this->argument('message');

        broadcast(new TestReverb($message));

        $this->info("TestReverb event broadcasted with message: \"$message\"");
    }
}
