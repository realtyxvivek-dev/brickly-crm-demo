<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\InterestedProjectName;
use App\Services\DynamicFormService;
use App\Services\FormDetectionService;
use App\Services\KycFormSchemaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DynamicFormController extends Controller
{
    private const ALLOWED_FORM_PATHS = [
        'leads.create',
        'lead-detail.requirements',
        'lead-detail.meeting',
        'lead-detail.site-visit',
        'lead-detail.follow-up',
        KycFormSchemaService::LOCATION_PATH,
    ];

    protected $formDetectionService;
    protected $dynamicFormService;
    protected $kycFormSchemaService;

    public function __construct(FormDetectionService $formDetectionService, DynamicFormService $dynamicFormService, KycFormSchemaService $kycFormSchemaService)
    {
        $this->formDetectionService = $formDetectionService;
        $this->dynamicFormService = $dynamicFormService;
        $this->kycFormSchemaService = $kycFormSchemaService;
    }

    /**
     * Preview fields of an existing system form
     */
    public function previewExistingForm(string $formPath)
    {
        $fields = $this->getExistingFormFields($formPath);
        $formName = $this->getExistingFormName($formPath);

        if (empty($fields)) {
            abort(404, 'Form not found');
        }

        return view('admin.forms.existing-preview', compact('fields', 'formName', 'formPath'));
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

    private function getExistingFormName(string $formPath): string
    {
        $names = [
            'crm.automation.leads.create' => 'Lead Creation Form',
            'leads.create'                => 'Add Lead Form',
            'leads.edit'                  => 'Lead Edit Form',
            'lead-detail.requirements'    => 'Lead Detail Requirements Form',
            'meetings.create'             => 'Meeting Form',
            'site-visits.create'          => 'Site Visit Form',
            'lead-detail.meeting'         => 'Lead Detail Meeting Popup Form',
            'lead-detail.site-visit'      => 'Lead Detail Site Visit Popup Form',
            'lead-detail.follow-up'       => 'Lead Detail Follow Up Popup Form',
            KycFormSchemaService::LOCATION_PATH => 'Closer KYC Form Builder',
            'calls.create'                => 'Call Log Form',
            'projects.create'             => 'Project Form',
            'closers.index'               => 'Closer Submit Form',
            'finance-manager.incentives'  => 'Incentive Submit Form',
        ];

        return $names[$formPath] ?? 'Form Preview';
    }

    private function getExistingFormFields(string $formPath): array
    {
        if ($formPath === KycFormSchemaService::LOCATION_PATH) {
            return $this->kycFormSchemaService->getResolvedSchema()['fields'];
        }

        $definitions = [
            'crm.automation.leads.create' => [
                ['label' => 'Customer Name',   'type' => 'text',     'name' => 'name',     'required' => true],
                ['label' => 'Phone Number',    'type' => 'tel',      'name' => 'phone',    'required' => true],
                ['label' => 'Email Address',   'type' => 'email',    'name' => 'email',    'required' => false],
                ['label' => 'Lead Source',     'type' => 'select',   'name' => 'source',   'required' => false,
                 'options' => ['Facebook', 'Google', 'Walk-in', 'Referral', 'Other']],
                ['label' => 'Project Interest','type' => 'text',     'name' => 'project',  'required' => false],
                ['label' => 'Budget Range',    'type' => 'text',     'name' => 'budget',   'required' => false],
                ['label' => 'Notes',           'type' => 'textarea', 'name' => 'notes',    'required' => false],
            ],
            'leads.create' => [
                ['label' => 'Name',              'type' => 'text',     'name' => 'name',               'required' => true],
                ['label' => 'Phone',             'type' => 'tel',      'name' => 'phone',              'required' => true],
                ['label' => 'Email',             'type' => 'email',    'name' => 'email',              'required' => false],
                ['label' => 'Source',            'type' => 'select',   'name' => 'source',             'required' => false,
                 'options' => array_values(Lead::sourceOptions())],
                ['label' => 'Address',           'type' => 'textarea', 'name' => 'address',            'required' => false],
                ['label' => 'City',              'type' => 'text',     'name' => 'city',               'required' => false],
                ['label' => 'State',             'type' => 'text',     'name' => 'state',              'required' => false],
                ['label' => 'Pincode',           'type' => 'text',     'name' => 'pincode',            'required' => false],
                ['label' => 'Preferred Location','type' => 'select',   'name' => 'preferred_location', 'required' => false],
                ['label' => 'Preferred Size',    'type' => 'text',     'name' => 'preferred_size',     'required' => false],
                ['label' => 'Preferred Projects','type' => 'select',   'name' => 'preferred_projects', 'required' => false],
                ['label' => 'Budget',            'type' => 'select',   'name' => 'budget',             'required' => false],
                ['label' => 'Use/End Use',       'type' => 'select',   'name' => 'use_end_use',        'required' => false],
                ['label' => 'Property Type',     'type' => 'select',   'name' => 'property_type',      'required' => false],
                ['label' => 'Possession Status', 'type' => 'select',   'name' => 'possession_status',  'required' => false],
                ['label' => 'Assign To User',    'type' => 'select',   'name' => 'assigned_to',        'required' => false],
                ['label' => 'Requirements',      'type' => 'textarea', 'name' => 'requirements',       'required' => false],
                ['label' => 'Notes',             'type' => 'textarea', 'name' => 'notes',              'required' => false],
            ],
            'leads.edit' => [
                ['label' => 'Customer Name',   'type' => 'text',     'name' => 'name',     'required' => true],
                ['label' => 'Phone Number',    'type' => 'tel',      'name' => 'phone',    'required' => true],
                ['label' => 'Email Address',   'type' => 'email',    'name' => 'email',    'required' => false],
                ['label' => 'Lead Source',     'type' => 'select',   'name' => 'source',   'required' => false,
                 'options' => ['Facebook', 'Google', 'Walk-in', 'Referral', 'Other']],
                ['label' => 'Status',          'type' => 'select',   'name' => 'status',   'required' => false,
                 'options' => ['New', 'Contacted', 'Interested', 'Not Interested', 'Converted']],
                ['label' => 'Budget',          'type' => 'text',     'name' => 'budget',   'required' => false],
                ['label' => 'Notes',           'type' => 'textarea', 'name' => 'notes',    'required' => false],
            ],
            'lead-detail.requirements' => [
                ['label' => 'Customer Name',        'type' => 'text',     'name' => 'name',                'required' => true],
                ['label' => 'Phone',                'type' => 'tel',      'name' => 'phone',               'required' => true],
                ['label' => 'Category',             'type' => 'select',   'name' => 'category',            'required' => true,
                 'options' => ['Residential', 'Commercial', 'Both', 'N.A']],
                ['label' => 'Location',             'type' => 'select',   'name' => 'preferred_location',  'required' => true,
                 'options' => ['Inside City', 'Sitapur Road', 'Hardoi Road', 'Faizabad Road', 'Sultanpur Road', 'Shaheed Path', 'Raebareily Road', 'Kanpur Road', 'Outer Ring Road', 'Bijnor Road', 'Deva Road', 'Sushant Golf City', 'Vrindavan Yojana', 'N.A']],
                ['label' => 'Budget',               'type' => 'select',   'name' => 'budget',              'required' => true,
                 'options' => ['Below 50 Lacs', '50-75 Lacs', '75 Lacs-1 Cr', 'Above 1 Cr', 'Above 2 Cr', 'N.A']],
                ['label' => 'Type',                 'type' => 'select',   'name' => 'type',                'required' => true,
                 'options' => ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A']],
                ['label' => 'Purpose',              'type' => 'select',   'name' => 'purpose',             'required' => true,
                 'options' => ['End Use', 'Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use', 'N.A']],
                ['label' => 'Possession',           'type' => 'select',   'name' => 'possession',          'required' => true,
                 'options' => ['Under Construction', 'Ready To Move', 'Pre Launch', 'Both', 'N.A']],
                ['label' => 'Status',               'type' => 'select',   'name' => 'lead_status',         'required' => true,
                 'options' => ['hot', 'warm', 'cold', 'junk']],
                ['label' => 'Lead Quality',         'type' => 'select',   'name' => 'lead_quality',        'required' => true,
                 'options' => ['1', '2', '3', '4', '5']],
                ['label' => 'Interested Projects',  'type' => 'select',   'name' => 'interested_projects', 'required' => true,
                 'options' => $this->getInterestedProjectOptions()],
                ['label' => 'Customer Job',         'type' => 'text',     'name' => 'customer_job',        'required' => false],
                ['label' => 'Industry / Sector',    'type' => 'select',   'name' => 'industry_sector',     'required' => false,
                 'options' => ['IT', 'Education', 'Healthcare', 'Business', 'FMCG', 'Government', 'Other']],
                ['label' => 'Buying Frequency',     'type' => 'select',   'name' => 'buying_frequency',    'required' => false,
                 'options' => ['Regular', 'Occasional', 'First-time']],
                ['label' => 'Living City',          'type' => 'text',     'name' => 'living_city',         'required' => false],
                ['label' => 'City Type',            'type' => 'select',   'name' => 'city_type',           'required' => false,
                 'options' => ['Metro', 'Tier 1', 'Tier 2', 'Tier 3', 'Local Resident']],
                ['label' => 'Remark',               'type' => 'textarea', 'name' => 'manager_remark',      'required' => false],
            ],
            'meetings.create' => [
                ['label' => 'Customer Name',   'type' => 'text',     'name' => 'customer_name',  'required' => true],
                ['label' => 'Phone Number',    'type' => 'tel',      'name' => 'phone',           'required' => true],
                ['label' => 'Employee',        'type' => 'text',     'name' => 'employee',        'required' => false, 'readonly' => true],
                ['label' => 'Occupation',      'type' => 'text',     'name' => 'occupation',      'required' => false],
                ['label' => 'Date of Visit',   'type' => 'date',     'name' => 'date_of_visit',   'required' => true],
                ['label' => 'Project',         'type' => 'select',   'name' => 'project_id',      'required' => false,
                 'options' => ['Select Project...']],
                ['label' => 'Meeting Notes',   'type' => 'textarea', 'name' => 'notes',           'required' => false],
            ],
            'site-visits.create' => [
                ['label' => 'Customer Name',   'type' => 'text',     'name' => 'customer_name',  'required' => true],
                ['label' => 'Phone Number',    'type' => 'tel',      'name' => 'phone',           'required' => true],
                ['label' => 'Date of Visit',   'type' => 'date',     'name' => 'date_of_visit',   'required' => true],
                ['label' => 'Time of Visit',   'type' => 'time',     'name' => 'time_of_visit',   'required' => false],
                ['label' => 'Project',         'type' => 'select',   'name' => 'project_id',      'required' => false,
                 'options' => ['Select Project...']],
                ['label' => 'Employee',        'type' => 'text',     'name' => 'employee',        'required' => false, 'readonly' => true],
                ['label' => 'Visit Notes',     'type' => 'textarea', 'name' => 'notes',           'required' => false],
            ],
            'lead-detail.meeting' => [
                ['label' => 'Meeting Type',    'type' => 'select',   'name' => 'meeting_type',    'required' => true,
                 'options' => ['Initial Meeting', 'Follow-up Meeting', 'Negotiation Meeting', 'Closing Meeting']],
                ['label' => 'Scheduled Date',  'type' => 'date',     'name' => 'meeting_date',    'required' => true],
                ['label' => 'Scheduled Time',  'type' => 'time',     'name' => 'meeting_time',    'required' => true],
                ['label' => 'Meeting Mode',    'type' => 'select',   'name' => 'meeting_mode',    'required' => true,
                 'options' => ['Online', 'Offline']],
                ['label' => 'Meeting Link',    'type' => 'url',      'name' => 'meeting_link',    'required' => false],
                ['label' => 'Location',        'type' => 'text',     'name' => 'location',        'required' => false],
                ['label' => 'Remark',          'type' => 'textarea', 'name' => 'meeting_notes',   'required' => false],
                ['label' => 'Remind Me Before Meeting', 'type' => 'checkbox', 'name' => 'reminder_enabled', 'required' => false],
            ],
            'lead-detail.site-visit' => [
                ['label' => 'Visit Date',      'type' => 'date',     'name' => 'visit_date',      'required' => true],
                ['label' => 'Visit Time',      'type' => 'time',     'name' => 'visit_time',      'required' => true],
                ['label' => 'Visit Type',      'type' => 'select',   'name' => 'visit_type',      'required' => false,
                 'options' => ['Site visit', 'Office visit']],
                ['label' => 'Project To Visit','type' => 'text',     'name' => 'project_name',    'required' => false],
                ['label' => 'Visit Location',  'type' => 'text',     'name' => 'visit_location',  'required' => false],
                ['label' => 'Remark',          'type' => 'textarea', 'name' => 'visit_notes',     'required' => false],
                ['label' => 'Remind Me Before Visit', 'type' => 'checkbox', 'name' => 'visit_reminder', 'required' => false],
            ],
            'lead-detail.follow-up' => [
                ['label' => 'Follow Up Required', 'type' => 'checkbox', 'name' => 'followup_required', 'required' => false],
                ['label' => 'Follow Up Date & Time', 'type' => 'datetime-local', 'name' => 'scheduled_at', 'required' => true],
                ['label' => 'Remark',          'type' => 'textarea', 'name' => 'notes',           'required' => false],
            ],
            'calls.create' => [
                ['label' => 'Customer Name',   'type' => 'text',     'name' => 'customer_name',  'required' => true],
                ['label' => 'Phone Number',    'type' => 'tel',      'name' => 'phone',           'required' => true],
                ['label' => 'Call Date & Time','type' => 'datetime-local', 'name' => 'called_at', 'required' => true],
                ['label' => 'Duration (mins)', 'type' => 'number',   'name' => 'duration',        'required' => false],
                ['label' => 'Call Outcome',    'type' => 'select',   'name' => 'outcome',         'required' => false,
                 'options' => ['Connected', 'Not Answered', 'Busy', 'Wrong Number', 'Callback Requested']],
                ['label' => 'Notes',           'type' => 'textarea', 'name' => 'notes',           'required' => false],
            ],
            'projects.create' => [
                ['label' => 'Project Name',    'type' => 'text',     'name' => 'name',       'required' => true],
                ['label' => 'Location',        'type' => 'text',     'name' => 'location',   'required' => false],
                ['label' => 'Project Type',    'type' => 'select',   'name' => 'type',       'required' => false,
                 'options' => ['Residential', 'Commercial', 'Mixed Use', 'Plot']],
                ['label' => 'Price Range',     'type' => 'text',     'name' => 'price_range','required' => false],
                ['label' => 'Description',     'type' => 'textarea', 'name' => 'description','required' => false],
            ],
            'closers.index' => [
                ['label' => 'Customer Name',   'type' => 'text',     'name' => 'customer_name', 'required' => true],
                ['label' => 'Phone Number',    'type' => 'tel',      'name' => 'phone',          'required' => true],
                ['label' => 'Project',         'type' => 'select',   'name' => 'project_id',     'required' => true,
                 'options' => ['Select Project...']],
                ['label' => 'Closer Amount',   'type' => 'number',   'name' => 'amount',         'required' => true],
                ['label' => 'Closing Date',    'type' => 'date',     'name' => 'closing_date',   'required' => true],
                ['label' => 'Status',          'type' => 'select',   'name' => 'status',         'required' => false,
                 'options' => ['Pending', 'Verified', 'Rejected']],
                ['label' => 'Remarks',         'type' => 'textarea', 'name' => 'remarks',        'required' => false],
            ],
            'finance-manager.incentives' => [
                ['label' => 'Employee',        'type' => 'select',   'name' => 'user_id',        'required' => true,
                 'options' => ['Select Employee...']],
                ['label' => 'Incentive Type',  'type' => 'select',   'name' => 'type',           'required' => true,
                 'options' => ['Performance', 'Referral', 'Closing Bonus', 'Festival Bonus', 'Other']],
                ['label' => 'Month',           'type' => 'month',    'name' => 'month',          'required' => true],
                ['label' => 'Amount (₹)',      'type' => 'number',   'name' => 'amount',         'required' => true],
                ['label' => 'Remarks',         'type' => 'textarea', 'name' => 'remarks',        'required' => false],
            ],
        ];

        return $definitions[$formPath] ?? [];
    }

    /**
     * Display the approved existing forms that can be managed from admin.
     */
    public function index(Request $request)
    {
        $filter = $request->input('filter', 'all');

        $existingForms = collect($this->getExistingForms())
            ->filter(function (array $form) {
                return in_array($form['path'], self::ALLOWED_FORM_PATHS, true);
            })
            ->map(function (array $form) {
                $linkedForm = $this->dynamicFormService->getLatestFormByLocation($form['path']);

                $form['edit_url'] = $this->buildExistingFormEditUrl($form);
                $form['status'] = $linkedForm?->status ?? 'draft';
                $form['status_label'] = $linkedForm ? ucfirst($linkedForm->status) : 'System';

                if (empty($form['description'])) {
                    $form['description'] = 'Edit this live form directly from here. This is the single admin entry for this form location.';
                }

                return $form;
            })
            ->filter(function (array $form) use ($filter) {
                if ($filter === 'drafts') {
                    return $form['status'] === 'draft';
                }

                if ($filter === 'published') {
                    return $form['status'] === 'published';
                }

                return true;
            })
            ->all();

        $customForms = DynamicForm::with(['fields', 'creator', 'replacedForm'])
            ->whereNotIn('location_path', self::ALLOWED_FORM_PATHS)
            ->when($filter === 'drafts', fn ($query) => $query->where('status', 'draft'))
            ->when($filter === 'published', fn ($query) => $query->where('status', 'published'))
            ->latest()
            ->get();

        return view('admin.forms.index', compact('existingForms', 'customForms', 'filter'));
    }

    /**
     * Show form builder
     */
    public function create(Request $request)
    {
        $existingForm = null;
        $detectedFields = [];

        if (!$request->has('from_existing')) {
            return redirect()
                ->route('admin.forms.index')
                ->withErrors(['error' => 'Direct form creation is disabled. Only approved linked forms can be managed here.']);
        }

        $oldFields = session()->getOldInput('fields');
        if (is_string($oldFields)) {
            $decodedFields = json_decode($oldFields, true);
            if (is_array($decodedFields) && !empty($decodedFields)) {
                $detectedFields = $decodedFields;
            }
        } elseif (is_array($oldFields) && !empty($oldFields)) {
            $detectedFields = $oldFields;
        }
        
        // If creating from existing form
        if ($request->has('from_existing')) {
            $formType = $request->input('type', 'custom');
            $locationPath = $request->input('path', '');

            if (!in_array($locationPath, self::ALLOWED_FORM_PATHS, true)) {
                return redirect()
                    ->route('admin.forms.index')
                    ->withErrors(['error' => 'This form is not available in the restricted forms section.']);
            }

            $linkedForm = $this->dynamicFormService->getLatestFormByLocation($locationPath);
            if ($linkedForm) {
                return redirect()->route('admin.forms.edit', $linkedForm->id);
            }
            
            $existingForm = [
                'name' => $request->input('name', 'New Form'),
                'location_path' => $locationPath,
                'form_type' => $formType,
                'description' => 'Dynamic version of existing form: ' . $request->input('name', ''),
            ];
            
            // Detect fields from existing form
            if (empty($detectedFields)) {
                $detectedFields = $this->formDetectionService->getFieldDefinitions($formType, $locationPath);
            }
        }
        
        return view('admin.forms.builder', [
            'form' => null, 
            'existingForm' => $existingForm,
            'detectedFields' => $detectedFields
        ]);
    }

    public function openExistingEditor(Request $request)
    {
        $locationPath = (string) $request->query('path', '');
        $formName = (string) $request->query('name', 'New Form');
        $formType = (string) $request->query('type', 'custom');

        if (!in_array($locationPath, self::ALLOWED_FORM_PATHS, true)) {
            return redirect()
                ->route('admin.forms.index')
                ->withErrors(['error' => 'This form is not available in the restricted forms section.']);
        }

        $linkedForm = $this->dynamicFormService->getLatestFormByLocation($locationPath);
        if (!$linkedForm) {
            $linkedForm = $this->createLinkedDraftForm($formName, $locationPath, $formType);
        }

        return redirect()->route('admin.forms.edit', $linkedForm->id);
    }

    /**
     * Show form builder for editing
     */
    public function edit(DynamicForm $dynamicForm)
    {
        $dynamicForm->load(['fields' => function($query) {
            $query->orderBy('order');
        }]);

        $this->hydrateMissingFieldOptions($dynamicForm);
        $this->hydrateMissingFieldDefinitions($dynamicForm);

        // #region agent log
        \Log::info('DynamicFormController:edit - Loading form for editing', [
            'form_id' => $dynamicForm->id,
            'fields_count' => $dynamicForm->fields->count(),
            'fields' => $dynamicForm->fields->map(function($f) {
                return ['id' => $f->id, 'key' => $f->field_key, 'type' => $f->field_type];
            })->toArray(),
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'H4'
        ]);
        // #endregion

        return view('admin.forms.builder', ['form' => $dynamicForm]);
    }

    /**
     * Store a newly created form
     */
    public function store(Request $request)
    {
        // Log to verify we're in store method
        \Log::info('DynamicFormController:store - Method called', [
            'request_method' => $request->method(),
            'has_method_field' => $request->has('_method'),
            'method_field_value' => $request->input('_method'),
            'form_name' => $request->input('name'),
        ]);
        
        // Decode JSON fields if sent as string
        $fieldsData = $request->input('fields');
        if (is_string($fieldsData)) {
            $fieldsData = json_decode($fieldsData, true);
            $request->merge(['fields' => $fieldsData]);
        }

        // Decode JSON settings if sent as string
        $settingsData = $request->input('settings');
        if (is_string($settingsData)) {
            $settingsData = json_decode($settingsData, true);
            $request->merge(['settings' => $settingsData ?? []]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:dynamic_forms,slug',
            'description' => 'nullable|string',
            'location_path' => 'required|string|max:255',
            'form_type' => 'required|string|max:50',
            'status' => 'nullable|in:draft,published',
            'settings' => 'nullable|array',
            'fields' => 'required|array|min:1',
            'fields.*.field_key' => 'required|string|max:255',
            'fields.*.field_type' => 'required|string|max:50',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.placeholder' => 'nullable|string',
            'fields.*.help_text' => 'nullable|string',
            'fields.*.options' => 'nullable|array',
            'fields.*.options.*' => 'nullable|string',
            'fields.*.validation' => 'nullable|array',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.order' => 'nullable|integer',
            'fields.*.section' => 'nullable|string|max:255',
            'fields.*.styles' => 'nullable|array',
            'fields.*.default_value' => 'nullable',
            'fields.*.is_system' => 'nullable|boolean',
            'fields.*.system_binding' => 'nullable|string|max:255',
            'fields.*.is_visible' => 'nullable|boolean',
        ]);

        if ($validated['location_path'] === KycFormSchemaService::LOCATION_PATH) {
            $validated['fields'] = $this->kycFormSchemaService->prepareFieldsForPersistence($validated['fields']);
        }

        DB::beginTransaction();
        try {
            $status = $validated['status'] ?? 'draft';
            $locationPath = $validated['location_path'];

            $linkedForm = $this->dynamicFormService->getLatestFormByLocation($locationPath);
            if ($linkedForm) {
                DB::rollBack();

                return redirect()
                    ->route('admin.forms.edit', $linkedForm->id)
                    ->withErrors(['error' => 'This form already has a live editable record. Open the same existing form and continue editing there.']);
            }
            
            // If publishing, check for existing published form at same location
            $replacesFormId = null;
            if ($status === 'published') {
                $existingForm = DynamicForm::where('location_path', $locationPath)
                    ->where('status', 'published')
                    ->where('is_active', true)
                    ->first();
                
                if ($existingForm) {
                    // Mark old form as replaced
                    $existingForm->update([
                        'status' => 'draft',
                        'is_active' => false,
                    ]);
                    $replacesFormId = $existingForm->id;
                }
            }
            
            $form = DynamicForm::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? $this->generateUniqueSlug($validated['name']),
                'description' => $validated['description'] ?? null,
                'location_path' => $locationPath,
                'form_type' => $validated['form_type'],
                'status' => $status,
                'settings' => $validated['settings'] ?? [],
                'replaces_form_id' => $replacesFormId,
                'created_by' => auth()->id(),
            ]);

            // Create form fields
            foreach ($validated['fields'] as $index => $fieldData) {
                DynamicFormField::create([
                    'form_id' => $form->id,
                    'field_key' => $fieldData['field_key'],
                    'field_type' => $fieldData['field_type'],
                    'label' => $fieldData['label'],
                    'placeholder' => $fieldData['placeholder'] ?? null,
                    'help_text' => $fieldData['help_text'] ?? null,
                    'options' => is_array($fieldData['options'] ?? null) ? $fieldData['options'] : null,
                    'validation' => is_array($fieldData['validation'] ?? null) ? $fieldData['validation'] : null,
                    'required' => isset($fieldData['required']) && $fieldData['required'],
                    'order' => $index,
                    'section' => $fieldData['section'] ?? 'default',
                    'styles' => is_array($fieldData['styles'] ?? null) ? $fieldData['styles'] : null,
                    'default_value' => $fieldData['default_value'] ?? null,
                    'is_system' => (bool) ($fieldData['is_system'] ?? false),
                    'system_binding' => $fieldData['system_binding'] ?? null,
                    'is_visible' => array_key_exists('is_visible', $fieldData) ? (bool) $fieldData['is_visible'] : true,
                ]);
            }

            DB::commit();

            return redirect()
                ->route('admin.forms.index')
                ->with('success', 'Form created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('DynamicFormController:store - Failed to create form', [
                'message' => $e->getMessage(),
                'location_path' => $request->input('location_path'),
                'name' => $request->input('name'),
            ]);
            return back()
                ->withErrors(['error' => 'Failed to create form: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Update the specified form
     */
    public function update(Request $request, DynamicForm $dynamicForm)
    {
        // Log to verify we're in update method, not store
        \Log::info('DynamicFormController:update - Method called', [
            'form_id' => $dynamicForm->id,
            'form_name' => $dynamicForm->name,
            'request_method' => $request->method(),
            'has_method_field' => $request->has('_method'),
            'method_field_value' => $request->input('_method'),
        ]);
        
        // Decode JSON fields if sent as string
        $fieldsData = $request->input('fields');
        if (is_string($fieldsData)) {
            $fieldsData = json_decode($fieldsData, true);
            $request->merge(['fields' => $fieldsData]);
        }

        // Decode JSON settings if sent as string
        $settingsData = $request->input('settings');
        if (is_string($settingsData)) {
            $settingsData = json_decode($settingsData, true);
            $request->merge(['settings' => $settingsData ?? []]);
        }

        // #region agent log
        \Log::info('DynamicFormController:update - Raw request data', [
            'raw_fields' => $request->input('fields'),
            'fields_data_type' => gettype($request->input('fields')),
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'H3'
        ]);
        // #endregion
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:dynamic_forms,slug,' . $dynamicForm->id,
            'description' => 'nullable|string',
            'location_path' => 'required|string|max:255',
            'form_type' => 'required|string|max:50',
            'status' => 'nullable|in:draft,published',
            'settings' => 'nullable|array',
            'fields' => 'required|array|min:1',
            'fields.*.field_key' => 'required|string|max:255',
            'fields.*.field_type' => 'required|string|max:50',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.placeholder' => 'nullable|string',
            'fields.*.help_text' => 'nullable|string',
            'fields.*.options' => 'nullable|array',
            'fields.*.options.*' => 'nullable|string',
            'fields.*.validation' => 'nullable|array',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.order' => 'nullable|integer',
            'fields.*.section' => 'nullable|string|max:255',
            'fields.*.styles' => 'nullable|array',
            'fields.*.default_value' => 'nullable',
            'fields.*.is_system' => 'nullable|boolean',
            'fields.*.system_binding' => 'nullable|string|max:255',
            'fields.*.is_visible' => 'nullable|boolean',
        ]);

        if ($validated['location_path'] === KycFormSchemaService::LOCATION_PATH) {
            $validated['fields'] = $this->kycFormSchemaService->prepareFieldsForPersistence($validated['fields']);
        }
        
        // #region agent log
        \Log::info('DynamicFormController:update - After validation', [
            'validated_fields_count' => count($validated['fields']),
            'validated_fields' => array_map(function($f) {
                return ['key' => $f['field_key'] ?? 'N/A', 'type' => $f['field_type'] ?? 'N/A'];
            }, $validated['fields']),
            'sessionId' => 'debug-session',
            'runId' => 'run1',
            'hypothesisId' => 'H3'
        ]);
        // #endregion

        DB::beginTransaction();
        try {
            $status = $validated['status'] ?? $dynamicForm->status ?? 'draft';
            $locationPath = $validated['location_path'];
            $isPublishing = ($status === 'published' && $dynamicForm->status !== 'published');
            
            // If changing from draft to published, handle replacement
            $replacesFormId = $dynamicForm->replaces_form_id;
            if ($isPublishing) {
                $existingForm = DynamicForm::where('location_path', $locationPath)
                    ->where('status', 'published')
                    ->where('is_active', true)
                    ->where('id', '!=', $dynamicForm->id)
                    ->first();
                
                if ($existingForm) {
                    // Mark old form as replaced
                    $existingForm->update([
                        'status' => 'draft',
                        'is_active' => false,
                    ]);
                    $replacesFormId = $existingForm->id;
                }
            }
            
            $dynamicForm->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? $dynamicForm->slug,
                'description' => $validated['description'] ?? null,
                'location_path' => $locationPath,
                'form_type' => $validated['form_type'],
                'status' => $status,
                'settings' => $validated['settings'] ?? [],
                'replaces_form_id' => $replacesFormId,
            ]);

            // Delete existing fields
            $dynamicForm->fields()->delete();

            // #region agent log
            \Log::info('DynamicFormController:update - Received fields', [
                'fields_count' => count($validated['fields']),
                'fields' => array_map(function($f) {
                    return ['key' => $f['field_key'] ?? 'N/A', 'type' => $f['field_type'] ?? 'N/A', 'raw_type' => $f['field_type'] ?? null];
                }, $validated['fields']),
                'form_id' => $dynamicForm->id,
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'H3'
            ]);
            // #endregion

            // Create updated form fields
            foreach ($validated['fields'] as $index => $fieldData) {
                // #region agent log
                \Log::info('DynamicFormController:update - Creating field', [
                    'index' => $index,
                    'field_key' => $fieldData['field_key'] ?? 'N/A',
                    'field_type' => $fieldData['field_type'] ?? 'N/A',
                    'field_type_type' => gettype($fieldData['field_type'] ?? null),
                    'label' => $fieldData['label'] ?? 'N/A',
                    'form_id' => $dynamicForm->id,
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'H3'
                ]);
                // #endregion
                
                $fieldTypeToSave = $fieldData['field_type'] ?? 'text';
                
                $createdField = DynamicFormField::create([
                    'form_id' => $dynamicForm->id,
                    'field_key' => $fieldData['field_key'],
                    'field_type' => $fieldTypeToSave, // Explicitly use the field type
                    'label' => $fieldData['label'],
                    'placeholder' => $fieldData['placeholder'] ?? null,
                    'help_text' => $fieldData['help_text'] ?? null,
                    'options' => is_array($fieldData['options'] ?? null) ? $fieldData['options'] : null,
                    'validation' => is_array($fieldData['validation'] ?? null) ? $fieldData['validation'] : null,
                    'required' => isset($fieldData['required']) && $fieldData['required'],
                    'order' => $index,
                    'section' => $fieldData['section'] ?? 'default',
                    'styles' => is_array($fieldData['styles'] ?? null) ? $fieldData['styles'] : null,
                    'default_value' => $fieldData['default_value'] ?? null,
                    'is_system' => (bool) ($fieldData['is_system'] ?? false),
                    'system_binding' => $fieldData['system_binding'] ?? null,
                    'is_visible' => array_key_exists('is_visible', $fieldData) ? (bool) $fieldData['is_visible'] : true,
                ]);
                
                // #region agent log
                \Log::info('DynamicFormController:update - Field created in DB', [
                    'field_id' => $createdField->id,
                    'saved_field_type' => $createdField->field_type,
                    'saved_field_key' => $createdField->field_key,
                    'fresh_from_db' => $createdField->fresh()->field_type, // Re-fetch from DB to verify
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'H3'
                ]);
                // #endregion
            }
            
            // #region agent log
            // After all fields are created, reload and verify
            $dynamicForm->refresh();
            $dynamicForm->load('fields');
            \Log::info('DynamicFormController:update - After save, reloaded form fields', [
                'form_id' => $dynamicForm->id,
                'fields_count' => $dynamicForm->fields->count(),
                'fields_from_db' => $dynamicForm->fields->map(function($f) {
                    return ['id' => $f->id, 'key' => $f->field_key, 'type' => $f->field_type];
                })->toArray(),
                'sessionId' => 'debug-session',
                'runId' => 'run1',
                'hypothesisId' => 'H3'
            ]);
            // #endregion

            DB::commit();

            return redirect()
                ->route('admin.forms.index')
                ->with('success', 'Form updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('DynamicFormController:update - Failed to update form', [
                'message' => $e->getMessage(),
                'form_id' => $dynamicForm->id,
                'location_path' => $request->input('location_path'),
            ]);
            return back()
                ->withErrors(['error' => 'Failed to update form: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified form
     */
    public function destroy(DynamicForm $dynamicForm)
    {
        try {
            $dynamicForm->delete();
            return redirect()
                ->route('admin.forms.index')
                ->with('success', 'Form deleted successfully!');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete form: ' . $e->getMessage()]);
        }
    }

    /**
     * Get existing forms in the system
     */
    private function getExistingForms(): array
    {
        $forms = [];

        $addIfRouteExists = function(string $routeName, array $data) use (&$forms) {
            try {
                $data['route'] = route($routeName);
                $forms[] = $data;
            } catch (\Exception $e) {
                // Route doesn't exist, skip
            }
        };

        // 1. Lead Creation Form (CRM Automation)
        $addIfRouteExists('crm.automation.leads.create', [
            'name'     => 'Lead Creation Form',
            'location' => 'CRM > Automation > Create Lead',
            'path'     => 'crm.automation.leads.create',
            'type'     => 'lead',
        ]);

        // 2. Lead Form (Standard - Leads module)
        try {
            $forms[] = [
                'name'        => 'New Lead Add Form',
                'location'    => 'Leads > Create',
                'path'        => 'leads.create',
                'type'        => 'lead',
                'route'       => route('leads.create'),
                'description' => 'Used on the main new lead page at /leads/create. Changes here affect the exact lead creation form shown to admin/CRM users.',
            ];
        } catch (\Exception $e) {
            // Skip if route is unavailable
        }

        // 3. Lead Edit Form (uses leads.index as preview since edit needs an ID)
        try {
            $forms[] = [
                'name'     => 'Lead Edit Form',
                'location' => 'Leads > Edit',
                'path'     => 'leads.edit',
                'type'     => 'lead',
                'route'    => route('leads.index'),
            ];
        } catch (\Exception $e) {
            // Skip
        }

        // 4. Meeting Form
        $addIfRouteExists('meetings.create', [
            'name'     => 'Meeting Form',
            'location' => 'Senior Manager > Create Meeting',
            'path'     => 'meetings.create',
            'type'     => 'meeting',
        ]);

        // 5. Site Visit Form
        $addIfRouteExists('site-visits.create', [
            'name'     => 'Site Visit Form',
            'location' => 'Senior Manager > Create Site Visit',
            'path'     => 'site-visits.create',
            'type'     => 'site_visit',
            'description' => 'Used on Senior Manager create site visit page. Changes here do not affect Lead Detail popup.',
        ]);

        $forms[] = [
            'name'        => 'Lead Detail Requirements Form',
            'location'    => 'Lead Detail > Edit Requirements',
            'path'        => 'lead-detail.requirements',
            'type'        => 'lead',
            'route'       => route('leads.index'),
            'description' => 'Used on Lead Detail page when user clicks Edit Requirements. Changes here affect the main Lead Form modal on lead detail.',
        ];

        $forms[] = [
            'name'        => 'Lead Detail Meeting Popup Form',
            'location'    => 'Lead Detail > Quick Actions > Meeting',
            'path'        => 'lead-detail.meeting',
            'type'        => 'meeting',
            'route'       => route('leads.index'),
            'description' => 'Used on Lead Detail page when user clicks Meeting. Changes here affect only the Lead Detail Meeting popup.',
        ];

        $forms[] = [
            'name'        => 'Lead Detail Site Visit Popup Form',
            'location'    => 'Lead Detail > Quick Actions > Site Visit',
            'path'        => 'lead-detail.site-visit',
            'type'        => 'site_visit',
            'route'       => route('leads.index'),
            'description' => 'Used on Lead Detail page when user clicks Site Visit. Changes here affect only the Lead Detail Site Visit popup.',
        ];

        $forms[] = [
            'name'        => 'Lead Detail Follow Up Popup Form',
            'location'    => 'Lead Detail > Quick Actions > Follow Up',
            'path'        => 'lead-detail.follow-up',
            'type'        => 'follow_up',
            'route'       => route('leads.index'),
            'description' => 'Used on Lead Detail page when user clicks Follow Up. Changes here affect only the Lead Detail Follow Up popup.',
        ];

        // 6. Call Log Form
        $addIfRouteExists('calls.create', [
            'name'     => 'Call Log Form',
            'location' => 'Calls > Create Call Log',
            'path'     => 'calls.create',
            'type'     => 'call',
        ]);

        // 7. Project Form
        $addIfRouteExists('projects.create', [
            'name'     => 'Project Form',
            'location' => 'Projects > Create',
            'path'     => 'projects.create',
            'type'     => 'project',
        ]);

        // 8. Closer Submit Form
        $addIfRouteExists('closers.index', [
            'name'     => 'Closer Submit Form',
            'location' => 'Closers > Submit Closer',
            'path'     => 'closers.index',
            'type'     => 'closer',
        ]);

        // 9. Incentive Submit Form
        $addIfRouteExists('finance-manager.incentives', [
            'name'     => 'Incentive Submit Form',
            'location' => 'Finance Manager > Incentives',
            'path'     => 'finance-manager.incentives',
            'type'     => 'incentive',
        ]);

        try {
            $forms[] = [
                'name' => 'Closer KYC Form Builder',
                'location' => 'Admin > Form > KYC Builder',
                'path' => KycFormSchemaService::LOCATION_PATH,
                'type' => KycFormSchemaService::FORM_TYPE,
                'route' => route('sales-manager.closed'),
                'description' => 'Controls the closer KYC submission form, finance KYC view, and CRM KYC review layout.',
            ];
        } catch (\Exception $e) {
            // Skip if route is unavailable
        }

        return $forms;
    }

    private function buildExistingFormEditUrl(array $form): string
    {
        $linkedForm = $this->dynamicFormService->getLatestFormByLocation($form['path']);

        if ($linkedForm) {
            return route('admin.forms.edit', $linkedForm->id);
        }

        return route('admin.forms.open-existing', [
            'name' => $form['name'],
            'path' => $form['path'],
            'type' => $form['type'],
        ]);
    }

    private function createLinkedDraftForm(string $name, string $locationPath, string $formType): DynamicForm
    {
        $form = DynamicForm::create([
            'name' => $name,
            'slug' => $this->generateUniqueSlug($name),
            'description' => 'Dynamic version of existing form: ' . $name,
            'location_path' => $locationPath,
            'form_type' => $formType,
            'status' => 'draft',
            'settings' => [],
            'created_by' => auth()->id(),
        ]);

        $detectedFields = $locationPath === KycFormSchemaService::LOCATION_PATH
            ? $this->kycFormSchemaService->getDefaultFieldDefinitions()
            : $this->formDetectionService->getFieldDefinitions($formType, $locationPath);

        foreach ($detectedFields as $index => $fieldData) {
            DynamicFormField::create([
                'form_id' => $form->id,
                'field_key' => $fieldData['field_key'],
                'field_type' => $fieldData['field_type'] ?? 'text',
                'label' => $fieldData['label'] ?? $fieldData['field_key'],
                'placeholder' => $fieldData['placeholder'] ?? null,
                'options' => is_array($fieldData['options'] ?? null) ? $fieldData['options'] : null,
                'required' => (bool) ($fieldData['required'] ?? false),
                'order' => $fieldData['order'] ?? $index,
                'section' => $fieldData['section'] ?? 'default',
                'is_system' => (bool) ($fieldData['is_system'] ?? false),
                'system_binding' => $fieldData['system_binding'] ?? null,
                'is_visible' => array_key_exists('is_visible', $fieldData) ? (bool) $fieldData['is_visible'] : true,
            ]);
        }

        return $form;
    }

    private function hydrateMissingFieldOptions(DynamicForm $dynamicForm): void
    {
        if (!$dynamicForm->location_path) {
            return;
        }

        if ($dynamicForm->location_path === KycFormSchemaService::LOCATION_PATH) {
            return;
        }

        $defaultFields = collect(
            $this->formDetectionService->getFieldDefinitions($dynamicForm->form_type, $dynamicForm->location_path)
        )->keyBy('field_key');

        foreach ($dynamicForm->fields as $field) {
            if ($field->field_key === 'interested_projects' && $dynamicForm->location_path === 'lead-detail.requirements') {
                if ($field->field_type !== 'select') {
                    $field->field_type = 'select';
                }

                if (empty($field->options)) {
                    $field->options = $this->getInterestedProjectOptions();
                }

                $field->save();
                continue;
            }

            if (!in_array($field->field_type, ['select', 'radio', 'checkbox'], true)) {
                continue;
            }

            if (!empty($field->options)) {
                continue;
            }

            $defaultField = $defaultFields->get($field->field_key);
            if (!empty($defaultField['options']) && is_array($defaultField['options'])) {
                $field->options = $defaultField['options'];
                $field->save();
            }
        }
    }

    private function hydrateMissingFieldDefinitions(DynamicForm $dynamicForm): void
    {
        if (!$dynamicForm->location_path) {
            return;
        }

        $defaultFields = collect(
            $dynamicForm->location_path === KycFormSchemaService::LOCATION_PATH
                ? $this->kycFormSchemaService->getDefaultFieldDefinitions()
                : $this->formDetectionService->getFieldDefinitions($dynamicForm->form_type, $dynamicForm->location_path)
        )->keyBy('field_key');

        if ($defaultFields->isEmpty()) {
            return;
        }

        $existingFields = $dynamicForm->fields->keyBy('field_key');
        $mergedFields = collect();

        foreach ($defaultFields as $fieldKey => $defaultField) {
            if ($existingFields->has($fieldKey)) {
                $field = $existingFields->get($fieldKey);

                if (empty($field->options) && !empty($defaultField['options']) && is_array($defaultField['options'])) {
                    $field->options = $defaultField['options'];
                }

                if (empty($field->placeholder) && !empty($defaultField['placeholder'])) {
                    $field->placeholder = $defaultField['placeholder'];
                }

                if (empty($field->section) && !empty($defaultField['section'])) {
                    $field->section = $defaultField['section'];
                }

                if ($field->label === null || $field->label === '') {
                    $field->label = $defaultField['label'] ?? $fieldKey;
                }

                $field->order = $defaultField['order'] ?? $field->order;
                $mergedFields->push($field);
                continue;
            }

            $mergedFields->push(new DynamicFormField([
                'form_id' => $dynamicForm->id,
                'field_key' => $defaultField['field_key'],
                'field_type' => $defaultField['field_type'] ?? 'text',
                'label' => $defaultField['label'] ?? $fieldKey,
                'placeholder' => $defaultField['placeholder'] ?? null,
                'required' => (bool) ($defaultField['required'] ?? false),
                'options' => is_array($defaultField['options'] ?? null) ? $defaultField['options'] : null,
                'order' => $defaultField['order'] ?? 0,
                'section' => $defaultField['section'] ?? 'default',
                'is_system' => (bool) ($defaultField['is_system'] ?? false),
                'system_binding' => $defaultField['system_binding'] ?? null,
                'is_visible' => array_key_exists('is_visible', $defaultField) ? (bool) $defaultField['is_visible'] : true,
            ]));
        }

        foreach ($dynamicForm->fields as $field) {
            if (!$defaultFields->has($field->field_key)) {
                $mergedFields->push($field);
            }
        }

        $dynamicForm->setRelation('fields', $mergedFields->sortBy('order')->values());
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (
            DynamicForm::withTrashed()
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
