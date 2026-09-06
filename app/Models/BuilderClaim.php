<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuilderClaim extends Model
{
    protected $guarded = [];
    protected $casts = [
        'collection_percent' => 'decimal:3', 'release_percent' => 'decimal:3',
        'eligible_amount' => 'decimal:2', 'claim_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2', 'tds_amount' => 'decimal:2',
        'claim_date' => 'date', 'expected_date' => 'date',
    ];
    public function postSaleCase() { return $this->belongsTo(PostSaleCase::class); }
    public function receipts() { return $this->hasMany(BuilderReceipt::class, 'builder_claim_id'); }
    public function invoiceItems() { return $this->hasMany(BuilderInvoiceItem::class); }
}
