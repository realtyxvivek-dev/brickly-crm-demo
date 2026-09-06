<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSalePlanTemplate extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function builder() { return $this->belongsTo(Builder::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function items() { return $this->hasMany(PostSalePlanTemplateItem::class, 'template_id')->orderBy('sort_order'); }
}
