<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ApplicationMetric;
use phpseclib3\Net\SSH2;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CollectApplicationMetrics extends Command
{
    protected $signature = 'applications:collect-metrics';
    protected $description = 'Collect performance metrics from applications via SSH';

    public function handle()
    {
        $applications = Application::with(['server.agentConnection'])
            ->where('status', 'up')
            ->get();

        if ($applications->isEmpty()) {
            $this->error('No active applications found.');
            return;
        }

        foreach ($applications as $application) {
            $this->info("Collecting metrics for: {$application->name} ({$application->app_url})");

            if (!$application->server || !$application->server->agentConnection) {
                $this->error("No agent connection configured for server of {$application->name}");
                continue;
            }

            if (!$application->access_log_path) {
                $this->warn("No access log path specified for {$application->name}, skipping");
                continue;
            }

            try {
                $server = $application->server;
                $ssh = new SSH2($server->ip_address, $server->agentConnection->port ?? 22);

                $authSuccessful = $ssh->login(
                    $server->agentConnection->username,
                    $server->agentConnection->password
                );

                if (!$authSuccessful) {
                    throw new \Exception("SSH authentication failed");
                }

                // Get application metrics
                $metrics = [
                    'application_id' => $application->id,
                    'request_count' => $this->getRequestCount($ssh, $application, $server->agentConnection->password),
                    'error_count' => $this->getErrorCount($ssh, $application, $server->agentConnection->password),
                    'response_time_avg' => 0,
                    'cache_hit_ratio' => 0,
                    'uptime' => $this->getApplicationUptime($ssh, $application, $server->agentConnection->password),
                    'recorded_at' => now(),
                ];

                // Debug output
                $this->info("Collected application metrics: " . json_encode($metrics, JSON_PRETTY_PRINT));

                // Create new metrics record
                ApplicationMetric::create($metrics);

                $this->info("✅ Application metrics collected successfully.");

            } catch (\Exception $e) {
                Log::error("Metrics collection failed for {$application->name}: " . $e->getMessage());
                $this->error("❌ Failed: " . $e->getMessage());
                // Log the full exception for debugging
                Log::error($e);
            }
        }
    }

    private function executeWithNiceAndIonice(SSH2 $ssh, string $command): string
    {
        // Add nice and ionice to the command to limit CPU and disk usage
        $commandWithNice = "ionice -c2 -n7 nice -n 19 $command";
        return $ssh->exec($commandWithNice);
    }

    private function getRequestCount(SSH2 $ssh, Application $application, $password): int
    {
        try {
            $logPath = $application->access_log_path;
            $this->info($logPath);

            // Simple command to count all lines in the log file using sudo
            $command = "echo '$password' | sudo -S wc -l $logPath 2>/dev/null";

            $this->info("Executing command: wc -l $logPath");

            // Execute the command directly
            $output = trim($ssh->exec($command));

            $this->info($output);

            $count = (int)$output;

            $this->info("Request count: " . $count);

            return $count;
        } catch (\Exception $e) {
            Log::warning("Failed to get request count for {$application->name}: " . $e->getMessage());
            $this->error("Error: " . $e->getMessage());
            return 0;
        }
    }


    private function getErrorCount(SSH2 $ssh, Application $application, $password): int
    {
        try {
            if (!$application->error_log_path) {
                return 0;
            }

            $command = "echo '$password' | sudo -S wc -l  $application->error_log_path 2>/dev/null";
            $count = (int)trim($ssh->exec($command));

            $this->info("Total error count: " . $count);
            return $count;
        } catch (\Exception $e) {
            Log::warning("Error count failed for {$application->name}: " . $e->getMessage());
            return 0;
        }
    }

    private function getApplicationUptime(SSH2 $ssh, Application $application, $password): int
    {
        try {
            $server = $application->server;

            // Get services associated with the server
            $services = $server->services;

            // If no services found, fall back to system uptime
            if ($services->isEmpty()) {
                $this->info("No services found for server, falling back to system uptime");
                return $this->getSystemUptime($ssh, $password);
            }

            // Filter services to find web servers and databases
            $webServices = $services->where('type', 'web server');
            $dbServices = $services->where('type', 'database');

            $uptime = 0;

            // Check web server uptime first
            if ($webServices->isNotEmpty()) {
                $webService = $webServices->first();
                $this->info("Checking uptime for web server: {$webService->name}");
                $uptime = $this->getServiceUptime($ssh, $webService->name, $password);
            }
            // If no web server or uptime is 0, check database uptime
            else if ($dbServices->isNotEmpty() && $uptime == 0) {
                $dbService = $dbServices->first();
                $this->info("Checking uptime for database: {$dbService->name}");
                $uptime = $this->getServiceUptime($ssh, $dbService->name, $password);
            }
            // If no web server or database, fall back to system uptime
            else {
                $this->info("No web server or database services found, falling back to system uptime");
                $uptime = $this->getSystemUptime($ssh, $password);
            }

            $this->info("Calculated uptime in seconds: " . $uptime);
            return $uptime;
        } catch (\Exception $e) {
            Log::warning("Uptime check failed for {$application->name}: " . $e->getMessage());
            return 0;
        }
    }

    private function getServiceUptime(SSH2 $ssh, string $serviceName, string $password): int
    {
        try {
            // Get the timestamp when the service became active
            $command = "echo '$password' | sudo -S systemctl show -p ActiveEnterTimestamp {$serviceName} --value";
            $timestamp = trim($ssh->exec($command));

            $this->info("Service {$serviceName} active since: " . $timestamp);

            // If timestamp is empty, service might not be running
            if (empty($timestamp)) {
                return 0;
            }

            // Parse the timestamp and calculate uptime
            $startTime = strtotime($timestamp);
            $currentTime = time();
            $uptime = $currentTime - $startTime;

            return max(0, $uptime);
        } catch (\Exception $e) {
            Log::warning("Service uptime check failed for {$serviceName}: " . $e->getMessage());
            return 0;
        }
    }

    private function getSystemUptime(SSH2 $ssh, string $password): int
    {
        // Use the 'uptime -p' command to get the system uptime
        $command = "echo '$password' | sudo -S uptime -p";
        $output = trim($ssh->exec($command));

        $this->info("System uptime output: " . $output);

        // Parse the uptime output to get seconds
        // Example outputs:
        // "up 2 days, 3 hours, 45 minutes"
        // "up 4 weeks, 4 days, 20 hours, 43 minutes"
        $uptime = 0;

        // Extract weeks if present
        $weeks = 0;
        if (preg_match('/up\s+(\d+)\s+weeks?/', $output, $weekMatches)) {
            $weeks = (int)$weekMatches[1];
        }

        // Extract days if present
        $days = 0;
        if (preg_match('/(\d+)\s+days?/', $output, $dayMatches)) {
            $days = (int)$dayMatches[1];
        }

        // Extract hours if present
        $hours = 0;
        if (preg_match('/(\d+)\s+hours?/', $output, $hourMatches)) {
            $hours = (int)$hourMatches[1];
        }

        // Extract minutes if present
        $minutes = 0;
        if (preg_match('/(\d+)\s+minutes?/', $output, $minuteMatches)) {
            $minutes = (int)$minuteMatches[1];
        }

        // Calculate total uptime in seconds
        $uptime = ($weeks * 7 * 86400) + ($days * 86400) + ($hours * 3600) + ($minutes * 60);

        $this->info("Parsed uptime: {$weeks} weeks, {$days} days, {$hours} hours, {$minutes} minutes");
        $this->info("Calculated uptime in seconds: {$uptime}");

        return $uptime;
    }

}
