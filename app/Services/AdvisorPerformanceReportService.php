<?php

namespace App\Services;

use App\Models\Incentive;
use App\Models\LeadAssignment;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\AttendanceMonthlyRollup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdvisorPerformanceReportService
{
    public const ADVISOR_ROLES = [
        Role::SALES_MANAGER,
        Role::SENIOR_MANAGER,
        Role::ASSISTANT_SALES_MANAGER,
        Role::SALES_EXECUTIVE,
    ];

    public function resolvePeriod(array $input = []): array
    {
        $now = now();
        $requestedPeriodType = $input['period_type'] ?? 'month';
        $periodType = in_array($requestedPeriodType, ['month', 'quarter', 'year', 'till_date', 'custom'], true)
            ? $requestedPeriodType
            : 'month';
        $year = max(2000, min(2100, (int) ($input['year'] ?? $now->year)));
        $month = max(1, min(12, (int) ($input['month'] ?? $now->month)));
        $quarter = max(1, min(4, (int) ($input['quarter'] ?? 1)));
        $requestedTillBasis = $input['till_basis'] ?? 'financial_year';
        $tillBasis = in_array($requestedTillBasis, ['system_start', 'financial_year', 'calendar_year'], true)
            ? $requestedTillBasis
            : 'financial_year';

        if ($periodType === 'quarter') {
            $ranges = [
                1 => [$year, 4, $year, 6],
                2 => [$year, 7, $year, 9],
                3 => [$year, 10, $year, 12],
                4 => [$year + 1, 1, $year + 1, 3],
            ];
            [$startYear, $startMonth, $endYear, $endMonth] = $ranges[$quarter];
            $start = Carbon::create($startYear, $startMonth, 1)->startOfDay();
            $end = Carbon::create($endYear, $endMonth, 1)->endOfMonth();
            $label = 'FY ' . $year . '-' . substr((string) ($year + 1), -2) . ' Q' . $quarter . ' (' . $start->format('d M Y') . ' - ' . $end->format('d M Y') . ')';
        } elseif ($periodType === 'year') {
            $start = Carbon::create($year, 1, 1)->startOfYear();
            $end = Carbon::create($year, 12, 31)->endOfYear();
            $label = 'Calendar Year ' . $year;
        } elseif ($periodType === 'till_date') {
            $end = $now->copy()->endOfDay();
            if ($tillBasis === 'system_start') {
                $start = $this->systemStartDate();
                $basisLabel = 'System Start';
            } elseif ($tillBasis === 'calendar_year') {
                $start = $now->copy()->startOfYear();
                $basisLabel = 'Calendar Year';
            } else {
                $start = $now->month >= 4
                    ? Carbon::create($now->year, 4, 1)->startOfDay()
                    : Carbon::create($now->year - 1, 4, 1)->startOfDay();
                $basisLabel = 'Financial Year';
            }
            $label = $basisLabel . ' Till Date (' . $start->format('d M Y') . ' - ' . $end->format('d M Y') . ')';
        } elseif ($periodType === 'custom') {
            $start = $this->parseDate($input['start_date'] ?? null, Carbon::create($year, $month, 1))->startOfDay();
            $end = $this->parseDate($input['end_date'] ?? null, $start->copy())->endOfDay();
            if ($end->lt($start)) {
                $end = $start->copy()->endOfDay();
            }
            $label = 'Custom (' . $start->format('d M Y') . ' - ' . $end->format('d M Y') . ')';
        } else {
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $label = $start->format('F Y');
        }

        return [
            'period_type' => $periodType,
            'year' => $year,
            'month' => $month,
            'quarter' => $quarter,
            'till_basis' => $tillBasis,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'start' => $start,
            'end' => $end,
            'label' => $label,
            'query' => [
                'period_type' => $periodType,
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
                'till_basis' => $tillBasis,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
        ];
    }

    public function build(array $period, ?int $advisorId = null): array
    {
        $start = $period['start'];
        $end = $period['end'];

        $advisorIds = $this->resolveAdvisorIds($start, $end, $advisorId);
        $advisors = User::query()
            ->with(['role', 'salaryProfile.salaryStructure.components'])
            ->whereIn('id', $advisorIds)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $leadCounts = $this->leadCounts($advisorIds, $start, $end);
        $currentUniqueLeadMetrics = $this->currentUniqueLeadMetrics($advisorIds, $start, $end);
        $currentUniqueLeadCounts = $currentUniqueLeadMetrics['by_advisor'];
        $incentives = $this->incentiveSums($advisorIds, $start, $end);
        $visits = $this->visitCounts($advisorIds, $start, $end);
        $meetings = $this->meetingCounts($advisorIds, $start, $end);
        $bookingMetrics = $this->bookingMetrics($advisorIds, $start, $end);
        $periodSalaries = $this->periodSalaryMap($advisorIds, $advisors, $start, $end);

        $totalRevenue = $bookingMetrics->sum('revenue_value');
        $totalTv = $bookingMetrics->sum('booking_value');
        $totalSqft = $bookingMetrics->sum('sqft');
        $totalSalary = 0.0;
        $totalJustification = 0.0;

        $rows = $advisors->map(function (User $advisor) use ($leadCounts, $currentUniqueLeadCounts, $incentives, $visits, $meetings, $bookingMetrics, $periodSalaries, $totalRevenue, &$totalSalary, &$totalJustification) {
            $userId = (int) $advisor->id;
            $leads = (int) ($leadCounts[$userId] ?? 0);
            $currentUniqueLeads = (int) ($currentUniqueLeadCounts[$userId] ?? 0);
            $visitCount = (int) ($visits[$userId] ?? 0);
            $f2f = (int) ($meetings[$userId] ?? 0);
            $metric = $bookingMetrics[$userId] ?? [
                'units' => 0,
                'booking_value' => 0.0,
                'sqft' => 0.0,
                'revenue_value' => 0.0,
            ];
            $salary = (float) ($periodSalaries[$userId] ?? 0);
            $justification = $salary * 5;
            $revenue = (float) $metric['revenue_value'];

            $totalSalary += $salary;
            $totalJustification += $justification;

            return [
                'advisor_id' => $userId,
                'advisor' => $advisor->name,
                'leads' => $leads,
                'current_unique_leads' => $currentUniqueLeads,
                'incentive' => (float) ($incentives[$userId] ?? 0),
                'visits' => $visitCount,
                'f2f' => $f2f,
                'ps_percent' => $leads > 0 ? (($visitCount + $f2f) / $leads) : 0,
                'pp_percent' => $leads > 0 ? ((int) $metric['units'] / $leads) : 0,
                'units' => (int) $metric['units'],
                'tv_in_cr' => ((float) $metric['booking_value']) / 10000000,
                'sqft' => (float) $metric['sqft'],
                'salary' => $salary,
                'justification' => $justification,
                'revenue_value' => $revenue,
                'contributed_percent' => $totalRevenue > 0 ? ($revenue / $totalRevenue) : 0,
                'ep' => $justification > 0 ? (($revenue - $justification) / $justification) : 0,
            ];
        })->values();

        $totals = [
            'advisor' => 'TOTAL',
            'leads' => (int) $rows->sum('leads'),
            'current_unique_leads' => (int) $currentUniqueLeadMetrics['total'],
            'incentive' => (float) $rows->sum('incentive'),
            'visits' => (int) $rows->sum('visits'),
            'f2f' => (int) $rows->sum('f2f'),
            'ps_percent' => $rows->sum('leads') > 0 ? (($rows->sum('visits') + $rows->sum('f2f')) / $rows->sum('leads')) : 0,
            'pp_percent' => $rows->sum('leads') > 0 ? ($rows->sum('units') / $rows->sum('leads')) : 0,
            'units' => (int) $rows->sum('units'),
            'tv_in_cr' => $totalTv / 10000000,
            'sqft' => (float) $totalSqft,
            'salary' => (float) $totalSalary,
            'justification' => (float) $totalJustification,
            'revenue_value' => (float) $totalRevenue,
            'contributed_percent' => $totalRevenue > 0 ? 1 : 0,
            'ep' => $totalJustification > 0 ? (($totalRevenue - $totalJustification) / $totalJustification) : 0,
        ];

        return [
            'year' => $period['year'],
            'month' => $period['month'],
            'period' => $period,
            'period_label' => $period['label'],
            'period_query' => $period['query'],
            'advisors' => $advisors->values(),
            'rows' => $rows,
            'totals' => $totals,
            'bookings' => $this->revenueBookings($start, $end, $advisorId),
        ];
    }

    public function export(array $report): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Advisor Performance');

        $headers = [
            'Advisor', 'Assigned Leads', 'Current Unique Leads', 'Incentive', 'Visits', 'F2F', 'PS %', 'PP %', 'Units',
            'TV (in Cr)', 'SqFt', 'Salary', 'Justification', 'Revenue Value', '% Contributed', 'EP',
        ];

        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A1', 'Advisor Performance & Revenue Contribution Report - ' . $report['period_label']);
        $sheet->fromArray($headers, null, 'A3');

        $rowIndex = 4;
        foreach ($report['rows'] as $row) {
            $this->writeExcelRow($sheet, $rowIndex++, $row);
        }
        $this->writeExcelRow($sheet, $rowIndex, $report['totals']);

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B6B4F');
        $sheet->getStyle('A3:P3')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A3:P3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('205A44');
        $sheet->getStyle("A{$rowIndex}:P{$rowIndex}")->getFont()->setBold(true);
        $sheet->getStyle("A3:P{$rowIndex}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("G4:H{$rowIndex}")->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle("O4:P{$rowIndex}")->getNumberFormat()->setFormatCode('0.00%');
        $sheet->getStyle("D4:D{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("L4:N{$rowIndex}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->freezePane('A4');

        foreach (range('A', 'P') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'advisor-performance-revenue-' . $this->periodSlug($report['period']) . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function writeExcelRow($sheet, int $rowIndex, array $row): void
    {
        $sheet->fromArray([
            $row['advisor'],
            $row['leads'],
            $row['current_unique_leads'],
            $row['incentive'],
            $row['visits'],
            $row['f2f'],
            $row['ps_percent'],
            $row['pp_percent'],
            $row['units'],
            $row['tv_in_cr'],
            $row['sqft'],
            $row['salary'],
            $row['justification'],
            $row['revenue_value'],
            $row['contributed_percent'],
            $row['ep'],
        ], null, "A{$rowIndex}");
    }

    private function resolveAdvisorIds(Carbon $start, Carbon $end, ?int $advisorId): array
    {
        if ($advisorId) {
            return User::query()
                ->whereKey($advisorId)
                ->whereHas('role', fn ($query) => $query->whereIn('slug', self::ADVISOR_ROLES))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $roleUserIds = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', self::ADVISOR_ROLES))
            ->pluck('id');

        $activityIds = collect()
            ->merge(LeadAssignment::query()
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('assigned_at', [$start, $end])
                        ->orWhere(function ($fallback) use ($start, $end) {
                            $fallback->whereNull('assigned_at')->whereBetween('created_at', [$start, $end]);
                        });
                })
                ->pluck('assigned_to'))
            ->merge(Meeting::query()->whereBetween('completed_at', [$start, $end])->pluck('assigned_to'))
            ->merge(SiteVisit::withQueueHidden()->whereBetween('completed_at', [$start, $end])->pluck('assigned_to'))
            ->merge(Incentive::query()->whereBetween('created_at', [$start, $end])->pluck('user_id'));

        $candidateIds = $roleUserIds->merge($activityIds)->filter()->unique()->map(fn ($id) => (int) $id)->values();

        return User::query()
            ->whereIn('id', $candidateIds)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', self::ADVISOR_ROLES))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function monthlySalary(User $advisor): float
    {
        $profile = $advisor->salaryProfile;
        if (!$profile) {
            return 0.0;
        }

        $baseSalary = (float) $profile->base_salary;
        $earnings = $profile->salaryStructure?->components
            ?->where('is_active', true)
            ->where('component_type', 'earning')
            ->sum(function ($component) use ($baseSalary) {
                return $component->calc_type === 'percent_of_base'
                    ? round($baseSalary * (((float) $component->value) / 100), 2)
                    : round((float) $component->value, 2);
            }) ?? 0;

        return round($baseSalary + (float) $earnings, 2);
    }

    private function monthlySalaryMap(array $advisorIds, Carbon $start): Collection
    {
        $monthDays = $start->daysInMonth;

        return AttendanceMonthlyRollup::query()
            ->whereIn('user_id', $advisorIds)
            ->where('year', $start->year)
            ->where('month', $start->month)
            ->where('estimated_salary', '>', 0)
            ->where('payable_days', '>', 0)
            ->get(['user_id', 'estimated_salary', 'payable_days'])
            ->mapWithKeys(function (AttendanceMonthlyRollup $rollup) use ($monthDays) {
                $salary = ((float) $rollup->estimated_salary / (float) $rollup->payable_days) * $monthDays;

                return [(int) $rollup->user_id => round($salary, 2)];
            });
    }

    private function periodSalaryMap(array $advisorIds, Collection $advisors, Carbon $start, Carbon $end): Collection
    {
        if (empty($advisorIds)) {
            return collect();
        }

        $months = collect();
        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $selectedStart = $start->greaterThan($monthStart) ? $start->copy() : $monthStart;
            $selectedEnd = $end->lessThan($monthEnd) ? $end->copy() : $monthEnd;

            $months->push([
                'year' => $cursor->year,
                'month' => $cursor->month,
                'days_in_month' => $cursor->daysInMonth,
                'selected_days' => $selectedStart->diffInDays($selectedEnd) + 1,
            ]);

            $cursor->addMonthNoOverflow();
        }

        $rollups = AttendanceMonthlyRollup::query()
            ->whereIn('user_id', $advisorIds)
            ->where(function ($query) use ($months) {
                foreach ($months as $month) {
                    $query->orWhere(function ($monthQuery) use ($month) {
                        $monthQuery->where('year', $month['year'])->where('month', $month['month']);
                    });
                }
            })
            ->where('estimated_salary', '>', 0)
            ->where('payable_days', '>', 0)
            ->get(['user_id', 'year', 'month', 'estimated_salary', 'payable_days'])
            ->keyBy(fn (AttendanceMonthlyRollup $rollup) => $rollup->user_id . '-' . $rollup->year . '-' . $rollup->month);

        return collect($advisorIds)->mapWithKeys(function (int $advisorId) use ($advisors, $months, $rollups) {
            $advisor = $advisors[$advisorId] ?? null;
            $fallbackMonthly = $advisor ? $this->monthlySalary($advisor) : 0.0;
            $salary = 0.0;

            foreach ($months as $month) {
                $key = $advisorId . '-' . $month['year'] . '-' . $month['month'];
                $rollup = $rollups[$key] ?? null;
                $fullMonthSalary = $rollup
                    ? (((float) $rollup->estimated_salary / (float) $rollup->payable_days) * (float) $month['days_in_month'])
                    : $fallbackMonthly;
                $salary += ($fullMonthSalary / (float) $month['days_in_month']) * (float) $month['selected_days'];
            }

            return [$advisorId => round($salary, 2)];
        });
    }

    private function parseDate(?string $value, Carbon $fallback): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function systemStartDate(): Carbon
    {
        $dates = collect([
            LeadAssignment::query()->min('assigned_at'),
            LeadAssignment::query()->min('created_at'),
            Meeting::query()->min('completed_at'),
            Meeting::query()->min('created_at'),
            SiteVisit::withQueueHidden()->min('completed_at'),
            SiteVisit::withQueueHidden()->min('created_at'),
            Incentive::query()->min('created_at'),
        ])->filter();

        return $dates->isNotEmpty()
            ? Carbon::parse($dates->min())->startOfDay()
            : now()->startOfYear();
    }

    private function periodSlug(array $period): string
    {
        return match ($period['period_type']) {
            'quarter' => 'fy' . $period['year'] . '-q' . $period['quarter'],
            'year' => 'year-' . $period['year'],
            'till_date' => 'till-date-' . str_replace('_', '-', $period['till_basis']) . '-' . $period['end_date'],
            'custom' => 'custom-' . $period['start_date'] . '-to-' . $period['end_date'],
            default => 'month-' . $period['year'] . '-' . str_pad((string) $period['month'], 2, '0', STR_PAD_LEFT),
        };
    }

    private function leadCounts(array $advisorIds, Carbon $start, Carbon $end): Collection
    {
        return LeadAssignment::query()
            ->whereIn('assigned_to', $advisorIds)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('assigned_at', [$start, $end])
                    ->orWhere(function ($fallback) use ($start, $end) {
                        $fallback->whereNull('assigned_at')->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->select('assigned_to', DB::raw('COUNT(DISTINCT lead_id) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');
    }

    private function currentUniqueLeadMetrics(array $advisorIds, Carbon $start, Carbon $end): array
    {
        if (empty($advisorIds)) {
            return [
                'by_advisor' => collect(),
                'total' => 0,
            ];
        }

        $baseQuery = LeadAssignment::query()
            ->join('leads', 'leads.id', '=', 'lead_assignments.lead_id')
            ->whereIn('lead_assignments.assigned_to', $advisorIds)
            ->where('lead_assignments.is_active', true)
            ->whereNull('leads.deleted_at')
            ->whereBetween('leads.created_at', [$start, $end]);

        return [
            'by_advisor' => (clone $baseQuery)
                ->select('lead_assignments.assigned_to as advisor_id', DB::raw('COUNT(DISTINCT leads.id) as total'))
                ->groupBy('lead_assignments.assigned_to')
                ->pluck('total', 'advisor_id'),
            'total' => (int) (clone $baseQuery)->distinct('leads.id')->count('leads.id'),
        ];
    }

    private function incentiveSums(array $advisorIds, Carbon $start, Carbon $end): Collection
    {
        return Incentive::query()
            ->whereIn('user_id', $advisorIds)
            ->where('status', 'verified')
            ->whereBetween('created_at', [$start, $end])
            ->select('user_id', DB::raw('SUM(amount) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');
    }

    private function visitCounts(array $advisorIds, Carbon $start, Carbon $end): Collection
    {
        return SiteVisit::withQueueHidden()
            ->whereIn('assigned_to', $advisorIds)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');
    }

    private function meetingCounts(array $advisorIds, Carbon $start, Carbon $end): Collection
    {
        return Meeting::withQueueHidden()
            ->whereIn('assigned_to', $advisorIds)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');
    }

    private function bookingMetrics(array $advisorIds, Carbon $start, Carbon $end): Collection
    {
        return SiteVisit::withQueueHidden()
            ->whereIn('assigned_to', $advisorIds)
            ->whereNotNull('revenue_value')
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($financeQuery) use ($start, $end) {
                    $financeQuery->where('finance_handover_status', 'finance_approved')
                        ->whereBetween('finance_reviewed_at', [$start, $end]);
                })->orWhere(function ($legacyQuery) use ($start, $end) {
                    $legacyQuery->where('status', 'completed')
                        ->where(function ($handoverQuery) {
                            $handoverQuery->whereNull('finance_reviewed_at')
                                ->orWhereNull('finance_handover_status')
                                ->orWhere('finance_handover_status', '!=', 'finance_approved');
                        })
                        ->whereHas('incentives', fn ($incentiveQuery) => $this->verifiedCloserInPeriod($incentiveQuery, $start, $end));
                });
            })
            ->get(['id', 'assigned_to', 'unit_details', 'revenue_value'])
            ->groupBy('assigned_to')
            ->map(function (Collection $visits) {
                return [
                    'units' => $visits->count(),
                    'booking_value' => $visits->sum(fn (SiteVisit $visit) => $this->extractMoney($visit->unit_details['final_total'] ?? null)),
                    'sqft' => $visits->sum(fn (SiteVisit $visit) => $this->extractSqft($visit->unit_details ?? [])),
                    'revenue_value' => $visits->sum(fn (SiteVisit $visit) => (float) $visit->revenue_value),
                ];
            });
    }

    private function revenueBookings(Carbon $start, Carbon $end, ?int $advisorId = null): Collection
    {
        return SiteVisit::withQueueHidden()
            ->with(['lead:id,name,phone', 'assignedTo:id,name', 'revenueUpdatedBy:id,name', 'revenueAudits.changedBy:id,name'])
            ->whereNotNull('revenue_value')
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($financeQuery) use ($start, $end) {
                    $financeQuery->where('finance_handover_status', 'finance_approved')
                        ->whereBetween('finance_reviewed_at', [$start, $end]);
                })->orWhere(function ($legacyQuery) use ($start, $end) {
                    $legacyQuery->where('status', 'completed')
                        ->where(function ($handoverQuery) {
                            $handoverQuery->whereNull('finance_reviewed_at')
                                ->orWhereNull('finance_handover_status')
                                ->orWhere('finance_handover_status', '!=', 'finance_approved');
                        })
                        ->whereHas('incentives', fn ($incentiveQuery) => $this->verifiedCloserInPeriod($incentiveQuery, $start, $end));
                });
            })
            ->when($advisorId, fn ($query) => $query->where('assigned_to', $advisorId))
            ->latest('finance_reviewed_at')
            ->latest('revenue_updated_at')
            ->get()
            ->map(function (SiteVisit $visit) {
                return [
                    'id' => $visit->id,
                    'customer' => $visit->customer_name ?: ($visit->lead?->name ?: 'Customer'),
                    'advisor' => $visit->assignedTo?->name ?: 'Unassigned',
                    'project' => $visit->project ?: ($visit->property_name ?: ($visit->unit_details['project_name'] ?? 'Project not filled')),
                    'booking_value' => $this->extractMoney($visit->unit_details['final_total'] ?? null),
                    'sqft' => $this->extractSqft($visit->unit_details ?? []),
                    'revenue_value' => (float) ($visit->revenue_value ?? 0),
                    'revenue_note' => $visit->revenue_note,
                    'finance_reviewed_at' => optional($visit->finance_reviewed_at)?->format('d M Y h:i A'),
                    'updated_by' => $visit->revenueUpdatedBy?->name,
                    'audits' => $visit->revenueAudits->sortByDesc('created_at')->take(3)->values(),
                ];
            });
    }

    private function verifiedCloserInPeriod($query, Carbon $start, Carbon $end)
    {
        return $query->where('type', 'closer')
            ->where('status', 'verified')
            ->where(function ($dateQuery) use ($start, $end) {
                $dateQuery->whereBetween('finance_manager_verified_at', [$start, $end])
                    ->orWhere(function ($fallbackQuery) use ($start, $end) {
                        $fallbackQuery->whereNull('finance_manager_verified_at')
                            ->whereBetween('updated_at', [$start, $end]);
                    });
            });
    }

    private function extractSqft(array $unitDetails): float
    {
        foreach (['super_area_sq_ft', 'carpet_area_sq_ft', 'build_up_area_sq_ft'] as $key) {
            $value = $this->extractMoney($unitDetails[$key] ?? null);
            if ($value > 0) {
                return $value;
            }
        }

        return 0.0;
    }

    private function extractMoney(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
