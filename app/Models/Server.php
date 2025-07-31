<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function downloadProgresses(): HasMany
    {
        return $this->hasMany(DownloadProgress::class);
    }

    /**
     * Get active downloads for this server
     */
    public function activeDownloads(): HasMany
    {
        return $this->hasMany(DownloadProgress::class)->inProgress();
    }

    /**
     * Get completed downloads for this server
     */
    public function completedDownloads(): HasMany
    {
        return $this->hasMany(DownloadProgress::class)->completed();
    }

    /**
     * Get failed downloads for this server
     */
    public function failedDownloads(): HasMany
    {
        return $this->hasMany(DownloadProgress::class)->failed();
    }

    public function uploadProgress()
    {
        return $this->hasMany(UploadProgress::class);
    }

// If you don't already have the download relationship, add this too:
    /**
     * Get all download progress records for this server
     */
}
