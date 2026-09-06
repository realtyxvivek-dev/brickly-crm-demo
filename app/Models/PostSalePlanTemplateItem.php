<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSalePlanTemplateItem extends Model
{
    protected $guarded = [];
    protected $casts = ['fixed_date' => 'date', 'percentage' => 'decimal:3', 'fixed_amount' => 'decimal:2'];
    public function template() { return $this->belongsTo(PostSalePlanTemplate::class, 'template_id'); }
}
