<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\ServerService;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    /**
     * @var ServerService
     */
    private $serverService;

    /**
     * ServerController constructor.
     *
     * @param ServerService $serverService
     */
    public function __construct(ServerService $serverService)
    {
        $this->serverService = $serverService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('servers/index',[
            'servers' => $this->serverService->getAllServers()
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip|unique:servers,ip_address',
            'is_active' => 'boolean',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
        ]);

        $this->serverService->createServer($validated);

        return redirect()->back()->with('success', 'Server added successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Server $server)
    {
        $server = $this->serverService->getServerWithRelations($server);
        return inertia('servers/show',[
            'server' => $server,
            'servers' => Server::all(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Server $server)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Server $server)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip|unique:servers,ip_address,'.$server->id,
            'is_active' => 'boolean',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
        ]);

        $this->serverService->updateServer($server, $validated);

        return redirect()->back()->with('success', 'Server updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Server $server)
    {
//        $this->authorize('delete', $server);

        $this->serverService->deleteServer($server);

        return redirect()->route('servers.index')->with('success', 'Server deleted successfully');
    }

    public function fetch(Server $server): void
    {
        $this->serverService->fetchServerMetrics($server);
    }

//    public function execute(Request $request, Server $server)
//    {
////        dd($request->command);
//        $validated = $request->validate([
//            'command' => 'required|string|max:50'
//        ]);
//
//        try {
//            $ssh = new SSH2($server->ip_address, $server->agentConnection->port ?? 22);
//
//
//            if (!$ssh->login($server->agentConnection->username, $server->agentConnection->password)) {
//                return response()->json(['output' => 'SSH login failed'], 401);
//            }
//            $output = $ssh->exec($validated['command']);
//
//            return response()->json(['output' => $output]);
//
//        } catch (\Exception $e){
//            return response()->json(['output' => 'Error: ' . $e->getMessage()], 500);
//        }
//
//    }
    public function execute(Request $request, Server $server)
    {
        $validated = $request->validate([
            'command' => 'required|string|max:255'
        ]);

        try {
            $output = $this->serverService->executeCommand(
                $server,
                $validated['command'],
                $request->sudoEnabled ?? false
            );

            return response()->json([
                'output' => $output
            ]);
        } catch (\Exception $e) {
            $statusCode = 500;

            if (strpos($e->getMessage(), 'Command blocked') !== false) {
                $statusCode = 403;
            } elseif (strpos($e->getMessage(), 'SSH login failed') !== false) {
                $statusCode = 401;
            }

            return response()->json([
                'output' => 'Error: ' . $e->getMessage()
            ], $statusCode);
        }
    }
}
