<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'metric',
        'condition',
        'threshold',
        'severity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function alertable()
    {
        return $this->morphTo();
    }

    public function notifications()
    {
        return $this->hasMany(AlertNotification::class);
    }
}
