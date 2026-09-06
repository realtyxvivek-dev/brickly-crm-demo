<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppRoutingRule extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_routing_rules';

    protected $fillable = [
        'name',
        'priority',
        'conditions',
        'meta_waba_account_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'priority' => 'integer',
        'conditions' => 'array',
        'is_active' => 'boolean',
    ];

    public function metaWabaAccount(): BelongsTo
    {
        return $this->belongsTo(MetaWabaAccount::class, 'meta_waba_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
