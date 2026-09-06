<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanPartnerBank extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'logo',
        'short_offer_text',
        'interest_rate_text',
        'status',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        return asset('storage/loan-partners/logos/' . $this->logo);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
