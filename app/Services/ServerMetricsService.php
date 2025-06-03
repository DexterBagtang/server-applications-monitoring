<?php

namespace App\Services;

use App\Models\Server;
use App\Models\Service;
use phpseclib3\Net\SSH2;
use Illuminate\Support\Facades\Log;

class ServerMetricsService
{
    private string $commandPrefix;

    public function __construct()
    {
        $this->commandPrefix = "nice -n 10 ionice -c2 -n7 ";
    }

    public function updateMetrics(Server $server): bool
    {
        $server = $server->fresh(['agentConnection']);


        Log::info("Updating metrics for server: {$server->name} ({$server->ip_address})");

        if (!$server->agentConnection) {

            Log::error("No agent connection configured for {$server->name}");

            return false;
        }

        try {
            $ssh = $this->establishSshConnection($server);

            $server->update($this->getAllMetrics($ssh));

            $server->agentConnection->update([
                'last_connection_at' => now(),
            ]);

            Log::info("Successfully updated metrics for server: {$server->name}");
            return true;

        } catch (\Exception $e) {
            $server->update([
                'status' => 'Offline',
                'remarks' => $e->getMessage()
            ]);
            Log::error("Server metrics update failed for {$server->name}: " . $e->getMessage());
            return false;
        }
    }

    private function establishSshConnection(Server $server): SSH2
    {
        $ssh = new SSH2($server->ip_address, $server->agentConnection->port ?? 22);

        if ($server->agentConnection->auth_type === 'key') {
            $authSuccessful = $ssh->login(
                $server->agentConnection->username,
                $server->agentConnection->ssh_key
            );
        } else {
            $authSuccessful = $ssh->login(
                $server->agentConnection->username,
                $server->agentConnection->password
            );
        }

        if (!$authSuccessful) {
            throw new \Exception("SSH authentication failed");
        }

        $ssh->setTimeout(10);
        return $ssh;
    }

    private function getAllMetrics(SSH2 $ssh): array
    {
        return [
            'hostname' => $this->getHostname($ssh),
            'os_type' => $this->getOsType($ssh),
            'os_version' => $this->getOsVersion($ssh),
            'cpu_model' => $this->getCpuModel($ssh),
            'cpu_cores' => $this->getCpuCores($ssh),
            'ram_gb' => $this->getRamGb($ssh),
            'disk_gb' => $this->getDiskGb($ssh),
            'public_ip' => $this->getPublicIp($ssh),
            'gateway' => $this->getGateway($ssh),
            'status' => 'Online',
            'last_ping_at' => now(),
        ];
    }

    // All the helper methods
    private function getOsType(SSH2 $ssh): ?string
    {
        $osId = strtolower(trim($ssh->exec($this->commandPrefix. ' cat /etc/os-release | grep "^ID=" | cut -d "=" -f2 | tr -d \'"\'')));

        return match ($osId) {
            'ubuntu', 'centos', 'almalinux', 'debian', 'rocky' => $osId,
            default => 'other',
        };
    }

    private function getHostname(SSH2 $ssh): ?string
    {
        return trim($ssh->exec('hostname'));
    }

    private function getOsVersion(SSH2 $ssh): ?string
    {
        return trim($ssh->exec($this->commandPrefix.' cat /etc/os-release | grep "PRETTY_NAME" | cut -d "=" -f2 | tr -d \'"\''));
    }

    private function getCpuModel(SSH2 $ssh): ?string
    {
        return trim($ssh->exec($this->commandPrefix.' lscpu | grep "Model name" | cut -d ":" -f2 | xargs'));
    }

    private function getCpuCores(SSH2 $ssh): int
    {
        return (int) $ssh->exec($this->commandPrefix.' nproc');
    }

    private function getRamGb(SSH2 $ssh): int
    {
        return (int) $ssh->exec($this->commandPrefix.' free -g | grep Mem | awk \'{print $2}\'');
    }

    private function getDiskGb(SSH2 $ssh): int
    {
        return (int) $ssh->exec($this->commandPrefix.' df -BG / | tail -1 | awk \'{print $2}\' | tr -d "G"');
    }

    private function getPublicIp(SSH2 $ssh): ?string
    {
        return trim($ssh->exec($this->commandPrefix.' curl -s ifconfig.me || curl -s icanhazip.com'));
    }

    private function getGateway(SSH2 $ssh): ?string
    {
        return trim($ssh->exec($this->commandPrefix.' ip route | grep default | awk \'{print $3}\''));
    }

