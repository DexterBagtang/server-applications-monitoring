<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function metrics()
    {
        return $this->hasMany(ApplicationMetric::class);
    }

    public function latestMetric()
    {
        return $this->hasOne(ApplicationMetric::class)->latestOfMany();
    }


}
