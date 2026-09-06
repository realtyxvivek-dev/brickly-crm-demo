<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadFormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_key',
        'field_label',
        'field_type',
        'field_level',
        'options',
        'is_required',
        'validation_rules',
        'dependent_field',
        'dependent_conditions',
        'display_order',
        'is_active',
        'default_value',
        'placeholder',
        'help_text',
    ];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'dependent_conditions' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get all field values for this field
     */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(LeadFormFieldValue::class, 'field_key', 'field_key');
    }

    /**
     * Scope to get active fields
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get fields for a specific level
     */
    public function scopeForLevel($query, string $level)
    {
        return $query->where('field_level', $level);
    }

    /**
     * Scope to get fields visible to a user role
     */
    public function scopeVisibleToRole($query, string $roleSlug)
    {
        // Senior Manager/Sales Head/Admin/CRM can see all fields
        if (in_array($roleSlug, ['sales_manager', 'sales_head', 'admin', 'crm'])) {
            return $query->active();
        }
        
        // Sales Executive (previously Telecaller) sees only sales_executive fields
        if ($roleSlug === 'sales_executive') {
            return $query->active()->where('field_level', 'sales_executive');
        }
        
        // Assistant Sales Manager (previously Sales Executive) sees sales_executive fields + assistant_sales_manager fields
        if ($roleSlug === 'assistant_sales_manager') {
            return $query->active()->whereIn('field_level', ['sales_executive', 'assistant_sales_manager']);
        }
        
        return $query->whereRaw('1 = 0'); // No fields for unknown roles
    }

    /**
     * Get options as array (for select fields)
     */
    public function getOptionsArray(): array
    {
        return $this->options ?? [];
    }

    /**
     * Check if field is dependent on another field
     */
    public function isDependent(): bool
    {
        return !empty($this->dependent_field);
    }
}
