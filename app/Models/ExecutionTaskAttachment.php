<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionTaskAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'attachment_kind',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'link_url',
        'link_title',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ExecutionTask::class, 'task_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = max($this->file_size, 0);
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = min((int) floor(($bytes ? log($bytes) : 0) / log(1024)), count($units) - 1);
        $value = $bytes / pow(1024, $pow ?: 0);

        return round($value, 2) . ' ' . $units[$pow];
    }

    public function isLink(): bool
    {
        return $this->attachment_kind === 'link';
    }

    public function getDisplayNameAttribute(): string
    {
        if (!$this->isLink()) {
            return $this->file_name;
        }

        if ($this->link_title) {
            return $this->link_title;
        }

        $host = parse_url((string) $this->link_url, PHP_URL_HOST);

        return $host ?: ($this->file_name ?: 'Open link');
    }
}
