<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostSaleCase extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'booking_date' => 'date',
        'agreement_value' => 'decimal:2',
        'revenue_value' => 'decimal:2',
        'kyc_complete' => 'boolean',
        'needs_mapping' => 'boolean',
        'reminders_enabled' => 'boolean',
        'activated_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function siteVisit() { return $this->belongsTo(SiteVisit::class); }
    public function lead() { return $this->belongsTo(Lead::class); }
    public function project() { return $this->belongsTo(Project::class); }
    public function builder() { return $this->belongsTo(Builder::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function demands() { return $this->hasMany(PostSaleDemand::class)->orderBy('sort_order'); }
    public function transactions() { return $this->hasMany(PostSaleTransaction::class); }
    public function documents() { return $this->hasMany(PostSaleDocument::class); }
    public function slabs() { return $this->hasMany(PostSaleCaseSlab::class)->orderBy('sort_order'); }
    public function claims() { return $this->hasMany(BuilderClaim::class); }
    public function activities() { return $this->hasMany(PostSaleActivity::class)->latest(); }
}
