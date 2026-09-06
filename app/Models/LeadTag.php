<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class LeadTag extends Model
{
    use HasFactory;

    public const TYPES = ['campaign', 'vendor', 'city', 'product', 'quality', 'month', 'custom'];

    protected $fillable = [
        'name',
        'slug',
        'type',
        'color',
        'is_folder',
        'created_by',
    ];

    protected $casts = [
        'is_folder' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (LeadTag $tag) {
            if (!$tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'lead_tag_assignments')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }
}
