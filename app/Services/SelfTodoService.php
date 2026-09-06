<?php

namespace App\Services;

use App\Models\SelfTodo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SelfTodoService
{
    public const FILTERS = ['today', 'pending', 'done', 'high', 'all'];

    public function canUse(User $user): bool
    {
        return $user->canUseSelfTodoPilot() && Schema::hasTable('self_todos');
    }

    public function normalizeFilter(?string $filter, string $default = 'today'): string
    {
        $filter = $filter ?: $default;

        return in_array($filter, self::FILTERS, true) ? $filter : $default;
    }

    public function countsForUser(User $user): array
    {
        if (!$this->canUse($user)) {
            return $this->emptyCounts();
        }

        $base = SelfTodo::query()->where('user_id', $user->id);

        return [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->where('status', SelfTodo::STATUS_OPEN)->count(),
            'completed' => (clone $base)->where('status', SelfTodo::STATUS_COMPLETED)->count(),
            'today' => (clone $base)->whereDate('due_at', now()->toDateString())->count(),
            'overdue' => (clone $base)
                ->where('status', SelfTodo::STATUS_OPEN)
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
        ];
    }

    public function listForUser(User $user, string $filter = 'today'): Collection
    {
        if (!$this->canUse($user)) {
            return collect();
        }

        $filter = $this->normalizeFilter($filter);
        $query = SelfTodo::query()->where('user_id', $user->id);

        if ($filter === 'today') {
            $query->where(function ($query): void {
                $query->whereDate('due_at', now()->toDateString())
                    ->orWhere(function ($open): void {
                        $open->where('status', SelfTodo::STATUS_OPEN)
                            ->whereNull('due_at');
                    });
            });
        } elseif ($filter === 'pending') {
            $query->where('status', SelfTodo::STATUS_OPEN);
        } elseif ($filter === 'done') {
            $query->where('status', SelfTodo::STATUS_COMPLETED);
        } elseif ($filter === 'high') {
            $query->where('priority', 'high')
                ->where('status', SelfTodo::STATUS_OPEN);
        }

        return $query
            ->orderByRaw("case when status = 'completed' then 1 else 0 end")
            ->orderByRaw("case when due_at is null then 1 else 0 end")
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function emptyCounts(): array
    {
        return [
            'total' => 0,
            'open' => 0,
            'completed' => 0,
            'today' => 0,
            'overdue' => 0,
        ];
    }
}
