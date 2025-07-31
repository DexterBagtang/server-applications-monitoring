<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'progress_key',
        'server_id',
        'local_path',
        'remote_path',
        'original_filename',
        'uploaded_mb',
        'total_size_mb',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'uploaded_mb' => 'decimal:2',
        'total_size_mb' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the server that owns the upload progress
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Update upload progress
     */
    public function updateProgress(float $uploadedMb, string $status = null, string $errorMessage = null): void
    {
        $data = ['uploaded_mb' => round($uploadedMb, 2)];

        if ($status) {
            $data['status'] = $status;

            if ($status === 'uploading' && $this->started_at === null) {
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
            $data['uploaded_mb'] = round($finalSizeMb, 2);
            $data['total_size_mb'] = round($finalSizeMb, 2);
        }

        $this->update($data);
    }

    /**
     * Calculate upload percentage
     */
    public function getProgressPercentageAttribute(): ?float
    {
        if (!$this->total_size_mb || $this->total_size_mb <= 0) {
            return null;
        }

        return round(($this->uploaded_mb / $this->total_size_mb) * 100, 2);
    }

    /**
     * Check if upload is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'uploading']);
    }

    /**
     * Check if upload is complete
     */
    public function isComplete(): bool
    {
        return $this->status === 'complete';
    }

    /**
     * Check if upload failed
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
        if (!$this->isInProgress() || !$this->started_at || !$this->total_size_mb || $this->uploaded_mb <= 0) {
            return null;
        }

        $elapsedSeconds = now()->diffInSeconds($this->started_at);
        $uploadRate = $this->uploaded_mb / $elapsedSeconds; // MB per second
        $remainingMb = $this->total_size_mb - $this->uploaded_mb;

        if ($uploadRate <= 0) {
            return null;
        }

        return (int) ceil($remainingMb / $uploadRate);
    }

    /**
     * Get upload speed in MB/s
     */
    public function getUploadSpeedAttribute(): ?float
    {
        if (!$this->isInProgress() || !$this->started_at || $this->uploaded_mb <= 0) {
            return null;
        }

        $elapsedSeconds = now()->diffInSeconds($this->started_at);

        if ($elapsedSeconds <= 0) {
            return null;
        }

        return round($this->uploaded_mb / $elapsedSeconds, 2);
    }

    /**
     * Scope for active uploads
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', ['pending', 'uploading']);
    }

    /**
     * Scope for completed uploads
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'complete');
    }

    /**
     * Scope for failed uploads
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for recent uploads (within last 24 hours)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subDay());
    }
}
