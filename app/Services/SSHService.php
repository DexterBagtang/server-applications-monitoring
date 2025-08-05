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
            $command = "echo {$password} | sudo -S -p \"\" bash -lc '{$command}'";
        }

        try {
            $output = $ssh->exec($command.' 2>&1');
            return $output;
        } catch (\Exception $e) {
            // If command execution fails, try with a fresh connection
            $this->closeConnection($server);
            $ssh = $this->getConnection($server, true);
            return $ssh->exec($command.' 2>&1');
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

        // Normalize whitespace
        $command = preg_replace('/\s+/', ' ', $command);

        // Optional: Define safe paths for rm commands (customize as needed)
        $safeRmPaths = [
            '/tmp/',
            '/home/youruser/uploads/',
            // Add more safe paths as needed
        ];

        // Check if it's an rm command
//        if (preg_match('/\brm\s+(.*)/', $command, $matches)) {
//            $target = $matches[1];
//
//            // Block known dangerous patterns for rm
//            $rmDangerousPatterns = [
////                '/\brm\s+-[rf]+\s+\/\s*/',         // rm -rf /
////                '/\brm\s+-[rf]+\s+\*/',            // rm -rf *
////                '/\brm\s+\*/',                     // rm *
////                '/\brm\s+.*\/\*/',                 // rm /home/*
////                '/\brm\s+-[rf]+\s+\$[A-Za-z_]+/',  // rm -rf $VAR
//            ];
//
//            foreach ($rmDangerousPatterns as $pattern) {
//                if (preg_match($pattern, $command)) {
//                    return true;
//                }
//            }
//
//            // Optional whitelist check: block if not under allowed paths
//            $isSafePath = false;
//            foreach ($safeRmPaths as $path) {
//                if (strpos($target, $path) === 0) {
//                    $isSafePath = true;
//                    break;
//                }
//            }
//
//            if (!$isSafePath) {
//                return true; // Block rm if not under safe path
//            }
//        }

        // General dangerous patterns
        $dangerousPatterns = [
            '/\bmkfs\./',
            '/\bdd\s+.*of=\/dev\//',
            '/\bshred\s/',
            '/\bwipe\s/',
            '/\bkillall\s/',
            '/\bpkill\s+-9/',
            '/\bkill\s+-9\s+1\b/',
            '/\breboot\b/',
            '/\bshutdown\b/',
            '/\bhalt\b/',
            '/\bpoweroff\b/',
            '/\binit\s+0\b/',
            '/\binit\s+6\b/',
            '/\buserdel\s/',
            '/\busermod\s.*-s\s*\/bin\/false/',
            '/\bpasswd\s+.*root/',
            '/\bchmod\s+000\s/',
            '/\bchmod\s+777\s+\//',
            '/\bchown\s+.*:.*\s+\//',
            '/\biptables\s+-F/',
            '/\bufw\s+disable/',
            '/\bservice\s+.*stop/',
            '/\bsystemctl\s+stop/',
            '/\bsystemctl\s+disable/',
            '/\bapt\s+remove\s+.*sudo/',
            '/\bapt\s+remove\s+.*ssh/',
            '/\byum\s+remove\s+.*sudo/',
            '/\byum\s+remove\s+.*ssh/',
            '/\bpip\s+uninstall\s+-y\s+pip/',
            '/>\s*\/dev\/sda/',
            '/>\s*\/dev\/null\s+2>&1\s*&/',
            '/\bchattr\s+\+i\s+\//',
            '/\bmount\s+.*\/dev\//',
            '/\bumount\s+\//',
            '/;\s*(rm|del|format|shutdown)/',
            '/\|\s*(rm|del|format|shutdown)/',
            '/&&\s*(rm|del|format|shutdown)/',
            '/\$\(.*rm.*\)/',
            '/`.*rm.*`/',
            '/\bcrontab\s+-r/',
            '/>\s*\/etc\/crontab/',
            '/>\s*\/root\/\.ssh\/authorized_keys/',
            '/>\s*\/home\/.*\/\.ssh\/authorized_keys/',
            '/\bservice\s+ssh\s+stop/',
            '/\bsystemctl\s+stop\s+ssh/',
            '/>\s*\/var\/log\//',
            '/\bshred\s+\/var\/log\//',
            '/\bdocker\s+rm\s+-f\s+\$\(docker\s+ps\s+-aq\)/',
            '/\bdocker\s+system\s+prune\s+-af/',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return true;
            }
        }

        // Suspicious sequences (check context)
        $suspiciousSequences = [
            '/dev/sd',
            '/dev/hd',
            '/dev/null',
            '$((',
            '2>/dev/null',
        ];

        foreach ($suspiciousSequences as $sequence) {
            if (stripos($command, $sequence) !== false) {
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
                return preg_match('/dd.*>\s*\/dev\/null/', $command);

            case '2>/dev/null':
                // Block when combined with potentially dangerous commands
                return preg_match('/(dd|mkfs|shred).*2>\/dev\/null/', $command);

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
