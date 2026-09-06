<?php

namespace App\Services;

use App\Models\ExpenseEntry;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ExpenseDashboardSummaryService
{
    private const HIGH_VALUE_PENDING_THRESHOLD = 25000;

    public function getMonthSnapshot(int $year, int $month, int $recentLimit = 8): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        return $this->getRangeSnapshot($startDate, $endDate, $recentLimit);
    }

    public function getRangeSnapshot(
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        int $recentLimit = 8
    ): array {
        $rangeStart = Carbon::parse($startDate->toDateTimeString())->startOfDay();
        $rangeEnd = Carbon::parse($endDate->toDateTimeString())->endOfDay();

        $baseQuery = $this->visibleExpenseQuery()
            ->whereDate('expense_date', '>=', $rangeStart->toDateString())
            ->whereDate('expense_date', '<=', $rangeEnd->toDateString());

        $summary = $this->buildSummary(clone $baseQuery);
        $currentMonthStart = $rangeStart->copy()->startOfMonth();
        $currentMonthEnd = $currentMonthStart->copy()->endOfMonth();
        $currentMonthQuery = $this->visibleExpenseQuery()
            ->whereDate('expense_date', '>=', $currentMonthStart->toDateString())
            ->whereDate('expense_date', '<=', $currentMonthEnd->toDateString());
        $previousMonthStart = $currentMonthStart->copy()->subMonth()->startOfMonth();
        $previousMonthEnd = $previousMonthStart->copy()->endOfMonth();
        $previousMonthQuery = $this->visibleExpenseQuery()
            ->whereDate('expense_date', '>=', $previousMonthStart->toDateString())
            ->whereDate('expense_date', '<=', $previousMonthEnd->toDateString());

        return [
            'year' => $rangeStart->year,
            'month' => $rangeStart->month,
            'start_date' => $rangeStart->toDateString(),
            'end_date' => $rangeEnd->toDateString(),
            'summary' => $summary,
            'status_totals' => $this->buildStatusTotals(clone $baseQuery),
            'latest_entries' => (clone $baseQuery)
                ->with(['company', 'category', 'subcategory', 'creator'])
                ->latest('expense_date')
                ->latest('id')
                ->limit($recentLimit)
                ->get(),
            'subcategory_totals' => (clone $baseQuery)
                ->selectRaw('expense_subcategory_id, SUM(amount) as total_amount')
                ->with('subcategory:id,name')
                ->groupBy('expense_subcategory_id')
                ->orderByDesc('total_amount')
                ->limit(6)
                ->get(),
            'category_totals' => (clone $baseQuery)
                ->selectRaw('expense_category_id, SUM(amount) as total_amount, COUNT(*) as entry_count')
                ->with('category:id,name')
                ->groupBy('expense_category_id')
                ->orderByDesc('total_amount')
                ->limit(6)
                ->get(),
            'pending_count' => $summary['pending_count'],
            'approved_amount' => $summary['approved_amount'],
            'rejected_amount' => $summary['rejected_amount'],
            'pending_amount' => $summary['pending_amount'],
            'high_value_pending' => $this->buildHighValuePending(clone $baseQuery),
            'oldest_pending' => $this->buildOldestPending(clone $baseQuery),
            'month_comparison' => $this->buildMonthComparison((clone $currentMonthQuery)->sum('amount'), clone $previousMonthQuery, $currentMonthStart, $previousMonthStart),
        ];
    }

    private function buildSummary(Builder $query): array
    {
        $totalAmount = (clone $query)->sum('amount');

        $companyTotals = (clone $query)
            ->selectRaw('company_id, SUM(amount) as total_amount')
            ->with('company:id,name')
            ->groupBy('company_id')
            ->orderByDesc('total_amount')
            ->get();

        $categoryTotals = (clone $query)
            ->selectRaw('expense_category_id, SUM(amount) as total_amount')
            ->with('category:id,name')
            ->groupBy('expense_category_id')
            ->orderByDesc('total_amount')
            ->get();

        return [
            'total_amount' => (float) $totalAmount,
            'entry_count' => (clone $query)->count(),
            'company_totals' => $companyTotals,
            'category_totals' => $categoryTotals,
            'average_amount' => (float) ((clone $query)->avg('amount') ?? 0),
            'pending_count' => (clone $query)->where('status', ExpenseEntry::STATUS_DRAFT)->count(),
            'pending_amount' => (float) (clone $query)->where('status', ExpenseEntry::STATUS_DRAFT)->sum('amount'),
            'approved_amount' => (float) (clone $query)->where('status', ExpenseEntry::STATUS_APPROVED)->sum('amount'),
            'rejected_amount' => (float) (clone $query)->where('status', ExpenseEntry::STATUS_REJECTED)->sum('amount'),
            'rejected_count' => (clone $query)->where('status', ExpenseEntry::STATUS_REJECTED)->count(),
        ];
    }

    private function buildStatusTotals(Builder $query): array
    {
        $grouped = (clone $query)
            ->selectRaw('status, SUM(amount) as total_amount, COUNT(*) as entry_count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect([
            ExpenseEntry::STATUS_DRAFT => 'Draft',
            ExpenseEntry::STATUS_APPROVED => 'Approved',
            ExpenseEntry::STATUS_REJECTED => 'Rejected',
        ])->mapWithKeys(function ($label, $status) use ($grouped) {
            $row = $grouped->get($status);

            return [$status => [
                'label' => $label,
                'entry_count' => (int) ($row->entry_count ?? 0),
                'total_amount' => (float) ($row->total_amount ?? 0),
            ]];
        })->all();
    }

    private function visibleExpenseQuery(): Builder
    {
        return ExpenseEntry::query()
            ->when(Schema::hasColumn('expense_entries', 'deleted_at'), fn (Builder $query) => $query->whereNull('deleted_at'));
    }

    private function buildHighValuePending(Builder $query): array
    {
        $pending = (clone $query)
            ->where('status', ExpenseEntry::STATUS_DRAFT)
            ->where('amount', '>=', self::HIGH_VALUE_PENDING_THRESHOLD);

        return [
            'threshold' => self::HIGH_VALUE_PENDING_THRESHOLD,
            'count' => (clone $pending)->count(),
            'amount' => (float) (clone $pending)->sum('amount'),
        ];
    }

    private function buildOldestPending(Builder $query): ?array
    {
        $entry = (clone $query)
            ->with(['category:id,name', 'subcategory:id,name', 'creator:id,name'])
            ->where('status', ExpenseEntry::STATUS_DRAFT)
            ->orderBy('expense_date')
            ->orderBy('id')
            ->first();

        if (!$entry) {
            return null;
        }

        return [
            'id' => $entry->id,
            'amount' => (float) $entry->amount,
            'expense_date' => optional($entry->expense_date)->toDateString(),
            'category_name' => $entry->category?->name,
            'subcategory_name' => $entry->subcategory?->name,
            'creator_name' => $entry->creator?->name,
        ];
    }

    private function buildMonthComparison(float $currentAmount, Builder $previousMonthQuery, Carbon $currentMonthStart, Carbon $previousMonthStart): array
    {
        $previousAmount = (float) $previousMonthQuery->sum('amount');
        $difference = $currentAmount - $previousAmount;

        return [
            'current_month_label' => $currentMonthStart->format('M Y'),
            'previous_month_label' => $previousMonthStart->format('M Y'),
            'current_amount' => $currentAmount,
            'previous_amount' => $previousAmount,
            'difference_amount' => $difference,
            'percentage_change' => $previousAmount > 0 ? round(($difference / $previousAmount) * 100, 1) : null,
            'direction' => $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'flat'),
        ];
    }
}
