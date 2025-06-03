<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApplicationService
{
    /**
     * @var SSHService
     */
    private $sshService;

    /**
     * ApplicationService constructor.
     *
     * @param SSHService $sshService
     */
    public function __construct(SSHService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Create a new application
     *
     * @param array $data
     * @return Application
     */
    public function createApplication(array $data): Application
    {
        // Determine application status based on app_url
        $status = $this->checkApplicationStatus($data['app_url']);

        // Create the application with status
        $application = Application::create(array_merge($data, [
            'status' => $status,
            'framework_version' => null,
            'language_version' => null,
            'environment_variables' => null,
            'additional_settings' => null,
            'notes' => null,
        ]));

        return $application;
    }

    /**
     * Update an application
     *
     * @param Application $application
     * @param array $data
     * @return Application
     */
    public function updateApplication(Application $application, array $data): Application
    {
        // Determine application status based on app_url
        $status = $this->checkApplicationStatus($data['app_url']);

        // Add status to data
        $data['status'] = $status;

        // Update the application with validated data
        $application->update($data);

        return $application;
    }

    /**
     * Delete an application
     *
     * @param Application $application
     * @return bool
     */
    public function deleteApplication(Application $application): bool
    {
        try {
            $application->delete();
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete application: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check the status of an application by making an HTTP request to its URL
     *
     * @param string $appUrl
     * @return string
     */
    private function checkApplicationStatus(string $appUrl): string
    {
        try {
            $response = Http::timeout(5)->get($appUrl);

            if ($response->successful()) {
                return 'up';
            } else {
                return 'down';
            }
        } catch (\Exception $e) {
            Log::warning("Failed to check application status for {$appUrl}: " . $e->getMessage());
            return 'down'; // Could not connect or timed out
        }
    }

    /**
     * Fetch logs for an application
     *
     * @param Application $application
     * @param string $logPath
     * @return array
     * @throws \Exception
     */
    public function fetchLogs(Application $application, string $logPath): array
    {
        $server = Server::with('agentConnection')->find($application->server_id);

        if (!$server) {
            throw new \Exception("Server not found for application {$application->name}");
        }

        $command = "tail -n 50 {$logPath} 2>/dev/null";
        $logs = $this->sshService->executeCommand($server, $command, true);

        return [
            'logs' => $logs,
            'log_path' => $logPath
        ];
    }
}
