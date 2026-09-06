<?php

namespace App\Services;

use App\Models\InterestedProjectName;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class LeadExportService
{
    private ?User $exportViewer = null;

    public function getAvailableStatuses(): array
    {
        return [
            'new',
            'fresh_transfer',
            'connected',
            'verified_prospect',
            'meeting_scheduled',
            'meeting_completed',
            'visit_scheduled',
            'visit_done',
            'revisited_scheduled',
            'revisited_completed',
            'closed',
            'dead',
            'junk',
            'not_interested',
            'on_hold',
        ];
    }

    public function getLeadTypeOptions(): array
    {
        return [
            'prospect' => 'Prospect',
            'visit' => 'Visit',
            'revisit' => 'Revisit',
            'meeting' => 'Meeting',
            'closer' => 'Closer',
        ];
    }

    public function getDateRangeOptions(): array
    {
        return [
            'all_time' => 'All Time',
            'today' => 'Today',
            'this_week' => 'This Week',
            'previous_week' => 'Previous Week',
            'this_month' => 'This Month',
            'previous_month' => 'Previous Month',
            'this_year' => 'This Year',
            'custom' => 'Custom Range',
        ];
    }

    public function getFieldLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Customer Name',
            'phone' => 'Phone Number',
            'email' => 'Email',
            'status' => 'Status',
            'budget' => 'Budget',
            'preferred_location' => 'Location',
            'source' => 'Lead Source',
            'assigned_to' => 'Assigned To',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date',
            'last_contacted_at' => 'Last Contacted',
            'notes' => 'Notes',
            'employee_remark' => 'Employee Remark',
            'manager_remark' => 'Manager Remark',
            'interested_projects' => 'Interested Projects',
            'dead_reason' => 'Dead Reason',
            'dead_at_stage' => 'Dead At Stage',
            'marked_dead_at' => 'Marked Dead Date',
            'marked_dead_by' => 'Marked Dead By',
        ];
    }

    public function getInterestedProjects()
    {
        return InterestedProjectName::where('is_active', true)->orderBy('name')->get();
    }

    public function getAvailableSources(): Collection
    {
        return Lead::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->values();
    }

    public function getAvailableAssigneesFor(User $user): Collection
    {
        $accessibleIds = $this->getAccessibleAssigneeIds($user);

        return User::whereIn('id', $accessibleIds)
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', [
                    Role::SALES_MANAGER,
                    Role::SENIOR_MANAGER,
                    Role::ASSISTANT_SALES_MANAGER,
                    Role::SALES_EXECUTIVE,
                ]);
            })
            ->orderBy('name')
            ->get();
    }

    public function sanitizeFields(array $fields): array
    {
        $allowed = array_keys($this->getFieldLabels());
        $fields = array_values(array_intersect($fields, $allowed));

        return empty($fields) ? ['name', 'phone', 'status', 'assigned_to', 'created_at'] : $fields;
    }

    public function sanitizeFiltersForUser(User $user, array $filters): array
    {
        $accessibleIds = $this->getAccessibleAssigneeIds($user);
        $scope = $filters['assigned_scope'] ?? 'my_team';

        if (!in_array($scope, ['own', 'my_team', 'specific_user'], true)) {
            $scope = 'my_team';
        }

        $specificUserId = isset($filters['user_id']) ? (int) $filters['user_id'] : null;
        $hasMultipleAccessibleUsers = count($accessibleIds) > 1;

        if (!$hasMultipleAccessibleUsers && !$user->isAdmin() && !$user->isCrm()) {
            $scope = 'own';
            $specificUserId = null;
        }

        if ($scope === 'specific_user' && (!$specificUserId || !in_array($specificUserId, $accessibleIds, true))) {
            $scope = $hasMultipleAccessibleUsers ? 'my_team' : 'own';
            $specificUserId = null;
        }

        return [
            'report_type' => $filters['report_type'] ?? 'all',
            'status' => array_values(array_filter((array) ($filters['status'] ?? []))),
            'lead_type' => array_values(array_filter((array) ($filters['lead_type'] ?? []))),
            'interested_projects' => array_values(array_map('intval', array_filter((array) ($filters['interested_projects'] ?? [])))),
            'source' => array_values(array_filter((array) ($filters['source'] ?? []), fn ($source) => is_string($source) && trim($source) !== '')),
            'date_range' => $filters['date_range'] ?? 'all_time',
            'from_date' => $filters['from_date'] ?? null,
            'to_date' => $filters['to_date'] ?? null,
            'assigned_scope' => $scope,
            'user_id' => $specificUserId,
            'search' => trim((string) ($filters['search'] ?? '')),
        ];
    }

    public function buildLeadQueryForUser(User $user, array $filters): Builder
    {
        $filters = $this->sanitizeFiltersForUser($user, $filters);
        $reportType = $filters['report_type'] ?? 'all';

        $query = Lead::with([
            'creator',
            'activeAssignments.assignedTo',
            'prospects.interestedProjects',
            'meetings',
            'siteVisits',
        ]);

        $accessibleIds = $this->getAccessibleAssigneeIds($user);

        if (empty($accessibleIds) && !($user->isAdmin() || $user->isCrm())) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin() || $user->isCrm()) {
            if (($filters['assigned_scope'] ?? 'my_team') === 'specific_user' && $filters['user_id']) {
                $query->whereHas('activeAssignments', function ($assignmentQuery) use ($filters) {
                    $assignmentQuery->where('assigned_to', $filters['user_id']);
                });
            }
        } elseif ($reportType === 'visits') {
            $this->applyWorkflowOwnerFilter($query, 'siteVisits', $filters, $user, $accessibleIds);
        } elseif ($reportType === 'meetings') {
            $this->applyWorkflowOwnerFilter($query, 'meetings', $filters, $user, $accessibleIds);
        } elseif (($filters['assigned_scope'] ?? 'my_team') === 'own') {
            $query->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                $assignmentQuery->where('assigned_to', $user->id);
            });
        } elseif (($filters['assigned_scope'] ?? 'my_team') === 'specific_user' && $filters['user_id']) {
            $query->whereHas('activeAssignments', function ($assignmentQuery) use ($filters) {
                $assignmentQuery->where('assigned_to', $filters['user_id']);
            });
        } else {
            $query->whereHas('activeAssignments', function ($assignmentQuery) use ($accessibleIds) {
                $assignmentQuery->whereIn('assigned_to', $accessibleIds);
            });
        }

        if (!empty($filters['status']) && !in_array($reportType, ['visits', 'meetings'], true)) {
            $query->whereIn('status', $filters['status']);
        }

        if (!empty($filters['source'])) {
            $query->whereIn('source', $filters['source']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($reportType === 'visits') {
            $this->applySiteVisitReportFilter($query, $filters, $user, $accessibleIds);
        } elseif ($reportType === 'meetings') {
            $this->applyMeetingReportFilter($query, $filters, $user, $accessibleIds);
        } elseif (!empty($filters['lead_type'])) {
            $types = $filters['lead_type'];
            $query->where(function ($typeQuery) use ($types) {
                foreach ($types as $type) {
                    $typeQuery->orWhere(function ($subQuery) use ($type) {
                        if ($type === 'prospect') {
                            $subQuery->where('status', 'verified_prospect');
                        } elseif ($type === 'visit') {
                            $subQuery->whereIn('status', ['visit_scheduled', 'visit_done'])
                                ->whereHas('siteVisits', function ($visitQuery) {
                                    $visitQuery->where('lead_type', 'New Visit');
                                });
                        } elseif ($type === 'revisit') {
                            $subQuery->whereIn('status', ['revisited_scheduled', 'revisited_completed'])
                                ->whereHas('siteVisits', function ($visitQuery) {
                                    $visitQuery->where('lead_type', 'Revisited');
                                });
                        } elseif ($type === 'meeting') {
                            $subQuery->where(function ($meetingQuery) {
                                $meetingQuery->whereIn('status', ['meeting_scheduled', 'meeting_completed'])
                                    ->orWhereHas('meetings');
                            });
                        } elseif ($type === 'closer') {
                            $subQuery->whereHas('siteVisits', function ($closerQuery) {
                                $closerQuery->where(function ($statusQuery) {
                                    $statusQuery->where('closer_status', 'pending')
                                        ->orWhereNotNull('closer_status');
                                });
                            });
                        }
                    });
                }
            });
        }

        if (!empty($filters['interested_projects']) && !in_array($reportType, ['visits', 'meetings'], true)) {
            $query->whereHas('prospects.interestedProjects', function ($projectQuery) use ($filters) {
                $projectQuery->whereIn('interested_project_names.id', $filters['interested_projects']);
            });
        }

        $range = $this->resolveDateRange($filters['date_range'] ?? 'all_time', $filters['from_date'] ?? null, $filters['to_date'] ?? null);
        if ($range && !in_array($reportType, ['visits', 'meetings'], true)) {
            $query->whereBetween('created_at', [$range['start_date'], $range['end_date']]);
        }

        return $query;
    }

    private function applyWorkflowOwnerFilter(Builder $query, string $relation, array $filters, User $user, array $accessibleIds): void
    {
        $scope = $filters['assigned_scope'] ?? 'my_team';

        if ($scope === 'own') {
            $ownerIds = [$user->id];
        } elseif ($scope === 'specific_user' && $filters['user_id']) {
            $ownerIds = [(int) $filters['user_id']];
        } else {
            $ownerIds = $accessibleIds;
        }

        $query->where(function ($ownerQuery) use ($relation, $ownerIds) {
            $ownerQuery->whereHas($relation, function ($workflowQuery) use ($ownerIds) {
                $workflowQuery->whereIn('assigned_to', $ownerIds);
            })->orWhereHas('activeAssignments', function ($assignmentQuery) use ($ownerIds) {
                $assignmentQuery->whereIn('assigned_to', $ownerIds);
            });
        });
    }

    private function applySiteVisitReportFilter(Builder $query, array $filters, User $user, array $accessibleIds): void
    {
        $range = $this->resolveDateRange($filters['date_range'] ?? 'all_time', $filters['from_date'] ?? null, $filters['to_date'] ?? null);
        $selectedTypes = $filters['lead_type'] ?? [];
        $selectedStatuses = $filters['status'] ?? [];
        $workflowStatuses = [];

        if (array_intersect($selectedStatuses, ['visit_scheduled', 'revisited_scheduled'])) {
            $workflowStatuses = array_merge($workflowStatuses, ['scheduled', 'in_progress', 'rescheduled']);
        }

        if (array_intersect($selectedStatuses, ['visit_done', 'revisited_completed'])) {
            $workflowStatuses[] = 'completed';
        }

        $query->whereHas('siteVisits', function ($visitQuery) use ($filters, $user, $accessibleIds, $range, $selectedTypes, $workflowStatuses) {
            $this->applyWorkflowRelationOwnerFilter($visitQuery, $filters, $user, $accessibleIds);
            $visitQuery->where('is_dead', false);

            if (!empty($workflowStatuses)) {
                $visitQuery->whereIn('status', array_values(array_unique($workflowStatuses)));
            }

            if (count($selectedTypes) === 1) {
                if ($selectedTypes[0] === 'visit') {
                    $visitQuery->where(function ($typeQuery) {
                        $typeQuery->where('lead_type', 'New Visit')
                            ->orWhereNull('lead_type')
                            ->orWhere('lead_type', '');
                    });
                } elseif ($selectedTypes[0] === 'revisit') {
                    $visitQuery->where('lead_type', 'Revisited');
                }
            }

            if ($range) {
                $visitQuery->whereBetween('scheduled_at', [$range['start_date'], $range['end_date']]);
            }
        });
    }

    private function applyMeetingReportFilter(Builder $query, array $filters, User $user, array $accessibleIds): void
    {
        $range = $this->resolveDateRange($filters['date_range'] ?? 'all_time', $filters['from_date'] ?? null, $filters['to_date'] ?? null);
        $selectedStatuses = $filters['status'] ?? [];
        $workflowStatuses = [];

        if (in_array('meeting_scheduled', $selectedStatuses, true)) {
            $workflowStatuses[] = 'scheduled';
        }

        if (in_array('meeting_completed', $selectedStatuses, true)) {
            $workflowStatuses[] = 'completed';
        }

        $query->whereHas('meetings', function ($meetingQuery) use ($filters, $user, $accessibleIds, $range, $workflowStatuses) {
            $this->applyWorkflowRelationOwnerFilter($meetingQuery, $filters, $user, $accessibleIds);
            $meetingQuery->where('is_dead', false)
                ->where('is_converted', false);

            if (!empty($workflowStatuses)) {
                $meetingQuery->whereIn('status', array_values(array_unique($workflowStatuses)));
            }

            if ($range) {
                $meetingQuery->whereBetween('scheduled_at', [$range['start_date'], $range['end_date']]);
            }
        });
    }

    private function applyWorkflowRelationOwnerFilter(
        Builder $workflowQuery,
        array $filters,
        User $user,
        array $accessibleIds,
        bool $includeCreator = false,
        bool $includeLeadActiveAssignments = false
    ): void
    {
        $scope = $filters['assigned_scope'] ?? 'my_team';
        $ownerIds = $accessibleIds;

        if ($scope === 'own') {
            $ownerIds = [$user->id];
        } elseif ($scope === 'specific_user' && $filters['user_id']) {
            $ownerIds = [(int) $filters['user_id']];
        }

        $workflowQuery->where(function ($ownerQuery) use ($ownerIds, $includeCreator, $includeLeadActiveAssignments) {
            $ownerQuery->whereIn('assigned_to', $ownerIds);

            if ($includeCreator) {
                $ownerQuery->orWhereIn('created_by', $ownerIds);
            }

            if ($includeLeadActiveAssignments) {
                $ownerQuery->orWhereHas('lead.activeAssignments', function ($assignmentQuery) use ($ownerIds) {
                    $assignmentQuery->whereIn('assigned_to', $ownerIds)
                        ->where('is_active', true);
                });
            }
        });
    }

    public function countLeadsForUser(User $user, array $filters): int
    {
        return $this->countExportRecordsForUser($user, $filters);
    }

    public function countExportRecordsForUser(User $user, array $filters): int
    {
        $filters = $this->sanitizeFiltersForUser($user, $filters);

        return match ($filters['report_type'] ?? 'all') {
            'visits' => (int) $this->buildSiteVisitExportQueryForUser($user, $filters)->toBase()->getCountForPagination(),
            'meetings' => (int) $this->buildMeetingExportQueryForUser($user, $filters)->toBase()->getCountForPagination(),
            default => (int) $this->buildLeadQueryForUser($user, $filters)->toBase()->getCountForPagination(),
        };
    }

    public function generateLeadExportFile(User $user, array $filters, array $fields, string $format): array
    {
        $this->exportViewer = $user;
        $filters = $this->sanitizeFiltersForUser($user, $filters);
        $fields = $this->sanitizeFields($fields);
        $format = strtolower($format) === 'pdf' ? 'pdf' : 'csv';

        if (($filters['report_type'] ?? 'all') === 'visits') {
            return $this->generateWorkflowExportFile($this->buildSiteVisitExportQueryForUser($user, $filters)->get(), $fields, $format, 'site_visits');
        }

        if (($filters['report_type'] ?? 'all') === 'meetings') {
            return $this->generateWorkflowExportFile($this->buildMeetingExportQueryForUser($user, $filters)->get(), $fields, $format, 'meetings');
        }

        $leads = $this->buildLeadQueryForUser($user, $filters)->get();
        app(LeadDisplayStatusResolver::class)->apply($leads);
        $count = $leads->count();

        if ($count === 0) {
            throw new \RuntimeException('No leads found matching the selected filters.');
        }

        if ($count > 10000) {
            throw new \RuntimeException('Export limit exceeded. Please refine the filters to 10,000 records or less.');
        }

        if ($format === 'pdf') {
            return $this->storeLeadPdf($leads, $fields);
        }

        return $this->storeLeadCsv($leads, $fields);
    }

    public function getAccessibleAssigneeIds(User $user): array
    {
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        if ($user->isAdmin() || $user->isCrm()) {
            return User::where('is_active', true)->pluck('id')->all();
        }

        if ($user->isSalesHead()) {
            return collect([$user->id])
                ->merge($user->getAllTeamMemberIds())
                ->unique()
                ->values()
                ->all();
        }

        if ($user->isSalesManager() || $user->isSeniorManager()) {
            return collect([$user->id])
                ->merge($user->teamMembers()->pluck('id')->all())
                ->unique()
                ->values()
                ->all();
        }

        return [$user->id];
    }

    private function buildSiteVisitExportQueryForUser(User $user, array $filters): Builder
    {
        $filters = $this->sanitizeFiltersForUser($user, $filters);
        $accessibleIds = $this->getAccessibleAssigneeIds($user);
        $range = $this->resolveDateRange($filters['date_range'] ?? 'all_time', $filters['from_date'] ?? null, $filters['to_date'] ?? null);
        $selectedStatuses = $filters['status'] ?? [];
        $selectedTypes = $filters['lead_type'] ?? [];
        $workflowStatuses = [];

        if (array_intersect($selectedStatuses, ['visit_scheduled', 'revisited_scheduled'])) {
            $workflowStatuses = array_merge($workflowStatuses, ['scheduled', 'in_progress', 'rescheduled']);
        }

        if (array_intersect($selectedStatuses, ['visit_done', 'revisited_completed'])) {
            $workflowStatuses[] = 'completed';
        }

        $query = SiteVisit::with(['lead.prospects.interestedProjects', 'assignedTo', 'creator'])
            ->where('is_dead', false)
            ->where(function ($visibleQuery) {
                $visibleQuery->whereNull('lead_id')
                    ->orWhereHas('lead', function ($leadQuery) {
                        $leadQuery->where('status', '!=', 'closed');
                    });
            });

        $this->applyWorkflowRelationOwnerFilter(
            $query,
            $filters,
            $user,
            $accessibleIds,
            includeLeadActiveAssignments: true
        );

        if (!empty($workflowStatuses)) {
            $query->whereIn('status', array_values(array_unique($workflowStatuses)));
        }

        if (count($selectedTypes) === 1) {
            if ($selectedTypes[0] === 'visit') {
                $query->where(function ($typeQuery) {
                    $typeQuery->where('lead_type', 'New Visit')
                        ->orWhereNull('lead_type')
                        ->orWhere('lead_type', '');
                });
            } elseif ($selectedTypes[0] === 'revisit') {
                $query->where('lead_type', 'Revisited');
            }
        }

        if ($range) {
            $query->whereBetween('scheduled_at', [$range['start_date'], $range['end_date']]);
        }

        $this->applyWorkflowCommonFilters($query, $filters);

        return $query->latest('scheduled_at');
    }

    private function buildMeetingExportQueryForUser(User $user, array $filters): Builder
    {
        $filters = $this->sanitizeFiltersForUser($user, $filters);
        $accessibleIds = $this->getAccessibleAssigneeIds($user);
        $range = $this->resolveDateRange($filters['date_range'] ?? 'all_time', $filters['from_date'] ?? null, $filters['to_date'] ?? null);
        $selectedStatuses = $filters['status'] ?? [];
        $workflowStatuses = [];

        if (in_array('meeting_scheduled', $selectedStatuses, true)) {
            $workflowStatuses[] = 'scheduled';
        }

        if (in_array('meeting_completed', $selectedStatuses, true)) {
            $workflowStatuses[] = 'completed';
        }

        $query = Meeting::with(['lead.prospects.interestedProjects', 'assignedTo', 'creator'])
            ->where('is_dead', false)
            ->where('is_converted', false);

        $this->applyWorkflowRelationOwnerFilter(
            $query,
            $filters,
            $user,
            $accessibleIds,
            includeCreator: true
        );

        if (!empty($workflowStatuses)) {
            $query->whereIn('status', array_values(array_unique($workflowStatuses)));
        }

        if ($range) {
            $query->whereBetween('scheduled_at', [$range['start_date'], $range['end_date']]);
        }

        $this->applyWorkflowCommonFilters($query, $filters);

        return $query->latest('scheduled_at');
    }

    private function applyWorkflowCommonFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['source'])) {
            $query->whereHas('lead', function ($leadQuery) use ($filters) {
                $leadQuery->whereIn('source', $filters['source']);
            });
        }

        if (!empty($filters['interested_projects'])) {
            $projectNames = InterestedProjectName::whereIn('id', $filters['interested_projects'])->pluck('name')->all();
            $query->where(function ($projectQuery) use ($filters, $projectNames) {
                if (!empty($projectNames)) {
                    $projectQuery->whereIn('project', $projectNames);
                }

                $projectQuery->orWhereHas('lead.prospects.interestedProjects', function ($leadProjectQuery) use ($filters) {
                    $leadProjectQuery->whereIn('interested_project_names.id', $filters['interested_projects']);
                });
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('lead', function ($leadQuery) use ($search) {
                        $leadQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }
    }

    private function storeLeadCsv(Collection $leads, array $fields): array
    {
        $headers = [];
        $fieldLabels = $this->getFieldLabels();

        foreach ($fields as $field) {
            if (isset($fieldLabels[$field])) {
                $headers[] = $fieldLabels[$field];
            }
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($leads as $lead) {
            $row = [];
            foreach ($fields as $field) {
                $row[] = $this->getLeadFieldValue($lead, $field);
            }
            fputcsv($handle, $row);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        $timestamp = now()->format('Ymd_His');
        $fileName = "asm_leads_{$timestamp}.csv";
        $relativePath = "lead-downloads/{$fileName}";
        Storage::disk('local')->put($relativePath, $contents);

        return [
            'path' => $relativePath,
            'disk' => 'local',
            'file_name' => $fileName,
            'mime_type' => 'text/csv; charset=UTF-8',
            'actual_format' => 'csv',
            'record_count' => $leads->count(),
        ];
    }

    private function storeLeadPdf(Collection $leads, array $fields): array
    {
        $fieldLabels = $this->getFieldLabels();
        $headers = [];

        foreach ($fields as $field) {
            if (isset($fieldLabels[$field])) {
                $headers[] = $fieldLabels[$field];
            }
        }

        $html = view('export.pdf.leads', compact('leads', 'headers', 'fields'))->render();
        $html = (string) app(PhonePrivacyService::class)->maskText($html, $this->exportViewer);
        $timestamp = now()->format('Ymd_His');

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $contents = $pdf->output();
            $fileName = "asm_leads_{$timestamp}.pdf";
            $relativePath = "lead-downloads/{$fileName}";
            Storage::disk('local')->put($relativePath, $contents);

            return [
                'path' => $relativePath,
                'disk' => 'local',
                'file_name' => $fileName,
                'mime_type' => 'application/pdf',
                'actual_format' => 'pdf',
                'record_count' => $leads->count(),
            ];
        }

        $fileName = "asm_leads_{$timestamp}.html";
        $relativePath = "lead-downloads/{$fileName}";
        Storage::disk('local')->put($relativePath, $html);

        return [
            'path' => $relativePath,
            'disk' => 'local',
            'file_name' => $fileName,
            'mime_type' => 'text/html; charset=UTF-8',
            'actual_format' => 'html',
            'record_count' => $leads->count(),
        ];
    }

    private function generateWorkflowExportFile(Collection $records, array $fields, string $format, string $prefix): array
    {
        $count = $records->count();

        if ($count === 0) {
            throw new \RuntimeException('No records found matching the selected filters.');
        }

        if ($count > 10000) {
            throw new \RuntimeException('Export limit exceeded. Please refine the filters to 10,000 records or less.');
        }

        if ($format === 'pdf') {
            return $this->storeWorkflowPdf($records, $fields, $prefix);
        }

        return $this->storeWorkflowCsv($records, $fields, $prefix);
    }

    private function storeWorkflowCsv(Collection $records, array $fields, string $prefix): array
    {
        $fieldLabels = $this->getFieldLabels();
        $headers = [];

        foreach ($fields as $field) {
            if (isset($fieldLabels[$field])) {
                $headers[] = $fieldLabels[$field];
            }
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($records as $record) {
            $row = [];
            foreach ($fields as $field) {
                $row[] = $this->getWorkflowFieldValue($record, $field);
            }
            fputcsv($handle, $row);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        $timestamp = now()->format('Ymd_His');
        $fileName = "asm_{$prefix}_{$timestamp}.csv";
        $relativePath = "lead-downloads/{$fileName}";
        Storage::disk('local')->put($relativePath, $contents);

        return [
            'path' => $relativePath,
            'disk' => 'local',
            'file_name' => $fileName,
            'mime_type' => 'text/csv; charset=UTF-8',
            'actual_format' => 'csv',
            'record_count' => $records->count(),
        ];
    }

    private function storeWorkflowPdf(Collection $records, array $fields, string $prefix): array
    {
        $fieldLabels = $this->getFieldLabels();
        $headers = [];

        foreach ($fields as $field) {
            if (isset($fieldLabels[$field])) {
                $headers[] = $fieldLabels[$field];
            }
        }

        $rows = $records->map(function ($record) use ($fields) {
            $row = [];
            foreach ($fields as $field) {
                $row[] = $this->getWorkflowFieldValue($record, $field);
            }
            return $row;
        });

        $html = view('export.pdf.generic-table', [
            'title' => ucwords(str_replace('_', ' ', $prefix)) . ' Export',
            'headers' => $headers,
            'rows' => $rows,
        ])->render();
        $timestamp = now()->format('Ymd_His');

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $contents = $pdf->output();
            $fileName = "asm_{$prefix}_{$timestamp}.pdf";
            $relativePath = "lead-downloads/{$fileName}";
            Storage::disk('local')->put($relativePath, $contents);

            return [
                'path' => $relativePath,
                'disk' => 'local',
                'file_name' => $fileName,
                'mime_type' => 'application/pdf',
                'actual_format' => 'pdf',
                'record_count' => $records->count(),
            ];
        }

        $fileName = "asm_{$prefix}_{$timestamp}.html";
        $relativePath = "lead-downloads/{$fileName}";
        Storage::disk('local')->put($relativePath, $html);

        return [
            'path' => $relativePath,
            'disk' => 'local',
            'file_name' => $fileName,
            'mime_type' => 'text/html; charset=UTF-8',
            'actual_format' => 'html',
            'record_count' => $records->count(),
        ];
    }

    private function getLeadFieldValue(Lead $lead, string $field): string
    {
        return match ($field) {
            'phone' => (string) app(PhonePrivacyService::class)->display(
                (string) $lead->getRawOriginal('phone'),
                $this->exportViewer
            ),
            'assigned_to' => $lead->activeAssignments->first()?->assignedTo->name ?? 'Unassigned',
            'status' => app(LeadDisplayStatusResolver::class)->label($lead),
            'created_at', 'updated_at', 'last_contacted_at', 'marked_dead_at' => $lead->{$field} ? $lead->{$field}->format('Y-m-d H:i') : 'N/A',
            'marked_dead_by' => optional(User::find($lead->marked_dead_by))->name ?? 'N/A',
            'interested_projects' => $this->formatInterestedProjects($lead),
            default => (string) ($lead->{$field} ?? 'N/A'),
        };
    }

    private function getWorkflowFieldValue($record, string $field): string
    {
        $lead = $record->lead;

        if ($lead instanceof Lead && in_array($field, ['id', 'email', 'source', 'preferred_location', 'budget', 'notes', 'employee_remark', 'manager_remark', 'dead_reason', 'dead_at_stage', 'marked_dead_at', 'marked_dead_by'], true)) {
            return $this->getLeadFieldValue($lead, $field);
        }

        return match ($field) {
            'id' => (string) $record->id,
            'name' => (string) ($lead?->name ?? $record->customer_name ?? 'N/A'),
            'phone' => (string) app(PhonePrivacyService::class)->display(
                (string) ($lead?->getRawOriginal('phone') ?: $record->getRawOriginal('phone') ?: 'N/A'),
                $this->exportViewer
            ),
            'email' => (string) ($lead?->email ?? 'N/A'),
            'status' => ucfirst(str_replace('_', ' ', (string) ($record->status ?? $lead?->status ?? 'N/A'))),
            'assigned_to' => $record->assignedTo?->name ?? $lead?->activeAssignments?->first()?->assignedTo?->name ?? 'Unassigned',
            'created_at', 'updated_at', 'last_contacted_at' => $record->{$field} ? $record->{$field}->format('Y-m-d H:i') : ($lead?->{$field} ? $lead->{$field}->format('Y-m-d H:i') : 'N/A'),
            'preferred_location' => (string) ($record->property_address ?? $record->location ?? $lead?->preferred_location ?? 'N/A'),
            'budget' => (string) ($record->budget_range ?? $lead?->budget ?? 'N/A'),
            'source' => (string) ($lead?->source ?? 'N/A'),
            'notes' => (string) ($record->visit_notes ?? $record->meeting_notes ?? $lead?->notes ?? 'N/A'),
            'interested_projects' => (string) ($record->project ?? ($lead ? $this->formatInterestedProjects($lead) : 'No Projects')),
            default => (string) ($lead?->{$field} ?? $record->{$field} ?? 'N/A'),
        };
    }

    private function formatInterestedProjects(Lead $lead): string
    {
        $projects = collect();

        foreach ($lead->prospects as $prospect) {
            if ($prospect->interestedProjects) {
                $projects = $projects->merge($prospect->interestedProjects);
            }
        }

        $names = $projects->unique('id')->pluck('name');

        return $names->isNotEmpty() ? $names->implode(', ') : 'No Projects';
    }

    private function resolveDateRange(string $range, ?string $fromDate, ?string $toDate): ?array
    {
        $today = Carbon::today();

        return match ($range) {
            'today' => [
                'start_date' => $today->copy()->startOfDay(),
                'end_date' => $today->copy()->endOfDay(),
            ],
            'this_week' => [
                'start_date' => $today->copy()->startOfWeek(),
                'end_date' => $today->copy()->endOfWeek(),
            ],
            'previous_week' => [
                'start_date' => $today->copy()->subWeek()->startOfWeek(),
                'end_date' => $today->copy()->subWeek()->endOfWeek(),
            ],
            'this_month' => [
                'start_date' => $today->copy()->startOfMonth(),
                'end_date' => $today->copy()->endOfMonth(),
            ],
            'previous_month' => [
                'start_date' => $today->copy()->subMonthNoOverflow()->startOfMonth(),
                'end_date' => $today->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'this_year' => [
                'start_date' => $today->copy()->startOfYear(),
                'end_date' => $today->copy()->endOfYear(),
            ],
            'custom' => $this->resolveCustomDateRange($fromDate, $toDate),
            default => null,
        };
    }

    private function resolveCustomDateRange(?string $fromDate, ?string $toDate): ?array
    {
        if (!$fromDate || !$toDate) {
            return null;
        }

        return [
            'start_date' => Carbon::parse($fromDate)->startOfDay(),
            'end_date' => Carbon::parse($toDate)->endOfDay(),
        ];
    }
}
