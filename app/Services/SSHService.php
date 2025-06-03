<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;

class SSHService
{
    /**
     * Connection pool to reuse SSH connections
     * @var array
     */
    private static $connections = [];

    /**
     * Default connection timeout in seconds
     * @var int
     */
    private $timeout = 10;

    /**
     * Maximum number of retry attempts
     * @var int
     */
    private $maxRetries = 3;

    /**
     * Delay between retries in seconds
     * @var int
     */
    private $retryDelay = 2;

    /**
     * Get an SSH connection for the given server
     *
     * @param Server $server
     * @param bool $forceNew Force creation of a new connection
     * @return SSH2
     * @throws \Exception
     */
    public function getConnection(Server $server, bool $forceNew = false): SSH2
    {
        $connectionKey = $this->getConnectionKey($server);

        // Return existing connection if available and not forcing new
        if (!$forceNew && isset(self::$connections[$connectionKey]) && self::$connections[$connectionKey]['connection']->isConnected()) {
            return self::$connections[$connectionKey]['connection'];
        }

        // Create new connection with retry logic
        return $this->createConnection($server);
    }

    /**
     * Get an SFTP connection for the given server
     *
     * @param Server $server
     * @param bool $forceNew Force creation of a new connection
     * @return SFTP
     * @throws \Exception
     */
    public function getSFTPConnection(Server $server, bool $forceNew = false): SFTP
    {
        $connectionKey = 'sftp_' . $this->getConnectionKey($server);

        // Return existing connection if available and not forcing new
        if (!$forceNew && isset(self::$connections[$connectionKey]) && self::$connections[$connectionKey]['connection']->isConnected()) {
            return self::$connections[$connectionKey]['connection'];
        }

        // Create new SFTP connection with retry logic
        return $this->createSFTPConnection($server);
    }

    /**
     * Execute a command on the server
     *
     * @param Server $server
     * @param string $command
     * @param bool $sudo Whether to run the command with sudo
     * @return string Command output
     * @throws \Exception
     */
    public function executeCommand(Server $server, string $command, bool $sudo = false): string
    {
        $ssh = $this->getConnection($server);

        if ($sudo) {
            $password = sprintf("'%s'", $server->agentConnection->password);
            $command = "echo {$password} | sudo -S {$command}";
        }

        try {
            $output = $ssh->exec($command);
            return $output;
        } catch (\Exception $e) {
            // If command execution fails, try with a fresh connection
            $this->closeConnection($server);
            $ssh = $this->getConnection($server, true);

            $output = $ssh->exec($command);
            return $output;
        }
    }

