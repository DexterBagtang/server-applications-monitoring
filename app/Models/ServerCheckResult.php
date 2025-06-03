<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServerCheckResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_check_id',
        'success',
        'output',
        'execution_time',
        'executed_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'executed_at' => 'datetime',
    ];

    public function check()
    {
        return $this->belongsTo(ServerCheck::class, 'server_check_id');
    }
}
