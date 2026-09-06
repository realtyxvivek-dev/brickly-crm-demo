<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpensePaymentMethod extends Model
{
    use HasFactory;

    public const TYPE_CASH = 'cash';
    public const TYPE_BANK = 'bank';
    public const TYPE_UPI = 'upi';
    public const TYPE_CREDIT_CARD = 'credit_card';

    protected $fillable = [
        'type',
        'name',
        'details',
        'is_active',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function types(): array
    {
        return [
            self::TYPE_CASH => 'Cash',
            self::TYPE_BANK => 'Bank',
            self::TYPE_UPI => 'UPI',
            self::TYPE_CREDIT_CARD => 'Credit Card',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
