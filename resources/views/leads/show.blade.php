@php
    $user = auth()->user();
    $leadPhoneMaskedForViewer = app(\App\Services\PhonePrivacyService::class)->shouldMask($user);
    $leadCallUrl = !blank($lead->phone) ? \App\Helpers\ContactHelper::getCallUrl((string) $lead->phone) : null;
    $leadWhatsAppPhoneDigits = !blank($lead->phone) ? \App\Helpers\ContactHelper::formatPhoneForWhatsApp((string) $lead->phone) : '';
    $leadWhatsAppPhone = $leadWhatsAppPhoneDigits !== '' ? '+' . $leadWhatsAppPhoneDigits : '';
    $leadWhatsAppMessage = 'Hello ' . $lead->name;
    $leadWhatsAppUrl = $leadWhatsAppPhoneDigits !== ''
        ? 'https://wa.me/' . $leadWhatsAppPhoneDigits . '?text=' . rawurlencode($leadWhatsAppMessage)
        : null;
    $leadWhatsAppBusinessUrl = $leadWhatsAppPhoneDigits !== ''
        ? 'whatsapp-business://send?phone=' . $leadWhatsAppPhoneDigits . '&text=' . rawurlencode($leadWhatsAppMessage)
        : null;
    $defaultBackUrl = route('leads.index');
    $asmOpenTasks = $asmOpenTasks ?? collect();
    $oldTasks = $oldTasks ?? collect();
    $canUseLeadTaskFlow = (bool) ($user && (
        $user->isAssistantSalesManager()
        || $user->isSalesManager()
        || $user->isSeniorManager()
        || $user->isSalesExecutive()
        || $user->isTelecaller()
    ));
    $usesManagerMobileSafeLeadDetail = (bool) ($user && (
        $user->isSeniorManager()
        || $user->isAssistantSalesManager()
        || $user->isSalesManager()
        || $user->isSalesExecutive()
        || $user->isTelecaller()
    ));
    $embedTaskFlowOnly = request('embed_task_flow') === '1';
    $leadDetailsPreview = (bool) request()->attributes->get('lead_details_preview', false);
    $isLeadFavorite = $user
        ? \App\Models\LeadFavorite::where('user_id', $user->id)->where('lead_id', $lead->id)->exists()
        : false;

    if ($user?->isAssistantSalesManager() || $user?->isSalesManager() || $user?->isSeniorManager()) {
        $defaultBackUrl = route('sales-manager.leads');
    }

    $requestedBackUrl = request('back');
    $backUrl = $defaultBackUrl;
    $taskSectionUrl = $defaultBackUrl;

    if (filled($requestedBackUrl)) {
        $requestedBackUrl = (string) $requestedBackUrl;

        if (($user?->isAssistantSalesManager() || $user?->isSalesManager() || $user?->isSeniorManager())
            && $requestedBackUrl === route('leads.index')) {
            $backUrl = route('sales-manager.leads');
        } elseif (
            str_starts_with($requestedBackUrl, route('leads.index')) ||
            str_starts_with($requestedBackUrl, route('sales-manager.leads'))
        ) {
            $backUrl = $requestedBackUrl;
        }
    }

    if ($user?->isAssistantSalesManager() || $user?->isSalesManager() || $user?->isSeniorManager()) {
        $taskSectionUrl = route('sales-manager.tasks');
    } elseif ($user?->isTelecaller()) {
        $taskSectionUrl = route('telecaller.tasks');
    }
    $fieldConfig = function ($form, string $key, array $defaults = []) {
        $field = $form?->fields?->firstWhere('field_key', $key);

        return [
            'label' => $field?->label ?? ($defaults['label'] ?? ''),
            'placeholder' => $field?->placeholder ?? ($defaults['placeholder'] ?? ''),
            'help_text' => $field?->help_text ?? ($defaults['help_text'] ?? ''),
            'required' => $field ? (bool) $field->required : ($defaults['required'] ?? false),
            'options' => ($field && is_array($field->options) && count($field->options))
                ? $field->options
                : ($defaults['options'] ?? []),
            'default_value' => $field?->default_value ?? ($defaults['default_value'] ?? ''),
        ];
    };

    $leadNameField = $fieldConfig($leadDetailRequirementsForm ?? null, 'name', [
        'label' => 'Customer name',
        'placeholder' => 'Enter lead name',
        'required' => true,
    ]);
    $leadPhoneField = $fieldConfig($leadDetailRequirementsForm ?? null, 'phone', [
        'label' => 'Phone',
        'placeholder' => 'Enter phone number',
        'required' => true,
    ]);
    $leadCategoryField = $fieldConfig($leadDetailRequirementsForm ?? null, 'category', [
        'label' => 'Category',
        'required' => true,
        'options' => ['Residential', 'Commercial', 'Both', 'N.A'],
    ]);
    $leadLocationField = $fieldConfig($leadDetailRequirementsForm ?? null, 'preferred_location', [
        'label' => 'Location',
        'required' => true,
        'options' => ['Inside City', 'Sitapur Road', 'Hardoi Road', 'Faizabad Road', 'Sultanpur Road', 'Shaheed Path', 'Raebareily Road', 'Kanpur Road', 'Outer Ring Road', 'Bijnor Road', 'Deva Road', 'Sushant Golf City', 'Vrindavan Yojana', 'N.A'],
    ]);
    $leadBudgetField = $fieldConfig($leadDetailRequirementsForm ?? null, 'budget', [
        'label' => 'Budget',
        'required' => true,
        'options' => ['Below 50 Lacs', '50-75 Lacs', '75 Lacs-1 Cr', 'Above 1 Cr', 'Above 2 Cr', 'N.A'],
    ]);
    $leadTypeField = $fieldConfig($leadDetailRequirementsForm ?? null, 'type', [
        'label' => 'Type',
        'required' => true,
        'placeholder' => 'Select type',
    ]);
    $leadPurposeField = $fieldConfig($leadDetailRequirementsForm ?? null, 'purpose', [
        'label' => 'Purpose',
        'required' => true,
        'options' => ['End Use', 'Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use', 'N.A'],
    ]);
    $leadPossessionField = $fieldConfig($leadDetailRequirementsForm ?? null, 'possession', [
        'label' => 'Possession',
        'required' => true,
        'options' => ['Under Construction', 'Ready To Move', 'Pre Launch', 'Both', 'N.A'],
    ]);
    $leadStatusField = $fieldConfig($leadDetailRequirementsForm ?? null, 'lead_status', [
        'label' => 'Status',
        'required' => true,
        'options' => ['hot', 'warm', 'cold', 'junk'],
    ]);
    $leadQualityField = $fieldConfig($leadDetailRequirementsForm ?? null, 'lead_quality', [
        'label' => 'Lead quality',
        'required' => true,
        'options' => ['1', '2', '3', '4', '5'],
    ]);
    $leadProjectsField = $fieldConfig($leadDetailRequirementsForm ?? null, 'interested_projects', [
        'label' => 'Interested projects',
        'placeholder' => 'Search and select interested projects',
        'required' => true,
        'options' => ['Jash Elevate', 'Oro Constella', 'Okas Res.', 'Other'],
    ]);
    $leadCustomerJobField = $fieldConfig($leadDetailRequirementsForm ?? null, 'customer_job', [
        'label' => 'Customer job',
        'placeholder' => 'Enter job / occupation',
    ]);
    $leadIndustryField = $fieldConfig($leadDetailRequirementsForm ?? null, 'industry_sector', [
        'label' => 'Industry / sector',
        'options' => ['IT', 'Education', 'Healthcare', 'Business', 'FMCG', 'Government', 'Other'],
    ]);
    $leadBuyingFrequencyField = $fieldConfig($leadDetailRequirementsForm ?? null, 'buying_frequency', [
        'label' => 'Buying frequency',
        'options' => ['Regular', 'Occasional', 'First-time'],
    ]);
    $leadLivingCityField = $fieldConfig($leadDetailRequirementsForm ?? null, 'living_city', [
        'label' => 'Living city',
        'placeholder' => 'Enter living city',
    ]);
    $leadCityTypeField = $fieldConfig($leadDetailRequirementsForm ?? null, 'city_type', [
        'label' => 'City type',
        'options' => ['Metro', 'Tier 1', 'Tier 2', 'Tier 3', 'Local Resident'],
    ]);
    $leadRemarkField = $fieldConfig($leadDetailRequirementsForm ?? null, 'manager_remark', [
        'label' => 'Remark',
        'placeholder' => 'Enter remarks or notes...',
    ]);

    $leadDetailRequirementsFormConfig = [
        'name' => [
            'label' => $leadNameField['label'],
            'placeholder' => $leadNameField['placeholder'],
            'required' => $leadNameField['required'],
        ],
        'phone' => [
            'label' => $leadPhoneField['label'],
            'placeholder' => $leadPhoneField['placeholder'],
            'required' => $leadPhoneField['required'],
        ],
        'category' => [
            'label' => $leadCategoryField['label'],
            'required' => $leadCategoryField['required'],
            'options' => $leadCategoryField['options'],
        ],
        'preferred_location' => [
            'label' => $leadLocationField['label'],
            'required' => $leadLocationField['required'],
            'options' => $leadLocationField['options'],
        ],
        'budget' => [
            'label' => $leadBudgetField['label'],
            'required' => $leadBudgetField['required'],
            'options' => $leadBudgetField['options'],
        ],
        'type' => [
            'label' => $leadTypeField['label'],
            'required' => $leadTypeField['required'],
            'placeholder' => $leadTypeField['placeholder'],
        ],
        'purpose' => [
            'label' => $leadPurposeField['label'],
            'required' => $leadPurposeField['required'],
            'options' => $leadPurposeField['options'],
        ],
        'possession' => [
            'label' => $leadPossessionField['label'],
            'required' => $leadPossessionField['required'],
            'options' => $leadPossessionField['options'],
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
            'label' => $leadProjectsField['label'],
            'placeholder' => $leadProjectsField['placeholder'],
            'required' => $leadProjectsField['required'],
            'options' => $leadProjectsField['options'],
        ],
        'customer_job' => [
            'label' => $leadCustomerJobField['label'],
            'placeholder' => $leadCustomerJobField['placeholder'],
        ],
        'industry_sector' => [
            'label' => $leadIndustryField['label'],
            'options' => $leadIndustryField['options'],
        ],
        'buying_frequency' => [
            'label' => $leadBuyingFrequencyField['label'],
            'options' => $leadBuyingFrequencyField['options'],
        ],
        'living_city' => [
            'label' => $leadLivingCityField['label'],
            'placeholder' => $leadLivingCityField['placeholder'],
        ],
        'city_type' => [
            'label' => $leadCityTypeField['label'],
            'options' => $leadCityTypeField['options'],
        ],
        'manager_remark' => [
            'label' => $leadRemarkField['label'],
            'placeholder' => $leadRemarkField['placeholder'],
        ],
        'type_option_groups' => [
            'Residential' => ['Plots & Villas', 'Apartments', 'Studio', 'Farmhouse', 'N.A'],
            'Commercial' => ['Retail Shops', 'Office Space', 'Studio', 'N.A'],
            'Both' => ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A'],
            'N.A' => ['N.A'],
        ],
    ];

    $followUpRequiredField = $fieldConfig($leadDetailFollowUpForm ?? null, 'followup_required', ['label' => 'Follow up required']);
    $followUpDateField = $fieldConfig($leadDetailFollowUpForm ?? null, 'scheduled_at', [
        'label' => 'Follow up date & time',
        'required' => true,
        'help_text' => 'Select a future date and time for the next follow-up.',
    ]);
    $followUpNotesField = $fieldConfig($leadDetailFollowUpForm ?? null, 'notes', [
        'label' => 'Remark',
        'placeholder' => 'Add follow-up note, context, or callback instruction...',
    ]);

    $visitDateField = $fieldConfig($leadDetailSiteVisitForm ?? null, 'visit_date', ['label' => 'Visit date', 'required' => true]);
    $visitTimeField = $fieldConfig($leadDetailSiteVisitForm ?? null, 'visit_time', ['label' => 'Visit time', 'required' => true]);
    $visitTypeField = $fieldConfig($leadDetailSiteVisitForm ?? null, 'visit_type', [
        'label' => 'Visit type',
        'options' => ['Site visit', 'Office visit'],
    ]);
    $visitProjectField = $fieldConfig($leadDetailSiteVisitForm ?? null, 'project_name', [
        'label' => 'Project to visit',
        'placeholder' => 'Enter project name',
    ]);
    $visitLocationField = $fieldConfig($leadDetailSiteVisitForm ?? null, 'visit_location', [
        'label' => 'Visit location',
        'placeholder' => 'Project site address or landmark',
    ]);
    $visitNotesField = $fieldConfig($leadDetailSiteVisitForm ?? null, 'visit_notes', [
        'label' => 'Remark',
        'placeholder' => 'Add visit note or instruction...',
    ]);
    $visitReminderField = $fieldConfig($leadDetailSiteVisitForm ?? null, 'visit_reminder', ['label' => 'Remind me before visit']);

    $meetingTypeField = $fieldConfig($leadDetailMeetingForm ?? null, 'meeting_type', [
        'label' => 'Meeting type',
        'required' => true,
        'options' => ['Initial Meeting', 'Follow-up Meeting', 'Negotiation Meeting', 'Closing Meeting'],
    ]);
    $meetingDateField = $fieldConfig($leadDetailMeetingForm ?? null, 'meeting_date', ['label' => 'Scheduled date', 'required' => true]);
    $meetingTimeField = $fieldConfig($leadDetailMeetingForm ?? null, 'meeting_time', ['label' => 'Scheduled time', 'required' => true]);
    $meetingModeField = $fieldConfig($leadDetailMeetingForm ?? null, 'meeting_mode', [
        'label' => 'Meeting mode',
        'required' => true,
        'options' => ['Online', 'Offline'],
    ]);
    $meetingLinkField = $fieldConfig($leadDetailMeetingForm ?? null, 'meeting_link', [
        'label' => 'Meeting link',
        'placeholder' => 'https://meet.google.com/...',
    ]);
    $meetingLocationField = $fieldConfig($leadDetailMeetingForm ?? null, 'location', [
        'label' => 'Location',
        'placeholder' => 'Office address, project site, etc.',
    ]);
    $meetingNotesField = $fieldConfig($leadDetailMeetingForm ?? null, 'meeting_notes', [
        'label' => 'Remark',
        'placeholder' => 'Any notes about this meeting...',
    ]);
    $meetingReminderField = $fieldConfig($leadDetailMeetingForm ?? null, 'reminder_enabled', ['label' => 'Remind me before meeting']);

    $leadDetailOutputFormConfig = [
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

    $leadMeetingReferenceData = ($lead->meetings ?? collect())->map(function ($meeting) {
        return [
            'id' => $meeting->id,
            'scheduled_at' => optional($meeting->scheduled_at)->toIso8601String(),
            'status' => $meeting->status,
        ];
    })->values();

    $leadSiteVisitReferenceData = ($lead->siteVisits ?? collect())->map(function ($visit) {
        return [
            'id' => $visit->id,
            'scheduled_at' => optional($visit->scheduled_at)->toIso8601String(),
            'status' => $visit->status,
            'lead_type' => $visit->lead_type,
            'project' => $visit->project ?: $visit->property_name,
            'property_name' => $visit->property_name,
        ];
    })->values();

    $leadStatusLabel = ucfirst(str_replace('_', ' ', (string) $lead->status));
    $leadSourceLabel = $lead->source_label ?: \App\Models\Lead::displaySourceLabel($lead->source);
    $leadImportChannelTag = $lead->import_channel_tag;
    $timelineItems = collect($timeline ?? []);
    $leadDisplayCreatedAt = $lead->display_created_at;
    $canViewLeadCallHistory = (bool) ($canViewLeadCallHistory ?? false);
    $leadCallLogs = collect($leadCallLogs ?? []);
    $leadMcubeOutboundAttempts = collect($leadMcubeOutboundAttempts ?? []);
    $wabaCallEvents = collect($wabaCallEvents ?? []);
    $leadCallSummary = $leadCallSummary ?? [
        'total_calls' => 0,
        'answered_calls' => 0,
        'unanswered_calls' => 0,
        'total_talk_seconds' => 0,
        'last_call_at' => null,
    ];
    $formatLeadCallDuration = function ($seconds): string {
        $seconds = max(0, (int) $seconds);
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes > 0) {
            return $minutes . ' min ' . str_pad((string) $remainingSeconds, 2, '0', STR_PAD_LEFT) . ' sec';
        }

        return $remainingSeconds . ' sec';
    };
    $canViewMetaLeadData = (bool) (
        $user?->isAdmin()
        || $user?->isCrm()
        || $user?->isSalesManager()
        || $user?->isSeniorManager()
        || $user?->isAssistantSalesManager()
    );
    $metaFormName = $canViewMetaLeadData
        ? trim((string) ($lead->latestFbLead?->form?->form_name ?? ''))
        : '';
    $formatMetaFieldLabel = function (string $key): string {
        $label = str_replace(['_', '-'], ' ', $key);
        $label = preg_replace('/[^\pL\pN\s\/]+/u', ' ', $label) ?: $label;
        $label = preg_replace('/\s+/', ' ', trim($label)) ?: $key;

        return ucwords(strtolower($label));
    };
    $normalizeMetaFieldValue = function ($value): string {
        if (is_array($value)) {
            $value = collect($value)
                ->flatten()
                ->filter(fn ($item) => !blank($item))
                ->map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item))
                ->implode(', ');
        }

        return trim((string) $value);
    };
    $normalizeInterestedProjectNames = function ($rawValue) {
        if ($rawValue instanceof \Illuminate\Support\Collection) {
            $rawValue = $rawValue->all();
        }

        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rawValue = $decoded;
            } else {
                $rawValue = preg_split('/\s*,\s*/', $rawValue) ?: [$rawValue];
            }
        }

        if (!is_array($rawValue)) {
            return collect();
        }

        return collect($rawValue)
            ->map(function ($project) {
                if ($project instanceof \App\Models\Project) {
                    return trim((string) $project->name);
                }

                if (is_array($project)) {
                    return trim((string) ($project['name'] ?? $project['label'] ?? ''));
                }

                if (is_object($project)) {
                    return trim((string) ($project->name ?? $project->label ?? ''));
                }

                return trim((string) $project);
            })
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->values();
    };
    $metaFieldRows = collect();
    if ($canViewMetaLeadData && $lead->latestFbLead) {
        if (filled($lead->latestFbLead->leadgen_id)) {
            $metaFieldRows->push([
                'label' => 'Meta Lead ID',
                'value' => (string) $lead->latestFbLead->leadgen_id,
                'is_url' => false,
            ]);
        }

        if ($metaFormName !== '') {
            $metaFieldRows->push([
                'label' => 'Form Name',
                'value' => $metaFormName,
                'is_url' => false,
            ]);
        }

        foreach (($lead->latestFbLead->field_data_json ?? []) as $metaKey => $metaValue) {
            $metaValue = $normalizeMetaFieldValue($metaValue);
            if ($metaValue === '') {
                continue;
            }

            $metaFieldRows->push([
                'label' => $formatMetaFieldLabel((string) $metaKey),
                'value' => $metaValue,
                'is_url' => filter_var($metaValue, FILTER_VALIDATE_URL) !== false,
            ]);
        }
    }
    $mobileInfoRows = collect([
        ['label' => 'Source', 'value' => $leadSourceLabel],
        ['label' => 'Owner', 'value' => optional($lead->activeAssignments->first()?->assignedTo)->name ?: 'Unassigned'],
        ['label' => 'Budget', 'value' => $lead->budget ?: 'N.A'],
        ['label' => 'Category', 'value' => $lead->category ?: 'N.A'],
        ['label' => 'Location', 'value' => $lead->preferred_location ?: ($lead->location ?: 'N.A')],
        ['label' => 'Type', 'value' => $lead->property_type ?: ($lead->type ?: 'N.A')],
        ['label' => 'Purpose', 'value' => $lead->purpose ?: 'N.A'],
        ['label' => 'Created By', 'value' => $lead->creator_display_name ?: 'System'],
        ['label' => 'Created On', 'value' => optional($leadDisplayCreatedAt)->format('d M Y, h:i A') ?: 'N.A'],
    ])->filter(fn ($row) => filled($row['value']));

    if ($metaFieldRows->isNotEmpty()) {
        $existingMobileInfoLabels = $mobileInfoRows
            ->pluck('label')
            ->map(fn ($label) => strtolower((string) $label))
            ->all();

        $metaMobileInfoRows = $metaFieldRows
            ->reject(fn ($row) => in_array(strtolower((string) ($row['label'] ?? '')), $existingMobileInfoLabels, true))
            ->take(12)
            ->map(fn ($row) => [
                'label' => $row['label'] ?? '',
                'value' => $row['value'] ?? 'N.A',
                'is_url' => $row['is_url'] ?? false,
            ]);

        $mobileInfoRows = $mobileInfoRows->concat($metaMobileInfoRows)->values();
    }

    $mobileNoteLines = preg_split('/\r\n|\r|\n/', trim((string) $displayLeadNotes)) ?: [];
    $mobileParsedNoteFields = [];
    $mobilePlainNoteLines = [];
    foreach ($mobileNoteLines as $mobileNoteLine) {
        $trimmedMobileNoteLine = trim((string) $mobileNoteLine);
        if ($trimmedMobileNoteLine === '') {
            continue;
        }

        if (str_contains($trimmedMobileNoteLine, ':')) {
            [$mobileNoteKey, $mobileNoteValue] = array_pad(explode(':', $trimmedMobileNoteLine, 2), 2, '');
            $mobileNoteKey = trim((string) $mobileNoteKey);
            $mobileNoteValue = trim((string) $mobileNoteValue);

            if ($mobileNoteKey !== '') {
                $mobileParsedNoteFields[] = [
                    'label' => ucwords(str_replace('_', ' ', $mobileNoteKey)),
                    'value' => $mobileNoteValue !== '' ? $mobileNoteValue : 'N/A',
                ];
                continue;
            }
        }

        $mobilePlainNoteLines[] = $trimmedMobileNoteLine;
    }
    foreach ($metaFieldRows as $metaFieldRow) {
        $mobileParsedNoteFields[] = $metaFieldRow;
    }

    $prospectInterestedProjects = collect($lead->prospects ?? [])
        ->flatMap(function ($prospect) use ($normalizeInterestedProjectNames) {
            return $normalizeInterestedProjectNames($prospect->interestedProjects ?? collect());
        });

    $rawInterestedProjectValues = collect();
    if (filled(data_get($lead, 'interested_projects'))) {
        $rawInterestedProjectValues->push(data_get($lead, 'interested_projects'));
    }

    foreach (($lead->formFieldValues ?? collect()) as $formFieldValue) {
        if (($formFieldValue->field_key ?? null) === 'interested_projects' && filled($formFieldValue->field_value)) {
            $rawInterestedProjectValues->push($formFieldValue->field_value);
        }
    }

    $leadInterestedProjects = $prospectInterestedProjects
        ->merge(
            $rawInterestedProjectValues->flatMap(function ($rawValue) use ($normalizeInterestedProjectNames) {
                return $normalizeInterestedProjectNames($rawValue);
            })
        )
        ->filter()
        ->unique(fn ($name) => mb_strtolower((string) $name))
        ->values();

    $leadActionOpenTasks = $asmOpenTasks->isNotEmpty()
        ? $asmOpenTasks
        : $oldTasks->map(function ($task) {
            return (object) [
                'id' => $task['id'] ?? null,
                'model_type' => $task['model_type'] ?? 'task',
                'category' => $task['category'] ?? 'other',
                'title' => $task['title'] ?? $task['task_label'] ?? 'Open task',
                'notes' => $task['notes'] ?? null,
                'description' => $task['description'] ?? null,
                'status' => $task['status'] ?? 'pending',
                'scheduled_at' => $task['scheduled_at'] ?? null,
                'meeting_id' => $task['meeting_id'] ?? null,
                'site_visit_id' => $task['site_visit_id'] ?? null,
                'follow_up_id' => $task['follow_up_id'] ?? null,
            ];
        });

    $nextAsmTask = $leadActionOpenTasks->sortBy(function ($task) {
        return optional($task->scheduled_at)->timestamp ?: optional($task->updated_at)->timestamp ?: optional($task->created_at)->timestamp ?: 0;
    })->first();
@endphp
@extends($layout ?? 'layouts.app')

@section('title', $lead->name . ' - Lead Details')
@section('page-title', 'Lead Details')

