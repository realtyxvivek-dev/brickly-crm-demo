<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSaleDocument extends Model
{
    protected $guarded = [];
    protected $casts = ['uploaded_at' => 'datetime', 'sent_at' => 'datetime', 'signed_at' => 'datetime'];
    public function postSaleCase() { return $this->belongsTo(PostSaleCase::class); }
}
