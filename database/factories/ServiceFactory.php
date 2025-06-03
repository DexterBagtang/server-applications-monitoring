<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'server_id' => 3, // Server ID is fixed to 3 for this seeder
            'name' => $this->faker->randomElement([
                'nginx', 'mysql', 'redis', 'cron', 'php-fpm', 'node', 'postgresql', 'memcached'
            ]),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement([
                'web server', 'database', 'cache', 'background worker', 'application server'
            ]),
            'status' => $this->faker->randomElement(['running', 'stopped', 'unknown']),
            'last_checked_at' => now(),
        ];
    }
}
