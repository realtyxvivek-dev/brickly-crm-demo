<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuilderInvoiceRevision extends Model
{
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array'];
    public function invoice() { return $this->belongsTo(BuilderInvoice::class, 'builder_invoice_id'); }
}
