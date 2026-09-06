<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuilderInvoice extends Model
{
    protected $guarded = [];
    protected $casts = [
        'invoice_date' => 'date', 'due_date' => 'date', 'issued_at' => 'datetime',
        'subtotal' => 'decimal:2', 'gst_amount' => 'decimal:2',
        'tds_amount' => 'decimal:2', 'net_receivable' => 'decimal:2',
    ];
    public function builder() { return $this->belongsTo(Builder::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function items() { return $this->hasMany(BuilderInvoiceItem::class)->orderBy('sort_order'); }
    public function revisions() { return $this->hasMany(BuilderInvoiceRevision::class)->latest('revision_no'); }
}
