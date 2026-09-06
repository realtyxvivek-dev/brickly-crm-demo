<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuilderInvoiceItem extends Model
{
    protected $guarded = [];
    protected $casts = ['quantity' => 'decimal:3', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];
    public function invoice() { return $this->belongsTo(BuilderInvoice::class, 'builder_invoice_id'); }
    public function claim() { return $this->belongsTo(BuilderClaim::class, 'builder_claim_id'); }
    public function postSaleCase() { return $this->belongsTo(PostSaleCase::class); }
}
