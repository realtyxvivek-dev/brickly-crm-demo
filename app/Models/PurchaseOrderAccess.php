<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderAccess extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'can_raise_need_purchase',
        'can_raise_reimbursement',
        'can_mark_payment_done',
        'payment_limit',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'can_raise_need_purchase' => 'boolean',
        'can_raise_reimbursement' => 'boolean',
        'can_mark_payment_done' => 'boolean',
        'payment_limit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function forUser(User $user): ?self
    {
        return self::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
    }

    public static function canRaise(User $user, string $requestType): bool
    {
        if ($user->isAdmin() || $user->isFinanceManager()) {
            return true;
        }

        $access = self::forUser($user);
        if (!$access) {
            return false;
        }

        return match ($requestType) {
            PurchaseOrder::REQUEST_TYPE_ALREADY_PURCHASED => $access->can_raise_reimbursement,
            default => $access->can_raise_need_purchase,
        };
    }

    public static function allowedRequestTypes(User $user): array
    {
        if ($user->isAdmin() || $user->isFinanceManager()) {
            return PurchaseOrder::requestTypes();
        }

        $access = self::forUser($user);
        if (!$access) {
            return [];
        }

        return collect(PurchaseOrder::requestTypes())
            ->filter(function ($label, $type) use ($access) {
                return $type === PurchaseOrder::REQUEST_TYPE_ALREADY_PURCHASED
                    ? $access->can_raise_reimbursement
                    : $access->can_raise_need_purchase;
            })
            ->all();
    }

    public static function canPay(User $user, float $amount): bool
    {
        if ($user->isAdmin() || $user->isFinanceManager()) {
            return true;
        }

        $access = self::forUser($user);
        if (!$access) {
            return false;
        }

        if (!$access->can_mark_payment_done) {
            return false;
        }

        return !$access->payment_limit || $amount <= (float) $access->payment_limit;
    }
}
