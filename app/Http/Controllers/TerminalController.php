<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\TerminalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    use AuthorizesRequests;

    /**
     * @var TerminalService
     */
    private $terminalService;

    /**
     * TerminalController constructor.
     *
     * @param TerminalService $terminalService
     */
    public function __construct(TerminalService $terminalService)
    {
        $this->terminalService = $terminalService;
    }

    public function connect(Server $server, Request $request)
    {
        // Verify user has permission to access this server's terminal
//        $this->authorize('access-terminal', $server);

        $this->terminalService->connect($server, $request->connectionId);

        return response()->json(['status' => 'connected']);
    }

    public function execute(Server $server, Request $request)
    {
        // Verify user has permission to access this server's terminal
//        $this->authorize('access-terminal', $server);

        try {
            $this->terminalService->executeCommand(
                $server,
                $request->command,
                $request->connectionId,
                $request->sudoEnabled ?? false
            );

            return response()->json(['status' => 'executed']);
        } catch (\Exception $e) {
            $statusCode = 500;

            if (strpos($e->getMessage(), 'Command blocked') !== false) {
                $statusCode = 403;
            } elseif (strpos($e->getMessage(), 'SSH login failed') !== false) {
                $statusCode = 401;
            }

            return response()->json([
                'error' => $e->getMessage()
            ], $statusCode);
        }
    }

    public function disconnect(Server $server, Request $request)
    {
        // Verify user has permission to access this server's terminal
//        $this->authorize('access-terminal', $server);

        $this->terminalService->disconnect($server, $request->connectionId);

        return response()->json(['status' => 'disconnected']);
    }
}
