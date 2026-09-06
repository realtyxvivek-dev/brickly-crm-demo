<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicFormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'field_key',
        'field_type',
        'label',
        'placeholder',
        'help_text',
        'options',
        'validation',
        'required',
        'order',
        'section',
        'styles',
        'default_value',
        'is_system',
        'system_binding',
        'is_visible',
    ];

    protected $casts = [
        'options' => 'array',
        'validation' => 'array',
        'styles' => 'array',
        'required' => 'boolean',
        'order' => 'integer',
        'is_system' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(DynamicForm::class, 'form_id');
    }

    public function getValidationRules(): array
    {
        $rules = [];
        
        if ($this->required) {
            $rules[] = 'required';
        }
        
        if ($this->field_type === 'email') {
            $rules[] = 'email';
        }
        
        if ($this->field_type === 'number') {
            $rules[] = 'numeric';
        }
        
        if ($this->validation) {
            foreach ($this->validation as $key => $value) {
                if ($key === 'min') {
                    $rules[] = "min:{$value}";
                } elseif ($key === 'max') {
                    $rules[] = "max:{$value}";
                } elseif ($key === 'pattern') {
                    $rules[] = "regex:{$value}";
                }
            }
        }
        
        return $rules;
    }
}
