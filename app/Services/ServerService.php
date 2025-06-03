<?php

namespace App\Services;

use App\Jobs\UpdateServerMetricsJob;
use App\Models\Server;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServerService
{
    /**
     * @var SSHService
     */
    private $sshService;

    /**
     * ServerService constructor.
     *
     * @param SSHService $sshService
     */
    public function __construct(SSHService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Get all servers
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllServers()
    {
        return Server::all();
    }

    /**
     * Get a server by ID with related data
     *
     * @param Server $server
     * @return Server
     */
    public function getServerWithRelations(Server $server)
    {
        return $server->load([
            'services',
            'applications.latestMetric'
        ]);
    }

    /**
     * Create a new server
     *
     * @param array $data
     * @return Server
     */
    public function createServer(array $data): Server
    {
        // Set default values
        $data['is_active'] = $data['is_active'] ?? true;
        $data['auth_type'] = 'password'; // Default auth type

        // Use transaction for data consistency
        $server = DB::transaction(function () use ($data) {
            // Create the server
            $server = Server::create([
                'name' => $data['name'],
                'ip_address' => $data['ip_address'],
                'status' => 'Fetching',
            ]);

            // Create agent connection using the relationship
            $server->agentConnection()->create([
                'auth_type' => $data['auth_type'],
                'username' => $data['username'],
                'password' => $data['password'],
                'port' => $data['port'],
                // These fields will be updated when agent connects
                'agent_version' => null,
                'last_connection_at' => null,
            ]);

            return $server;
        });

        // Dispatch job to update server metrics
        UpdateServerMetricsJob::dispatch($server);

        return $server;
    }

    /**
     * Update a server
     *
     * @param Server $server
     * @param array $data
     * @return Server
     */
    public function updateServer(Server $server, array $data): Server
    {
        // Set default values
        $data['is_active'] = $data['is_active'] ?? true;
        $data['auth_type'] = 'password'; // Default auth type

        // Check if any of the relevant fields have changed
        $relevantFieldsChanged =
            $server->ip_address !== $data['ip_address'] ||
            $server->agentConnection->username !== $data['username'] ||
            (isset($data['password']) && $data['password'] !== '') ||
            $server->agentConnection->port !== $data['port'];

        // Use transaction for data consistency
        DB::transaction(function () use ($data, $server) {
            // Update the server
            $server->update([
                'name' => $data['name'],
            ]);

            $agentData = [
                'username' => $data['username'],
                'port' => $data['port'],
            ];

            // Only update password if it was provided and not empty
            if (!empty($data['password'])) {
                $agentData['password'] = $data['password'];
            }

            // Update agent connection using the relationship
            $agentConnection = $server->agentConnection;
            $agentConnection->update($agentData);
        });

        // Only dispatch the job if relevant fields changed
        if ($relevantFieldsChanged) {
            UpdateServerMetricsJob::dispatch($server);

            $server->update([
                'ip_address' => $data['ip_address'],
                'status' => 'Fetching',
                'remarks' => ''
            ]);
        }

        return $server;
    }

    /**
     * Delete a server
     *
     * @param Server $server
     * @return bool
     */
    public function deleteServer(Server $server): bool
    {
        try {
            DB::transaction(function () use ($server) {
                // Delete related records first
                $server->agentConnection()->delete();
                // Then delete the server
                $server->delete();
            });

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete server: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch server metrics
     *
     * @param Server $server
     * @return void
     */
    public function fetchServerMetrics(Server $server): void
    {
        UpdateServerMetricsJob::dispatch($server);

        $server->update([
            'status' => 'Fetching',
            'remarks' => '',
        ]);
    }

    /**
     * Execute a command on the server
     *
     * @param Server $server
     * @param string $command
     * @param bool $sudoEnabled
     * @return string
     * @throws \Exception
     */
    public function executeCommand(Server $server, string $command, bool $sudoEnabled = false): string
    {
        // Filter dangerous commands
        if ($this->sshService->isDangerousCommand($command)) {
            throw new \Exception('Command blocked for security reasons');
        }

        if ($sudoEnabled) {
            $password = sprintf("'%s'", $server->agentConnection->password);
            $command = str_replace('sudopassword', $password, $command);
        }

        $needColor = stripos($command, 'git diff') !== false;

        if ($needColor) {
            $command .= ' --color=always';
        }

        return $this->sshService->executeCommand($server, $command);
    }
}
