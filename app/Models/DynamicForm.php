<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DynamicForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'location_path',
        'form_type',
        'settings',
        'is_active',
        'status',
        'replaces_form_id',
        'created_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($form) {
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name);
            }
            if (empty($form->status)) {
                $form->status = 'draft';
            }
            // Auto-set is_active based on status
            if (isset($form->status)) {
                $form->is_active = $form->status === 'published';
            }
        });

        static::updating(function ($form) {
            // Auto-update is_active when status changes
            if ($form->isDirty('status')) {
                $form->is_active = $form->status === 'published';
            }
        });
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DynamicFormField::class, 'form_id')->orderBy('order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DynamicFormSubmission::class, 'form_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replacedForm(): BelongsTo
    {
        return $this->belongsTo(DynamicForm::class, 'replaces_form_id');
    }

    public function replacedByForm(): HasMany
    {
        return $this->hasMany(DynamicForm::class, 'replaces_form_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDrafts($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'published')->where('is_active', true);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function getFieldsBySection(): array
    {
        return $this->fields()
            ->get()
            ->groupBy('section')
            ->toArray();
    }
}
