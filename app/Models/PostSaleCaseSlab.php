<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSaleCaseSlab extends Model
{
    protected $guarded = [];
    protected $casts = ['customer_collection_percent' => 'decimal:3', 'brokerage_release_percent' => 'decimal:3'];
    public function postSaleCase() { return $this->belongsTo(PostSaleCase::class); }
}
