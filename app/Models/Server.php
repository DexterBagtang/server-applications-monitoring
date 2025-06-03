<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'last_ping_at' => 'datetime',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function metrics()
    {
        return $this->hasMany(ServerMetric::class);
    }

    public function checks()
    {
        return $this->hasMany(ServerCheck::class);
    }

    public function agentConnection()
    {
        return $this->hasOne(AgentConnection::class);
    }

    public function latestMetric()
    {
        return $this->hasOne(ServerMetric::class)->latestOfMany();
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