    /**
     * Check if a command is dangerous and should be blocked
     *
     * @param string $command
     * @return bool
     */
    public function isDangerousCommand(string $command): bool
    {
        $command = strtolower(trim($command));

        // Remove multiple spaces and normalize
        $command = preg_replace('/\s+/', ' ', $command);

        // Dangerous commands patterns
        $dangerousPatterns = [
            // System destruction
            '/\brm\s+(-[rf]*\s*)*\//',           // rm / or rm -rf /
            '/\brm\s+(-[rf]*\s*)*\*/',           // rm * or rm -rf *
            '/\bmkfs\./',                         // Format filesystem
            '/\bdd\s+.*of=\/dev\//',             // dd to device
            '/\bshred\s/',                        // Secure delete
            '/\bwipe\s/',                         // Wipe data

            // Process/system control
            '/\bkillall\s/',                      // Kill all processes
            '/\bpkill\s+-9/',                     // Force kill processes
            '/\bkill\s+-9\s+1\b/',               // Kill init process
            '/\breboot\b/',                       // System reboot
            '/\bshutdown\b/',                     // System shutdown
            '/\bhalt\b/',                         // System halt
            '/\bpoweroff\b/',                     // Power off
            '/\binit\s+0\b/',                     // Shutdown via init
            '/\binit\s+6\b/',                     // Restart via init

            // User/permission manipulation
            '/\buserdel\s/',                      // Delete user
            '/\busermod\s.*-s\s*\/bin\/false/',  // Disable user shell
            '/\bpasswd\s+.*root/',               // Change root password
            '/\bchmod\s+000\s/',                 // Remove all permissions
            '/\bchmod\s+777\s+\//',              // Dangerous permissions on root
            '/\bchown\s+.*:.*\s+\//',            // Change ownership of root

            // Network/firewall
            '/\biptables\s+-F/',                  // Flush firewall rules
            '/\bufw\s+disable/',                  // Disable firewall
            '/\bservice\s+.*stop/',              // Stop services
            '/\bsystemctl\s+stop/',              // Stop systemd services
            '/\bsystemctl\s+disable/',           // Disable systemd services

            // Package management dangers
            '/\bapt\s+remove\s+.*sudo/',         // Remove sudo
            '/\bapt\s+remove\s+.*ssh/',          // Remove SSH
            '/\byum\s+remove\s+.*sudo/',         // Remove sudo (CentOS)
            '/\byum\s+remove\s+.*ssh/',          // Remove SSH (CentOS)
            '/\bpip\s+uninstall\s+-y\s+pip/',   // Uninstall pip itself

            // Dangerous file operations
            '/>\s*\/dev\/sda/',                   // Write to disk device
            '/>\s*\/dev\/null\s+2>&1\s*&/',      // Background dangerous operations
            '/\bchattr\s+\+i\s+\//',             // Make root immutable
            '/\bmount\s+.*\/dev\//',             // Mount operations
            '/\bumount\s+\//',                   // Unmount root

            // Code injection attempts
            '/;\s*(rm|del|format|shutdown)/',     // Command chaining
            '/\|\s*(rm|del|format|shutdown)/',   // Pipe to dangerous commands
            '/&&\s*(rm|del|format|shutdown)/',   // AND dangerous commands
            '/\$\(.*rm.*\)/',                    // Command substitution with rm
            '/`.*rm.*`/',                        // Backtick command substitution

            // Cron/scheduled tasks
            '/\bcrontab\s+-r/',                  // Remove all cron jobs
            '/>\s*\/etc\/crontab/',              // Overwrite system crontab

            // SSH/security
            '/>\s*\/root\/\.ssh\/authorized_keys/', // Overwrite SSH keys
            '/>\s*\/home\/.*\/\.ssh\/authorized_keys/', // Overwrite user SSH keys
            '/\bservice\s+ssh\s+stop/',          // Stop SSH service
            '/\bsystemctl\s+stop\s+ssh/',       // Stop SSH via systemctl

            // Logging/audit
            '/>\s*\/var\/log\//',                // Overwrite log files
            '/\bshred\s+\/var\/log\//',         // Destroy log files

            // Docker/container dangers (if applicable)
            '/\bdocker\s+rm\s+-f\s+\$\(docker\s+ps\s+-aq\)/', // Remove all containers
            '/\bdocker\s+system\s+prune\s+-af/', // Remove all docker data
        ];

        // Check against patterns
        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return true;
            }
        }

        // Check for suspicious characters/sequences
        $suspiciousSequences = [
            '/dev/sd',     // Direct disk access
            '/dev/hd',     // Direct disk access
            '/dev/null',   // Null device (context dependent)
            '$((',         // Arithmetic expansion that might hide commands
            '2>/dev/null', // Error redirection (sometimes used to hide)
        ];

        foreach ($suspiciousSequences as $sequence) {
            if (stripos($command, $sequence) !== false) {
                // Additional context checking could be added here
                if ($this->isHighRiskContext($command, $sequence)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a command with a suspicious sequence is in a high-risk context
     *
     * @param string $command
     * @param string $sequence
     * @return bool
     */
    private function isHighRiskContext(string $command, string $sequence): bool
    {
        // Additional context-specific risk assessment
        switch ($sequence) {
            case '/dev/null':
                // Allow simple redirections but block dangerous combinations
                return preg_match('/rm.*>\s*\/dev\/null/', $command) ||
                    preg_match('/dd.*>\s*\/dev\/null/', $command);

            case '2>/dev/null':
                // Block when combined with potentially dangerous commands
                return preg_match('/(rm|dd|mkfs|shred).*2>\/dev\/null/', $command);

            default:
                return true;
        }
    }

    /**
     * Close a connection to the server
     *
     * @param Server $server
     * @return void
     */
    public function closeConnection(Server $server): void
    {
        $connectionKey = $this->getConnectionKey($server);

        if (isset(self::$connections[$connectionKey])) {
            try {
                self::$connections[$connectionKey]['connection']->exec('exit');
            } catch (\Exception $e) {
                // Ignore errors when closing connection
            }

            unset(self::$connections[$connectionKey]);
        }

        // Also close SFTP connection if exists
        $sftpKey = 'sftp_' . $connectionKey;
        if (isset(self::$connections[$sftpKey])) {
            unset(self::$connections[$sftpKey]);
        }
    }

    /**
     * Close all open connections
     *
     * @return void
     */
    public function closeAllConnections(): void
    {
        foreach (self::$connections as $key => $connection) {
            try {
                if (strpos($key, 'sftp_') === 0) {
                    // SFTP connections don't need explicit exit
                    continue;
                }
                $connection['connection']->exec('exit');
            } catch (\Exception $e) {
                // Ignore errors when closing connections
            }
        }

        self::$connections = [];
    }

    /**
     * Create a new SSH connection with retry logic
     *
     * @param Server $server
     * @return SSH2
     * @throws \Exception
     */
    private function createConnection(Server $server): SSH2
    {
        $server = $server->fresh(['agentConnection']);

        if (!$server->agentConnection) {
            throw new \Exception("No agent connection configured for {$server->name}");
        }

        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $ssh = new SSH2($server->ip_address, $server->agentConnection->port ?? 22);
                $ssh->setTimeout($this->timeout);

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

                // Set terminal type and keepalive
                $ssh->exec('export TERM=xterm-256color');
                $ssh->setKeepAlive(10);

                // Store connection in pool
                $connectionKey = $this->getConnectionKey($server);
                self::$connections[$connectionKey] = [
                    'connection' => $ssh,
                    'created_at' => time(),
                    'last_used' => time(),
                ];

                return $ssh;
            } catch (\Exception $e) {
                $lastException = $e;
                $attempts++;

                if ($attempts < $this->maxRetries) {
                    Log::warning("SSH connection attempt {$attempts} failed for server {$server->name}: {$e->getMessage()}. Retrying in {$this->retryDelay} seconds.");
                    sleep($this->retryDelay);
                }
            }
        }

        // All attempts failed
        Log::error("All SSH connection attempts failed for server {$server->name}");
        throw $lastException ?? new \Exception("Failed to establish SSH connection after {$this->maxRetries} attempts");
    }

    /**
     * Create a new SFTP connection with retry logic
     *
     * @param Server $server
     * @return SFTP
     * @throws \Exception
     */
    private function createSFTPConnection(Server $server): SFTP
    {
        $server = $server->fresh(['agentConnection']);

        if (!$server->agentConnection) {
            throw new \Exception("No agent connection configured for {$server->name}");
        }

        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $sftp = new SFTP($server->ip_address, $server->agentConnection->port ?? 22);
                $sftp->setTimeout($this->timeout);

                if ($server->agentConnection->auth_type === 'key') {
                    $authSuccessful = $sftp->login(
                        $server->agentConnection->username,
                        $server->agentConnection->ssh_key
                    );
                } else {
                    $authSuccessful = $sftp->login(
                        $server->agentConnection->username,
                        $server->agentConnection->password
                    );
                }

                if (!$authSuccessful) {
                    throw new \Exception("SFTP authentication failed");
                }

                // Store connection in pool
                $connectionKey = 'sftp_' . $this->getConnectionKey($server);
                self::$connections[$connectionKey] = [
                    'connection' => $sftp,
                    'created_at' => time(),
                    'last_used' => time(),
                ];

                return $sftp;
            } catch (\Exception $e) {
                $lastException = $e;
                $attempts++;

                if ($attempts < $this->maxRetries) {
                    Log::warning("SFTP connection attempt {$attempts} failed for server {$server->name}: {$e->getMessage()}. Retrying in {$this->retryDelay} seconds.");
                    sleep($this->retryDelay);
                }
            }
        }

        // All attempts failed
        Log::error("All SFTP connection attempts failed for server {$server->name}");
        throw $lastException ?? new \Exception("Failed to establish SFTP connection after {$this->maxRetries} attempts");
    }

    /**
     * Generate a unique key for the connection pool based on server details
     *
     * @param Server $server
     * @return string
     */
    private function getConnectionKey(Server $server): string
    {
        return $server->id . '_' . $server->ip_address . '_' . ($server->agentConnection->port ?? 22);
    }

    /**
     * Clean up resources when the service is destroyed
     */
    public function __destruct()
    {
        $this->closeAllConnections();
    }
}
