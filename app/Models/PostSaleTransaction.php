<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostSaleTransaction extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'transaction_date' => 'date', 'verified_at' => 'datetime'];
    public function postSaleCase() { return $this->belongsTo(PostSaleCase::class); }
    public function demand() { return $this->belongsTo(PostSaleDemand::class); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
}