@section('content')
@unless($embedTaskFlowOnly)
<div class="space-y-6 lead-detail-container {{ $usesManagerMobileSafeLeadDetail ? 'manager-mobile-safe' : '' }}" style="width: 100%; max-width: 100%; overflow-x: hidden; box-sizing: border-box;">
    @if($leadDetailsPreview)
        @include('leads.partials.preview-v2')
    @else
    @if($usesManagerMobileSafeLeadDetail)
    <section class="asm-mobile-lead-shell">
        {{-- Top navigation bar --}}
        <div class="asm-m-topbar">
            <a href="{{ $backUrl }}" class="asm-m-topbar-btn" aria-label="Back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <span class="asm-m-topbar-title">Lead Details</span>
            <div class="asm-m-topbar-actions">
                <button type="button" class="asm-m-topbar-btn lead-favorite-btn {{ $isLeadFavorite ? 'is-favorite' : '' }}" data-lead-favorite-btn data-favorite-state="{{ $isLeadFavorite ? '1' : '0' }}" onclick="toggleLeadDetailFavorite({{ $lead->id }})" aria-label="{{ $isLeadFavorite ? 'Remove from favorite leads' : 'Add to favorite leads' }}" title="{{ $isLeadFavorite ? 'Remove from favorites' : 'Add to favorites' }}">
                    <i class="{{ $isLeadFavorite ? 'fas' : 'far' }} fa-heart"></i>
                </button>
                <button type="button" class="asm-m-topbar-btn" onclick="openLeadRequirementsModal({{ $lead->id }})">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>
        </div>

        {{-- Lead profile card --}}
        <div class="asm-m-profile-card">
            <div class="asm-m-profile-row">
                <div class="asm-m-avatar">{{ strtoupper(substr($lead->name, 0, 1)) }}</div>
                <div class="asm-m-profile-info">
                    <h1 class="asm-m-lead-name">{{ $lead->name }}</h1>
                    <div class="asm-m-lead-meta">
                        <a href="{{ $leadCallUrl ?: '#' }}" data-lead-call-trigger data-lead-id="{{ $lead->id }}" data-lead-phone="{{ $lead->phone }}" class="asm-m-phone-link"><i class="fas fa-phone-alt"></i> {{ $lead->phone }}</a>
                        <span class="asm-m-dot-sep">&middot;</span>
                        <span class="asm-m-assignee"><i class="fas fa-user"></i> {{ optional($lead->activeAssignments->first()?->assignedTo)->name ?: 'Unassigned' }}</span>
                    </div>
                </div>
            </div>

            <div class="asm-m-badges">
                @php
                    $statusColorMap = [
                        'hot' => ['bg' => '#dc2626', 'text' => '#fff'],
                        'warm' => ['bg' => '#f59e0b', 'text' => '#fff'],
                        'cold' => ['bg' => '#3b82f6', 'text' => '#fff'],
                        'new' => ['bg' => '#10b981', 'text' => '#fff'],
                        'junk' => ['bg' => '#6b7280', 'text' => '#fff'],
                    ];
                    $sColor = $statusColorMap[strtolower($lead->lead_status ?? '')] ?? ['bg' => '#063A1C', 'text' => '#fff'];
                @endphp
                <span class="asm-m-badge" style="background:{{ $sColor['bg'] }};color:{{ $sColor['text'] }}">{{ $leadStatusLabel }}</span>
                <span class="asm-m-badge asm-m-badge-outline">{{ $leadSourceLabel }}</span>
                @if($lead->budget)
                <span class="asm-m-badge asm-m-badge-outline">{{ $lead->budget }}</span>
                @endif
                @if($lead->is_reenquiry)
                <span class="asm-m-badge asm-m-badge-amber">Re-enquiry</span>
                @endif
            </div>

            @if($nextAsmTask)
            <div class="asm-m-next-action">
                <div class="asm-m-next-icon"><i class="fas fa-bolt"></i></div>
                <div class="asm-m-next-body">
                    <span class="asm-m-next-label">Next Action</span>
                    <span class="asm-m-next-title">{{ $nextAsmTask->title ?: 'Open task' }}</span>
                </div>
                <span class="asm-m-next-time">{{ optional($nextAsmTask->scheduled_at)->format('d M, h:i A') ?: 'Pending' }}</span>
            </div>
            @endif
        </div>

        {{-- Quick actions --}}
        <div class="asm-m-actions-card">
            <div class="asm-m-actions-grid">
                <a href="{{ $leadCallUrl ?: '#' }}" data-lead-call-trigger data-lead-id="{{ $lead->id }}" data-lead-phone="{{ $lead->phone }}" class="asm-m-action">
                    <span class="asm-m-action-icon asm-m-action-icon-green"><i class="fas fa-phone-alt"></i></span>
                    <span class="asm-m-action-label">Call</span>
                </a>
                <a href="{{ $leadWhatsAppUrl ?: 'javascript:void(0)' }}"
                   target="_blank"
                   class="asm-m-action"
                   data-whatsapp-url="{{ $leadWhatsAppUrl }}"
                   data-whatsapp-business-url="{{ $leadWhatsAppBusinessUrl }}"
                   data-whatsapp-phone="{{ $leadWhatsAppPhone }}"
                   data-lead-id="{{ $lead->id }}"
                   onclick="return handleLeadWhatsAppClick(event, this)">
                    <span class="asm-m-action-icon asm-m-action-icon-teal"><i class="fab fa-whatsapp"></i></span>
                    <span class="asm-m-action-label">WhatsApp</span>
                </a>
                <button type="button" onclick="openFollowupModal()" class="asm-m-action">
                    <span class="asm-m-action-icon asm-m-action-icon-blue"><i class="fas fa-calendar-check"></i></span>
                    <span class="asm-m-action-label">Follow Up</span>
                </button>
                <button type="button" onclick="openMeetingModal()" class="asm-m-action">
                    <span class="asm-m-action-icon asm-m-action-icon-purple"><i class="fas fa-handshake"></i></span>
                    <span class="asm-m-action-label">Meeting</span>
                </button>
                <button type="button" onclick="openSiteVisitModal()" class="asm-m-action">
                    <span class="asm-m-action-icon asm-m-action-icon-orange"><i class="fas fa-map-marker-alt"></i></span>
                    <span class="asm-m-action-label">Visit</span>
                </button>
                <button type="button" onclick="openScheduleCallTaskModal()" class="asm-m-action">
                    <span class="asm-m-action-icon asm-m-action-icon-slate"><i class="fas fa-tasks"></i></span>
                    <span class="asm-m-action-label">Task</span>
                </button>
                @if(isset($ownerTransferUsers) && $ownerTransferUsers->isNotEmpty())
                <button type="button" onclick="openOwnerTransferModal()" class="asm-m-action">
                    <span class="asm-m-action-icon asm-m-action-icon-indigo"><i class="fas fa-user-edit"></i></span>
                    <span class="asm-m-action-label">Change Owner</span>
                </button>
                @endif
            </div>
            <button type="button" onclick="openLeadRequirementsModal({{ $lead->id }})" class="asm-m-req-btn">
                <i class="fas fa-file-signature"></i>
                Edit Requirement Form
            </button>
        </div>

        {{-- Tabbed content --}}
        <div class="asm-m-tab-card">
            <div class="asm-m-tab-bar" role="tablist" aria-label="Lead detail sections">
                <button type="button" class="asm-mobile-tab-btn is-active" data-asm-lead-tab="timeline">
                    <i class="fas fa-stream"></i> Timeline
                    <span class="asm-m-tab-count">{{ $timelineItems->count() }}</span>
                </button>
                <button type="button" class="asm-mobile-tab-btn" data-asm-lead-tab="tasks">
                    <i class="fas fa-clipboard-list"></i> Tasks
                    <span class="asm-m-tab-count">{{ $leadActionOpenTasks->count() }}</span>
                </button>
                <button type="button" class="asm-mobile-tab-btn" data-asm-lead-tab="notes">
                    <i class="fas fa-sticky-note"></i> Notes
                    <span class="asm-m-tab-count">{{ count($mobileParsedNoteFields) + count($mobilePlainNoteLines) }}</span>
                </button>
                <button type="button" class="asm-mobile-tab-btn" data-asm-lead-tab="info">
                    <i class="fas fa-info-circle"></i> Info
                </button>
            </div>

            <div class="asm-mobile-tab-panel is-active" data-asm-lead-panel="timeline">
                @forelse($timelineItems as $activity)
                @php
                    $mobileAutomationLabel = $activity['metadata']['automation']['label'] ?? null;
                    $mobileAutomationDetails = $activity['metadata']['automation']['details'] ?? null;
                    $mobileIsAutomationEvent = filled($mobileAutomationLabel) && is_array($mobileAutomationDetails);
                    $mobileAutomationDetailLines = [];
                    if ($mobileIsAutomationEvent) {
                        $mobileAutomationDetailLines[] = 'Automation: ' . $mobileAutomationLabel;
                        $mobileAutomationDetailLines[] = 'Trigger: ' . ($mobileAutomationDetails['trigger'] ?? 'Automatic transfer');
                        if (!empty($mobileAutomationDetails['from_user_id']) || !empty($mobileAutomationDetails['to_user_id'])) {
                            $mobileAutomationDetailLines[] = 'Route: User #' . ($mobileAutomationDetails['from_user_id'] ?? 'N/A') . ' -> User #' . ($mobileAutomationDetails['to_user_id'] ?? 'N/A');
                        }
                    }
                @endphp
                <article class="asm-m-tl-item">
                    <div class="asm-m-tl-dot" style="background:{{ $activity['color'] }}15;color:{{ $activity['color'] }};border-color:{{ $activity['color'] }}30">
                        <i class="fas {{ $activity['icon'] }}"></i>
                    </div>
                    <div class="asm-m-tl-body">
                        <span class="asm-m-tl-time">{{ $activity['timestamp']->format('d M Y, h:i A') }}</span>
                        <div class="flex items-center gap-2">
                            <h3 class="asm-m-tl-title">{{ $activity['title'] }}</h3>
                            @if(auth()->user()?->isAdmin() && $mobileIsAutomationEvent)
                                <details class="timeline-automation-info">
                                    <summary class="timeline-automation-summary" aria-label="Automation details">i</summary>
                                    <div class="timeline-automation-popover">{{ $mobileAutomationLabel }}</div>
                                </details>
                            @endif
                        </div>
                        @if($activity['description'])
                        <p class="asm-m-tl-desc">{{ $activity['description'] }}</p>
                        @endif
                        @php
                            $mobileSubmittedForm = $activity['metadata']['submitted_form'] ?? null;
                        @endphp
                        @if(is_array($mobileSubmittedForm) && !empty($mobileSubmittedForm['fields']))
                            <button type="button" class="lead-submitted-form-trigger inline-flex items-center gap-1.5 mt-2 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100" data-form-title="{{ $mobileSubmittedForm['title'] }}" data-form-fields="{{ base64_encode(json_encode($mobileSubmittedForm['fields'])) }}">
                                <i class="fas fa-eye"></i><span>View Details</span>
                            </button>
                        @endif
                        @if($mobileIsAutomationEvent)
                            <div class="asm-m-tl-remark">by Automation</div>
                        @elseif($activity['user'])
                            <div class="asm-m-tl-remark">by {{ $activity['user']->name }}</div>
                        @endif
                        @if(isset($activity['metadata']) && !empty($activity['metadata']))
                            @php
                                $showMobileTaskRemark = ($activity['type'] ?? null) === 'task_completed';
                                $mobileTaskRemark = trim((string) ($activity['metadata']['outcome_remark'] ?? ''));
                            @endphp
                            <div class="asm-m-tl-meta">
                                @if(isset($activity['metadata']['status']) && ($activity['type'] ?? null) !== 'task_created')
                                    <span class="asm-m-meta-chip asm-m-meta-chip-status">
                                        Status: {{ ucfirst($activity['metadata']['status']) }}
                                    </span>
                                @endif
                                @if(isset($activity['metadata']['duration']))
                                    <span class="asm-m-meta-chip asm-m-meta-chip-duration">
                                        Duration: {{ $activity['metadata']['duration'] }}
                                    </span>
                                @endif
                                @if(isset($activity['metadata']['lead_score']))
                                    <span class="asm-m-meta-chip asm-m-meta-chip-score">
                                        Lead Score: {{ $activity['metadata']['lead_score'] }}/5
                                    </span>
                                @endif
                            </div>
                            @if($showMobileTaskRemark)
                                <div class="asm-m-tl-remark">
                                    Remark: {{ $mobileTaskRemark !== '' ? $mobileTaskRemark : 'No remark added' }}
                                </div>
                            @endif
                            @if(!empty($activity['metadata']['can_play_recording']) && !empty($activity['metadata']['recording_route']))
                                <div class="pt-3">
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 px-3 py-3 crm-recording-player" data-expected-duration="{{ (int) ($activity['metadata']['duration'] ?? 0) }}">
                                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                            <span class="text-[11px] font-semibold uppercase tracking-[0.08em] text-emerald-700">
                                                Call Recording
                                            </span>
                                            @if(!empty($activity['metadata']['recording_download_route']))
                                                <a
                                                    href="{{ $activity['metadata']['recording_download_route'] }}"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                                >
                                                    <i class="fas fa-download text-xs"></i>
                                                    <span>Download</span>
                                                </a>
                                            @endif
                                        </div>
                                        <audio preload="metadata" class="crm-recording-audio hidden">
                                            <source src="{{ $activity['metadata']['recording_route'] }}" type="audio/mpeg">
                                            Your browser does not support audio playback.
                                        </audio>
                                        <div class="crm-recording-shell">
                                            <div class="crm-recording-toolbar">
                                                <button type="button" class="crm-recording-play" aria-label="Play recording">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                                <div class="crm-recording-time">
                                                    <span class="crm-recording-current">0:00</span>
                                                    <span>/</span>
                                                    <span class="crm-recording-total">{{ gmdate('i:s', (int) ($activity['metadata']['duration'] ?? 0)) }}</span>
                                                </div>
                                                <div class="crm-recording-rates">
                                                    <button type="button" class="crm-rate-btn is-active" data-rate="1">1x</button>
                                                    <button type="button" class="crm-rate-btn" data-rate="2">2x</button>
                                                    <button type="button" class="crm-rate-btn" data-rate="3">3x</button>
                                                </div>
                                            </div>
                                            <input type="range" min="0" max="1000" value="0" class="crm-recording-progress" aria-label="Seek recording">
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </article>
                @empty
                <div class="asm-m-empty"><i class="fas fa-inbox"></i><span>No timeline activity yet</span></div>
                @endforelse
            </div>

            <div class="asm-mobile-tab-panel" data-asm-lead-panel="tasks">
                @forelse($leadActionOpenTasks as $task)
                    @php
                    $taskModelType = (string) ($task->model_type ?? 'task');
                    $mobileTaskCategory = strtolower((string) ($task->category ?? 'task'));
                    $mobileTaskLabel = str_contains($mobileTaskCategory, 'meeting')
                        ? 'Meeting'
                        : (str_contains($mobileTaskCategory, 'visit') ? 'Visit' : (str_contains($mobileTaskCategory, 'follow') ? 'Follow Up' : 'Task'));
                    $taskIconMap = ['Meeting' => 'fa-handshake', 'Visit' => 'fa-map-marker-alt', 'Follow Up' => 'fa-calendar-check'];
                    $taskIcon = $taskIconMap[$mobileTaskLabel] ?? 'fa-clipboard-check';
                @endphp
                <article class="asm-m-task-item">
                    <div class="asm-m-task-header">
                        <span class="asm-m-task-type"><i class="fas {{ $taskIcon }}"></i> {{ $mobileTaskLabel }}</span>
                        <span class="asm-m-task-status asm-m-task-status-{{ strtolower(str_replace(' ', '', $task->status)) }}">{{ ucfirst(str_replace('_', ' ', $task->status)) }}</span>
                    </div>
                    <h3 class="asm-m-task-title">{{ $task->title ?: ('Task #' . $task->id) }}</h3>
                    <div class="asm-m-task-schedule">
                        <i class="far fa-clock"></i>
                        {{ optional($task->scheduled_at)->format('d M Y, h:i A') ?: 'Not scheduled' }}
                    </div>
                    @if(filled($task->notes) || filled($task->description))
                    <p class="asm-m-task-note">{{ \Illuminate\Support\Str::limit($task->notes ?: $task->description, 140) }}</p>
                    @endif
                    <div class="asm-m-task-footer">
                        <a href="{{ $leadCallUrl ?: '#' }}" data-lead-call-trigger data-lead-id="{{ $lead->id }}" data-lead-phone="{{ $lead->phone }}" data-task-id="{{ $task->id }}" class="asm-m-task-btn">
                            <i class="fas fa-phone-alt"></i> Call
                        </a>
                        <button type="button" class="asm-m-task-btn asm-m-task-btn-primary" onclick="openCompleteAsmTaskModal()">
                            <i class="fas fa-check-circle"></i> Take Action
                        </button>
                    </div>
                </article>
                @empty
                <div class="asm-m-empty"><i class="fas fa-clipboard-check"></i><span>No active tasks</span></div>
                @endforelse
            </div>

            <div class="asm-mobile-tab-panel" data-asm-lead-panel="notes">
                @if(!empty($mobileParsedNoteFields))
                    <div class="asm-m-notes-grid">
                        @foreach($mobileParsedNoteFields as $noteField)
                        <article class="asm-m-note-tile">
                            <span class="asm-m-note-label">{{ $noteField['label'] }}</span>
                            @if(!empty($noteField['is_url']))
                                <a class="asm-m-note-value text-emerald-700 underline" href="{{ $noteField['value'] }}" target="_blank" rel="noopener">Open Link</a>
                            @else
                                <span class="asm-m-note-value">{{ $noteField['value'] }}</span>
                            @endif
                        </article>
                        @endforeach
                    </div>
                @endif
                @if(!empty($mobilePlainNoteLines))
                    @foreach($mobilePlainNoteLines as $plainNoteLine)
                    <article class="asm-m-note-plain">{{ $plainNoteLine }}</article>
                    @endforeach
                @endif
                @if(empty($mobileParsedNoteFields) && empty($mobilePlainNoteLines))
                <div class="asm-m-empty"><i class="fas fa-sticky-note"></i><span>No notes available</span></div>
                @endif
            </div>

            <div class="asm-mobile-tab-panel" data-asm-lead-panel="info">
                <div class="asm-m-info-grid">
                    @foreach($mobileInfoRows as $infoRow)
                    <article class="asm-m-info-tile">
                        <span class="asm-m-info-label">{{ $infoRow['label'] }}</span>
                        @if(!empty($infoRow['is_url']))
                            <a class="asm-m-info-value text-emerald-700 underline" href="{{ $infoRow['value'] }}" target="_blank" rel="noopener">Open Link</a>
                        @else
                            <span class="asm-m-info-value">{{ $infoRow['value'] }}</span>
                        @endif
                    </article>
                    @endforeach
                </div>

                @if($leadInterestedProjects->isNotEmpty())
                <div class="asm-m-info-section">
                    <span class="asm-m-info-label">Interested Projects</span>
                    <div class="asm-m-badges" style="margin-top:8px">
                        @foreach($leadInterestedProjects as $project)
                        <span class="asm-m-badge asm-m-badge-outline">{{ $project }}</span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </section>
    @endif

    <!-- Professional Header Section -->
    <div class="bg-gradient-to-r from-[#063A1C] via-[#205A44] to-[#063A1C] rounded-2xl shadow-xl border border-emerald-800/20 overflow-hidden mb-6 asm-mobile-desktop-block">
        <div class="p-4 sm:p-6 md:p-8">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4 sm:mb-6">
                <div class="flex items-start gap-3 sm:gap-4 flex-1 min-w-0">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 md:w-20 md:h-20 rounded-xl sm:rounded-2xl bg-white/10 backdrop-blur-sm border-2 border-white/20 flex items-center justify-center text-white text-xl sm:text-2xl md:text-3xl font-bold shadow-lg flex-shrink-0">
                        {{ strtoupper(substr($lead->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-xl sm:text-2xl md:text-3xl font-bold text-white mb-2 leading-tight break-words word-wrap">{{ $lead->name }}</h1>
                        <div class="flex flex-wrap items-center gap-2 sm:gap-3 mt-2 sm:mt-3">
                            <a href="{{ $leadCallUrl ?: '#' }}" data-lead-call-trigger data-lead-id="{{ $lead->id }}" data-lead-phone="{{ $lead->phone }}" class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-1.5 sm:py-2 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-lg text-white text-xs sm:text-sm md:text-base font-medium transition-all duration-200 border border-white/20 shadow-sm whitespace-nowrap">
                                <i class="fas fa-phone text-xs sm:text-sm"></i>
                                <span class="truncate max-w-[120px] sm:max-w-none">{{ $lead->phone }}</span>
                            </a>
                            @if($lead->email)
                            <a href="mailto:{{ $lead->email }}" class="inline-flex items-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-1.5 sm:py-2 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-lg text-white text-xs sm:text-sm md:text-base font-medium transition-all duration-200 border border-white/20 shadow-sm whitespace-nowrap">
                                <i class="fas fa-envelope text-xs sm:text-sm"></i>
                                <span class="hidden sm:inline truncate max-w-xs">{{ $lead->email }}</span>
                                <span class="sm:hidden">Email</span>
                            </a>
                            @endif
                            <span class="px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg text-[10px] sm:text-xs font-semibold whitespace-nowrap {{
                                $lead->status === 'dead' ? 'bg-red-500/20 text-red-100 border border-red-400/30' : 
                                ($lead->status === 'closed' ? 'bg-green-500/20 text-green-100 border border-green-400/30' : 
                                'bg-blue-500/20 text-blue-100 border border-blue-400/30') 
                            }}">
                                {{ ucfirst(str_replace('_', ' ', $lead->status)) }}
                            </span>
                            @if($lead->is_reenquiry)
                            <span class="px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg text-[10px] sm:text-xs font-semibold whitespace-nowrap bg-amber-500/20 text-amber-100 border border-amber-400/30">
                                Re-enquiry
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    @if($user?->isAdmin())
                    <a href="{{ route('leads.preview', ['lead' => $lead->id, 'back' => $backUrl]) }}" class="px-3 sm:px-4 py-2 bg-white text-emerald-950 hover:bg-emerald-50 rounded-lg text-xs sm:text-sm font-semibold transition-colors border border-white shadow-sm whitespace-nowrap">
                        <i class="fas fa-columns mr-1.5"></i>Try New View
                    </a>
                    @endif
                    <button type="button" class="lead-favorite-btn lead-favorite-btn-desktop {{ $isLeadFavorite ? 'is-favorite' : '' }}" data-lead-favorite-btn data-favorite-state="{{ $isLeadFavorite ? '1' : '0' }}" onclick="toggleLeadDetailFavorite({{ $lead->id }})" aria-label="{{ $isLeadFavorite ? 'Remove from favorite leads' : 'Add to favorite leads' }}" title="{{ $isLeadFavorite ? 'Remove from favorites' : 'Add to favorites' }}">
                        <i class="{{ $isLeadFavorite ? 'fas' : 'far' }} fa-heart"></i>
                    </button>
                    <a href="{{ $backUrl }}" class="px-3 sm:px-4 py-2 bg-white/10 hover:bg-white/20 backdrop-blur-sm rounded-lg text-white text-xs sm:text-sm font-medium transition-all duration-200 border border-white/20 shadow-sm whitespace-nowrap">
                        <i class="fas fa-arrow-left mr-1.5 sm:mr-2"></i><span class="hidden sm:inline">Back</span><span class="sm:hidden">Back</span>
                    </a>
                </div>
            </div>
            
            <!-- Quick Actions Section -->
            <div class="pt-4 sm:pt-6 border-t border-white/10">
                <h3 class="text-xs sm:text-sm font-semibold text-white/90 mb-3 sm:mb-4 uppercase tracking-wider">Quick Actions</h3>
                <div class="flex flex-wrap gap-2 sm:gap-3">
                    <!-- Call Button -->
                    <a href="{{ $leadCallUrl ?: '#' }}" data-lead-call-trigger data-lead-id="{{ $lead->id }}" data-lead-phone="{{ $lead->phone }}" class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-white/20 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[80px] sm:min-w-0">
                        <i class="fas fa-phone text-xs sm:text-sm"></i>
                        <span>Call</span>
                    </a>
                    
                    <!-- WhatsApp Button -->
                    <a href="{{ $leadWhatsAppUrl ?: 'javascript:void(0)' }}" 
                       target="_blank"
                       class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-white/20 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[100px] sm:min-w-0"
                       data-whatsapp-url="{{ $leadWhatsAppUrl }}"
                       data-whatsapp-business-url="{{ $leadWhatsAppBusinessUrl }}"
                       data-whatsapp-phone="{{ $leadWhatsAppPhone }}"
                       onclick="return handleLeadWhatsAppClick(event, this)">
                        <i class="fab fa-whatsapp text-xs sm:text-sm"></i>
                        <span>WhatsApp</span>
                    </a>
                    
                    <!-- Follow-up Button -->
                    <button onclick="openFollowupModal()" class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-white/20 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[100px] sm:min-w-0">
                        <i class="fas fa-calendar-check text-xs sm:text-sm"></i>
                        <span>Follow-up</span>
                    </button>
                    
                    <!-- Site Visit Button -->
                    <button onclick="openSiteVisitModal()" class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-white/20 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[110px] sm:min-w-0">
                        <i class="fas fa-map-marker-alt text-xs sm:text-sm"></i>
                        <span>Site Visit</span>
                    </button>
                    
                    <!-- Meeting Button -->
                    <button onclick="openMeetingModal()" class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-white/20 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[100px] sm:min-w-0">
                        <i class="fas fa-handshake text-xs sm:text-sm"></i>
                        <span>Meeting</span>
                    </button>
                    
                    <!-- Schedule Call Task Button -->
                    <button onclick="openScheduleCallTaskModal()" class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-white/20 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[120px] sm:min-w-0">
                        <i class="fas fa-calendar-alt text-xs sm:text-sm"></i>
                        <span>Schedule Task</span>
                    </button>

                    @if($user && ($user->isAdmin() || $user->isCrm() || $user->isSalesHead() || $user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager() || $user->isSalesExecutive()))
                    <button type="button" id="markCloserDraftBtn" onclick="markLeadAsCloserDraft()" class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-amber-500/90 hover:bg-amber-500 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-amber-300/50 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[125px] sm:min-w-0">
                        <i class="fas fa-file-signature text-xs sm:text-sm"></i>
                        <span>Mark as Closer</span>
                    </button>
                    @endif

                                @if($canUseLeadTaskFlow)
                                    @if($leadActionOpenTasks->isNotEmpty())
                                    <button onclick="openCompleteAsmTaskModal()" class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-white/20 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[145px] sm:min-w-0">
                            <i class="fas fa-check-circle text-xs sm:text-sm"></i>
                            <span>Mark Task Complete</span>
                        </button>
                        @else
                        <button type="button" disabled class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/5 backdrop-blur-sm text-white/60 rounded-lg border border-white/10 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap cursor-not-allowed min-w-[125px] sm:min-w-0">
                            <i class="fas fa-ban text-xs sm:text-sm"></i>
                            <span>No Open Task</span>
                        </button>
                        @endif
                    @endif

                    @if($user && ($user->isAdmin() || $user->isCrm() || $user->isAssistantSalesManager() || $user->isSeniorManager() || $user->isSalesManager()))
                    <button onclick="openOwnerTransferModal()" class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-white/20 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[130px] sm:min-w-0">
                        <i class="fas fa-user-edit text-xs sm:text-sm"></i>
                        <span>Change Owner</span>
                    </button>
                    @if($user->isAdmin() || $user->isCrm())
                    <form method="POST" action="{{ route('leads.destroy', $lead->id) }}" class="inline" onsubmit="return confirm('Remove this lead from the list? It will be moved to trash and can be recovered.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-red-600/90 hover:bg-red-700 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-red-500/50 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[120px] sm:min-w-0">
                            <i class="fas fa-trash-alt text-xs sm:text-sm"></i>
                            <span>Delete lead</span>
                        </button>
                    </form>
                    @endif
                    @endif
                    
                    @include('leads.partials.reopen')
                    <!-- Edit Requirements Button - Show for roles that can use centralized form -->
                    @if($user)
                        <button type="button" onclick="openLeadRequirementsModal({{ $lead->id }})" class="flex items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2 sm:py-2.5 bg-white/10 hover:bg-white/20 backdrop-blur-sm text-white rounded-lg transition-all duration-200 border border-white/20 shadow-sm font-medium text-xs sm:text-sm whitespace-nowrap min-w-[140px] sm:min-w-0">
                            <i class="fas fa-edit text-xs sm:text-sm"></i>
                            <span>Edit Requirements</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 asm-mobile-desktop-block" style="width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden;">
        <!-- Lead Information Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl sm:rounded-2xl shadow-md border border-slate-200/80 p-4 sm:p-6 md:p-8 mb-4 sm:mb-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 mb-1">Lead Information</p>
                        <h2 class="text-xl md:text-2xl font-bold text-slate-900">Contact Details</h2>
                    </div>
                    <div class="hidden md:flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-slate-600 to-slate-700 text-white shadow-sm">
                        <i class="fas fa-user-circle text-lg"></i>
                    </div>
                </div>
                
                <div class="space-y-3 sm:space-y-4 md:space-y-5">
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1.5 sm:mb-2">Name</p>
                        <p class="text-sm sm:text-base font-semibold text-slate-900 break-words">{{ $lead->name }}</p>
                    </div>
                    
                    @if($lead->email)
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1.5 sm:mb-2">Email</p>
                        <p class="text-sm sm:text-base font-semibold text-slate-900 break-all">
                            <a href="mailto:{{ $lead->email }}" class="text-blue-600 hover:text-blue-700 hover:underline transition-colors break-all">
                                {{ $lead->email }}
                            </a>
                        </p>
                    </div>
                    @endif
                    
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1.5 sm:mb-2">Phone</p>
                        <p class="text-sm sm:text-base font-semibold text-slate-900 break-words">
                            <a href="{{ $leadCallUrl ?: '#' }}" data-lead-call-trigger data-lead-id="{{ $lead->id }}" data-lead-phone="{{ $lead->phone }}" class="text-blue-600 hover:text-blue-700 hover:underline transition-colors break-words">
                                {{ $lead->phone }}
                            </a>
                        </p>
                    </div>
                    
                    @if($lead->address)
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1.5 sm:mb-2">Address</p>
                        <p class="text-sm sm:text-base font-semibold text-slate-900 break-words">{{ $lead->address }}</p>
                        @if($lead->city || $lead->state || $lead->pincode)
                            <p class="text-xs sm:text-sm text-slate-600 mt-1 break-words">
                                {{ trim(implode(', ', array_filter([$lead->city, $lead->state, $lead->pincode]))) }}
                            </p>
                        @endif
                    </div>
                    @endif
                    
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1.5 sm:mb-2">Source</p>
                        @if($user && ($user->isAdmin() || $user->isCrm()))
                            <form method="POST" action="{{ route('leads.update', $lead->id) }}" class="space-y-3">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="source_inline_update" value="1">
                                <select name="source" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-900 focus:border-[#205A44] focus:ring-2 focus:ring-[#205A44]">
                                    @foreach(\App\Models\Lead::sourceOptions() as $value => $label)
                                        <option value="{{ $value }}" {{ \App\Models\Lead::normalizeSource($lead->source) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="inline-flex items-center rounded-lg bg-gradient-to-r from-[#063A1C] to-[#205A44] px-3 py-2 text-xs font-semibold text-white">
                                    Save Source
                                </button>
                            </form>
                        @else
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm sm:text-base font-semibold text-slate-900 break-words">{{ $lead->source_label }}</p>
                                @if($leadImportChannelTag)
                                    <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-indigo-700">{{ $leadImportChannelTag }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    
                    @if($lead->budget)
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1.5 sm:mb-2">Budget</p>
                        <p class="text-sm sm:text-base font-semibold text-slate-900 break-words">{{ $lead->budget }}</p>
                    </div>
                    @endif
                    
                    @if($lead->property_type)
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1.5 sm:mb-2">Property Type</p>
                        <p class="text-sm sm:text-base font-semibold text-slate-900 break-words">{{ ucfirst($lead->property_type) }}</p>
                    </div>
                    @endif
                    
                    @if($leadInterestedProjects->count() > 0)
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-2 sm:mb-3">Interested Projects</p>
                        <div class="flex flex-wrap gap-1.5 sm:gap-2">
                            @foreach($leadInterestedProjects as $project)
                                <span class="inline-flex items-center px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg text-[10px] sm:text-xs font-semibold bg-gradient-to-r from-slate-600 to-slate-700 text-white shadow-sm break-words">
                                    {{ $project }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1.5 sm:mb-2">Created By</p>
                        <p class="text-sm sm:text-base font-semibold text-slate-900 break-words">{{ $lead->creator_display_name }}</p>
                        @if($lead->creator_display_context)
                            <p class="text-xs sm:text-sm text-slate-500 mt-1 break-words">{{ $lead->creator_display_context }}</p>
                        @endif
                            <p class="text-xs sm:text-sm text-slate-500 mt-1">{{ optional($leadDisplayCreatedAt)->format('M d, Y h:i A') ?: 'N.A' }}</p>
                    </div>
                    
                    @if($lead->activeAssignments->count() > 0)
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1.5 sm:mb-2">Assigned To</p>
                        <div class="space-y-1.5 sm:space-y-2">
                            @foreach($lead->activeAssignments as $assignment)
                                <p class="text-sm sm:text-base font-semibold text-slate-900 break-words">{{ $assignment->assignedTo->name ?? 'N/A' }}</p>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                    <!-- Response Time Section -->
                    @if(isset($responseTimeData) && $responseTimeData['assigned_at'])
                    <div class="p-4 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200/70 mt-4">
                        <h3 class="text-sm font-semibold text-slate-800 mb-4 flex items-center gap-2">
                            <i class="fas fa-clock text-blue-600"></i>
                            Response Time
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <p class="text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1">Assigned At</p>
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ $responseTimeData['assigned_at']->format('M d, Y h:i A') }}
                                </p>
                            </div>
                            @if($responseTimeData['called_at'])
                            <div>
                                <p class="text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1">Called At</p>
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ $responseTimeData['called_at']->format('M d, Y h:i A') }}
                                </p>
                            </div>
                            @if($responseTimeData['response_time_minutes'] !== null)
                            <div>
                                <p class="text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1">Response Time</p>
                                <p class="text-base font-bold text-blue-600">
                                    @if($responseTimeData['response_time_minutes'] < 60)
                                        {{ $responseTimeData['response_time_minutes'] }} minutes
                                    @else
                                        {{ floor($responseTimeData['response_time_minutes'] / 60) }}h {{ $responseTimeData['response_time_minutes'] % 60 }}m
                                    @endif
                                </p>
                            </div>
                            @endif
                            @else
                            <div>
                                <p class="text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1">Status</p>
                                <p class="text-sm font-semibold text-orange-600">Not Called Yet</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                    
                    @if($displayLeadNotes || $metaFieldRows->isNotEmpty())
                    @php
                        $noteLines = preg_split('/\r\n|\r|\n/', trim((string) $displayLeadNotes)) ?: [];
                        $parsedNoteFields = [];
                        $plainNoteLines = [];

                        foreach ($noteLines as $noteLine) {
                            $trimmedNoteLine = trim($noteLine);
                            if ($trimmedNoteLine === '') {
                                continue;
                            }

                            if (str_contains($trimmedNoteLine, ':')) {
                                [$noteKey, $noteValue] = array_pad(explode(':', $trimmedNoteLine, 2), 2, '');
                                $noteKey = trim($noteKey);
                                $noteValue = trim($noteValue);

                                if ($noteKey !== '') {
                                    $parsedNoteFields[] = [
                                        'label' => ucwords(str_replace('_', ' ', $noteKey)),
                                        'value' => $noteValue !== '' ? $noteValue : 'N/A',
                                    ];
                                    continue;
                                }
                            }

                            $plainNoteLines[] = $trimmedNoteLine;
                        }

                        foreach ($metaFieldRows as $metaFieldRow) {
                            $parsedNoteFields[] = $metaFieldRow;
                        }

                        $renderStructuredNotes = !empty($parsedNoteFields) && count($parsedNoteFields) >= count($plainNoteLines);
                    @endphp
                    <div class="p-3 sm:p-4 rounded-lg sm:rounded-xl bg-slate-50 border border-slate-200/70">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <p class="text-[11px] sm:text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500">Notes</p>
                            @if($renderStructuredNotes)
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-emerald-700 border border-emerald-200">
                                    Structured Details
                                </span>
                            @endif
                        </div>

                        @if($renderStructuredNotes)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($parsedNoteFields as $noteField)
                                    <div class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1">{{ $noteField['label'] }}</p>
                                        @if(!empty($noteField['is_url']))
                                            <a href="{{ $noteField['value'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-semibold text-emerald-700 leading-relaxed break-all hover:text-emerald-800">
                                                Open Link <i class="fas fa-external-link-alt text-[10px]"></i>
                                            </a>
                                        @else
                                            <p class="text-sm font-semibold text-slate-900 leading-relaxed break-words">{{ $noteField['value'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @if(!empty($plainNoteLines))
                                <div class="mt-3 rounded-xl border border-slate-200 bg-white px-3 py-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-2">Additional Note</p>
                                    <p class="text-xs sm:text-sm text-slate-900 whitespace-pre-wrap leading-relaxed break-words">{{ implode("\n", $plainNoteLines) }}</p>
                                </div>
                            @endif
                        @else
                            <p class="text-xs sm:text-sm text-slate-900 whitespace-pre-wrap leading-relaxed break-words">{{ $displayLeadNotes }}</p>
                        @endif
                    </div>
                    @endif

                    @php
                        $importMeta = $lead->latestImportedLead?->import_data['metadata'] ?? [];
                    @endphp
                    @if(($lead->latestImportedLead?->import_data['kind'] ?? null) === 'old_crm' && !empty($importMeta))
                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200/70">
                        <p class="text-[12px] font-semibold uppercase tracking-[0.08em] text-amber-800 mb-3">Old CRM Import Data</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($importMeta as $metaKey => $metaValue)
                                @if(!blank($metaValue))
                                    <div>
                                        <p class="text-xs text-amber-700 mb-1">{{ ucwords(str_replace('_', ' ', $metaKey)) }}</p>
                                        <p class="text-sm font-semibold text-slate-900 whitespace-pre-wrap break-words">{{ $metaValue }}</p>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @endif
                    
                    <!-- Source Information -->
                    @php
                        $sourceInfo = [];
                        $sheetAssignment = $lead->activeAssignments->firstWhere('sheet_config_id', '!=', null);
                        if ($sheetAssignment && $sheetAssignment->sheetConfig) {
                            $sourceInfo['type'] = 'Google Sheets';
                            $sourceInfo['sheet_name'] = $sheetAssignment->sheetConfig->sheet_name;
                            $sourceInfo['sheet_id'] = $sheetAssignment->sheetConfig->sheet_id;
                            $sourceInfo['row_number'] = $sheetAssignment->sheet_row_number;
                        } elseif (in_array($lead->source, ['google_sheets', 'sheet'], true)) {
                            $sourceInfo['type'] = 'Sheet';
                        } elseif (in_array($lead->source, ['pabbly', 'facebook_lead_ads', 'social_media', 'meta'], true)) {
                            $sourceInfo['type'] = 'Meta';
                        } elseif (in_array($lead->source, ['csv'], true)) {
                            $sourceInfo['type'] = 'Sheet';
                        } elseif (in_array($lead->source, ['mcube', 'call', 'ivr'], true)) {
                            $sourceInfo['type'] = 'Ivr';
                        } else {
                            $sourceInfo['type'] = \App\Models\Lead::displaySourceLabel($lead->source);
                        }
                        
                        // Get form field values for source tracking (excluding sheet_name and sheet_id as per user request)
                        $sourceFields = $lead->formFieldValues()->whereIn('field_key', [
                            'source_row_number'
                        ])->get()->keyBy('field_key');

                        if ($sourceFields->has('source_row_number')) {
                            $sourceInfo['row_number'] = $sourceFields['source_row_number']->field_value;
                        }
                    @endphp
                    
                    @if(!empty($sourceInfo) && isset($sourceInfo['type']))
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/70 mt-4">
                        <p class="text-[12px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-3">Source Information</p>
                        <div class="space-y-2">
                            <div>
                                <p class="text-xs text-slate-500 mb-1">Type</p>
                                <p class="text-sm font-semibold text-slate-900">{{ $sourceInfo['type'] ?? 'N/A' }}</p>
                            </div>
                            @if(isset($sourceInfo['row_number']))
                            <div>
                                <p class="text-xs text-slate-500 mb-1">Row Number</p>
                                <p class="text-sm font-semibold text-slate-900">{{ $sourceInfo['row_number'] }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Form Data / Custom Fields -->
            @php
                $formFieldValues = $lead->formFieldValues()
                    ->whereNotIn('field_key', ['source_sheet_name', 'source_sheet_id', 'source_row_number'])
                    ->orderBy('created_at')
                    ->get()
                    ->groupBy(function($fv) {
                        // Group by source: meta_* for Meta/Facebook, custom_* for custom, etc.
                        if (str_starts_with($fv->field_key, 'meta_')) {
                            return 'Meta/Facebook Form';
                        } elseif (str_starts_with($fv->field_key, 'custom_')) {
                            return 'Custom Fields';
                        } else {
                            return 'Other Fields';
                        }
                    });
            @endphp
            
            @if($formFieldValues->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-md border border-slate-200/80 p-6 md:p-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Form Data</p>
                        <h2 class="text-xl md:text-2xl font-bold text-slate-900">Additional Information</h2>
                    </div>
                    <div class="hidden md:flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-br from-slate-600 to-slate-700 text-white shadow-sm">
                        <i class="fas fa-list-alt"></i>
                    </div>
                </div>
                <div class="space-y-5">
                    @foreach($formFieldValues as $groupName => $fields)
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 md:p-5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center text-sm font-semibold uppercase">
                                {{ substr($groupName, 0, 1) }}
                            </div>
                            <h3 class="text-sm md:text-base font-semibold text-slate-800">{{ $groupName }}</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                            @foreach($fields as $fieldValue)
                            @php
                                $fieldConfig = $fieldValue->fieldConfig();
                                $displayLabel = $fieldConfig ? $fieldConfig->field_label : str_replace('_', ' ', ucfirst(str_replace(['meta_', 'custom_'], '', $fieldValue->field_key)));
                                $fieldValueText = $fieldValue->field_value ?? 'N/A';
                                $normalizedInterestedProjectValues = strtolower((string) $fieldValue->field_key) === 'interested_projects'
                                    ? $normalizeInterestedProjectNames($fieldValue->field_value)
                                    : collect();
                                
                                // Determine icon based on field key
                                $icon = 'fa-file-alt';
                                $fieldKeyLower = strtolower($fieldValue->field_key);
                                if (str_contains($fieldKeyLower, 'name') || str_contains($fieldKeyLower, 'ad')) {
                                    $icon = 'fa-building';
                                } elseif (str_contains($fieldKeyLower, 'budget') || str_contains($fieldKeyLower, 'price') || str_contains($fieldKeyLower, 'amount')) {
                                    $icon = 'fa-money-bill-wave';
                                } elseif (str_contains($fieldKeyLower, 'email')) {
                                    $icon = 'fa-envelope';
                                } elseif (str_contains($fieldKeyLower, 'phone') || str_contains($fieldKeyLower, 'mobile')) {
                                    $icon = 'fa-phone';
                                } elseif (str_contains($fieldKeyLower, 'location') || str_contains($fieldKeyLower, 'address') || str_contains($fieldKeyLower, 'city') || str_contains($fieldKeyLower, 'lucknow')) {
                                    $icon = 'fa-map-marker-alt';
                                } elseif (str_contains($fieldKeyLower, 'property') || str_contains($fieldKeyLower, 'plot') || str_contains($fieldKeyLower, 'villa')) {
                                    $icon = 'fa-home';
                                } elseif (str_contains($fieldKeyLower, 'purpose') || str_contains($fieldKeyLower, 'use')) {
                                    $icon = 'fa-key';
                                } elseif (str_contains($fieldKeyLower, 'job') || str_contains($fieldKeyLower, 'title') || str_contains($fieldKeyLower, 'occupation')) {
                                    $icon = 'fa-briefcase';
                                } elseif (str_contains($fieldKeyLower, 'buy') || str_contains($fieldKeyLower, 'when') || str_contains($fieldKeyLower, 'time')) {
                                    $icon = 'fa-calendar';
                                } elseif (str_contains($fieldKeyLower, 'row') || str_contains($fieldKeyLower, 'number')) {
                                    $icon = 'fa-hashtag';
                                }
                                
                                // Check if value should be displayed as a badge/pill
                                $isBadgeValue = in_array(strtolower($fieldValueText), ['yes', 'no', 'end use / reside', 'end_use_/_reside', 'plots_/_villas', 'plots & villas', 'within_month', 'within a month', 'immediate_use_(_1_yr)', 'immediate use (1 yr)']) || 
                                               str_contains(strtolower($fieldValueText), '₹') || 
                                               str_contains(strtolower($fieldValueText), 'lk') || 
                                               str_contains(strtolower($fieldValueText), 'cr');
                            @endphp
                            <div class="p-4 rounded-xl bg-white border border-slate-200/70 shadow-sm hover:shadow-md transition-shadow duration-200">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0">
                                        <i class="fas {{ $icon }} text-slate-600 text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500 mb-2">
                                            {{ $displayLabel }}
                                        </p>
                                        @if($isBadgeValue)
                                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ $fieldValueText }}
                                        </span>
                                        @elseif($normalizedInterestedProjectValues->isNotEmpty())
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($normalizedInterestedProjectValues as $projectName)
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 border border-emerald-200">
                                                {{ $projectName }}
                                            </span>
                                            @endforeach
                                        </div>
                                        @elseif(str_contains(strtolower($fieldValueText), '@') && str_contains(strtolower($fieldValueText), '.'))
                                        <a href="mailto:{{ $fieldValueText }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700 hover:underline break-all">
                                            {{ $fieldValueText }}
                                        </a>
                                        @else
                                        <p class="text-sm font-semibold text-slate-900 leading-snug break-words">
                                            {{ $fieldValueText }}
                                        </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Quick Stats -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Stats</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Calls</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $lead->tasks()->where('task_type', 'calling')->count() + $lead->managerTasks()->where('type', 'phone_call')->count() }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Site Visits</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $lead->siteVisits->count() }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Follow-ups</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $lead->followUps->count() }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Meetings</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $lead->meetings->count() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="lg:col-span-2">
            @include('leads.partials.proposal-card')

            @if($user && ($user->isAdmin() || $user->isCrm()))
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                <div class="flex items-center justify-between gap-3 mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Old Tasks</h2>
                        <p class="text-sm text-gray-500 mt-1">Manage open lead tasks directly from this page.</p>
                    </div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                        {{ $oldTasks->count() }} Open
                    </span>
                </div>

                @if($oldTasks->isNotEmpty())
                    <div class="space-y-4" id="oldTasksList">
                        @foreach($oldTasks as $oldTask)
                            @php
                                $statusLabel = ucfirst(str_replace('_', ' ', $oldTask['status']));
                                $modelLabel = $oldTask['model_type'] === 'telecaller_task' ? 'Telecaller Task' : 'Manager Task';
                            @endphp
                            <div
                                class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 sm:px-5"
                                data-old-task-row
                                data-model-type="{{ $oldTask['model_type'] }}"
                                data-task-id="{{ $oldTask['id'] }}"
                                data-task-category="{{ $oldTask['category'] ?? 'other' }}"
                                data-task-title="{{ e($oldTask['title'] ?? $oldTask['task_label']) }}"
                                data-task-description="{{ e($oldTask['description'] ?? '') }}"
                                data-task-notes="{{ e($oldTask['notes'] ?? '') }}"
                            data-task-scheduled-at="{{ optional($oldTask['scheduled_at'])->toIso8601String() }}"
                            data-task-meeting-id="{{ $oldTask['meeting_id'] ?? '' }}"
                            data-task-site-visit-id="{{ $oldTask['site_visit_id'] ?? '' }}"
                            data-task-site-visit-project="{{ e($oldTask['site_visit_project'] ?? '') }}"
                            data-task-follow-up-id="{{ $oldTask['follow_up_id'] ?? '' }}"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <h3 class="text-base font-semibold text-slate-900 break-words">{{ $oldTask['task_label'] }}</h3>
                                            <span class="inline-flex items-center rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600 border border-slate-200">
                                                {{ $modelLabel }}
                                            </span>
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-amber-700 border border-amber-200">
                                                {{ $statusLabel }}
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1">Assigned To</p>
                                                <p class="font-medium text-slate-900">{{ $oldTask['assigned_to_name'] ?: 'Unassigned' }}</p>
                                            </div>
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1">Scheduled At</p>
                                                <p class="font-medium text-slate-900">
                                                    {{ $oldTask['scheduled_at'] ? $oldTask['scheduled_at']->format('M d, Y h:i A') : 'Not scheduled' }}
                                                </p>
                                            </div>
                                        </div>

                                        @if(filled($oldTask['notes']))
                                            <div class="mt-3 rounded-xl bg-white px-3 py-3 border border-slate-200">
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-1">Notes</p>
                                                <p class="text-sm text-slate-700 break-words">{{ \Illuminate\Support\Str::limit($oldTask['notes'], 220) }}</p>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex flex-col sm:flex-row gap-2 lg:min-w-[220px] lg:justify-end">
                                        <button
                                            type="button"
                                            onclick='openOldTaskTransferModal(@json($oldTask['model_type']), {{ $oldTask['id'] }}, @json($oldTask['task_label']), @json($oldTask['assigned_to_name'] ?: 'Unassigned'))'
                                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-100"
                                        >
                                            <i class="fas fa-random text-xs"></i>
                                            <span>Transfer</span>
                                        </button>
                                        @if($oldTask['can_complete'])
                                        <button
                                            type="button"
                                            onclick='handleOldTaskCompleteAction(this)'
                                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-95"
                                        >
                                            <i class="fas fa-check-circle text-xs"></i>
                                            <span>Mark Complete</span>
                                        </button>
                                        @endif
                                        @if($oldTask['can_delete'])
                                        <button
                                            type="button"
                                            onclick='deleteOldTask(@json($oldTask['model_type']), {{ $oldTask['id'] }}, @json($oldTask['task_label']))'
                                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100"
                                        >
                                            <i class="fas fa-trash-alt text-xs"></i>
                                            <span>Delete</span>
                                        </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <i class="fas fa-inbox text-3xl text-slate-300 mb-3"></i>
                        <p class="text-sm font-medium text-slate-600">No old open task found for this lead.</p>
                    </div>
                @endif
            </div>
            @endif

            @if($canViewLeadCallHistory)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between mb-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 mb-1">Admin Only</p>
                        <h2 class="text-xl font-bold text-gray-900">Call History</h2>
                        <p class="text-sm text-slate-500 mt-1">Android app se synced call duration aur unanswered calls.</p>
                    </div>
                    @if(!empty($leadCallSummary['last_call_at']))
                        <div class="rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                            Last call: {{ $leadCallSummary['last_call_at']->format('d M, h:i A') }}
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-[11px] uppercase tracking-[0.08em] font-semibold text-slate-500">Total Calls</div>
                        <div class="mt-2 text-2xl font-bold text-slate-900">{{ $leadCallSummary['total_calls'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-[11px] uppercase tracking-[0.08em] font-semibold text-emerald-700">Answered</div>
                        <div class="mt-2 text-2xl font-bold text-emerald-800">{{ $leadCallSummary['answered_calls'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <div class="text-[11px] uppercase tracking-[0.08em] font-semibold text-amber-700">Unanswered / Missed</div>
                        <div class="mt-2 text-2xl font-bold text-amber-800">{{ $leadCallSummary['unanswered_calls'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
                        <div class="text-[11px] uppercase tracking-[0.08em] font-semibold text-rose-700">Rejected</div>
                        <div class="mt-2 text-2xl font-bold text-rose-800">{{ $leadCallSummary['rejected_calls'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
                        <div class="text-[11px] uppercase tracking-[0.08em] font-semibold text-indigo-700">Talk Time</div>
                        <div class="mt-2 text-2xl font-bold text-indigo-800">{{ $formatLeadCallDuration($leadCallSummary['total_talk_seconds'] ?? 0) }}</div>
                    </div>
                </div>

                @if($leadCallLogs->isNotEmpty())
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">User</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Call</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Status</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Duration</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Date & Time</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Source</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Recording</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach($leadCallLogs as $callLog)
                                    @php
                                        $callDuration = (int) ($callLog->duration ?? 0);
                                        $isAnsweredCall = $callDuration > 0;
                                        $caller = $callLog->user ?: $callLog->telecaller;
                                        $statusPillClass = match($callLog->status) {
                                            'completed' => 'bg-emerald-100 text-emerald-700',
                                            'rejected' => 'bg-rose-100 text-rose-700',
                                            'busy' => 'bg-yellow-100 text-yellow-700',
                                            default => $callLog->call_type === 'incoming'
                                                ? 'bg-red-100 text-red-700'
                                                : 'bg-amber-100 text-amber-700',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-slate-900">{{ $caller?->name ?? 'Unknown user' }}</div>
                                            <div class="text-xs text-slate-500">{{ $callLog->phone_number }}</div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                <i class="fas {{ $callLog->call_type === 'incoming' ? 'fa-phone-volume' : 'fa-phone-alt' }}"></i>
                                                {{ $callLog->call_type_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusPillClass }}">{{ $callLog->status_label }}</span>
                                            @if($callLog->call_outcome && !$isAnsweredCall)
                                                <div class="mt-1 text-xs text-slate-500">{{ $callLog->call_outcome_label }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $formatLeadCallDuration($callDuration) }}</td>
                                        <td class="px-4 py-3 align-top text-slate-700">
                                            {{ optional($callLog->start_time)->format('d M Y, h:i A') ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            @if($callLog->synced_from_mobile)
                                                <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Android App</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Manual</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            @if(filled($callLog->recording_url))
                                                <a href="{{ route('calls.recording', $callLog) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">
                                                    <i class="fas fa-play"></i> Play
                                                </a>
                                            @elseif(str_contains(strtolower((string) $callLog->notes), 'recording unavailable'))
                                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">Unavailable</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Not enabled</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if(filled($callLog->notes) || filled($callLog->call_outcome))
                                    <tr class="bg-slate-50/70">
                                        <td colspan="7" class="px-4 py-2 text-xs text-slate-600">
                                            @if(filled($callLog->call_outcome))
                                                <span class="font-semibold">Outcome:</span> {{ $callLog->call_outcome_label }}
                                            @endif
                                            @if(filled($callLog->notes))
                                                <span class="font-semibold {{ filled($callLog->call_outcome) ? 'ml-3' : '' }}">Notes:</span> {{ $callLog->notes }}
                                            @endif
                                        </td>
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <i class="fas fa-phone-slash text-3xl text-slate-300 mb-3"></i>
                        <p class="text-sm font-medium text-slate-600">No call history found for this lead yet.</p>
                    </div>
                @endif
            </div>
            @endif

            @if($canViewLeadCallHistory)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between mb-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 mb-1">Admin Only</p>
                        <h2 class="text-xl font-bold text-gray-900">MCube Outbound Attempts</h2>
                        <p class="text-sm text-slate-500 mt-1">CRM se initiated calls ka API result, final callback aur recording status.</p>
                    </div>
                    @if($leadMcubeOutboundAttempts->isNotEmpty())
                        <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600">
                            Last {{ $leadMcubeOutboundAttempts->count() }} attempt(s)
                        </div>
                    @endif
                </div>

                @if($leadMcubeOutboundAttempts->isNotEmpty())
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Time</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">User</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Agent</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Customer</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">API Result</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Final Status</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Recording</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach($leadMcubeOutboundAttempts as $attempt)
                                    @php
                                        $matchedCallLog = $attempt['matched_call_log'] ?? null;
                                        $recordingUrl = $attempt['recording_url'] ?? null;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 align-top whitespace-nowrap text-slate-700">
                                            {{ optional($attempt['attempted_at'] ?? null)->format('d M Y, h:i A') ?? '-' }}
                                            @if(!empty($attempt['api_callid']))
                                                <div class="mt-1 font-mono text-[11px] text-slate-400">Callid: {{ $attempt['api_callid'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-slate-900">{{ $attempt['user']?->name ?? 'Unknown user' }}</div>
                                            <div class="text-xs text-slate-500">Attempt #{{ $attempt['id'] }}</div>
                                        </td>
                                        <td class="px-4 py-3 align-top font-mono text-xs text-slate-700">{{ $attempt['agent_number'] ?: '-' }}</td>
                                        <td class="px-4 py-3 align-top font-mono text-xs text-slate-700">{{ $attempt['customer_number'] ?: '-' }}</td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ ($attempt['status'] ?? '') === 'success' ? 'bg-emerald-100 text-emerald-700' : ((($attempt['status'] ?? '') === 'pending') ? 'bg-blue-100 text-blue-700' : 'bg-rose-100 text-rose-700') }}">
                                                {{ ucfirst($attempt['status'] ?? 'unknown') }}
                                            </span>
                                            @if(!empty($attempt['http_status']))
                                                <div class="mt-1 text-xs text-slate-500">HTTP {{ $attempt['http_status'] }}</div>
                                            @endif
                                            <div class="mt-1 max-w-[260px] text-xs text-slate-500">{{ $attempt['api_message'] ?? '-' }}</div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $attempt['final_status_class'] ?? 'bg-slate-100 text-slate-700' }}">
                                                {{ $attempt['final_status_label'] ?? 'Unknown' }}
                                            </span>
                                            @if(!empty($attempt['matched_webhook']))
                                                <div class="mt-1 text-xs text-slate-500">Webhook #{{ $attempt['matched_webhook']->id }}</div>
                                            @elseif(!empty($matchedCallLog))
                                                <div class="mt-1 text-xs text-slate-500">Call log #{{ $matchedCallLog->id }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            @if($matchedCallLog && filled($matchedCallLog->recording_url))
                                                <a href="{{ route('calls.recording', $matchedCallLog) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">
                                                    <i class="fas fa-play"></i> Play
                                                </a>
                                            @elseif(filled($recordingUrl))
                                                <a href="{{ $recordingUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">
                                                    <i class="fas fa-external-link-alt"></i> Open
                                                </a>
                                            @else
                                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Recording not received from MCube</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <i class="fas fa-phone-volume text-3xl text-slate-300 mb-3"></i>
                        <p class="text-sm font-medium text-slate-600">No MCube outbound attempt found for this lead yet.</p>
                    </div>
                @endif
            </div>
            @endif

            @if($canViewLeadCallHistory)
            <div class="bg-white rounded-xl shadow-sm border border-emerald-100 p-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between mb-6">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-emerald-600 mb-1">Meta WABA</p>
                        <h2 class="text-xl font-bold text-gray-900">WhatsApp Call Events</h2>
                        <p class="text-sm text-slate-500 mt-1">Incoming WhatsApp calls, missed calls aur callback requests.</p>
                    </div>
                    @if($wabaCallEvents->where('event_type', 'callback_request')->count())
                        <div class="rounded-full border border-amber-100 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700">
                            {{ $wabaCallEvents->where('event_type', 'callback_request')->count() }} callback request(s)
                        </div>
                    @endif
                </div>

                @if($wabaCallEvents->isNotEmpty())
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Event</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Phone</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Assigned</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Task</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach($wabaCallEvents as $event)
                                    @php
                                        $eventTone = $event->event_type === 'callback_request' || in_array($event->status, ['missed', 'missed_call', 'no_answer', 'unanswered'], true)
                                            ? 'bg-amber-100 text-amber-700'
                                            : 'bg-emerald-100 text-emerald-700';
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $eventTone }}">{{ $event->event_type_label }}</span>
                                            <div class="mt-1 text-xs text-slate-500">{{ $event->status_label }}</div>
                                        </td>
                                        <td class="px-4 py-3 align-top font-semibold text-slate-900">{{ $event->phone ? '+' . $event->phone : '-' }}</td>
                                        <td class="px-4 py-3 align-top text-slate-700">{{ $event->assignedTo?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 align-top">
                                            @if($event->telecallerTask)
                                                <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">Task #{{ $event->telecallerTask->id }} · {{ ucfirst($event->telecallerTask->status) }}</span>
                                            @elseif($event->shouldCreateFollowUpTask())
                                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Pending task/link</span>
                                            @else
                                                <span class="text-slate-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 align-top text-slate-700">{{ optional($event->occurred_at)->format('d M Y, h:i A') ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-emerald-200 bg-emerald-50/50 px-6 py-8 text-center">
                        <i class="fab fa-whatsapp text-3xl text-emerald-300 mb-3"></i>
                        <p class="text-sm font-medium text-slate-600">No WhatsApp call events captured for this lead yet.</p>
                    </div>
                @endif
            </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Activity Timeline</h2>
                @php
                    $adminInternalAuditRemarks = collect($internalAuditRemarks ?? []);
                @endphp
                @if(auth()->user()?->isAdmin() && (filled($internalAuditStage ?? null) || $adminInternalAuditRemarks->isNotEmpty()))
                    <section class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-semibold text-amber-950"><i class="fas fa-user-shield mr-2"></i>Internal Audit</h3>
                            @if(filled($internalAuditStage ?? null))<span class="rounded bg-amber-200 px-2 py-1 text-xs font-bold text-amber-900">{{ $internalAuditStage }}</span>@endif
                        </div>
                        @if($adminInternalAuditRemarks->isNotEmpty())
                            <div class="mt-3 space-y-2">
                                @foreach($adminInternalAuditRemarks as $internalAuditRemark)
                                    <div class="rounded border border-amber-100 bg-white px-3 py-2 text-sm text-slate-700">
                                        <div class="mb-1 text-xs text-slate-500">{{ $internalAuditRemark->editor?->name ?? 'Lead Quality Auditor' }} · {{ optional($internalAuditRemark->edited_at)->format('d M Y, h:i A') }}</div>
                                        <div class="whitespace-pre-wrap">{{ $internalAuditRemark->new_value }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif
                
                @php
                    $timelineItems = collect($timeline);
                @endphp

                @if($timelineItems->count() > 0)
                    <div class="relative">
                        <!-- Timeline Line -->
                        <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                        
                        <div class="space-y-6">
                            @php
                                $currentDate = null;
                            @endphp
                            
                            @foreach($timelineItems as $activity)
                                @php
                                    $activityDate = $activity['timestamp']->format('Y-m-d');
                                    $showDateHeader = $currentDate !== $activityDate;
                                    $automationLabel = $activity['metadata']['automation']['label'] ?? null;
                                    $automationDetails = $activity['metadata']['automation']['details'] ?? null;
                                    $isAutomationEvent = filled($automationLabel) && is_array($automationDetails);
                                    $automationDetailLines = [];
                                    if ($isAutomationEvent) {
                                        $automationDetailLines[] = 'Automation: ' . $automationLabel;
                                        $automationDetailLines[] = 'Trigger: ' . ($automationDetails['trigger'] ?? 'Automatic transfer');
                                        if (!empty($automationDetails['from_user_id']) || !empty($automationDetails['to_user_id'])) {
                                            $automationDetailLines[] = 'Route: User #' . ($automationDetails['from_user_id'] ?? 'N/A') . ' -> User #' . ($automationDetails['to_user_id'] ?? 'N/A');
                                        }
                                        if (!empty($automationDetails['note'])) {
                                            $automationDetailLines[] = 'Note: ' . $automationDetails['note'];
                                        }
                                    }
                                    if ($showDateHeader) {
                                        $currentDate = $activityDate;
                                    }
                                @endphp
                                
                                @if($showDateHeader)
                                    <div class="relative">
                                        <div class="flex items-center mb-4">
                                            <div class="flex-1 border-t border-gray-200"></div>
                                            <span class="px-4 text-sm font-semibold text-gray-500">
                                                @if($activity['timestamp']->isToday())
                                                    Today
                                                @elseif($activity['timestamp']->isYesterday())
                                                    Yesterday
                                                @elseif($activity['timestamp']->isCurrentWeek())
                                                    {{ $activity['timestamp']->format('l, M d') }}
                                                @else
                                                    {{ $activity['timestamp']->format('M d, Y') }}
                                                @endif
                                            </span>
                                            <div class="flex-1 border-t border-gray-200"></div>
                                        </div>
                                    </div>
                                @endif
                                
                                <div class="relative pl-16 pb-6">
                                    <!-- Timeline Dot -->
                                    <div class="absolute left-0 top-1">
                                        <div class="w-12 h-12 rounded-full border-4 border-white shadow-md flex items-center justify-center" style="background-color: {{ $activity['color'] }}20;">
                                            <i class="fas {{ $activity['icon'] }} text-sm" style="color: {{ $activity['color'] }};"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Activity Card -->
                                    <div class="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition-colors">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="mb-1 flex items-center gap-2">
                                                    <h3 class="font-semibold text-gray-900">{{ $activity['title'] }}</h3>
                                                    @if(auth()->user()?->isAdmin() && $isAutomationEvent)
                                                        <details class="timeline-automation-info">
                                                            <summary class="timeline-automation-summary" aria-label="Automation details">i</summary>
                                                            <div class="timeline-automation-popover">{{ $automationLabel }}</div>
                                                        </details>
                                                    @endif
                                                </div>
                                                <p class="text-sm text-gray-600 mb-2">{{ $activity['description'] }}</p>
                                                @php
                                                    $submittedForm = $activity['metadata']['submitted_form'] ?? null;
                                                @endphp
                                                @if(is_array($submittedForm) && !empty($submittedForm['fields']))
                                                    <button type="button" class="lead-submitted-form-trigger inline-flex items-center gap-1.5 mb-2 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100" data-form-title="{{ $submittedForm['title'] }}" data-form-fields="{{ base64_encode(json_encode($submittedForm['fields'])) }}">
                                                        <i class="fas fa-eye"></i><span>View Details</span>
                                                    </button>
                                                @endif
                                                
                                                @if(isset($activity['metadata']) && !empty($activity['metadata']))
                                                    @php
                                                        $showTaskRemark = ($activity['type'] ?? null) === 'task_completed';
                                                        $taskRemark = trim((string) ($activity['metadata']['outcome_remark'] ?? ''));
                                                    @endphp
                                                    <div class="mt-2 space-y-1">
                                                        @if(isset($activity['metadata']['status']) && ($activity['type'] ?? null) !== 'task_created')
                                                            <span class="inline-block px-2 py-1 text-xs rounded bg-blue-100 text-blue-800">
                                                                Status: {{ ucfirst($activity['metadata']['status']) }}
                                                            </span>
                                                        @endif
                                                        @if($showTaskRemark)
                                                            <div class="text-xs text-slate-600">
                                                                Remark: {{ $taskRemark !== '' ? $taskRemark : 'No remark added' }}
                                                            </div>
                                                        @endif
                                                        @if(isset($activity['metadata']['duration']))
                                                            <span class="inline-block px-2 py-1 text-xs rounded bg-green-100 text-green-800">
                                                                Duration: {{ $activity['metadata']['duration'] }}
                                                            </span>
                                                        @endif
                                                        @if(!empty($activity['metadata']['can_play_recording']) && !empty($activity['metadata']['recording_route']))
                                                            <div class="pt-3 space-y-2">
                                                                <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 px-3 py-3 crm-recording-player" data-expected-duration="{{ (int) ($activity['metadata']['duration'] ?? 0) }}">
                                                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                                                        <span class="text-xs font-semibold uppercase tracking-[0.08em] text-emerald-700">
                                                                            Call Recording
                                                                        </span>
                                                                        @if(!empty($activity['metadata']['recording_download_route']))
                                                                            <a
                                                                                href="{{ $activity['metadata']['recording_download_route'] }}"
                                                                                class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                                                                            >
                                                                                <i class="fas fa-download text-xs"></i>
                                                                                <span>Download</span>
                                                                            </a>
                                                                        @endif
                                                                    </div>
                                                                    <audio preload="metadata" class="crm-recording-audio hidden">
                                                                        <source src="{{ $activity['metadata']['recording_route'] }}" type="audio/mpeg">
                                                                        Your browser does not support audio playback.
                                                                    </audio>
                                                                    <div class="crm-recording-shell">
                                                                        <div class="crm-recording-toolbar">
                                                                            <button type="button" class="crm-recording-play" aria-label="Play recording">
                                                                                <i class="fas fa-play"></i>
                                                                            </button>
                                                                            <div class="crm-recording-time">
                                                                                <span class="crm-recording-current">0:00</span>
                                                                                <span>/</span>
                                                                                <span class="crm-recording-total">{{ gmdate('i:s', (int) ($activity['metadata']['duration'] ?? 0)) }}</span>
                                                                            </div>
                                                                            <div class="crm-recording-rates">
                                                                                <button type="button" class="crm-rate-btn is-active" data-rate="1">1x</button>
                                                                                <button type="button" class="crm-rate-btn" data-rate="2">2x</button>
                                                                                <button type="button" class="crm-rate-btn" data-rate="3">3x</button>
                                                                            </div>
                                                                        </div>
                                                                        <input type="range" min="0" max="1000" value="0" class="crm-recording-progress" aria-label="Seek recording">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                @if(isset($activity['metadata']['lead_score']))
                                                            <span class="inline-block px-2 py-1 text-xs rounded bg-purple-100 text-purple-800">
                                                                Lead Score: {{ $activity['metadata']['lead_score'] }}/5
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                            
                                            <div class="text-right ml-4">
                                                <p class="text-xs text-gray-500">
                                                    {{ $activity['timestamp']->format('h:i A') }}
                                                </p>
                                                @if($isAutomationEvent)
                                                    <p class="text-xs text-gray-400 mt-1">
                                                        by Automation
                                                    </p>
                                                @elseif($activity['user'])
                                                    <p class="text-xs text-gray-400 mt-1">
                                                        by {{ $activity['user']->name }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="text-center py-12">
                        <i class="fas fa-history text-gray-300 text-6xl mb-4"></i>
                        <p class="text-gray-500">No activities found for this lead.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>

<div id="submittedFormModal" class="fixed inset-0 z-[120] hidden items-center justify-center bg-slate-900/45 p-4" role="dialog" aria-modal="true" aria-labelledby="submittedFormTitle">
    <div class="w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 id="submittedFormTitle" class="text-lg font-bold text-slate-900">Submitted Form</h2>
                <p class="mt-0.5 text-sm text-slate-500">Form values saved for this activity</p>
            </div>
            <button type="button" id="submittedFormClose" class="flex h-9 w-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100" aria-label="Close form"><i class="fas fa-times"></i></button>
        </div>
        <div id="submittedFormContent" class="max-h-[70vh] overflow-y-auto p-5"></div>
    </div>
</div>

<!-- Modals for Quick Actions -->
<!-- Follow-up Modal -->
<div id="followupModal" class="fixed inset-0 bg-black/40 hidden overflow-y-auto h-full w-full z-50">
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-5xl rounded-[28px] bg-white shadow-2xl overflow-hidden border border-slate-200">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <h3 class="text-xl font-bold text-slate-900">Follow Up</h3>
                <button onclick="closeFollowupModal()" class="text-slate-400 hover:text-slate-600 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="followupForm" onsubmit="submitFollowup(event)" class="p-6 md:p-8">
                <div class="rounded-[24px] border border-emerald-100 bg-gradient-to-br from-white to-slate-50 p-5 md:p-7">
                    <div class="mb-5">
                        <h4 class="text-xl font-bold text-slate-900">Follow Up Required</h4>
                        <p class="mt-1 text-sm text-slate-600">Outcome section se hi next call schedule aur reminder controls manage karo.</p>
                    </div>
                    <label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-900 mb-5">
                        <input type="checkbox" id="followup_required" checked class="h-4 w-4 rounded border-slate-300 text-red-500 focus:ring-red-400">
                        <span>{{ $followUpRequiredField['label'] }}</span>
                    </label>
                    <input type="hidden" name="type" value="call">
                    <div class="mb-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Follow Up Date @if($followUpDateField['required'])<span class="text-red-500">*</span>@endif</label>
                            <input type="date" name="scheduled_date" @if($followUpDateField['required']) required @endif class="w-full rounded-2xl border border-emerald-400 px-4 py-3 text-base text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Follow Up Time @if($followUpDateField['required'])<span class="text-red-500">*</span>@endif</label>
                            <input type="time" name="scheduled_time" @if($followUpDateField['required']) required @endif class="w-full rounded-2xl border border-emerald-400 px-4 py-3 text-base text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        </div>
                    </div>
                    @if($followUpDateField['help_text'])
                        <small class="mt-2 block text-sm text-slate-500">{{ $followUpDateField['help_text'] }}</small>
                    @endif
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">{{ $followUpNotesField['label'] }}</label>
                        <textarea name="notes" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="{{ $followUpNotesField['placeholder'] }}"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeFollowupModal()" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white">Save Follow Up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Site Visit Modal -->
<div id="siteVisitModal" class="fixed inset-0 bg-black/40 hidden overflow-y-auto h-full w-full z-50">
    <div class="min-h-full flex items-start sm:items-center justify-center p-4">
        @include('components.site-visit-form-card', [
            'title' => 'Site Visit',
            'icon' => 'fas fa-map-marker-alt',
            'closeHandler' => 'closeSiteVisitModal()',
            'formId' => 'siteVisitForm',
            'submitHandler' => 'submitSiteVisit(event)',
            'projectInputId' => 'siteVisitProjectInput',
            'projectOptionsId' => 'siteVisitProjectOptions',
            'projectHiddenId' => 'siteVisitProjectHidden',
            'scheduledAtId' => 'siteVisitScheduledAt',
            'visitSequenceId' => 'siteVisitVisitSequence',
            'submitLabel' => 'Save Visit',
            'submitIcon' => 'fas fa-save',
        ])
    </div>
</div>
@endunless

<!-- Schedule Call Task Modal -->
<div id="scheduleCallTaskModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Schedule Call Task</h3>
                <button onclick="closeScheduleCallTaskModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="scheduleCallTaskForm" onsubmit="submitScheduleCallTask(event)">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Scheduled Date & Time *</label>
                        <div class="grid gap-3 md:grid-cols-2">
                            <input type="date" name="scheduled_date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <input type="time" name="scheduled_time" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                        <small class="text-xs text-gray-500 mt-1 block">Select when you want to make the call</small>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Task Notes (Optional)</label>
                        <textarea name="notes" rows="3" placeholder="Add any notes or reminders for this call task..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeScheduleCallTaskModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-all duration-200">Schedule Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($canUseLeadTaskFlow)
<div id="completeAsmTaskModal" class="fixed inset-0 bg-black/40 hidden overflow-y-auto h-full w-full z-50">
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-[28px] bg-white shadow-2xl overflow-hidden border border-slate-200">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Mark Task Complete</h3>
                    <p class="mt-1 text-sm text-slate-500">Select which open call task should be completed from this lead.</p>
                </div>
                <button type="button" onclick="closeCompleteAsmTaskModal()" class="text-slate-400 hover:text-slate-600 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="completeAsmTaskForm" onsubmit="submitCompleteAsmTask(event)" class="p-6 md:p-8">
                <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
                    @forelse($leadActionOpenTasks as $task)
                    @php
                        $taskModelType = (string) ($task->model_type ?? 'task');
                        $rawTaskCategory = strtolower((string) ($task->category ?? ''));
                        $taskTextForCategory = strtolower(trim(implode(' ', array_filter([
                            $task->title,
                            $task->notes,
                            $task->description,
                        ]))));
                        $taskCategory = match (true) {
                            (bool) $task->meeting_id,
                            str_contains($rawTaskCategory, 'meeting'),
                            str_contains($taskTextForCategory, 'meeting') => 'meeting',
                            (bool) $task->site_visit_id => 'site_visit',
                            (bool) $task->follow_up_id,
                            str_contains($rawTaskCategory, 'follow_up'),
                            str_contains($rawTaskCategory, 'follow up') => 'follow_up',
                            filled($rawTaskCategory) => $rawTaskCategory,
                            default => 'other',
                        };
                        $taskTypeBadge = match ($taskCategory) {
                            'follow_up' => ['label' => 'F', 'classes' => 'bg-blue-100 text-blue-700 border-blue-200'],
                            'meeting' => ['label' => 'M', 'classes' => 'bg-emerald-100 text-emerald-700 border-emerald-200'],
                            'site_visit' => ['label' => 'S', 'classes' => 'bg-cyan-100 text-cyan-700 border-cyan-200'],
                            default => null,
                        };
                    @endphp
                    <label class="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-4 transition hover:border-emerald-300 hover:bg-emerald-50/60">
                        <input
                            type="radio"
                            name="task_id"
                            value="{{ $task->id }}"
                            data-task-model-type="{{ $taskModelType }}"
                            data-task-category="{{ $task->category ?? 'other' }}"
                            data-task-title="{{ e($task->title ?? '') }}"
                            data-task-notes="{{ e($task->notes ?? '') }}"
                            data-task-description="{{ e($task->description ?? '') }}"
                            data-task-scheduled-at="{{ optional($task->scheduled_at)->toIso8601String() }}"
                            data-task-meeting-id="{{ $task->meeting_id }}"
                            data-task-site-visit-id="{{ $task->site_visit_id }}"
                            data-task-site-visit-project="{{ e($task->site_visit_project ?? '') }}"
                            data-task-follow-up-id="{{ $task->follow_up_id }}"
                            class="mt-1 h-4 w-4 border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            {{ $loop->first ? 'checked' : '' }}
                        >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($taskTypeBadge)
                                <span class="inline-flex items-center justify-center rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.12em] {{ $taskTypeBadge['classes'] }}">
                                    {{ $taskTypeBadge['label'] }}
                                </span>
                                @endif
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ $task->title ?: ('Phone call task #' . $task->id) }}
                                </p>
                                @if($loop->first)
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-emerald-700">
                                    Latest
                                </span>
                                @endif
                                <span class="inline-flex items-center rounded-full bg-slate-200 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600">
                                    {{ str_replace('_', ' ', $task->status) }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-slate-600">
                                Scheduled:
                                <span class="font-medium text-slate-800">
                                    {{ optional($task->scheduled_at)->format('M d, Y h:i A') ?? 'Not scheduled' }}
                                </span>
                            </p>
                            @if(filled($task->notes) || filled($task->description))
                            <p class="mt-2 text-sm text-slate-500 break-words">
                                {{ \Illuminate\Support\Str::limit($task->notes ?: $task->description, 160) }}
                            </p>
                            @endif
                        </div>
                    </label>
                    @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        No open ASM call task is available for this lead.
                    </div>
                    @endforelse
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeCompleteAsmTaskModal()" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" @disabled($leadActionOpenTasks->isEmpty()) class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white disabled:cursor-not-allowed disabled:opacity-50">
                        Complete Selected Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($canUseLeadTaskFlow)
<div id="verifyRejectPromptModal" class="modal">
    <div class="modal-content asm-outcome-modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Call Outcome</h3>
            <button class="close-modal" onclick="closeTaskOutcomeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="asm-outcome-modal-body">
                <div class="asm-outcome-grid">
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-green" onclick="selectTaskOutcome('interested')">
                        <i class="fas fa-thumbs-up"></i>
                        <span>Interested</span>
                    </button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-slate" onclick="selectTaskOutcome('not_interested')">
                        <i class="fas fa-user-slash"></i>
                        <span>Not Interested</span>
                    </button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-blue" onclick="selectTaskOutcome('follow_up')">
                        <i class="fas fa-clock"></i>
                        <span>Follow Up</span>
                    </button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-amber" onclick="selectTaskOutcome('cnp')">
                        <i class="fas fa-phone-slash"></i>
                        <span>CNP</span>
                    </button>
                    <button type="button" class="asm-outcome-btn asm-outcome-btn-red asm-outcome-btn-full" onclick="selectTaskOutcome('junk')">
                        <i class="fas fa-trash"></i>
                        <span>Junk</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="managerLeadRequirementFormModal" class="modal manager-lead-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Lead Requirement Form - Verify Prospect</h3>
            <button class="close-btn" onclick="cancelManagerLeadRequirementForm()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="managerLeadTaskFormContainer">
                <div style="text-align: center; padding: 40px;">
                    <div class="spinner" style="display: inline-block;"></div>
                    <p style="margin-top: 15px; color: #666;">Loading form...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="rejectReasonModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="rejectReasonModalTitle">Add Remark</h3>
            <button class="close-modal" onclick="cancelJunkRemarkModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="rejectReasonInput">Remark <span style="color:#6b7280; font-weight:400;">(optional)</span></label>
                <textarea id="rejectReasonInput" rows="4" placeholder="Add context for this outcome..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"></textarea>
            </div>
            <div class="form-footer" style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn-cancel" onclick="cancelJunkRemarkModal()">Cancel</button>
                <button type="button" class="btn-reject" id="rejectReasonSubmitBtn" onclick="submitOutcomeRemark()">Save</button>
            </div>
        </div>
    </div>
</div>

<div id="cnpTimeSelectionModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 id="outcomeDateTimeModalTitle">Select Retry Time for CNP</h3>
            <button class="close-modal" onclick="cancelOutcomeDateTimeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="padding: 20px;">
                <p id="outcomeDateTimeModalText" style="font-size: 14px; color: #666; margin-bottom: 20px;">Choose when to retry this call:</p>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
                    <button type="button" class="time-option-btn" onclick="selectCnpTime(15, event)" data-minutes="15" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">15 Minutes</button>
                    <button type="button" class="time-option-btn" onclick="selectCnpTime(30, event)" data-minutes="30" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">30 Minutes</button>
                    <button type="button" class="time-option-btn" onclick="selectCnpTime(60, event)" data-minutes="60" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">1 Hour</button>
                    <button type="button" class="time-option-btn" onclick="selectCnpTime(120, event)" data-minutes="120" style="padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">2 Hours</button>
                </div>
                <div style="margin-bottom: 20px;">
                    <button type="button" class="time-option-btn" onclick="showCustomTimePicker()" id="customTimeOptionBtn" style="width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; background: white; color: #333; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s;">Custom Date & Time</button>
                </div>
                <div id="customTimePickerContainer" style="display: none; padding: 16px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                    <div style="margin-bottom: 12px;">
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;"><strong>Date</strong> <span style="color: #d32f2f;">*</span></label>
                        <input type="date" id="cnpCustomDate" min="" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #333; margin-bottom: 6px;"><strong>Time</strong> <span style="color: #d32f2f;">*</span></label>
                        <input type="time" id="cnpCustomTime" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                    </div>
                    <small style="display: block; margin-top: 8px; color: #666; font-size: 12px;">Select a future date and time</small>
                </div>
                <div id="selectedTimeDisplay" style="padding: 12px; background: #e8f5e9; border-radius: 6px; margin-bottom: 20px; display: none;">
                    <p style="font-size: 14px; color: #2e7d32; margin: 0;"><strong>Selected:</strong> <span id="selectedTimeText"></span></p>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="outcomeDateTimeRemark" style="display:block; font-size:14px; font-weight:500; color:#333; margin-bottom:6px;">Remark <span style="color:#6b7280; font-weight:400;">(optional)</span></label>
                    <textarea id="outcomeDateTimeRemark" rows="3" placeholder="Add follow-up or CNP context..." style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 20px; border-top: 1px solid #e0e0e0;">
            <button type="button" onclick="cancelOutcomeDateTimeModal()" style="padding: 10px 20px; border: 1px solid #ddd; border-radius: 6px; background: white; color: #333; cursor: pointer; font-size: 14px; font-weight: 500;">Cancel</button>
            <button type="button" id="outcomeDateTimeConfirmBtn" onclick="confirmOutcomeDateTimeSelection()" style="padding: 10px 20px; background: #f59e0b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;">Confirm</button>
        </div>
    </div>
</div>

<div id="followUpActionHubModal" class="modal">
    <div class="modal-content follow-up-action-hub-modal">
        <div class="modal-header follow-up-action-hub-header">
            <div>
                <h3>Follow-Up Actions</h3>
                <p class="follow-up-action-hub-subtitle">Choose the next step for this follow-up conversation.</p>
            </div>
            <button class="close-modal" onclick="closeFollowUpActionHubModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="follow-up-action-hub-grid">
                <button type="button" class="follow-up-action-tile follow-up-action-tile-blue" onclick="handleFollowUpHubAction('reschedule')">
                    <span class="follow-up-action-icon"><i class="fas fa-calendar-alt"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Reschedule Follow Up</strong>
                        <small>Pick the next call date and time.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-green" onclick="handleFollowUpHubAction('complete')">
                    <span class="follow-up-action-icon"><i class="fas fa-check-circle"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Follow Up Complete</strong>
                        <small>Close this task without creating another one.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-slate" onclick="handleFollowUpHubAction('edit_requirement')">
                    <span class="follow-up-action-icon"><i class="fas fa-file-signature"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Edit Requirement Form</strong>
                        <small>Update requirement details and keep the task open.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-emerald" onclick="handleFollowUpHubAction('meeting')">
                    <span class="follow-up-action-icon"><i class="fas fa-handshake"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Schedule Meeting</strong>
                        <small>Create a meeting and complete this follow-up.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-cyan" onclick="handleFollowUpHubAction('visit')">
                    <span class="follow-up-action-icon"><i class="fas fa-map-marker-alt"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Schedule Visit</strong>
                        <small>Create a site visit and complete this follow-up.</small>
                    </span>
                </button>
            </div>

            <div class="follow-up-more-wrap">
                <button type="button" class="follow-up-more-toggle" onclick="toggleFollowUpMoreActions()">
                    <span><i class="fas fa-ellipsis-h"></i> More Outcomes</span>
                    <i class="fas fa-chevron-down" id="followUpMoreChevron"></i>
                </button>

                <div id="followUpMoreActionsPanel" class="follow-up-more-panel">
                    <button type="button" class="follow-up-more-btn" onclick="handleFollowUpHubAction('interested')">
                        <i class="fas fa-thumbs-up"></i>
                        <span>Interested</span>
                    </button>
                    <button type="button" class="follow-up-more-btn" onclick="handleFollowUpHubAction('not_interested')">
                        <i class="fas fa-user-slash"></i>
                        <span>Not Interested</span>
                    </button>
                    <button type="button" class="follow-up-more-btn" onclick="handleFollowUpHubAction('cnp')">
                        <i class="fas fa-phone-slash"></i>
                        <span>CNP</span>
                    </button>
                    <button type="button" class="follow-up-more-btn" onclick="handleFollowUpHubAction('junk')">
                        <i class="fas fa-trash"></i>
                        <span>Junk</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="visitActionHubModal" class="modal">
    <div class="modal-content follow-up-action-hub-modal">
        <div class="modal-header follow-up-action-hub-header">
            <div>
                <h3>Visit Actions</h3>
                <p class="follow-up-action-hub-subtitle">Choose the next step for this visit workflow.</p>
            </div>
            <button class="close-modal" onclick="closeVisitActionHubModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="follow-up-action-hub-grid">
                <button type="button" class="follow-up-action-tile follow-up-action-tile-green" onclick="handleVisitHubAction('complete')">
                    <span class="follow-up-action-icon"><i class="fas fa-check-circle"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Visited</strong>
                        <small>Upload proof and close this site visit reminder.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-blue" onclick="handleVisitHubAction('reschedule')">
                    <span class="follow-up-action-icon"><i class="fas fa-calendar-alt"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Reschedule Visit</strong>
                        <small>Pick a new visit date and time for this lead.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-slate" onclick="handleVisitHubAction('not_available')">
                    <span class="follow-up-action-icon"><i class="fas fa-user-clock"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Customer Not Available</strong>
                        <small>Save the failed attempt with a short remark.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-emerald" onclick="handleVisitHubAction('follow_up')">
                    <span class="follow-up-action-icon"><i class="fas fa-phone"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Follow-up Needed</strong>
                        <small>Create the next follow-up task from this visit flow.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-cyan" onclick="handleVisitHubAction('cancelled')">
                    <span class="follow-up-action-icon"><i class="fas fa-ban"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Cancelled</strong>
                        <small>Cancel the site visit with a mandatory reason.</small>
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>

<div id="meetingActionHubModal" class="modal">
    <div class="modal-content follow-up-action-hub-modal">
        <div class="modal-header follow-up-action-hub-header">
            <div>
                <h3>Meeting Actions</h3>
                <p class="follow-up-action-hub-subtitle">Capture proof or choose the next business step for this meeting.</p>
            </div>
            <button class="close-modal" onclick="closeMeetingActionHubModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="follow-up-action-hub-grid">
                <button type="button" class="follow-up-action-tile follow-up-action-tile-green" onclick="handleMeetingHubAction('complete')">
                    <span class="follow-up-action-icon"><i class="fas fa-check-circle"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Meeting Complete</strong>
                        <small>Upload proof and close this meeting task.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-blue" onclick="handleMeetingHubAction('reschedule')">
                    <span class="follow-up-action-icon"><i class="fas fa-calendar-alt"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Reschedule Meeting</strong>
                        <small>Choose a new meeting slot and close this task.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-slate" onclick="handleMeetingHubAction('edit_requirement')">
                    <span class="follow-up-action-icon"><i class="fas fa-file-signature"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Edit Requirement Form</strong>
                        <small>Update requirement details and keep the meeting task open.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-cyan" onclick="handleMeetingHubAction('visit')">
                    <span class="follow-up-action-icon"><i class="fas fa-map-marker-alt"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Schedule Visit</strong>
                        <small>Create a site visit and complete this meeting task.</small>
                    </span>
                </button>
                <button type="button" class="follow-up-action-tile follow-up-action-tile-emerald" onclick="handleMeetingHubAction('follow_up')">
                    <span class="follow-up-action-icon"><i class="fas fa-phone"></i></span>
                    <span class="follow-up-action-copy">
                        <strong>Schedule Follow Up</strong>
                        <small>Create a follow-up task on the selected date and time.</small>
                    </span>
                </button>
            </div>

            <div class="follow-up-more-wrap">
                <button type="button" class="follow-up-more-toggle" onclick="toggleMeetingMoreActions()">
                    <span><i class="fas fa-ellipsis-h"></i> More Outcomes</span>
                    <i class="fas fa-chevron-down" id="meetingMoreActionsIcon"></i>
                </button>

                <div id="meetingMoreActionsPanel" class="follow-up-more-panel">
                    <button type="button" class="follow-up-more-btn" onclick="handleMeetingHubAction('interested')">
                        <i class="fas fa-thumbs-up"></i>
                        <span>Interested</span>
                    </button>
                    <button type="button" class="follow-up-more-btn" onclick="handleMeetingHubAction('not_interested')">
                        <i class="fas fa-user-slash"></i>
                        <span>Not Interested</span>
                    </button>
                    <button type="button" class="follow-up-more-btn" onclick="handleMeetingHubAction('junk')">
                        <i class="fas fa-trash"></i>
                        <span>Junk</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="leadDetailCompleteMeetingModal" class="modal">
    <div class="modal-content follow-up-action-hub-modal">
        <div class="modal-header follow-up-action-hub-header">
            <div>
                <h3>Complete Meeting</h3>
                <p class="follow-up-action-hub-subtitle">Upload proof and choose the next action from the same completion flow.</p>
            </div>
            <button class="close-modal" onclick="closeLeadDetailCompleteMeetingModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="leadDetailCompleteMeetingForm" class="space-y-4" onsubmit="submitLeadDetailCompleteMeeting(event)">
                <div>
                    <label for="leadDetailMeetingProofPhotos" class="block text-sm font-semibold text-slate-700 mb-2">Proof Photos <span class="text-red-500">*</span></label>
                    <input id="leadDetailMeetingProofPhotos" type="file" accept="image/*" multiple class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700" onchange="handleLeadDetailMeetingProofPhotosChange(event)">
                    <p class="mt-2 text-xs text-slate-500">Upload at least one meeting proof image. Allowed: JPG, PNG, WEBP.</p>
                    <div id="leadDetailMeetingProofPreview" class="mt-3 flex flex-wrap gap-3"></div>
                </div>
                <div>
                    <label for="leadDetailMeetingFeedback" class="block text-sm font-semibold text-slate-700 mb-2">Feedback</label>
                    <textarea id="leadDetailMeetingFeedback" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Summarize discussion, interest level, and next steps..."></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="leadDetailMeetingRating" class="block text-sm font-semibold text-slate-700 mb-2">Meeting Quality Rating</label>
                        <select id="leadDetailMeetingRating" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            <option value="">Select rating</option>
                            <option value="1">1 - Poor</option>
                            <option value="2">2 - Fair</option>
                            <option value="3">3 - Good</option>
                            <option value="4">4 - Very Good</option>
                            <option value="5">5 - Excellent</option>
                        </select>
                    </div>
                    <div>
                        <label for="leadDetailMeetingNotes" class="block text-sm font-semibold text-slate-700 mb-2">Notes</label>
                        <textarea id="leadDetailMeetingNotes" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Add internal notes or follow-up context..."></textarea>
                    </div>
                </div>
                <div>
                    <label for="leadDetailMeetingOutcome" class="block text-sm font-semibold text-slate-700 mb-2">Meeting Outcome <span class="text-red-500">*</span></label>
                    <select id="leadDetailMeetingOutcome" class="w-full rounded-2xl border border-emerald-400 px-4 py-3 text-base text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" onchange="toggleLeadDetailMeetingOutcomeFields()">
                        <option value="">Select next action</option>
                        <option value="interested">Interested</option>
                        <option value="schedule_visit">Schedule Visit</option>
                        <option value="schedule_follow_up">Schedule Follow Up</option>
                        <option value="not_interested">Not Interested</option>
                        <option value="junk">Junk</option>
                    </select>
                </div>
                <div id="leadDetailMeetingVisitFields" class="space-y-4 hidden">
                    <div class="rounded-[20px] border border-sky-100 bg-sky-50/60 p-4">
                        <h4 class="text-sm font-bold text-slate-900 mb-3">Schedule Visit</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="leadDetailMeetingVisitDate" class="block text-sm font-semibold text-slate-700 mb-2">Visit Date <span class="text-red-500">*</span></label>
                                <input id="leadDetailMeetingVisitDate" type="date" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="leadDetailMeetingVisitTime" class="block text-sm font-semibold text-slate-700 mb-2">Visit Time <span class="text-red-500">*</span></label>
                                <input id="leadDetailMeetingVisitTime" type="time" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div class="md:col-span-2">
                                <label for="leadDetailMeetingVisitProject" class="block text-sm font-semibold text-slate-700 mb-2">Project <span class="text-red-500">*</span></label>
                                <input id="leadDetailMeetingVisitProject" type="text" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Project to visit">
                            </div>
                            <div class="md:col-span-2">
                                <label for="leadDetailMeetingVisitLocation" class="block text-sm font-semibold text-slate-700 mb-2">Visit Location</label>
                                <input id="leadDetailMeetingVisitLocation" type="text" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Visit location or property address">
                            </div>
                            <div class="md:col-span-2">
                                <label for="leadDetailMeetingVisitRemark" class="block text-sm font-semibold text-slate-700 mb-2">Visit Notes</label>
                                <textarea id="leadDetailMeetingVisitRemark" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Notes for the scheduled visit"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="leadDetailMeetingFollowUpFields" class="space-y-4 hidden">
                    <div class="rounded-[20px] border border-blue-100 bg-blue-50/60 p-4">
                        <h4 class="text-sm font-bold text-slate-900 mb-3">Schedule Follow Up</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="leadDetailMeetingFollowUpDate" class="block text-sm font-semibold text-slate-700 mb-2">Follow Up Date <span class="text-red-500">*</span></label>
                                <input id="leadDetailMeetingFollowUpDate" type="date" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="leadDetailMeetingFollowUpTime" class="block text-sm font-semibold text-slate-700 mb-2">Follow Up Time <span class="text-red-500">*</span></label>
                                <input id="leadDetailMeetingFollowUpTime" type="time" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div class="md:col-span-2">
                                <label for="leadDetailMeetingFollowUpRemark" class="block text-sm font-semibold text-slate-700 mb-2">Remark</label>
                                <textarea id="leadDetailMeetingFollowUpRemark" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Follow-up context for the next call"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="leadDetailMeetingRemarkFields" class="hidden">
                    <div class="rounded-[20px] border border-amber-100 bg-amber-50/70 p-4">
                        <label for="leadDetailMeetingOutcomeRemark" class="block text-sm font-semibold text-slate-700 mb-2">Remark <span class="text-red-500">*</span></label>
                        <textarea id="leadDetailMeetingOutcomeRemark" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100" placeholder="Add outcome context..."></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeLeadDetailCompleteMeetingModal()" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="leadDetailRescheduleMeetingModal" class="modal">
    <div class="modal-content follow-up-action-hub-modal">
        <div class="modal-header follow-up-action-hub-header">
            <div>
                <h3>Reschedule Meeting</h3>
                <p class="follow-up-action-hub-subtitle">Choose a new meeting date and time before closing this task.</p>
            </div>
            <button class="close-modal" onclick="closeLeadDetailRescheduleMeetingModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="leadDetailRescheduleMeetingForm" class="space-y-4" onsubmit="submitLeadDetailRescheduleMeeting(event)">
                <div>
                    <label for="leadDetailRescheduleMeetingAt" class="block text-sm font-semibold text-slate-700 mb-2">New Scheduled Date &amp; Time <span class="text-red-500">*</span></label>
                    <input id="leadDetailRescheduleMeetingAt" type="datetime-local" required class="w-full rounded-2xl border border-emerald-400 px-4 py-3 text-base text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                </div>
                <div>
                    <label for="leadDetailRescheduleMeetingReason" class="block text-sm font-semibold text-slate-700 mb-2">Reason <span class="text-red-500">*</span></label>
                    <textarea id="leadDetailRescheduleMeetingReason" rows="4" required class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Enter reason for rescheduling..."></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeLeadDetailRescheduleMeetingModal()" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white">Reschedule Meeting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="leadDetailCompleteVisitModal" class="modal">
    <div class="modal-content follow-up-action-hub-modal">
        <div class="modal-header follow-up-action-hub-header">
            <div>
                <h3 id="leadDetailVisitModalTitle">Complete Visit</h3>
                <p id="leadDetailVisitModalSubtitle" class="follow-up-action-hub-subtitle">Upload visit proof and complete this site visit.</p>
            </div>
            <button class="close-modal" onclick="closeLeadDetailCompleteVisitModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="leadDetailCompleteVisitForm" class="space-y-4" onsubmit="submitLeadDetailCompleteVisit(event)">
                <input type="hidden" id="leadDetailVisitOutcome" value="visited">
                <div id="leadDetailVisitProofFields">
                    <label for="leadDetailVisitProofPhotos" class="block text-sm font-semibold text-slate-700 mb-2">Proof Photos <span class="text-red-500">*</span></label>
                    <input id="leadDetailVisitProofPhotos" type="file" accept="image/*" multiple class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-700" onchange="handleLeadDetailVisitProofPhotosChange(event)">
                    <p class="mt-2 text-xs text-slate-500">Upload at least one site-visit proof image. Allowed: JPG, PNG, WEBP.</p>
                    <div id="leadDetailVisitProofPreview" class="mt-3 flex flex-wrap gap-3"></div>
                </div>
                <div id="leadDetailVisitCompletionFields" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="leadDetailVisitFeedback" class="block text-sm font-semibold text-slate-700 mb-2">Feedback</label>
                        <textarea id="leadDetailVisitFeedback" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Summarize customer response and visit outcome..."></textarea>
                    </div>
                    <div>
                        <label for="leadDetailVisitNotes" class="block text-sm font-semibold text-slate-700 mb-2">Notes</label>
                        <textarea id="leadDetailVisitNotes" rows="4" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Add internal notes or closure context..."></textarea>
                    </div>
                    <div>
                        <label for="leadDetailVisitRating" class="block text-sm font-semibold text-slate-700 mb-2">Rating</label>
                        <select id="leadDetailVisitRating" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            <option value="">Select rating</option>
                            <option value="1">1 - Poor</option>
                            <option value="2">2 - Fair</option>
                            <option value="3">3 - Good</option>
                            <option value="4">4 - Very Good</option>
                            <option value="5">5 - Excellent</option>
                        </select>
                    </div>
                    <div>
                        <label for="leadDetailVisitVisitedProjects" class="block text-sm font-semibold text-slate-700 mb-2">Visited Projects</label>
                        <input id="leadDetailVisitVisitedProjects" type="text" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Example: Oro Constella, Jash Elevate">
                        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                            <input id="leadDetailVisitAddOnProject" type="text" list="leadDetailVisitProjectSuggestions" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Add another project" onkeydown="if (event.key === 'Enter') { event.preventDefault(); addLeadDetailVisitedProject(); }">
                            <button type="button" onclick="addLeadDetailVisitedProject()" class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">
                                <i class="fas fa-plus text-xs"></i>
                                <span>Add</span>
                            </button>
                        </div>
                        <datalist id="leadDetailVisitProjectSuggestions">
                            @foreach($uniqueProjectNames ?? [] as $projectName)
                                <option value="{{ $projectName }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Property Type</label>
                        <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-3">
                            <label class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-sm font-semibold text-emerald-900">
                                <input type="checkbox" class="leadDetailVisitPropertyType rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" value="plot"> Plot
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-sm font-semibold text-emerald-900">
                                <input type="checkbox" class="leadDetailVisitPropertyType rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" value="villa"> Villa
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-sm font-semibold text-emerald-900">
                                <input type="checkbox" class="leadDetailVisitPropertyType rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" value="apartment"> Apartments
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-sm font-semibold text-emerald-900">
                                <input type="checkbox" class="leadDetailVisitPropertyType rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" value="commercial"> Commercial
                            </label>
                            <label class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50/60 px-3 py-2 text-sm font-semibold text-emerald-900">
                                <input type="checkbox" class="leadDetailVisitPropertyType rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" value="other"> Other
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Multiple property types select kar sakte hain.</p>
                    </div>
                    <div>
                        <label for="leadDetailVisitTentativeClosingTime" class="block text-sm font-semibold text-slate-700 mb-2">Tentative Closing Time</label>
                        <select id="leadDetailVisitTentativeClosingTime" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            <option value="">Select an option</option>
                            <option value="within_3_days">Within 3 Days</option>
                            <option value="tomorrow">Tomorrow</option>
                            <option value="this_week">This Week</option>
                            <option value="this_month">This Month</option>
                            <option value="it_will_take_time">It Will Take Time</option>
                        </select>
                    </div>
                </div>
                <div id="leadDetailVisitRescheduleFields" class="space-y-4 hidden">
                    <div class="rounded-[20px] border border-emerald-100 bg-emerald-50/60 p-4">
                        <h4 class="text-sm font-bold text-slate-900 mb-3">Reschedule Visit</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="leadDetailVisitRescheduleDate" class="block text-sm font-semibold text-slate-700 mb-2">Visit Date <span class="text-red-500">*</span></label>
                                <input id="leadDetailVisitRescheduleDate" type="date" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="leadDetailVisitRescheduleTime" class="block text-sm font-semibold text-slate-700 mb-2">Visit Time <span class="text-red-500">*</span></label>
                                <input id="leadDetailVisitRescheduleTime" type="time" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div class="md:col-span-2">
                                <label for="leadDetailVisitRescheduleRemark" class="block text-sm font-semibold text-slate-700 mb-2">Reason <span class="text-red-500">*</span></label>
                                <textarea id="leadDetailVisitRescheduleRemark" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Reason for rescheduling the visit"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="leadDetailVisitFollowUpFields" class="space-y-4 hidden">
                    <div class="rounded-[20px] border border-blue-100 bg-blue-50/60 p-4">
                        <h4 class="text-sm font-bold text-slate-900 mb-3">Follow-up Needed</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="leadDetailVisitFollowUpDate" class="block text-sm font-semibold text-slate-700 mb-2">Follow Up Date <span class="text-red-500">*</span></label>
                                <input id="leadDetailVisitFollowUpDate" type="date" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div>
                                <label for="leadDetailVisitFollowUpTime" class="block text-sm font-semibold text-slate-700 mb-2">Follow Up Time <span class="text-red-500">*</span></label>
                                <input id="leadDetailVisitFollowUpTime" type="time" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                            </div>
                            <div class="md:col-span-2">
                                <label for="leadDetailVisitFollowUpRemark" class="block text-sm font-semibold text-slate-700 mb-2">Remark</label>
                                <textarea id="leadDetailVisitFollowUpRemark" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" placeholder="Follow-up context for the next call"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="leadDetailVisitRemarkFields" class="hidden">
                    <div class="rounded-[20px] border border-amber-100 bg-amber-50/70 p-4">
                        <label for="leadDetailVisitOutcomeRemark" class="block text-sm font-semibold text-slate-700 mb-2">Remark <span class="text-red-500">*</span></label>
                        <textarea id="leadDetailVisitOutcomeRemark" rows="3" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100" placeholder="Add outcome context..."></textarea>
                    </div>
                </div>
                <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" onclick="backToVisitActionHub()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50">
                        <i class="fas fa-arrow-left text-sm"></i>
                        <span>Back</span>
                    </button>
                    <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <button type="button" onclick="closeLeadDetailCompleteVisitModal()" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50">Cancel</button>
                        <button id="leadDetailVisitSubmitButton" type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white">Complete Visit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($user && ($user->isAdmin() || $user->isCrm()))
<div id="oldTaskTransferModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-[520px] shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">Transfer Task</h3>
                    <p id="oldTaskTransferSummary" class="mt-1 text-sm text-gray-500"></p>
                </div>
                <button type="button" onclick="closeOldTaskTransferModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="oldTaskTransferForm" onsubmit="submitOldTaskTransfer(event)">
                <input type="hidden" id="oldTaskTransferModelType" name="model_type">
                <input type="hidden" id="oldTaskTransferTaskId" name="task_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transfer To *</label>
                        <select id="oldTaskTransferAssignedTo" name="assigned_to" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select user</option>
                            @foreach(($ownerTransferUsers ?? collect()) as $transferUser)
                                <option value="{{ $transferUser->id }}">{{ $transferUser->name }} ({{ $transferUser->role->name ?? 'User' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                        <textarea id="oldTaskTransferNotes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Reason for task transfer"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeOldTaskTransferModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="oldTaskTransferSubmitBtn" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-all duration-200">Transfer Task</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($user && ($user->isAdmin() || $user->isCrm() || $user->isAssistantSalesManager() || $user->isSeniorManager() || $user->isSalesManager()))
@php
    $currentOwnerId = optional($lead->activeAssignments->first())->assigned_to;
@endphp
<div id="ownerTransferModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Change Lead Owner</h3>
                <button onclick="closeOwnerTransferModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="ownerTransferForm" onsubmit="submitOwnerTransfer(event)">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Owner *</label>
                        <select id="ownerTransferAssignedTo" name="assigned_to" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="">Select user</option>
                            @foreach(($ownerTransferUsers ?? collect()) as $transferUser)
                                <option value="{{ $transferUser->id }}" {{ (int) $currentOwnerId === (int) $transferUser->id ? 'selected' : '' }}>
                                    {{ $transferUser->name }} ({{ $transferUser->role->name ?? 'User' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" id="ownerTransferCreateCallingTask" name="create_calling_task" checked class="mt-1">
                            <span class="text-sm text-gray-700">Create calling task for new owner</span>
                        </label>
                    </div>
                    <input type="hidden" id="ownerTransferExistingTasks" name="transfer_existing_tasks" value="1">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transfer Reason *</label>
                        <textarea id="ownerTransferNotes" name="notes" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="Why is this lead being transferred?"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeOwnerTransferModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="ownerTransferSubmitBtn" class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d] transition-all duration-200">Transfer Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Meeting Modal -->
<div id="meetingModal" class="fixed inset-0 bg-black/40 hidden overflow-y-auto h-full w-full z-50">
    <div class="min-h-full flex items-start sm:items-center justify-center p-4">
        <div class="w-full max-w-6xl max-h-[calc(100dvh-2rem)] rounded-[28px] bg-white shadow-2xl overflow-hidden border border-slate-200 flex flex-col">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-200">
                <h3 class="text-xl font-bold text-slate-900">Meeting</h3>
                <button onclick="closeMeetingModal()" class="text-slate-400 hover:text-slate-600 text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="meetingForm" onsubmit="submitMeeting(event)" class="p-6 md:p-8 overflow-y-auto overscroll-contain min-h-0">
                <div class="rounded-[24px] border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-5 md:p-7">
                    <div class="mb-5">
                        <h4 class="text-xl font-bold text-emerald-800">Meeting Planning</h4>
                        <p class="mt-1 text-sm text-slate-600">Meeting select karte hi yahin se type, date, time aur mode set karo.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ $meetingTypeField['label'] }} @if($meetingTypeField['required'])<span class="text-red-500">*</span>@endif</label>
                            <select id="meeting_type" name="meeting_type" @if($meetingTypeField['required']) required @endif class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                                <option value="">Select meeting type</option>
                                @foreach($meetingTypeField['options'] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" id="meeting_sequence" name="meeting_sequence" value="1">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ $meetingDateField['label'] }} @if($meetingDateField['required'])<span class="text-red-500">*</span>@endif</label>
                            <input type="date" name="meeting_date" id="meeting_date" @if($meetingDateField['required']) required @endif class="w-full rounded-2xl border border-emerald-400 px-4 py-3 text-base text-slate-900 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ $meetingTimeField['label'] }} @if($meetingTimeField['required'])<span class="text-red-500">*</span>@endif</label>
                            <input type="time" name="meeting_time" id="meeting_time" @if($meetingTimeField['required']) required @endif class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ $meetingModeField['label'] }} @if($meetingModeField['required'])<span class="text-red-500">*</span>@endif</label>
                            <select name="meeting_mode" id="meeting_mode" @if($meetingModeField['required']) required @endif class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100" onchange="toggleMeetingModeFields()">
                                @foreach($meetingModeField['options'] as $option)
                                    @php
                                        $optionValue = strtolower($option) === 'offline' ? 'offline' : 'online';
                                    @endphp
                                    <option value="{{ $optionValue }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="meetingLinkField" class="md:col-span-2 hidden">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ $meetingLinkField['label'] }}</label>
                            <input type="url" name="meeting_link" placeholder="{{ $meetingLinkField['placeholder'] }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        </div>
                        <div id="meetingLocationField" class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ $meetingLocationField['label'] }}</label>
                            <input type="text" name="location" id="location_input" placeholder="{{ $meetingLocationField['placeholder'] }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ $meetingNotesField['label'] }}</label>
                            <textarea name="meeting_notes" rows="4" placeholder="{{ $meetingNotesField['placeholder'] }}" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-base text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 resize-none"></textarea>
                        </div>
                    </div>
                    <label class="mt-4 inline-flex items-center gap-3 text-sm font-semibold text-slate-900">
                        <input type="checkbox" name="reminder_enabled" checked class="h-4 w-4 rounded border-slate-300 text-red-500 focus:ring-red-400">
                        <span>{{ $meetingReminderField['label'] }}</span>
                    </label>
                </div>
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeMeetingModal()" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white">Save Meeting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include Meeting Post-Call Popup Component -->
@include('components.meeting-post-call-popup')


{{-- Lead Requirements Modal --}}
<div id="leadRequirementsModal" class="lead-requirements-overlay" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;background:rgba(0,0,0,0.6)">
    <div class="lead-requirements-modal">
        <div class="lead-requirements-modal-head">
            <h2 id="leadReqModalTitle">Lead Detail Form</h2>
            <button onclick="closeLeadRequirementsModal()" class="lead-requirements-close-btn" aria-label="Close lead detail form">&times;</button>
        </div>
        <div class="lead-requirements-modal-body">
            <div id="leadDetailFormContainer" style="overflow-y:auto;flex:1">
                <div id="leadReqLoading" style="text-align:center;padding:40px;color:#666">Loading...</div>
            </div>
        </div>
    </div>
</div>

<script>
let _lrLeadId = null;
window.managerLeadMeetingCreateUrl = '{{ route("sales-manager.meetings.create") }}';
window.managerLeadSiteVisitCreateUrl = '{{ route("sales-manager.site-visits.create") }}';
window.leadDetailRequirementsFormConfig = @json($leadDetailRequirementsFormConfig);
window.leadDetailOutputFormConfig = @json($leadDetailOutputFormConfig);
const LEAD_DETAIL_API_TOKEN = document.querySelector('meta[name="api-token"]')?.content || @json(session('api_token') ?? '');

function getLeadRequirementHeaders(includeJson = false) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const apiToken = typeof window.getManagerApiToken === 'function' ? window.getManagerApiToken() : LEAD_DETAIL_API_TOKEN;
    const headers = {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
    };

    if (includeJson) {
        headers['Content-Type'] = 'application/json';
    }

    if (apiToken) {
        headers['Authorization'] = `Bearer ${apiToken}`;
    }

    return headers;
}

function updateLeadDetailFavoriteButtons(isFavorite) {
    document.querySelectorAll('[data-lead-favorite-btn]').forEach((button) => {
        button.dataset.favoriteState = isFavorite ? '1' : '0';
        button.classList.toggle('is-favorite', isFavorite);
        button.setAttribute('aria-label', isFavorite ? 'Remove from favorite leads' : 'Add to favorite leads');
        button.setAttribute('title', isFavorite ? 'Remove from favorites' : 'Add to favorites');

        const icon = button.querySelector('i');
        if (icon) {
            icon.className = `${isFavorite ? 'fas' : 'far'} fa-heart`;
        }
    });
}

async function toggleLeadDetailFavorite(leadId) {
    const buttons = document.querySelectorAll('[data-lead-favorite-btn]');
    const firstButton = buttons[0];
    const isFavorite = firstButton?.dataset.favoriteState === '1';
    const method = isFavorite ? 'DELETE' : 'POST';

    buttons.forEach((button) => button.classList.add('is-saving'));

    try {
        const response = await fetch(`/api/sales-manager/leads/${leadId}/favorite`, {
            method,
            headers: getLeadRequirementHeaders(),
            credentials: 'same-origin',
        });

        const data = await response.json();
        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Failed to update favorite lead');
        }

        updateLeadDetailFavoriteButtons(Boolean(data.is_favorite));
    } catch (error) {
        console.error('Lead favorite update failed:', error);
        alert(error.message || 'Failed to update favorite lead.');
    } finally {
        buttons.forEach((button) => button.classList.remove('is-saving'));
    }
}

window.openLeadRequirementsModal = async function(leadId) {
    _lrLeadId = leadId;
    const modal = document.getElementById('leadRequirementsModal');
    const container = document.getElementById('leadDetailFormContainer');
    modal.style.display = 'flex';
    if (modal) {
        modal.dataset.formContext = 'lead';
    }
    if (container) {
        container.dataset.formContext = 'lead';
        container.innerHTML = '<div style="text-align:center;padding:40px;color:#666">Loading...</div>';
    }

    try {
        const res = await fetch('/api/leads/' + leadId + '/requirement-form', {
            headers: getLeadRequirementHeaders()
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Failed to load');

        // Use same renderManagerLeadForm from tasks.blade.php (loaded via layout)
        if (typeof renderManagerLeadForm === 'function') {
            window.currentTaskId = null;
            renderManagerLeadForm(data);
        } else {
            document.getElementById('leadDetailFormContainer').innerHTML = '<p style="color:red">Form renderer not available. Please use the tasks page.</p>';
        }
    } catch(e) {
        document.getElementById('leadDetailFormContainer').innerHTML = '<p style="color:red">' + e.message + '</p>';
    }
};

window.closeLeadRequirementsModal = function() {
    const modal = document.getElementById('leadRequirementsModal');
    const container = document.getElementById('leadDetailFormContainer');
    if (modal) {
        modal.style.display = 'none';
        delete modal.dataset.formContext;
    }
    if (container) {
        delete container.dataset.formContext;
        container.innerHTML = '';
    }
    window.managerActiveLeadRequirementsFormConfig = null;
    window.managerActiveLeadOutputFormConfig = null;
    _lrLeadId = null;
};

window.submitLeadRequirementsFromShow = async function() {
    if (!_lrLeadId) return;
    const btn = document.getElementById('leadReqSubmitBtn');
    const defaultButtonText = btn ? btn.textContent : 'Save requirements';
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving...';
    }

    const payload = window.buildLeadRequirementsPayload
        ? window.buildLeadRequirementsPayload()
        : null;
    const validationError = !payload || !payload.customer_name || !payload.phone
        ? 'Please complete the lead requirements form'
        : '';

    if (validationError) {
        if (window.showAlert) {
            window.showAlert(validationError, 'warning');
        } else {
            alert(validationError);
        }
        if (btn) {
            btn.disabled = false;
            btn.textContent = defaultButtonText;
        }
        return;
    }

    try {
        const res = await fetch('/api/leads/' + _lrLeadId + '/update-requirements', {
            method: 'POST',
            headers: getLeadRequirementHeaders(true),
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Save failed');

        closeLeadRequirementsModal();
        if (window.showAlert) {
            window.showAlert('Requirements saved successfully!', 'success', 3000);
        } else {
            const t = document.createElement('div');
            t.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#205A44;color:white;padding:12px 20px;border-radius:8px;z-index:99999;font-size:14px';
            t.textContent = 'Requirements saved successfully!';
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 3000);
        }
        setTimeout(() => {
            window.location.reload();
        }, 350);
    } catch(e) {
        document.getElementById('leadDetailFormContainer').insertAdjacentHTML('afterbegin',
            '<p style="color:red;margin-bottom:12px">' + e.message + '</p>');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = defaultButtonText;
        }
    }
};

document.getElementById('leadRequirementsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeLeadRequirementsModal();
    }
});
</script>

<script src="{{ asset('js/manager-lead-form.js') }}?v={{ @filemtime(public_path('js/manager-lead-form.js')) ?: time() }}"></script>
<div id="leadWhatsAppSheet"
     class="fixed inset-0 z-[120] hidden items-end justify-center bg-slate-950/45 px-4 pb-safe"
     aria-hidden="true">
    <div class="absolute inset-0" onclick="closeLeadWhatsAppSheet()"></div>
    <div class="relative w-full max-w-md rounded-t-3xl bg-white p-5 shadow-2xl sm:rounded-3xl sm:mb-6">
        <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-slate-200 sm:hidden"></div>
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-900">Open WhatsApp</p>
                <p class="mt-1 text-xs text-slate-500">Choose where to open this chat on your phone.</p>
            </div>
            <button type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-500"
                    onclick="closeLeadWhatsAppSheet()"
                    aria-label="Close WhatsApp options">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        <div class="mt-5 space-y-3">
            <button type="button"
                    id="leadWhatsAppOpenDefault"
                    class="flex w-full items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left transition hover:border-emerald-300 hover:bg-emerald-50"
                    onclick="openLeadWhatsAppDestination('default')">
                <span class="flex items-center gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
                        <i class="fab fa-whatsapp text-lg"></i>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">Open in WhatsApp</span>
                        <span class="block text-xs text-slate-500">Uses your phone's default WhatsApp app.</span>
                    </span>
                </span>
                <i class="fas fa-chevron-right text-xs text-slate-400"></i>
            </button>
            <button type="button"
                    id="leadWhatsAppOpenBusiness"
                    class="flex w-full items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left transition hover:border-emerald-300 hover:bg-emerald-50"
                    onclick="openLeadWhatsAppDestination('business')">
                <span class="flex items-center gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-teal-100 text-teal-700">
                        <i class="fas fa-briefcase text-base"></i>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">Open in WhatsApp Business</span>
                        <span class="block text-xs text-slate-500">Tries Business first, then falls back to WhatsApp.</span>
                    </span>
                </span>
                <i class="fas fa-chevron-right text-xs text-slate-400"></i>
            </button>
            <button type="button"
                    id="leadWhatsAppCopyNumber"
                    class="flex w-full items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-left transition hover:border-slate-300 hover:bg-slate-50"
                    onclick="copyLeadWhatsAppNumber()">
                <span class="flex items-center gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                        <i class="fas fa-copy text-base"></i>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">Copy Number</span>
                        <span class="block text-xs text-slate-500" id="leadWhatsAppCopyLabel">{{ $leadWhatsAppPhone }}</span>
                    </span>
                </span>
                <i class="fas fa-chevron-right text-xs text-slate-400"></i>
            </button>
        </div>
    </div>
</div>
@include('partials.lead-cloud-call-menu')
@push('scripts')
<script>
    (function () {
        function formatPlayerTime(totalSeconds) {
            const seconds = Math.max(0, Math.floor(Number(totalSeconds) || 0));
            const minutes = Math.floor(seconds / 60);
            const remainder = seconds % 60;
            return `${minutes}:${String(remainder).padStart(2, '0')}`;
        }

        function initRecordingPlayers() {
            document.querySelectorAll('.crm-recording-player').forEach((player) => {
                if (player.dataset.ready === '1') {
                    return;
                }

                const audio = player.querySelector('.crm-recording-audio');
                const playBtn = player.querySelector('.crm-recording-play');
                const playIcon = playBtn?.querySelector('i');
                const currentTimeEl = player.querySelector('.crm-recording-current');
                const totalTimeEl = player.querySelector('.crm-recording-total');
                const progress = player.querySelector('.crm-recording-progress');
                const rateButtons = player.querySelectorAll('.crm-rate-btn');
                const expectedDuration = Number(player.dataset.expectedDuration || 0);

                if (!audio || !playBtn || !currentTimeEl || !totalTimeEl || !progress) {
                    return;
                }

                const getDisplayDuration = () => {
                    const mediaDuration = Number.isFinite(audio.duration) ? audio.duration : 0;
                    return Math.max(mediaDuration, expectedDuration);
                };

                const syncUi = () => {
                    const displayDuration = getDisplayDuration();
                    currentTimeEl.textContent = formatPlayerTime(audio.currentTime);
                    totalTimeEl.textContent = formatPlayerTime(displayDuration);

                    if (displayDuration > 0) {
                        progress.value = String(Math.min(1000, Math.round((audio.currentTime / displayDuration) * 1000)));
                        progress.disabled = false;
                    } else {
                        progress.value = '0';
                        progress.disabled = true;
                    }
                };

                playBtn.addEventListener('click', () => {
                    if (audio.paused) {
                        audio.play().catch(() => {});
                    } else {
                        audio.pause();
                    }
                });

                audio.addEventListener('play', () => {
                    playIcon?.classList.remove('fa-play');
                    playIcon?.classList.add('fa-pause');
                });

                audio.addEventListener('pause', () => {
                    playIcon?.classList.remove('fa-pause');
                    playIcon?.classList.add('fa-play');
                });

                audio.addEventListener('loadedmetadata', syncUi);
                audio.addEventListener('durationchange', syncUi);
                audio.addEventListener('timeupdate', syncUi);
                audio.addEventListener('ended', () => {
                    audio.currentTime = 0;
                    syncUi();
                });

                progress.addEventListener('input', () => {
                    const displayDuration = getDisplayDuration();
                    if (displayDuration > 0) {
                        audio.currentTime = (Number(progress.value) / 1000) * displayDuration;
                    }
                });

                rateButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        const rate = Number(button.dataset.rate || 1);
                        audio.playbackRate = rate;
                        rateButtons.forEach((candidate) => candidate.classList.remove('is-active'));
                        button.classList.add('is-active');
                    });
                });

                syncUi();
                player.dataset.ready = '1';
            });
        }

        document.addEventListener('DOMContentLoaded', initRecordingPlayers);
        initRecordingPlayers();
    })();
</script>
@endpush
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/manager-lead-form.css') }}?v={{ @filemtime(public_path('css/manager-lead-form.css')) ?: time() }}">
<style>
    .lead-favorite-btn {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.22);
        background: rgba(255, 255, 255, 0.10);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 10px 22px rgba(0, 0, 0, 0.12);
    }
    .lead-favorite-btn:hover {
        background: rgba(255, 255, 255, 0.20);
        transform: translateY(-1px);
    }
    .lead-favorite-btn.is-favorite {
        background: #fff;
        border-color: #fff;
        color: #e11d48;
    }
    .lead-favorite-btn.is-saving {
        opacity: 0.65;
        pointer-events: none;
    }
    .asm-m-topbar-actions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .asm-m-topbar .lead-favorite-btn {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        border-color: rgba(15, 23, 42, 0.08);
        background: #fff;
        color: #64748b;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    }
    .asm-m-topbar .lead-favorite-btn.is-favorite {
        color: #e11d48;
        background: #fff1f2;
        border-color: #fecdd3;
    }
    .crm-recording-shell {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .crm-recording-toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .crm-recording-play {
        width: 38px;
        height: 38px;
        border-radius: 999px;
        border: none;
        background: #0f766e;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 6px 14px rgba(15, 118, 110, 0.18);
    }
    .crm-recording-time {
        font-size: 13px;
        font-weight: 600;
        color: #0f172a;
        min-width: 88px;
    }
    .crm-recording-rates {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: auto;
    }
    .crm-rate-btn {
        border: 1px solid #99f6e4;
        background: #fff;
        color: #115e59;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }
    .crm-rate-btn.is-active {
        background: #115e59;
        color: #fff;
        border-color: #115e59;
    }
    .crm-recording-progress {
        width: 100%;
        accent-color: #111827;
        cursor: pointer;
    }
    .crm-recording-progress[disabled] {
        cursor: not-allowed;
        opacity: 0.7;
    }
    @if($embedTaskFlowOnly)
    html, body {
        height: auto !important;
        min-height: 100%;
        overflow: auto !important;
        background: transparent !important;
    }

    #sidebar,
    #mobileFooterNav,
    .sidebar-overlay,
    .header,
    .container > .header,
    .lead-detail-container,
    #completeAsmTaskModal {
        display: none !important;
    }

    #mainContent,
    body #mainContent,
    html body #mainContent,
    div#mainContent,
    .container {
        margin: 0 !important;
        padding: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
        min-height: auto !important;
        background: transparent !important;
    }
    @endif

    #leadRequirementsModal .lead-requirements-modal {
        width: min(1100px, calc(100vw - 24px));
        max-height: calc(100vh - 24px);
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 24px 64px rgba(15, 23, 42, 0.35);
        border: 1px solid rgba(15, 23, 42, 0.14);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    #leadRequirementsModal .lead-requirements-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        border-bottom: 1px solid rgba(255, 255, 255, 0.18);
    }
    #leadRequirementsModal .lead-requirements-modal-head h2 {
        color: #ffffff;
        font-size: 1.85rem;
        line-height: 1.1;
        font-weight: 700;
        margin: 0;
    }
    #leadRequirementsModal .lead-requirements-close-btn {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.28);
        background: rgba(255, 255, 255, 0.14);
        color: #ffffff;
        font-size: 28px;
        line-height: 1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #leadRequirementsModal .lead-requirements-modal-body {
        flex: 1 1 auto;
        overflow: hidden;
    }
    #leadRequirementsModal #leadDetailFormContainer {
        height: 100%;
        overflow-y: auto !important;
        padding: 14px;
        background: #f8faf9;
    }

    .timeline-item {
        position: relative;
    }

    .timeline-automation-info {
        position: relative;
        display: inline-block;
        margin: 0;
    }

    .timeline-automation-summary {
        list-style: none;
        width: 18px;
        height: 18px;
        border-radius: 9999px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        line-height: 1;
        user-select: none;
    }

    .timeline-automation-summary::-webkit-details-marker {
        display: none;
    }

    .timeline-automation-popover {
        position: absolute;
        top: calc(100% + 6px);
        left: 50%;
        transform: translateX(-50%);
        min-width: 220px;
        max-width: 280px;
        padding: 8px 10px;
        border-radius: 10px;
        border: 1px solid #dbeafe;
        background: #ffffff;
        color: #0f172a;
        font-size: 12px;
        line-height: 1.4;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        z-index: 30;
        white-space: normal;
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e5e7eb;
    }

    .asm-mobile-lead-shell {
        display: none;
    }

    /* ── Top Navigation Bar ── */
    .asm-m-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 4px;
        margin-bottom: 2px;
    }
    .asm-m-topbar-btn {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        border: none;
        background: #f1f5f9;
        color: #1e293b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        transition: background 0.15s;
        cursor: pointer;
        text-decoration: none;
    }
    .asm-m-topbar-btn:active { background: #e2e8f0; }
    .asm-m-topbar-title {
        font-size: 0.9rem;
        font-weight: 700;
        color: #334155;
        letter-spacing: -0.01em;
    }

    /* ── Profile Card ── */
    .asm-m-profile-card {
        background: #ffffff;
        border: 1px solid #e8eeed;
        border-radius: 20px;
        padding: 20px 18px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 24px rgba(6,58,28,0.04);
    }
    .asm-m-profile-row {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .asm-m-avatar {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        background: linear-gradient(135deg, #063A1C 0%, #0f6b3a 100%);
        color: #fff;
        font-size: 1.35rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        letter-spacing: -0.02em;
    }
    .asm-m-profile-info {
        min-width: 0;
        flex: 1;
    }
    .asm-m-lead-name {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .asm-m-lead-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 5px;
        font-size: 0.8rem;
        color: #64748b;
    }
    .asm-m-phone-link {
        color: #063A1C;
        font-weight: 600;
        text-decoration: none;
    }
    .asm-m-phone-link i { font-size: 0.7rem; margin-right: 2px; }
    .asm-m-dot-sep { color: #cbd5e1; font-size: 0.6rem; }
    .asm-m-assignee { color: #94a3b8; }
    .asm-m-assignee i { font-size: 0.65rem; margin-right: 2px; }

    /* ── Badges ── */
    .asm-m-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 14px;
    }
    .asm-m-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: capitalize;
        line-height: 1.4;
    }
    .asm-m-badge-outline {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .asm-m-badge-amber {
        background: #fffbeb;
        color: #b45309;
        border: 1px solid #fde68a;
    }

    /* ── Next Action Strip ── */
    .asm-m-next-action {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 16px;
        padding: 12px 14px;
        background: #f0fdf4;
        border: 1px solid #dcfce7;
        border-radius: 14px;
    }
    .asm-m-next-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #063A1C;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .asm-m-next-body {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }
    .asm-m-next-label {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
    }
    .asm-m-next-title {
        font-size: 0.88rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.3;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .asm-m-next-time {
        font-size: 0.72rem;
        font-weight: 600;
        color: #047857;
        white-space: nowrap;
        flex-shrink: 0;
    }

    /* ── Quick Actions Card ── */
    .asm-m-actions-card {
        margin-top: 12px;
        background: #ffffff;
        border: 1px solid #e8eeed;
        border-radius: 20px;
        padding: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 24px rgba(6,58,28,0.04);
    }
    .asm-m-actions-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
    }
    .asm-m-action {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        padding: 14px 6px 12px;
        border-radius: 16px;
        background: #f8faf9;
        border: 1px solid #e8eeed;
        text-decoration: none;
        cursor: pointer;
        transition: background 0.15s, transform 0.1s;
        -webkit-tap-highlight-color: transparent;
    }
    .asm-m-action:active { background: #eef3f0; transform: scale(0.97); }
    .asm-m-action-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .asm-m-action-icon-green  { background: #dcfce7; color: #15803d; }
    .asm-m-action-icon-teal   { background: #ccfbf1; color: #0d9488; }
    .asm-m-action-icon-blue   { background: #dbeafe; color: #2563eb; }
    .asm-m-action-icon-purple { background: #ede9fe; color: #7c3aed; }
    .asm-m-action-icon-orange { background: #ffedd5; color: #ea580c; }
    .asm-m-action-icon-slate  { background: #e2e8f0; color: #475569; }
    .asm-m-action-icon-indigo { background: #e0e7ff; color: #4f46e5; }
    .asm-m-action-label {
        font-size: 0.73rem;
        font-weight: 700;
        color: #334155;
        text-align: center;
        line-height: 1.2;
    }
    .asm-m-req-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        margin-top: 10px;
        padding: 14px;
        border-radius: 14px;
        border: none;
        background: linear-gradient(135deg, #063A1C 0%, #0d5c30 100%);
        color: #fff;
        font-size: 0.88rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(6,58,28,0.18);
        transition: opacity 0.15s;
    }
    .asm-m-req-btn:active { opacity: 0.88; }
    .asm-m-req-btn i { font-size: 0.9rem; }

    /* ── Tab Card ── */
    .asm-m-tab-card {
        margin-top: 12px;
        background: #ffffff;
        border: 1px solid #e8eeed;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 8px 24px rgba(6,58,28,0.04);
    }
    .asm-m-tab-bar {
        display: flex;
        gap: 0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        border-bottom: 1px solid #f1f5f0;
        background: #fafcfb;
    }
    .asm-m-tab-bar::-webkit-scrollbar { display: none; }

    .asm-mobile-tab-btn {
        flex: 1;
        min-width: max-content;
        border: none;
        background: transparent;
        color: #94a3b8;
        padding: 14px 14px 12px;
        font-size: 0.78rem;
        font-weight: 700;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        cursor: pointer;
        position: relative;
        transition: color 0.2s;
    }
    .asm-mobile-tab-btn i { font-size: 0.72rem; }
    .asm-mobile-tab-btn::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 12px;
        right: 12px;
        height: 2.5px;
        border-radius: 2px 2px 0 0;
        background: transparent;
        transition: background 0.2s;
    }
    .asm-mobile-tab-btn.is-active {
        color: #063A1C;
    }
    .asm-mobile-tab-btn.is-active::after {
        background: #063A1C;
    }
    .asm-m-tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 6px;
        font-size: 0.68rem;
        font-weight: 800;
        background: #f1f5f9;
        color: #64748b;
        line-height: 1;
    }
    .asm-mobile-tab-btn.is-active .asm-m-tab-count {
        background: #063A1C;
        color: #fff;
    }

    .asm-mobile-tab-panel {
        display: none;
        padding: 16px;
    }
    .asm-mobile-tab-panel.is-active {
        display: block;
    }

    /* ── Timeline ── */
    .asm-m-tl-item {
        display: grid;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: 12px;
        position: relative;
        padding-bottom: 18px;
    }
    .asm-m-tl-item:not(:last-child)::after {
        content: '';
        position: absolute;
        left: 19px;
        top: 42px;
        bottom: 0;
        width: 2px;
        background: #e5e7eb;
        border-radius: 1px;
    }
    .asm-m-tl-dot {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        position: relative;
        z-index: 1;
        border: 1px solid transparent;
    }
    .asm-m-tl-body {
        padding-top: 2px;
    }
    .asm-m-tl-time {
        display: block;
        font-size: 0.7rem;
        color: #94a3b8;
        font-weight: 600;
        margin-bottom: 2px;
    }
    .asm-m-tl-title {
        margin: 0;
        font-size: 0.88rem;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.35;
    }
    .asm-m-tl-desc {
        margin: 3px 0 0;
        font-size: 0.8rem;
        color: #64748b;
        line-height: 1.5;
    }
    .asm-m-tl-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }
    .asm-m-meta-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 0.7rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .asm-m-meta-chip-status { background: #dbeafe; color: #1d4ed8; }
    .asm-m-meta-chip-duration { background: #dcfce7; color: #166534; }
    .asm-m-meta-chip-score { background: #f3e8ff; color: #7c3aed; }
    .asm-m-tl-remark {
        margin-top: 8px;
        font-size: 0.78rem;
        color: #64748b;
        line-height: 1.5;
    }

    /* ── Task Items ── */
    .asm-m-task-item {
        background: #f8faf9;
        border: 1px solid #e5ebe8;
        border-radius: 16px;
        padding: 16px;
    }
    .asm-m-task-item + .asm-m-task-item {
        margin-top: 10px;
    }
    .asm-m-task-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 8px;
    }
    .asm-m-task-type {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.7rem;
        font-weight: 700;
        color: #047857;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        padding: 3px 10px;
        border-radius: 8px;
    }
    .asm-m-task-type i { font-size: 0.65rem; }
    .asm-m-task-status {
        font-size: 0.68rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 8px;
        text-transform: capitalize;
    }
    .asm-m-task-status-pending { background: #fef3c7; color: #92400e; }
    .asm-m-task-status-completed { background: #d1fae5; color: #065f46; }
    .asm-m-task-status-overdue,
    .asm-m-task-status-missed { background: #fee2e2; color: #991b1b; }
    .asm-m-task-status { background: #f1f5f9; color: #475569; }
    .asm-m-task-title {
        margin: 0;
        font-size: 0.92rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.35;
    }
    .asm-m-task-schedule {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.76rem;
        color: #94a3b8;
        margin-top: 6px;
    }
    .asm-m-task-schedule i { font-size: 0.68rem; }
    .asm-m-task-note {
        margin: 8px 0 0;
        font-size: 0.8rem;
        color: #64748b;
        line-height: 1.5;
    }
    .asm-m-task-footer {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 14px;
    }
    .asm-m-task-btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #374151;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s;
        text-decoration: none;
    }
    .asm-m-task-btn:active { background: #f3f4f6; }
    .asm-m-task-btn i { font-size: 0.75rem; }
    .asm-m-task-btn-primary {
        background: #063A1C;
        border-color: #063A1C;
        color: #fff;
    }
    .asm-m-task-btn-primary:active { background: #0a5428; }

    /* ── Notes ── */
    .asm-m-notes-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    .asm-m-note-tile {
        background: #f8faf9;
        border: 1px solid #e5ebe8;
        border-radius: 14px;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .asm-m-note-label {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
    }
    .asm-m-note-value {
        font-size: 0.88rem;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.3;
        word-break: break-word;
    }
    .asm-m-note-plain {
        background: #f8faf9;
        border: 1px solid #e5ebe8;
        border-radius: 14px;
        padding: 14px;
        font-size: 0.84rem;
        color: #475569;
        line-height: 1.55;
    }
    .asm-m-note-plain + .asm-m-note-plain {
        margin-top: 8px;
    }

    /* ── Info Grid ── */
    .asm-m-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    .asm-m-info-tile {
        background: #f8faf9;
        border: 1px solid #e5ebe8;
        border-radius: 14px;
        padding: 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .asm-m-info-label {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
    }
    .asm-m-info-value {
        font-size: 0.88rem;
        font-weight: 700;
        color: #1e293b;
        line-height: 1.3;
        word-break: break-word;
    }
    .asm-m-info-section {
        margin-top: 10px;
        background: #f8faf9;
        border: 1px solid #e5ebe8;
        border-radius: 14px;
        padding: 14px;
    }

    /* ── Empty State ── */
    .asm-m-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 32px 16px;
        color: #94a3b8;
        text-align: center;
    }
    .asm-m-empty i {
        font-size: 1.6rem;
        opacity: 0.5;
    }
    .asm-m-empty span {
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    /* Lead Detail Page Responsive Fixes */
    .lead-detail-container {
        width: 100%;
        max-width: 100%;
        margin: 0 auto;
        padding: 0;
        box-sizing: border-box;
        overflow-x: hidden; /* Prevent horizontal scroll */
    }
    
    /* Ensure proper word wrapping */
    .word-wrap {
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
    }
    
    /* Desktop view fixes - prevent overflow and ensure proper layout */
    @media (min-width: 1024px) {
        .lead-detail-container {
            overflow-x: hidden !important;
            width: 100% !important;
            max-width: 100% !important;
            margin-left: 0 !important;
            padding-left: 0 !important;
        }
        
        /* Ensure grid uses full width properly - respect Tailwind lg:grid-cols-3 */
        .lead-detail-container > .grid.lg\:grid-cols-3 {
            width: 100% !important;
            max-width: 100% !important;
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 1.5rem !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
            display: grid !important;
        }
        
        /* Ensure col-span classes work properly */
        .lead-detail-container > .grid > .lg\:col-span-1 {
            grid-column: span 1 / span 1 !important;
            min-width: 0 !important;
            max-width: 100% !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
        }
        
        .lead-detail-container > .grid > .lg\:col-span-2 {
            grid-column: span 2 / span 2 !important;
            min-width: 0 !important;
            max-width: 100% !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
        }
        
        /* Prevent any child from causing overflow */
        .lead-detail-container > .grid > * {
            min-width: 0 !important;
            box-sizing: border-box !important;
        }
        
        /* Ensure all cards use proper width */
        .lead-detail-container .bg-white {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
        }
        
        /* Fix grid inside cards */
        .lead-detail-container .grid.grid-cols-2 {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
        }
        
        /* Ensure no horizontal scroll */
        .lead-detail-container * {
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        
        /* Ensure grid children don't force full width on desktop */
        .lead-detail-container > .grid > .lg\:col-span-1 > *,
        .lead-detail-container > .grid > .lg\:col-span-2 > * {
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
    }
    
    /* Mobile specific fixes */
    @media (max-width: 640px) {
        .lead-detail-container {
            padding: 0 8px;
        }
        
        /* Ensure grid takes full width on mobile */
        .lead-detail-container .grid {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }
        
        /* Fix contact details spacing */
        .lead-detail-container .space-y-3 > * + * {
            margin-top: 0.75rem;
        }
        
        /* Ensure buttons don't overflow */
        .lead-detail-container button,
        .lead-detail-container a {
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Meeting Modal - Mobile optimization */
        #meetingModal {
            padding: 0.5rem !important;
        }
        
        #meetingModal > div {
            max-width: 100% !important;
        }
        
        #meetingModal .bg-white {
            max-height: calc(100vh - 1rem) !important;
            margin-bottom: 0 !important;
        }
        
        /* Compact spacing */
        #meetingModal .px-6 {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
        
        #meetingModal .py-5 {
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
        }
        
        /* Compact button footer on mobile */
        #meetingModal .bg-gray-50 {
            padding: 0.75rem 1rem !important;
        }
        
        /* Smaller buttons on mobile */
        #meetingModal button {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
            font-size: 0.875rem !important;
        }

        .asm-m-notes-grid,
        .asm-m-info-grid {
            grid-template-columns: 1fr;
        }
    }
    
    /* Prevent content cutoff */
    .lead-detail-container * {
        box-sizing: border-box;
    }
    
    /* Ensure text doesn't overflow */
    .lead-detail-container p,
    .lead-detail-container span,
    .lead-detail-container a {
        word-break: break-word;
        overflow-wrap: break-word;
    }

    /* Meeting Modal Clean Layout */
    #meetingModal {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    #meetingModal.hidden {
        display: none !important;
    }
    
    /* Remove extra spacing in form */
    #meetingForm .space-y-4 > * + * {
        margin-top: 1rem !important;
    }
    
    /* Compact button footer - NO extra space */
    #meetingModal .bg-gray-50 {
        min-height: auto !important;
        padding-bottom: 0.875rem !important;
    }
    
    /* Ensure buttons are properly styled */
    #meetingModal button {
        white-space: nowrap;
    }
    
    /* Remove any bottom margin/padding from modal container */
    #meetingModal .bg-white {
        margin-bottom: 0 !important;
        padding-bottom: 0 !important;
    }

    @media (max-width: 1024px) {
        #leadRequirementsModal .lead-requirements-modal {
            width: min(100vw - 8px, 100%);
            max-width: min(100vw - 8px, 100%);
            max-height: calc(100dvh - 8px);
            border-radius: 14px;
        }
        #leadRequirementsModal .lead-requirements-modal-head {
            padding: 12px 14px;
        }
        #leadRequirementsModal .lead-requirements-modal-head h2 {
            font-size: 1.2rem;
        }
        #leadRequirementsModal .lead-requirements-close-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            font-size: 24px;
        }
        #leadRequirementsModal #leadDetailFormContainer {
            padding: 10px;
        }
    }

    @media (max-width: 640px) {
        #leadRequirementsModal {
            align-items: stretch !important;
            justify-content: stretch !important;
            padding: 0 !important;
            background: rgba(15, 23, 42, 0.44) !important;
        }

        #leadRequirementsModal .lead-requirements-modal {
            width: 100vw;
            max-width: 100vw;
            min-height: 100dvh;
            max-height: 100dvh;
            border-radius: 0;
            margin: 0;
            border: 0;
            box-shadow: none;
        }

        #leadRequirementsModal .lead-requirements-modal-head {
            padding: 18px 16px;
        }

        #leadRequirementsModal .lead-requirements-modal-head h2 {
            font-size: 1.9rem;
        }

        #leadRequirementsModal .lead-requirements-close-btn {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            font-size: 28px;
        }

        #leadRequirementsModal .lead-requirements-modal-body {
            padding: 0;
        }

        #leadRequirementsModal #leadDetailFormContainer {
            padding: 8px;
            background: #f4f6f5;
        }
    }
</style>
@endpush

@push('styles')
<style>
    .site-visit-project-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background-color: #10b981;
        color: white;
        border-radius: 9999px;
        font-size: 14px;
        font-weight: 500;
    }
    .site-visit-project-tag-remove {
        cursor: pointer;
        margin-left: 4px;
        opacity: 0.8;
        font-size: 12px;
    }
    .site-visit-project-tag-remove:hover {
        opacity: 1;
    }
    .convert-project-dropdown-panel {
        border: 2px solid #e5e7eb;
        border-radius: 16px;
        background: #fff;
        padding: 14px;
    }
    .convert-project-search-wrap {
        margin-bottom: 10px;
    }
    .convert-project-options {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        max-height: 220px;
        overflow-y: auto;
    }
    .convert-project-chip {
        border: 1px solid #d1d5db;
        background: #fff;
        color: #4b5563;
        border-radius: 9999px;
        padding: 8px 14px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .convert-project-chip:hover {
        border-color: #205A44;
        color: #205A44;
    }
    .convert-project-chip.active {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        border-color: #063A1C;
        color: #fff;
        box-shadow: 0 10px 24px rgba(15, 109, 68, 0.18);
    }

    @media (max-width: 767px) {
        .lead-detail-container.manager-mobile-safe .asm-mobile-lead-shell {
            display: block;
        }

        .lead-detail-container.manager-mobile-safe .asm-mobile-desktop-block {
            display: none !important;
        }

        .lead-detail-container.manager-mobile-safe {
            padding: 0 6px 20px;
            background: #f5f7f6;
        }

        .lead-detail-container.manager-mobile-safe > .bg-gradient-to-r {
            display: none !important;
        }

        .asm-m-notes-grid,
        .asm-m-info-grid {
            grid-template-columns: 1fr;
        }
    }

    .modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 2000;
        background: rgba(15, 23, 42, 0.55);
        padding: 16px;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }
    .modal.active {
        display: block;
    }
    .modal-content {
        width: min(100%, 720px);
        margin: 48px auto;
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 24px 54px rgba(15, 23, 42, 0.18);
    }
    @if($embedTaskFlowOnly)
    html body #mobileFooterNav,
    html body #chatbotWidget,
    html body #chatbotToggle,
    html body #chatbotWindow,
    html body #chatbotBadge,
    html body #sidebar,
    html body .sidebar-overlay,
    html body .header,
    html body .container > .header,
    html body #successPopupOverlay {
        display: none !important;
        visibility: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    html body .modal,
    html body #followupModal,
    html body #siteVisitModal,
    html body #meetingModal,
    html body #scheduleCallTaskModal,
    html body #verifyRejectPromptModal,
    html body #managerLeadRequirementFormModal,
    html body #rejectReasonModal,
    html body #cnpTimeSelectionModal,
    html body #followUpActionHubModal,
    html body #visitActionHubModal,
    html body #meetingActionHubModal,
    html body #leadDetailCompleteMeetingModal,
    html body #leadDetailRescheduleMeetingModal,
    html body #leadDetailCompleteVisitModal {
        background: transparent !important;
    }

    html body .modal-content,
    html body .lead-requirements-modal,
    html body .follow-up-action-hub-modal,
    html body .asm-outcome-modal {
        box-shadow: 0 24px 54px rgba(15, 23, 42, 0.18) !important;
    }
    @endif

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 24px;
        border-bottom: 1px solid #e5e7eb;
    }
    .modal-header h3 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #111827;
    }
    .modal-body {
        padding: 24px;
    }
    .follow-up-action-hub-modal {
        width: min(100%, 880px);
        max-height: calc(100dvh - 32px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .follow-up-action-hub-modal .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        touch-action: pan-y;
        padding-bottom: calc(24px + env(safe-area-inset-bottom, 0px));
    }
    .follow-up-action-hub-header {
        background: linear-gradient(135deg, #063A1C 0%, #205A44 100%);
        border-bottom: 0;
    }
    .follow-up-action-hub-header h3,
    .follow-up-action-hub-header .close-modal {
        color: #ffffff;
    }
    .follow-up-action-hub-subtitle {
        margin-top: 6px;
        color: rgba(255, 255, 255, 0.86);
        font-size: 14px;
    }
    .follow-up-action-hub-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }
    .follow-up-action-tile {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 18px;
        border-radius: 18px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        text-align: left;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .follow-up-action-tile:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
    }
    .follow-up-action-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 18px;
    }
    .follow-up-action-copy {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }
    .follow-up-action-copy strong {
        color: #0f172a;
        font-size: 15px;
        line-height: 1.35;
    }
    .follow-up-action-copy small {
        color: #64748b;
        font-size: 12px;
        line-height: 1.45;
    }
    .follow-up-action-tile-blue .follow-up-action-icon { background: #dbeafe; color: #2563eb; }
    .follow-up-action-tile-green .follow-up-action-icon { background: #dcfce7; color: #16a34a; }
    .follow-up-action-tile-slate .follow-up-action-icon { background: #e2e8f0; color: #475569; }
    .follow-up-action-tile-emerald .follow-up-action-icon { background: #d1fae5; color: #059669; }
    .follow-up-action-tile-cyan .follow-up-action-icon { background: #cffafe; color: #0891b2; }
    .follow-up-more-wrap {
        margin-top: 18px;
        border-top: 1px solid #e5e7eb;
        padding-top: 18px;
    }
    .follow-up-more-toggle {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        color: #0f172a;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
    }
    .follow-up-more-panel {
        display: none;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 12px;
    }
    .follow-up-more-panel.active {
        display: grid;
    }
    .follow-up-more-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 13px 16px;
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        color: #334155;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .follow-up-more-btn:hover {
        border-color: #94a3b8;
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
    }
    .close-modal,
    .close-btn {
        border: 0;
        background: transparent;
        color: #6b7280;
        font-size: 28px;
        line-height: 1;
        cursor: pointer;
    }
    .asm-outcome-modal .modal-body {
        padding-top: 0;
    }
    .asm-outcome-modal-body {
        padding-top: 20px;
        text-align: center;
    }
    .asm-outcome-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .asm-outcome-btn {
        min-height: 58px;
        border: none;
        border-radius: 10px;
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 16px;
        text-align: center;
    }
    .asm-outcome-btn-green { background: #205A44; }
    .asm-outcome-btn-slate { background: #6b7280; }
    .asm-outcome-btn-blue { background: #2563eb; }
    .asm-outcome-btn-amber { background: #f59e0b; }
    .asm-outcome-btn-red { background: #dc2626; }
    .asm-outcome-btn-full { grid-column: 1 / -1; }
    .time-option-btn.active {
        border-color: #205A44 !important;
        background: #ecfdf5 !important;
        color: #065f46 !important;
    }
    html.modal-open,
    body.modal-open {
        overflow: hidden;
        overscroll-behavior: none;
    }
    html.modal-open #mobileFooterNav,
    body.modal-open #mobileFooterNav,
    html.modal-open #chatbotWidget,
    body.modal-open #chatbotWidget,
    html.modal-open #chatbotToggle,
    body.modal-open #chatbotToggle,
    html.modal-open #chatbotWindow,
    body.modal-open #chatbotWindow,
    html.modal-open #chatbotBadge,
    body.modal-open #chatbotBadge {
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
    }
    #followupModal,
    #siteVisitModal,
    #meetingModal,
    #scheduleCallTaskModal,
    #completeAsmTaskModal,
    #ownerTransferModal {
        z-index: 2100 !important;
    }
    #siteVisitForm,
    #meetingForm {
        padding-bottom: calc(24px + env(safe-area-inset-bottom, 0px));
        -webkit-overflow-scrolling: touch;
        touch-action: pan-y;
    }
    @media (max-width: 768px) {
        #siteVisitModal > div,
        #meetingModal > div,
        #completeAsmTaskModal > div {
            align-items: flex-start;
            padding-top: 8px;
            padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));
        }
        #siteVisitModal > div > div,
        #meetingModal > div > div,
        #completeAsmTaskModal > div > div {
            max-height: calc(100dvh - 16px);
        }
        #completeAsmTaskForm {
            max-height: calc(100dvh - 120px);
            overflow-y: auto;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }
        .modal-content {
            width: 100%;
            margin: 20px auto;
        }
        .follow-up-action-hub-modal {
            max-height: calc(100dvh - 20px);
        }
        .modal-header,
        .modal-body {
            padding-left: 16px;
            padding-right: 16px;
        }
        .asm-outcome-grid {
            grid-template-columns: 1fr;
        }
        .follow-up-action-hub-grid,
        .follow-up-more-panel {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Use window only to avoid duplicate declaration when layout (e.g. telecaller) already defines API_BASE_URL
    if (typeof window.API_BASE_URL === 'undefined') {
        window.API_BASE_URL = '{{ url("/api") }}';
    }
    if (typeof window.API_TOKEN === 'undefined') {
        window.API_TOKEN = document.querySelector('meta[name="api-token"]')?.getAttribute('content') || '';
    }
    if (typeof window.USER_ROLE === 'undefined') {
        window.USER_ROLE = '{{ $user->role->slug ?? "" }}';
    }
    if (typeof window.ALLOW_PRIVILEGED_PAST_SCHEDULING === 'undefined') {
        window.ALLOW_PRIVILEGED_PAST_SCHEDULING = @json((bool) ($user?->isAdmin() || $user?->isCrm()));
    }
    window.currentTaskId = window.currentTaskId || null;
    window.currentTaskContext = window.currentTaskContext || {};
    const LEAD_ID = {{ $lead->id }};
    const LEAD_INTERESTED_PROJECTS = @json($uniqueProjectNames ?? []);
    const LEAD_MEETINGS = @json($leadMeetingReferenceData);
    const LEAD_SITE_VISITS = @json($leadSiteVisitReferenceData);
    const CAN_TRANSFER_OWNER = @json($user && ($user->isAdmin() || $user->isCrm() || $user->isAssistantSalesManager() || $user->isSeniorManager() || $user->isSalesManager()));
    const managerUsesUnifiedCnpScheduling = @json(optional($user)->isAssistantSalesManager() || optional($user)->isSeniorManager());
    let selectedTaskOutcome = null;
    let currentTaskCategory = 'other';
    let selectedCnpMinutes = null;
    let isCustomTimeSelected = false;
    let pendingFollowUpWorkflowAction = null;
    let pendingVisitWorkflowAction = null;
    let leadDetailVisitLockedOutcome = 'visited';
    let pendingMeetingWorkflowAction = null;
    let modalScrollLockY = 0;

    function setCurrentTaskId(value) {
        window.currentTaskId = value;
    }

    function setCurrentTaskContext(context = {}) {
        window.currentTaskContext = context || {};
    }

    function extractMeetingIdFromTaskContext(context = null) {
        const source = context || window.currentTaskContext || {};
        if (source.meetingId) {
            return Number(source.meetingId);
        }
        const parts = [source.title, source.notes, source.description].filter(Boolean);
        for (const part of parts) {
            const match = String(part).match(/meeting id\s*:\s*(\d+)/i);
            if (match) {
                return parseInt(match[1], 10);
            }
        }

        return null;
    }

    function findNearestScheduledRecord(records, taskScheduledAt, offsetMinutes = 0) {
        if (!Array.isArray(records) || !records.length || !taskScheduledAt) {
            return null;
        }

        const taskTime = new Date(taskScheduledAt).getTime();
        if (Number.isNaN(taskTime)) {
            return null;
        }

        let closestRecord = null;
        let closestDiff = Number.POSITIVE_INFINITY;
        records.forEach((record) => {
            if (!record?.scheduled_at) {
                return;
            }

            const scheduledTime = new Date(record.scheduled_at).getTime();
            if (Number.isNaN(scheduledTime)) {
                return;
            }

            const adjustedTaskTime = taskTime + (offsetMinutes * 60 * 1000);
            const diff = Math.abs(scheduledTime - adjustedTaskTime);
            if (
                diff < closestDiff
                || (
                    diff === closestDiff
                    && closestRecord
                    && Number(record?.id || 0) > Number(closestRecord?.id || 0)
                )
                || (diff === closestDiff && !closestRecord)
            ) {
                closestDiff = diff;
                closestRecord = record;
            }
        });

        return closestDiff <= (90 * 60 * 1000) ? closestRecord : null;
    }

    function resolveMeetingIdFromTaskContext(context = null) {
        const source = context || window.currentTaskContext || {};
        const directId = extractMeetingIdFromTaskContext(context);
        const nearestRecord = findNearestScheduledRecord(LEAD_MEETINGS, source.scheduledAt, 30);

        if (directId) {
            const directRecord = Array.isArray(LEAD_MEETINGS)
                ? LEAD_MEETINGS.find((record) => Number(record?.id) === Number(directId))
                : null;

            if (!directRecord) {
                return nearestRecord?.id || directId;
            }

            if (nearestRecord && Number(nearestRecord.id) !== Number(directId)) {
                const directTime = new Date(directRecord.scheduled_at || '').getTime();
                const nearestTime = new Date(nearestRecord.scheduled_at || '').getTime();
                const sameScheduleWindow = !Number.isNaN(directTime)
                    && !Number.isNaN(nearestTime)
                    && Math.abs(directTime - nearestTime) <= (5 * 60 * 1000);

                if (sameScheduleWindow && Number(nearestRecord.id) > Number(directId)) {
                    return Number(nearestRecord.id);
                }
            }

            return Number(directId);
        }

        return nearestRecord?.id || null;
    }

    function resolveSiteVisitIdFromTaskContext(context = null) {
        const source = context || window.currentTaskContext || {};
        if (source.siteVisitId) {
            return Number(source.siteVisitId);
        }
        const parts = [source.title, source.notes, source.description].filter(Boolean);
        for (const part of parts) {
            const match = String(part).match(/site visit id\s*:\s*(\d+)/i);
            if (match) {
                return parseInt(match[1], 10);
            }
        }

        const activeVisits = Array.isArray(LEAD_SITE_VISITS)
            ? LEAD_SITE_VISITS.filter((visit) => {
                const status = String(visit?.status || '').toLowerCase();
                return !['cancelled', 'canceled', 'dead'].includes(status);
            })
            : [];

        return findNearestScheduledRecord(LEAD_SITE_VISITS, source.scheduledAt, 10)?.id
            || findNearestScheduledRecord(LEAD_SITE_VISITS, source.scheduledAt, 30)?.id
            || (activeVisits.length === 1 ? Number(activeVisits[0]?.id || 0) : null)
            || (activeVisits.length ? Number(activeVisits.slice().sort((a, b) => Number(b?.id || 0) - Number(a?.id || 0))[0]?.id || 0) : null)
            || null;
    }

    function resolveSiteVisitProjectFromTaskContext(context = null) {
        const source = context || window.currentTaskContext || {};
        if (source.siteVisitProject) {
            return String(source.siteVisitProject || '').trim();
        }

        const siteVisitId = resolveSiteVisitIdFromTaskContext(source);
        const matchingVisit = Array.isArray(LEAD_SITE_VISITS) && siteVisitId
            ? LEAD_SITE_VISITS.find((visit) => Number(visit?.id) === Number(siteVisitId))
            : null;
        const nearestVisit = matchingVisit || findNearestScheduledRecord(LEAD_SITE_VISITS, source.scheduledAt, 30);

        return String(nearestVisit?.project || nearestVisit?.property_name || '').trim();
    }

    function normalizeVisitedProjectList(value) {
        return String(value || '')
            .split(',')
            .map((project) => project.trim())
            .filter(Boolean);
    }

    function setLeadDetailVisitedProjects(projects) {
        const input = document.getElementById('leadDetailVisitVisitedProjects');
        if (!input) {
            return;
        }

        const normalized = Array.from(new Set((Array.isArray(projects) ? projects : [])
            .map((project) => String(project || '').trim())
            .filter(Boolean)));

        input.value = normalized.join(', ');
    }

    function prefillLeadDetailVisitedProjects() {
        const input = document.getElementById('leadDetailVisitVisitedProjects');
        if (!input || input.value.trim()) {
            return;
        }

        const scheduledProject = resolveSiteVisitProjectFromTaskContext();
        if (scheduledProject) {
            setLeadDetailVisitedProjects([scheduledProject]);
        }
    }

    function addLeadDetailVisitedProject() {
        const addOnInput = document.getElementById('leadDetailVisitAddOnProject');
        const value = String(addOnInput?.value || '').trim();
        if (!value) {
            showLeadDetailAlert('Please enter a project name to add', 'warning');
            return;
        }

        const existingProjects = normalizeVisitedProjectList(document.getElementById('leadDetailVisitVisitedProjects')?.value);
        setLeadDetailVisitedProjects([...existingProjects, value]);

        if (addOnInput) {
            addOnInput.value = '';
            addOnInput.focus();
        }
    }

    function normalizeTaskCategory(taskCategory = 'other', context = null) {
        const source = context || window.currentTaskContext || {};
        const rawCategory = String(taskCategory || '').trim().toLowerCase();
        const textHaystack = [
            source.title,
            source.notes,
            source.description,
        ].filter(Boolean).join(' ').toLowerCase();
        const resolvedMeetingId = extractMeetingIdFromTaskContext(source)
            || findNearestScheduledRecord(LEAD_MEETINGS, source.scheduledAt, 30)?.id
            || null;

        if (resolvedMeetingId && (source.meetingId || rawCategory.includes('meeting') || textHaystack.includes('meeting'))) {
            return 'meeting';
        }

        const resolvedSiteVisitId = resolveSiteVisitIdFromTaskContext(source);
        const looksLikeSiteVisitTask = source.siteVisitId
            || rawCategory.includes('site_visit')
            || rawCategory.includes('site visit')
            || textHaystack.includes('site visit');

        if (looksLikeSiteVisitTask && resolvedSiteVisitId) {
            return 'site_visit';
        }

        if (rawCategory === 'fresh_lead') {
            return 'fresh_lead';
        }

        if (
            source.followUpId
            || rawCategory.includes('follow_up')
            || rawCategory.includes('follow up')
        ) {
            return 'follow_up';
        }

        return rawCategory || 'other';
    }

    function isLeadDetailUtilityModalVisible(id) {
        const element = document.getElementById(id);
        if (!element) {
            return false;
        }

        if (element.classList.contains('hidden')) {
            return false;
        }

        return window.getComputedStyle(element).display !== 'none';
    }

    function syncModalBodyLock() {
        const hasActiveModal = document.querySelector('.modal.active')
            || isLeadDetailUtilityModalVisible('followupModal')
            || isLeadDetailUtilityModalVisible('siteVisitModal')
            || isLeadDetailUtilityModalVisible('meetingModal')
            || isLeadDetailUtilityModalVisible('scheduleCallTaskModal')
            || isLeadDetailUtilityModalVisible('completeAsmTaskModal')
            || isLeadDetailUtilityModalVisible('ownerTransferModal')
            || isLeadDetailUtilityModalVisible('leadRequirementsModal');
        if (hasActiveModal) {
            if (!document.body.classList.contains('modal-open')) {
                modalScrollLockY = window.scrollY || window.pageYOffset || 0;
                document.body.style.position = 'fixed';
                document.body.style.top = `-${modalScrollLockY}px`;
                document.body.style.left = '0';
                document.body.style.right = '0';
                document.body.style.width = '100%';
            }
            document.documentElement.classList.add('modal-open');
            document.body.classList.add('modal-open');
            return;
        }

        document.documentElement.classList.remove('modal-open');
        document.body.classList.remove('modal-open');
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';
        window.scrollTo(0, modalScrollLockY || 0);
    }

    function getAuthHeaders(extraHeaders = {}, options = {}) {
        const bearerToken = typeof window.getManagerApiToken === 'function'
            ? window.getManagerApiToken()
            : window.API_TOKEN;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const baseHeaders = {
            'Accept': 'application/json',
            'Authorization': `Bearer ${bearerToken}`,
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (!options.omitJsonContentType) {
            baseHeaders['Content-Type'] = 'application/json';
        }

        if (typeof window.getManagerAuthHeaders === 'function') {
            return window.getManagerAuthHeaders({
                ...baseHeaders,
                ...extraHeaders,
            });
        }

        return {
            ...baseHeaders,
            ...extraHeaders,
        };
    }

    async function apiCall(endpoint, options = {}) {
        const normalizedEndpoint = endpoint.startsWith('/tasks/')
            ? `/sales-manager${endpoint}`
            : endpoint;

        const response = await fetch(`${window.API_BASE_URL}${normalizedEndpoint}`, {
            method: options.method || 'GET',
            headers: {
                ...getAuthHeaders(),
                ...(options.headers || {})
            },
            body: options.body || undefined,
            credentials: 'same-origin'
        });

        const text = await response.text();
        let result = {};

        try {
            result = text ? JSON.parse(text) : {};
        } catch (error) {
            result = { success: false, message: text || 'Invalid server response' };
        }

        if (!response.ok) {
            return { success: false, ...result, message: result.message || result.error || `HTTP ${response.status}` };
        }

        return result;
    }

    async function submitTaskWorkflowFormData(taskId, formData) {
        const response = await fetch(`${window.API_BASE_URL}/sales-manager/tasks/${taskId}/workflow-action`, {
            method: 'POST',
            headers: getAuthHeaders({}, { omitJsonContentType: true }),
            body: formData,
            credentials: 'same-origin'
        });

        const text = await response.text();
        let result = {};

        try {
            result = text ? JSON.parse(text) : {};
        } catch (error) {
            result = { success: false, message: text || 'Invalid server response' };
        }

        if (!response.ok || result?.success === false) {
            throw new Error(getLeadDetailApiErrorMessage(result, 'Failed to process workflow action'));
        }

        return result;
    }

    function showLeadDetailAlert(message, type = 'success', duration = 3000) {
        if (typeof window.showAlert === 'function') {
            window.showAlert(message, type, duration);
            return;
        }
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type, duration);
            return;
        }
        alert(message);
    }

    function getLeadDetailApiErrorMessage(result, fallback = 'Failed to process request') {
        const firstValidationError = result?.errors
            ? Object.values(result.errors).flat().find(Boolean)
            : null;

        return firstValidationError || result?.message || result?.error || fallback;
    }

    let markCloserDraftInFlight = false;

    async function markLeadAsCloserDraft() {
        if (markCloserDraftInFlight) {
            return;
        }

        const confirmed = window.confirm('Move this lead directly to Closer Draft?');
        if (!confirmed) {
            return;
        }

        const button = document.getElementById('markCloserDraftBtn');
        const originalHtml = button ? button.innerHTML : '';

        try {
            markCloserDraftInFlight = true;
            if (button) {
                button.disabled = true;
                button.classList.add('opacity-70', 'cursor-not-allowed');
                button.innerHTML = '<i class="fas fa-spinner fa-spin text-xs sm:text-sm"></i><span>Moving...</span>';
            }

            const response = await fetch(`${window.API_BASE_URL}/leads/${LEAD_ID}/mark-closer-draft`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${window.API_TOKEN}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({}),
            });

            const result = await response.json().catch(() => ({}));
            if (!response.ok || result?.success === false) {
                throw new Error(getLeadDetailApiErrorMessage(result, 'Failed to move lead to closer draft'));
            }

            showLeadDetailAlert(result.message || 'Lead moved to closer draft successfully.', 'success', 2500);
            setTimeout(() => window.location.reload(), 1200);
        } catch (error) {
            showLeadDetailAlert(error.message || 'Failed to move lead to closer draft', 'error', 4000);
        } finally {
            markCloserDraftInFlight = false;
            if (button) {
                button.disabled = false;
                button.classList.remove('opacity-70', 'cursor-not-allowed');
                button.innerHTML = originalHtml;
            }
        }
    }

    async function completeOldTask(modelType, taskId, taskLabel) {
        const confirmed = window.confirm(`Mark "${taskLabel}" as completed?`);
        if (!confirmed) {
            return;
        }

        const result = await apiCall(`/leads/${LEAD_ID}/old-tasks/complete`, {
            method: 'POST',
            body: JSON.stringify({
                model_type: modelType,
                task_id: taskId,
            }),
        });

        if (!result?.success) {
            showLeadDetailAlert(result?.message || 'Failed to complete task', 'error');
            return;
        }

        showLeadDetailAlert(result.message || 'Task completed successfully');
        window.location.reload();
    }

    function handleOldTaskCompleteAction(button) {
        const row = button?.closest('[data-old-task-row]');
        if (!row) {
            return;
        }

        const modelType = row.dataset.modelType || 'task';
        const taskId = row.dataset.taskId;
        const taskLabel = row.dataset.taskTitle || 'Task';

        if (modelType === 'task') {
            setCurrentTaskId(taskId);
            setCurrentTaskContext({
                title: row.dataset.taskTitle || '',
                notes: row.dataset.taskNotes || '',
                description: row.dataset.taskDescription || '',
                scheduledAt: row.dataset.taskScheduledAt || '',
                meetingId: row.dataset.taskMeetingId || '',
                siteVisitId: row.dataset.taskSiteVisitId || '',
                siteVisitProject: row.dataset.taskSiteVisitProject || '',
                followUpId: row.dataset.taskFollowUpId || '',
            });
            currentTaskCategory = normalizeTaskCategory(row.dataset.taskCategory, window.currentTaskContext);
            openTaskOutcomeModal(taskId, currentTaskCategory);
            return;
        }

        completeOldTask(modelType, taskId, taskLabel);
    }

    async function deleteOldTask(modelType, taskId, taskLabel) {
        const confirmed = window.confirm(`Delete "${taskLabel}" from this lead?`);
        if (!confirmed) {
            return;
        }

        const result = await apiCall(`/leads/${LEAD_ID}/old-tasks/delete`, {
            method: 'POST',
            body: JSON.stringify({
                model_type: modelType,
                task_id: taskId,
            }),
        });

        if (!result?.success) {
            showLeadDetailAlert(result?.message || 'Failed to delete task', 'error');
            return;
        }

        showLeadDetailAlert(result.message || 'Task removed successfully');
        window.location.reload();
    }

    let oldTaskTransferInFlight = false;

    function openOldTaskTransferModal(modelType, taskId, taskLabel, currentAssignee) {
        const modal = document.getElementById('oldTaskTransferModal');
        if (!modal) {
            return;
        }

        document.getElementById('oldTaskTransferModelType').value = modelType || '';
        document.getElementById('oldTaskTransferTaskId').value = taskId || '';
        document.getElementById('oldTaskTransferAssignedTo').value = '';
        document.getElementById('oldTaskTransferNotes').value = '';

        const summary = document.getElementById('oldTaskTransferSummary');
        if (summary) {
            summary.textContent = `${taskLabel || 'Task'} currently assigned to ${currentAssignee || 'Unassigned'}. Lead owner will not change.`;
        }

        modal.classList.remove('hidden');
        syncModalBodyLock();
    }

    function closeOldTaskTransferModal() {
        document.getElementById('oldTaskTransferModal')?.classList.add('hidden');
        syncModalBodyLock();
    }

    async function submitOldTaskTransfer(event) {
        event.preventDefault();
        if (oldTaskTransferInFlight) {
            return;
        }

        const form = event.target;
        const formData = new FormData(form);
        const assignedTo = formData.get('assigned_to');
        if (!assignedTo) {
            alert('Please select user');
            return;
        }

        const submitBtn = document.getElementById('oldTaskTransferSubmitBtn');
        const originalText = submitBtn ? submitBtn.innerHTML : '';

        try {
            oldTaskTransferInFlight = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
                submitBtn.innerHTML = 'Transferring...';
            }

            const result = await apiCall(`/leads/${LEAD_ID}/old-tasks/transfer`, {
                method: 'POST',
                body: JSON.stringify({
                    model_type: formData.get('model_type'),
                    task_id: parseInt(formData.get('task_id'), 10),
                    assigned_to: parseInt(assignedTo, 10),
                    notes: (formData.get('notes') || '').trim() || null,
                }),
            });

            if (!result?.success) {
                showLeadDetailAlert(result?.message || 'Failed to transfer task', 'error');
                return;
            }

            closeOldTaskTransferModal();
            showLeadDetailAlert(result.message || 'Task transferred successfully');
            window.location.reload();
        } catch (error) {
            console.error('Old task transfer error:', error);
            alert(error.message || 'Unable to transfer task');
        } finally {
            oldTaskTransferInFlight = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                submitBtn.innerHTML = originalText;
            }
        }
    }

    // Modal open/close functions
    function openFollowupModal() {
        document.getElementById('followupModal').classList.remove('hidden');
        syncModalBodyLock();
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.querySelector('#followupForm input[name="scheduled_date"]').value = tomorrow.toISOString().split('T')[0];
        document.querySelector('#followupForm input[name="scheduled_time"]').value = '10:00';
        const checkbox = document.getElementById('followup_required');
        if (checkbox) checkbox.checked = true;
    }

    function closeFollowupModal() {
        document.getElementById('followupModal').classList.add('hidden');
        document.getElementById('followupForm').reset();
        syncModalBodyLock();
    }

    function openSiteVisitModal() {
        closeCompleteAsmTaskModal();
        document.getElementById('siteVisitModal').classList.remove('hidden');
        syncModalBodyLock();
        document.getElementById('siteVisitForm')?.scrollTo({ top: 0, behavior: 'auto' });
        const scheduledAtInput = document.getElementById('siteVisitScheduledAt');
        const defaultDate = new Date();
        defaultDate.setDate(defaultDate.getDate() + 1);
        const minDate = new Date();
        minDate.setHours(minDate.getHours() + 1, minDate.getMinutes(), 0, 0);
        if (scheduledAtInput) {
            if (window.ALLOW_PRIVILEGED_PAST_SCHEDULING) {
                scheduledAtInput.removeAttribute('min');
            } else {
                scheduledAtInput.min = minDate.toISOString().slice(0, 16);
            }
            scheduledAtInput.value = defaultDate.toISOString().slice(0, 16);
        }
        const defaultProject = (LEAD_INTERESTED_PROJECTS && LEAD_INTERESTED_PROJECTS[0]) ? LEAD_INTERESTED_PROJECTS[0] : '';
        loadSiteVisitProjectOptions(defaultProject);
        setupSiteVisitProjectInput();
    }

    function closeSiteVisitModal() {
        document.getElementById('siteVisitModal').classList.add('hidden');
        document.getElementById('siteVisitForm').reset();
        pendingVisitWorkflowAction = null;
        pendingFollowUpWorkflowAction = null;
        if (pendingMeetingWorkflowAction === 'visit') {
            pendingMeetingWorkflowAction = null;
        }
        const hiddenInput = document.getElementById('siteVisitProjectHidden');
        if (hiddenInput) {
            hiddenInput.value = '';
        }
        const projectOptions = document.getElementById('siteVisitProjectOptions');
        if (projectOptions) {
            projectOptions.innerHTML = '';
        }
        syncModalBodyLock();
    }

    let siteVisitProjectOptions = [];

    function ensureSiteVisitSelectedProjectsContainer() {
        const optionsContainer = document.getElementById('siteVisitProjectOptions');
        if (!optionsContainer) return null;

        let selectedContainer = document.getElementById('siteVisitSelectedProjects');
        if (selectedContainer) return selectedContainer;

        selectedContainer = document.createElement('div');
        selectedContainer.id = 'siteVisitSelectedProjects';
        selectedContainer.className = 'convert-project-options';
        selectedContainer.style.marginBottom = '10px';
        optionsContainer.parentNode.insertBefore(selectedContainer, optionsContainer);
        return selectedContainer;
    }

    function getSiteVisitSelectedProjects() {
        const hiddenInput = document.getElementById('siteVisitProjectHidden');
        if (!hiddenInput) return [];

        const raw = String(hiddenInput.value || '').trim();
        if (!raw) return [];

        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed)
                ? parsed.map(project => String(project || '').trim()).filter(Boolean)
                : [];
        } catch (error) {
            return raw.split(',').map(project => String(project || '').trim()).filter(Boolean);
        }
    }

    function renderSiteVisitSelectedProjects(projects) {
        const container = ensureSiteVisitSelectedProjectsContainer();
        if (!container) return;

        const normalized = Array.isArray(projects) ? projects : [];
        if (!normalized.length) {
            container.innerHTML = '';
            container.style.display = 'none';
            return;
        }

        container.style.display = 'flex';
        container.innerHTML = normalized.map(project =>
            `<button type="button" class="convert-project-chip active" data-site-visit-selected-project-name="${escapeHtml(project)}">${escapeHtml(project)}</button>`
        ).join('');
    }

    function setSiteVisitSelectedProjects(projects) {
        const hiddenInput = document.getElementById('siteVisitProjectHidden');
        if (!hiddenInput) return;

        const normalized = Array.from(new Set((Array.isArray(projects) ? projects : [])
            .map(project => String(project || '').trim())
            .filter(Boolean)));

        hiddenInput.value = JSON.stringify(normalized);
        renderSiteVisitSelectedProjects(normalized);
    }

    function updateSiteVisitProjectHiddenInput() {
        const projectInput = document.getElementById('siteVisitProjectInput');
        const hiddenInput = document.getElementById('siteVisitProjectHidden');

        if (!hiddenInput || !projectInput) return;

        if (projectInput.value.trim() && getSiteVisitSelectedProjects().length === 0) {
            setSiteVisitSelectedProjects([projectInput.value.trim()]);
        }
    }

    function setupSiteVisitProjectInput() {
        const input = document.getElementById('siteVisitProjectInput');
        if (!input) {
            return;
        }

        input.oninput = function() {
            renderSiteVisitProjectOptions(this.value);
        };

        input.onkeydown = function(e) {
            if (e.key !== 'Enter') {
                return;
            }

            e.preventDefault();
            const value = this.value.trim();
            if (!value) {
                return;
            }

            if (!siteVisitProjectOptions.includes(value)) {
                siteVisitProjectOptions.unshift(value);
            }

            setSiteVisitProjectSelection(value);
        };
    }

    async function loadSiteVisitProjectOptions(selectedProject = '') {
        let options = [];

        try {
            const response = await fetch('/api/interested-project-names', {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${window.API_TOKEN}`,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const result = await response.json();

            if (response.ok && result?.success && Array.isArray(result.data)) {
                options = result.data
                    .map(project => String(project?.name || '').trim())
                    .filter(Boolean);
            }
        } catch (error) {
            console.warn('Failed to load lead detail site visit projects:', error);
        }

        const selectedProjects = Array.isArray(selectedProject)
            ? selectedProject.map(project => String(project || '').trim()).filter(Boolean)
            : (String(selectedProject || '').trim() ? [String(selectedProject || '').trim()] : []);

        selectedProjects.forEach(project => options.unshift(project));

        siteVisitProjectOptions = Array.from(new Set(options.filter(Boolean)));
        renderSiteVisitProjectOptions();
        setSiteVisitSelectedProjects(selectedProjects);
    }

    function renderSiteVisitProjectOptions(filter = '') {
        const container = document.getElementById('siteVisitProjectOptions');
        if (!container) {
            return;
        }

        const selectedProjects = getSiteVisitSelectedProjects().map(project => project.toLowerCase());
        const searchTerm = String(filter || '').trim().toLowerCase();
        const filteredOptions = siteVisitProjectOptions.filter(project => !searchTerm || project.toLowerCase().includes(searchTerm));

        if (!filteredOptions.length) {
            container.innerHTML = '<div class="text-sm text-gray-500 px-1 py-2">No matching project found. Press Enter to use typed project.</div>';
            return;
        }

        container.innerHTML = filteredOptions.map(project => {
            const activeClass = selectedProjects.includes(project.toLowerCase()) ? ' active' : '';
            return `<button type="button" class="convert-project-chip${activeClass}" data-site-visit-project-name="${escapeHtml(project)}">${escapeHtml(project)}</button>`;
        }).join('');
    }

    function setSiteVisitProjectSelection(projectName) {
        const input = document.getElementById('siteVisitProjectInput');
        const value = String(projectName || '').trim();
        if (!value) return;

        const selectedProjects = getSiteVisitSelectedProjects();
        const existingIndex = selectedProjects.findIndex(project => project.toLowerCase() === value.toLowerCase());

        if (existingIndex >= 0) {
            selectedProjects.splice(existingIndex, 1);
        } else {
            selectedProjects.push(value);
        }

        setSiteVisitSelectedProjects(selectedProjects);

        if (input) {
            input.value = '';
        }

        renderSiteVisitProjectOptions('');
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    document.addEventListener('click', function(event) {
        const optionButton = event.target.closest('#siteVisitProjectOptions [data-site-visit-project-name]');
        if (!optionButton) {
            return;
        }

        setSiteVisitProjectSelection(optionButton.getAttribute('data-site-visit-project-name') || '');
    });

    document.addEventListener('click', function(event) {
        const selectedChip = event.target.closest('#siteVisitSelectedProjects [data-site-visit-selected-project-name]');
        if (!selectedChip) {
            return;
        }

        setSiteVisitProjectSelection(selectedChip.getAttribute('data-site-visit-selected-project-name') || '');
    });

    async function openMeetingModal() {
        closeCompleteAsmTaskModal();
        document.getElementById('meetingModal').classList.remove('hidden');
        syncModalBodyLock();
        document.getElementById('meetingForm')?.scrollTo({ top: 0, behavior: 'auto' });

        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setMinutes(tomorrow.getMinutes() - tomorrow.getTimezoneOffset());
        const dateInput = document.getElementById('meeting_date');
        const timeInput = document.getElementById('meeting_time');
        if (dateInput) dateInput.value = tomorrow.toISOString().slice(0, 10);
        if (timeInput) timeInput.value = tomorrow.toISOString().slice(11, 16);
        const typeInput = document.getElementById('meeting_type');
        if (typeInput && !typeInput.value) typeInput.value = 'Initial Meeting';
        const modeInput = document.getElementById('meeting_mode');
        if (modeInput && !modeInput.value) modeInput.value = 'online';
        toggleMeetingModeFields();

        try {
            const response = await fetch(`${window.API_BASE_URL}/sales-manager/leads/${LEAD_ID}/meeting-history`, {
                headers: {
                    'Authorization': `Bearer ${window.API_TOKEN}`,
                    'Accept': 'application/json',
                }
            });

            if (response.ok) {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const result = await response.json();
                    document.getElementById('meeting_sequence').value = result.next_sequence || 1;
                } else {
                    console.warn('Non-JSON response for meeting history');
                }
            }
        } catch (error) {
            console.error('Failed to load meeting history:', error);
            document.getElementById('meeting_sequence').value = 1;
        }
    }

    function closeMeetingModal() {
        document.getElementById('meetingModal').classList.add('hidden');
        document.getElementById('meetingForm').reset();
        pendingVisitWorkflowAction = null;
        pendingFollowUpWorkflowAction = null;
        if (pendingMeetingWorkflowAction !== 'complete' && pendingMeetingWorkflowAction !== 'reschedule') {
            pendingMeetingWorkflowAction = null;
        }
        const modeInput = document.getElementById('meeting_mode');
        if (modeInput) modeInput.value = 'online';
        toggleMeetingModeFields();
        syncModalBodyLock();
    }

    function toggleMeetingModeFields() {
        const mode = document.getElementById('meeting_mode')?.value || 'online';
        const onlineFields = document.getElementById('meetingLinkField');
        const offlineFields = document.getElementById('meetingLocationField');
        const locationInput = document.getElementById('location_input');
        const meetingLinkInput = document.querySelector('#meetingForm input[name="meeting_link"]');

        if (mode === 'online') {
            if (onlineFields) onlineFields.classList.remove('hidden');
            if (offlineFields) offlineFields.classList.add('hidden');
            if (locationInput) locationInput.removeAttribute('required');
            if (meetingLinkInput) {
                meetingLinkInput.setAttribute('required', 'required');
            }
        } else {
            if (onlineFields) onlineFields.classList.add('hidden');
            if (offlineFields) offlineFields.classList.remove('hidden');
            if (locationInput) locationInput.setAttribute('required', 'required');
            if (meetingLinkInput) {
                meetingLinkInput.removeAttribute('required');
            }
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleMeetingModeFields();

        const params = new URLSearchParams(window.location.search);
        const openTaskId = params.get('open_task');
        const openTaskModelType = params.get('task_model') || 'task';
        if (!openTaskId) {
            return;
        }

        const taskCategory = params.get('task_category') || 'other';
        setCurrentTaskId(openTaskId);
        let derivedTaskContext = {
            title: params.get('task_title') || '',
            notes: params.get('task_notes') || '',
            description: params.get('task_description') || '',
            scheduledAt: params.get('task_scheduled_at') || '',
            meetingId: params.get('meeting_id') || '',
            siteVisitId: params.get('site_visit_id') || '',
            siteVisitProject: params.get('site_visit_project') || '',
            followUpId: params.get('follow_up_id') || '',
        };

        const selectedTaskInput = document.querySelector(`#completeAsmTaskForm input[name="task_id"][value="${CSS.escape(openTaskId)}"][data-task-model-type="${CSS.escape(openTaskModelType)}"]`)
            || document.querySelector(`#completeAsmTaskForm input[name="task_id"][value="${CSS.escape(openTaskId)}"]`);
        if (selectedTaskInput) {
            selectedTaskInput.checked = true;
            derivedTaskContext = {
                title: selectedTaskInput.dataset.taskTitle || derivedTaskContext.title || '',
                notes: selectedTaskInput.dataset.taskNotes || derivedTaskContext.notes || '',
                description: selectedTaskInput.dataset.taskDescription || derivedTaskContext.description || '',
                scheduledAt: selectedTaskInput.dataset.taskScheduledAt || derivedTaskContext.scheduledAt || '',
                meetingId: selectedTaskInput.dataset.taskMeetingId || derivedTaskContext.meetingId || '',
                siteVisitId: selectedTaskInput.dataset.taskSiteVisitId || derivedTaskContext.siteVisitId || '',
                siteVisitProject: selectedTaskInput.dataset.taskSiteVisitProject || derivedTaskContext.siteVisitProject || '',
                followUpId: selectedTaskInput.dataset.taskFollowUpId || derivedTaskContext.followUpId || '',
            };
        }
        setCurrentTaskContext(derivedTaskContext);

        const cleanUrl = new URL(window.location.href);
        [
            'open_task',
            'task_model',
            'task_category',
            'task_title',
            'task_notes',
            'task_description',
            'task_scheduled_at',
            'meeting_id',
            'site_visit_id',
            'site_visit_project',
            'follow_up_id',
        ].forEach((key) => cleanUrl.searchParams.delete(key));
        window.history.replaceState({}, document.title, `${cleanUrl.pathname}${cleanUrl.search}${cleanUrl.hash}`);

        setTimeout(() => {
            currentTaskCategory = normalizeTaskCategory(taskCategory, window.currentTaskContext);
            openTaskOutcomeModal(openTaskId, currentTaskCategory);
        }, 120);
    });

    @if($embedTaskFlowOnly)
    function getActiveEmbedModalPanel() {
        const selectors = [
            '.modal.active .modal-content',
            '.modal.active .lead-requirements-modal',
            '#followupModal:not(.hidden) > div > div',
            '#siteVisitModal:not(.hidden) > div > div',
            '#meetingModal:not(.hidden) > div > div',
            '#scheduleCallTaskModal:not(.hidden) > div',
            '#completeAsmTaskModal:not(.hidden) > div > div',
            '#verifyRejectPromptModal.active .asm-outcome-modal',
            '#managerLeadRequirementFormModal.active .modal-content',
            '#rejectReasonModal.active .modal-content',
            '#cnpTimeSelectionModal.active .modal-content',
            '#followUpActionHubModal.active .modal-content',
            '#visitActionHubModal.active .modal-content',
            '#meetingActionHubModal.active .modal-content',
            '#leadDetailCompleteMeetingModal.active .modal-content',
            '#leadDetailRescheduleMeetingModal.active .modal-content',
            '#leadDetailCompleteVisitModal.active .modal-content'
        ];

        for (const selector of selectors) {
            const element = document.querySelector(selector);
            if (element && element.offsetParent !== null) {
                return element;
            }
        }

        return null;
    }

    function notifyLeadTaskEmbedSize() {
        if (window.parent === window) {
            return;
        }

        const activePanel = getActiveEmbedModalPanel();
        const rect = activePanel ? activePanel.getBoundingClientRect() : { width: 520, height: 560 };
        const height = activePanel ? Math.max(rect.height, activePanel.scrollHeight || 0) : rect.height;

        window.parent.postMessage({
            type: 'lead-task-embed-size',
            width: Math.ceil(rect.width),
            height: Math.ceil(height)
        }, window.location.origin);
    }

    function stripLeadTaskEmbedChrome() {
        const selectors = [
            '#mobileFooterNav',
            '#chatbotWidget',
            '#chatbotToggle',
            '#chatbotWindow',
            '#chatbotBadge',
            '#sidebar',
            '.sidebar-overlay',
            '.header',
            '.container > .header',
            '#successPopupOverlay'
        ];

        selectors.forEach((selector) => {
            document.querySelectorAll(selector).forEach((element) => {
                element.style.setProperty('display', 'none', 'important');
                element.style.setProperty('visibility', 'hidden', 'important');
                element.style.setProperty('opacity', '0', 'important');
                element.style.setProperty('pointer-events', 'none', 'important');
            });
        });

        const transparentBackdropSelectors = [
            '.modal',
            '#followupModal',
            '#siteVisitModal',
            '#meetingModal',
            '#scheduleCallTaskModal',
            '#verifyRejectPromptModal',
            '#managerLeadRequirementFormModal',
            '#rejectReasonModal',
            '#cnpTimeSelectionModal',
            '#followUpActionHubModal',
            '#visitActionHubModal',
            '#meetingActionHubModal',
            '#leadDetailCompleteMeetingModal',
            '#leadDetailRescheduleMeetingModal',
            '#leadDetailCompleteVisitModal'
        ];

        transparentBackdropSelectors.forEach((selector) => {
            document.querySelectorAll(selector).forEach((element) => {
                element.style.setProperty('background', 'transparent', 'important');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        stripLeadTaskEmbedChrome();
        notifyLeadTaskEmbedSize();

        const observer = new MutationObserver(() => {
            stripLeadTaskEmbedChrome();
            window.requestAnimationFrame(notifyLeadTaskEmbedSize);
        });

        observer.observe(document.body, {
            attributes: true,
            childList: true,
            subtree: true,
            attributeFilter: ['class', 'style']
        });

        window.addEventListener('resize', notifyLeadTaskEmbedSize);
        window.setInterval(() => {
            stripLeadTaskEmbedChrome();
            notifyLeadTaskEmbedSize();
        }, 400);
    });
    @endif

    function openScheduleCallTaskModal() {
        document.getElementById('scheduleCallTaskModal').classList.remove('hidden');
        syncModalBodyLock();
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.querySelector('#scheduleCallTaskForm input[name="scheduled_date"]').value = tomorrow.toISOString().split('T')[0];
        document.querySelector('#scheduleCallTaskForm input[name="scheduled_time"]').value = '10:00';
    }

    function closeScheduleCallTaskModal() {
        document.getElementById('scheduleCallTaskModal').classList.add('hidden');
        document.getElementById('scheduleCallTaskForm').reset();
        syncModalBodyLock();
    }

    function openCompleteAsmTaskModal() {
        const modal = document.getElementById('completeAsmTaskModal');
        if (modal) {
            modal.classList.remove('hidden');
            syncModalBodyLock();
        }
    }

    function closeCompleteAsmTaskModal() {
        const modal = document.getElementById('completeAsmTaskModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        syncModalBodyLock();
    }

    function openTaskOutcomeModal(taskId, taskCategory = 'other') {
        setCurrentTaskId(taskId);
        currentTaskCategory = normalizeTaskCategory(taskCategory);
        selectedTaskOutcome = null;
        closeCompleteAsmTaskModal();
        if (currentTaskCategory === 'follow_up') {
            openFollowUpActionHubModal();
            return;
        }
        if (currentTaskCategory === 'meeting') {
            openMeetingActionHubModal();
            return;
        }
        if (currentTaskCategory === 'site_visit') {
            openVisitActionHubModal();
            return;
        }
        document.getElementById('verifyRejectPromptModal')?.classList.add('active');
        syncModalBodyLock();
    }

    function closeTaskOutcomeModal() {
        document.getElementById('verifyRejectPromptModal')?.classList.remove('active');
        syncModalBodyLock();
    }

    function openFollowUpActionHubModal() {
        document.getElementById('followUpActionHubModal')?.classList.add('active');
        toggleFollowUpMoreActions(false);
        syncModalBodyLock();
    }

    function closeFollowUpActionHubModal() {
        document.getElementById('followUpActionHubModal')?.classList.remove('active');
        toggleFollowUpMoreActions(false);
        syncModalBodyLock();
    }

    function openVisitActionHubModal() {
        document.getElementById('visitActionHubModal')?.classList.add('active');
        toggleVisitMoreActions(false);
        syncModalBodyLock();
    }

    function closeVisitActionHubModal() {
        document.getElementById('visitActionHubModal')?.classList.remove('active');
        toggleVisitMoreActions(false);
        syncModalBodyLock();
    }

    function openMeetingActionHubModal() {
        document.getElementById('meetingActionHubModal')?.classList.add('active');
        toggleMeetingMoreActions(false);
        syncModalBodyLock();
    }

    function closeMeetingActionHubModal() {
        document.getElementById('meetingActionHubModal')?.classList.remove('active');
        toggleMeetingMoreActions(false);
        syncModalBodyLock();
    }

    function toggleFollowUpMoreActions(forceState = null) {
        const panel = document.getElementById('followUpMoreActionsPanel');
        const icon = document.getElementById('followUpMoreChevron');
        if (!panel) {
            return;
        }

        const shouldOpen = typeof forceState === 'boolean'
            ? forceState
            : !panel.classList.contains('active');

        panel.classList.toggle('active', shouldOpen);
        if (icon) {
            icon.classList.toggle('fa-chevron-up', shouldOpen);
            icon.classList.toggle('fa-chevron-down', !shouldOpen);
        }
    }

    function toggleVisitMoreActions(forceState = null) {
        const panel = document.getElementById('visitMoreActionsPanel');
        const icon = document.getElementById('visitMoreActionsIcon');
        if (!panel) {
            return;
        }

        const shouldOpen = typeof forceState === 'boolean'
            ? forceState
            : !panel.classList.contains('active');

        panel.classList.toggle('active', shouldOpen);
        if (icon) {
            icon.classList.toggle('fa-chevron-up', shouldOpen);
            icon.classList.toggle('fa-chevron-down', !shouldOpen);
        }
    }

    function toggleMeetingMoreActions(forceState = null) {
        const panel = document.getElementById('meetingMoreActionsPanel');
        const icon = document.getElementById('meetingMoreActionsIcon');
        if (!panel) {
            return;
        }

        const shouldOpen = typeof forceState === 'boolean'
            ? forceState
            : !panel.classList.contains('active');

        panel.classList.toggle('active', shouldOpen);
        if (icon) {
            icon.classList.toggle('fa-chevron-up', shouldOpen);
            icon.classList.toggle('fa-chevron-down', !shouldOpen);
        }
    }

    async function completeCurrentTaskDirectly(successMessage = 'Follow-up completed successfully') {
        if (!window.currentTaskId) {
            throw new Error('Task ID not found');
        }

        const result = await apiCall(`/tasks/${window.currentTaskId}/complete`, {
            method: 'POST',
            body: JSON.stringify({})
        });

        if (!result?.success) {
            throw new Error(result?.message || result?.error || 'Failed to complete task');
        }

        closeCompleteAsmTaskModal();
        setCurrentTaskId(null);
        currentTaskCategory = 'other';
        selectedTaskOutcome = null;
        pendingFollowUpWorkflowAction = null;
        pendingVisitWorkflowAction = null;
        pendingMeetingWorkflowAction = null;
        setCurrentTaskContext({});
        showLeadDetailAlert(result.message || successMessage, 'success');
        setTimeout(() => window.location.reload(), 500);
        return result;
    }

    function configureOutcomeDateTimeModal(outcome, options = {}) {
        selectedTaskOutcome = outcome;
        const title = document.getElementById('outcomeDateTimeModalTitle');
        const text = document.getElementById('outcomeDateTimeModalText');
        const confirmBtn = document.getElementById('outcomeDateTimeConfirmBtn');

        if (title) {
            title.textContent = options.title || (outcome === 'follow_up' ? 'Schedule Follow Up' : 'Select Retry Time for CNP');
        }
        if (text) {
            text.textContent = options.text || (outcome === 'follow_up'
                ? 'Choose when the next follow-up call should happen:'
                : 'Choose when to retry this call:');
        }
        if (confirmBtn) {
            confirmBtn.textContent = options.confirmText || (outcome === 'follow_up' ? 'Schedule Follow Up' : 'Confirm CNP');
            confirmBtn.style.background = options.color || (outcome === 'follow_up' ? '#2563eb' : '#f59e0b');
        }
    }

    async function handleFollowUpHubAction(action) {
        if (!window.currentTaskId) {
            showLeadDetailAlert('Task ID not found', 'error');
            return;
        }

        if (action === 'reschedule') {
            closeFollowUpActionHubModal();
            configureOutcomeDateTimeModal('follow_up');
            openCnpTimeSelectionModal();
            showCustomTimePicker();
            return;
        }

        if (action === 'complete') {
            closeFollowUpActionHubModal();
            try {
                await completeCurrentTaskDirectly('Follow-up completed successfully');
            } catch (error) {
                showLeadDetailAlert(error.message || 'Failed to complete follow-up task', 'error');
            }
            return;
        }

        if (action === 'edit_requirement') {
            closeFollowUpActionHubModal();
            await openManagerLeadRequirementFormModal(window.currentTaskId);
            return;
        }

        if (action === 'meeting' || action === 'visit') {
            pendingFollowUpWorkflowAction = action;
            closeFollowUpActionHubModal();
            if (action === 'meeting') {
                await openMeetingModal();
            } else {
                openSiteVisitModal();
            }
            return;
        }

        if (action === 'interested') {
            closeFollowUpActionHubModal();
            window.managerTaskOutcomeContext = { outcome: 'interested' };
            await openManagerLeadRequirementFormModal(window.currentTaskId);
            return;
        }

        if (action === 'not_interested' || action === 'junk') {
            closeFollowUpActionHubModal();
            openOutcomeRemarkModal(action);
            return;
        }

        if (action === 'cnp') {
            closeFollowUpActionHubModal();
            configureOutcomeDateTimeModal('cnp');
            openCnpTimeSelectionModal();
        }
    }

    async function handleVisitHubAction(action) {
        if (!window.currentTaskId) {
            showLeadDetailAlert('Task ID not found', 'error');
            return;
        }

        if (action === 'complete') {
            closeVisitActionHubModal();
            openLeadDetailCompleteVisitModal('visited');
            return;
        }

        if (action === 'reschedule') {
            closeVisitActionHubModal();
            openLeadDetailCompleteVisitModal('reschedule');
            return;
        }

        if (action === 'edit_requirement') {
            closeVisitActionHubModal();
            openLeadRequirementsModal(LEAD_ID);
            return;
        }

        if (action === 'follow_up') {
            closeVisitActionHubModal();
            openLeadDetailCompleteVisitModal('follow_up_needed');
            return;
        }

        if (action === 'not_available' || action === 'cancelled') {
            closeVisitActionHubModal();
            openLeadDetailCompleteVisitModal(action === 'not_available' ? 'customer_not_available' : 'cancelled');
        }
    }

    function openLeadDetailCompleteMeetingModal() {
        const visitProjectInput = document.getElementById('leadDetailMeetingVisitProject');
        if (visitProjectInput && !visitProjectInput.value) {
            visitProjectInput.value = (LEAD_INTERESTED_PROJECTS && LEAD_INTERESTED_PROJECTS[0]) ? LEAD_INTERESTED_PROJECTS[0] : '';
        }
        toggleLeadDetailMeetingOutcomeFields();
        document.getElementById('leadDetailCompleteMeetingModal')?.classList.add('active');
        syncModalBodyLock();
    }

    function closeLeadDetailCompleteMeetingModal() {
        document.getElementById('leadDetailCompleteMeetingModal')?.classList.remove('active');
        document.getElementById('leadDetailCompleteMeetingForm')?.reset();
        const preview = document.getElementById('leadDetailMeetingProofPreview');
        if (preview) {
            preview.innerHTML = '';
        }
        toggleLeadDetailMeetingOutcomeFields();
        syncModalBodyLock();
    }

    function openLeadDetailCompleteVisitModal(outcome = 'visited') {
        setLeadDetailVisitLockedOutcome(outcome);
        toggleLeadDetailVisitOutcomeFields();
        if (leadDetailVisitLockedOutcome === 'visited') {
            prefillLeadDetailVisitedProjects();
        }
        document.getElementById('leadDetailCompleteVisitModal')?.classList.add('active');
        syncModalBodyLock();
    }

    function closeLeadDetailCompleteVisitModal() {
        document.getElementById('leadDetailCompleteVisitModal')?.classList.remove('active');
        document.getElementById('leadDetailCompleteVisitForm')?.reset();
        const preview = document.getElementById('leadDetailVisitProofPreview');
        if (preview) {
            preview.innerHTML = '';
        }
        document.querySelectorAll('.leadDetailVisitPropertyType').forEach((input) => {
            input.checked = false;
        });
        setLeadDetailVisitLockedOutcome('visited');
        toggleLeadDetailVisitOutcomeFields();
        syncModalBodyLock();
    }

    function backToVisitActionHub() {
        closeLeadDetailCompleteVisitModal();
        openVisitActionHubModal();
    }

    function openLeadDetailRescheduleMeetingModal() {
        const input = document.getElementById('leadDetailRescheduleMeetingAt');
        if (input) {
            const minDateTime = new Date();
            minDateTime.setMinutes(minDateTime.getMinutes() - minDateTime.getTimezoneOffset());
            input.min = minDateTime.toISOString().slice(0, 16);
        }
        document.getElementById('leadDetailRescheduleMeetingModal')?.classList.add('active');
        syncModalBodyLock();
    }

    function closeLeadDetailRescheduleMeetingModal() {
        document.getElementById('leadDetailRescheduleMeetingModal')?.classList.remove('active');
        document.getElementById('leadDetailRescheduleMeetingForm')?.reset();
        syncModalBodyLock();
    }

    function handleLeadDetailMeetingProofPhotosChange(event) {
        const files = event.target?.files || [];
        const preview = document.getElementById('leadDetailMeetingProofPreview');
        if (!preview) {
            return;
        }

        preview.innerHTML = '';
        Array.from(files).forEach((file) => {
            const reader = new FileReader();
            reader.onload = function(loadEvent) {
                const img = document.createElement('img');
                img.src = loadEvent.target?.result;
                img.style.width = '88px';
                img.style.height = '88px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '14px';
                img.style.border = '1px solid #dbe3ef';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    }

    function handleLeadDetailVisitProofPhotosChange(event) {
        const files = event.target?.files || [];
        const preview = document.getElementById('leadDetailVisitProofPreview');
        if (!preview) {
            return;
        }

        preview.innerHTML = '';
        Array.from(files).forEach((file) => {
            const reader = new FileReader();
            reader.onload = function(loadEvent) {
                const img = document.createElement('img');
                img.src = loadEvent.target?.result;
                img.style.width = '88px';
                img.style.height = '88px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '14px';
                img.style.border = '1px solid #dbe3ef';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    }

    function toggleLeadDetailMeetingOutcomeFields() {
        const outcome = document.getElementById('leadDetailMeetingOutcome')?.value || '';
        document.getElementById('leadDetailMeetingVisitFields')?.classList.toggle('hidden', outcome !== 'schedule_visit');
        document.getElementById('leadDetailMeetingFollowUpFields')?.classList.toggle('hidden', outcome !== 'schedule_follow_up');
        document.getElementById('leadDetailMeetingRemarkFields')?.classList.toggle('hidden', !['not_interested', 'junk'].includes(outcome));
    }

    function setLeadDetailVisitLockedOutcome(outcome = 'visited') {
        const allowedOutcomes = ['visited', 'reschedule', 'customer_not_available', 'cancelled', 'follow_up_needed'];
        leadDetailVisitLockedOutcome = allowedOutcomes.includes(outcome) ? outcome : 'visited';

        const outcomeInput = document.getElementById('leadDetailVisitOutcome');
        if (outcomeInput) {
            outcomeInput.value = leadDetailVisitLockedOutcome;
        }
    }

    function toggleLeadDetailVisitOutcomeFields() {
        const outcome = leadDetailVisitLockedOutcome || document.getElementById('leadDetailVisitOutcome')?.value || 'visited';
        const config = {
            visited: {
                title: 'Complete Visit',
                subtitle: 'Upload visit proof and complete this site visit.',
                submit: 'Complete Visit',
            },
            reschedule: {
                title: 'Reschedule Visit',
                subtitle: 'Pick the new visit date and add the reason.',
                submit: 'Reschedule Visit',
            },
            customer_not_available: {
                title: 'Customer Not Available',
                subtitle: 'Save the failed visit attempt with a clear remark. The task stays open.',
                submit: 'Save Remark',
            },
            cancelled: {
                title: 'Cancel Visit',
                subtitle: 'Cancel this scheduled visit with a mandatory reason.',
                submit: 'Cancel Visit',
            },
            follow_up_needed: {
                title: 'Schedule Follow-up',
                subtitle: 'Create the next follow-up task from this visit flow.',
                submit: 'Schedule Follow-up',
            },
        }[outcome] || {
            title: 'Complete Visit',
            subtitle: 'Upload visit proof and complete this site visit.',
            submit: 'Complete Visit',
        };

        const title = document.getElementById('leadDetailVisitModalTitle');
        const subtitle = document.getElementById('leadDetailVisitModalSubtitle');
        const submitButton = document.getElementById('leadDetailVisitSubmitButton');
        if (title) title.textContent = config.title;
        if (subtitle) subtitle.textContent = config.subtitle;
        if (submitButton) submitButton.textContent = config.submit;

        document.getElementById('leadDetailVisitProofFields')?.classList.toggle('hidden', outcome !== 'visited');
        document.getElementById('leadDetailVisitCompletionFields')?.classList.toggle('hidden', outcome !== 'visited');
        document.getElementById('leadDetailVisitRescheduleFields')?.classList.toggle('hidden', outcome !== 'reschedule');
        document.getElementById('leadDetailVisitFollowUpFields')?.classList.toggle('hidden', outcome !== 'follow_up_needed');
        document.getElementById('leadDetailVisitRemarkFields')?.classList.toggle('hidden', !['customer_not_available', 'cancelled'].includes(outcome));
    }

    function formatLocalDateTimeForApi(dateValue) {
        if (!(dateValue instanceof Date) || Number.isNaN(dateValue.getTime())) {
            throw new Error('Invalid date and time selected');
        }

        const year = dateValue.getFullYear();
        const month = String(dateValue.getMonth() + 1).padStart(2, '0');
        const day = String(dateValue.getDate()).padStart(2, '0');
        const hours = String(dateValue.getHours()).padStart(2, '0');
        const minutes = String(dateValue.getMinutes()).padStart(2, '0');
        const seconds = String(dateValue.getSeconds()).padStart(2, '0');

        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    function combineDateAndTime(dateValue, timeValue, label) {
        if (!dateValue || !timeValue) {
            throw new Error(`${label} date and time are required`);
        }

        const combined = new Date(`${dateValue}T${timeValue}`);
        if (Number.isNaN(combined.getTime())) {
            throw new Error(`${label} date and time are invalid`);
        }

        if (!window.ALLOW_PRIVILEGED_PAST_SCHEDULING && combined <= new Date()) {
            throw new Error(`${label} must be in the future`);
        }

        return formatLocalDateTimeForApi(combined);
    }

    function buildLeadDetailVisitPayloadFromMeetingCompletion() {
        return {
            lead_id: LEAD_ID,
            prospect_id: null,
            assigned_to: null,
            property_name: document.getElementById('leadDetailMeetingVisitProject')?.value?.trim() || null,
            property_address: document.getElementById('leadDetailMeetingVisitLocation')?.value?.trim() || null,
            scheduled_at: combineDateAndTime(
                document.getElementById('leadDetailMeetingVisitDate')?.value,
                document.getElementById('leadDetailMeetingVisitTime')?.value,
                'Visit'
            ),
            visit_notes: document.getElementById('leadDetailMeetingVisitRemark')?.value?.trim() || null,
            customer_name: @json($lead->name),
            phone: @json($lead->phone),
            employee: '',
            occupation: null,
            date_of_visit: document.getElementById('leadDetailMeetingVisitDate')?.value || null,
            project: document.getElementById('leadDetailMeetingVisitProject')?.value?.trim() || null,
            budget_range: null,
            team_leader: null,
            property_type: 'Just Exploring',
            payment_mode: 'Self Fund',
            tentative_period: 'Within 1 Month',
            lead_type: 'Meeting',
        };
    }

    function buildLeadDetailMeetingPayloadFromVisitCompletion() {
        const mode = document.getElementById('leadDetailVisitMeetingMode')?.value || 'online';
        return {
            lead_id: LEAD_ID,
            meeting_sequence: 1,
            scheduled_at: combineDateAndTime(
                document.getElementById('leadDetailVisitMeetingDate')?.value,
                document.getElementById('leadDetailVisitMeetingTime')?.value,
                'Meeting'
            ),
            meeting_mode: mode,
            meeting_link: mode === 'online' ? (document.getElementById('leadDetailVisitMeetingLink')?.value?.trim() || null) : null,
            location: mode === 'offline' ? (document.getElementById('leadDetailVisitMeetingLocation')?.value?.trim() || null) : null,
            reminder_enabled: true,
            reminder_minutes: 5,
            meeting_notes: document.getElementById('leadDetailVisitMeetingRemark')?.value?.trim() || null,
        };
    }

    async function createLeadDetailMeetingRecord(payload) {
        const response = await fetch(`${window.API_BASE_URL}/sales-manager/meetings/quick-schedule-with-reminder`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${window.API_TOKEN}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const result = await response.json();
        if (!response.ok || result?.success === false) {
            throw new Error(result?.message || 'Failed to schedule meeting');
        }

        return result;
    }

    async function createLeadDetailSiteVisitRecord(payload) {
        const response = await fetch(`${window.API_BASE_URL}/sales-manager/site-visits`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${window.API_TOKEN}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const result = await response.json();
        if (!response.ok || result?.success === false) {
            throw new Error(result?.message || 'Failed to schedule site visit');
        }

        return result;
    }

    async function routeLeadDetailCompletionInterestedFlow(successMessage) {
        closeLeadDetailCompleteMeetingModal();
        closeLeadDetailCompleteVisitModal();
        showLeadDetailAlert(successMessage, 'success');
        window.managerTaskOutcomeContext = { outcome: 'interested' };
        await openManagerLeadRequirementFormModal(window.currentTaskId);
    }

    async function handleMeetingHubAction(action) {
        if (!window.currentTaskId) {
            showLeadDetailAlert('Task ID not found', 'error');
            return;
        }

        if (action === 'complete') {
            closeMeetingActionHubModal();
            openLeadDetailCompleteMeetingModal();
            return;
        }

        if (action === 'reschedule') {
            pendingMeetingWorkflowAction = 'reschedule';
            closeMeetingActionHubModal();
            openLeadDetailRescheduleMeetingModal();
            return;
        }

        if (action === 'edit_requirement') {
            closeMeetingActionHubModal();
            openLeadRequirementsModal(LEAD_ID);
            return;
        }

        if (action === 'visit') {
            pendingMeetingWorkflowAction = 'visit';
            closeMeetingActionHubModal();
            openSiteVisitModal();
            return;
        }

        if (action === 'follow_up') {
            pendingMeetingWorkflowAction = 'follow_up';
            closeMeetingActionHubModal();
            configureOutcomeDateTimeModal('follow_up');
            openCnpTimeSelectionModal();
            showCustomTimePicker();
            return;
        }

        if (action === 'interested') {
            closeMeetingActionHubModal();
            window.managerTaskOutcomeContext = { outcome: 'interested' };
            await openManagerLeadRequirementFormModal(window.currentTaskId);
            return;
        }

        if (action === 'not_interested' || action === 'junk') {
            closeMeetingActionHubModal();
            openOutcomeRemarkModal(action);
        }
    }

    async function submitLeadDetailCompleteMeeting(event) {
        event.preventDefault();

        const meetingId = resolveMeetingIdFromTaskContext();
        if (!meetingId) {
            showLeadDetailAlert('Meeting ID not found for this task', 'error');
            return;
        }

        const photosInput = document.getElementById('leadDetailMeetingProofPhotos');
        if (!photosInput?.files?.length) {
            showLeadDetailAlert('Please upload at least one proof photo', 'warning');
            return;
        }

        const formData = new FormData();
        Array.from(photosInput.files).forEach((file) => formData.append('proof_photos[]', file));

        const feedback = document.getElementById('leadDetailMeetingFeedback')?.value?.trim();
        const rating = document.getElementById('leadDetailMeetingRating')?.value;
        const notes = document.getElementById('leadDetailMeetingNotes')?.value?.trim();
        const outcome = document.getElementById('leadDetailMeetingOutcome')?.value || '';

        if (feedback) formData.append('feedback', feedback);
        if (rating) formData.append('rating', rating);
        if (notes) formData.append('notes', notes);
        formData.append('workflow', 'meeting_complete');
        formData.append('meeting_id', meetingId);
        formData.append('outcome', outcome);

        if (!outcome) {
            showLeadDetailAlert('Please select a meeting outcome', 'warning');
            return;
        }

        try {
            if (outcome === 'schedule_visit') {
                formData.append('scheduled_at', combineDateAndTime(
                    document.getElementById('leadDetailMeetingVisitDate')?.value,
                    document.getElementById('leadDetailMeetingVisitTime')?.value,
                    'Visit'
                ));
                formData.append('project', document.getElementById('leadDetailMeetingVisitProject')?.value?.trim() || '');
                formData.append('location', document.getElementById('leadDetailMeetingVisitLocation')?.value?.trim() || '');
                formData.append('remark', document.getElementById('leadDetailMeetingVisitRemark')?.value?.trim() || notes || '');
                formData.append('reminder_enabled', document.getElementById('leadDetailMeetingVisitReminder')?.checked ? '1' : '0');
            }

            if (outcome === 'schedule_follow_up') {
                formData.append('scheduled_at', combineDateAndTime(
                    document.getElementById('leadDetailMeetingFollowUpDate')?.value,
                    document.getElementById('leadDetailMeetingFollowUpTime')?.value,
                    'Follow up'
                ));
                formData.append('remark', document.getElementById('leadDetailMeetingFollowUpRemark')?.value?.trim() || notes || '');
            }

            if (outcome === 'not_interested' || outcome === 'junk') {
                const remark = document.getElementById('leadDetailMeetingOutcomeRemark')?.value?.trim();
                if (!remark) {
                    throw new Error('Remark is required for this outcome');
                }
                formData.append('remark', remark);
            }

            const result = await submitTaskWorkflowFormData(window.currentTaskId, formData);

            if (outcome === 'interested') {
                closeLeadDetailCompleteMeetingModal();
                showLeadDetailAlert(result.message || 'Meeting completed successfully', 'success');
                window.managerTaskOutcomeContext = { outcome: 'interested' };
                await openManagerLeadRequirementFormModal(window.currentTaskId);
                return;
            }

            closeLeadDetailCompleteMeetingModal();
            showLeadDetailAlert(result.message || 'Meeting workflow updated successfully', 'success');
            setTimeout(() => window.location.reload(), 500);
        } catch (error) {
            showLeadDetailAlert(error.message || 'Failed to complete meeting', 'error');
        }
    }

    async function submitLeadDetailCompleteVisit(event) {
        event.preventDefault();

        const siteVisitId = resolveSiteVisitIdFromTaskContext();
        if (!siteVisitId) {
            showLeadDetailAlert('Site visit ID not found for this task', 'error');
            return;
        }

        const outcome = leadDetailVisitLockedOutcome || document.getElementById('leadDetailVisitOutcome')?.value || '';
        if (!outcome) {
            showLeadDetailAlert('Please select a visit outcome', 'warning');
            return;
        }

        const photosInput = document.getElementById('leadDetailVisitProofPhotos');
        if (outcome === 'visited' && !photosInput?.files?.length) {
            showLeadDetailAlert('Please upload at least one proof photo', 'warning');
            return;
        }

        const formData = new FormData();
        if (outcome === 'visited') {
            Array.from(photosInput.files).forEach((file) => formData.append('proof_photos[]', file));
        }

        const feedback = document.getElementById('leadDetailVisitFeedback')?.value?.trim();
        const rating = document.getElementById('leadDetailVisitRating')?.value;
        const notes = document.getElementById('leadDetailVisitNotes')?.value?.trim();
        const visitedProjects = document.getElementById('leadDetailVisitVisitedProjects')?.value?.trim();
        const visitedPropertyTypes = Array.from(document.querySelectorAll('.leadDetailVisitPropertyType:checked'))
            .map((input) => input.value);
        const tentativeClosingTime = document.getElementById('leadDetailVisitTentativeClosingTime')?.value;

        if (feedback) formData.append('feedback', feedback);
        if (rating) formData.append('rating', rating);
        if (notes) formData.append('notes', notes);
        formData.append('site_visit_id', siteVisitId);
        if (visitedProjects) formData.append('visited_projects', visitedProjects);
        if (outcome === 'visited') {
            visitedPropertyTypes.forEach((type) => formData.append('visited_property_types[]', type));
        }
        if (tentativeClosingTime) formData.append('tentative_closing_time', tentativeClosingTime);

        try {
            if (outcome === 'reschedule') {
                formData.append('workflow', 'visit_reschedule');
                formData.append('scheduled_at', combineDateAndTime(
                    document.getElementById('leadDetailVisitRescheduleDate')?.value,
                    document.getElementById('leadDetailVisitRescheduleTime')?.value,
                    'Visit'
                ));
                const reason = document.getElementById('leadDetailVisitRescheduleRemark')?.value?.trim() || '';
                if (!reason) {
                    throw new Error('Reason is required for rescheduling');
                }
                formData.append('remark', reason);
            } else {
                formData.append('workflow', 'visit_complete');
                formData.append('outcome', outcome);
            }

            if (outcome === 'follow_up_needed') {
                formData.append('scheduled_at', combineDateAndTime(
                    document.getElementById('leadDetailVisitFollowUpDate')?.value,
                    document.getElementById('leadDetailVisitFollowUpTime')?.value,
                    'Follow up'
                ));
                formData.append('remark', document.getElementById('leadDetailVisitFollowUpRemark')?.value?.trim() || notes || '');
            }

            if (outcome === 'customer_not_available' || outcome === 'cancelled') {
                const remark = document.getElementById('leadDetailVisitOutcomeRemark')?.value?.trim();
                if (!remark) {
                    throw new Error('Remark is required for this outcome');
                }
                formData.append('remark', remark);
            }

            const result = await submitTaskWorkflowFormData(window.currentTaskId, formData);

            closeLeadDetailCompleteVisitModal();
            showLeadDetailAlert(result.message || 'Visit workflow updated successfully', 'success');
            setTimeout(() => window.location.reload(), 500);
        } catch (error) {
            showLeadDetailAlert(error.message || 'Failed to complete visit', 'error');
        }
    }

    async function submitLeadDetailRescheduleMeeting(event) {
        event.preventDefault();

        const meetingId = resolveMeetingIdFromTaskContext();
        if (!meetingId) {
            showLeadDetailAlert('Meeting ID not found for this task', 'error');
            return;
        }

        const scheduledAt = document.getElementById('leadDetailRescheduleMeetingAt')?.value;
        const reason = document.getElementById('leadDetailRescheduleMeetingReason')?.value?.trim();

        if (!scheduledAt) {
            showLeadDetailAlert('Please select a new scheduled date and time', 'warning');
            return;
        }

        if (!reason) {
            showLeadDetailAlert('Please provide a reason for rescheduling', 'warning');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('workflow', 'meeting_reschedule');
            formData.append('meeting_id', meetingId);
            formData.append('scheduled_at', scheduledAt);
            formData.append('remark', reason);

            const result = await submitTaskWorkflowFormData(window.currentTaskId, formData);

            closeLeadDetailRescheduleMeetingModal();
            showLeadDetailAlert(result.message || 'Meeting rescheduled successfully', 'success');
            setTimeout(() => window.location.reload(), 500);
        } catch (error) {
            showLeadDetailAlert(error.message || 'Failed to reschedule meeting', 'error');
        } finally {
            pendingMeetingWorkflowAction = null;
        }
    }

    async function openManagerLeadRequirementFormModal(taskId) {
        const modal = document.getElementById('managerLeadRequirementFormModal');
        const container = document.getElementById('managerLeadTaskFormContainer');

        if (!modal || !container) {
            showLeadDetailAlert('Requirement form modal not available', 'error');
            return;
        }

        modal.dataset.formContext = 'task';
        container.dataset.formContext = 'task';
        modal.classList.add('active');
        syncModalBodyLock();
        modal.scrollTop = 0;
        container.scrollTop = 0;
        container.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner" style="display: inline-block;"></div><p style="margin-top: 15px; color: #666;">Loading form...</p></div>';

        try {
            const result = await apiCall(`/tasks/${taskId}/lead-requirement-form`);

            if (result && result.success) {
                if (typeof window.renderManagerLeadForm === 'function') {
                    window.renderManagerLeadForm(result);
                } else {
                    showLeadDetailAlert('Requirement form renderer not available', 'error');
                    closeManagerLeadRequirementFormModal();
                }
            } else {
                showLeadDetailAlert('Failed to load form: ' + (result?.error || result?.message || 'Unknown error'), 'error');
                closeManagerLeadRequirementFormModal();
            }
        } catch (error) {
            console.error('Error loading form:', error);
            showLeadDetailAlert('Error loading form: ' + error.message, 'error');
            closeManagerLeadRequirementFormModal();
        }
    }

    function closeManagerLeadRequirementFormModal() {
        const modal = document.getElementById('managerLeadRequirementFormModal');
        const container = document.getElementById('managerLeadTaskFormContainer');

        modal?.classList.remove('active');
        if (modal) {
            delete modal.dataset.formContext;
        }
        if (container) {
            delete container.dataset.formContext;
            container.innerHTML = '';
        }
        window.managerActiveLeadRequirementsFormConfig = null;
        window.managerActiveLeadOutputFormConfig = null;
        window.managerTaskOutcomeContext = null;
        setCurrentTaskId(null);
        setCurrentTaskContext({});
        syncModalBodyLock();
    }

    function cancelManagerLeadRequirementForm() {
        closeManagerLeadRequirementFormModal();
    }

    function cancelVerifyRejectPrompt() {
        closeTaskOutcomeModal();
        currentTaskCategory = 'other';
        setCurrentTaskId(null);
        setCurrentTaskContext({});
    }

    function openOutcomeRemarkModal(outcome) {
        selectedTaskOutcome = outcome;
        const modal = document.getElementById('rejectReasonModal');
        const title = document.getElementById('rejectReasonModalTitle');
        const textarea = document.getElementById('rejectReasonInput');
        const submitBtn = document.getElementById('rejectReasonSubmitBtn');

        if (title) {
            title.textContent = outcome === 'junk'
                ? 'Mark Lead as Junk'
                : outcome === 'not_interested'
                    ? 'Mark Lead as Not Interested'
                    : 'Add CNP Remark';
        }

        if (textarea) {
            textarea.value = '';
            textarea.placeholder = outcome === 'junk'
                ? 'Add junk reason or context...'
                : outcome === 'not_interested'
                    ? 'Add not interested context...'
                    : 'Add CNP context...';
        }

        if (submitBtn) {
            submitBtn.textContent = outcome === 'junk'
                ? 'Mark Junk'
                : outcome === 'not_interested'
                    ? 'Continue'
                    : 'Confirm CNP';
        }

        modal?.classList.add('active');
        syncModalBodyLock();
    }

    function closeJunkRemarkModal() {
        document.getElementById('rejectReasonModal')?.classList.remove('active');
        const input = document.getElementById('rejectReasonInput');
        if (input) {
            input.value = '';
        }
        syncModalBodyLock();
    }

    function cancelJunkRemarkModal() {
        closeJunkRemarkModal();
    }

    function openCnpTimeSelectionModal() {
        const modal = document.getElementById('cnpTimeSelectionModal');
        if (!modal) return;

        selectedCnpMinutes = null;
        isCustomTimeSelected = false;
        document.querySelectorAll('.time-option-btn').forEach((button) => button.classList.remove('active'));
        document.getElementById('customTimePickerContainer')?.style.setProperty('display', 'none');
        document.getElementById('selectedTimeDisplay')?.style.setProperty('display', 'none');
        const remarkField = document.getElementById('outcomeDateTimeRemark');
        if (remarkField) {
            remarkField.value = '';
        }
        const dateInput = document.getElementById('cnpCustomDate');
        if (dateInput) {
            dateInput.min = new Date().toISOString().split('T')[0];
            dateInput.value = '';
        }
        const timeInput = document.getElementById('cnpCustomTime');
        if (timeInput) {
            timeInput.value = '';
        }

        modal.classList.add('active');
        syncModalBodyLock();
    }

    function closeCnpTimeSelectionModal() {
        document.getElementById('cnpTimeSelectionModal')?.classList.remove('active');
        selectedCnpMinutes = null;
        isCustomTimeSelected = false;
        syncModalBodyLock();
    }

    function cancelOutcomeDateTimeModal() {
        closeCnpTimeSelectionModal();
    }

    function updateSelectedTimeDisplay(text) {
        const display = document.getElementById('selectedTimeDisplay');
        const textNode = document.getElementById('selectedTimeText');
        if (display && textNode) {
            textNode.textContent = text;
            display.style.display = 'block';
        }
    }

    function selectCnpTime(minutes, event) {
        selectedCnpMinutes = minutes;
        isCustomTimeSelected = false;
        document.querySelectorAll('.time-option-btn').forEach((button) => button.classList.remove('active'));
        event?.currentTarget?.classList.add('active');
        document.getElementById('customTimePickerContainer')?.style.setProperty('display', 'none');
        const prefix = selectedTaskOutcome === 'follow_up' ? 'Follow up in' : 'Retry call in';
        updateSelectedTimeDisplay(`${prefix} ${minutes} minutes`);
    }

    function showCustomTimePicker() {
        isCustomTimeSelected = true;
        selectedCnpMinutes = null;
        document.querySelectorAll('.time-option-btn').forEach((button) => button.classList.remove('active'));
        document.getElementById('customTimeOptionBtn')?.classList.add('active');
        document.getElementById('customTimePickerContainer')?.style.setProperty('display', 'block');
        document.getElementById('selectedTimeDisplay')?.style.setProperty('display', 'none');
    }

    async function selectTaskOutcome(outcome) {
        closeTaskOutcomeModal();

        if (!window.currentTaskId) {
            showLeadDetailAlert('Task ID not found', 'error');
            return;
        }

        if (outcome === 'interested') {
            window.managerTaskOutcomeContext = { outcome: 'interested' };
            await openManagerLeadRequirementFormModal(window.currentTaskId);
            return;
        }

        if (outcome === 'not_interested') {
            openOutcomeRemarkModal('not_interested');
            return;
        }

        if (outcome === 'follow_up') {
            selectedTaskOutcome = 'follow_up';
            openFollowUpActionHubModal();
            return;
        }

        if (outcome === 'cnp') {
            if (!managerUsesUnifiedCnpScheduling && currentTaskCategory === 'fresh_lead') {
                openOutcomeRemarkModal('cnp');
                return;
            }

            configureOutcomeDateTimeModal('cnp');
            openCnpTimeSelectionModal();
            return;
        }

        if (outcome === 'junk') {
            openOutcomeRemarkModal('junk');
        }
    }

    async function submitTaskOutcome(outcome, extraData = {}, closeOutcomeModal = true) {
        if (!window.currentTaskId) {
            showLeadDetailAlert('Task ID not found', 'error');
            return null;
        }

        const result = await apiCall(`/tasks/${window.currentTaskId}/outcome`, {
            method: 'POST',
            body: JSON.stringify({
                outcome,
                ...extraData
            })
        });

        if (!result || !result.success) {
            showLeadDetailAlert(getLeadDetailApiErrorMessage(result, 'Failed to update task outcome'), 'error');
            return result;
        }

        if (closeOutcomeModal) {
            closeTaskOutcomeModal();
        }

        closeCompleteAsmTaskModal();
        setCurrentTaskId(null);
        currentTaskCategory = 'other';
        selectedTaskOutcome = null;
        pendingFollowUpWorkflowAction = null;
        pendingVisitWorkflowAction = null;
        pendingMeetingWorkflowAction = null;
        setCurrentTaskContext({});
        showLeadDetailAlert(result.message || 'Outcome submitted successfully', 'success');
        const shouldRedirectToTasks = ['not_interested', 'junk'].includes((outcome || '').toLowerCase());
        setTimeout(() => {
            if (shouldRedirectToTasks) {
                window.location.href = @json($taskSectionUrl);
                return;
            }

            window.location.reload();
        }, 500);
        return result;
    }

    async function confirmOutcomeDateTimeSelection() {
        if (!window.currentTaskId) {
            showLeadDetailAlert('Task ID not found', 'error');
            return;
        }

        let nextDateTime = null;

        if (isCustomTimeSelected) {
            const date = document.getElementById('cnpCustomDate')?.value;
            const time = document.getElementById('cnpCustomTime')?.value;

            if (!date || !time) {
                showLeadDetailAlert('Please select both date and time', 'warning');
                return;
            }

            const selectedDateTime = new Date(`${date}T${time}`);
            if (selectedDateTime <= new Date()) {
                showLeadDetailAlert('Please select a future date and time', 'warning');
                return;
            }

            nextDateTime = formatLocalDateTimeForApi(selectedDateTime);
            const customPrefix = selectedTaskOutcome === 'follow_up' ? 'Follow up on' : 'Retry call on';
            updateSelectedTimeDisplay(`${customPrefix} ${selectedDateTime.toLocaleString()}`);
        } else if (selectedCnpMinutes !== null) {
            nextDateTime = formatLocalDateTimeForApi(new Date(Date.now() + (selectedCnpMinutes * 60 * 1000)));
        } else {
            showLeadDetailAlert('Please select a time option', 'warning');
            return;
        }

        const result = await submitTaskOutcome(selectedTaskOutcome, {
            next_datetime: nextDateTime,
            remark: document.getElementById('outcomeDateTimeRemark')?.value.trim() || ''
        }, false);

        if (result?.success) {
            closeCnpTimeSelectionModal();
        }
    }

    async function submitOutcomeRemark() {
        const remark = document.getElementById('rejectReasonInput')?.value.trim() || '';
        const outcome = ['junk', 'not_interested', 'cnp'].includes(selectedTaskOutcome)
            ? selectedTaskOutcome
            : 'junk';

        const result = await submitTaskOutcome(outcome, { remark }, false);
        if (result?.success) {
            closeJunkRemarkModal();
            if (outcome === 'cnp') {
                currentTaskCategory = 'other';
            }
        }
    }

    function openOwnerTransferModal() {
        if (!CAN_TRANSFER_OWNER) return;
        const modal = document.getElementById('ownerTransferModal');
        if (modal) {
            modal.classList.remove('hidden');
            syncModalBodyLock();
        }
    }

    function closeOwnerTransferModal() {
        const modal = document.getElementById('ownerTransferModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        const form = document.getElementById('ownerTransferForm');
        if (form) {
            form.reset();
            const createCheckbox = document.getElementById('ownerTransferCreateCallingTask');
            if (createCheckbox) {
                createCheckbox.checked = true;
            }
        }
        syncModalBodyLock();
    }

    // Form submission functions
    async function submitFollowup(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = {
            lead_id: LEAD_ID,
            type: formData.get('type'),
            notes: formData.get('notes'),
            scheduled_at: combineDateAndTime(
                formData.get('scheduled_date'),
                formData.get('scheduled_time'),
                'Follow up'
            ),
        };

        try {
            const response = await fetch(`${window.API_BASE_URL}/follow-ups`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${window.API_TOKEN}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            });

            // Check if response is JSON before parsing
            const contentType = response.headers.get('content-type');
            let result;
            
            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                alert('Server error: Invalid response format. Please try again.');
                return;
            }

            if (response.ok) {
                alert('Follow-up scheduled successfully!');
                closeFollowupModal();
                location.reload(); // Reload to show in timeline
            } else {
                let errorMessage = result.message || 'Failed to schedule follow-up';
                if (result.errors) {
                    errorMessage += '\n\n' + Object.values(result.errors).flat().join('\n');
                }
                alert(errorMessage);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while scheduling the follow-up');
        }
    }

    async function submitSiteVisit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        updateSiteVisitProjectHiddenInput();
        const scheduledAtValue = String(formData.get('scheduled_at') || '').trim();
        const projectName = getSiteVisitSelectedProjects().join(', ')
            || document.getElementById('siteVisitProjectInput').value.trim()
            || null;
        if (!projectName) {
            showLeadDetailAlert('Please select or type a project', 'warning');
            return;
        }

        if (!scheduledAtValue) {
            showLeadDetailAlert('Please select a scheduled date and time', 'warning');
            return;
        }

        const scheduledAt = new Date(scheduledAtValue);
        if (Number.isNaN(scheduledAt.getTime())) {
            showLeadDetailAlert('Scheduled date and time is invalid', 'warning');
            return;
        }

        if (!window.ALLOW_PRIVILEGED_PAST_SCHEDULING && scheduledAt <= new Date()) {
            showLeadDetailAlert('Scheduled date and time must be in the future', 'warning');
            return;
        }

        const data = {
            lead_id: LEAD_ID,
            scheduled_at: formatLocalDateTimeForApi(scheduledAt),
            project: projectName,
            property_name: projectName,
            property_address: null,
            visit_notes: null,
            reminder_enabled: true,
            visit_sequence: formData.get('visit_sequence') || null,
            create_linked_task: true,
        };

        try {
            const response = await fetch(`${window.API_BASE_URL}/site-visits`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${window.API_TOKEN}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            });

            const contentType = response.headers.get('content-type');
            let result;

            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                if (typeof showNotification === 'function') {
                    showNotification('Server error: Invalid response format. Please try again.', 'error', 3000);
                } else {
                    alert('Server error: Invalid response format. Please try again.');
                }
                return;
            }

            if (response.ok && result.success) {
                const visitWorkflowAction = pendingVisitWorkflowAction;
                if (pendingVisitWorkflowAction === 'reschedule' && window.currentTaskId) {
                    try {
                        await apiCall(`/tasks/${window.currentTaskId}/complete`, {
                            method: 'POST',
                            body: JSON.stringify({})
                        });
                    } catch (completionError) {
                        console.error('Visit completion after reschedule failed:', completionError);
                        showLeadDetailAlert('Visit saved, but current visit task completion failed. Please refresh and check.', 'warning');
                        pendingVisitWorkflowAction = null;
                        closeSiteVisitModal();
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                        return;
                    }
                }

                if (pendingFollowUpWorkflowAction === 'visit' && window.currentTaskId) {
                    try {
                        await apiCall(`/tasks/${window.currentTaskId}/complete`, {
                            method: 'POST',
                            body: JSON.stringify({})
                        });
                    } catch (completionError) {
                        console.error('Follow-up completion after visit failed:', completionError);
                        showLeadDetailAlert('Visit created, but follow-up task completion failed. Please refresh and check.', 'warning');
                        pendingFollowUpWorkflowAction = null;
                        closeSiteVisitModal();
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                        return;
                    }
                }

                if (pendingMeetingWorkflowAction === 'visit' && window.currentTaskId) {
                    try {
                        await apiCall(`/tasks/${window.currentTaskId}/complete`, {
                            method: 'POST',
                            body: JSON.stringify({})
                        });
                    } catch (completionError) {
                        console.error('Meeting completion after visit failed:', completionError);
                        showLeadDetailAlert('Visit created, but current meeting task completion failed. Please refresh and check.', 'warning');
                        pendingMeetingWorkflowAction = null;
                        closeSiteVisitModal();
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                        return;
                    }
                }

                pendingFollowUpWorkflowAction = null;
                pendingVisitWorkflowAction = null;
                pendingMeetingWorkflowAction = null;
                if (typeof showNotification === 'function') {
                    showNotification(visitWorkflowAction === 'reschedule' ? 'Visit rescheduled successfully!' : 'Site visit scheduled successfully!', 'success', 3000);
                } else {
                    alert(visitWorkflowAction === 'reschedule' ? 'Visit rescheduled successfully!' : 'Site visit scheduled successfully!');
                }
                closeSiteVisitModal();
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                if (typeof showNotification === 'function') {
                    showNotification(result.message || 'Failed to schedule site visit', 'error', 3000);
                } else {
                    alert(result.message || 'Failed to schedule site visit');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while scheduling the site visit');
        }
    }

    async function submitMeeting(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const scheduledAt = new Date(`${formData.get('meeting_date')}T${formData.get('meeting_time')}`);

        const data = {
            lead_id: LEAD_ID,
            meeting_sequence: parseInt(formData.get('meeting_sequence')),
            scheduled_at: formatLocalDateTimeForApi(scheduledAt),
            meeting_mode: formData.get('meeting_mode'),
            meeting_link: formData.get('meeting_link') || null,
            location: formData.get('location') || null,
            reminder_enabled: formData.get('reminder_enabled') === 'on',
            reminder_minutes: 5,
            meeting_notes: formData.get('meeting_notes') || null,
        };

        try {
            const response = await fetch(`${window.API_BASE_URL}/sales-manager/meetings/quick-schedule-with-reminder`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${window.API_TOKEN}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            });

            const contentType = response.headers.get('content-type');
            let result;

            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                alert('Server error: Invalid response format. Please try again.');
                return;
            }

            if (response.ok && (result.success !== false)) {
                if (pendingVisitWorkflowAction === 'meeting' && window.currentTaskId) {
                    try {
                        await apiCall(`/tasks/${window.currentTaskId}/complete`, {
                            method: 'POST',
                            body: JSON.stringify({})
                        });
                    } catch (completionError) {
                        console.error('Visit completion after meeting failed:', completionError);
                        showLeadDetailAlert('Meeting created, but current visit task completion failed. Please refresh and check.', 'warning');
                        pendingVisitWorkflowAction = null;
                        closeMeetingModal();
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                        return;
                    }
                }

                if (pendingFollowUpWorkflowAction === 'meeting' && window.currentTaskId) {
                    try {
                        await apiCall(`/tasks/${window.currentTaskId}/complete`, {
                            method: 'POST',
                            body: JSON.stringify({})
                        });
                    } catch (completionError) {
                        console.error('Follow-up completion after meeting failed:', completionError);
                        showLeadDetailAlert('Meeting created, but follow-up task completion failed. Please refresh and check.', 'warning');
                        pendingFollowUpWorkflowAction = null;
                        closeMeetingModal();
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                        return;
                    }
                }

                if (pendingMeetingWorkflowAction === 'meeting' && window.currentTaskId) {
                    try {
                        await apiCall(`/tasks/${window.currentTaskId}/complete`, {
                            method: 'POST',
                            body: JSON.stringify({})
                        });
                    } catch (completionError) {
                        console.error('Meeting task completion after meeting schedule failed:', completionError);
                        showLeadDetailAlert('Meeting created, but current meeting task completion failed. Please refresh and check.', 'warning');
                        pendingMeetingWorkflowAction = null;
                        closeMeetingModal();
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                        return;
                    }
                }

                pendingFollowUpWorkflowAction = null;
                pendingVisitWorkflowAction = null;
                pendingMeetingWorkflowAction = null;
                const message = 'Meeting scheduled successfully!' + (data.reminder_enabled ? ' You will get a reminder 5 minutes before.' : '');
                showSuccessPopup(message);
                closeMeetingModal();
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                let errorMsg = result.message || 'Failed to schedule meeting';
                if (result.errors) {
                    const errorList = Object.values(result.errors).flat().join('\n');
                    errorMsg += '\n\n' + errorList;
                } else if (result.error) {
                    errorMsg += '\n\n' + result.error;
                }
                alert(errorMsg);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while scheduling the meeting. Please check console for details.');
        }
    }

    async function submitScheduleCallTask(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = {
            lead_id: LEAD_ID,
            scheduled_at: combineDateAndTime(
                formData.get('scheduled_date'),
                formData.get('scheduled_time'),
                'Schedule call'
            ),
            notes: formData.get('notes') || null,
        };

        try {
            // Determine the correct API endpoint based on user role
            let endpoint;
            if (window.USER_ROLE === 'telecaller') {
                endpoint = `${window.API_BASE_URL}/telecaller/tasks/schedule-call`;
            } else {
                // For sales managers, sales executives, and others, use sales-manager endpoint
                endpoint = `${window.API_BASE_URL}/sales-manager/tasks/schedule-call`;
            }
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${window.API_TOKEN}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            });

            // Check if response is JSON before parsing
            const contentType = response.headers.get('content-type');
            let result;
            
            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                const text = await response.text();
                console.error('Non-JSON response:', text);
                alert('Server error: Invalid response format. Please try again.');
                return;
            }

            if (response.ok && result.success) {
                // Show success message with button to go to task section
                closeScheduleCallTaskModal();
                
                // Determine task route based on user role
                let taskRoute = '#';
                if (window.USER_ROLE === 'telecaller') {
                    taskRoute = '{{ route("telecaller.tasks") }}';
                } else if (window.USER_ROLE === 'sales_manager' || window.USER_ROLE === 'sales_executive') {
                    @php
                        try {
                            $tasksRoute = route('sales-manager.tasks');
                        } catch (\Exception $e) {
                            $tasksRoute = '/sales-manager/tasks';
                        }
                    @endphp
                    taskRoute = '{{ $tasksRoute }}';
                } else {
                    // For other roles, try to construct the URL
                    taskRoute = '/sales-manager/tasks';
                }
                
                // Create success message with button
                const successMessage = `
                    <div id="taskSuccessMessage" style="position: fixed; top: 20px; right: 20px; z-index: 10000; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px 24px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); min-width: 320px; max-width: 400px; animation: slideInRight 0.3s ease-out;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                            <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: bold;">
                                ✓
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; font-size: 16px; margin-bottom: 4px;">Task Created Successfully!</div>
                                <div style="font-size: 14px; opacity: 0.9;">Call task has been scheduled.</div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button onclick="document.getElementById('taskSuccessMessage').remove(); window.location.href='${taskRoute}';" style="flex: 1; background: white; color: #059669; border: none; padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.02)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';">
                                Go to Task Section
                            </button>
                            <button onclick="document.getElementById('taskSuccessMessage').remove(); location.reload();" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)';" onmouseout="this.style.background='rgba(255,255,255,0.2)';">
                                Close
                            </button>
                        </div>
                    </div>
                    <style>
                        @keyframes slideInRight {
                            from {
                                transform: translateX(100%);
                                opacity: 0;
                            }
                            to {
                                transform: translateX(0);
                                opacity: 1;
                            }
                        }
                    </style>
                `;
                
                // Insert success message
                document.body.insertAdjacentHTML('beforeend', successMessage);
                
                // Auto-remove after 10 seconds
                setTimeout(() => {
                    const msg = document.getElementById('taskSuccessMessage');
                    if (msg) msg.remove();
                }, 10000);
            } else {
                // Show detailed error message
                let errorMsg = result.message || 'Failed to schedule call task';
                if (response.status === 422 && result.message === 'Please complete old task first.') {
                    errorMsg = 'Please complete old task first.';
                }
                if (result.errors) {
                    const errorDetails = Object.values(result.errors).flat().join(', ');
                    if (errorDetails && !errorMsg.includes(errorDetails)) {
                        errorMsg += ': ' + errorDetails;
                    }
                }
                
                console.error('Task creation error:', result);
                console.error('Response status:', response.status);
                console.error('Response body:', result);
                
                if (typeof showNotification === 'function') {
                    showNotification(errorMsg, 'error', 5000);
                } else {
                    alert(errorMsg);
                }
            }
        } catch (error) {
            console.error('Error:', error);
            if (typeof showNotification === 'function') {
                showNotification('An error occurred while scheduling the call task', 'error', 3000);
            } else {
                alert('An error occurred while scheduling the call task');
            }
        }
    }

    function showOwnerTransferSuccessPopup(message, redirectUrl) {
        const existingPopup = document.getElementById('ownerTransferSuccessPopup');
        if (existingPopup) {
            existingPopup.remove();
        }

        const safeRedirectUrl = redirectUrl || @json($backUrl);
        const popup = document.createElement('div');
        popup.id = 'ownerTransferSuccessPopup';
        popup.className = 'fixed inset-0 z-[9999] flex items-center justify-center p-4';
        popup.innerHTML = `
            <div class="absolute inset-0 bg-slate-950/55"></div>
            <div class="relative z-10 w-full max-w-md rounded-3xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
                <div class="bg-gradient-to-r from-[#063A1C] to-[#205A44] px-6 py-5 text-white">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-bold">Lead Transfer Successful</h3>
                            <p class="mt-1 text-sm text-emerald-50">Lead owner updated successfully.</p>
                        </div>
                        <button type="button" id="ownerTransferSuccessPopupCloseX" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <div class="flex items-center gap-3 rounded-2xl bg-emerald-50 border border-emerald-100 px-4 py-3 text-emerald-900">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-white">
                            <i class="fas fa-check"></i>
                        </div>
                        <p class="text-sm font-medium">${message}</p>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <button type="button" id="ownerTransferSuccessPopupCloseBtn" class="inline-flex items-center rounded-xl bg-[#0E4B2E] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#0a3a24] transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;

        const closePopup = () => {
            popup.remove();
            window.location.href = safeRedirectUrl;
        };

        popup.querySelector('#ownerTransferSuccessPopupCloseX')?.addEventListener('click', closePopup);
        popup.querySelector('#ownerTransferSuccessPopupCloseBtn')?.addEventListener('click', closePopup);
        popup.querySelector('.absolute.inset-0')?.addEventListener('click', closePopup);

        document.body.appendChild(popup);
    }

    let ownerTransferInFlight = false;

    async function submitOwnerTransfer(event) {
        event.preventDefault();
        if (ownerTransferInFlight) {
            return;
        }

        const form = event.target;
        const formData = new FormData(form);
        const assignedToRaw = formData.get('assigned_to');
        if (!assignedToRaw) {
            alert('Please select new owner');
            return;
        }
        const transferReason = (formData.get('notes') || '').trim();
        if (!transferReason) {
            alert('Please enter transfer reason');
            return;
        }

        const submitBtn = document.getElementById('ownerTransferSubmitBtn');
        const originalBtnText = submitBtn ? submitBtn.innerHTML : '';

        const payload = {
            assigned_to: parseInt(assignedToRaw, 10),
            create_calling_task: formData.get('create_calling_task') === 'on',
            transfer_existing_tasks: true,
            notes: transferReason,
        };

        try {
            ownerTransferInFlight = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
                submitBtn.innerHTML = 'Transferring...';
            }

            const response = await fetch(`${window.API_BASE_URL}/leads/${LEAD_ID}/assign`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${window.API_TOKEN}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const contentType = response.headers.get('content-type') || '';
            const result = contentType.includes('application/json') ? await response.json() : {};
            if (!response.ok) {
                throw new Error(result.message || 'Failed to transfer lead owner');
            }

            closeOwnerTransferModal();
            showOwnerTransferSuccessPopup(
                result.message || 'Lead transferred successfully.',
                @json($backUrl)
            );
        } catch (error) {
            console.error('Owner transfer error:', error);
            alert(error.message || 'Unable to transfer lead owner');
        } finally {
            ownerTransferInFlight = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                submitBtn.innerHTML = originalBtnText;
            }
        }
    }

    async function submitCompleteAsmTask(event) {
        event.preventDefault();

        const form = event.target;
        const selectedTask = form.querySelector('input[name="task_id"]:checked');

        if (!selectedTask) {
            if (typeof showNotification === 'function') {
                showNotification('Please select a task to complete.', 'error', 3000);
            } else {
                alert('Please select a task to complete.');
            }
            return;
        }

        setCurrentTaskId(selectedTask.value);
        const selectedTaskModelType = selectedTask.dataset.taskModelType || 'task';
        if (selectedTaskModelType !== 'task') {
            completeOldTask(
                selectedTaskModelType,
                selectedTask.value,
                selectedTask.dataset.taskTitle || 'Task'
            );
            return;
        }
        setCurrentTaskContext({
            title: selectedTask.dataset.taskTitle || '',
            notes: selectedTask.dataset.taskNotes || '',
            description: selectedTask.dataset.taskDescription || '',
            scheduledAt: selectedTask.dataset.taskScheduledAt || '',
            meetingId: selectedTask.dataset.taskMeetingId || '',
            siteVisitId: selectedTask.dataset.taskSiteVisitId || '',
            siteVisitProject: selectedTask.dataset.taskSiteVisitProject || '',
            followUpId: selectedTask.dataset.taskFollowUpId || '',
        });
        currentTaskCategory = normalizeTaskCategory(selectedTask.dataset.taskCategory, window.currentTaskContext);
        openTaskOutcomeModal(selectedTask.value, currentTaskCategory);
    }

    const submittedFormModal = document.getElementById('submittedFormModal');
    const submittedFormTitle = document.getElementById('submittedFormTitle');
    const submittedFormContent = document.getElementById('submittedFormContent');

    function closeSubmittedForm() {
        submittedFormModal?.classList.add('hidden');
        submittedFormModal?.classList.remove('flex');
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('.lead-submitted-form-trigger');
        if (!button) return;

        try {
            const fields = JSON.parse(atob(button.dataset.formFields || ''));
            const files = JSON.parse(atob(button.dataset.formFiles || 'W10='));
            submittedFormTitle.textContent = button.dataset.formTitle || 'Submitted Form';
            const fieldMarkup = fields.length
                ? `<div class="grid gap-3 sm:grid-cols-2">${fields.map(field => `<div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2"><div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(field.label)}</div><div class="mt-1 whitespace-pre-wrap break-words text-sm font-medium text-slate-800">${escapeHtml(field.value)}</div></div>`).join('')}</div>`
                : '';
            const fileMarkup = files.length
                ? `<div class="mt-5 border-t border-slate-200 pt-4"><h3 class="mb-3 text-sm font-bold text-slate-900">Submitted files</h3><div class="grid gap-2 sm:grid-cols-2">${files.map(file => `<a href="${escapeHtml(file.url)}" target="_blank" rel="noopener" class="flex min-h-11 items-center gap-3 rounded-md border border-slate-200 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50"><i class="fas fa-paperclip"></i><span class="min-w-0"><span class="block">${escapeHtml(file.label)}</span><small class="block truncate font-normal text-slate-500">${escapeHtml(file.name)}</small></span><i class="fas fa-external-link-alt ml-auto text-xs"></i></a>`).join('')}</div></div>`
                : '';
            submittedFormContent.innerHTML = fieldMarkup || fileMarkup
                ? fieldMarkup + fileMarkup
                : '<p class="text-sm text-slate-500">No form values were saved.</p>';
            submittedFormModal.classList.remove('hidden');
            submittedFormModal.classList.add('flex');
        } catch (error) {
            submittedFormTitle.textContent = 'Submitted Form';
            submittedFormContent.innerHTML = '<p class="text-sm text-rose-600">Form details could not be loaded.</p>';
            submittedFormModal.classList.remove('hidden');
            submittedFormModal.classList.add('flex');
        }
    });
    document.getElementById('submittedFormClose')?.addEventListener('click', closeSubmittedForm);

    // Close modals when clicking outside
    window.onclick = function(event) {
        const followupModal = document.getElementById('followupModal');
        const siteVisitModal = document.getElementById('siteVisitModal');
        const meetingModal = document.getElementById('meetingModal');
        const scheduleCallTaskModal = document.getElementById('scheduleCallTaskModal');
        const completeAsmTaskModal = document.getElementById('completeAsmTaskModal');

        if (event.target === submittedFormModal) {
            closeSubmittedForm();
        }

        if (event.target === followupModal) {
            closeFollowupModal();
        }
        if (event.target === siteVisitModal) {
            closeSiteVisitModal();
        }
        if (event.target === meetingModal) {
            closeMeetingModal();
        }
        if (event.target === scheduleCallTaskModal) {
            closeScheduleCallTaskModal();
        }
        if (event.target === completeAsmTaskModal) {
            closeCompleteAsmTaskModal();
        }
        if (event.target === document.getElementById('verifyRejectPromptModal')) {
            cancelVerifyRejectPrompt();
        }
        if (event.target === document.getElementById('rejectReasonModal')) {
            cancelJunkRemarkModal();
        }
        if (event.target === document.getElementById('cnpTimeSelectionModal')) {
            cancelOutcomeDateTimeModal();
        }
        if (event.target === document.getElementById('managerLeadRequirementFormModal')) {
            cancelManagerLeadRequirementForm();
        }
        if (event.target === document.getElementById('followUpActionHubModal')) {
            closeFollowUpActionHubModal();
        }
        if (event.target === document.getElementById('visitActionHubModal')) {
            closeVisitActionHubModal();
        }
        if (event.target === document.getElementById('meetingActionHubModal')) {
            closeMeetingActionHubModal();
        }
        if (event.target === document.getElementById('leadDetailCompleteMeetingModal')) {
            closeLeadDetailCompleteMeetingModal();
        }
        if (event.target === document.getElementById('leadDetailCompleteVisitModal')) {
            closeLeadDetailCompleteVisitModal();
        }
        if (event.target === document.getElementById('leadDetailRescheduleMeetingModal')) {
            closeLeadDetailRescheduleMeetingModal();
        }
        const ownerTransferModal = document.getElementById('ownerTransferModal');
        if (event.target === ownerTransferModal) {
            closeOwnerTransferModal();
        }
        const oldTaskTransferModal = document.getElementById('oldTaskTransferModal');
        if (event.target === oldTaskTransferModal) {
            closeOldTaskTransferModal();
        }
    }

    // Animated Success Popup
    function showSuccessPopup(message) {
        // Create popup if it doesn't exist
        let popup = document.getElementById('successPopup');
        if (!popup) {
            popup = document.createElement('div');
            popup.id = 'successPopup';
            popup.className = 'fixed inset-0 z-[9999] flex items-center justify-center pointer-events-none';
            popup.innerHTML = `
                <div class="bg-black bg-opacity-50 fixed inset-0 pointer-events-auto" id="successPopupOverlay"></div>
                <div id="successPopupContent" class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full mx-4 transform scale-0 pointer-events-auto relative z-10">
                    <div class="flex flex-col items-center">
                        <div class="success-tick-container w-20 h-20 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center mb-4 shadow-lg">
                            <svg class="success-tick" width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="24" cy="24" r="22" stroke="white" stroke-width="3" class="tick-circle"/>
                                <path d="M14 24 L20 30 L34 16" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" class="tick-path" fill="none"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">Success!</h3>
                        <p class="text-gray-600 text-center">${message}</p>
                    </div>
                </div>
            `;
            document.body.appendChild(popup);
        }

        // Update message
        const messageEl = popup.querySelector('p');
        if (messageEl) {
            messageEl.textContent = message;
        }

        // Show popup with animation
        popup.style.display = 'flex';
        const content = document.getElementById('successPopupContent');
        content.style.transform = 'scale(0)';
        content.style.opacity = '0';

        // Trigger animation
        setTimeout(() => {
            content.style.transition = 'all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
            content.style.transform = 'scale(1)';
            content.style.opacity = '1';
        }, 10);

        // Auto-hide after 2 seconds
        setTimeout(() => {
            content.style.transition = 'all 0.3s ease-in';
            content.style.transform = 'scale(0.8)';
            content.style.opacity = '0';
            setTimeout(() => {
                popup.style.display = 'none';
            }, 300);
        }, 2000);
    }

    function initializeAsmMobileLeadTabs() {
        const buttons = Array.from(document.querySelectorAll('[data-asm-lead-tab]'));
        const panels = Array.from(document.querySelectorAll('[data-asm-lead-panel]'));

        if (!buttons.length || !panels.length) {
            return;
        }

        const activateTab = (target) => {
            buttons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.asmLeadTab === target);
            });
            panels.forEach((panel) => {
                panel.classList.toggle('is-active', panel.dataset.asmLeadPanel === target);
            });
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => activateTab(button.dataset.asmLeadTab));
        });

        const activeButton = buttons.find((button) => button.classList.contains('is-active'));
        activateTab(activeButton ? activeButton.dataset.asmLeadTab : buttons[0].dataset.asmLeadTab);
    }

    const leadWhatsAppSheetState = {
        defaultUrl: @json($leadWhatsAppUrl),
        businessUrl: @json($leadWhatsAppBusinessUrl),
        phone: @json($leadWhatsAppPhone),
    };

    function isLeadWhatsAppMobileView() {
        return window.matchMedia('(max-width: 767.98px)').matches;
    }

    function openLeadWhatsAppSheetFromTrigger(trigger) {
        const sheet = document.getElementById('leadWhatsAppSheet');
        if (!sheet) {
            return;
        }

        leadWhatsAppSheetState.defaultUrl = trigger?.dataset.whatsappUrl || leadWhatsAppSheetState.defaultUrl || '';
        leadWhatsAppSheetState.businessUrl = trigger?.dataset.whatsappBusinessUrl || leadWhatsAppSheetState.businessUrl || '';
        leadWhatsAppSheetState.phone = trigger?.dataset.whatsappPhone || leadWhatsAppSheetState.phone || '';

        const copyLabel = document.getElementById('leadWhatsAppCopyLabel');
        if (copyLabel) {
            copyLabel.textContent = leadWhatsAppSheetState.phone || 'No number available';
        }

        sheet.classList.remove('hidden');
        sheet.classList.add('flex');
        sheet.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    function closeLeadWhatsAppSheet() {
        const sheet = document.getElementById('leadWhatsAppSheet');
        if (!sheet) {
            return;
        }

        sheet.classList.add('hidden');
        sheet.classList.remove('flex');
        sheet.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }

    function handleLeadWhatsAppClick(event, trigger) {
        if (@json($leadPhoneMaskedForViewer)) {
            event.preventDefault();
            window.openProtectedLeadWhatsApp(trigger?.dataset.leadId || @json($lead->id), @json($leadWhatsAppMessage));
            return false;
        }
        if (!isLeadWhatsAppMobileView()) {
            return true;
        }

        event.preventDefault();

        if (!(trigger?.dataset.whatsappUrl)) {
            if (window.showAlert) {
                window.showAlert('Phone number not available for WhatsApp.', 'warning');
            } else {
                alert('Phone number not available for WhatsApp.');
            }
            return false;
        }

        openLeadWhatsAppSheetFromTrigger(trigger);
        return false;
    }

    function openLeadWhatsAppDestination(target) {
        const defaultUrl = leadWhatsAppSheetState.defaultUrl || '';
        const businessUrl = leadWhatsAppSheetState.businessUrl || defaultUrl;

        if (!defaultUrl) {
            closeLeadWhatsAppSheet();
            return;
        }

        closeLeadWhatsAppSheet();

        if (target === 'business') {
            window.location.href = businessUrl;
            window.setTimeout(() => {
                if (!document.hidden) {
                    window.location.href = defaultUrl;
                }
            }, 900);
            return;
        }

        window.location.href = defaultUrl;
    }

    async function copyLeadWhatsAppNumber() {
        const phone = leadWhatsAppSheetState.phone || '';
        if (!phone) {
            return;
        }

        try {
            await navigator.clipboard.writeText(phone);
            if (window.showAlert) {
                window.showAlert('WhatsApp number copied.', 'success');
            }
        } catch (error) {
            const input = document.createElement('input');
            input.value = phone;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
        }

        closeLeadWhatsAppSheet();
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeLeadWhatsAppSheet();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        initializeAsmMobileLeadTabs();
    });
</script>

<style>
    @keyframes tickDraw {
        0% {
            stroke-dasharray: 0, 100;
            stroke-dashoffset: 0;
        }
        100% {
            stroke-dasharray: 100, 0;
            stroke-dashoffset: 0;
        }
    }

    @keyframes tickScale {
        0% {
            transform: scale(0);
        }
        50% {
            transform: scale(1.1);
        }
        100% {
            transform: scale(1);
        }
    }

    @keyframes circleDraw {
        0% {
            stroke-dasharray: 0, 138;
            stroke-dashoffset: 0;
        }
        100% {
            stroke-dasharray: 138, 0;
            stroke-dashoffset: 0;
        }
    }

    .success-tick-container {
        animation: tickScale 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }

    .success-tick .tick-circle {
        stroke-dasharray: 0, 138;
        animation: circleDraw 0.6s ease-out forwards;
    }

    .success-tick .tick-path {
        stroke-dasharray: 0, 30;
        animation: tickDraw 0.4s ease-out 0.3s forwards;
    }
</style>
@endpush
