<?php

namespace App\Services;

use App\Events\TerminalOutput;
use App\Models\Server;
use Illuminate\Support\Facades\Log;

class TerminalService
{
    /**
     * @var SSHService
     */
    private $sshService;

    /**
     * TerminalService constructor.
     *
     * @param SSHService $sshService
     */
    public function __construct(SSHService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Connect to a server terminal
     *
     * @param Server $server
     * @param string $connectionId
     * @return bool
     */
    public function connect(Server $server, string $connectionId): bool
    {
        try {
            // Send welcome message
            TerminalOutput::dispatch(
                "Connected to {$server->name} terminal",
                $server->id,
                $connectionId
            );

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to connect to terminal for server {$server->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute a command in the terminal
     *
     * @param Server $server
     * @param string $command
     * @param string $connectionId
     * @param bool $sudoEnabled
     * @return string
     * @throws \Exception
     */
    public function executeCommand(Server $server, string $command, string $connectionId, bool $sudoEnabled = false): string
    {
        try {
            // Check if command is dangerous
            if ($this->sshService->isDangerousCommand($command)) {
                $errorMessage = "Command blocked for security reasons";

                // Dispatch error message to terminal
                TerminalOutput::dispatch(
                    $errorMessage,
                    $server->id,
                    $connectionId
                );

                throw new \Exception($errorMessage);
            }

            // Execute the command
            $output = $this->sshService->executeCommand($server, $command, $sudoEnabled);

            // Dispatch output to terminal
            TerminalOutput::dispatch(
                $output,
                $server->id,
                $connectionId
            );

            return $output;
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), "Command blocked") === false) {
                // Only log non-security related errors
                Log::error("Terminal command execution failed: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Disconnect from a server terminal
     *
     * @param Server $server
     * @param string $connectionId
     * @return bool
     */
    public function disconnect(Server $server, string $connectionId): bool
    {
        try {
            // Close the SSH connection
            $this->sshService->closeConnection($server);

            // Send disconnect message
            TerminalOutput::dispatch(
                "Disconnected from {$server->name} terminal",
                $server->id,
                $connectionId
            );

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to disconnect from terminal for server {$server->name}: " . $e->getMessage());
            return false;
        }
    }
}
