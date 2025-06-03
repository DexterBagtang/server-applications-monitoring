<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Optionally, manually add specific services if needed
        Service::create([
            'server_id' => 3,
            'name' => 'nginx',
            'description' => 'Web server for hosting the application',
            'type' => 'web server',
            'status' => 'running',
            'last_checked_at' => now(),
        ]);

        Service::create([
            'server_id' => 3,
            'name' => 'mysql',
            'description' => 'Database for storing application data',
            'type' => 'database',
            'status' => 'running',
            'last_checked_at' => now(),
        ]);

        Service::create([
            'server_id' => 3,
            'name' => 'redis',
            'description' => 'Cache service for improving performance',
            'type' => 'cache',
            'status' => 'running',
            'last_checked_at' => now(),
        ]);

        Service::create([
            'server_id' => 3,
            'name' => 'cron',
            'description' => 'Scheduled tasks runner',
            'type' => 'background worker',
            'status' => 'running',
            'last_checked_at' => now(),
        ]);
    }
}
