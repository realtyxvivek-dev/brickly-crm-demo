<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class CompanyFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'dimensions',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size' => 'integer',
    ];

    /**
     * Get the user who uploaded this file.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get public URL for file.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Get full storage path.
     */
    public function getFullPathAttribute(): string
    {
        return Storage::disk('public')->path($this->file_path);
    }

    /**
     * Scope: Only active files.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by file type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('file_type', $type);
    }

    /**
     * Get active file by type.
     */
    public static function getActiveFile(string $type): ?self
    {
        return self::where('file_type', $type)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Upload file and create record.
     */
    public static function uploadFile($file, string $type, int $userId): self
    {
        // Deactivate old file of same type
        self::where('file_type', $type)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Generate file path
        $directory = match($type) {
            'logo' => 'company/logos',
            'favicon' => 'company/icons',
            'email_header', 'email_footer' => 'company/email',
            default => 'company/files',
        };

        $filename = time() . '_' . $type . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs($directory, $filename, 'public');

        // Get image dimensions if it's an image
        $dimensions = null;
        if (str_starts_with($file->getMimeType(), 'image/')) {
            $imageInfo = getimagesize($file->getRealPath());
            if ($imageInfo) {
                $dimensions = $imageInfo[0] . 'x' . $imageInfo[1];
            }
        }

        // Create record
        return self::create([
            'file_type' => $type,
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'dimensions' => $dimensions,
            'is_active' => true,
            'uploaded_by' => $userId,
        ]);
    }
}
