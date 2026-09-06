<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSaleActivity extends Model
{
    protected $guarded = [];
    protected $casts = ['before_snapshot' => 'array', 'after_snapshot' => 'array'];
    public function postSaleCase() { return $this->belongsTo(PostSaleCase::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
