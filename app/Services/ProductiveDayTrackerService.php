<?php

namespace App\Services;

use App\Models\SiteVisit;
use App\Models\Meeting;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ProductiveDayTrackerService
{
    public const STATUS_PD = 'pd';
    public const STATUS_PENDING = 'pending';
    public const STATUS_NPD = 'npd';

    private const PRODUCTIVE_VERIFIED_STATUSES = [
        'verified',
        'approved',
        'completed',
        'done',
    ];

    public const SALES_ROLE_SLUGS = [
        'sales_head',
        'sales_manager',
        'senior_manager',
        'assistant_sales_manager',
        'sales_executive',
    ];

    public function forUsers(Collection $profiles, CarbonInterface $startDate, CarbonInterface $endDate, ?User $actor = null): Collection
    {
        $salesUserIds = $profiles
            ->filter(fn ($profile) => $this->isSalesProfile($profile))
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($salesUserIds->isEmpty()) {
            return collect();
        }

        $visits = SiteVisit::query()
            ->withoutGlobalScope('visible_in_queue')
            ->with(['lead:id,name,phone', 'creator:id,name,role_id,manager_id', 'assignedTo:id,name'])
            ->whereIn('assigned_to', $salesUserIds)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('completed_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                    ->orWhereBetween('date_of_visit', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($fallbackQuery) use ($startDate, $endDate) {
                        $fallbackQuery->whereNull('completed_at')
                            ->whereNull('date_of_visit')
                            ->whereBetween('scheduled_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);
                    });
            })
            ->where('status', '!=', 'cancelled')
            ->when(Schema::hasColumn('site_visits', 'is_dead'), fn ($query) => $query->where(function ($inner) {
                $inner->whereNull('is_dead')->orWhere('is_dead', false);
            }))
            ->get(['id', 'lead_id', 'created_by', 'assigned_to', 'customer_name', 'phone', 'property_name', 'property_address', 'date_of_visit', 'scheduled_at', 'completed_at', 'status', 'verification_status', 'photos', 'completion_proof_photos', 'closer_request_proof_photos']);

        $meetings = Meeting::query()
            ->withoutGlobalScope('visible_in_queue')
            ->with(['lead:id,name,phone', 'prospect:id,name,phone', 'creator:id,name,role_id,manager_id', 'assignedTo:id,name'])
            ->whereIn('assigned_to', $salesUserIds)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('completed_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                    ->orWhereBetween('date_of_visit', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($fallbackQuery) use ($startDate, $endDate) {
                        $fallbackQuery->whereNull('completed_at')
                            ->whereNull('date_of_visit')
                            ->whereBetween('scheduled_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()]);
                    });
            })
            ->where('status', '!=', 'cancelled')
            ->when(Schema::hasColumn('meetings', 'is_dead'), fn ($query) => $query->where(function ($inner) {
                $inner->whereNull('is_dead')->orWhere('is_dead', false);
            }))
            ->get(['id', 'lead_id', 'prospect_id', 'created_by', 'assigned_to', 'customer_name', 'phone', 'project', 'date_of_visit', 'scheduled_at', 'completed_at', 'status', 'verification_status', 'photos', 'completion_proof_photos']);

        $routing = app(VerificationRoutingService::class);
        $productiveItems = $visits->map(fn (SiteVisit $visit) => $this->productiveItem($visit, $routing, $actor))
            ->toBase()
            ->merge($meetings->map(fn (Meeting $meeting) => $this->productiveItem($meeting, $routing, $actor))->toBase())
            ->filter(fn (array $item) => !empty($item['date']));

        return $productiveItems
            ->groupBy(fn (array $item) => $item['assigned_to'] . '|' . $item['date'])
            ->map(function (Collection $dayItems) {
                $assigned = $dayItems->count();
                $verified = $dayItems
                    ->filter(fn (array $item) => in_array(
                        strtolower((string) ($item['verification_status'] ?? '')),
                        self::PRODUCTIVE_VERIFIED_STATUSES,
                        true
                    ))
                    ->count();
                $status = $verified > 0 ? self::STATUS_PD : self::STATUS_PENDING;
                $statusCounts = $dayItems
                    ->countBy(fn (array $item) => strtolower((string) ($item['verification_status'] ?? 'pending')))
                    ->map(fn ($count, $statusName) => ucfirst(str_replace('_', ' ', $statusName)) . ': ' . $count)
                    ->values()
                    ->implode(', ');

                return [
                    'status' => $status,
                    'label' => $status === self::STATUS_PD ? 'PD' : 'Pending',
                    'assigned' => $assigned,
                    'verified' => $verified,
                    'title' => trim("Assigned: {$assigned}, Verified: {$verified}" . ($statusCounts ? " ({$statusCounts})" : '')),
                    'items' => $dayItems->values()->all(),
                ];
            });
    }

    public function forUser(User $user, CarbonInterface $startDate, CarbonInterface $endDate, ?User $actor = null): Collection
    {
        return $this->forUsers(collect([$this->profileForUser($user)]), $startDate, $endDate, $actor);
    }

    public function forCell(Collection $tracker, int|string|null $userId, CarbonInterface|string $date): ?array
    {
        if (!$userId) {
            return null;
        }

        $dateKey = $date instanceof CarbonInterface ? $date->toDateString() : (string) $date;
        $entry = $tracker->get($userId . '|' . $dateKey);

        if ($entry) {
            return $entry;
        }

        return [
            'status' => self::STATUS_NPD,
            'label' => 'NPD',
            'assigned' => 0,
            'verified' => 0,
            'title' => 'No assigned visit',
            'items' => [],
        ];
    }

    public function summary(Collection $profiles, Collection $dates, Collection $tracker): array
    {
        $summary = [
            self::STATUS_PD => 0,
            self::STATUS_PENDING => 0,
            self::STATUS_NPD => 0,
        ];

        foreach ($profiles as $profile) {
            if (!$this->isSalesProfile($profile)) {
                continue;
            }

            foreach ($dates as $date) {
                $entry = $this->forCell($tracker, $profile->user_id, $date);
                $summary[$entry['status']]++;
            }
        }

        return $summary;
    }

    public function isSalesProfile($profile): bool
    {
        $slug = $profile?->user?->role?->slug;

        return in_array($slug, self::SALES_ROLE_SLUGS, true);
    }

    public function profileForUser(User $user): UserAttendanceProfile
    {
        $user->loadMissing(['role', 'employeeProfile', 'attendanceProfile.officeLocation']);
        $profile = $user->attendanceProfile ?: new UserAttendanceProfile([
            'user_id' => $user->id,
            'employee_code' => $user->employeeProfile?->employee_code,
            'attendance_enabled' => false,
        ]);

        $profile->setRelation('user', $user);

        return $profile;
    }

    private function productiveItem(SiteVisit|Meeting $item, VerificationRoutingService $routing, ?User $actor = null): array
    {
        $type = $item instanceof SiteVisit ? 'site_visit' : 'meeting';
        $workflow = $item instanceof SiteVisit
            ? VerificationRoutingService::WORKFLOW_SITE_VISIT
            : VerificationRoutingService::WORKFLOW_MEETING;
        $status = strtolower((string) ($item->verification_status ?? 'pending'));
        $isPending = in_array($status, ['', 'pending', 'pending_verification'], true);
        $isCompleted = (string) ($item->status ?? '') === 'completed';
        $canRouteVerify = $actor && $isPending
            ? $routing->canVerify($actor, $item, $workflow)
            : false;
        $canVerify = $actor && $isPending && $isCompleted
            ? $canRouteVerify
            : false;

        return [
            'type' => $type,
            'type_label' => $item instanceof SiteVisit ? 'Site Visit' : 'Meeting',
            'id' => $item->id,
            'lead_id' => $item->lead_id,
            'view_url' => $item->lead_id ? url('/leads/' . $item->lead_id) : null,
            'assigned_to' => $item->assigned_to,
            'assigned_to_name' => $item->assignedTo?->name,
            'customer_name' => $item->customer_name ?: ($item->lead?->name ?: ($item->prospect?->name ?? 'Unknown lead')),
            'phone' => $item->phone ?: ($item->lead?->phone ?: ($item->prospect?->phone ?? null)),
            'project' => $item instanceof SiteVisit ? ($item->property_name ?: $item->property_address) : $item->project,
            'date' => $this->productiveDate($item),
            'scheduled_at' => $item->scheduled_at?->format('d M Y, h:i A'),
            'completed_at' => $item->completed_at?->format('d M Y, h:i A'),
            'verification_status' => $status ?: 'pending',
            'is_verified' => in_array($status, self::PRODUCTIVE_VERIFIED_STATUSES, true),
            'can_verify' => $canVerify,
            'can_reject' => (bool) $canRouteVerify,
            'photos' => $this->photoUrls($item->photos ?? []),
            'proof_photos' => $this->photoUrls($item->completion_proof_photos ?? []),
            'closer_proof_photos' => $item instanceof SiteVisit ? $this->photoUrls($item->closer_request_proof_photos ?? []) : [],
            'cannot_verify_reason' => $isCompleted
                ? 'You are not eligible to verify this item based on Verification Routing.'
                : 'This item can be verified after it is completed.',
            'cannot_reject_reason' => 'You are not eligible to reject this item based on Verification Routing.',
        ];
    }

    private function photoUrls(array|string|null $photos): array
    {
        if (empty($photos)) {
            return [];
        }

        $photos = is_array($photos) ? $photos : [$photos];

        return collect($photos)
            ->filter(fn ($photo) => is_string($photo) && trim($photo) !== '')
            ->map(function (string $photo) {
                $photo = trim($photo);

                if (filter_var($photo, FILTER_VALIDATE_URL)) {
                    return $photo;
                }

                return asset('storage/' . ltrim($photo, '/'));
            })
            ->values()
            ->all();
    }

    private function productiveDate(SiteVisit|Meeting $item): string
    {
        return $item->completed_at?->toDateString()
            ?: $item->date_of_visit?->toDateString()
            ?: $item->scheduled_at?->toDateString()
            ?: '';
    }
}
