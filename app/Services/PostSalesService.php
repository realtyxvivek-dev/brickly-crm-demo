<?php

namespace App\Services;

use App\Models\BuilderClaim;
use App\Models\BuilderReleaseScheme;
use App\Models\Incentive;
use App\Models\PostSaleActivity;
use App\Models\PostSaleCase;
use App\Models\PostSaleDemand;
use App\Models\PostSaleDocument;
use App\Models\PostSalePlanTemplate;
use App\Models\PostSaleTransaction;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostSalesService
{
    public const CASE_STATUSES = ['handover_pending', 'active', 'on_hold', 'completed', 'cancelled'];
    public const DOCUMENT_STATUSES = ['pending', 'under_process', 'uploaded', 'sent', 'signed', 'completed'];
    public const DOCUMENT_TYPES = [
        'booking_form' => 'Booking Form',
        'cost_sheet' => 'Cost Sheet',
        'agreement' => 'Agreement',
        'allotment_letter' => 'Allotment Letter',
        'payment_receipts' => 'Payment Receipts',
        'demand_letters' => 'Demand Letters',
        'other_builder_files' => 'Other Builder Files',
    ];

    public function syncIncentive(Incentive $incentive, bool $historical = false): ?PostSaleCase
    {
        if ($incentive->type !== 'closer' || $incentive->status !== 'verified' || !$incentive->finance_manager_verified_by) {
            return null;
        }

        $visit = $incentive->siteVisit()->with(['lead', 'assignedTo'])->first();

        return $visit ? $this->syncSiteVisit($visit, $incentive->finance_manager_verified_by, $historical) : null;
    }

    public function syncSiteVisit(SiteVisit $visit, ?int $actorId = null, bool $historical = false): PostSaleCase
    {
        return DB::transaction(function () use ($visit, $actorId, $historical) {
            $visit->loadMissing(['lead', 'assignedTo']);
            $project = $this->matchProject((string) ($visit->project ?: $visit->property_name));
            $agreementValue = $this->money(data_get($visit->unit_details, 'final_total'));
            $kycComplete = $visit->hasCompleteKyc();
            $ready = $kycComplete && $agreementValue > 0 && (float) $visit->revenue_value > 0 && $project;
            $existing = PostSaleCase::where('site_visit_id', $visit->id)->first();

            $attributes = [
                'lead_id' => $visit->lead_id,
                'project_id' => $project?->id,
                'builder_id' => $project?->builder_id,
                'owner_id' => $visit->assigned_to ?: $visit->created_by,
                'customer_name' => $visit->customer_name ?: ($visit->lead?->name ?: 'Customer'),
                'customer_phone' => $visit->phone ?: $visit->lead?->phone,
                'customer_email' => $visit->lead?->email,
                'project_name' => $visit->project ?: $visit->property_name,
                'unit_label' => $this->unitLabel((array) $visit->unit_details),
                'booking_date' => data_get($visit->unit_details, 'booking_date') ?: $visit->actual_closer_date?->toDateString(),
                'agreement_value' => $agreementValue,
                'revenue_value' => (float) ($visit->revenue_value ?? 0),
                'kyc_complete' => $kycComplete,
                'needs_mapping' => !$project,
                'updated_by' => $actorId,
            ];

            if ($existing) {
                $existing->update($attributes);
                return $existing->fresh();
            }

            $case = PostSaleCase::create($attributes + [
                'site_visit_id' => $visit->id,
                'case_number' => 'PS-TMP-'.Str::uuid(),
                'status' => (!$historical && $ready) ? 'active' : 'handover_pending',
                'reminders_enabled' => false,
                'activated_at' => (!$historical && $ready) ? now() : null,
                'created_by' => $actorId,
            ]);
            $case->update(['case_number' => 'PS-'.now()->format('Y').'-'.str_pad((string) $case->id, 6, '0', STR_PAD_LEFT)]);

            $this->ensureDocuments($case, $actorId);
            if ($case->status === 'active') {
                $this->snapshotPlan($case, $actorId);
                $this->snapshotSlabs($case);
            }
            $this->activity($case, 'case_created', null, $case->fresh()->toArray(), $actorId, $historical ? 'Historical finance handover imported.' : 'Verified closer moved to Post Sales.');

            return $case->fresh();
        });
    }

    public function activate(PostSaleCase $case, User $actor): PostSaleCase
    {
        if (!$case->kyc_complete || $case->agreement_value <= 0 || $case->revenue_value <= 0 || !$case->project_id) {
            throw new \RuntimeException('KYC, agreement value, revenue value and project mapping are required before activation.');
        }

        return DB::transaction(function () use ($case, $actor) {
            $before = $case->toArray();
            $case->update(['status' => 'active', 'activated_at' => $case->activated_at ?: now(), 'updated_by' => $actor->id]);
            $this->snapshotPlan($case, $actor->id);
            $this->snapshotSlabs($case);
            $this->activity($case, 'case_activated', $before, $case->fresh()->toArray(), $actor->id, 'Finance activated the collection workflow.');
            return $case->fresh();
        });
    }

    public function snapshotPlan(PostSaleCase $case, ?int $actorId = null): void
    {
        if ($case->demands()->exists()) {
            return;
        }

        $template = PostSalePlanTemplate::with('items')
            ->where('is_active', true)
            ->where(function ($query) use ($case) {
                $query->where('project_id', $case->project_id)
                    ->orWhere(function ($builder) use ($case) {
                        $builder->whereNull('project_id')->where('builder_id', $case->builder_id);
                    });
            })
            ->orderByRaw('project_id IS NULL')
            ->latest('version')
            ->first();

        $items = $template?->items ?? collect();
        if ($items->isEmpty()) {
            PostSaleDemand::create([
                'post_sale_case_id' => $case->id,
                'title' => 'Initial Booking Payment',
                'amount_type' => 'percentage',
                'percentage' => 10,
                'amount' => round((float) $case->agreement_value * 0.10, 2),
                'due_rule' => 'fixed_date',
                'due_date' => $case->booking_date ?: now()->toDateString(),
                'status' => 'due',
                'sort_order' => 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            return;
        }

        foreach ($items as $item) {
            $amount = $item->amount_type === 'fixed'
                ? (float) $item->fixed_amount
                : round((float) $case->agreement_value * ((float) $item->percentage / 100), 2);
            $dueDate = match ($item->due_rule) {
                'fixed_date' => $item->fixed_date?->toDateString(),
                'relative_days' => ($case->booking_date ?: now())->copy()->addDays((int) $item->relative_days)->toDateString(),
                default => null,
            };
            PostSaleDemand::create([
                'post_sale_case_id' => $case->id,
                'template_item_id' => $item->id,
                'title' => $item->title,
                'amount_type' => $item->amount_type,
                'percentage' => $item->percentage,
                'amount' => $amount,
                'due_rule' => $item->due_rule,
                'due_date' => $dueDate,
                'milestone_name' => $item->milestone_name,
                'status' => $dueDate && $dueDate <= now()->toDateString() ? 'due' : 'upcoming',
                'sort_order' => $item->sort_order,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }
    }

    public function snapshotSlabs(PostSaleCase $case): void
    {
        if ($case->slabs()->exists()) {
            return;
        }

        $scheme = BuilderReleaseScheme::with('slabs')->where('is_active', true)
            ->where(function ($query) use ($case) {
                $query->where('project_id', $case->project_id)
                    ->orWhere(function ($builder) use ($case) {
                        $builder->whereNull('project_id')->where('builder_id', $case->builder_id);
                    });
            })
            ->orderByRaw('project_id IS NULL')
            ->first();

        foreach ($scheme?->slabs ?? [] as $slab) {
            $case->slabs()->create([
                'source_scheme_id' => $scheme->id,
                'customer_collection_percent' => $slab->customer_collection_percent,
                'brokerage_release_percent' => $slab->brokerage_release_percent,
                'sort_order' => $slab->sort_order,
            ]);
        }
    }

    public function metrics(PostSaleCase $case): array
    {
        $transactions = $case->transactions()->where('status', 'verified')->get();
        $paid = (float) $transactions->where('type', 'payment')->sum('amount');
        $reversed = (float) $transactions->whereIn('type', ['refund', 'reversal'])->sum('amount');
        $net = max(0, $paid - $reversed);
        $collectionPercent = $case->agreement_value > 0 ? round($net / (float) $case->agreement_value * 100, 3) : 0;
        $releasePercent = (float) ($case->slabs()->where('customer_collection_percent', '<=', $collectionPercent)->max('brokerage_release_percent') ?? 0);
        $eligible = round((float) $case->revenue_value * ($releasePercent / 100), 2);
        $claimed = (float) $case->claims()->where('status', '!=', 'void')->sum('claim_amount');
        $received = (float) DB::table('builder_receipts')->join('builder_claims', 'builder_claims.id', '=', 'builder_receipts.builder_claim_id')
            ->where('builder_claims.post_sale_case_id', $case->id)->sum('builder_receipts.amount');

        return [
            'paid' => $paid,
            'reversed' => $reversed,
            'net_collected' => $net,
            'collection_percent' => $collectionPercent,
            'release_percent' => $releasePercent,
            'eligible_brokerage' => $eligible,
            'claimed' => $claimed,
            'fresh_claimable' => max(0, round($eligible - $claimed, 2)),
            'received' => $received,
            'outstanding' => max(0, round($claimed - $received, 2)),
            'over_claimed' => $claimed > $eligible,
        ];
    }

    public function refreshDemandStatuses(PostSaleCase $case): void
    {
        foreach ($case->demands as $demand) {
            if ($demand->status === 'cancelled') {
                continue;
            }
            $received = (float) $demand->transactions()->where('status', 'verified')->where('type', 'payment')->sum('amount')
                - (float) $demand->transactions()->where('status', 'verified')->whereIn('type', ['refund', 'reversal'])->sum('amount');
            $status = $received >= (float) $demand->amount ? 'paid'
                : ($received > 0 ? 'part_paid' : ($demand->due_date && $demand->due_date->isPast() ? 'overdue' : ($demand->due_date && $demand->due_date->isToday() ? 'due' : 'upcoming')));
            $demand->update(['status' => $status]);
        }
    }

    public function ensureDocuments(PostSaleCase $case, ?int $actorId = null): void
    {
        foreach (self::DOCUMENT_TYPES as $type => $title) {
            PostSaleDocument::firstOrCreate(
                ['post_sale_case_id' => $case->id, 'document_type' => $type],
                ['title' => $title, 'status' => 'pending', 'updated_by' => $actorId]
            );
        }
    }

    public function activity(PostSaleCase $case, string $action, ?array $before, ?array $after, ?int $actorId, ?string $remark = null, mixed $subject = null): PostSaleActivity
    {
        return PostSaleActivity::create([
            'post_sale_case_id' => $case->id,
            'subject_type' => is_object($subject) ? $subject::class : null,
            'subject_id' => is_object($subject) && method_exists($subject, 'getKey') ? $subject->getKey() : null,
            'action' => $action,
            'remark' => $remark,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'actor_id' => $actorId,
        ]);
    }

    private function matchProject(string $name): ?Project
    {
        $needle = $this->normalizeName($name);
        if ($needle === '') {
            return null;
        }
        return Project::with('builder')->get()->first(fn (Project $project) => $this->normalizeName($project->name) === $needle)
            ?: Project::with('builder')->get()->first(function (Project $project) use ($needle) {
                $candidate = $this->normalizeName($project->name);
                return strlen($needle) >= 5 && (str_contains($candidate, $needle) || str_contains($needle, $candidate));
            });
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(trim($value))) ?: '';
    }

    private function money(mixed $value): float
    {
        return round((float) preg_replace('/[^0-9.\-]/', '', (string) $value), 2);
    }

    private function unitLabel(array $details): ?string
    {
        $parts = array_filter([
            data_get($details, 'unit_type'), data_get($details, 'block_tower'),
            data_get($details, 'tower'), data_get($details, 'unit_number'), data_get($details, 'floor'),
        ]);
        return $parts ? implode(' / ', array_unique($parts)) : null;
    }
}
