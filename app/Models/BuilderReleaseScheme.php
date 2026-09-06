<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuilderReleaseScheme extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function builder() { return $this->belongsTo(Builder::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function slabs() { return $this->hasMany(BuilderReleaseSlab::class, 'scheme_id')->orderBy('sort_order'); }
}
