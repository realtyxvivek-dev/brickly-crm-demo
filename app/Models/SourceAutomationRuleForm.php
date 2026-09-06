<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceAutomationRuleForm extends Model
{
    protected $fillable = [
        'rule_id',
        'fb_form_id',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(SourceAutomationRule::class, 'rule_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FbForm::class, 'fb_form_id');
    }
}
