<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuilderReceipt extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'received_date' => 'date'];
    public function claim() { return $this->belongsTo(BuilderClaim::class, 'builder_claim_id'); }
}
