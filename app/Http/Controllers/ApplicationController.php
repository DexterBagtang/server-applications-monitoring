<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\ApplicationService;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    /**
     * @var ApplicationService
     */
    private $applicationService;

    /**
     * ApplicationController constructor.
     *
     * @param ApplicationService $applicationService
     */
    public function __construct(ApplicationService $applicationService)
    {
        $this->applicationService = $applicationService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        // Validate the request data
        $validated = $request->validate([
            'server_id' => ['required', 'exists:servers,id'],
            'name' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'string', 'max:255'],
            'web_server' => ['nullable', 'string', 'max:255'],
            'database_type' => ['nullable', 'string', 'max:255'],
            'access_log_path' => ['nullable', 'string', 'max:255'],
            'error_log_path' => ['nullable', 'string', 'max:255'],
        ]);

        $this->applicationService->createApplication($validated);

        return redirect()->back()->with('success', 'Application created successfully!');
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Application $application)
    {
        // Validate the request data
        $validated = $request->validate([
            'server_id' => ['required', 'exists:servers,id'],
            'name' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'language' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'string', 'max:255'],
            'web_server' => ['nullable', 'string', 'max:255'],
            'database_type' => ['nullable', 'string', 'max:255'],
            'access_log_path' => ['nullable', 'string', 'max:255'],
            'error_log_path' => ['nullable', 'string', 'max:255'],
        ]);

        $this->applicationService->updateApplication($application, $validated);

        // Return a response
        return redirect()->back()
            ->with('success', 'Application updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Application $application)
    {
        $this->applicationService->deleteApplication($application);
        return back()->with('success','Application deleted successfully');
    }

    public function fetchLogs(Application $application, Request $request)
    {
        try {
            $result = $this->applicationService->fetchLogs($application, $request->log_path);
            return response()->json($result);
        } catch (\Exception $e) {
            $statusCode = 500;

            if (strpos($e->getMessage(), 'SSH login failed') !== false) {
                $statusCode = 401;
            }

            return response()->json([
                'output' => 'Error: ' . $e->getMessage()
            ], $statusCode);
        }
    }
}
