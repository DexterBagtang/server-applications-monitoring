<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServerCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'type',
        'name',
        'command',
        'expected_output',
        'is_active',
        'timeout',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function results()
    {
        return $this->hasMany(ServerCheckResult::class);
    }

    public function latestResult()
    {
        return $this->hasOne(ServerCheckResult::class)->latestOfMany();
    }
}
