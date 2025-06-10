<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'progress_key',
        'server_id',
        'remote_path',
        'local_filename',
        'downloaded_mb',
        'total_size_mb',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'downloaded_mb' => 'decimal:2',
        'total_size_mb' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the server that owns the download progress
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Update download progress
     */
    public function updateProgress(float $downloadedMb, string $status = null, string $errorMessage = null): void
    {
        $data = ['downloaded_mb' => round($downloadedMb, 2)];

        if ($status) {
            $data['status'] = $status;

            if ($status === 'downloading' && $this->started_at === null) {
                $data['started_at'] = now();
            } elseif (in_array($status, ['complete', 'failed'])) {
                $data['completed_at'] = now();
            }
        }

        if ($errorMessage) {
            $data['error_message'] = $errorMessage;
        }

        $this->update($data);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as complete
     */
    public function markAsComplete(float $finalSizeMb = null): void
    {
        $data = [
            'status' => 'complete',
            'completed_at' => now(),
        ];

        if ($finalSizeMb !== null) {
            $data['downloaded_mb'] = round($finalSizeMb, 2);
            $data['total_size_mb'] = round($finalSizeMb, 2);
        }

        $this->update($data);
    }

    /**
     * Calculate download percentage
     */
    public function getProgressPercentageAttribute(): ?float
    {
        if (!$this->total_size_mb || $this->total_size_mb <= 0) {
            return null;
        }

        return round(($this->downloaded_mb / $this->total_size_mb) * 100, 2);
    }

    /**
     * Check if download is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'downloading']);
    }

    /**
     * Check if download is complete
     */
    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    /**
     * Check if download failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get estimated time remaining (in seconds)
     */
    public function getEstimatedTimeRemainingAttribute(): ?int
    {
        if (!$this->isInProgress() || !$this->started_at || !$this->total_size_mb || $this->downloaded_mb <= 0) {
            return null;
        }

        $elapsedSeconds = now()->diffInSeconds($this->started_at);
        $downloadRate = $this->downloaded_mb / $elapsedSeconds; // MB per second
        $remainingMb = $this->total_size_mb - $this->downloaded_mb;

        if ($downloadRate <= 0) {
            return null;
        }

        return (int) ceil($remainingMb / $downloadRate);
    }

    /**
     * Scope for active downloads
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['pending', 'downloading']);
    }

    /**
     * Scope for completed downloads
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'complete');
    }

    /**
     * Scope for failed downloads
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
