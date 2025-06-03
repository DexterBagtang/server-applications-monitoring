<?php

namespace App\Console\Commands;

use App\Models\Application;
use phpseclib3\Net\SSH2;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateApplicationData extends Command
{
    protected $signature = 'applications:update-data
        {application_id? : Optional application ID, updates all if not specified}';

    protected $description = 'Fetch and update application details via SSH';


    public function handle()
    {
        $applications = $this->getApplicationsToProcess();

        if ($applications->isEmpty()) {
            return 1; // Exit code for failure
        }

        foreach ($applications as $application) {
            $this->processApplication($application);
        }

        return 0; // Success exit code
    }

    protected function getApplicationsToProcess()
    {
        $query = Application::with('server.agentConnection');
        $applicationId = $this->argument('application_id');

        if ($applicationId) {
            $applications = $query->where('id', $applicationId)->get();

            if ($applications->isEmpty()) {
                $this->error("Application with ID {$applicationId} not found.");
                return collect();
            }

            return $applications;
        }

        $applications = $query->get();

        if ($applications->isEmpty()) {
            $this->error('No applications found.');
            return collect();
        }

        return $applications;
    }

    protected function processApplication(Application $application)
    {
        $server = $application->server;
        $appName = $application->name;

        if (!$this->validateServer($server, $appName)) {
            return;
        }

        $this->info("Updating data for application: {$appName} on {$server->name}");

        try {
            $ssh = $this->establishSshConnection($server);
            $appData = $this->detectApplicationDetails($ssh, $application);

            $this->updateApplicationData($application, $appData);
            $this->info("✅ Updated application details successfully.");

        } catch (\Exception $e) {
            $this->handleApplicationError($application, $e);
        }
    }

    protected function validateServer($server, string $appName): bool
    {
        if (!$server || !$server->is_active) {
            $this->warn("Server for application '{$appName}' is not active or found. Skipping.");
            return false;
        }

        if (!$server->agentConnection) {
            $this->error("No agent connection configured for server {$server->name}");
            return false;
        }

        return true;
    }

    protected function establishSshConnection($server): SSH2
    {
        $connection = $server->agentConnection;
        $ssh = new SSH2($server->ip_address, $connection->port ?? 22);

        $authSuccessful = $connection->auth_type === 'key'
            ? $ssh->login($connection->username, $connection->ssh_key)
            : $ssh->login($connection->username, $connection->password);

        if (!$authSuccessful) {
            throw new \Exception("SSH authentication failed");
        }

        return $ssh;
    }

    protected function detectApplicationDetails(SSH2 $ssh, Application $application): array
    {
        $data = [];
        $appPath = $application->path;
        $appType = strtolower($application->type);

        if (!$this->validateApplicationPath($ssh, $appPath)) {
            $data['status'] = 'path-not-found';
            return $data;
        }

        $data['web_server'] = $this->detectWebServer($ssh);

        // First try Laravel detection (even if not specified as type)
        if ($this->isLaravelApplication($ssh, $appPath)) {
            $this->detectLaravelDetails($ssh, $appPath, $data);
        }
        // If not Laravel, use the provided type
        elseif ($appType) {
            $this->detectByProvidedType($ssh, $appPath, $appType, $data);
        }
        // Finally, try auto-detection
        else {
            $this->autoDetectFramework($ssh, $appPath, $data);
        }

        $this->setDeploymentTimestamp($ssh, $appPath, $data);
        $this->setApplicationStatus($data);

        return $data;
    }

    protected function isLaravelApplication(SSH2 $ssh, string $path): bool
    {
        $artisanExists = trim($ssh->exec("cd {$path} && if [ -f artisan ]; then echo 1; else echo 0; fi"));
        return $artisanExists === '1';
    }

    protected function detectLaravelDetails(SSH2 $ssh, string $path, array &$data): void
    {
        // Laravel version
        $versionCheck = $ssh->exec("cd {$path} && php artisan --version 2>/dev/null");
        if (preg_match('/Laravel Framework\s+([0-9\.]+)/', $versionCheck, $matches)) {
            $data['framework_version'] = $matches[1];
            $data['type'] = 'laravel';
        }

        // PHP version
        $phpVersion = trim($ssh->exec("cd {$path} && php -r \"echo phpversion();\""));
        if (preg_match('/(\d+\.\d+\.\d+)/', $phpVersion, $matches)) {
            $data['language_version'] = $matches[1];
            $data['language'] = 'php';
        }

        // Database type
        $dbConnection = trim($ssh->exec("cd {$path} && grep DB_CONNECTION .env 2>/dev/null | cut -d '=' -f2"));
        if ($dbConnection) {
            $data['database_type'] = $dbConnection;
        }

        // Environment variables
        $envVars = ['APP_ENV', 'APP_DEBUG', 'QUEUE_CONNECTION', 'CACHE_DRIVER', 'SESSION_DRIVER', 'LOG_CHANNEL'];
        $this->collectEnvironmentVariables($ssh, $path, $envVars, $data);

        // Application health check
        $storageWritable = trim($ssh->exec("cd {$path} && if [ -w ./storage ]; then echo 1; else echo 0; fi"));
        $data['status'] = $storageWritable === '1' ? 'active' : 'error';

        if ($data['status'] === 'error') {
            $data['additional_settings'] = json_encode(['error' => 'Storage directory not writable']);
        }
    }

    protected function detectByProvidedType(SSH2 $ssh, string $path, string $type, array &$data): void
    {
        $detectors = [
            'laravel' => 'detectLaravelDetails',
            'codeigniter' => 'detectCodeIgniterDetails',
            'django' => 'detectDjangoDetails',
            'node' => 'detectNodeDetails',
            'express' => 'detectNodeDetails',
            'rails' => 'detectRubyDetails',
            'ruby' => 'detectRubyDetails',
            'php' => 'detectPhpDetails',
            'python' => 'detectPythonDetails',
        ];

        if (isset($detectors[$type])) {
            $method = $detectors[$type];
            $this->$method($ssh, $path, $data);
        } else {
            $this->autoDetectFramework($ssh, $path, $data);
        }
    }

    protected function autoDetectFramework(SSH2 $ssh, string $path, array &$data): void
    {
        // Try to detect framework based on files present
        $checks = [
            'laravel' => ["cd {$path} && if [ -f artisan ]; then echo 1; else echo 0; fi", 'detectLaravelDetails'],
            'codeigniter' => ["cd {$path} && if [ -f system/core/CodeIgniter.php ]; then echo 1; else echo 0; fi", 'detectCodeIgniterDetails'],
            'django' => ["cd {$path} && if [ -f manage.py ]; then echo 1; else echo 0; fi", 'detectDjangoDetails'],
            'node' => ["cd {$path} && if [ -f package.json ]; then echo 1; else echo 0; fi", 'detectNodeDetails'],
            'rails' => ["cd {$path} && if [ -f Gemfile ] && grep -q Rails Gemfile; then echo 1; else echo 0; fi", 'detectRubyDetails'],
        ];

        foreach ($checks as $type => [$command, $method]) {
            $result = trim($ssh->exec($command));
            if ($result === '1') {
                $this->$method($ssh, $path, $data);
                return;
            }
        }

        // Fallback to language detection
        $this->detectLanguage($ssh, $path, $data);
    }

    protected function detectLanguage(SSH2 $ssh, string $path, array &$data): void
    {
        // Check for PHP files
        $phpFiles = trim($ssh->exec("cd {$path} && find . -name '*.php' | head -1"));
        if ($phpFiles) {
            $this->detectPhpDetails($ssh, $path, $data);
            return;
        }

        // Check for Python files
        $pythonFiles = trim($ssh->exec("cd {$path} && find . -name '*.py' | head -1"));
        if ($pythonFiles) {
            $this->detectPythonDetails($ssh, $path, $data);
            return;
        }

        // Check for Node.js files
        $jsFiles = trim($ssh->exec("cd {$path} && find . -name '*.js' | head -1"));
        if ($jsFiles) {
            $this->detectNodeDetails($ssh, $path, $data);
            return;
        }

        $data['status'] = 'unknown';
    }

    protected function detectPhpDetails(SSH2 $ssh, string $path, array &$data): void
    {
        $phpVersion = trim($ssh->exec("cd {$path} && php -r \"echo phpversion();\""));
        if (preg_match('/(\d+\.\d+\.\d+)/', $phpVersion, $matches)) {
            $data['language'] = 'php';
            $data['language_version'] = $matches[1];
            $data['status'] = 'active';
        }
    }

    protected function detectPythonDetails(SSH2 $ssh, string $path, array &$data): void
    {
        $pythonVersion = trim($ssh->exec("cd {$path} && python3 -V 2>/dev/null"));
        if (preg_match('/Python\s+([0-9\.]+)/', $pythonVersion, $matches)) {
            $data['language'] = 'python';
            $data['language_version'] = $matches[1];
            $data['status'] = 'active';
        }
    }

    protected function updateApplicationData(Application $application, array $appData)
    {
        $updateData = [
            'framework_version' => $appData['framework_version'] ?? null,
            'language_version' => $appData['language_version'] ?? null,
            'web_server' => $appData['web_server'] ?? null,
            'database_type' => $appData['database_type'] ?? null,
            'environment_variables' => $appData['environment_variables'] ?? null,
            'additional_settings' => $appData['additional_settings'] ?? null,
            'last_deployed_at' => $appData['last_deployed_at'] ?? null,
            'status' => $appData['status'] ?? 'unknown',
        ];

        // Only update type if we detected it
        if (isset($appData['type'])) {
            $updateData['type'] = $appData['type'];
        }

        // Only update language if we detected it
        if (isset($appData['language'])) {
            $updateData['language'] = $appData['language'];
        }

        $application->update($updateData);
    }

    protected function handleApplicationError(Application $application, \Exception $e)
    {
        Log::error("Application data update failed for {$application->name}: " . $e->getMessage());
        $this->error("❌ Failed: " . $e->getMessage());
    }

    protected function validateApplicationPath(SSH2 $ssh, string $path): bool
    {
        $pathExists = trim($ssh->exec("if [ -d {$path} ]; then echo 1; else echo 0; fi"));
        return $pathExists === '1';
    }

    protected function setDeploymentTimestamp(SSH2 $ssh, string $path, array &$data)
    {
        if (isset($data['last_deployed_at'])) {
            return;
        }

        $command = "find {$path} -type f -not -path \"*/\.*\" " .
            "-not -path \"*/vendor/*\" -not -path \"*/node_modules/*\" " .
            "-exec stat -c %Y {} \; | sort -nr | head -1";

        $lastModifiedTime = trim($ssh->exec($command));

        if ($lastModifiedTime && is_numeric($lastModifiedTime)) {
            $data['last_deployed_at'] = date('Y-m-d H:i:s', (int)$lastModifiedTime);
        }
    }

    protected function setApplicationStatus(array &$data)
    {
        if (isset($data['status'])) {
            return;
        }

        $data['status'] = isset($data['framework_version']) || isset($data['language_version'])
            ? 'active'
            : 'unknown';
    }

    protected function detectWebServer(SSH2 $ssh): ?string
    {
        $processCheck = trim($ssh->exec("ps aux | grep -E 'apache2|httpd|nginx' | grep -v grep"));

        if (strpos($processCheck, 'nginx') !== false) {
            return 'nginx';
        }

        if (strpos($processCheck, 'apache2') !== false || strpos($processCheck, 'httpd') !== false) {
            return 'apache';
        }

        return null;
    }

    protected function collectEnvironmentVariables(SSH2 $ssh, string $path, array $variables, array &$data)
    {
        $envData = [];

        foreach ($variables as $var) {
            $value = trim($ssh->exec("cd {$path} && grep {$var} .env 2>/dev/null | cut -d '=' -f2"));
            if ($value) {
                $envData[$var] = $value;
            }
        }

        if (!empty($envData)) {
            $data['environment_variables'] = json_encode($envData);
        }
    }

    // Implement other framework-specific detection methods (CodeIgniter, Django, Node, Ruby) here
    // Following the same pattern as detectLaravelDetails()
}
