<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderApprovalLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class PurchaseOrderService
{
    public function storeItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $purchaseOrder->items()->delete();

        $total = 0;
        foreach ($items as $item) {
            $quantity = round((float) ($item['quantity'] ?? 0), 2);
            $rate = round((float) ($item['rate'] ?? 0), 2);
            $taxAmount = round((float) ($item['tax_amount'] ?? 0), 2);
            $lineTotal = round(($quantity * $rate) + $taxAmount, 2);
            $total += $lineTotal;

            $purchaseOrder->items()->create([
                'item_name' => $item['item_name'],
                'category' => $item['category'] ?? ($purchaseOrder->category?->code ?? 'other'),
                'expense_category_id' => $item['expense_category_id'] ?? $purchaseOrder->expense_category_id,
                'expense_subcategory_id' => $item['expense_subcategory_id'] ?? $purchaseOrder->expense_subcategory_id,
                'quantity' => $quantity,
                'rate' => $rate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ]);
        }

        $purchaseOrder->update(['total_amount' => round($total, 2)]);
    }

    public function submit(PurchaseOrder $purchaseOrder, User $actor, string $action = 'submitted'): void
    {
        DB::transaction(function () use ($purchaseOrder, $actor, $action) {
            if (!$purchaseOrder->request_number) {
                $purchaseOrder->request_number = $this->nextNumber('POR');
            }

            $purchaseOrder->status = PurchaseOrder::STATUS_SUBMITTED;
            $purchaseOrder->admin_remark = null;
            $purchaseOrder->save();

            $this->log($purchaseOrder, $action, $actor);
        });
    }

    public function approve(PurchaseOrder $purchaseOrder, User $actor): void
    {
        $recipientIds = DB::transaction(function () use ($purchaseOrder, $actor) {
            $submittedBy = $purchaseOrder->logs()
                ->whereIn('action', ['submitted', 'resubmitted'])
                ->latest('action_at')
                ->latest('id')
                ->value('action_by');

            if (!$purchaseOrder->po_number) {
                $purchaseOrder->po_number = $this->nextNumber('PO');
            }

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_PAYMENT_PENDING,
                'admin_reviewed_by' => $actor->id,
                'admin_reviewed_at' => now(),
                'admin_remark' => null,
            ]);

            $this->log($purchaseOrder, 'approved', $actor);

            return collect([$purchaseOrder->created_by, $submittedBy])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        });

        try {
            $approvedOrder = $purchaseOrder->fresh();
            User::query()->with('role')->whereKey($recipientIds)->get()->each(function (User $recipient) use ($approvedOrder) {
                $actionUrl = $recipient->isFinanceManager()
                    ? url('/finance-manager/purchase-orders/' . $approvedOrder->id)
                    : url('/purchase-orders/' . $approvedOrder->id);

                app(NotificationService::class)->notifyPurchaseOrderApproved($recipient, $approvedOrder, $actionUrl);
            });
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function reject(PurchaseOrder $purchaseOrder, User $actor, string $remark): void
    {
        DB::transaction(function () use ($purchaseOrder, $actor, $remark) {
            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_REJECTED,
                'admin_reviewed_by' => $actor->id,
                'admin_reviewed_at' => now(),
                'admin_remark' => $remark,
            ]);

            $this->log($purchaseOrder, 'rejected', $actor, $remark);
        });
    }

    public function requestDelete(PurchaseOrder $purchaseOrder, User $actor, string $reason): void
    {
        DB::transaction(function () use ($purchaseOrder, $actor, $reason) {
            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_DELETE_REQUESTED,
                'delete_requested_by' => $actor->id,
                'delete_requested_at' => now(),
                'delete_request_reason' => $reason,
                'delete_restore_status' => $purchaseOrder->status,
                'delete_reviewed_by' => null,
                'delete_reviewed_at' => null,
                'delete_reject_reason' => null,
            ]);

            $this->log($purchaseOrder->fresh(), 'delete_requested', $actor, $reason);
        });
    }

    public function approveDelete(PurchaseOrder $purchaseOrder, User $actor): void
    {
        DB::transaction(function () use ($purchaseOrder, $actor) {
            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_DELETED,
                'delete_reviewed_by' => $actor->id,
                'delete_reviewed_at' => now(),
                'delete_reject_reason' => null,
            ]);

            $this->log($purchaseOrder->fresh(), 'delete_approved', $actor, $purchaseOrder->delete_request_reason);
        });
    }

    public function deleteByFinanceManager(PurchaseOrder $purchaseOrder, User $actor, string $reason): void
    {
        DB::transaction(function () use ($purchaseOrder, $actor, $reason) {
            $previousStatus = $purchaseOrder->status;

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_DELETED,
                'delete_requested_by' => $actor->id,
                'delete_requested_at' => now(),
                'delete_request_reason' => $reason,
                'delete_restore_status' => $previousStatus,
                'delete_reviewed_by' => $actor->id,
                'delete_reviewed_at' => now(),
                'delete_reject_reason' => null,
            ]);

            $this->log($purchaseOrder->fresh(), 'finance_deleted', $actor, $reason);
        });
    }

    public function rejectDelete(PurchaseOrder $purchaseOrder, User $actor, string $reason): void
    {
        DB::transaction(function () use ($purchaseOrder, $actor, $reason) {
            $purchaseOrder->update([
                'status' => $purchaseOrder->delete_restore_status ?: PurchaseOrder::STATUS_DRAFT,
                'delete_reviewed_by' => $actor->id,
                'delete_reviewed_at' => now(),
                'delete_reject_reason' => $reason,
            ]);

            $this->log($purchaseOrder->fresh(), 'delete_rejected', $actor, $reason);
        });
    }

    public function log(PurchaseOrder $purchaseOrder, string $action, ?User $actor, ?string $remark = null): void
    {
        PurchaseOrderApprovalLog::create([
            'purchase_order_id' => $purchaseOrder->id,
            'action' => $action,
            'action_by' => $actor?->id,
            'remark' => $remark,
            'action_at' => now(),
        ]);
    }

    private function nextNumber(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {
            $year = now()->year;
            $column = $prefix === 'PO' ? 'po_number' : 'request_number';
            $existingNumbers = PurchaseOrder::query()
                ->where($column, 'like', $prefix . '-' . $year . '-%')
                ->lockForUpdate()
                ->pluck($column);

            $max = 0;
            foreach ($existingNumbers as $number) {
                if (is_string($number) && preg_match('/^' . preg_quote($prefix, '/') . '-' . $year . '-(\d+)$/', $number, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }

            do {
                $max++;
                $nextNumber = sprintf('%s-%d-%04d', $prefix, $year, $max);
            } while (PurchaseOrder::where($column, $nextNumber)->exists());

            return $nextNumber;
        });
    }
}
