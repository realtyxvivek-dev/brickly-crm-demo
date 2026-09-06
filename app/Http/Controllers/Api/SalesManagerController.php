<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\SalesManagerProfile;
use App\Models\Prospect;
use App\Models\Target;
use App\Models\Lead;
use App\Models\LeadFavorite;
use App\Models\LeadFormField;
use App\Models\LeadFormFieldValue;
use App\Models\LeadAssignment;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\FollowUp;
use App\Models\InterestedProjectName;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\UserProfile;
use App\Services\LeadOutcomeService;
use App\Services\UserStatusService;
use App\Services\AsmCnpAutomationService;
use App\Services\DynamicFormService;
use App\Services\MetaReviewAccessService;
use App\Services\MetaReviewSaveService;
use App\Services\MetaReviewStageService;
use App\Services\MetaReviewAutoStageService;
use App\Services\NotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SalesManagerController extends Controller
{
    public function clearDashboardCache()
    {
        try {
            Artisan::call('optimize:clear');

            return response()->json([
                'success' => true,
                'message' => 'Dashboard cache cleared successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to clear Sales Manager dashboard cache: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear dashboard cache.',
            ], 500);
        }
    }

    private function defaultDashboardVisibility(): array
    {
        return [
            'today_focus_panel' => true,
            'today_focus_fresh_leads' => true,
            'today_focus_overdue' => true,
            'today_focus_meetings' => true,
            'today_focus_site_visits' => true,
            'today_focus_follow_ups' => true,
            'favorites_panel' => true,
            'stat_leads_received' => true,
            'stat_todays_prospects' => true,
            'stat_pending_verifications' => true,
            'stat_overdue_tasks' => true,
            'stat_team_members' => true,
            'stat_pending_tasks' => true,
            'stat_no_response_yet' => true,
            'no_response_section' => true,
            'team_call_stats_section' => true,
            'manager_targets_section' => true,
            'team_targets_section' => true,
            'team_members_cards_section' => true,
            'incentives_section' => true,
        ];
    }

    private function normalizedDashboardVisibility(?array $preferences): array
    {
        $defaults = $this->defaultDashboardVisibility();
        $saved = is_array($preferences['dashboard_visibility'] ?? null)
            ? $preferences['dashboard_visibility']
            : [];

        $normalized = [];
        foreach ($defaults as $key => $default) {
            $normalized[$key] = array_key_exists($key, $saved)
                ? filter_var($saved[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $saved[$key]
                : $default;
        }

        return $normalized;
    }

    private function resolveInterestedProjectName(string $projectName, int $userId): InterestedProjectName
    {
        $normalizedName = trim($projectName);
        $slug = Str::slug($normalizedName);

        $existing = InterestedProjectName::query()
            ->where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])
            ->first();

        if ($existing) {
            if (!$existing->is_active) {
                $existing->is_active = true;
                $existing->save();
            }

            return $existing;
        }

        return InterestedProjectName::create([
            'name' => $normalizedName,
            'slug' => $slug ?: Str::slug(Str::random(8)),
            'is_active' => true,
            'created_by' => $userId,
        ]);
    }

    private function getInterestedProjectOptions(): array
    {
        $options = InterestedProjectName::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        return count($options) > 0
            ? $options
            : ['Jash Elevate', 'Oro Constella', 'Okas Res.'];
    }

    private function getAllowedInterestedProjectNames(?array $forms = null): array
    {
        $forms = $forms ?? $this->getLeadDetailDynamicForms();
        $requirementsForm = $forms['requirements'] ?? null;
        $field = $requirementsForm?->fields?->firstWhere('field_key', 'interested_projects');
        $options = is_array($field?->options) && count($field->options)
            ? $field->options
            : $this->getInterestedProjectOptions();

        return collect($options)
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->unique(fn ($option) => mb_strtolower($option))
            ->values()
            ->all();
    }

    private function resolveInterestedProjectIdsFromSelection(array $submittedProjects, int $userId, ?array $forms = null): array
    {
        $allowedNames = $this->getAllowedInterestedProjectNames($forms);
        $allowedLookup = collect($allowedNames)
            ->mapWithKeys(fn ($name) => [mb_strtolower($name) => $name]);

        $projectIds = [];

        foreach ($submittedProjects as $project) {
            if (is_int($project) || is_numeric($project)) {
                $projectModel = InterestedProjectName::query()->find((int) $project);
                if ($projectModel && $allowedLookup->has(mb_strtolower($projectModel->name))) {
                    $projectIds[] = $projectModel->id;
                }
                continue;
            }

            if (!is_array($project) || !isset($project['name'])) {
                continue;
            }

            $projectName = trim((string) $project['name']);
            if ($projectName === '') {
                continue;
            }

            $canonicalName = $allowedLookup->get(mb_strtolower($projectName));
            if (!$canonicalName) {
                continue;
            }

            $projectModel = $this->resolveInterestedProjectName($canonicalName, $userId);
            $projectIds[] = $projectModel->id;
        }

        return array_values(array_unique(array_map('intval', $projectIds)));
    }

    private function getLeadDetailDynamicForms(): array
    {
        $dynamicFormService = app(DynamicFormService::class);

        return [
            'requirements' => $dynamicFormService->getPublishedFormByLocation('lead-detail.requirements'),
            'meeting' => $dynamicFormService->getPublishedFormByLocation('lead-detail.meeting'),
            'site_visit' => $dynamicFormService->getPublishedFormByLocation('lead-detail.site-visit'),
            'follow_up' => $dynamicFormService->getPublishedFormByLocation('lead-detail.follow-up'),
        ];
    }

    private function dynamicFieldConfig($form, string $fieldKey, array $defaults = []): array
    {
        $field = $form?->fields?->firstWhere('field_key', $fieldKey);

        return [
            'label' => $field?->label ?? ($defaults['label'] ?? ''),
            'placeholder' => $field?->placeholder ?? ($defaults['placeholder'] ?? ''),
            'help_text' => $field?->help_text ?? ($defaults['help_text'] ?? ''),
            'required' => $field ? (bool) $field->required : ($defaults['required'] ?? false),
            'options' => ($field && is_array($field->options) && count($field->options))
                ? $field->options
                : ($defaults['options'] ?? []),
            'default_value' => $field?->default_value ?? ($defaults['default_value'] ?? ''),
            'field_type' => $field?->field_type ?? ($defaults['field_type'] ?? 'text'),
            'section' => $field?->section ?? ($defaults['section'] ?? null),
            'order' => $field?->order ?? ($defaults['order'] ?? 0),
        ];
    }

    private function buildLeadDetailRequirementsFormConfig(array $forms, ?Lead $lead = null, ?User $user = null, string $context = 'lead'): array
    {
        $requirementsForm = $forms['requirements'];

        $nameField = $this->dynamicFieldConfig($requirementsForm, 'name', [
            'label' => 'Customer name',
            'placeholder' => 'Enter lead name',
            'required' => true,
        ]);
        $phoneField = $this->dynamicFieldConfig($requirementsForm, 'phone', [
            'label' => 'Phone',
            'placeholder' => 'Enter phone number',
            'required' => true,
        ]);
        $categoryField = $this->dynamicFieldConfig($requirementsForm, 'category', [
            'label' => 'Category',
            'required' => true,
            'options' => ['Residential', 'Commercial', 'Both', 'N.A'],
        ]);
        $locationField = $this->dynamicFieldConfig($requirementsForm, 'preferred_location', [
            'label' => 'Location',
            'required' => true,
            'options' => ['Inside City', 'Sitapur Road', 'Hardoi Road', 'Faizabad Road', 'Sultanpur Road', 'Shaheed Path', 'Raebareily Road', 'Kanpur Road', 'Outer Ring Road', 'Bijnor Road', 'Deva Road', 'Sushant Golf City', 'Vrindavan Yojana', 'N.A'],
        ]);
        $budgetField = $this->dynamicFieldConfig($requirementsForm, 'budget', [
            'label' => 'Budget',
            'required' => true,
            'options' => ['Below 50 Lacs', '50-75 Lacs', '75 Lacs-1 Cr', 'Above 1 Cr', 'Above 2 Cr', 'N.A'],
        ]);
        $typeField = $this->dynamicFieldConfig($requirementsForm, 'type', [
            'label' => 'Type',
            'placeholder' => 'Select type',
            'required' => true,
        ]);
        $purposeField = $this->dynamicFieldConfig($requirementsForm, 'purpose', [
            'label' => 'Purpose',
            'required' => true,
            'options' => ['End Use', 'Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use', 'N.A'],
        ]);
        $possessionField = $this->dynamicFieldConfig($requirementsForm, 'possession', [
            'label' => 'Possession',
            'required' => true,
            'options' => ['Under Construction', 'Ready To Move', 'Pre Launch', 'Both', 'N.A'],
        ]);
        $leadStatusField = $this->dynamicFieldConfig($requirementsForm, 'lead_status', [
            'label' => 'Status',
            'required' => true,
            'options' => ['hot', 'warm', 'cold', 'junk'],
        ]);
        $leadQualityField = $this->dynamicFieldConfig($requirementsForm, 'lead_quality', [
            'label' => 'Lead quality',
            'required' => true,
            'options' => ['1', '2', '3', '4', '5'],
        ]);
        $interestedProjectsField = $this->dynamicFieldConfig($requirementsForm, 'interested_projects', [
            'label' => 'Interested projects',
            'placeholder' => 'Search and select interested projects',
            'required' => true,
            'options' => $this->getInterestedProjectOptions(),
            'field_type' => 'select',
        ]);
        $customerJobField = $this->dynamicFieldConfig($requirementsForm, 'customer_job', [
            'label' => 'Customer job',
            'placeholder' => 'Enter job / occupation',
        ]);
        $industryField = $this->dynamicFieldConfig($requirementsForm, 'industry_sector', [
            'label' => 'Industry / sector',
            'options' => ['IT', 'Education', 'Healthcare', 'Business', 'FMCG', 'Government', 'Other'],
        ]);
        $buyingFrequencyField = $this->dynamicFieldConfig($requirementsForm, 'buying_frequency', [
            'label' => 'Buying frequency',
            'options' => ['Regular', 'Occasional', 'First-time'],
        ]);
        $livingCityField = $this->dynamicFieldConfig($requirementsForm, 'living_city', [
            'label' => 'Living city',
            'placeholder' => 'Enter living city',
        ]);
        $cityTypeField = $this->dynamicFieldConfig($requirementsForm, 'city_type', [
            'label' => 'City type',
            'options' => ['Metro', 'Tier 1', 'Tier 2', 'Tier 3', 'Local Resident'],
        ]);
        $remarkField = $this->dynamicFieldConfig($requirementsForm, 'manager_remark', [
            'label' => 'Remark',
            'placeholder' => 'Enter remarks or notes...',
        ]);
        $metaStageField = $this->dynamicFieldConfig($requirementsForm, 'meta_stage', [
            'label' => 'Meta stage',
            'placeholder' => 'Select Meta stage',
            'options' => app(MetaReviewStageService::class)->options(),
        ]);
        $metaReviewNoteField = $this->dynamicFieldConfig($requirementsForm, 'meta_review_note', [
            'label' => 'Meta review note',
            'placeholder' => 'Add Meta qualification note...',
        ]);

        return [
            'name' => [
                'label' => $nameField['label'],
                'placeholder' => $nameField['placeholder'],
                'required' => $nameField['required'],
            ],
            'phone' => [
                'label' => $phoneField['label'],
                'placeholder' => $phoneField['placeholder'],
                'required' => $phoneField['required'],
            ],
            'category' => [
                'label' => $categoryField['label'],
                'required' => $categoryField['required'],
                'options' => $categoryField['options'],
            ],
            'preferred_location' => [
                'label' => $locationField['label'],
                'required' => $locationField['required'],
                'options' => $locationField['options'],
            ],
            'budget' => [
                'label' => $budgetField['label'],
                'required' => $budgetField['required'],
                'options' => $budgetField['options'],
            ],
            'type' => [
                'label' => $typeField['label'],
                'required' => $typeField['required'],
                'placeholder' => $typeField['placeholder'],
            ],
            'purpose' => [
                'label' => $purposeField['label'],
                'required' => $purposeField['required'],
                'options' => $purposeField['options'],
            ],
            'possession' => [
                'label' => $possessionField['label'],
                'required' => $possessionField['required'],
                'options' => $possessionField['options'],
            ],
            'lead_status' => [
                'label' => $leadStatusField['label'],
                'required' => $leadStatusField['required'],
                'options' => $leadStatusField['options'],
            ],
            'lead_quality' => [
                'label' => $leadQualityField['label'],
                'required' => $leadQualityField['required'],
                'options' => $leadQualityField['options'],
            ],
            'interested_projects' => [
                'label' => $interestedProjectsField['label'],
                'placeholder' => $interestedProjectsField['placeholder'],
                'required' => $interestedProjectsField['required'],
                'options' => $interestedProjectsField['options'],
                'allow_custom' => false,
                'field_type' => $interestedProjectsField['field_type'],
            ],
            'customer_job' => [
                'label' => $customerJobField['label'],
                'placeholder' => $customerJobField['placeholder'],
            ],
            'industry_sector' => [
                'label' => $industryField['label'],
                'options' => $industryField['options'],
            ],
            'buying_frequency' => [
                'label' => $buyingFrequencyField['label'],
                'options' => $buyingFrequencyField['options'],
            ],
            'living_city' => [
                'label' => $livingCityField['label'],
                'placeholder' => $livingCityField['placeholder'],
            ],
            'city_type' => [
                'label' => $cityTypeField['label'],
                'options' => $cityTypeField['options'],
            ],
            'manager_remark' => [
                'label' => $remarkField['label'],
                'placeholder' => $remarkField['placeholder'],
            ],
            'meta_review' => $this->buildMetaReviewConfig($lead, $user, $context, $metaStageField, $metaReviewNoteField),
            'type_option_groups' => [
                'Residential' => ['Plots & Villas', 'Apartments', 'Studio', 'Farmhouse', 'N.A'],
                'Commercial' => ['Retail Shops', 'Office Space', 'Studio', 'N.A'],
                'Both' => ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A'],
                'N.A' => ['N.A'],
            ],
        ];
    }

    private function buildMetaReviewConfig(?Lead $lead, ?User $user, string $context, array $metaStageField, array $metaReviewNoteField): array
    {
        $accessService = app(MetaReviewAccessService::class);
        $stageService = app(MetaReviewStageService::class);
        $linkage = $lead ? $accessService->resolveMetaLinkage($lead) : ['is_linked' => false];
        $visible = $context === 'lead' && $lead !== null && $user !== null && $linkage['is_linked'];
        $editable = $visible && $accessService->canEdit($user, $lead);

        return [
            'visible' => $visible,
            'editable' => $editable,
            'owner' => $accessService->getOwner(),
            'stage' => [
                'label' => $metaStageField['label'],
                'placeholder' => $metaStageField['placeholder'],
                'options' => $stageService->labels(),
            ],
            'note' => [
                'label' => $metaReviewNoteField['label'],
                'placeholder' => $metaReviewNoteField['placeholder'],
            ],
            'sync' => [
                'status' => $lead?->meta_sync_status,
                'last_synced_at' => $lead?->meta_last_synced_at?->toDateTimeString(),
                'last_sent_stage' => $lead?->last_sent_meta_stage,
                'message' => $lead?->meta_sync_status === 'skipped' && $lead?->meta_last_sync_error
                    ? $lead->meta_last_sync_error
                    : null,
                'error' => $lead?->meta_sync_status === 'failed' ? $lead?->meta_last_sync_error : null,
                'updated_by' => $lead?->metaStageUpdatedBy?->name,
                'updated_at' => $lead?->meta_stage_updated_at?->toDateTimeString(),
            ],
        ];
    }

    private function buildLeadDetailOutputFormConfig(array $forms): array
    {
        $followUpForm = $forms['follow_up'];
        $meetingForm = $forms['meeting'];
        $siteVisitForm = $forms['site_visit'];

        $followUpRequiredField = $this->dynamicFieldConfig($followUpForm, 'followup_required', ['label' => 'Follow up required']);
        $followUpDateField = $this->dynamicFieldConfig($followUpForm, 'scheduled_at', [
            'label' => 'Follow up date & time',
            'required' => true,
            'help_text' => 'Select a future date and time for the next follow-up.',
        ]);
        $followUpNotesField = $this->dynamicFieldConfig($followUpForm, 'notes', [
            'label' => 'Remark',
            'placeholder' => 'Add follow-up note, context, or callback instruction...',
        ]);

        $meetingTypeField = $this->dynamicFieldConfig($meetingForm, 'meeting_type', [
            'label' => 'Meeting type',
            'required' => true,
            'options' => ['Initial Meeting', 'Follow-up Meeting', 'Negotiation Meeting', 'Closing Meeting'],
        ]);
        $meetingDateField = $this->dynamicFieldConfig($meetingForm, 'meeting_date', [
            'label' => 'Scheduled date',
            'required' => true,
        ]);
        $meetingTimeField = $this->dynamicFieldConfig($meetingForm, 'meeting_time', [
            'label' => 'Scheduled time',
            'required' => true,
        ]);
        $meetingModeField = $this->dynamicFieldConfig($meetingForm, 'meeting_mode', [
            'label' => 'Meeting mode',
            'required' => true,
            'options' => ['Online', 'Offline'],
        ]);
        $meetingLinkField = $this->dynamicFieldConfig($meetingForm, 'meeting_link', [
            'label' => 'Meeting link',
            'placeholder' => 'https://meet.google.com/...',
        ]);
        $meetingLocationField = $this->dynamicFieldConfig($meetingForm, 'location', [
            'label' => 'Location',
            'placeholder' => 'Office address, project site, etc.',
        ]);
        $meetingNotesField = $this->dynamicFieldConfig($meetingForm, 'meeting_notes', [
            'label' => 'Remark',
            'placeholder' => 'Any notes about this meeting...',
        ]);
        $meetingReminderField = $this->dynamicFieldConfig($meetingForm, 'reminder_enabled', [
            'label' => 'Remind me before meeting',
        ]);

        $visitDateField = $this->dynamicFieldConfig($siteVisitForm, 'visit_date', [
            'label' => 'Visit date',
            'required' => true,
        ]);
        $visitTimeField = $this->dynamicFieldConfig($siteVisitForm, 'visit_time', [
            'label' => 'Visit time',
            'required' => true,
        ]);
        $visitTypeField = $this->dynamicFieldConfig($siteVisitForm, 'visit_type', [
            'label' => 'Visit type',
            'options' => ['Site visit', 'Office visit'],
        ]);
        $visitProjectField = $this->dynamicFieldConfig($siteVisitForm, 'project_name', [
            'label' => 'Project to visit',
            'placeholder' => 'Enter project name',
        ]);
        $visitLocationField = $this->dynamicFieldConfig($siteVisitForm, 'visit_location', [
            'label' => 'Visit location',
            'placeholder' => 'Project site address or landmark',
        ]);
        $visitNotesField = $this->dynamicFieldConfig($siteVisitForm, 'visit_notes', [
            'label' => 'Remark',
            'placeholder' => 'Add visit note or instruction...',
        ]);
        $visitReminderField = $this->dynamicFieldConfig($siteVisitForm, 'visit_reminder', [
            'label' => 'Remind me before visit',
        ]);

        return [
            'follow_up' => [
                'required_label' => $followUpRequiredField['label'],
                'date' => [
                    'label' => $followUpDateField['label'],
                    'required' => $followUpDateField['required'],
                    'help_text' => $followUpDateField['help_text'],
                ],
                'notes' => [
                    'label' => $followUpNotesField['label'],
                    'placeholder' => $followUpNotesField['placeholder'],
                ],
            ],
            'meeting' => [
                'type' => [
                    'label' => $meetingTypeField['label'],
                    'required' => $meetingTypeField['required'],
                    'options' => $meetingTypeField['options'],
                ],
                'date' => [
                    'label' => $meetingDateField['label'],
                    'required' => $meetingDateField['required'],
                ],
                'time' => [
                    'label' => $meetingTimeField['label'],
                    'required' => $meetingTimeField['required'],
                ],
                'mode' => [
                    'label' => $meetingModeField['label'],
                    'required' => $meetingModeField['required'],
                    'options' => $meetingModeField['options'],
                ],
                'link' => [
                    'label' => $meetingLinkField['label'],
                    'placeholder' => $meetingLinkField['placeholder'],
                ],
                'location' => [
                    'label' => $meetingLocationField['label'],
                    'placeholder' => $meetingLocationField['placeholder'],
                ],
                'notes' => [
                    'label' => $meetingNotesField['label'],
                    'placeholder' => $meetingNotesField['placeholder'],
                ],
                'reminder' => [
                    'label' => $meetingReminderField['label'],
                ],
            ],
            'visit' => [
                'date' => [
                    'label' => $visitDateField['label'],
                    'required' => $visitDateField['required'],
                ],
                'time' => [
                    'label' => $visitTimeField['label'],
                    'required' => $visitTimeField['required'],
                ],
                'type' => [
                    'label' => $visitTypeField['label'],
                    'options' => $visitTypeField['options'],
                ],
                'project' => [
                    'label' => $visitProjectField['label'],
                    'placeholder' => $visitProjectField['placeholder'],
                ],
                'location' => [
                    'label' => $visitLocationField['label'],
                    'placeholder' => $visitLocationField['placeholder'],
                ],
                'notes' => [
                    'label' => $visitNotesField['label'],
                    'placeholder' => $visitNotesField['placeholder'],
                ],
                'reminder' => [
                    'label' => $visitReminderField['label'],
                ],
            ],
        ];
    }

    private function buildLeadDetailRequirementFields(array $forms): array
    {
        if (!$forms['requirements']) {
            return [];
        }

        return $forms['requirements']->fields
            ->sortBy('order')
            ->values()
            ->map(function ($field) {
                return [
                    'key' => $field->field_key,
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'field_label' => $field->label,
                    'type' => $field->field_type,
                    'field_type' => $field->field_type,
                    'required' => (bool) $field->required,
                    'is_required' => (bool) $field->required,
                    'options' => is_array($field->options) ? $field->options : [],
                    'dependent_field' => null,
                    'dependent_conditions' => null,
                    'placeholder' => $field->placeholder,
                    'help_text' => $field->help_text,
                    'display_order' => $field->order,
                    'section' => $field->section,
                ];
            })
            ->all();
    }

    private function serializeInterestedProjectsForForm(?Prospect $prospect): array
    {
        if (!$prospect) {
            return [];
        }

        $prospect->loadMissing('interestedProjects:id,name');

        return $prospect->interestedProjects
            ->map(function (InterestedProjectName $project) {
                return [
                    'name' => $project->name,
                    'is_custom' => true,
                ];
            })
            ->values()
            ->all();
    }

    private function decodeStoredLeadFormValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $value;
        }

        $looksJson = (
            (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))
            || (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))
        );

        if (!$looksJson) {
            return $value;
        }

        $decoded = json_decode($trimmed, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function normalizeLegacyRequirementType(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'apartment', 'flat' => 'Apartments',
            'villa', 'plot', 'plots & villas', 'plot/villa' => 'Plots & Villas',
            'commercial', 'office', 'office space' => 'Office Space',
            'shop', 'retail', 'retail shop', 'retail shops' => 'Retail Shops',
            'studio' => 'Studio',
            'farmhouse' => 'Farmhouse',
            'agricultural' => 'Agricultural',
            'other', 'others' => 'Others',
            'n.a', 'na' => 'N.A',
            default => $value ? trim($value) : null,
        };
    }

    private function inferRequirementCategoryFromType(?string $type): ?string
    {
        $normalizedType = strtolower(trim((string) $type));

        if (in_array($normalizedType, ['apartments', 'plots & villas', 'farmhouse', 'agricultural'], true)) {
            return 'Residential';
        }

        if (in_array($normalizedType, ['office space', 'retail shops', 'studio'], true)) {
            return 'Commercial';
        }

        if (in_array($normalizedType, ['others', 'n.a'], true)) {
            return 'Both';
        }

        return null;
    }

    private function normalizeLeadRequirementFormValues(Lead $lead, ?Prospect $prospect, array $existingValues): array
    {
        foreach ($existingValues as $key => $value) {
            $existingValues[$key] = $this->decodeStoredLeadFormValue($value);
        }

        if (!isset($existingValues['name']) || $existingValues['name'] === '') {
            $existingValues['name'] = $existingValues['customer_name'] ?? $prospect?->customer_name ?? $lead->name;
        }

        if (!isset($existingValues['phone']) || $existingValues['phone'] === '') {
            $existingValues['phone'] = $prospect?->phone ?: $lead->phone;
        }

        if (!isset($existingValues['meta_stage']) || $existingValues['meta_stage'] === '') {
            $existingValues['meta_stage'] = $lead->meta_stage;
        }

        if (!isset($existingValues['meta_review_note']) || $existingValues['meta_review_note'] === '') {
            $existingValues['meta_review_note'] = $lead->meta_review_note;
        }

        if (!isset($existingValues['preferred_location']) || $existingValues['preferred_location'] === '') {
            $existingValues['preferred_location'] = $existingValues['location']
                ?? ($lead->preferred_location ?: ($prospect?->preferred_location ?: null));
        }

        if (!isset($existingValues['budget']) || $existingValues['budget'] === '') {
            $existingValues['budget'] = $lead->budget ?: ($prospect?->budget ?: null);
        }

        if (!isset($existingValues['purpose']) || $existingValues['purpose'] === '') {
            $legacyPurpose = $existingValues['use_end_use'] ?? $lead->use_end_use ?? null;
            if ($legacyPurpose === 'End User') {
                $existingValues['purpose'] = 'End Use';
            } elseif ($legacyPurpose === '2nd Investments') {
                $existingValues['purpose'] = 'Short Term Investment';
            } elseif ($prospect?->purpose === 'end_user') {
                $existingValues['purpose'] = 'End Use';
            } elseif ($prospect?->purpose === 'investment') {
                $existingValues['purpose'] = 'Short Term Investment';
            }
        }

        if (!isset($existingValues['possession']) || $existingValues['possession'] === '') {
            $legacyPossession = $existingValues['possession_status'] ?? $lead->possession_status ?? $prospect?->possession;
            if ($legacyPossession) {
                $existingValues['possession'] = match (strtolower(trim((string) $legacyPossession))) {
                    'ready to move' => 'Ready To Move',
                    'under construction' => 'Under Construction',
                    'pre launch', 'pre-launch' => 'Pre Launch',
                    default => $legacyPossession,
                };
            }
        }

        if (!isset($existingValues['lead_status']) || $existingValues['lead_status'] === '') {
            $existingValues['lead_status'] = $existingValues['status'] ?? ($prospect?->lead_status ?: null);
        }

        if (!isset($existingValues['lead_quality']) || $existingValues['lead_quality'] === '') {
            $existingValues['lead_quality'] = $prospect?->lead_score
                ? (string) $prospect->lead_score
                : ($existingValues['lead_score'] ?? null);
        }

        if (!isset($existingValues['manager_remark']) || $existingValues['manager_remark'] === '') {
            $existingValues['manager_remark'] = $prospect?->manager_remark ?: ($lead->notes ?? null);
        }

        if (empty($existingValues['interested_projects'])) {
            $legacyProjects = $existingValues['preferred_projects'] ?? $lead->preferred_projects ?? null;

            if (is_string($legacyProjects)) {
                $decodedProjects = json_decode($legacyProjects, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedProjects)) {
                    $legacyProjects = $decodedProjects;
                }
            }

            if (is_array($legacyProjects) && count($legacyProjects)) {
                $existingValues['interested_projects'] = array_values(array_map(function ($project) {
                    if (is_array($project) && isset($project['name'])) {
                        return ['name' => (string) $project['name']];
                    }

                    return ['name' => (string) $project];
                }, array_filter($legacyProjects, function ($project) {
                    return $project !== null && $project !== '';
                })));
            }
        }

        if (empty($existingValues['interested_projects'])) {
            $existingValues['interested_projects'] = $this->serializeInterestedProjectsForForm($prospect);
        }

        if (!isset($existingValues['type']) || $existingValues['type'] === '') {
            $legacyType = $existingValues['property_type'] ?? $lead->property_type ?? null;
            $existingValues['type'] = $this->normalizeLegacyRequirementType($legacyType);
        } else {
            $existingValues['type'] = $this->normalizeLegacyRequirementType((string) $existingValues['type']);
        }

        if (!isset($existingValues['category']) || $existingValues['category'] === '') {
            $existingValues['category'] = $this->inferRequirementCategoryFromType($existingValues['type'] ?? null);
        }

        return $existingValues;
    }

    private function getHydratedLeadRequirementFormValues(Lead $lead, ?Prospect $prospect): array
    {
        $lead->loadMissing('formFieldValues');

        return $this->normalizeLeadRequirementFormValues(
            $lead,
            $prospect,
            $lead->getFormFieldsArray()
        );
    }

    private function resolveDashboardDateRange(Request $request): array
    {
        $today = Carbon::today();
        $dateFilter = $request->get('date_filter', 'today');

        switch ($dateFilter) {
            case 'this_week':
                $startDate = $today->copy()->startOfWeek();
                $endDate = $today->copy()->endOfWeek();
                break;
            case 'this_month':
                $startDate = $today->copy()->startOfMonth();
                $endDate = $today->copy()->endOfMonth();
                break;
            case 'custom':
                $start = $request->get('start_date');
                $end = $request->get('end_date');
                if (!$start || !$end) {
                    $dateFilter = 'today';
                    $startDate = $today->copy()->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
                }

                $startDate = Carbon::parse($start)->startOfDay();
                $endDate = Carbon::parse($end)->endOfDay();
                if ($startDate->gt($endDate)) {
                    [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
                }
                break;
            case 'today':
            default:
                $dateFilter = 'today';
                $startDate = $today->copy()->startOfDay();
                $endDate = $today->copy()->endOfDay();
                break;
        }

        return [
            'date_filter' => $dateFilter,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'target_month' => $endDate->copy()->startOfMonth(),
        ];
    }

    private function asmCnpAutomation(): AsmCnpAutomationService
    {
        return app(AsmCnpAutomationService::class);
    }

    private function usesUnifiedCnpScheduling(User $user): bool
    {
        return $user->isAssistantSalesManager() || $user->isSeniorManager();
    }

    private function overdueGraceMinutes(): int
    {
        return Task::OVERDUE_GRACE_MINUTES;
    }

    private function overdueCutoff(): Carbon
    {
        return now()->subMinutes($this->overdueGraceMinutes());
    }

    private function upcomingGraceCutoff(): Carbon
    {
        return now()->addMinutes($this->overdueGraceMinutes());
    }

    private function normalizeTaskDateTimeInput(string $value): Carbon
    {
        $timezone = config('app.timezone');
        $normalizedValue = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $normalizedValue) === 1) {
            $format = strlen($normalizedValue) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
            return Carbon::createFromFormat($format, $normalizedValue, $timezone);
        }

        return Carbon::parse($normalizedValue)->setTimezone($timezone);
    }

    private function formatCnpRetryTaskTitle(Lead $lead, Carbon $scheduledAt): string
    {
        return sprintf(
            'Retry call: %s (CNP rescheduled - %s)',
            trim((string) $lead->name),
            $scheduledAt->format('d M Y, h:i A')
        );
    }

    private function formatAsmTaskCardSubtitle(string $category, Task $task, bool $isCnpRetryTask): string
    {
        return match ($category) {
            'follow_up' => 'Follow Up',
            'meeting' => 'Meeting',
            'site_visit' => 'Site Visit',
            'prospect' => 'Prospect Verification',
            'closer' => 'Closer Task',
            'fresh_lead' => $isCnpRetryTask ? 'CNP Rescheduled' : 'Fresh Lead',
            default => $isCnpRetryTask ? 'CNP Rescheduled' : 'Task',
        };
    }

    private function applyAsmHiddenTransitionOutcomeFilter($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('outcome')
              ->orWhereNotIn('outcome', ['cnp', 'follow_up']);
        });
    }

    private function applyAsmHiddenCompletedFilter($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    private function activeLeadAssignmentCountForUsers($userIds, Carbon $startDate, Carbon $endDate): int
    {
        $ids = collect($userIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return LeadAssignment::query()
            ->whereHas('lead')
            ->where('is_active', true)
            ->whereIn('assigned_to', $ids->all())
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('lead_id')
            ->count('lead_id');
    }

    private function prospectFallbackLeadCountForTelecallers($telecallerIds, Carbon $startDate, Carbon $endDate): int
    {
        $ids = collect($telecallerIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return Lead::query()
            ->whereVisibleViaProspectFallback($ids, function ($prospectQuery) use ($startDate, $endDate) {
                $prospectQuery
                    ->whereIn('verification_status', ['verified', 'approved'])
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->count();
    }

    private function resolveLeadRemarkForUi(Lead $lead): string
    {
        $formValues = $lead->relationLoaded('formFieldValues')
            ? $lead->formFieldValues->pluck('field_value', 'field_key')->toArray()
            : [];

        $candidates = [
            $lead->manager_remark ?? null,
            $lead->remark ?? null,
            $lead->notes ?? null,
            $lead->requirements ?? null,
            $formValues['manager_remark'] ?? null,
            $formValues['remark'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return 'No remark added';
    }

    private function getFavoriteLeadPayload(User $user, ?int $limit = 5): array
    {
        $favoritesQuery = LeadFavorite::query()
            ->where('user_id', $user->id)
            ->with([
                'lead' => function ($query) {
                    $query->select([
                        'id',
                        'name',
                        'phone',
                        'status',
                        'created_at',
                        'updated_at',
                        'notes',
                        'requirements',
                    ])->with('formFieldValues:lead_id,field_key,field_value');
                },
            ])
            ->latest();

        if ($limit !== null) {
            $favoritesQuery->take(max(1, $limit));
        }

        $favorites = $favoritesQuery->get();

        return $favorites
            ->filter(fn ($favorite) => $favorite->lead !== null)
            ->map(function ($favorite) {
                $lead = $favorite->lead;

                return [
                    'lead_id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'status' => $lead->status,
                    'remark' => $this->resolveLeadRemarkForUi($lead),
                    'is_favorite' => true,
                    'favorited_at' => optional($favorite->created_at)->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    private function applySubordinateProspectVerificationScope($query, User $user, $teamMemberIds = null)
    {
        $teamMemberIds = $teamMemberIds instanceof \Illuminate\Support\Collection
            ? $teamMemberIds
            : collect($teamMemberIds ?? []);

        return $query->where(function ($q) use ($user, $teamMemberIds) {
            if ($teamMemberIds->isNotEmpty()) {
                $q->whereIn('telecaller_id', $teamMemberIds);
            }

            $q->orWhereHas('telecaller', function ($telecallerQuery) use ($user) {
                $telecallerQuery->where('manager_id', $user->id);
            });
        });
    }

    private function prospectRequiresManagerVerification(?Prospect $prospect, User $user, $teamMemberIds = null): bool
    {
        if (!$prospect) {
            return false;
        }

        if (!in_array($prospect->verification_status ?? '', ['pending', 'pending_verification'], true)) {
            return false;
        }

        if (!$prospect->telecaller_id) {
            return false;
        }

        $teamMemberIds = $teamMemberIds instanceof \Illuminate\Support\Collection
            ? $teamMemberIds
            : collect($teamMemberIds ?? []);

        if ($teamMemberIds->isNotEmpty() && $teamMemberIds->contains((int) $prospect->telecaller_id)) {
            return true;
        }

        if (!$prospect->relationLoaded('telecaller')) {
            $prospect->load('telecaller');
        }

        return (int) ($prospect->telecaller->manager_id ?? 0) === (int) $user->id;
    }

    /**
     * Get profile data with team members
     */
    public function getProfile(Request $request)
    {
        // Get user from request (works with Sanctum)
        $user = $request->user();
        $dashboardRange = $this->resolveDashboardDateRange($request);
        $startDate = $dashboardRange['start_date'];
        $endDate = $dashboardRange['end_date'];
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        $user->load('role', 'manager', 'salesManagerProfile', 'userProfile');
        
        // Log for debugging
        \Log::info('Senior Manager getProfile - User info', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_name' => $user->name,
        ]);
        
        // Get team members (telecallers and sales executives under this manager)
        // Include all users where manager_id matches, regardless of role
        $teamMembersQuery = User::where('manager_id', $user->id)
            ->with(['role', 'telecallerProfile', 'userProfile'])
            ->orderBy('name');
        
        // Log raw query for debugging
        \Log::info('Senior Manager getProfile - Team members query', [
            'manager_id' => $user->id,
            'raw_sql' => $teamMembersQuery->toSql(),
            'bindings' => $teamMembersQuery->getBindings(),
        ]);
        
        $teamMembers = $teamMembersQuery->get()->map(function($member) use ($startDate, $endDate) {
                // Get filtered stats for the team member
                $todayProspects = Prospect::where('telecaller_id', $member->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();
                
                $isAbsent = $member->userProfile?->isCurrentlyAbsent() ?? false;
                $absentReason = $member->userProfile?->absent_reason ?? null;
                
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone' => $member->phone,
                    'role' => $member->role->name ?? '-',
                    'profile_picture' => $member->profile_picture_url,
                    'is_active' => $member->is_active,
                    'is_absent' => $isAbsent,
                    'absent_reason' => $absentReason,
                    'absent_until' => $member->userProfile?->leadOffEndsAt(),
                    'joined_at' => $member->created_at ? $member->created_at->format('d M Y') : '-',
                    'today_prospects' => $todayProspects,
                ];
            });
        
        // Log team members found
        \Log::info('Senior Manager getProfile - Team members found', [
            'manager_id' => $user->id,
            'team_members_count' => $teamMembers->count(),
            'team_member_ids' => $teamMembers->pluck('id')->toArray(),
            'team_member_names' => $teamMembers->pluck('name')->toArray(),
        ]);
        
        // Get activity history (last 10 activities)
        $activityHistory = ActivityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['action', 'ip_address', 'created_at']);

        $teamMemberIds = $teamMembers->pluck('id');
        $pendingVerifications = 0;
        
        // Log for debugging
        \Log::info('Senior Manager getProfile - Pending verifications', [
            'manager_id' => $user->id,
            'manager_email' => $user->email,
            'team_member_ids' => $teamMemberIds->toArray(),
            'team_member_count' => $teamMemberIds->count(),
            'pending_verifications' => $pendingVerifications,
        ]);
        
        // Get assigned leads count for this sales manager
        // Include leads assigned to manager and all team members
        $teamMemberIds = $user->teamMembers()->pluck('id');
        $allUserIds = $teamMemberIds->merge([$user->id])->toArray();
        
        $assignedLeadsCount =
            $this->activeLeadAssignmentCountForUsers($allUserIds, $startDate, $endDate) +
            $this->prospectFallbackLeadCountForTelecallers($teamMemberIds, $startDate, $endDate);
        
        // Log for debugging
        \Log::info('Senior Manager Lead Count Debug', [
            'user_id' => $user->id,
            'team_member_ids' => $teamMemberIds->toArray(),
            'all_user_ids' => $allUserIds,
            'total_assigned_leads' => $assignedLeadsCount,
        ]);
        
        // Get pending tasks count for this sales manager.
        // Pending should exclude overdue items so the dashboard split stays exclusive.
        $overdueCutoff = $this->overdueCutoff();
        $upcomingGraceCutoff = $this->upcomingGraceCutoff();

        $pendingTasksQuery = Task::where('assigned_to', $user->id)
            ->whereHas('lead', function ($leadQuery) use ($user) {
                $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                    $assignmentQuery->where('assigned_to', $user->id)
                        ->where('is_active', true);
                });
            })
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->where(function($q) use ($overdueCutoff, $upcomingGraceCutoff) {
                // Include:
                // 1. Normal pending tasks (not CNP, not overdue)
                // 2. CNP tasks scheduled within the grace window
                $q->where(function($normalQ) use ($overdueCutoff) {
                    // Normal pending tasks (not CNP, not overdue)
                    $normalQ->where('notes', 'not like', '%CNP retry task created%')
                            ->where('title', 'not like', '%CNP rescheduled%')
                            ->where('description', 'not like', '%previous call not picked%')
                            ->where('scheduled_at', '>=', $overdueCutoff);
                })
                ->orWhere(function($cnpQ) use ($upcomingGraceCutoff, $overdueCutoff) {
                    // CNP tasks scheduled within the grace window (and not overdue)
                    $cnpQ->where(function($cnpMarkers) {
                        $cnpMarkers->where('notes', 'like', '%CNP retry task created%')
                                   ->orWhere('title', 'like', '%CNP rescheduled%')
                                   ->orWhere('description', 'like', '%previous call not picked%');
                    })
                    ->where('scheduled_at', '<=', $upcomingGraceCutoff)
                    ->where('scheduled_at', '>=', $overdueCutoff);
                });
            });
        
        // Apply same deduplication logic as getTasks (one task per lead_id)
        $allPendingTasks = $pendingTasksQuery->get();
        $deduplicatedPendingTasks = $allPendingTasks->groupBy('lead_id')
            ->map(function($group) {
                // Sort by priority (lower number = higher priority) and then prefer the
                // latest scheduled open task for that lead so stale older tasks do not
                // mask the newest actionable retry task.
                return $group->sortBy(function($task) {
                    $priority = [
                        'pending' => 1,
                        'in_progress' => 2,
                        'completed' => 3,
                        'cancelled' => 4
                    ];
                    $priorityValue = $priority[$task->status] ?? 5;
                    $scheduledAt = $task->scheduled_at ? -$task->scheduled_at->timestamp : PHP_INT_MAX;
                    $taskId = -((int) $task->id);
                    return [$priorityValue, $scheduledAt, $taskId];
                })->first();
            });
        
        $pendingTasksCount = $deduplicatedPendingTasks->count();
        
        // Get overdue tasks count using the shared grace cutoff.
        $overdueTasksCount = Task::where('assigned_to', $user->id)
            ->whereHas('lead', function ($leadQuery) use ($user) {
                $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                    $assignmentQuery->where('assigned_to', $user->id)
                        ->where('is_active', true);
                });
            })
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('scheduled_at', '<', $overdueCutoff)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->count();

        // Hero counters (ASM scope only: logged-in user)
        $freshLeadsTodayCount = LeadAssignment::where('assigned_to', $user->id)
            ->whereHas('lead')
            ->where('is_active', true)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct()
            ->count('lead_id');

        $todayMeetingsCount = Meeting::where('assigned_to', $user->id)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('status', 'scheduled')
            ->count();

        $todayVisitsCount = SiteVisit::where('assigned_to', $user->id)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('status', 'scheduled')
            ->count();

        $todayFollowUpsCount = FollowUp::where('created_by', $user->id)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->where('status', 'scheduled')
            ->count();

        $previousDayStart = now()->subDay()->startOfDay();
        $previousDayEnd = now()->subDay()->endOfDay();
        $previousDayOverdueTasksCount = Task::where('assigned_to', $user->id)
            ->whereHas('lead', function ($leadQuery) use ($user) {
                $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                    $assignmentQuery->where('assigned_to', $user->id)
                        ->where('is_active', true);
                });
            })
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereBetween('scheduled_at', [$previousDayStart, $previousDayEnd])
            ->count();

        // Log for debugging
        \Log::info('Senior Manager pending tasks count', [
            'user_id' => $user->id,
            'pending_tasks_count' => $pendingTasksCount,
            'overdue_tasks_count' => $overdueTasksCount,
            'previous_day_overdue_tasks_count' => $previousDayOverdueTasksCount,
            'tasks_before_dedup' => $allPendingTasks->count(),
            'tasks_after_dedup' => $pendingTasksCount,
            'overdue_cutoff' => $overdueCutoff->format('Y-m-d H:i:s'),
        ]);
        
        // Get team stats
        $teamStats = [
            'total_members' => $teamMembers->count(),
            'active_members' => $teamMembers->where('is_active', true)->count(),
            'available_members' => $teamMembers->filter(function($member) {
                return !($member['is_absent'] ?? false);
            })->count(),
            'today_prospects' => $teamMembers->sum('today_prospects'),
            'pending_verifications' => $pendingVerifications,
            'assigned_leads' => $assignedLeadsCount,
            'pending_tasks' => $pendingTasksCount,
            'overdue_tasks' => $overdueTasksCount,
            'previous_day_overdue_tasks' => $previousDayOverdueTasksCount,
            'fresh_leads_today' => $freshLeadsTodayCount,
            'today_meetings_count' => $todayMeetingsCount,
            'today_visits_count' => $todayVisitsCount,
            'today_followups_count' => $todayFollowUpsCount,
        ];
        $favoriteLeads = $this->getFavoriteLeadPayload($user, 5);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_picture' => $user->profile_picture_url,
                'role' => $user->role->name ?? 'Senior Manager',
                'manager' => $user->manager ? $user->manager->name : null,
                'created_at' => $user->created_at ? $user->created_at->format('d M Y') : '-',
            ],
            'profile' => [
                'lead_off_supported' => in_array($user->role->slug ?? '', [
                    \App\Models\Role::SALES_MANAGER,
                    \App\Models\Role::ASSISTANT_SALES_MANAGER,
                ], true),
                'is_absent' => $user->userProfile?->isCurrentlyAbsent() ?? false,
                'lead_off_enabled' => (bool) ($user->userProfile?->is_absent ?? false),
                'has_scheduled_lead_off' => $user->userProfile?->hasUpcomingLeadOffWindow() ?? false,
                'absent_reason' => $user->userProfile?->absent_reason,
                'absent_until' => $user->userProfile?->leadOffEndsAt()?->format('Y-m-d H:i:s'),
                'lead_off_start_at' => $user->userProfile?->lead_off_start_at?->format('Y-m-d H:i:s'),
                'lead_off_end_at' => $user->userProfile?->leadOffEndsAt()?->format('Y-m-d H:i:s'),
                'lead_off_source' => $user->userProfile?->lead_off_source,
            ],
            'team_members' => $teamMembers,
            'team_stats' => $teamStats,
            'favorite_leads' => $favoriteLeads,
            'favorite_leads_count' => count($favoriteLeads),
            'dashboard_filter' => [
                'date_filter' => $dashboardRange['date_filter'],
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'activity_history' => $activityHistory->map(function ($log) {
                return [
                    'action' => $log->action,
                    'ip' => $log->ip_address,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    public function getDashboardSettings(Request $request)
    {
        $user = $request->user();

        if (!$user || (!$user->isAssistantSalesManager() && !$user->isSeniorManager())) {
            return response()->json([
                'success' => false,
                'message' => 'Only Assistant Sales Managers and Senior Managers can access dashboard settings.',
            ], 403);
        }

        $profile = SalesManagerProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['preferences' => []]
        );

        return response()->json([
            'success' => true,
            'dashboard_visibility' => $this->normalizedDashboardVisibility($profile->preferences),
        ]);
    }

    public function updateDashboardSettings(Request $request)
    {
        $user = $request->user();

        if (!$user || (!$user->isAssistantSalesManager() && !$user->isSeniorManager())) {
            return response()->json([
                'success' => false,
                'message' => 'Only Assistant Sales Managers and Senior Managers can update dashboard settings.',
            ], 403);
        }

        $validated = $request->validate([
            'dashboard_visibility' => ['required', 'array'],
        ]);
        $submitted = $validated['dashboard_visibility'];

        $profile = SalesManagerProfile::firstOrCreate(['user_id' => $user->id], ['preferences' => []]);
        $preferences = is_array($profile->preferences) ? $profile->preferences : [];
        $saved = is_array($preferences['dashboard_visibility'] ?? null)
            ? $preferences['dashboard_visibility']
            : [];
        $merged = array_merge($this->defaultDashboardVisibility(), $saved);

        foreach (array_keys($this->defaultDashboardVisibility()) as $key) {
            if (array_key_exists($key, $submitted)) {
                $value = filter_var($submitted[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $merged[$key] = $value === null ? (bool) $submitted[$key] : $value;
            }
        }

        $preferences['dashboard_visibility'] = $merged;
        $profile->preferences = $preferences;
        $profile->save();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard settings updated successfully.',
            'dashboard_visibility' => $this->normalizedDashboardVisibility($profile->preferences),
        ]);
    }

    /**
     * Update profile (name, email, phone)
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        $user = $user->fresh(['role', 'manager']);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->name ?? 'Senior Manager',
                'manager' => $user->manager ? $user->manager->name : 'Not Assigned',
                'created_at' => $user->created_at ? $user->created_at->format('d M Y') : '-',
            ],
        ]);
    }

    public function updateAvailability(Request $request, UserStatusService $userStatusService)
    {
        $validator = Validator::make($request->all(), [
            'is_absent' => 'required|boolean',
            'absent_reason' => 'nullable|string|max:255',
            'mode' => 'nullable|in:normal,now,schedule,on',
            'absent_until' => 'nullable|date',
            'lead_off_start_at' => 'nullable|date',
            'lead_off_end_at' => 'nullable|date',
        ]);

        $validator->after(function ($validator) use ($request) {
            $isAbsent = $request->boolean('is_absent');
            $mode = $request->input('mode');
            $leadOffStartAt = $request->filled('lead_off_start_at') ? Carbon::parse($request->input('lead_off_start_at')) : null;
            $leadOffEndAt = $request->filled('lead_off_end_at') ? Carbon::parse($request->input('lead_off_end_at')) : null;
            $absentUntil = $request->filled('absent_until') ? Carbon::parse($request->input('absent_until')) : null;
            $now = now();

            if (!$isAbsent || $mode === 'on') {
                return;
            }

            if ($mode === 'schedule') {
                if (!$leadOffStartAt) {
                    $validator->errors()->add('lead_off_start_at', 'Lead Off From is required for a scheduled window.');
                }
                if (!$leadOffEndAt) {
                    $validator->errors()->add('lead_off_end_at', 'Lead Off Until is required for a scheduled window.');
                }
                if ($leadOffStartAt && $leadOffStartAt->lte($now)) {
                    $validator->errors()->add('lead_off_start_at', 'Lead Off From must be a future time for a scheduled window.');
                }
                if ($leadOffStartAt && $leadOffEndAt && $leadOffEndAt->lte($leadOffStartAt)) {
                    $validator->errors()->add('lead_off_end_at', 'Scheduled end time must be after start time.');
                }
                return;
            }

            $effectiveEndAt = $leadOffEndAt ?? $absentUntil;
            if ($effectiveEndAt && $effectiveEndAt->lte($now)) {
                $validator->errors()->add('absent_until', 'Lead Off Until must be after now.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all())->first() ?: 'Please correct the highlighted errors.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $allowedRoles = [
            \App\Models\Role::SALES_MANAGER,
            \App\Models\Role::ASSISTANT_SALES_MANAGER,
        ];

        if (!in_array($user->role->slug ?? '', $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Lead off mode is only available for lead-receiving users.',
            ], 403);
        }

        $leadOffStartAt = $request->lead_off_start_at ? Carbon::parse($request->lead_off_start_at) : null;
        $leadOffEndAt = $request->lead_off_end_at ? Carbon::parse($request->lead_off_end_at) : null;
        $absentUntil = $request->absent_until ? Carbon::parse($request->absent_until) : $leadOffEndAt;

        if ($request->boolean('is_absent') && !$leadOffStartAt) {
            $leadOffStartAt = now();
        }

        $profile = $userStatusService->toggleAbsentStatus(
            $user->id,
            $request->boolean('is_absent'),
            $request->absent_reason,
            $absentUntil,
            $leadOffStartAt,
            $leadOffEndAt,
            'self',
            $user->id
        )->fresh();

        return response()->json([
            'success' => true,
            'message' => $request->is_absent ? 'Lead off mode updated successfully' : 'Lead off mode disabled successfully',
            'profile' => [
                'lead_off_supported' => true,
                'is_absent' => $profile->isCurrentlyAbsent(),
                'lead_off_enabled' => (bool) $profile->is_absent,
                'has_scheduled_lead_off' => $profile->hasUpcomingLeadOffWindow(),
                'absent_reason' => $profile->absent_reason,
                'absent_until' => $profile->leadOffEndsAt()?->format('Y-m-d H:i:s'),
                'lead_off_start_at' => $profile->lead_off_start_at?->format('Y-m-d H:i:s'),
                'lead_off_end_at' => $profile->leadOffEndsAt()?->format('Y-m-d H:i:s'),
                'lead_off_source' => $profile->lead_off_source,
            ],
        ]);
    }

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture(Request $request)
    {
        try {
            $request->validate([
                'profile_picture' => 'required|image|mimes:jpeg,jpg,png|max:2048', // Max 2MB
            ]);

            $user = $request->user();

            // Delete old profile picture if exists
            if ($user->profile_picture) {
                $oldPath = $user->profile_picture;
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Store new profile picture
            $file = $request->file('profile_picture');
            $filename = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('profiles', $filename, 'public');

            // Update user profile picture
            $user->update([
                'profile_picture' => $path,
            ]);

            // Refresh to get updated URL
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'profile_picture' => $user->profile_picture_url,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);
        $user->clearPasswordChangeRequirement();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Get team member details
     */
    public function getTeamMemberDetails(Request $request, $memberId)
    {
        $manager = $request->user();
        
        $member = User::where('id', $memberId)
            ->where('manager_id', $manager->id)
            ->with(['role', 'telecallerProfile', 'userProfile'])
            ->firstOrFail();

        // Get member's performance stats
        $todayProspects = Prospect::where('telecaller_id', $member->id)
            ->whereDate('created_at', Carbon::today())
            ->count();
        
        $weekProspects = Prospect::where('telecaller_id', $member->id)
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();
        
        $monthProspects = Prospect::where('telecaller_id', $member->id)
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();

        return response()->json([
            'success' => true,
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'role' => $member->role->name,
                'profile_picture' => $member->profile_picture_url,
                'is_active' => $member->is_active,
                'is_absent' => $member->userProfile?->isCurrentlyAbsent() ?? false,
                'absent_reason' => $member->userProfile?->absent_reason ?? null,
                'absent_until' => $member->userProfile?->leadOffEndsAt() ?? null,
                'performance' => [
                    'today_prospects' => $todayProspects,
                    'week_prospects' => $weekProspects,
                    'month_prospects' => $monthProspects,
                ],
            ],
        ]);
    }

    /**
     * Get team performance overview
     */
    public function getTeamPerformance(Request $request)
    {
        $manager = $request->user();
        
        // Get all team members
        $teamMembers = User::where('manager_id', $manager->id)->pluck('id');
        
        // Get prospects created by team today
        $todayProspects = Prospect::whereIn('telecaller_id', $teamMembers)
            ->whereDate('created_at', Carbon::today())
            ->count();
        
        // Get prospects created by team this week
        $weekProspects = Prospect::whereIn('telecaller_id', $teamMembers)
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();
        
        // Get prospects created by team this month
        $monthProspects = Prospect::whereIn('telecaller_id', $teamMembers)
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();
        
        // Get top performers
        $topPerformers = Prospect::whereIn('telecaller_id', $teamMembers)
            ->whereMonth('created_at', Carbon::now()->month)
            ->selectRaw('telecaller_id, COUNT(*) as prospect_count')
            ->groupBy('telecaller_id')
            ->orderByDesc('prospect_count')
            ->limit(5)
            ->get()
            ->map(function($item) {
                $user = User::find($item->telecaller_id);
                return [
                    'name' => $user ? $user->name : 'Unknown',
                    'prospect_count' => $item->prospect_count,
                ];
            });

        return response()->json([
            'success' => true,
            'performance' => [
                'today_prospects' => $todayProspects,
                'week_prospects' => $weekProspects,
                'month_prospects' => $monthProspects,
                'top_performers' => $topPerformers,
            ],
        ]);
    }

    /**
     * Senior Manager team dashboard, scoped only to direct active team members.
     */
    public function getDashboardTeamOverview(Request $request)
    {
        $manager = $request->user();

        if (!$manager) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$request->has('date_filter')) {
            $request->merge(['date_filter' => 'this_month']);
        }

        $range = $this->resolveDashboardDateRange($request);
        $startDate = $range['start_date'];
        $endDate = $range['end_date'];

        $teamMembers = User::query()
            ->with(['role', 'userProfile.leadOffFallbackUser'])
            ->where('manager_id', $manager->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $previousOverdueCounts = $teamMembers->mapWithKeys(fn (User $member) => [
            $member->id => $this->previousOverdueItemsForMember($member)->count(),
        ]);
        $todayOverdueCounts = $this->todayOverdueCountsForTeam($teamMembers->pluck('id'));

        $responseRows = $teamMembers->map(function (User $member) use ($startDate, $endDate, $previousOverdueCounts, $todayOverdueCounts) {
            $newLeadsNotCompleted = LeadAssignment::query()
                ->whereHas('lead', function ($query) {
                    $query->whereIn('status', ['new', Lead::STATUS_FRESH_TRANSFER]);
                })
                ->where('is_active', true)
                ->where('assigned_to', $member->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->distinct('lead_id')
                ->count('lead_id');

            $avgSeconds = $this->averageTeamLeadResponseSeconds($member, $startDate, $endDate);
            return [
                'user_id' => $member->id,
                'user_name' => $member->name,
                'role' => optional($member->role)->name,
                'new_leads_not_completed' => $newLeadsNotCompleted,
                'avg_response_time' => $this->formatTeamDashboardDuration($avgSeconds),
                'previous_overdue' => (int) ($previousOverdueCounts[$member->id] ?? 0),
                'today_overdue' => (int) ($todayOverdueCounts[$member->id] ?? 0),
            ];
        })->values();

        $performanceRows = $teamMembers->map(function (User $member) use ($startDate, $endDate) {
            $baseLeadQuery = Lead::query()
                ->whereHas('activeAssignments', function ($query) use ($member, $startDate, $endDate) {
                    $query->where('assigned_to', $member->id)
                        ->where('is_active', true)
                        ->whereBetween('created_at', [$startDate, $endDate]);
                });

            $statusCount = function (array $statuses) use ($baseLeadQuery): int {
                return (clone $baseLeadQuery)->whereIn('status', $statuses)->count();
            };

            $assigned = LeadAssignment::query()
                ->whereHas('lead')
                ->where('is_active', true)
                ->where('assigned_to', $member->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->distinct('lead_id')
                ->count('lead_id');

            $fresh = $statusCount(['new', Lead::STATUS_FRESH_TRANSFER]);
            $followUp = $statusCount(['connected', 'on_hold']);
            $meeting = $statusCount(['meeting_scheduled', 'meeting_completed']);
            $visit = $statusCount(['visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed']);
            $closer = $statusCount(['closed']);
            $junk = $statusCount(['junk', 'dead']);
            $notInterested = $statusCount(['not_interested']);
            $stageBreakdown = (clone $baseLeadQuery)
                ->selectRaw("COALESCE(NULLIF(status, ''), 'unknown') as stage, COUNT(*) as total")
                ->groupBy('stage')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row) => [
                    'stage' => (string) $row->stage,
                    'label' => ucwords(str_replace('_', ' ', (string) $row->stage)),
                    'count' => (int) $row->total,
                ])
                ->values();

            return [
                'user_id' => $member->id,
                'user_name' => $member->name,
                'assigned' => $assigned,
                'fresh' => $fresh,
                'follow_up' => $followUp,
                'meeting' => $meeting,
                'visit' => $visit,
                'closer' => $closer,
                'junk' => $junk,
                'not_interested' => $notInterested,
                'stage_breakdown' => $stageBreakdown,
            ];
        })->values();

        $temperatureRows = $teamMembers->map(function (User $member) use ($startDate, $endDate) {
            $baseLeadQuery = Lead::query()
                ->whereHas('activeAssignments', function ($query) use ($member, $startDate, $endDate) {
                    $query->where('assigned_to', $member->id)
                        ->where('is_active', true)
                        ->whereBetween('created_at', [$startDate, $endDate]);
                });

            $temperatureCounts = $this->leadTemperatureCountsForLeadIds(
                (clone $baseLeadQuery)->pluck('leads.id')
            );

            return [
                'user_id' => $member->id,
                'user_name' => $member->name,
                'hot' => $temperatureCounts['hot'],
                'warm' => $temperatureCounts['warm'],
                'cold' => $temperatureCounts['cold'],
                'other' => $temperatureCounts['other'],
                'total' => $temperatureCounts['total'],
            ];
        })->values();

        $teamMemberIds = $teamMembers->pluck('id');
        $sourceRows = LeadAssignment::query()
            ->join('leads', 'leads.id', '=', 'lead_assignments.lead_id')
            ->whereIn('lead_assignments.assigned_to', $teamMemberIds)
            ->where('lead_assignments.is_active', true)
            ->whereBetween('lead_assignments.created_at', [$startDate, $endDate])
            ->selectRaw("COALESCE(NULLIF(leads.source, ''), 'other') as source, COUNT(DISTINCT lead_assignments.lead_id) as total")
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'source' => (string) $row->source,
                'label' => Lead::displaySourceLabel($row->source),
                'count' => (int) $row->total,
            ])
            ->values();
        $leadOffRows = $teamMembers->map(fn (User $member) => [
            'user_id' => $member->id,
            'user_name' => $member->name,
            'role' => optional($member->role)->name,
            'is_lead_off' => $member->userProfile?->isCurrentlyAbsent() ?? false,
            'lead_off_enabled' => ($member->userProfile?->isCurrentlyAbsent() ?? false)
                || ($member->userProfile?->hasUpcomingLeadOffWindow() ?? false),
            'has_scheduled_lead_off' => $member->userProfile?->hasUpcomingLeadOffWindow() ?? false,
            'fallback_user_id' => $member->userProfile?->lead_off_fallback_user_id,
            'fallback_user_name' => $member->userProfile?->leadOffFallbackUser?->name,
            'lead_off_reason' => $member->userProfile?->absent_reason,
            'lead_off_start_at' => $member->userProfile?->lead_off_start_at?->format('Y-m-d H:i:s'),
            'lead_off_end_at' => $member->userProfile?->leadOffEndsAt()?->format('Y-m-d H:i:s'),
        ])->values();

        return response()->json([
            'success' => true,
            'range' => [
                'date_filter' => $range['date_filter'],
                'label' => $this->formatDashboardRangeLabel($range['date_filter'], $startDate, $endDate),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'team_count' => $teamMembers->count(),
            'response_rows' => $responseRows,
            'performance_rows' => $performanceRows,
            'temperature_rows' => $temperatureRows,
            'source_rows' => $sourceRows,
            'lead_off_rows' => $leadOffRows,
        ]);
    }

    /**
     * A senior manager can control lead availability only for direct team members.
     */
    public function updateDashboardTeamMemberLeadAvailability(Request $request, User $member, UserStatusService $userStatusService)
    {
        $manager = $request->user();

        if (!$manager || (int) $member->manager_id !== (int) $manager->id || !$member->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found.',
            ], 404);
        }

        $validated = $request->validate([
            'is_lead_off' => ['required', 'boolean'],
            'fallback_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'duration_mode' => ['nullable', 'in:manual,window'],
            'lead_off_start_at' => ['nullable', 'date'],
            'lead_off_end_at' => ['nullable', 'date', 'after:lead_off_start_at'],
        ]);
        $isLeadOff = (bool) $validated['is_lead_off'];
        $durationMode = $validated['duration_mode'] ?? 'manual';
        $leadOffStartAt = null;
        $leadOffEndAt = null;

        if ($isLeadOff) {
            if (empty($validated['reason'])) {
                return response()->json(['success' => false, 'message' => 'Lead Off reason is required for audit.'], 422);
            }

            $fallback = User::query()
                ->where('id', $validated['fallback_user_id'] ?? null)
                ->where('manager_id', $manager->id)
                ->where('is_active', true)
                ->first();

            if (!$fallback || (int) $fallback->id === (int) $member->id) {
                return response()->json(['success' => false, 'message' => 'Select another active direct team member as fallback.'], 422);
            }

            if (!$userStatusService->canUserReceiveLeads($fallback->id)) {
                return response()->json(['success' => false, 'message' => 'Selected fallback user is currently Lead Off.'], 422);
            }

            if ($durationMode === 'window') {
                if (empty($validated['lead_off_start_at']) || empty($validated['lead_off_end_at'])) {
                    return response()->json(['success' => false, 'message' => 'Lead Off From and To are required.'], 422);
                }

                $leadOffStartAt = Carbon::parse($validated['lead_off_start_at']);
                $leadOffEndAt = Carbon::parse($validated['lead_off_end_at']);
                if ($leadOffEndAt->lte(now())) {
                    return response()->json(['success' => false, 'message' => 'Lead Off To must be in the future.'], 422);
                }
            }
        }

        $profile = $userStatusService->toggleAbsentStatus(
            $member->id,
            $isLeadOff,
            $isLeadOff ? $validated['reason'] : null,
            $leadOffEndAt,
            $isLeadOff ? ($leadOffStartAt ?? now()) : null,
            $leadOffEndAt,
            'manager',
            $manager->id,
            $isLeadOff ? (int) $validated['fallback_user_id'] : null
        )->fresh();

        return response()->json([
            'success' => true,
            'message' => $isLeadOff ? 'Lead Off enabled.' : 'Lead On enabled.',
            'member' => [
                'user_id' => $member->id,
                'is_lead_off' => $profile->isCurrentlyAbsent(),
                'lead_off_enabled' => $profile->isCurrentlyAbsent() || $profile->hasUpcomingLeadOffWindow(),
                'has_scheduled_lead_off' => $profile->hasUpcomingLeadOffWindow(),
                'fallback_user_id' => $profile->lead_off_fallback_user_id,
                'fallback_user_name' => $profile->leadOffFallbackUser?->name,
                'lead_off_reason' => $profile->absent_reason,
                'lead_off_start_at' => $profile->lead_off_start_at?->format('Y-m-d H:i:s'),
                'lead_off_end_at' => $profile->leadOffEndsAt()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Today-only work detail for one direct team member.
     */
    public function getDashboardTeamMemberWorkload(Request $request, User $member)
    {
        $manager = $request->user();

        if (!$manager || (int) $member->manager_id !== (int) $manager->id || !$member->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found.',
            ], 404);
        }

        $previousOverdueItems = $this->previousOverdueItemsForMember($member);
        $overdueItems = $this->todayOverdueItemsForMember($member);

        return response()->json([
            'success' => true,
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'role' => optional($member->role)->name,
            ],
            'previous_overdue' => [
                'count' => $previousOverdueItems->count(),
                'items' => $previousOverdueItems->values(),
            ],
            'today_overdue' => [
                'count' => $overdueItems->count(),
                'items' => $overdueItems->values(),
            ],
        ]);
    }

    /**
     * Fresh/new leads assigned to one active direct team member for the selected dashboard range.
     */
    public function getDashboardTeamMemberFreshLeads(Request $request, User $member)
    {
        $manager = $request->user();

        if (!$manager || (int) $member->manager_id !== (int) $manager->id || !$member->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found.',
            ], 404);
        }

        if (!$request->has('date_filter')) {
            $request->merge(['date_filter' => 'this_month']);
        }

        $range = $this->resolveDashboardDateRange($request);
        $query = Lead::query()
            ->whereIn('status', ['new', Lead::STATUS_FRESH_TRANSFER])
            ->whereHas('activeAssignments', function ($assignmentQuery) use ($member, $range) {
                $assignmentQuery->where('assigned_to', $member->id)
                    ->where('is_active', true)
                    ->whereBetween('created_at', [$range['start_date'], $range['end_date']]);
            });
        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(250)
            ->get(['id', 'name', 'phone', 'status'])
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name ?: 'Unnamed lead',
                'phone' => $lead->phone ?: 'Not provided',
                'status' => ucwords(str_replace('_', ' ', (string) $lead->status)),
                'view_url' => url('/leads/' . $lead->id),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'member' => ['id' => $member->id, 'name' => $member->name],
            'total' => $total,
            'showing' => $items->count(),
            'items' => $items,
        ]);
    }

    private function previousOverdueItemsForMember(User $member)
    {
        return Task::query()
            ->with('lead:id,name,phone')
            ->where('assigned_to', $member->id)
            ->where('type', 'phone_call')
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', now()->startOfDay())
            ->whereHas('lead', function ($leadQuery) use ($member) {
                $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($member) {
                    $assignmentQuery->where('assigned_to', $member->id)
                        ->where('is_active', true);
                });
            })
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get(['id', 'lead_id', 'title', 'status', 'scheduled_at'])
            ->filter(fn (Task $task) => $task->lead !== null)
            ->groupBy(fn (Task $task) => $task->lead_id ?: 'task-' . $task->id)
            ->map(function ($tasks) {
                $task = $tasks->sortBy(function (Task $item) {
                    $priority = ['pending' => 1, 'in_progress' => 2, 'rescheduled' => 3];

                    return [$priority[$item->status] ?? 99, $item->scheduled_at?->timestamp ?? PHP_INT_MAX, $item->id];
                })->first();

                return [
                    'type' => 'Call Task',
                    'customer' => $task->lead->name ?: 'Unknown lead',
                    'title' => $task->title,
                    'scheduled_at' => $task->scheduled_at?->format('d M, h:i A'),
                    'status' => $task->status,
                    'view_url' => url('/leads/' . $task->lead_id),
                ];
            })
            ->values();
    }

    private function todayOverdueCountsForTeam($memberIds): array
    {
        $ids = collect($memberIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $cutoff = now()->subMinutes(Task::OVERDUE_GRACE_MINUTES);
        $taskCounts = Task::query()
            ->whereIn('assigned_to', $ids)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereDate('scheduled_at', today())
            ->where('scheduled_at', '<', $cutoff)
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');
        $telecallerCounts = TelecallerTask::query()
            ->whereIn('assigned_to', $ids)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('scheduled_at', today())
            ->where('scheduled_at', '<', $cutoff)
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        return $ids->mapWithKeys(fn ($id) => [$id => (int) ($taskCounts[$id] ?? 0) + (int) ($telecallerCounts[$id] ?? 0)])->all();
    }

    private function todayOverdueItemsForMember(User $member)
    {
        $cutoff = now()->subMinutes(Task::OVERDUE_GRACE_MINUTES);
        $taskItems = Task::query()
            ->with('lead:id,name,phone')
            ->where('assigned_to', $member->id)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereDate('scheduled_at', today())
            ->where('scheduled_at', '<', $cutoff)
            ->get(['id', 'lead_id', 'title', 'type', 'status', 'scheduled_at'])
            ->map(fn (Task $task) => [
                'type' => ucwords(str_replace('_', ' ', $task->type ?: 'Task')),
                'customer' => $task->lead?->name ?: 'Unknown lead',
                'title' => $task->title,
                'scheduled_at' => $task->scheduled_at?->format('d M, h:i A'),
                'status' => $task->status,
                'view_url' => $task->lead_id ? url('/leads/' . $task->lead_id) : null,
            ]);
        $telecallerItems = TelecallerTask::query()
            ->with('lead:id,name,phone')
            ->where('assigned_to', $member->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('scheduled_at', today())
            ->where('scheduled_at', '<', $cutoff)
            ->get(['id', 'lead_id', 'title', 'task_type', 'status', 'scheduled_at'])
            ->map(fn (TelecallerTask $task) => [
                'type' => ucwords(str_replace('_', ' ', $task->task_type ?: 'Task')),
                'customer' => $task->lead?->name ?: 'Unknown lead',
                'title' => $task->title,
                'scheduled_at' => $task->scheduled_at?->format('d M, h:i A'),
                'status' => $task->status,
                'view_url' => $task->lead_id ? url('/leads/' . $task->lead_id) : null,
            ]);

        return $taskItems->merge($telecallerItems)->values();
    }

    private function leadTemperatureCountsForLeadIds($leadIds): array
    {
        $ids = collect($leadIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $counts = [
            'hot' => 0,
            'warm' => 0,
            'cold' => 0,
            'other' => 0,
            'total' => 0,
        ];

        if ($ids->isEmpty()) {
            return $counts;
        }

        $statusByLeadId = [];

        Prospect::query()
            ->whereIn('lead_id', $ids)
            ->whereNotNull('lead_status')
            ->orderBy('id')
            ->get(['lead_id', 'lead_status'])
            ->each(function (Prospect $prospect) use (&$statusByLeadId) {
                $status = $this->normalizeLeadTemperatureStatus($prospect->lead_status);
                if ($status !== null) {
                    $statusByLeadId[(int) $prospect->lead_id] = $status;
                }
            });

        LeadFormFieldValue::query()
            ->whereIn('lead_id', $ids)
            ->where('field_key', 'lead_status')
            ->whereNotNull('field_value')
            ->orderBy('id')
            ->get(['lead_id', 'field_value'])
            ->each(function (LeadFormFieldValue $fieldValue) use (&$statusByLeadId) {
                $status = $this->normalizeLeadTemperatureStatus($fieldValue->field_value);
                if ($status !== null) {
                    $statusByLeadId[(int) $fieldValue->lead_id] = $status;
                }
            });

        foreach ($statusByLeadId as $status) {
            $counts['total']++;

            if (array_key_exists($status, $counts) && $status !== 'total') {
                $counts[$status]++;
                continue;
            }

            $counts['other']++;
        }

        return $counts;
    }

    private function normalizeLeadTemperatureStatus($value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'hot' => 'hot',
            'warm' => 'warm',
            'cold' => 'cold',
            default => $normalized,
        };
    }

    private function averageTeamLeadResponseSeconds(User $member, Carbon $startDate, Carbon $endDate): ?int
    {
        $assignments = LeadAssignment::query()
            ->whereHas('lead')
            ->where('is_active', true)
            ->where('assigned_to', $member->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['lead_id', 'assigned_at', 'created_at']);

        $seconds = $assignments->map(function (LeadAssignment $assignment) use ($member) {
            $assignedAt = $assignment->assigned_at ?: $assignment->created_at;
            if (!$assignedAt) {
                return null;
            }

            $managerTaskAt = Task::query()
                ->where('lead_id', $assignment->lead_id)
                ->where('assigned_to', $member->id)
                ->whereIn('status', ['in_progress', 'completed'])
                ->selectRaw('COALESCE(completed_at, updated_at, created_at) as first_action_at')
                ->orderByRaw('COALESCE(completed_at, updated_at, created_at) asc')
                ->value('first_action_at');

            $telecallerTaskAt = TelecallerTask::query()
                ->where('lead_id', $assignment->lead_id)
                ->where('assigned_to', $member->id)
                ->whereIn('status', ['in_progress', 'completed'])
                ->selectRaw('COALESCE(completed_at, updated_at, created_at) as first_action_at')
                ->orderByRaw('COALESCE(completed_at, updated_at, created_at) asc')
                ->value('first_action_at');

            $firstActionAt = collect([$managerTaskAt, $telecallerTaskAt])
                ->filter()
                ->map(fn ($value) => Carbon::parse($value))
                ->sort()
                ->first();

            if (!$firstActionAt) {
                return null;
            }

            $diff = Carbon::parse($assignedAt)->diffInSeconds($firstActionAt, false);

            return $diff >= 0 ? $diff : null;
        })->filter(fn ($value) => $value !== null);

        if ($seconds->isEmpty()) {
            return null;
        }

        return (int) round($seconds->avg());
    }

    private function formatTeamDashboardDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return '-';
        }

        if ($seconds < 60) {
            return $seconds . ' sec';
        }

        $minutes = intdiv($seconds, 60);
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return trim($hours . 'h ' . ($remainingMinutes > 0 ? $remainingMinutes . 'm' : ''));
    }

    private function formatDashboardRangeLabel(string $dateFilter, Carbon $startDate, Carbon $endDate): string
    {
        return match ($dateFilter) {
            'today' => 'Today',
            'this_week' => 'This Week',
            'this_month' => 'This Month',
            'custom' => $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y'),
            default => $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y'),
        };
    }

    /**
     * Get achievements (target vs achieved) for sales manager / ASM.
     * When no target record exists (e.g. for ASM), use an in-memory target so achieved counts still show.
     */
    public function getAchievements(Request $request)
    {
        $user = $request->user();

        $target = Target::where('user_id', $user->id)
            ->whereYear('target_month', Carbon::now()->year)
            ->whereMonth('target_month', Carbon::now()->month)
            ->first();

        if (!$target) {
            $target = new Target([
                'user_id' => $user->id,
                'target_month' => Carbon::now()->startOfMonth(),
                'target_meetings' => 0,
                'target_visits' => 0,
                'target_closers' => 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'meetings' => $target->getAchievementProgress('meetings'),
            'site_visits' => $target->getAchievementProgress('visits'),
            'closers' => $target->getAchievementProgress('closers'),
        ]);
    }

    /**
     * Get team prospects for Senior Manager (also accessible by Admin, CRM, Sales Head)
     */
    public function getProspects(Request $request)
    {
        // Get user from request (works with Sanctum)
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'per_page' => 15,
                'total' => 0,
                'last_page' => 1,
                'message' => 'Unauthenticated'
            ], 401);
        }
        
        // Ensure role is loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        $restrictPendingVerificationToSubordinates =
            $user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager();
        
        // Query prospects
        $query = Prospect::with(['telecaller', 'manager', 'lead', 'createdBy'])
            ->where(function ($prospectQuery) {
                $prospectQuery->whereNull('lead_id')
                    ->orWhereHas('lead', function ($leadQuery) {
                        $leadQuery->whereNotIn('status', ['junk', 'not_interested']);
                    });
            });
        
        // Role-based filtering
        if ($user->isAdmin() || $user->isCrm()) {
            // Admin and CRM can see all prospects
            // No additional filtering needed
        } elseif ($user->isSalesHead()) {
            // Sales Head can see all prospects from their entire team hierarchy
            $allTeamMemberIds = $user->getAllTeamMemberIds();
            if (!empty($allTeamMemberIds)) {
                $query->where(function($q) use ($allTeamMemberIds, $user) {
                    $q->whereIn('telecaller_id', $allTeamMemberIds)
                      ->orWhere('manager_id', $user->id)
                      ->orWhereIn('manager_id', $allTeamMemberIds);
                });
            } else {
                // No team members, show only their own
                $query->where('manager_id', $user->id);
            }
        } elseif ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager()) {
            // Senior Manager / Manager / Assistant Sales Manager: prospects from their direct team members
            $teamMemberIds = $user->teamMembers()->pluck('id');
            
            // Build query to show all prospects for this manager:
            // 1. Prospects created by team members (telecallers under this manager)
            // 2. Prospects assigned to this manager (via manager_id)
            // 3. Prospects assigned to this manager (via assigned_manager)
            // 4. Prospects where telecaller's manager_id matches this manager (for old prospects)
            $query->where(function($q) use ($teamMemberIds, $user) {
                // Start with manager_id check (always include this)
                $q->where('manager_id', $user->id)
                  ->orWhere('assigned_manager', $user->id);
                
                // If there are team members, include their prospects
                if ($teamMemberIds->isNotEmpty()) {
                    $q->orWhereIn('telecaller_id', $teamMemberIds);
                }
                
                // Also check if telecaller's manager_id matches (for old prospects without manager_id set)
                $q->orWhereHas('telecaller', function($telecallerQuery) use ($user) {
                    $telecallerQuery->where('manager_id', $user->id);
                });
            });
            
            // Log for debugging - check actual data
            $prospectCountBeforeFilter = (clone $query)->count();
            
            // Also check raw counts for debugging
            $managerIdCount = Prospect::where('manager_id', $user->id)->count();
            $assignedManagerCount = Prospect::where('assigned_manager', $user->id)->count();
            $telecallerManagerCount = Prospect::whereHas('telecaller', function($q) use ($user) {
                $q->where('manager_id', $user->id);
            })->count();
            $teamMemberProspectsCount = $teamMemberIds->isNotEmpty() 
                ? Prospect::whereIn('telecaller_id', $teamMemberIds)->count() 
                : 0;
            
            \Log::info('Senior Manager prospects query', [
                'manager_id' => $user->id,
                'manager_email' => $user->email,
                'manager_name' => $user->name,
                'team_member_ids' => $teamMemberIds->toArray(),
                'team_member_count' => $teamMemberIds->count(),
                'prospects_before_status_filter' => $prospectCountBeforeFilter,
                'debug_counts' => [
                    'manager_id_count' => $managerIdCount,
                    'assigned_manager_count' => $assignedManagerCount,
                    'telecaller_manager_count' => $telecallerManagerCount,
                    'team_member_prospects_count' => $teamMemberProspectsCount,
                ],
            ]);
        } else {
            // Other roles - return empty
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'per_page' => 15,
                'total' => 0,
                'last_page' => 1
            ]);
        }
        
        // Get base query for counts (before search filter)
        $baseQuery = clone $query;
        
        // Filter by verification status
        if ($request->has('verification_status') && $request->verification_status !== 'all' && $request->verification_status !== '') {
            $status = $request->verification_status;
            if (in_array($status, ['pending', 'pending_verification', 'rejected'], true)) {
                $status = 'verified';
            }

            if ($status === 'verified') {
                $query->whereIn('verification_status', ['verified', 'approved']);
            } else {
                $query->where('verification_status', $status);
            }
        }
        
        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('preferred_location', 'like', "%{$search}%");
            });
        }
        
        // Filter by assigned user (telecaller_id or manager_id)
        if ($request->has('assigned_to') && $request->assigned_to) {
            $assignedToId = $request->assigned_to;
            $query->where(function($q) use ($assignedToId) {
                $q->where('telecaller_id', $assignedToId)
                  ->orWhere('manager_id', $assignedToId)
                  ->orWhere('assigned_manager', $assignedToId);
            });
        }

        // Filter by lead temperature/status
        if ($request->has('lead_status') && $request->lead_status && $request->lead_status !== 'all') {
            $query->where('lead_status', $request->lead_status);
        }

        if ($request->boolean('created_today')) {
            $query->whereDate('created_at', today());
        }
        
        // Order by created_at descending (newest first)
        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));
        $prospects = $query->latest('created_at')->paginate($perPage);
        
        // Log final results for debugging - include sample prospect IDs
        $sampleProspectIds = $prospects->items() ? array_slice(array_map(function($p) { return $p->id; }, $prospects->items()), 0, 5) : [];
        \Log::info('Senior Manager prospects query result', [
            'manager_id' => $user->id,
            'manager_email' => $user->email,
            'total_prospects' => $prospects->total(),
            'current_page' => $prospects->currentPage(),
            'per_page' => $prospects->perPage(),
            'verification_status_filter' => $request->input('verification_status', 'all'),
            'search_query' => $request->input('search', ''),
            'sample_prospect_ids' => $sampleProspectIds,
            'raw_sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);
        
        // Calculate counts for status filters (without search filter)
        // Note: Database uses 'pending' but frontend expects 'pending_verification'
        // Database uses 'approved' but frontend expects 'verified'
        // "all" count excludes rejected prospects
        $counts = [
            'all' => (clone $baseQuery)->count(),
            'pending_verification' => 0,
            'verified' => (clone $baseQuery)->whereIn('verification_status', ['verified', 'approved'])->count(),
            'rejected' => 0,
        ];
        
        return response()->json([
            ...$prospects->toArray(),
            'counts' => $counts
        ]);
    }

    public function getFavoriteLeads(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $this->getFavoriteLeadPayload($user, null),
        ]);
    }

    public function addFavoriteLead(Request $request, Lead $lead)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$this->canAccessLead($user, $lead)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to favorite this lead.',
            ], 403);
        }

        LeadFavorite::firstOrCreate([
            'user_id' => $user->id,
            'lead_id' => $lead->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lead marked as favorite.',
            'lead_id' => $lead->id,
            'is_favorite' => true,
            'favorites' => $this->getFavoriteLeadPayload($user, 5),
        ]);
    }

    public function removeFavoriteLead(Request $request, Lead $lead)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$this->canAccessLead($user, $lead)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update favorite for this lead.',
            ], 403);
        }

        LeadFavorite::where('user_id', $user->id)
            ->where('lead_id', $lead->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead removed from favorites.',
            'lead_id' => $lead->id,
            'is_favorite' => false,
            'favorites' => $this->getFavoriteLeadPayload($user, 5),
        ]);
    }

    public function getLeadOpenTask(Request $request, Lead $lead)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (!$this->canAccessLead($user, $lead)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this lead.',
            ], 403);
        }

        $managerTasks = $this->getLeadManagerOpenTasksPayload($lead, $user);
        $telecallerTasks = $this->getLeadTelecallerOpenTasksPayload($lead);
        $openTasks = collect($managerTasks)
            ->concat($telecallerTasks)
            ->sortBy(fn (array $task) => $task['scheduled_at'] ?: '9999-12-31 23:59:59')
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $openTasks[0] ?? null,
            'tasks' => $openTasks,
            'count' => count($openTasks),
        ]);
    }

    /**
     * Create prospect (Manager can create directly)
     */
    public function createProspect(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'lead_id' => 'nullable|exists:leads,id',
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'budget' => 'nullable|numeric',
            'preferred_location' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:255',
            'purpose' => 'nullable|in:end_user,investment',
            'possession' => 'nullable|string|max:255',
            'remark' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['manager_id'] = $user->id;
        $data['assigned_manager'] = $user->id;
        $data['created_by'] = $user->id;
        $data['verification_status'] = 'approved';

        // If lead_id provided, link it
        if (isset($data['lead_id'])) {
            $lead = Lead::find($data['lead_id']);
            if ($lead) {
                $data['telecaller_id'] = null; // Manager created, not from telecaller
            }
        }

        $prospect = Prospect::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Prospect created successfully',
            'data' => $prospect->load(['manager', 'lead']),
        ], 201);
    }

    /**
     * Get tasks assigned to current sales manager/executive
     */
    public function getTasks(Request $request)
    {
        try {
            $user = $request->user();
            $teamMemberIds = $user ? $user->teamMembers()->pluck('id') : collect();
            
            if (!$user) {
                \Log::warning('Senior Manager getTasks - User not authenticated');
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                    'data' => [],
                ], 401);
            }
            
            \Log::info('Senior Manager getTasks - Starting', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'status_filter' => $request->input('status', 'all'),
                'category_filter' => $request->input('category', 'all'),
            ]);
            
            // Query Tasks assigned to this user (manager call tasks + linked site visit reminder tasks)
            $query = Task::where('assigned_to', $user->id)
                ->whereHas('lead', function ($leadQuery) use ($user) {
                    $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                        $assignmentQuery->where('assigned_to', $user->id)
                            ->where('is_active', true);
                    });
                })
                ->whereIn('type', ['phone_call', 'site_visit'])
                ->with(['lead.prospects', 'assignedTo', 'creator', 'siteVisit']);
            $this->applyAsmHiddenCompletedFilter($query);
            $this->applyAsmHiddenTransitionOutcomeFilter($query);
                
            // Debug: Log total tasks before filters
            $totalTasksBeforeFilter = (clone $query)->count();
            \Log::info('Senior Manager getTasks - Total tasks before filters', [
                'user_id' => $user->id,
                'total_tasks' => $totalTasksBeforeFilter,
            ]);

            // Get date filter first to check if it's applied
            $dateFilter = $request->input('date_filter');
            $hasDateFilter = $dateFilter && $dateFilter !== 'all';

            // Filter by status
            // For ASM: "All Tasks" = only pending/in_progress (verification complete = task completed = hide from list)
            $statusFilter = $request->input('status');
            if (!$statusFilter || $statusFilter === 'all' || $statusFilter === '') {
                // Default / All: completed tasks are already excluded from ASM view
            } elseif ($statusFilter && $statusFilter !== 'all' && $statusFilter !== '') {
                if ($statusFilter === 'rescheduled') {
                    // Rescheduled: show CNP tasks scheduled beyond the grace window.
                    $upcomingGraceCutoff = $this->upcomingGraceCutoff();
                    $overdueCutoff = $this->overdueCutoff();
                    
                    // Identify CNP tasks by markers in notes/title/description
                    $query->where(function($q) {
                        $q->where('notes', 'like', '%CNP retry task created%')
                          ->orWhere('title', 'like', '%CNP rescheduled%')
                          ->orWhere('description', 'like', '%previous call not picked%');
                    })
                    ->where('status', 'pending') // Only pending CNP tasks
                    ->where('scheduled_at', '>', $upcomingGraceCutoff)
                    ->where('scheduled_at', '>=', $overdueCutoff);
                } elseif ($statusFilter === 'pending') {
                    // Pending: show normal pending tasks + CNP tasks within the grace window.
                    $query->where('status', 'pending');
                    
                    if ($hasDateFilter) {
                        // When date filter is applied, show all pending tasks for that date range
                        // Don't apply the grace-window restriction here; date filter handles the range.
                        // Date filter will be applied below
                    } else {
                        $upcomingGraceCutoff = $this->upcomingGraceCutoff();
                        $overdueCutoff = $this->overdueCutoff();
                        
                        $query->where('scheduled_at', '>=', $overdueCutoff)
                            ->where(function($q) use ($upcomingGraceCutoff) {
                                // Normal pending tasks (not CNP)
                                $q->where(function($notCnpQ) {
                                    $notCnpQ->where('notes', 'not like', '%CNP retry task created%')
                                            ->where('title', 'not like', '%CNP rescheduled%')
                                            ->where('description', 'not like', '%previous call not picked%');
                                })
                                // OR CNP tasks scheduled within the grace window (auto-moved to pending)
                                ->orWhere(function($cnpQ) use ($upcomingGraceCutoff) {
                                    $cnpQ->where(function($cnpMarkers) {
                                        $cnpMarkers->where('notes', 'like', '%CNP retry task created%')
                                                   ->orWhere('title', 'like', '%CNP rescheduled%')
                                                   ->orWhere('description', 'like', '%previous call not picked%');
                                    })
                                    ->where('scheduled_at', '<=', $upcomingGraceCutoff);
                                });
                            });
                    }
                } elseif ($statusFilter === 'overdue') {
                    $overdueCutoff = $this->overdueCutoff();
                    $query->whereIn('status', ['pending', 'in_progress'])
                          ->where('scheduled_at', '<', $overdueCutoff);
                } elseif ($statusFilter === 'completed') {
                    $query->whereRaw('1 = 0');
                } else {
                    // Other status filters (in_progress, etc.) - use standard filter
                    $query->where('status', $statusFilter);
                }
            }

            // Date filter - works in combination with status filter
            // For pending tasks with date filter, show all pending tasks for that date range
            if ($dateFilter && $dateFilter !== 'all') {
                if ($dateFilter === 'today') {
                    $query->whereDate('scheduled_at', Carbon::today());
                } elseif ($dateFilter === 'tomorrow') {
                    $query->whereDate('scheduled_at', Carbon::tomorrow());
                } elseif ($dateFilter === 'pod') {
                    $query->whereDate('scheduled_at', '<', Carbon::today());
                } elseif ($dateFilter === 'this_week') {
                    $query->whereBetween('scheduled_at', [
                        Carbon::now()->startOfWeek(),
                        Carbon::now()->endOfWeek()
                    ]);
                } elseif ($dateFilter === 'this_month') {
                    $query->whereBetween('scheduled_at', [
                        Carbon::now()->startOfMonth(),
                        Carbon::now()->endOfMonth()
                    ]);
                } elseif ($dateFilter === 'this_year') {
                    $query->whereBetween('scheduled_at', [
                        Carbon::now()->startOfYear(),
                        Carbon::now()->endOfYear()
                    ]);
                } elseif ($dateFilter === 'custom' && $request->has('custom_date')) {
                    $customDate = $request->input('custom_date');
                    if ($customDate) {
                        $query->whereDate('scheduled_at', $customDate);
                    }
                }
            }

            // Search filter
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('lead', function($leadQ) use ($search) {
                          $leadQ->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                      });
                });
            }

            $tasks = $query->get();
            $tasks = $tasks->filter(function (Task $task) use ($dateFilter, $request, $statusFilter) {
                return $this->shouldIncludeAsmTaskInResults($task, $dateFilter, $request->input('custom_date'), $statusFilter);
            })->values();
            
            \Log::info('Senior Manager getTasks - Tasks found after filters', [
                'user_id' => $user->id,
                'tasks_count' => $tasks->count(),
                'total' => $tasks->count(),
                'status_filter' => $statusFilter,
            ]);

            // Deduplicate tasks: keep only one task per lead_id.
            // Priority: pending > in_progress > completed > cancelled.
            // If same status, prefer the latest scheduled task so a freshly created retry
            // task is not hidden behind an older stale pending task for the same lead.
            $deduplicatedTasks = $tasks->groupBy('lead_id')
                ->map(function($group) {
                    // Sort by priority (lower number = higher priority) and then by latest scheduled_at
                    return $group->sortBy(function($task) {
                        $priority = [
                            'pending' => 1,
                            'in_progress' => 2,
                            'completed' => 3,
                            'cancelled' => 4
                        ];
                        $priorityValue = $priority[$task->status] ?? 5;
                        $scheduledAt = $task->scheduled_at ? -$task->scheduled_at->timestamp : PHP_INT_MAX;
                        $taskId = -((int) $task->id);
                        return [$priorityValue, $scheduledAt, $taskId];
                    })->first();
                })
                ->sort(function ($left, $right) {
                    $leftPriority = $left->isOverdue() ? 0 : 1;
                    $rightPriority = $right->isOverdue() ? 0 : 1;

                    if ($leftPriority !== $rightPriority) {
                        return $leftPriority <=> $rightPriority;
                    }

                    $leftScheduledAt = $left->scheduled_at ? $left->scheduled_at->timestamp : PHP_INT_MAX;
                    $rightScheduledAt = $right->scheduled_at ? $right->scheduled_at->timestamp : PHP_INT_MAX;

                    if ($leftScheduledAt !== $rightScheduledAt) {
                        return $leftScheduledAt <=> $rightScheduledAt;
                    }

                    return $left->id <=> $right->id;
                })
                ->values()
                ->all();

            // Log for debugging - include SQL query details
            \Log::info('Senior Manager getTasks', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'status_filter' => $request->input('status', 'all'),
                'total_tasks_before_dedup' => $tasks->count(),
                'tasks_in_page_before_dedup' => $tasks->count(),
                'tasks_after_dedup' => count($deduplicatedTasks),
                'sql_query' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);
            
            // Debug: Check total tasks without filters
            $totalTasksWithoutFilter = Task::where('assigned_to', $user->id)
                ->whereHas('lead', function ($leadQuery) use ($user) {
                    $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                        $assignmentQuery->where('assigned_to', $user->id)
                            ->where('is_active', true);
                    });
                })
                ->count();
            $phoneCallTasks = Task::where('assigned_to', $user->id)
                ->whereHas('lead', function ($leadQuery) use ($user) {
                    $leadQuery->whereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                        $assignmentQuery->where('assigned_to', $user->id)
                            ->where('is_active', true);
                    });
                })
                ->where('type', 'phone_call')
                ->count();
            \Log::info('Senior Manager getTasks - Debug counts', [
                'total_tasks_for_manager' => $totalTasksWithoutFilter,
                'phone_call_tasks' => $phoneCallTasks,
            ]);

            $categoryFilter = $request->input('category');
            $normalizedCategoryFilter = $categoryFilter ? strtolower(trim($categoryFilter)) : null;

            $deduplicatedLeadIds = collect($deduplicatedTasks)
                ->pluck('lead_id')
                ->filter()
                ->unique()
                ->values();

            $cnpSequenceByLead = $deduplicatedLeadIds->isEmpty()
                ? []
                : Task::query()
                    ->whereIn('lead_id', $deduplicatedLeadIds)
                    ->where('type', 'phone_call')
                    ->where('outcome', 'cnp')
                    ->selectRaw('lead_id, COUNT(*) as cnp_count')
                    ->groupBy('lead_id')
                    ->pluck('cnp_count', 'lead_id')
                    ->map(fn ($count) => (int) $count)
                    ->all();

            // Transform tasks to array format for JSON response (using deduplicated tasks)
            $tasksArray = [];
            foreach ($deduplicatedTasks as $task) {
                // Check if overdue
                $isOverdue = $task->isOverdue();
                $scheduledAtFormatted = $task->scheduled_at ? $task->scheduled_at->format('Y-m-d H:i:s') : null;
                
                // Get prospect information if available
                $leadStatus = null;
                $hasProspect = false;
                $prospectData = null;
                
                if ($task->lead) {
                    // Load prospects if not already loaded
                    if (!$task->lead->relationLoaded('prospects')) {
                        $task->lead->load('prospects');
                    }
                    
                    // Get latest prospect
                    $prospect = $task->lead->currentCycleProspects()->latest()->first();
                    
                    if ($prospect) {
                        $hasProspect = $this->prospectRequiresManagerVerification($prospect, $user, $teamMemberIds);
                        $leadStatus = $prospect->lead_status ?? null;
                        $prospectData = [
                            'id' => $prospect->id,
                            'verification_status' => $prospect->verification_status ?? null,
                            'lead_status' => $prospect->lead_status ?? null,
                            'telecaller_id' => $prospect->telecaller_id ?? null,
                            'is_pending_verification' => $hasProspect,
                        ];
                    }
                }
                
                $taskText = strtolower(trim(
                    ($task->title ?? '') . ' ' .
                    ($task->description ?? '') . ' ' .
                    ($task->notes ?? '')
                ));
                $isFollowUpTask = (bool) $task->follow_up_id ||
                                  str_contains($taskText, 'follow-up call') ||
                                  str_contains($taskText, 'follow up call') ||
                                  str_contains($taskText, 'follow-up scheduled');
                $isInterestedFollowUpTask = $isFollowUpTask && $this->isInterestedFollowUpTask($task, $taskText);
                $isCnpRetryTask = str_contains($taskText, 'cnp retry task created') ||
                                  str_contains($taskText, 'cnp rescheduled') ||
                                  str_contains($taskText, 'previous call not picked');
                $isCloserTask = str_contains($taskText, 'closer');
                $siteVisit = $this->resolveTaskSiteVisit($task);
                $isSiteVisitTask = $siteVisit !== null;
                $isMeetingTask = str_contains($taskText, 'meeting id') ||
                                 str_contains($taskText, 'pre-meeting') ||
                                 (str_contains($taskText, 'meeting') && !$isSiteVisitTask);
                // Treat a task as a prospect task only when the lead actually has a linked prospect
                // awaiting/available for ASM verification. Title text alone should not hide a fresh calling task.
                $isProspectTask = !$isInterestedFollowUpTask && !$isCnpRetryTask && $hasProspect;
                $isFreshLeadTask = !$isInterestedFollowUpTask &&
                                   !$isCnpRetryTask &&
                                   !$isCloserTask &&
                                   !$isSiteVisitTask &&
                                   !$isMeetingTask &&
                                   !$isProspectTask;
                $taskCategory = 'other';
                if ($isFreshLeadTask) {
                    $taskCategory = 'fresh_lead';
                } elseif ($isInterestedFollowUpTask) {
                    $taskCategory = 'follow_up';
                } elseif ($isCloserTask) {
                    $taskCategory = 'closer';
                } elseif ($isSiteVisitTask) {
                    $taskCategory = 'site_visit';
                } elseif ($isMeetingTask) {
                    $taskCategory = 'meeting';
                } elseif ($isProspectTask) {
                    $taskCategory = 'prospect';
                }

                $displayTitle = trim((string) ($task->lead->name ?? '')) ?: trim((string) $task->title) ?: 'Prospect';
                $displaySubtitle = $this->formatAsmTaskCardSubtitle($taskCategory, $task, $isCnpRetryTask);
                $cnpSequence = ($isCnpRetryTask && $task->type === 'phone_call' && $task->lead_id)
                    ? max(1, (int) ($cnpSequenceByLead[$task->lead_id] ?? 0))
                    : null;
                $taskData = [
                    'id' => $task->id,
                    'lead_id' => $task->lead_id,
                    'assigned_to' => $task->assigned_to,
                    'type' => $task->type,
                    'category' => $taskCategory,
                    'title' => $task->title,
                    'description' => $task->description,
                    'notes' => $task->notes,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'scheduled_at' => $task->scheduled_at ? $task->scheduled_at->toDateTimeString() : null,
                    'scheduled_at_formatted' => $scheduledAtFormatted,
                    'due_date' => $task->due_date ? $task->due_date->toDateTimeString() : null,
                    'completed_at' => $task->completed_at ? $task->completed_at->toDateTimeString() : null,
                    'meeting_id' => $task->meeting_id,
                    'site_visit_id' => $siteVisit?->id,
                    'site_visit_project' => $siteVisit?->project ?: $siteVisit?->property_name,
                    'follow_up_id' => $task->follow_up_id,
                    'is_overdue' => $isOverdue,
                    'display_title' => $displayTitle,
                    'display_subtitle' => $displaySubtitle,
                    'cnp_sequence' => $cnpSequence,
                    'has_prospect' => $hasProspect, // Flag to indicate if lead has associated prospect
                    'prospect' => $prospectData, // Prospect data if exists
                    'lead' => $task->lead ? [
                        'id' => $task->lead->id,
                        'name' => $task->lead->name ?? 'N/A',
                        'phone' => $task->lead->phone ?? 'N/A',
                        'email' => $task->lead->email ?? null,
                        'source' => $task->lead->source ?? null, // Include source to check if from telecaller
                        'lead_status' => $leadStatus,
                        'lead_phone' => $task->lead->phone ?? 'N/A', // For backward compatibility
                    ] : null,
                    'assignedTo' => $task->assignedTo ? [
                        'id' => $task->assignedTo->id,
                        'name' => $task->assignedTo->name ?? 'N/A',
                    ] : null,
                ];
                
                $tasksArray[] = $taskData;
            }

            $tasksArray = array_values(array_filter($tasksArray, function ($task) {
                if (($task['category'] ?? null) !== 'prospect') {
                    return true;
                }

                return ($task['has_prospect'] ?? false) === true;
            }));

            if ($normalizedCategoryFilter && $normalizedCategoryFilter !== 'all') {
                $tasksArray = array_values(array_filter($tasksArray, function ($task) use ($normalizedCategoryFilter) {
                    return isset($task['category']) && $task['category'] === $normalizedCategoryFilter;
                }));
            }

            // Log response structure for debugging
            \Log::info('Senior Manager getTasks - Response', [
                'user_id' => $user->id,
                'tasks_count' => count($tasksArray),
                'response_structure' => [
                    'success' => true,
                    'data_type' => 'array',
                    'data_length' => count($tasksArray),
                    'total' => count($tasksArray),
                ],
                'sample_task' => $tasksArray[0] ?? null,
            ]);

            // Return paginated response with properly formatted data
            // Note: total count is based on deduplicated tasks
            return response()->json([
                'success' => true,
                'data' => $tasksArray, // Plain PHP array, properly serialized (deduplicated)
                'current_page' => 1,
                'per_page' => count($tasksArray),
                'total' => count($tasksArray), // Use deduplicated count
                'last_page' => 1,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Senior Manager getTasks - Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load tasks: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get single task details with lead and prospect data
     */
    public function getTask(Request $request, Task $task)
    {
        $user = $request->user();
        
        // Verify task is assigned to current user (admin/crm can view all)
        if ((int)$task->assigned_to !== (int)$user->id && !$user->isAdmin() && !$user->isCrm()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to task',
            ], 403);
        }

        $task->load([
            'lead.prospects',
            'lead.formFieldValues',
            'lead.latestFbLead.form',
            'lead.latestImportedLead',
            'assignedTo',
            'creator',
        ]);
        $task->is_overdue = $task->isOverdue();
        $task->scheduled_at_formatted = $task->scheduled_at ? $task->scheduled_at->format('Y-m-d H:i:s') : null;

        // Get prospect lead_status if available
        if ($task->lead) {
            $prospect = $task->lead->currentCycleProspects()->latest()->first();
            if ($prospect) {
                $task->lead->lead_status = $prospect->lead_status ?? null;
                $task->prospect = $prospect;
            }
            
            // Add form fields to lead
            $task->lead->form_fields = $task->lead->getFormFieldsArray();
            $task->lead->source_details = $this->buildTaskLeadSourceDetailPayload($task->lead);
        }

        return response()->json([
            'success' => true,
            'data' => $task,
        ]);
    }

    private function buildTaskLeadSourceDetailPayload(Lead $lead): array
    {
        $normalizedSource = Lead::normalizeSource($lead->source);
        $payload = [
            'source' => $normalizedSource,
            'label' => Lead::displaySourceLabel($lead->source),
            'type' => 'generic',
            'recording' => null,
            'meta' => null,
        ];

        if ($normalizedSource === 'ivr') {
            $latestRecordingCall = $lead->callLogs()
                ->whereNotNull('recording_url')
                ->orderByDesc('created_at')
                ->first();

            $payload['type'] = 'ivr';
            $payload['recording'] = $latestRecordingCall ? [
                'call_id' => $latestRecordingCall->id,
                'recording_url' => $latestRecordingCall->recording_url,
                'duration_seconds' => (int) ($latestRecordingCall->duration ?? 0),
                'duration_label' => $latestRecordingCall->formatted_duration,
                'recording_route' => route('calls.recording', $latestRecordingCall),
                'download_route' => route('calls.recording', ['callLog' => $latestRecordingCall->id, 'download' => 1]),
                'started_at' => optional($latestRecordingCall->start_time)->toDateTimeString(),
                'status' => $latestRecordingCall->status,
            ] : null;

            return $payload;
        }

        if ($normalizedSource === 'meta') {
            $fbLead = $lead->latestFbLead;
            $importedLead = $lead->latestImportedLead;

            $payload['type'] = 'meta';
            $payload['meta'] = [
                'leadgen_id' => $fbLead->leadgen_id ?? null,
                'form_name' => $fbLead?->form?->form_name ?? null,
                'field_data' => $this->normalizeDetailPayload($fbLead->field_data_json ?? []),
                'raw_payload' => $this->normalizeDetailPayload($fbLead->raw_response_json ?? []),
                'import_data' => $this->normalizeDetailPayload($importedLead->import_data ?? []),
            ];

            return $payload;
        }

        return $payload;
    }

    private function normalizeDetailPayload($value)
    {
        if (!is_array($value)) {
            return [];
        }

        $normalize = function ($item) use (&$normalize) {
            if (is_array($item)) {
                $isAssoc = array_keys($item) !== range(0, count($item) - 1);

                if ($isAssoc) {
                    $normalized = [];
                    foreach ($item as $key => $child) {
                        $normalized[(string) $key] = $normalize($child);
                    }

                    return $normalized;
                }

                return array_map($normalize, $item);
            }

            if ($item instanceof \JsonSerializable) {
                return $normalize($item->jsonSerialize());
            }

            if ($item instanceof \DateTimeInterface) {
                return $item->format('Y-m-d H:i:s');
            }

            if (is_bool($item)) {
                return $item ? 'Yes' : 'No';
            }

            if ($item === null) {
                return '';
            }

            return (string) $item;
        };

        return $normalize($value);
    }

    /**
     * Update lead details and prospect from task form with verify/reject actions
     */
    public function updateLeadFromTask(Request $request, Task $task)
    {
        $user = $request->user();
        
        // Verify task is assigned to current user (admin/crm can view all)
        if ((int)$task->assigned_to !== (int)$user->id && !$user->isAdmin() && !$user->isCrm()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to task',
            ], 403);
        }

        $request->validate([
            'action' => 'required|in:verify,reject',
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'budget' => 'nullable|numeric|min:0',
            'preferred_location' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:255',
            'purpose' => 'nullable|in:end_user,investment',
            'possession' => 'nullable|string|max:255',
            'lead_status' => 'required|in:hot,warm,cold,junk',
            'manager_remark' => 'nullable|string',
            'interested_projects' => 'required_if:action,verify|array|min:1',
            'interested_projects.*' => function ($attribute, $value, $fail) {
                if (is_int($value) || is_numeric($value) || (is_array($value) && isset($value['name']))) {
                    return;
                }
                $fail('The ' . $attribute . ' must be a valid project selection.');
            },
        ]);

        DB::beginTransaction();
        try {
            $action = $request->input('action');
            $lead = $task->lead;
            $prospect = null;

            // Get or create prospect
            if ($task->lead_id && $lead) {
                $prospect = $lead->currentCycleProspects()->latest()->first();
                
                // Update lead
                $lead->update([
                    'name' => $request->input('customer_name'),
                    'phone' => $request->input('phone'),
                    'email' => $request->input('email'),
                    'address' => $request->input('address'),
                    'city' => $request->input('city'),
                    'state' => $request->input('state'),
                    'pincode' => $request->input('pincode'),
                    'preferred_location' => $request->input('preferred_location'),
                    'preferred_size' => $request->input('size'),
                    'budget' => $request->input('budget'),
                    'investment' => $request->input('budget'),
                ]);
            }

            // Update or create prospect
            $prospectData = [
                'customer_name' => $request->input('customer_name'),
                'phone' => $request->input('phone'),
                'budget' => $request->input('budget'),
                'preferred_location' => $request->input('preferred_location'),
                'size' => $request->input('size'),
                'purpose' => $request->input('purpose'),
                'possession' => $request->input('possession'),
                'lead_status' => $request->input('lead_status'),
                'manager_remark' => $request->input('manager_remark'),
            ];

            if ($action === 'verify') {
                $prospectData['verification_status'] = 'verified';
                $prospectData['verified_at'] = now();
                $prospectData['verified_by'] = $user->id;
                
                if ($prospect) {
                    $prospect->update($prospectData);
                } else if ($lead) {
                    $prospectData['lead_id'] = $lead->id;
                    $prospectData['manager_id'] = $user->id;
                    $prospectData['assigned_manager'] = $user->id;
                    $prospectData['created_by'] = $user->id;
                    $prospect = Prospect::create($prospectData);
                }
                
                // Sync interested projects from the configured admin option list only.
                if ($prospect && $request->has('interested_projects')) {
                    $projectIds = $this->resolveInterestedProjectIdsFromSelection(
                        (array) $request->input('interested_projects', []),
                        $user->id
                    );

                    if (!empty($projectIds)) {
                        $prospect->interestedProjects()->sync($projectIds);
                    }
                }
            } else { // reject flow removed - keep the prospect verified
                $prospectData['verification_status'] = 'verified';
                $prospectData['verified_at'] = now();
                $prospectData['verified_by'] = $user->id;
                $prospectData['rejection_reason'] = null;
                $prospectData['manager_remark'] = $request->input('manager_remark') ?: 'Verification flow removed';
                
                if ($prospect) {
                    $prospect->update($prospectData);
                } else if ($lead) {
                    $prospectData['lead_id'] = $lead->id;
                    $prospectData['manager_id'] = $user->id;
                    $prospectData['assigned_manager'] = $user->id;
                    $prospectData['created_by'] = $user->id;
                    $prospect = Prospect::create($prospectData);
                }
            }

            // Mark task as completed using Task model's method
            $task->markAsCompleted();

            DB::commit();

            $message = $action === 'verify' 
                ? 'Prospect verified and task marked as completed successfully'
                : 'Prospect marked as verified and task marked as completed successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $task->fresh(['lead.prospects']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating prospect from task: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get lead requirement form for manager (all fields visible)
     */
    public function getLeadRequirementFormForTask(Request $request, Task $task)
    {
        try {
            $user = $request->user();
            
            // Verify task is assigned to current user (admin/crm can view all)
            if ((int)$task->assigned_to !== (int)$user->id && !$user->isAdmin() && !$user->isCrm()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to task',
                ], 403);
            }
            
            $lead = $task->lead;
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'error' => 'Lead not found for this task',
                ], 404);
            }
            
            // Get prospect if exists
            $prospect = $lead->currentCycleProspects()
                ->orderByRaw("
                    CASE
                        WHEN lead_score IS NOT NULL
                            OR lead_status IS NOT NULL
                            OR budget IS NOT NULL
                            OR preferred_location IS NOT NULL
                            OR size IS NOT NULL
                            OR purpose IS NOT NULL
                            OR possession IS NOT NULL
                        THEN 0 ELSE 1 END
                ")
                ->latest()
                ->first();
            
            // Determine if this is a prospect (from telecaller) or direct lead
            $hasProspect = $prospect && in_array($prospect->verification_status ?? '', ['pending', 'pending_verification']);
            
            $existingValues = $this->getHydratedLeadRequirementFormValues($lead, $prospect);
            
            $forms = $this->getLeadDetailDynamicForms();
            $requirementsFormConfig = $this->buildLeadDetailRequirementsFormConfig($forms, $lead, $user, 'task');
            $outputFormConfig = $this->buildLeadDetailOutputFormConfig($forms);
            $mappedFields = $this->buildLeadDetailRequirementFields($forms);
            
            \Log::info('Manager lead requirement form retrieved', [
                'task_id' => $task->id,
                'lead_id' => $lead->id,
                'fields_count' => count($mappedFields),
                'field_keys' => array_column($mappedFields, 'key'),
                'prospect_id' => $prospect?->id,
                'has_prospect' => $hasProspect,
            ]);
            
            return response()->json([
                'success' => true,
                'task_id' => $task->id,
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'lead_phone' => $lead->phone,
                'lead_email' => $lead->email,
                'prospect_id' => $prospect?->id,
                'prospect_status' => $prospect?->verification_status,
                'has_prospect' => $hasProspect, // Flag to determine if this is a prospect or direct lead
                'form_values' => $existingValues,
                'form_fields' => $mappedFields,
                'requirements_form_config' => $requirementsFormConfig,
                'output_form_config' => $outputFormConfig,
            ]);
        } catch (\Exception $e) {
            \Log::error('Get Lead Requirement Form Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $task->id ?? null,
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to load form: ' . $e->getMessage(),
                'message' => 'An error occurred while loading the form. Please try again.',
            ], 500);
        }
    }

    /**
     * Submit ASM task outcome through a single endpoint.
     */
    public function submitTaskOutcome(Request $request, Task $task)
    {
        $user = $request->user();

        if ((int) $task->assigned_to !== (int) $user->id && !$user->isAdmin() && !$user->isCrm()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access to task',
            ], 403);
        }

        $validated = $request->validate([
            'outcome' => 'required|in:interested,not_interested,follow_up,cnp,junk',
            'next_datetime' => 'nullable|date|after:now',
            'remark' => 'nullable|string|max:2000',
            'lead_form_payload' => 'nullable|array',
            'defer_completion_for_visit' => 'nullable|boolean',
        ]);

        $outcome = $validated['outcome'];

        $isFreshLeadCnp = $outcome === 'cnp'
            && (int) $task->assigned_to === (int) $user->id
            && ($user->isAssistantSalesManager() || $user->isSeniorManager() || $user->isSalesManager())
            && $this->asmCnpAutomation()->isFreshLeadTask($task);
        $requiresScheduledCnp = $outcome === 'cnp' && $this->usesUnifiedCnpScheduling($user) && !$isFreshLeadCnp;

        if (($outcome === 'follow_up' || $requiresScheduledCnp || ($outcome === 'cnp' && !$isFreshLeadCnp)) && empty($validated['next_datetime'])) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => ['next_datetime' => ['Please select the next call date and time.']],
            ], 422);
        }

        if ($outcome === 'interested') {
            $payload = $request->input('lead_form_payload', []);
            if (!is_array($payload) || empty($payload)) {
                $payload = $request->except(['outcome', 'next_datetime', 'remark', 'lead_form_payload']);
            }

            $payload['output_action'] = $payload['output_action'] ?? 'interested';
            $payload['follow_up_required'] = $payload['follow_up_required'] ?? '0';
            $deferCompletionForVisit = (bool) ($validated['defer_completion_for_visit'] ?? $request->boolean('defer_completion_for_visit'));
            $payload['defer_task_completion_for_visit'] = $deferCompletionForVisit ? '1' : '0';

            $childRequest = Request::create('/', 'POST', $payload);
            $childRequest->setUserResolver(fn () => $user);

            $response = $this->verifyProspectFromTask($childRequest, $task);
            $body = $response->getData(true);

            if (($body['success'] ?? false) !== true || $response->getStatusCode() >= 300) {
                return $response;
            }

            $task->refresh();
            if (!$deferCompletionForVisit) {
                $this->recordTaskOutcome($task, 'interested');
            }

            $this->asmCnpAutomation()->cancelTaskStageAutomation($task, 'Lead moved to interested flow.');

            $body['outcome'] = 'interested';
            $body['deferred_visit_completion'] = $deferCompletionForVisit;

            if ($task->lead) {
                app(MetaReviewAutoStageService::class)->applyForOutcome($task->lead, 'interested');
            }

            return response()->json($body, $response->getStatusCode());
        }

        if ($outcome === 'cnp') {
            $payload = [];
            if (!empty($validated['next_datetime'])) {
                $payload['retry_at'] = $validated['next_datetime'];
            }
            if (array_key_exists('remark', $validated)) {
                $payload['remark'] = $validated['remark'];
            }

            $childRequest = Request::create('/', 'POST', $payload);
            $childRequest->setUserResolver(fn () => $user);

            $response = $this->markAsCNP($childRequest, $task);
            $body = $response->getData(true);

            if (($body['success'] ?? false) !== true || $response->getStatusCode() >= 300) {
                return $response;
            }

            $body['outcome'] = 'cnp';

            if ($task->lead) {
                app(MetaReviewAutoStageService::class)->applyForOutcome($task->lead, 'cnp');
            }

            return response()->json($body, $response->getStatusCode());
        }

        $lead = $task->lead;
        $existingProspect = $lead?->currentCycleProspects()->latest()->first();
        $isInterestedPipeline = $lead
            && (
                ($lead->status ?? null) === 'verified_prospect'
                || in_array($existingProspect->verification_status ?? null, ['verified', 'approved'], true)
            );

        [$lead, $prospect] = $this->getOrCreateTaskLeadAndProspect(
            $task,
            $user,
            $request,
            $outcome !== 'follow_up' || $isInterestedPipeline
        );

        if (!$lead) {
            return response()->json([
                'success' => false,
                'error' => 'Lead not found',
            ], 404);
        }

        DB::beginTransaction();

        try {
            if ($outcome === 'not_interested') {
                app(\App\Services\LeadOutcomeService::class)->markAsOtherLead(
                    $task,
                    $lead,
                    $prospect,
                    $user,
                    'not_interested',
                    $validated['remark'] ?? null
                );

                app(MetaReviewAutoStageService::class)->applyForOutcome($lead, 'not_interested');

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Lead marked as not interested and retained under the current owner in Other Leads.',
                    'outcome' => 'not_interested',
                ]);
            }

            if ($outcome === 'follow_up') {
                $nextAt = $this->normalizeTaskDateTimeInput((string) $validated['next_datetime']);
                $remark = trim((string) ($validated['remark'] ?? ''));
                $payload = $request->input('lead_form_payload', []);
                if (!is_array($payload) || empty($payload)) {
                    $payload = $request->except(['outcome', 'next_datetime', 'remark', 'lead_form_payload']);
                }

                $task->markAsCompleted();
                $this->recordTaskOutcome($task, 'follow_up', $remark ?: null, $nextAt);
                $this->asmCnpAutomation()->cancelTaskStageAutomation($task, 'Lead moved to follow-up flow.');

                $followUpTask = $this->createScheduledPhoneTask(
                    $lead,
                    $user->id,
                    $user->id,
                    $nextAt,
                    "Follow-up call: {$lead->name}",
                    "Follow-up call task scheduled for {$nextAt->format('Y-m-d H:i')}.",
                    $remark ?: "Follow-up scheduled for {$nextAt->format('Y-m-d H:i')}"
                );

                $lead->next_followup_at = $nextAt;
                $lead->notes = $this->appendNote($lead->notes, '[' . now()->format('Y-m-d H:i:s') . '] ASM outcome: Follow Up scheduled for ' . $nextAt->format('Y-m-d H:i:s'));
                $lead->save();
                if ($this->hasRequirementPayload($payload)) {
                    $this->validateRequiredRequirementPayload($payload);
                    $prospect = $this->saveRequirementPayloadForLead($lead, $user, $payload, $remark ?: "Follow-up scheduled for {$nextAt->format('Y-m-d H:i')}");
                }
                if ($prospect && $isInterestedPipeline) {
                    $prospect->update([
                        'verification_status' => 'verified',
                        'verified_at' => now(),
                        'verified_by' => $user->id,
                        'manager_remark' => $remark ?: "Follow-up scheduled for {$nextAt->format('Y-m-d H:i')}",
                    ]);
                }

                $this->completeSiblingPodTasks($lead, $user->id, [$task->id, $followUpTask->id]);

                app(MetaReviewAutoStageService::class)->applyForOutcome($lead, 'follow_up');

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Follow-up task created successfully.',
                    'outcome' => 'follow_up',
                    'task' => $followUpTask,
                ]);
            }

            app(\App\Services\LeadOutcomeService::class)->markAsOtherLead(
                $task,
                $lead,
                $prospect,
                $user,
                'junk',
                $validated['remark'] ?? null
            );

            app(MetaReviewAutoStageService::class)->applyForOutcome($lead, 'junk');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead marked as junk and retained under the current owner in Other Leads.',
                'outcome' => 'junk',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Submit Task Outcome Error', [
                'task_id' => $task->id,
                'outcome' => $outcome,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to submit task outcome: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getOrCreateTaskLeadAndProspect(Task $task, User $user, Request $request, bool $createVerifiedProspect = true): array
    {
        $lead = $task->lead;
        if (!$lead) {
            return [null, null];
        }

        $prospect = $lead->currentCycleProspects()->latest()->first();

        if (!$prospect && $createVerifiedProspect) {
            $prospect = Prospect::create([
                'lead_id' => $lead->id,
                'customer_name' => $request->input('name', $lead->name),
                'phone' => $request->input('phone', $lead->phone),
                'manager_id' => $user->id,
                'assigned_manager' => $user->id,
                'created_by' => $user->id,
                'verification_status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $user->id,
            ]);
        }

        return [$lead, $prospect];
    }

    private function hasRequirementPayload(array $payload): bool
    {
        foreach (['budget', 'preferred_location', 'category', 'type', 'purpose', 'possession', 'lead_status', 'lead_quality', 'interested_projects'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_array($value) ? !empty($value) : filled($value)) {
                return true;
            }
        }

        return false;
    }

    private function validateRequiredRequirementPayload(array $payload): void
    {
        Validator::make($payload, [
            'preferred_location' => ['required', 'string', 'max:255'],
            'budget' => ['required', 'string', 'max:255'],
        ], [
            'preferred_location.required' => 'Preferred location is required.',
            'budget.required' => 'Budget is required.',
        ])->validate();
    }

    private function saveRequirementPayloadForLead(Lead $lead, User $user, array $payload, ?string $managerRemark = null): Prospect
    {
        $prospect = $lead->currentCycleProspects()->latest()->first();
        if (!$prospect) {
            $prospect = Prospect::create([
                'lead_id' => $lead->id,
                'customer_name' => $payload['name'] ?? $lead->name,
                'phone' => $payload['phone'] ?? $lead->phone,
                'manager_id' => $user->id,
                'assigned_manager' => $user->id,
                'created_by' => $user->id,
                'verification_status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $user->id,
            ]);
        }

        $purpose = $this->normalizeProspectPurpose($payload['purpose'] ?? null);
        $prospect->update([
            'customer_name' => $payload['name'] ?? $lead->name,
            'phone' => $payload['phone'] ?? $lead->phone,
            'budget' => $payload['budget'] ?? $prospect->budget,
            'preferred_location' => $payload['preferred_location'] ?? $prospect->preferred_location,
            'size' => $payload['size'] ?? $payload['preferred_size'] ?? $prospect->size,
            'purpose' => $purpose ?? $prospect->purpose,
            'possession' => $payload['possession'] ?? $prospect->possession,
            'lead_status' => $payload['lead_status'] ?? $prospect->lead_status,
            'lead_score' => $payload['lead_quality'] ?? $payload['lead_score'] ?? $prospect->lead_score,
            'manager_remark' => $managerRemark ?? $payload['manager_remark'] ?? $prospect->manager_remark,
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $user->id,
            'rejection_reason' => null,
        ]);

        if (!empty($payload['interested_projects']) && is_array($payload['interested_projects'])) {
            $projectIds = $this->resolveInterestedProjectIdsFromSelection($payload['interested_projects'], $user->id);
            if (!empty($projectIds)) {
                $prospect->interestedProjects()->sync($projectIds);
            }
        }

        $this->syncLeadRequirementSummary($lead, $payload, $prospect);

        return $prospect;
    }

    private function normalizeProspectPurpose(?string $purposeRaw): ?string
    {
        return match ($purposeRaw) {
            'End Use' => 'end_user',
            'Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use' => 'investment',
            'N.A', '', null => null,
            default => $purposeRaw,
        };
    }

    private function syncLeadRequirementSummary(Lead $lead, array $payload, Prospect $prospect): void
    {
        $setIfPresent = function (string $column, $value) use ($lead): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }
            if (!\Illuminate\Support\Facades\Schema::hasColumn('leads', $column)) {
                return;
            }
            $lead->{$column} = trim((string) $value);
        };

        $setIfPresent('category', $payload['category'] ?? null);
        $setIfPresent('property_category', $payload['category'] ?? null);
        $setIfPresent('type', $payload['type'] ?? null);
        $setIfPresent('property_type', $payload['type'] ?? null);
        $setIfPresent('budget', $payload['budget'] ?? $prospect->budget);
        $setIfPresent('budget_range', $payload['budget'] ?? $prospect->budget);
        $setIfPresent('preferred_location', $payload['preferred_location'] ?? $prospect->preferred_location);
        $setIfPresent('location', $payload['preferred_location'] ?? $prospect->preferred_location);
        $setIfPresent('preferred_size', $payload['size'] ?? $payload['preferred_size'] ?? $prospect->size);
        $setIfPresent('purpose', $payload['purpose'] ?? $prospect->purpose);
        $setIfPresent('possession_status', $payload['possession'] ?? $prospect->possession);

        if (\Illuminate\Support\Facades\Schema::hasColumn('leads', 'form_filled_by_manager')) {
            $lead->form_filled_by_manager = true;
        }

        $lead->save();
    }

    private function deactivateLeadAssignments(Lead $lead): void
    {
        $lead->assignments()
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);
    }

    private function recordTaskOutcome(Task $task, string $outcome, ?string $remark = null, ?Carbon $nextActionAt = null): void
    {
        $task->update([
            'outcome' => $outcome,
            'outcome_remark' => $remark,
            'outcome_recorded_at' => now(),
            'next_action_at' => $nextActionAt,
        ]);
    }

    private function createScheduledPhoneTask(Lead $lead, int $assignedTo, int $createdBy, Carbon $scheduledAt, string $title, string $description, ?string $notes = null): Task
    {
        app(\App\Services\LeadSingleOpenTaskService::class)->closeOpenTasksForLead($lead->id);

        return Task::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo,
            'type' => 'phone_call',
            'title' => $title,
            'description' => $description,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
            'created_by' => $createdBy,
            'notes' => $notes,
        ]);
    }

    private function completeSiblingPodTasks(Lead $lead, int $assignedTo, array $exceptTaskIds = []): void
    {
        $exceptIds = collect($exceptTaskIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $stalePodTasks = Task::withoutGlobalScopes()
            ->where('lead_id', $lead->id)
            ->where('assigned_to', $assignedTo)
            ->where('type', 'phone_call')
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereDate('scheduled_at', '<', Carbon::today())
            ->when($exceptIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $exceptIds->all()))
            ->get();

        foreach ($stalePodTasks as $staleTask) {
            $staleTask->markAsCompleted();
        }
    }

    private function appendNote(?string $existing, string $entry): string
    {
        $existing = trim((string) $existing);

        return $existing !== '' ? $existing . "\n\n" . $entry : $entry;
    }

    /**
     * Verify prospect from task (with full form data)
     */
    public function verifyProspectFromTask(Request $request, Task $task)
    {
        try {
            $user = $request->user();
            
            // Verify task is assigned to current user (admin/crm can view all)
            if ((int)$task->assigned_to !== (int)$user->id && !$user->isAdmin() && !$user->isCrm()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to task',
                ], 403);
            }
            
            $lead = $task->lead;
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'error' => 'Lead not found',
                ], 404);
            }
            
            // Get or find prospect
            $prospect = $lead->currentCycleProspects()->latest()->first();
            
            if (!$prospect) {
                // Direct-assigned/imported leads may not have a prospect yet.
                // Create one so manager form submission can proceed normally.
                $prospect = Prospect::create([
                    'lead_id' => $lead->id,
                    'customer_name' => $request->input('name', $lead->name),
                    'phone' => $request->input('phone', $lead->phone),
                    'manager_id' => $user->id,
                    'assigned_manager' => $user->id,
                    'created_by' => $user->id,
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'verified_by' => $user->id,
                ]);
            }
            
            // Validate basic fields
            $validationRules = [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'lead_status' => 'required|in:hot,warm,cold,junk',
                'lead_quality' => 'required|integer|between:1,5',
                'follow_up_required' => 'nullable|boolean',
                'follow_up_date' => 'nullable|required_if:follow_up_required,1|date',
            'defer_task_completion_for_visit' => 'nullable|boolean',
            'interested_projects' => 'required|array|min:1',
            'interested_projects.*' => function ($attribute, $value, $fail) {
                if (is_int($value) || is_numeric($value) || (is_array($value) && isset($value['name']))) {
                    return;
                }
                $fail('The ' . $attribute . ' must be a valid project selection.');
            },
            ];
            
            // Get all fields for manager (for dynamic validation)
            $allFields = LeadFormField::active()
                ->visibleToRole('sales_manager')
                ->get();
            
            // Add dynamic field validation
            foreach ($allFields as $field) {
                if ($field->is_required) {
                    $rule = ['required'];
                } else {
                    $rule = ['nullable'];
                }
                
                switch ($field->field_type) {
                    case 'email':
                        $rule[] = 'email';
                        break;
                    case 'number':
                        $rule[] = 'numeric';
                        break;
                    case 'date':
                        $rule[] = 'date';
                        break;
                    case 'time':
                        $rule[] = 'date_format:H:i';
                        break;
                }
                
                $validationRules[$field->field_key] = $rule;
            }

            $validationRules['preferred_location'] = ['required', 'string', 'max:255'];
            $validationRules['budget'] = ['required', 'string', 'max:255'];
            
            $validated = $request->validate($validationRules);
            
            DB::beginTransaction();
            
            try {
                // Update lead basic fields
                $lead->name = $validated['name'];
                $lead->phone = $validated['phone'];
                if ($request->has('email')) {
                    $lead->email = $request->input('email');
                }
                if ($request->has('address')) {
                    $lead->address = $request->input('address');
                }
                if ($request->has('city')) {
                    $lead->city = $request->input('city');
                }
                if ($request->has('state')) {
                    $lead->state = $request->input('state');
                }
                if ($request->has('pincode')) {
                    $lead->pincode = $request->input('pincode');
                }
                $lead->save();
                
                // Save dynamic form field values
                foreach ($allFields as $field) {
                    if ($request->has($field->field_key)) {
                        $value = $request->input($field->field_key);
                        if (!empty($value) || $field->is_required) {
                            $lead->setFormFieldValue($field->field_key, $value ?? '', $user->id);
                        }
                    }
                }
                
                // Save Customer Profiling fields (all optional)
                $customerProfilingFields = ['customer_job', 'industry_sector', 'buying_frequency', 'living_city', 'city_type'];
                foreach ($customerProfilingFields as $fieldKey) {
                    if ($request->has($fieldKey) && $request->input($fieldKey) !== null && $request->input($fieldKey) !== '') {
                        $lead->setFormFieldValue($fieldKey, $request->input($fieldKey), $user->id);
                    }
                }
                
                // Mark form as filled by manager
                $lead->form_filled_by_manager = true;
                $lead->save();
                
                // Map form values to prospect fields
                $budget = $request->input('budget');
                $preferredLocation = $request->input('preferred_location');
                $purposeRaw = $request->input('purpose');
                $possession = $request->input('possession');
                
                // Map purpose
                $purpose = $purposeRaw;
                if ($purposeRaw === 'End Use') {
                    $purpose = 'end_user';
                } elseif (in_array($purposeRaw, ['Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use'])) {
                    $purpose = 'investment';
                } elseif ($purposeRaw === 'N.A' || empty($purposeRaw)) {
                    $purpose = null;
                }
                
                // Get lead status and follow-up required flag
                $leadStatus = $validated['lead_status'];
                $leadQuality = $validated['lead_quality'];
                // Handle follow_up_required checkbox value (can be '1', '0', true, false, or 'true', 'false')
                $followUpRequiredValue = $request->input('follow_up_required', '0');
                $isFollowUpRequired = in_array($followUpRequiredValue, ['1', 'true', true, 1], true);
                $isFollowUp = $isFollowUpRequired; // Alias for consistency with other parts of the code
                
                // Update prospect - different logic for Follow Up Required vs normal verification
                if ($isFollowUpRequired) {
                    // Validate follow_up_date is present when follow_up_required is true (should already be validated, but double-check)
                    if (!isset($validated['follow_up_date']) || empty($validated['follow_up_date'])) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Validation failed',
                            'errors' => ['follow_up_date' => ['Follow Up Date is required when Follow Up Required is checked.']],
                        ], 422);
                    }
                    
                    // For Follow Up Required: Keep prospect as pending_verification, don't verify yet
                    $managerRemark = $request->input('manager_remark', '');
                    $followUpDateStr = $validated['follow_up_date'];
                    $followUpRemark = $managerRemark ? $managerRemark . ' | ' : '';
                    $followUpRemark .= 'Follow-up scheduled for ' . $followUpDateStr;
                    
                    $prospect->update([
                        'customer_name' => $validated['name'],
                        'phone' => $validated['phone'],
                        'budget' => $budget,
                        'preferred_location' => $preferredLocation,
                        'purpose' => $purpose,
                        'possession' => $possession,
                        'lead_status' => $leadStatus,
                        'lead_score' => $leadQuality, // Save lead quality to lead_score
                        'manager_remark' => $followUpRemark,
                        'verification_status' => 'verified',
                        'verified_at' => now(),
                        'verified_by' => $user->id,
                        'rejection_reason' => null,
                    ]);

                    // Keep the lead in the prospect pipeline when ASM selects follow-up after interested.
                    if ($lead) {
                        if (!$lead->canAutoUpdate()) {
                            $lead->enableAutoUpdate();
                        }
                        $lead->updateStatusIfAllowed('verified_prospect');
                        if ($lead->status !== 'verified_prospect') {
                            $lead->status = 'verified_prospect';
                            $lead->save();
                        }
                        $this->asmCnpAutomation()->cancelLeadAutomation($lead, 'Lead moved to interested follow-up pipeline.');
                    }
                    
                    // Create follow-up calling task for the selected date and time
                    $followUpDate = $this->normalizeTaskDateTimeInput((string) $validated['follow_up_date']);
                    
                    $followUpTask = $this->createScheduledPhoneTask(
                        $lead,
                        $user->id,
                        $user->id,
                        $followUpDate,
                        "Follow-up call: {$validated['name']}",
                        "Follow-up call task scheduled for {$followUpDate->format('Y-m-d H:i')}. Prospect requires follow-up call on selected date and time.",
                        $managerRemark ?: "Follow-up scheduled for {$followUpDate->format('Y-m-d H:i')}"
                    );
                    
                    \Log::info('Follow-up task created for manager', [
                        'current_task_id' => $task->id,
                        'new_follow_up_task_id' => $followUpTask->id,
                        'prospect_id' => $prospect->id,
                        'lead_id' => $lead->id,
                        'manager_id' => $user->id,
                        'follow_up_date' => $followUpDate->format('Y-m-d H:i'),
                        'lead_quality' => $leadQuality,
                    ]);
                    
                    // Check if telecaller task should also be created
                    $createTelecallerTaskValue = $request->input('create_telecaller_task', false);
                    $createTelecallerTask = in_array($createTelecallerTaskValue, ['1', 'true', true, 1], true);
                    
                    if ($createTelecallerTask) {
                        // Get telecaller from prospect
                        $telecallerId = $prospect->telecaller_id ?? $prospect->created_by;
                        
                        if ($telecallerId) {
                            $telecaller = User::find($telecallerId);
                            
                            if ($telecaller) {
                                $telecallerTaskService = app(\App\Services\TelecallerTaskService::class);
                                $telecallerTask = $telecallerTaskService->createScheduledCallingTask(
                                    $lead,
                                    $telecaller,
                                    $followUpDate,
                                    $user->id,
                                    "Follow-up calling task created by manager. Scheduled for {$followUpDate->format('Y-m-d H:i')}."
                                );
                                
                                \Log::info('Follow-up telecaller task created', [
                                    'telecaller_task_id' => $telecallerTask->id,
                                    'telecaller_id' => $telecallerId,
                                    'lead_id' => $lead->id,
                                    'follow_up_date' => $followUpDate->format('Y-m-d H:i'),
                                ]);
                            }
                        }
                    }
                } else {
                    // For normal verification (no follow-up): Verify prospect normally
                    $prospect->update([
                        'customer_name' => $validated['name'],
                        'phone' => $validated['phone'],
                        'budget' => $budget,
                        'preferred_location' => $preferredLocation,
                        'purpose' => $purpose,
                        'possession' => $possession,
                        'lead_status' => $leadStatus,
                        'lead_score' => $leadQuality, // Save lead quality to lead_score
                        'manager_remark' => $request->input('manager_remark'),
                        'verification_status' => 'verified',
                        'verified_at' => now(),
                        'verified_by' => $user->id,
                        'rejection_reason' => null, // Clear rejection reason if was rejected before
                    ]);
                }
                
                // Sync interested projects from the configured admin option list only.
                if ($request->has('interested_projects')) {
                    $projectIds = $this->resolveInterestedProjectIdsFromSelection(
                        (array) $request->input('interested_projects', []),
                        $user->id
                    );

                    if (!empty($projectIds)) {
                        $prospect->interestedProjects()->sync($projectIds);
                    }
                }

                $this->syncLeadRequirementSummary($lead, $request->all(), $prospect);
                
                // After verification, ensure lead is created/updated and status is set correctly
                if (!$isFollowUpRequired) {
                    // Ensure prospect status is saved as verified (double-check)
                    if ($prospect->verification_status !== 'verified') {
                        $prospect->verification_status = 'verified';
                        $prospect->verified_at = now();
                        $prospect->verified_by = $user->id;
                        $prospect->save();
                    }
                    
                    // Refresh prospect to get latest data
                    $prospect->refresh();
                    
                    // If prospect doesn't have a lead_id, create lead from prospect
                    if (!$prospect->lead_id) {
                        // Map prospect fields to lead fields
                        $leadData = [
                            'name' => $prospect->customer_name,
                            'phone' => $prospect->phone,
                            'email' => null,
                            'budget' => $prospect->budget,
                            'preferred_location' => $prospect->preferred_location,
                            'preferred_size' => $prospect->size,
                            'use_end_use' => $prospect->purpose === 'end_user' ? 'End User' : ($prospect->purpose === 'investment' ? '2nd Investments' : null),
                            'possession_status' => $prospect->possession,
                            'source' => \App\Models\Lead::normalizeSource('call'),
                            'status' => 'verified_prospect',
                            'created_by' => $user->id,
                        ];
                        
                        // Combine remarks in notes
                        $notes = [];
                        if ($prospect->remark) {
                            $notes[] = "Telecaller Remark: " . $prospect->remark;
                        }
                        if ($prospect->manager_remark) {
                            $notes[] = "Manager Remark: " . $prospect->manager_remark;
                        }
                        if (!empty($notes)) {
                            $leadData['notes'] = implode("\n\n", $notes);
                        }
                        
                        // Add requirements if available
                        if ($prospect->notes) {
                            $leadData['requirements'] = $prospect->notes;
                        }
                        
                        $lead = Lead::create($leadData);
                        $prospect->lead_id = $lead->id;
                        $prospect->save();
                        
                        // Assign lead to the manager who verified
                        $assignedTo = $prospect->manager_id ?? ($prospect->telecaller ? $prospect->telecaller->manager_id : null) ?? $user->id;
                        
                        // Deactivate existing assignments for this lead
                        LeadAssignment::where('lead_id', $lead->id)->update([
                            'is_active' => false,
                            'unassigned_at' => now()
                        ]);
                        
                        // Create new assignment
                        LeadAssignment::create([
                            'lead_id' => $lead->id,
                            'assigned_to' => $assignedTo,
                            'assigned_by' => $user->id,
                            'assignment_type' => 'primary',
                            'assigned_at' => now(),
                            'is_active' => true,
                        ]);
                        
                        // Fire LeadAssigned event
                        event(new \App\Events\LeadAssigned($lead, $assignedTo, $user->id));
                        
                        // Lead status is already set to 'verified_prospect' in creation
                    } else {
                        // If lead already exists, update its status to verified_prospect
                        $lead = Lead::find($prospect->lead_id);
                        if ($lead) {
                            // Enable auto-update if disabled, then update status
                            if (!$lead->canAutoUpdate()) {
                                $lead->enableAutoUpdate();
                            }
                            $lead->updateStatusIfAllowed('verified_prospect');
                            
                            // If updateStatusIfAllowed failed (shouldn't happen now), force update
                            if ($lead->status !== 'verified_prospect') {
                                $lead->status = 'verified_prospect';
                                $lead->save();
                            }
                        }
                    }
                    
                    // Final save to ensure all changes are persisted
                    $prospect->save();
                }
                
                $shouldDeferTaskCompletionForVisit = $request->boolean('defer_task_completion_for_visit');
                if (!$shouldDeferTaskCompletionForVisit) {
                    $task->markAsCompleted();
                }
                
                // Send notification to telecaller when prospect is verified (not for Follow Up Required)
                if (!$isFollowUpRequired && $prospect->verification_status === 'verified' && $prospect->telecaller_id) {
                    try {
                        $telecaller = \App\Models\User::find($prospect->telecaller_id);
                        if ($telecaller) {
                            $notificationService = new NotificationService();
                            
                            $managerRemark = $request->input('manager_remark', '');
                            $remarkText = $managerRemark ? "\nManager Remark: {$managerRemark}" : '';
                            
                            $title = "Prospect Verified: {$lead->name}";
                            $message = "Manager {$user->name} verified prospect {$lead->name}. Status: " . ucfirst($leadStatus) . "{$remarkText}";
                            $actionUrl = url('/telecaller/verification-pending');
                            
                            $notificationData = [
                                'lead_id' => $lead->id,
                                'lead_name' => $lead->name,
                                'lead_phone' => $lead->phone,
                                'prospect_id' => $prospect->id,
                                'verification_status' => 'verified',
                                'lead_status' => $leadStatus,
                                'manager_remark' => $managerRemark,
                                'verified_at' => now()->toIso8601String(),
                                'manager_name' => $user->name,
                                'manager_id' => $user->id,
                            ];
                            
                            $notificationService->notifyNewVerification(
                                $telecaller,
                                'verified',
                                $title,
                                $message,
                                $actionUrl,
                                $notificationData
                            );
                            
                            \Log::info('Verification notification sent to telecaller', [
                                'telecaller_id' => $telecaller->id,
                                'prospect_id' => $prospect->id,
                                'lead_id' => $lead->id,
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Log notification error but don't fail the verification
                        \Log::error('Failed to send verification notification to telecaller', [
                            'error' => $e->getMessage(),
                            'telecaller_id' => $prospect->telecaller_id,
                            'prospect_id' => $prospect->id,
                        ]);
                    }
                }
                
                // Fire event for telecaller achievement (if prospect is verified, count towards telecaller)
                // Only fire event if prospect is actually verified (not for Follow Up)
                if (!$isFollowUp) {
                    // This will be handled by existing event listeners if needed
                }
                
                // Final verification: Ensure prospect status is 'verified' before committing
                if (!$isFollowUpRequired) {
                    $prospect->refresh();
                    if ($prospect->verification_status !== 'verified') {
                        $prospect->verification_status = 'verified';
                        $prospect->verified_at = now();
                        $prospect->verified_by = $user->id;
                        $prospect->save();
                    }
                    $this->asmCnpAutomation()->cancelLeadAutomation($lead, 'Lead converted to verified prospect.');
                }
                
                DB::commit();
                
                // Get lead ID for logging
                $leadId = $prospect->lead_id ?? null;
                
                $logMessage = $isFollowUp 
                    ? 'Follow-up task created for prospect from manager task'
                    : 'Prospect verified successfully from manager task';
                    
                \Log::info($logMessage, [
                    'task_id' => $task->id,
                    'prospect_id' => $prospect->id,
                    'lead_id' => $leadId,
                    'manager_id' => $user->id,
                    'lead_status' => $leadStatus,
                    'is_follow_up' => $isFollowUp,
                    'follow_up_date' => $isFollowUp ? $validated['follow_up_date'] ?? null : null,
                    'prospect_verification_status' => $prospect->verification_status,
                ]);
                
                $responseMessage = $isFollowUp 
                    ? 'Follow-up task created successfully. Prospect will be called on the selected date.'
                    : 'Prospect verified successfully';
                
                // Refresh prospect one more time to get latest data
                $prospect->refresh();
                
                return response()->json([
                    'success' => true,
                    'message' => $responseMessage,
                    'prospect' => $prospect->fresh(['manager', 'telecaller', 'lead']),
                    'is_follow_up' => $isFollowUp,
                ], 200);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
            } catch (\Illuminate\Validation\ValidationException $e) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors(),
                ], 422);
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Verify Prospect From Task Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'task_id' => $task->id,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to verify prospect: ' . $e->getMessage(),
                ], 500);
            }
        }

    /**
     * Reject prospect from task
     */
    public function rejectProspectFromTask(Request $request, Task $task)
    {
        try {
            $user = $request->user();
            
            // Verify task is assigned to current user (admin/crm can view all)
            if ((int)$task->assigned_to !== (int)$user->id && !$user->isAdmin() && !$user->isCrm()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to task',
                ], 403);
            }
            
            $request->validate([
                'rejection_reason' => 'required|string|max:1000',
            ]);
            
            $lead = $task->lead;
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'error' => 'Lead not found',
                ], 404);
            }

            if ($this->asmCnpAutomation()->isFreshLeadTask($task)) {
                $result = $this->asmCnpAutomation()->handleFreshLeadCnp($task, $user);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'current_task' => $task->fresh(),
                    'retry_task' => $result['retry_task'],
                    'automation_state' => $result['state'],
                    'auto_retry' => true,
                ], 200);
            }
            
            DB::beginTransaction();
            
            try {
                // Verification flow removed - keep prospect verified even when old reject action is used.
                $rejectionReason = $request->input('rejection_reason');
                $prospect->update([
                    'verification_status' => 'verified',
                    'rejection_reason' => null,
                    'verified_by' => $user->id,
                    'verified_at' => now(),
                    'manager_remark' => $rejectionReason,
                ]);
                
                // Mark task as completed
                $task->markAsCompleted();
                $this->asmCnpAutomation()->cancelLeadAutomation($lead, 'Lead prospect rejected.');
                
                // Send notification to telecaller when prospect is rejected
                if ($prospect->telecaller_id) {
                    try {
                        $telecaller = \App\Models\User::find($prospect->telecaller_id);
                        if ($telecaller) {
                            $notificationService = new NotificationService();
                            
                            $title = "Prospect Updated: {$lead->name}";
                            $message = "Manager {$user->name} updated prospect {$lead->name}. Remark: {$rejectionReason}";
                            $actionUrl = url('/telecaller/verification-pending');
                            
                            $notificationData = [
                                'lead_id' => $lead->id,
                                'lead_name' => $lead->name,
                                'lead_phone' => $lead->phone,
                                'prospect_id' => $prospect->id,
                                'verification_status' => 'verified',
                                'manager_remark' => $rejectionReason,
                                'updated_at' => now()->toIso8601String(),
                                'manager_name' => $user->name,
                                'manager_id' => $user->id,
                            ];
                            
                            $notificationService->notifyNewVerification(
                                $telecaller,
                                'verified',
                                $title,
                                $message,
                                $actionUrl,
                                $notificationData
                            );
                            
                            \Log::info('Rejection notification sent to telecaller', [
                                'telecaller_id' => $telecaller->id,
                                'prospect_id' => $prospect->id,
                                'lead_id' => $lead->id,
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Log notification error but don't fail the rejection
                        \Log::error('Failed to send rejection notification to telecaller', [
                            'error' => $e->getMessage(),
                            'telecaller_id' => $prospect->telecaller_id,
                            'prospect_id' => $prospect->id,
                        ]);
                    }
                }
                
                DB::commit();
                
                \Log::info('Prospect rejected from manager task', [
                    'task_id' => $task->id,
                    'prospect_id' => $prospect->id,
                    'lead_id' => $lead->id,
                    'manager_id' => $user->id,
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Prospect marked as verified successfully',
                    'prospect' => $prospect->fresh(['manager', 'telecaller']),
                ], 200);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Reject Prospect From Task Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $task->id,
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to reject prospect: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark prospect as CNP (Call Not Picked) and create retry task at selected time
     */
    public function markAsCNP(Request $request, Task $task)
    {
        try {
            $user = $request->user();
            
            // Verify task is assigned to current user (admin/crm can view all)
            if ((int)$task->assigned_to !== (int)$user->id && !$user->isAdmin() && !$user->isCrm()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to task',
                ], 403);
            }
            
            // Validate retry time (retry_at OR retry_minutes)
            $request->validate([
                'retry_at' => 'nullable|date|after:now',
                'retry_minutes' => 'nullable|integer|min:1|max:10080', // Max 1 week (10080 minutes)
                'remark' => 'nullable|string|max:2000',
            ], [
                'retry_at.after' => 'Retry time must be in the future',
                'retry_minutes.max' => 'Retry time cannot be more than 1 week in the future',
            ]);
            
            $lead = $task->lead;
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'error' => 'Lead not found',
                ], 404);
            }

            if ((int) $task->assigned_to === (int) $user->id
                && ($user->isAssistantSalesManager() || $user->isSeniorManager() || $user->isSalesManager())
                && $this->asmCnpAutomation()->isFreshLeadTask($task)) {
                $requestedRetryAt = null;
                if ($request->has('retry_at') && $request->retry_at) {
                    $requestedRetryAt = $this->normalizeTaskDateTimeInput((string) $request->retry_at);
                } elseif ($request->has('retry_minutes') && $request->retry_minutes) {
                    $requestedRetryAt = now()->addMinutes((int) $request->retry_minutes);
                }

                $result = $this->asmCnpAutomation()->handleFreshLeadCnp($task, $user, $requestedRetryAt);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'current_task' => $task->fresh(),
                    'retry_task' => $result['retry_task'],
                    'automation_state' => $result['state'],
                    'auto_retry' => true,
                ], 200);
            }
            
            DB::beginTransaction();
            
            try {
                // Calculate scheduled time based on selection
                $retryScheduledAt = null;
                $timeDescription = '';
                $remark = trim((string) $request->input('remark', ''));
                
                if ($request->has('retry_at') && $request->retry_at) {
                    // Custom datetime provided
                    $retryScheduledAt = $this->normalizeTaskDateTimeInput((string) $request->retry_at);
                    $timeDescription = $retryScheduledAt->format('d M Y, h:i A');
                } elseif ($request->has('retry_minutes') && $request->retry_minutes) {
                    // Quick option (minutes) provided
                    $retryScheduledAt = now()->addMinutes($request->retry_minutes);
                    $timeDescription = "in {$request->retry_minutes} minutes ({$retryScheduledAt->format('d M Y, h:i A')})";
                } else {
                    // Default: 2 hours later (backward compatibility)
                    $retryScheduledAt = now()->addHours(2);
                    $timeDescription = "in 2 hours ({$retryScheduledAt->format('d M Y, h:i A')})";
                }
                
                // Ensure scheduled time is in future
                if ($retryScheduledAt->isPast()) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => 'Retry time must be in the future',
                    ], 422);
                }
                
                $cnpNote = "Call Not Picked - Rescheduled for {$timeDescription} on " . now()->format('Y-m-d H:i:s');
                $cnpNoteWithRemark = $remark !== '' ? $cnpNote . " | Remark: {$remark}" : $cnpNote;
                $currentDescription = $task->description ?? '';
                $task->update([
                    'description' => $currentDescription . ($currentDescription ? "\n\n" : '') . $cnpNoteWithRemark,
                    'notes' => ($task->notes ?? '') . ($task->notes ? "\n\n" : '') . $cnpNoteWithRemark,
                ]);

                $task->markAsCompleted();
                $this->recordTaskOutcome(
                    $task,
                    'cnp',
                    $remark !== '' ? $remark : null,
                    $retryScheduledAt
                );
                
                $retryTaskTitle = $this->formatCnpRetryTaskTitle($lead, $retryScheduledAt);
                $retryTaskDescription = "Retry call {$timeDescription} - previous call not picked. Original task ID: {$task->id}";
                
                $retryTask = $this->createScheduledPhoneTask(
                    $lead,
                    $user->id,
                    $user->id,
                    $retryScheduledAt,
                    $retryTaskTitle,
                    $retryTaskDescription,
                    "CNP retry task created from task #{$task->id} on " . now()->format('Y-m-d H:i:s') . " - Scheduled for {$timeDescription}" . ($remark !== '' ? " | Remark: {$remark}" : '')
                );

                $this->completeSiblingPodTasks($lead, $user->id, [$task->id, $retryTask->id]);
                
                // Prospect status remains pending_verification (no change)
                // This is already the case, so no update needed
                
                DB::commit();
                
                \Log::info('Prospect marked as CNP - Retry task created', [
                    'current_task_id' => $task->id,
                    'new_retry_task_id' => $retryTask->id,
                    'lead_id' => $lead->id,
                    'manager_id' => $user->id,
                    'retry_scheduled_at' => $retryScheduledAt->format('Y-m-d H:i:s'),
                    'retry_minutes' => $request->retry_minutes ?? null,
                    'retry_at' => $request->retry_at ?? null,
                ]);
                
                $successMessage = $request->has('retry_at') || $request->has('retry_minutes')
                    ? "Call Not Picked marked. New calling task created {$timeDescription}."
                    : 'Call Not Picked marked. New calling task created for 2 hours later.';
                
                return response()->json([
                    'success' => true,
                    'message' => $successMessage,
                    'current_task' => $task->fresh(),
                    'retry_task' => $retryTask,
                    'retry_scheduled_at' => $retryScheduledAt->format('Y-m-d H:i:s'),
                    'time_description' => $timeDescription,
                ], 200);
                
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Mark as CNP Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $task->id ?? null,
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark as CNP: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete a task
     */
    public function completeTask(Request $request, Task $task)
    {
        try {
            $user = $request->user();
            
            // Verify task is assigned to current user (admin/crm can view all)
            if ((int)$task->assigned_to !== (int)$user->id && !$user->isAdmin() && !$user->isCrm()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to task',
                ], 403);
            }
            
            // Check if task is already completed
            if ($task->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'message' => 'Task already completed',
                    'data' => $task,
                ]);
            }
            
            // Mark task as completed
            $task->markAsCompleted();
            
            return response()->json([
                'success' => true,
                'message' => 'Task completed successfully',
                'data' => $task->fresh(),
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error completing task: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to complete task: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function executeTaskWorkflowAction(Request $request, Task $task)
    {
        $user = $request->user();
        $workflow = (string) $request->input('workflow', '');

        if (in_array($workflow, ['visit_complete', 'visit_reschedule'], true)) {
            $task = $this->resolveVisitWorkflowTaskFromRequest($request, $task, $user);
        }

        if ((int) $task->assigned_to !== (int) $user->id && !$user->isAdmin() && !$user->isCrm()) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access to task',
            ], 403);
        }

        $validated = $request->validate([
            'workflow' => 'required|in:meeting_complete,meeting_send_to_closer,visit_complete,meeting_reschedule,visit_reschedule',
            'meeting_id' => 'nullable|integer|exists:meetings,id',
            'site_visit_id' => 'nullable|integer|exists:site_visits,id',
            'outcome' => 'nullable|in:interested,visited,schedule_visit,schedule_follow_up,not_interested,junk,schedule_meeting,customer_not_available,cancelled,follow_up_needed',
            'feedback' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'notes' => 'nullable|string',
            'remark' => 'nullable|string|max:2000',
            'scheduled_at' => 'nullable|date|after:now',
            'location' => 'nullable|string|max:1000',
            'project' => 'nullable|string|max:255',
            'budget_range' => 'nullable|string|max:255',
            'visited_projects' => 'nullable|string|max:1000',
            'visited_property_types' => 'nullable|array',
            'visited_property_types.*' => 'string|in:plot,villa,apartment,commercial,other',
            'tentative_closing_time' => 'nullable|string|max:255',
            'meeting_mode' => 'nullable|in:online,offline',
            'meeting_link' => 'nullable|string|max:1000',
            'reminder_enabled' => 'nullable|boolean',
            'proof_photos' => 'nullable|array|min:1',
            'proof_photos.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $workflowValidator = Validator::make($request->all(), []);
        $workflowValidator->after(function ($validator) use ($request, $validated) {
            $workflow = (string) ($validated['workflow'] ?? '');
            $outcome = (string) ($validated['outcome'] ?? '');

            if ($workflow === 'visit_complete') {
                if (!in_array($outcome, ['visited', 'customer_not_available', 'cancelled', 'follow_up_needed'], true)) {
                    $validator->errors()->add('outcome', 'A valid visit outcome is required.');
                }

                if ($outcome === 'visited' && !$request->hasFile('proof_photos')) {
                    $validator->errors()->add('proof_photos', 'At least one proof photo is required to complete a site visit.');
                }

                if (in_array($outcome, ['customer_not_available', 'cancelled'], true) && blank($validated['remark'] ?? null)) {
                    $validator->errors()->add('remark', 'Remark is required for this visit outcome.');
                }

                if ($outcome === 'follow_up_needed') {
                    if (blank($validated['scheduled_at'] ?? null)) {
                        $validator->errors()->add('scheduled_at', 'Follow-up date and time are required.');
                    }

                    if (blank($validated['remark'] ?? null)) {
                        $validator->errors()->add('remark', 'Remark is required to schedule a follow-up.');
                    }
                }
            }

            if ($workflow === 'visit_reschedule') {
                if (blank($validated['scheduled_at'] ?? null)) {
                    $validator->errors()->add('scheduled_at', 'Visit date and time are required.');
                }

                if (blank($validated['remark'] ?? null)) {
                    $validator->errors()->add('remark', 'Reason is required for rescheduling.');
                }
            }
        });

        if ($workflowValidator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $workflowValidator->errors(),
            ], 422);
        }

        try {
            $lead = $task->lead;
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lead not found for this task.',
                ], 404);
            }

            $workflowService = app(\App\Services\LeadDetailTaskWorkflowService::class);

            if ($validated['workflow'] === 'meeting_send_to_closer') {
                $meetingId = (int) ($validated['meeting_id'] ?? $task->meeting_id);
                $meeting = Meeting::find($meetingId);
                if (!$meeting || (int) $meeting->lead_id !== (int) $lead->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Meeting not found for this task.',
                    ], 404);
                }

                $missing = [];
                if (blank($validated['project'] ?? null)) {
                    $missing['project'] = ['Project is required.'];
                }
                if (blank($request->input('budget_range'))) {
                    $missing['budget_range'] = ['Budget range is required.'];
                }
                if (blank($validated['remark'] ?? null)) {
                    $missing['remark'] = ['Meeting summary is required.'];
                }

                if (!empty($missing)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $missing,
                    ], 422);
                }

                $result = $workflowService->sendMeetingToCloser($task, $meeting, $lead, $user, [
                    'project' => $validated['project'] ?? null,
                    'budget_range' => $request->input('budget_range'),
                    'remark' => $validated['remark'] ?? null,
                    'proof_photos' => $request->file('proof_photos', []),
                ]);

                return response()->json(['success' => true] + $result);
            }

            if ($validated['workflow'] === 'meeting_complete') {
                $meetingId = (int) ($validated['meeting_id'] ?? $task->meeting_id);
                $meeting = Meeting::find($meetingId);
                if (!$meeting || (int) $meeting->lead_id !== (int) $lead->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Meeting not found for this task.',
                    ], 404);
                }

                $result = $workflowService->completeMeeting($task, $meeting, $lead, $user, [
                    'feedback' => $validated['feedback'] ?? null,
                    'rating' => $validated['rating'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'outcome' => $validated['outcome'] ?? null,
                    'remark' => $validated['remark'] ?? null,
                    'scheduled_at' => $validated['scheduled_at'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'project' => $validated['project'] ?? null,
                    'reminder_enabled' => (bool) ($validated['reminder_enabled'] ?? false),
                    'proof_photos' => $request->file('proof_photos', []),
                ]);

                return response()->json(['success' => true] + $result);
            }

            if ($validated['workflow'] === 'visit_complete') {
                $siteVisitId = (int) ($validated['site_visit_id'] ?? $this->resolveTaskSiteVisitId($task));
                $siteVisit = SiteVisit::find($siteVisitId);
                if (!$siteVisit || (int) $siteVisit->lead_id !== (int) $lead->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Site visit not found for this task.',
                    ], 404);
                }

                $result = $workflowService->completeVisit($task, $siteVisit, $lead, $user, [
                    'feedback' => $validated['feedback'] ?? null,
                    'rating' => $validated['rating'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'outcome' => $validated['outcome'] ?? null,
                    'remark' => $validated['remark'] ?? null,
                    'scheduled_at' => $validated['scheduled_at'] ?? null,
                    'location' => $validated['location'] ?? null,
                    'meeting_mode' => $validated['meeting_mode'] ?? 'online',
                    'meeting_link' => $validated['meeting_link'] ?? null,
                    'reminder_enabled' => (bool) ($validated['reminder_enabled'] ?? false),
                    'visited_projects' => $validated['visited_projects'] ?? null,
                    'visited_property_types' => $validated['visited_property_types'] ?? [],
                    'tentative_closing_time' => $validated['tentative_closing_time'] ?? null,
                    'proof_photos' => $request->file('proof_photos', []),
                ]);

                return response()->json(['success' => true] + $result);
            }

            if ($validated['workflow'] === 'visit_reschedule') {
                $siteVisitId = (int) ($validated['site_visit_id'] ?? $this->resolveTaskSiteVisitId($task));
                $siteVisit = SiteVisit::find($siteVisitId);
                if (!$siteVisit || (int) $siteVisit->lead_id !== (int) $lead->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Site visit not found for this task.',
                    ], 404);
                }

                $result = $workflowService->rescheduleVisit(
                    $task,
                    $siteVisit,
                    $user,
                    Carbon::parse($validated['scheduled_at']),
                    trim((string) ($validated['remark'] ?? $validated['notes'] ?? 'Site visit rescheduled'))
                );

                return response()->json(['success' => true] + $result);
            }

            $meetingId = (int) ($validated['meeting_id'] ?? $task->meeting_id);
            $meeting = Meeting::find($meetingId);
            if (!$meeting || (int) $meeting->lead_id !== (int) $lead->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Meeting not found for this task.',
                ], 404);
            }

            $result = $workflowService->rescheduleMeeting(
                $task,
                $meeting,
                $user,
                Carbon::parse($validated['scheduled_at']),
                trim((string) ($validated['remark'] ?? $validated['notes'] ?? 'Meeting rescheduled'))
            );

            return response()->json(['success' => true] + $result);
        } catch (\Throwable $e) {
            Log::error('Task workflow action failed', [
                'task_id' => $task->id,
                'workflow' => $validated['workflow'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove all overdue tasks for the manager
     * Marks all overdue tasks as completed
     */
    public function removeAllOverdueTasks(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get all overdue tasks assigned to this manager using the shared grace cutoff.
            $overdueCutoff = $this->overdueCutoff();
            $overdueTasks = Task::where('assigned_to', $user->id)
                ->where('type', 'phone_call')
                ->whereIn('status', ['pending', 'in_progress'])
                ->where('scheduled_at', '<', $overdueCutoff)
                ->get();
            
            $count = 0;
            foreach ($overdueTasks as $task) {
                $task->markAsCompleted();
                $count++;
            }
            
            \Log::info('Removed all overdue tasks for manager', [
                'manager_id' => $user->id,
                'count' => $count,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Successfully removed {$count} overdue task(s)",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            \Log::error('Remove All Overdue Tasks Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'manager_id' => $request->user()->id ?? null,
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to remove overdue tasks: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Schedule a call task for a lead
     */
    public function scheduleCallTask(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
            'scheduled_at' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        // Check if scheduled_at is in the future (with 1 minute buffer for timezone issues)
        $scheduledAt = $this->normalizeTaskDateTimeInput((string) $request->scheduled_at);
        if ($scheduledAt->isPast() && $scheduledAt->diffInMinutes(now()) < -1) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduled time must be in the future',
                'errors' => ['scheduled_at' => ['The scheduled time must be in the future']],
            ], 422);
        }

        try {
            $lead = Lead::findOrFail($request->lead_id);
            $notes = $request->notes ?? null;
            
            Log::info('Schedule Call Task Request', [
                'user_id' => $user->id,
                'lead_id' => $request->lead_id,
                'scheduled_at' => $scheduledAt->toDateTimeString(),
                'user_role' => $user->role->slug ?? 'unknown',
            ]);

            // Check if user has permission to access this lead
            if (!$this->canAccessLead($user, $lead)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to create a task for this lead.',
                ], 403);
            }

            // Determine who the task should be assigned to
            // If lead is assigned to someone, assign task to them, otherwise assign to current user
            $assignedTo = $lead->activeAssignments->first()?->assigned_to ?? $user->id;

            // Create task based on assigned user's role (not current user's role)
            $assignedUser = User::with('role')->find($assignedTo);

            $existingOpenTask = $this->findExistingManualOpenTask($lead->id, $assignedTo, $assignedUser);
            if ($existingOpenTask !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please complete old task first.',
                    'errors' => [
                        'lead_id' => ['Please complete old task first.'],
                    ],
                    'existing_task' => $existingOpenTask,
                ], 422);
            }
            
            if ($assignedUser && $assignedUser->isTelecaller()) {
                $telecallerTaskService = app(\App\Services\TelecallerTaskService::class);
                $task = $telecallerTaskService->createScheduledCallingTask(
                    $lead,
                    $assignedUser,
                    $scheduledAt,
                    $user->id,
                    $notes
                );

                Log::info('Call task scheduled (TelecallerTask)', [
                    'task_id' => $task->id,
                    'lead_id' => $lead->id,
                    'assigned_to' => $assignedTo,
                    'scheduled_at' => $scheduledAt,
                    'created_by' => $user->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Call task scheduled successfully',
                    'data' => $task->load(['lead', 'assignedTo']),
                ], 201);
            }

            // For sales managers, sales executives, and others, create Task
            $task = Task::create([
                'lead_id' => $lead->id,
                'assigned_to' => $assignedTo,
                'type' => 'phone_call',
                'title' => "Call lead: {$lead->name}",
                'description' => "Phone call task for lead: {$lead->name} ({$lead->phone})" . ($notes ? "\n\nNotes: {$notes}" : ''),
                'status' => 'pending',
                'scheduled_at' => $scheduledAt,
                'created_by' => $user->id,
                'notes' => $notes,
            ]);

            Log::info('Call task scheduled (Task)', [
                'task_id' => $task->id,
                'lead_id' => $lead->id,
                'assigned_to' => $assignedTo,
                'scheduled_at' => $scheduledAt,
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call task scheduled successfully',
                'data' => $task->load(['lead', 'assignedTo', 'creator']),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Schedule Call Task Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
                'lead_id' => $request->lead_id ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule call task: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function findExistingManualOpenTask(int $leadId, int $assignedTo, ?User $assignedUser): ?array
    {
        $existingTask = Task::query()
            ->where('lead_id', $leadId)
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->latest('id')
            ->first();

        if ($existingTask) {
            return [
                'id' => $existingTask->id,
                'status' => $existingTask->status,
                'model_type' => 'task',
                'assigned_to' => $existingTask->assigned_to,
            ];
        }

        $existingTelecallerTask = TelecallerTask::query()
            ->where('lead_id', $leadId)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->latest('id')
            ->first();

        if (!$existingTelecallerTask) {
            return null;
        }

        return [
            'id' => $existingTelecallerTask->id,
            'status' => $existingTelecallerTask->status,
            'model_type' => 'telecaller_task',
            'assigned_to' => $existingTelecallerTask->assigned_to,
        ];
    }

    private function getLeadManagerOpenTasksPayload(Lead $lead, User $user): array
    {
        return Task::query()
            ->withoutGlobalScope('visible_in_queue')
            ->where('lead_id', $lead->id)
            ->where('type', 'phone_call')
            ->whereIn('status', Task::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task) => $this->formatLeadManagerTaskPayload($task))
            ->values()
            ->all();
    }

    private function getLeadTelecallerOpenTasksPayload(Lead $lead): array
    {
        return TelecallerTask::query()
            ->withoutGlobalScope('visible_in_queue')
            ->where('lead_id', $lead->id)
            ->whereIn('status', TelecallerTask::OPEN_STATUSES)
            ->whereNull('completed_at')
            ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->map(fn (TelecallerTask $task) => $this->formatLeadTelecallerTaskPayload($task))
            ->values()
            ->all();
    }

    private function formatLeadManagerTaskPayload(Task $task): array
    {
        $siteVisit = $this->resolveTaskSiteVisit($task);
        $siteVisitId = $siteVisit ? (int) $siteVisit->id : null;
        $taskText = strtolower(trim(implode(' ', array_filter([
            $task->title,
            $task->description,
            $task->notes,
        ]))));
        $isInterestedFollowUpTask = $this->isInterestedFollowUpTask($task, $taskText);

        $category = 'other';
        if (
            $task->meeting_id
            || str_contains($taskText, 'meeting id')
            || str_contains($taskText, 'pre-meeting')
            || (str_contains($taskText, 'meeting') && !str_contains($taskText, 'site visit') && !str_contains($taskText, 'site-visit'))
        ) {
            $category = 'meeting';
        } elseif ($siteVisitId) {
            $category = 'site_visit';
        } elseif (
            $isInterestedFollowUpTask
        ) {
            $category = 'follow_up';
        } elseif (
            !str_contains($taskText, 'cnp retry task created')
            && !str_contains($taskText, 'cnp rescheduled')
            && !str_contains($taskText, 'previous call not picked')
            && !str_contains($taskText, 'closer')
        ) {
            $category = 'fresh_lead';
        }

        return [
            'id' => 'mt_' . $task->id,
            'raw_id' => $task->id,
            'lead_id' => $task->lead_id,
            'model_type' => 'task',
            'category' => $category,
            'title' => $task->title,
            'notes' => $task->notes,
            'description' => $task->description,
            'status' => $task->status,
            'type' => $task->type,
            'priority' => $task->priority,
            'scheduled_at' => $task->scheduled_at ? $task->scheduled_at->toDateTimeString() : null,
            'meeting_id' => $task->meeting_id,
            'site_visit_id' => $siteVisitId,
            'site_visit_project' => $siteVisit?->project ?: $siteVisit?->property_name,
            'follow_up_id' => $task->follow_up_id,
            'is_overdue' => method_exists($task, 'isOverdue') ? $task->isOverdue() : false,
        ];
    }

    private function resolveTaskSiteVisitId(Task $task): ?int
    {
        $siteVisit = $this->resolveTaskSiteVisit($task);

        return $siteVisit ? (int) $siteVisit->id : null;
    }

    private function resolveVisitWorkflowTaskFromRequest(Request $request, Task $task, User $user): Task
    {
        $siteVisitId = (int) $request->input('site_visit_id');
        $siteVisit = $siteVisitId > 0 ? SiteVisit::query()->find($siteVisitId) : null;

        if (!$siteVisit) {
            $siteVisit = SiteVisit::query()->find((int) $task->id);
        }

        if (!$siteVisit || (int) ($task->lead_id ?? 0) === (int) $siteVisit->lead_id) {
            return $task;
        }

        $linkedTask = null;
        if (Schema::hasColumn('site_visits', 'reminder_task_id') && $siteVisit->reminder_task_id) {
            $linkedTask = Task::query()->find((int) $siteVisit->reminder_task_id);
        }

        if (!$linkedTask && Task::supportsColumn('site_visit_id')) {
            $linkedTask = Task::query()
                ->where('site_visit_id', $siteVisit->id)
                ->where('lead_id', $siteVisit->lead_id)
                ->latest('id')
                ->first();
        }

        if (!$linkedTask) {
            $linkedTask = Task::query()
                ->where('lead_id', $siteVisit->lead_id)
                ->where('type', 'site_visit')
                ->whereIn('status', ['pending', 'in_progress', 'rescheduled'])
                ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
                ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, scheduled_at, ?))', [$siteVisit->scheduled_at])
                ->latest('id')
                ->first();
        }

        if (
            $linkedTask
            && ((int) $linkedTask->assigned_to === (int) $user->id || $user->isAdmin() || $user->isCrm())
        ) {
            return $linkedTask;
        }

        return $task;
    }

    private function resolveTaskSiteVisit(Task $task): ?SiteVisit
    {
        if (Task::supportsColumn('site_visit_id') && $task->site_visit_id) {
            return SiteVisit::query()->find((int) $task->site_visit_id);
        }

        $taskText = trim(implode(' ', array_filter([
            $task->title,
            $task->description,
            $task->notes,
        ])));
        $hasLinkedVisitMarker = preg_match('/linked\s+site\s*visit\s*#\s*(\d+)/i', $taskText, $matches) === 1;

        if ($hasLinkedVisitMarker) {
            $linkedVisit = SiteVisit::query()->find((int) $matches[1]);
            if ($linkedVisit && (int) $linkedVisit->lead_id === (int) $task->lead_id) {
                return $linkedVisit;
            }
        }

        $looksLikeVisitWorkflow = (string) $task->type === 'site_visit'
            || $hasLinkedVisitMarker
            || preg_match('/^site\s*visit\s*:/i', trim((string) $task->title)) === 1
            || preg_match('/^scheduled\s+site\s*visit\s+for\b/i', trim((string) $task->description)) === 1
            || preg_match('/^reminder\s+call\s+\d+\s+min(?:ute)?s?\s+before\s+site\s*visit\s+scheduled\s+at\b/i', trim((string) $task->notes)) === 1;

        if (!$looksLikeVisitWorkflow) {
            return null;
        }

        if (Schema::hasColumn('site_visits', 'reminder_task_id')) {
            $reminderVisit = SiteVisit::query()
                ->where('reminder_task_id', $task->id)
                ->first();

            if ($reminderVisit) {
                return $reminderVisit;
            }
        }

        if (!$task->lead_id) {
            return null;
        }

        $query = SiteVisit::query()
            ->where('lead_id', $task->lead_id)
            ->whereNotIn('status', ['cancelled', 'canceled', 'dead']);

        if ($task->scheduled_at) {
            $scheduledAt = Carbon::parse($task->scheduled_at);
            $nearestVisit = (clone $query)
                ->whereBetween('scheduled_at', [
                    $scheduledAt->copy()->subHours(24),
                    $scheduledAt->copy()->addHours(24),
                ])
                ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, scheduled_at, ?))', [$scheduledAt->toDateTimeString()])
                ->latest('id')
                ->first();

            if ($nearestVisit) {
                return $nearestVisit;
            }
        }

        return $query
            ->orderByRaw("CASE status WHEN 'scheduled' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->latest('id')
            ->first();
    }

    private function formatLeadTelecallerTaskPayload(TelecallerTask $task): array
    {
        $category = match (true) {
            (bool) $task->meeting_id => 'meeting',
            (bool) $task->site_visit_id => 'site_visit',
            (bool) $task->follow_up_id => 'follow_up',
            str_contains(strtolower((string) $task->notes), 'meeting') => 'meeting',
            str_contains(strtolower((string) $task->notes), 'site visit'),
            str_contains(strtolower((string) $task->notes), 'site-visit') => 'site_visit',
            str_contains(strtolower((string) $task->notes), 'follow') => 'follow_up',
            default => 'fresh_lead',
        };

        return [
            'id' => $task->id,
            'lead_id' => $task->lead_id,
            'model_type' => 'telecaller_task',
            'category' => $category,
            'title' => match ($category) {
                'meeting' => 'Meeting reminder task',
                'site_visit' => 'Site visit reminder task',
                'follow_up' => 'Follow up task',
                default => 'Calling task',
            },
            'notes' => $task->notes,
            'description' => $task->notes,
            'status' => $task->status,
            'type' => $task->task_type ?: 'calling',
            'priority' => null,
            'scheduled_at' => $task->scheduled_at ? $task->scheduled_at->toDateTimeString() : null,
            'meeting_id' => $task->meeting_id,
            'site_visit_id' => $task->site_visit_id,
            'follow_up_id' => $task->follow_up_id,
            'is_overdue' => $task->scheduled_at ? $task->scheduled_at->lt(now()->subMinutes(Task::OVERDUE_GRACE_MINUTES)) : false,
        ];
    }

    private function shouldIncludeAsmTaskInResults(Task $task, ?string $dateFilter, ?string $customDate, ?string $statusFilter): bool
    {
        if (in_array((string) $task->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        $siteVisitId = $this->resolveTaskSiteVisitId($task);
        if ($siteVisitId) {
            $siteVisitStatus = SiteVisit::query()->whereKey($siteVisitId)->value('status');
            if (in_array((string) $siteVisitStatus, ['cancelled'], true)) {
                return false;
            }
        }

        if ($task->type !== 'site_visit') {
            return true;
        }

        $visitScheduledAt = $task->siteVisit?->scheduled_at ?: $task->scheduled_at;
        if (!$visitScheduledAt) {
            return true;
        }

        $visitDate = Carbon::parse($visitScheduledAt);
        $normalizedDateFilter = $dateFilter && $dateFilter !== '' ? strtolower(trim($dateFilter)) : 'all';
        $normalizedStatusFilter = $statusFilter && $statusFilter !== '' ? strtolower(trim($statusFilter)) : 'all';
        $isOverdue = $task->isOverdue();

        if ($normalizedStatusFilter === 'overdue' || $normalizedDateFilter === 'pod') {
            return $isOverdue;
        }

        if ($normalizedDateFilter === 'today') {
            return $visitDate->isSameDay(Carbon::today());
        }

        if ($normalizedDateFilter === 'tomorrow') {
            return $visitDate->isSameDay(Carbon::tomorrow());
        }

        if ($normalizedDateFilter === 'this_week') {
            return $visitDate->betweenIncluded(Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek());
        }

        if ($normalizedDateFilter === 'this_month') {
            return $visitDate->betweenIncluded(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
        }

        if ($normalizedDateFilter === 'this_year') {
            return $visitDate->betweenIncluded(Carbon::now()->startOfYear(), Carbon::now()->endOfYear());
        }

        if ($normalizedDateFilter === 'custom' && $customDate) {
            return $visitDate->isSameDay(Carbon::parse($customDate));
        }

        if ($normalizedDateFilter === 'all' || $normalizedDateFilter === '') {
            return $isOverdue || $visitDate->isSameDay(Carbon::today());
        }

        return true;
    }

    /**
     * Check if user can access a lead
     */
    private function canAccessLead($user, Lead $lead): bool
    {
        // Admin and CRM can see all leads
        if ($user->isAdmin() || $user->isCrm()) {
            return true;
        }

        // Check if lead is directly assigned to user
        if ($lead->isAssignedToUser($user->id)) {
            return true;
        }

        // Senior Manager, Manager, Assistant Sales Manager: team's leads
        if ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            
            if ($teamMemberIds->isNotEmpty() && $lead->isAssignedToAnyUser($teamMemberIds)) {
                return true;
            }
            
            if ($teamMemberIds->isNotEmpty()) {
                return $lead->isVisibleViaProspectFallback($teamMemberIds, function ($prospectQuery) {
                    $prospectQuery->whereIn('verification_status', ['verified', 'approved']);
                });
            }
        }

        // Sales Executive: only assigned leads or leads from their own prospects
        if ($user->isSalesExecutive()) {
            return $lead->isAssignedToUser($user->id) ||
                $lead->isVisibleViaProspectFallback([$user->id]);
        }
        return false;
    }

    private function isInterestedFollowUpTask(Task $task, ?string $taskText = null): bool
    {
        $taskText = $taskText !== null
            ? strtolower($taskText)
            : strtolower(trim(implode(' ', array_filter([
                $task->title,
                $task->description,
                $task->notes,
            ]))));

        $looksLikeFollowUp = (bool) $task->follow_up_id
            || str_contains($taskText, 'follow-up call')
            || str_contains($taskText, 'follow up call')
            || str_contains($taskText, 'follow-up scheduled');

        if (!$looksLikeFollowUp) {
            return false;
        }

        $lead = $task->relationLoaded('lead') ? $task->lead : $task->lead()->with('prospects')->first();
        if (!$lead) {
            return false;
        }

        if (($lead->status ?? null) === 'verified_prospect') {
            return true;
        }

        if (!$lead->relationLoaded('prospects')) {
            $lead->load('prospects');
        }

        $latestProspect = $lead->prospects->sortByDesc('created_at')->first();

        return in_array($latestProspect->verification_status ?? null, ['verified', 'approved'], true);
    }
    /**
     * Get lead requirement form directly by lead ID (no task required)
     * Used by admin/crm from lead show page
     */
    public function getLeadRequirementForm(Request $request, \App\Models\Lead $lead)
    {
        try {
            $user = $request->user();
            if (
                !$user->isAdmin() &&
                !$user->isCrm() &&
                !$user->isSalesManager() &&
                !$user->isSeniorManager() &&
                !$user->isAssistantSalesManager()
            ) {
                return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
            }

            $prospect = $lead->currentCycleProspects()->latest()->first();
            $hasProspect = $prospect && in_array($prospect->verification_status ?? '', ['pending', 'pending_verification']);

            $existingValues = $this->getHydratedLeadRequirementFormValues($lead, $prospect);

            $forms = $this->getLeadDetailDynamicForms();
            $requirementsFormConfig = $this->buildLeadDetailRequirementsFormConfig($forms, $lead, $user, 'lead');
            $outputFormConfig = $this->buildLeadDetailOutputFormConfig($forms);
            $mappedFields = $this->buildLeadDetailRequirementFields($forms);

            return response()->json([
                'success'          => true,
                'lead_id'          => $lead->id,
                'lead_name'        => $existingValues['name'] ?? $lead->name,
                'lead_phone'       => $existingValues['phone'] ?? $lead->phone,
                'lead_email'       => $lead->email,
                'prospect_id'      => $prospect?->id,
                'prospect_status'  => $prospect?->verification_status,
                'has_prospect'     => $hasProspect,
                'form_values'      => $existingValues,
                'form_fields'      => $mappedFields,
                'requirements_form_config' => $requirementsFormConfig,
                'output_form_config' => $outputFormConfig,
            ]);
        } catch (\Exception $e) {
            \Log::error('getLeadRequirementForm error', ['error' => $e->getMessage(), 'lead_id' => $lead->id ?? null]);
            return response()->json(['success' => false, 'error' => 'Failed to load form: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save lead requirements directly by lead ID (no task required)
     * Used by admin/crm from lead show page
     */
    public function updateLeadRequirements(Request $request, \App\Models\Lead $lead)
    {
        $user = $request->user();
        if (
            !$user->isAdmin() &&
            !$user->isCrm() &&
            !$user->isSalesManager() &&
            !$user->isSeniorManager() &&
            !$user->isAssistantSalesManager()
        ) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $formFieldsInput = is_array($request->input('form_fields')) ? $request->input('form_fields') : [];
        foreach (['preferred_location', 'budget'] as $fieldKey) {
            if (blank($request->input($fieldKey)) && filled($formFieldsInput[$fieldKey] ?? null)) {
                $request->merge([$fieldKey => $formFieldsInput[$fieldKey]]);
            }
        }

        $validated = $request->validate([
            'customer_name'      => 'required|string|max:255',
            'phone'              => 'required|string|max:20',
            'email'              => 'nullable|email|max:255',
            'preferred_location' => 'required|string|max:255',
            'budget'             => 'required|string|max:255',
            'possession'         => 'nullable|string|max:255',
            'purpose'            => 'nullable|string|max:255',
            'lead_quality'       => 'nullable|string|max:50',
            'lead_status'        => 'nullable|string|max:50',
            'meta_stage'         => 'nullable|string|max:100',
            'meta_review_note'   => 'nullable|string|max:5000',
            'form_fields'        => 'nullable|array',
        ]);

        \DB::beginTransaction();
        try {
            $formFields = is_array($validated['form_fields'] ?? null)
                ? $validated['form_fields']
                : [];
            $propertyType = $formFields['type'] ?? $formFields['property_type'] ?? null;
            if (
                (!isset($formFields['category']) || trim((string) $formFields['category']) === '')
                && is_string($propertyType)
                && trim($propertyType) !== ''
            ) {
                $formFields['category'] = $this->inferRequirementCategoryFromType($propertyType);
            }

            $lead->update([
                'name'               => $request->input('customer_name'),
                'phone'              => $request->input('phone'),
                'email'              => $request->input('email'),
                'preferred_location' => $request->input('preferred_location'),
                'budget'             => $request->input('budget'),
                'property_type'      => is_string($propertyType) && trim($propertyType) !== '' ? trim($propertyType) : $lead->property_type,
                'form_filled_by_manager' => true,
            ]);

            // Save dynamic form field values
            foreach ($formFields as $key => $value) {
                if (!is_string($key) || trim($key) === '') {
                    continue;
                }

                $lead->setFormFieldValue($key, $value, $user->id);
            }

            app(MetaReviewSaveService::class)->saveFromLeadRequirements(
                $lead,
                $user,
                $request->input('meta_stage'),
                $request->input('meta_review_note')
            );

            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead requirements updated successfully',
                'lead_id' => $lead->id,
            ]);
        } catch (AuthorizationException $e) {
            \DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            \DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('updateLeadRequirements error', ['error' => $e->getMessage(), 'lead_id' => $lead->id ?? null]);
            return response()->json(['success' => false, 'message' => 'Failed to save: ' . $e->getMessage()], 500);
        }
    }
}
