<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadSource extends Model
{
    use HasFactory;

    public const TYPE_OPTIONS = [
        'social' => 'Social',
        'digital' => 'Digital',
        'portal' => 'Portal',
        'call' => 'Call / IVR',
        'import' => 'Import',
        'referral' => 'Referral',
        'offline' => 'Offline',
        'other' => 'Other',
    ];

    protected $fillable = [
        'name',
        'key',
        'type',
        'is_active',
        'is_system',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function activeOptions(): array
    {
        if (!Schema::hasTable('lead_sources')) {
            return [];
        }

        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'key')
            ->all();
    }

    public static function activeKeyExists(string $key): bool
    {
        if (!Schema::hasTable('lead_sources')) {
            return false;
        }

        return static::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->exists();
    }

    public static function normalizeKey(string $name): string
    {
        $key = Str::slug(Str::lower(trim($name)), '_');

        return trim((string) $key, '_');
    }
}
