<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FbPage extends Model
{
    protected $table = 'fb_pages';

    protected $fillable = [
        'page_id',
        'page_name',
        'page_access_token',
        'token_reference',
        'facebook_portfolio_id',
    ];

    protected $casts = [
        'page_access_token' => 'encrypted',
    ];

    protected $hidden = [
        'page_access_token',
    ];

    public function forms(): HasMany
    {
        return $this->hasMany(FbForm::class, 'fb_page_id');
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(FacebookPortfolio::class, 'facebook_portfolio_id');
    }
}
