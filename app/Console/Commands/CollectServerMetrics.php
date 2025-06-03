<?php
//
//namespace App\Console\Commands;
//
//use App\Models\Server;
//use App\Models\ServerMetric;
//use phpseclib3\Net\SSH2;
//use Illuminate\Console\Command;
//use Illuminate\Support\Facades\Log;
//
//class CollectServerMetrics extends Command
//{
//    protected $signature = 'servers:collect-metrics';
//    protected $description = 'Collect performance metrics from servers via SSH';
//
//    public function handle()
//    {
//        $servers = Server::with('agentConnection')
//            ->where('is_active', true)
//            ->get();
//
//        if ($servers->isEmpty()) {
//            $this->error('No active servers found.');
//            return;
//        }
//
//        foreach ($servers as $server) {
//            $this->info("Collecting metrics for: {$server->name} ({$server->ip_address})");
//
//            if (!$server->agentConnection) {
//                $this->error("No agent connection configured for {$server->name}");
//                continue;
//            }
//
//            try {
//                $ssh = new SSH2($server->ip_address, $server->agentConnection->port ?? 22);
//
//                if ($server->agentConnection->auth_type === 'key') {
//                    $authSuccessful = $ssh->login(
//                        $server->agentConnection->username,
//                        $server->agentConnection->ssh_key
//                    );
//                } else {
//                    $authSuccessful = $ssh->login(
//                        $server->agentConnection->username,
//                        $server->agentConnection->password
//                    );
//                }
//
//                if (!$authSuccessful) {
//                    throw new \Exception("SSH authentication failed");
//                }
//
//                $ssh->setTimeout(10); // Timeout in seconds (adjust as needed)
//
//                // Get all metrics first
//                $metrics = [
//                    'server_id' => $server->id,
//                    'cpu_usage' => $this->getCpuUsage($ssh),
//                    'memory_total' => $this->getMemoryTotal($ssh),
//                    'memory_used' => $this->getMemoryUsed($ssh),
//                    'disk_total' => $this->getDiskTotal($ssh),
//                    'disk_used' => $this->getDiskUsed($ssh),
//                    'process_count' => $this->getProcessCount($ssh),
//                    'load_avg_1min' => $this->getLoadAvg1Min($ssh),
//                    'load_avg_5min' => $this->getLoadAvg5Min($ssh),
//                    'load_avg_15min' => $this->getLoadAvg15Min($ssh),
//                    'uptime_date' => $this->getUptimeDate($ssh),
//                    'network_in' => $this->getNetworkIn($ssh, $server),
//                    'network_out' => $this->getNetworkOut($ssh, $server),
//                    'swap_total' => $this->getSwapTotal($ssh),
//                    'swap_used' => $this->getSwapUsed($ssh),
//                    'recorded_at' => now(),
//                ];
//
//                // Debug output
//                $this->info("Collected metrics: " . json_encode($metrics, JSON_PRETTY_PRINT));
//
//                // Create new metrics record
//                ServerMetric::create($metrics);
//
//                $this->info("✅ Metrics collected successfully.");
//
//            } catch (\Exception $e) {
//                Log::error("Metrics collection failed for {$server->name}: " . $e->getMessage());
//                $this->error("❌ Failed: " . $e->getMessage());
//
//                // Log the full exception for debugging
//                Log::error($e);
//            }
//        }
//    }
//
//    // Metric collection methods
//    // Updated metric collection methods with better error handling
//    private function getCpuUsage(SSH2 $ssh): float
//    {
//        try {
//            $output = $ssh->exec("top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - \$1}'");
//            return (float) trim($output);
//        } catch (\Exception $e) {
//            Log::warning("Failed to get CPU usage: " . $e->getMessage());
//            return 0.0;
//        }
//    }
//
//    private function getMemoryTotal(SSH2 $ssh): float
//    {
//        // Memory total in MB
//        $output = $ssh->exec("free -m | grep Mem | awk '{print $2}'");
//        return (float) trim($output);
//    }
//
//    private function getMemoryUsed(SSH2 $ssh): float
//    {
//        // Memory used in MB (excluding buffers/cache)
//        $output = $ssh->exec("free -m | grep Mem | awk '{print $3}'");
//        return (float) trim($output);
//    }
//
//    private function getDiskTotal(SSH2 $ssh): float
//    {
//        // Disk total in MB
//        $output = $ssh->exec("df -m / | tail -1 | awk '{print $2}'");
//        return (float) trim($output);
//    }
//
//    private function getDiskUsed(SSH2 $ssh): float
//    {
//        // Disk used in MB
//        $output = $ssh->exec("df -m / | tail -1 | awk '{print $3}'");
//        return (float) trim($output);
//    }
//
//    private function getProcessCount(SSH2 $ssh): int
//    {
//        $output = $ssh->exec("ps -e | wc -l");
//        return (int) trim($output);
//    }
//
//    private function getLoadAvg1Min(SSH2 $ssh): float
//    {
//        $output = $ssh->exec("cat /proc/loadavg | awk '{print $1}'");
//        return (float) trim($output);
//    }
//
//    private function getLoadAvg5Min(SSH2 $ssh): float
//    {
//        $output = $ssh->exec("cat /proc/loadavg | awk '{print $2}'");
//        return (float) trim($output);
//    }
//
//    private function getLoadAvg15Min(SSH2 $ssh): float
//    {
//        $output = $ssh->exec("cat /proc/loadavg | awk '{print $3}'");
//        return (float) trim($output);
//    }
//
//    private function getUptimeDate(SSH2 $ssh): ?string
//    {
//        try {
//            // Get the system boot time in format: "2025-04-10 08:30:45"
//            $output = trim($ssh->exec('uptime -s'));
//
////            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $output)) {
////                return $output;
////            }// Validate the output looks like a timestamp
//            return $output;
//
//        } catch (\Exception $e) {
//            Log::warning("Failed to get uptime date: " . $e->getMessage());
//            return null;
//        }
//    }
//
//    private function getNetworkIn(SSH2 $ssh, Server $server): int
//    {
//        try {
//            $interface = $this->getNetworkInterface($ssh, $server);
//            $output = $ssh->exec("cat /proc/net/dev | grep $interface | awk '{print \$2}'");
//            return (int) trim($output);
//        } catch (\Exception $e) {
//            Log::warning("Failed to get network in: " . $e->getMessage());
//            return 0;
//        }
//    }
//
//    private function getNetworkOut(SSH2 $ssh, Server $server): int
//    {
//        try {
//            $interface = $this->getNetworkInterface($ssh, $server);
//            $output = $ssh->exec("cat /proc/net/dev | grep $interface | awk '{print \$10}'");
//            return (int) trim($output);
//        } catch (\Exception $e) {
//            Log::warning("Failed to get network out: " . $e->getMessage());
//            return 0;
//        }
//    }
//
//    private function getSwapTotal(SSH2 $ssh): float
//    {
//        $output = $ssh->exec("free -m | grep Swap | awk '{print $2}'");
//        return (float) trim($output);
//    }
//
//    private function getSwapUsed(SSH2 $ssh): float
//    {
//        $output = $ssh->exec("free -m | grep Swap | awk '{print $3}'");
//        return (float) trim($output);
//    }
//
//    private function getNetworkInterface(SSH2 $ssh, Server $server): string
//    {
//        // Try to get the primary network interface
//        try {
//            $output = $ssh->exec("route | grep '^default' | grep -o '[^ ]*$'");
//            $interface = trim($output);
//
//            if (empty($interface)) {
//                $interface = 'eth0'; // fallback
//            }
//
//            return $interface;
//        } catch (\Exception $e) {
//            Log::warning("Failed to detect network interface for {$server->name}, using eth0 as fallback");
//            return 'eth0';
//        }
//    }
//}
namespace App\Console\Commands;