    public function discoverServices(Server $server): bool
    {
        $server = $server->fresh(['agentConnection']);

        Log::info("Discovering services for server: {$server->name} ({$server->ip_address})");

        if (!$server->agentConnection) {
            Log::error("No agent connection configured for {$server->name}");
            return false;
        }

        try {
            $ssh = $this->establishSshConnection($server);

            // Fetch the list of services
            $services = $this->getSystemdServices($ssh);

            // Save the services to the database
            foreach ($services as $service) {
                $this->saveService($server, $service);
            }

            Log::info("Successfully discovered services for server: {$server->name}");
            return true;

        } catch (\Exception $e) {
            Log::error("Service discovery failed for {$server->name}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves and parses the list of active `systemd` services on a remote Linux server using SSH.
     *
     * @param SSH2 $ssh An active SSH2 connection (phpseclib) to the target server.
     * @return array Parsed list of services with name, status, and description.
     */
    private function getSystemdServices(SSH2 $ssh): array
    {
        // Step 1: Run the `systemctl` command remotely to get all services
        Log::info("Executing 'systemctl list-units --type=service --no-pager' on server.");
        $output = $ssh->exec($this->commandPrefix . ' systemctl list-units --type=service --no-pager');

        // Step 2: Log the full raw output from the server for debugging
        Log::debug("Raw systemctl output:\n" . $output);

        // Step 3: Normalize output into an array of lines
        $lines = preg_split('/\r\n|\r|\n/', trim($output));
        $services = [];
        $headerParsed = false; // Flag to track when we've reached the service list

        foreach ($lines as $line) {
            $line = trim($line); // Clean up line whitespace

            // Step 4: Skip blank lines, footer messages, or legends
            if (
                $line === '' ||
                str_starts_with($line, 'Legend:') ||
                str_contains($line, 'loaded units listed')
            ) {
                continue;
            }

            // Step 5: Detect and skip the header line (e.g., "UNIT LOAD ACTIVE SUB DESCRIPTION")
            if (!$headerParsed && preg_match('/^\s*UNIT\s+LOAD\s+ACTIVE\s+SUB\s+DESCRIPTION/', $line)) {
                $headerParsed = true; // Mark that header is seen, so we can process service lines next
                continue;
            }

            // Step 6: After the header is parsed, start parsing service lines
            if ($headerParsed) {
                Log::debug("Processing line: {$line}");

                // Step 7: Match expected columns from each line using regex
                if (preg_match(
                    '/^(\S+)\s+(loaded|not-found)\s+(active|inactive|failed)\s+(\S+)\s+(.*)$/',
                    $line,
                    $matches
                )) {
                    // Breakdown of matched groups
                    [$full, $unit, $load, $active, $sub, $description] = $matches;

                    // Step 8: Only include fully "loaded" services (ignore incomplete or missing ones)
                    if ($load === 'loaded') {
                        Log::info("Parsed service: {$unit} (Status: {$active}/{$sub}, Description: {$description})");

                        // Step 9: Add parsed service to results
                        $services[] = [
                            'name' => $unit,
                            'status' => "$active/$sub", // Combined high-level and low-level status
                            'description' => $description,
                        ];
                    } else {
                        Log::debug("Skipping non-loaded unit: {$unit}");
                    }
                } else {
                    // Log any line that doesn't match the expected format (might be edge case or unexpected)
                    Log::debug("Skipping line (does not match expected format): {$line}");
                }
            }
        }

        // Final log for how many services were parsed
        Log::info("Parsed " . count($services) . " services from systemctl output.");

        return $services;
    }



    /**
     * Saves or updates a systemd service record for the given server.
     *
     * @param Server $server The server instance this service belongs to.
     * @param array $serviceData Associative array containing 'name', 'description', and 'status'.
     * @return void
     */
    private function saveService(Server $server, array $serviceData): void
    {
        // Step 1: Try to find an existing service by server ID and service name.
        // If it doesn't exist, create a new instance (but don't save yet).
        $service = Service::firstOrNew([
            'server_id' => $server->id,
            'name' => $serviceData['name'],
        ]);

        // Step 2: Fill or update the fields with the latest service data
        $service->fill([
            'description' => $serviceData['description'], // e.g., "D-Bus System Message Bus"
            'type' => 'systemd',                          // Hardcoded type since we're parsing `systemctl`
            'status' => $serviceData['status'],           // e.g., "active/running" or "inactive/dead"
            'last_checked_at' => now(),                   // Timestamp of this check/update
        ])->save(); // Step 3: Persist the changes to the database (insert or update)
    }


    public function getServiceDetails(Server $server, string $serviceName): ?array
    {
        $server = $server->fresh(['agentConnection']);

        Log::info("Fetching service details for {$serviceName} on server: {$server->name} ({$server->ip_address})");

        if (!$server->agentConnection) {
            Log::error("No agent connection configured for {$server->name}");
            return null;
        }

        try {
            $ssh = $this->establishSshConnection($server);

            $details = [
                'details' => $this->getSimpleServiceStatus($ssh, $serviceName)
            ];

            Log::info("Successfully fetched details for service {$serviceName} on server: {$server->name}");
            return $details;

        } catch (\Exception $e) {
            Log::error("Failed to fetch details for service {$serviceName} on {$server->name}: " . $e->getMessage());
            return null;
        }
    }

    protected function getServiceStatus($ssh, string $serviceName): array
    {
        $statusCommand = "systemctl is-active {$serviceName}";
        $enabledCommand = "systemctl is-enabled {$serviceName}";

        return [
            'active' => trim($ssh->exec($statusCommand)),
            'enabled' => trim($ssh->exec($enabledCommand)),
        ];
    }

    protected function getUnitFileStatus($ssh, string $serviceName): ?string
    {
        $command = "systemctl status {$serviceName} | grep 'Loaded:' | awk -F';' '{print $1}' | cut -d'(' -f2";
        return trim($ssh->exec($command)) ?: null;
    }

    protected function getServiceLogs($ssh, string $serviceName, int $lines = 20): array
    {
        // Try journalctl first (modern systems)
        $journalCommand = "journalctl -u {$serviceName} -n {$lines} --no-pager 2>/dev/null";
        $journalLogs = trim($ssh->exec($journalCommand));

        if (!empty($journalLogs)) {
            return [
                'source' => 'journalctl',
                'logs' => $journalLogs,
            ];
        }

        // Fallback to checking common log locations
        $commonLogLocations = [
            "/var/log/{$serviceName}.log",
            "/var/log/{$serviceName}/error.log",
            "/var/log/{$serviceName}/access.log",
            "/var/log/syslog",
            "/var/log/messages",
        ];

        foreach ($commonLogLocations as $logPath) {
            $logCommand = "sudo [ -f {$logPath} ] && sudo tail -n {$lines} {$logPath} 2>/dev/null";
            $logs = trim($ssh->exec($logCommand));

            if (!empty($logs)) {
                return [
                    'source' => $logPath,
                    'logs' => $logs,
                ];
            }
        }

        return [
            'source' => 'unknown',
            'logs' => 'No logs found for this service',
        ];
    }

    protected function getServiceConfigPaths($ssh, string $serviceName): array
    {
        // Get the main unit file path
        $unitPathCommand = "systemctl show -p FragmentPath {$serviceName} | cut -d'=' -f2";
        $unitPath = trim($ssh->exec($unitPathCommand));

        $paths = [];

        if (!empty($unitPath)) {
            $paths['unit_file'] = $unitPath;

            // Try to find included configs
            $includesCommand = "grep -E '^Include|^include' {$unitPath} 2>/dev/null || true";
            $includes = trim($ssh->exec($includesCommand));

            if (!empty($includes)) {
                $paths['includes'] = array_map('trim', explode("\n", $includes));
            }
        }

        // Check for common config directories
        $commonConfigDirs = [
            "/etc/{$serviceName}",
            "/etc/{$serviceName}.d",
            "/etc/{$serviceName}/conf.d",
        ];

        foreach ($commonConfigDirs as $dir) {
            $checkDirCommand = "[ -d {$dir} ] && ls {$dir}/*.conf 2>/dev/null || true";
            $configFiles = trim($ssh->exec($checkDirCommand));

            if (!empty($configFiles)) {
                $paths['config_dir'] = $dir;
                $paths['config_files'] = array_map('trim', explode("\n", $configFiles));
                break;
            }
        }

        return $paths;
    }

    protected function getServiceResourceUsage($ssh, string $serviceName): array
    {
        // Get main PID
        $pidCommand = "systemctl show -p MainPID {$serviceName} --value";
        $pid = trim($ssh->exec($pidCommand));

        if (empty($pid) || $pid === '0') {
            return [
                'cpu_percent' => 0,
                'memory_percent' => 0,
                'memory_usage' => '0 KB',
                'process_count' => 0,
            ];
        }

        // Get process tree resource usage
        $psCommand = "ps -o %cpu,%mem,rss,cmd --ppid {$pid} 2>/dev/null || ps -o %cpu,%mem,rss,cmd --pid {$pid} 2>/dev/null";
        $processes = array_filter(explode("\n", trim($ssh->exec($psCommand))));

        // Remove header row
        array_shift($processes);

        $totalCpu = 0;
        $totalMem = 0;
        $totalRss = 0;

        foreach ($processes as $process) {
            $parts = preg_split('/\s+/', trim($process), 4);
            if (count($parts) >= 3) {
                $totalCpu += (float)$parts[0];
                $totalMem += (float)$parts[1];
                $totalRss += (int)$parts[2];
            }
        }

        return [
            'cpu_percent' => round($totalCpu, 2),
            'memory_percent' => round($totalMem, 2),
            'memory_usage' => round($totalRss / 1024, 2) . ' MB',
            'process_count' => count($processes),
        ];
    }

    protected function getServiceStartupTime($ssh, string $serviceName): ?string
    {
        $command = "systemctl show -p ExecMainStartTimestamp {$serviceName} --value";
        $timestamp = trim($ssh->exec($command));

        return !empty($timestamp) ? $timestamp : null;
    }

    protected function getSimpleServiceStatus($ssh, string $serviceName): ?string
    {
        $command = "systemctl status $serviceName";
        return trim($ssh->exec($command));
    }

}