use App\Models\Server;
use App\Models\ServerMetric;
use phpseclib3\Net\SSH2;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectServerMetrics extends Command
{
    protected $signature = 'servers:collect-metrics';
    protected $description = 'Collect performance metrics from servers via SSH';

    public function handle()
    {
        $servers = Server::with('agentConnection')
            ->where('is_active', true)
            ->get();

        if ($servers->isEmpty()) {
            $this->error('No active servers found.');
            return;
        }

        foreach ($servers as $server) {
            $this->info("Collecting metrics for: {$server->name} ({$server->ip_address})");

            if (!$server->agentConnection) {
                $this->error("No agent connection configured for {$server->name}");
                continue;
            }

            try {
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

                $ssh->setTimeout(10); // Timeout in seconds (adjust as needed)

                // Get all metrics first
                $metrics = [
                    'server_id' => $server->id,
                    'cpu_usage' => $this->getCpuUsage($ssh),
                    'memory_total' => $this->getMemoryTotal($ssh),
                    'memory_used' => $this->getMemoryUsed($ssh),
                    'disk_total' => $this->getDiskTotal($ssh),
                    'disk_used' => $this->getDiskUsed($ssh),
                    'process_count' => $this->getProcessCount($ssh),
                    'load_avg_1min' => $this->getLoadAvg1Min($ssh),
                    'load_avg_5min' => $this->getLoadAvg5Min($ssh),
                    'load_avg_15min' => $this->getLoadAvg15Min($ssh),
                    'uptime_date' => $this->getUptimeDate($ssh),
                    'network_in' => $this->getNetworkIn($ssh, $server),
                    'network_out' => $this->getNetworkOut($ssh, $server),
                    'swap_total' => $this->getSwapTotal($ssh),
                    'swap_used' => $this->getSwapUsed($ssh),
                    'recorded_at' => now(),
                ];

                // Debug output
                $this->info("Collected metrics: " . json_encode($metrics, JSON_PRETTY_PRINT));

                // Create new metrics record
                ServerMetric::create($metrics);

                $this->info("✅ Metrics collected successfully.");

            } catch (\Exception $e) {
                Log::error("Metrics collection failed for {$server->name}: " . $e->getMessage());
                $this->error("❌ Failed: " . $e->getMessage());

                // Log the full exception for debugging
                Log::error($e);
            }
        }
    }

    // Metric collection methods
    private function executeWithNiceAndIonice(SSH2 $ssh, string $command): string
    {
        // Add nice and ionice to the command to limit CPU and disk usage
        $commandWithNice = "ionice -c2 -n7 nice -n 19 $command";
        return $ssh->exec($commandWithNice);
    }

    private function getCpuUsage(SSH2 $ssh): float
    {
        try {
            $output = $this->executeWithNiceAndIonice($ssh, "top -bn1 | grep 'Cpu(s)' | sed 's/.*, *\\([0-9.]*\\)%* id.*/\\1/' | awk '{print 100 - \$1}'");
            return (float) trim($output);
        } catch (\Exception $e) {
            Log::warning("Failed to get CPU usage: " . $e->getMessage());
            return 0.0;
        }
    }

    private function getMemoryTotal(SSH2 $ssh): float
    {
        // Memory total in MB
        $output = $this->executeWithNiceAndIonice($ssh, "free -m | grep Mem | awk '{print $2}'");
        return (float) trim($output);
    }

    private function getMemoryUsed(SSH2 $ssh): float
    {
        // Memory used in MB (excluding buffers/cache)
        $output = $this->executeWithNiceAndIonice($ssh, "free -m | grep Mem | awk '{print $3}'");
        return (float) trim($output);
    }

    private function getDiskTotal(SSH2 $ssh): float
    {
        // Disk total in MB
        $output = $this->executeWithNiceAndIonice($ssh, "df -m / | tail -1 | awk '{print $2}'");
        return (float) trim($output);
    }

    private function getDiskUsed(SSH2 $ssh): float
    {
        // Disk used in MB
        $output = $this->executeWithNiceAndIonice($ssh, "df -m / | tail -1 | awk '{print $3}'");
        return (float) trim($output);
    }

    private function getProcessCount(SSH2 $ssh): int
    {
        $output = $this->executeWithNiceAndIonice($ssh, "ps -e | wc -l");
        return (int) trim($output);
    }

    private function getLoadAvg1Min(SSH2 $ssh): float
    {
        $output = $this->executeWithNiceAndIonice($ssh, "cat /proc/loadavg | awk '{print $1}'");
        return (float) trim($output);
    }

    private function getLoadAvg5Min(SSH2 $ssh): float
    {
        $output = $this->executeWithNiceAndIonice($ssh, "cat /proc/loadavg | awk '{print $2}'");
        return (float) trim($output);
    }

    private function getLoadAvg15Min(SSH2 $ssh): float
    {
        $output = $this->executeWithNiceAndIonice($ssh, "cat /proc/loadavg | awk '{print $3}'");
        return (float) trim($output);
    }

    private function getUptimeDate(SSH2 $ssh): ?string
    {
        try {
            // Get the system boot time in format: "2025-04-10 08:30:45"
            $output = trim($this->executeWithNiceAndIonice($ssh, 'uptime -s'));
            return $output;
        } catch (\Exception $e) {
            Log::warning("Failed to get uptime date: " . $e->getMessage());
            return null;
        }
    }

    private function getNetworkIn(SSH2 $ssh, Server $server): int
    {
        try {
            $interface = $this->getNetworkInterface($ssh, $server);
            $output = $this->executeWithNiceAndIonice($ssh, "cat /proc/net/dev | grep $interface | awk '{print \$2}'");
            return (int) trim($output);
        } catch (\Exception $e) {
            Log::warning("Failed to get network in: " . $e->getMessage());
            return 0;
        }
    }

    private function getNetworkOut(SSH2 $ssh, Server $server): int
    {
        try {
            $interface = $this->getNetworkInterface($ssh, $server);
            $output = $this->executeWithNiceAndIonice($ssh, "cat /proc/net/dev | grep $interface | awk '{print \$10}'");
            return (int) trim($output);
        } catch (\Exception $e) {
            Log::warning("Failed to get network out: " . $e->getMessage());
            return 0;
        }
    }

    private function getSwapTotal(SSH2 $ssh): float
    {
        $output = $this->executeWithNiceAndIonice($ssh, "free -m | grep Swap | awk '{print $2}'");
        return (float) trim($output);
    }

    private function getSwapUsed(SSH2 $ssh): float
    {
        $output = $this->executeWithNiceAndIonice($ssh, "free -m | grep Swap | awk '{print $3}'");
        return (float) trim($output);
    }

    private function getNetworkInterface(SSH2 $ssh, Server $server): string
    {
        // Try to get the primary network interface
        try {
            $output = $ssh->exec("route | grep '^default' | grep -o '[^ ]*$'");
            $interface = trim($output);

            if (empty($interface)) {
                $interface = 'eth0'; // fallback
            }

            return $interface;
        } catch (\Exception $e) {
            Log::warning("Failed to detect network interface for {$server->name}, using eth0 as fallback");
            return 'eth0';
        }
    }
}
