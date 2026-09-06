@extends('layouts.app')

@section('title', 'Company Settings - ' . brand_name())
@section('page-title', 'Company Settings')

@section('header-actions')
    <a href="{{ route('admin.dashboard') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
    </a>
@endsection

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Success/Error Messages -->
    <div id="message-container" class="mb-6" style="display: none;">
        <div id="message-alert" class="p-4 rounded-lg"></div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] mb-6">
        <div class="border-b border-[#E5DED4]">
            <nav class="flex -mb-px flex-wrap">
                <button onclick="switchTab('company-profile')" id="tab-company-profile" class="tab-button active px-6 py-4 text-sm font-medium text-center border-b-2 border-brand text-brand-secondary">
                    <i class="fas fa-building mr-2"></i> Company Profile
                </button>
                <button onclick="switchTab('branding')" id="tab-branding" class="tab-button px-6 py-4 text-sm font-medium text-center border-b-2 border-transparent text-[#B3B5B4] hover:text-brand-primary hover:border-[#063A1C]">
                    <i class="fas fa-palette mr-2"></i> Branding Settings
                </button>
                <button onclick="switchTab('setup-center')" id="tab-setup-center" class="tab-button px-6 py-4 text-sm font-medium text-center border-b-2 border-transparent text-[#B3B5B4] hover:text-brand-primary hover:border-[#063A1C]">
                    <i class="fas fa-sliders mr-2"></i> Setup Center
                </button>
            </nav>
        </div>
    </div>

    <!-- Company Profile Tab -->
    <div id="tab-content-company-profile" class="tab-content">
        <form id="company-profile-form" class="space-y-6">
            @csrf
            
            <!-- Basic Information -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4 pb-2 border-b border-[#E5DED4]">
                    <i class="fas fa-info-circle mr-2"></i> Basic Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-brand-primary mb-2">
                            Company Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="company_name" id="company_name" 
                               value="{{ $companyProfile['basic']['company_name'] ?? '' }}"
                               required
                               class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                        <p class="text-xs text-[#B3B5B4] mt-1">Enter your company name</p>
                    </div>

                </div>
            </div>

            <!-- Contact Information -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4 pb-2 border-b border-[#E5DED4]">
                    <i class="fas fa-address-book mr-2"></i> Contact Information
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label for="address" class="block text-sm font-medium text-brand-primary mb-2">
                            Address
                        </label>
                        <textarea name="address" id="address" rows="3"
                                  class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">{{ $companyProfile['contact']['address'] ?? '' }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="city" class="block text-sm font-medium text-brand-primary mb-2">City</label>
                            <input type="text" name="city" id="city" 
                                   value="{{ $companyProfile['contact']['city'] ?? '' }}"
                                   class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                        </div>
                        <div>
                            <label for="state" class="block text-sm font-medium text-brand-primary mb-2">State</label>
                            <input type="text" name="state" id="state" 
                                   value="{{ $companyProfile['contact']['state'] ?? '' }}"
                                   class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                        </div>
                        <div>
                            <label for="pincode" class="block text-sm font-medium text-brand-primary mb-2">Pincode</label>
                            <input type="text" name="pincode" id="pincode" 
                                   value="{{ $companyProfile['contact']['pincode'] ?? '' }}"
                                   class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="country" class="block text-sm font-medium text-brand-primary mb-2">Country</label>
                            <input type="text" name="country" id="country" 
                                   value="{{ $companyProfile['contact']['country'] ?? 'India' }}"
                                   class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-brand-primary mb-2">
                                Phone Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="phone" id="phone" 
                                   value="{{ $companyProfile['contact']['phone'] ?? '' }}"
                                   required
                                   class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                            <p class="text-xs text-[#B3B5B4] mt-1">Primary contact number</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="landline" class="block text-sm font-medium text-brand-primary mb-2">Landline</label>
                            <input type="text" name="landline" id="landline" 
                                   value="{{ $companyProfile['contact']['landline'] ?? '' }}"
                                   class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-brand-primary mb-2">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input type="email" name="email" id="email" 
                                   value="{{ $companyProfile['contact']['email'] ?? '' }}"
                                   required
                                   class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                            <p class="text-xs text-[#B3B5B4] mt-1">Primary email address</p>
                        </div>
                    </div>

                    <div>
                        <label for="website" class="block text-sm font-medium text-brand-primary mb-2">Website URL</label>
                        <input type="url" name="website" id="website" 
                               value="{{ $companyProfile['contact']['website'] ?? '' }}"
                               class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                        <p class="text-xs text-[#B3B5B4] mt-1">Company website URL</p>
                    </div>
                </div>
            </div>

            <!-- Legal Information -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4 pb-2 border-b border-[#E5DED4]">
                    <i class="fas fa-gavel mr-2"></i> Legal Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="gst_number" class="block text-sm font-medium text-brand-primary mb-2">GST Number</label>
                        <input type="text" name="gst_number" id="gst_number" 
                               value="{{ $companyProfile['legal']['gst_number'] ?? '' }}"
                               pattern="[0-9A-Z]{15}"
                               class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                        <p class="text-xs text-[#B3B5B4] mt-1">15-character GST number</p>
                    </div>

                    <div>
                        <label for="pan_number" class="block text-sm font-medium text-brand-primary mb-2">PAN Number</label>
                        <input type="text" name="pan_number" id="pan_number" 
                               value="{{ $companyProfile['legal']['pan_number'] ?? '' }}"
                               pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}"
                               class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                        <p class="text-xs text-[#B3B5B4] mt-1">10-character PAN number</p>
                    </div>

                    <div>
                        <label for="registration_number" class="block text-sm font-medium text-brand-primary mb-2">RERA Reg No.</label>
                        <input type="text" name="registration_number" id="registration_number" 
                               value="{{ $companyProfile['legal']['registration_number'] ?? '' }}"
                               class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand text-brand-primary">
                        <p class="text-xs text-[#B3B5B4] mt-1">RERA registration number</p>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-[#205A44] text-white rounded-lg hover:bg-[#15803d] transition-colors duration-200 font-medium">
                    <i class="fas fa-save mr-2"></i> Save Company Profile
                </button>
            </div>
        </form>
    </div>

    <!-- Setup Center Tab -->
    <div id="tab-content-setup-center" class="tab-content" style="display: none;">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-[#6B7280]">One-time configuration</div>
                    <h3 class="text-2xl font-bold text-brand-primary mt-1">Setup Center</h3>
                    <p class="text-sm text-[#6B7280] mt-2">Office, attendance, payroll aur integration setup ko yahin se manage karo. Daily HR work sidebar me clean rahega.</p>
                </div>
                <span class="inline-flex items-center px-4 py-2 rounded-full bg-[#F3F7F4] text-[#205A44] text-sm font-semibold border border-[#DCE8E0]">
                    <i class="fas fa-shield-halved mr-2"></i> Admin setup only
                </span>
            </div>

            @php
                $setupGroups = [
                    [
                        'title' => 'Attendance Setup',
                        'icon' => 'fa-user-clock',
                        'description' => 'Offices, rules, user mapping, leave types aur outside punch permissions.',
                        'links' => [
                            ['label' => 'Offices / Locations', 'route' => route('admin.attendance.offices.index')],
                            ['label' => 'Attendance Rules', 'route' => route('admin.attendance.policies.index')],
                            ['label' => 'Assign Users', 'route' => route('admin.attendance.user-mappings.index')],
                            ['label' => 'Leave Types', 'route' => route('admin.attendance.leave-types.index')],
                            ['label' => 'Outside Punch Permissions', 'route' => route('admin.attendance.outside-punch-permissions.index')],
                            ['label' => 'Photo / Fraud Rules', 'route' => route('admin.hr.fraud-settings.index')],
                        ],
                    ],
                    [
                        'title' => 'Payroll Setup',
                        'icon' => 'fa-file-invoice-dollar',
                        'description' => 'Salary structure, salary heads, employee salary profiles aur payslip settings.',
                        'links' => [
                            ['label' => 'Salary Rules', 'route' => route('admin.hr.salary-structures.index')],
                            ['label' => 'Salary Heads', 'route' => route('admin.hr.deduction-heads.index')],
                            ['label' => 'Employee Salary', 'route' => route('admin.hr.salary-profiles.index')],
                            ['label' => 'Payslip Setup', 'route' => route('admin.hr.payslip-settings.index')],
                        ],
                    ],
                    [
                        'title' => 'People Setup',
                        'icon' => 'fa-id-badge',
                        'description' => 'Employee master, documents, advisor profile assets aur loan/builder library.',
                        'links' => [
                            ['label' => 'Employees', 'route' => route('admin.hr.employees.index')],
                            ['label' => 'Document Center', 'route' => route('admin.hr.document-center.index')],
                            ['label' => 'Advisor Profiles', 'route' => route('admin.advisor-profiles.index')],
                            ['label' => 'Builder Logos', 'route' => route('admin.builder-logos.index')],
                            ['label' => 'Loan Partners', 'route' => route('admin.loan-partners.index')],
                        ],
                    ],
                    [
                        'title' => 'System Integrations',
                        'icon' => 'fa-plug',
                        'description' => 'External integrations, forms, automation aur company-level system settings.',
                        'links' => [
                            ['label' => 'Integrations', 'route' => route('integrations.index')],
                            ['label' => 'Forms', 'route' => route('admin.forms.index')],
                            ['label' => 'Automation', 'route' => route('admin.automation.index')],
                            ['label' => 'System Settings', 'route' => route('admin.system-settings.index')],
                            ['label' => 'Mobile App Update', 'route' => route('admin.mobile-app-update.index')],
                        ],
                    ],
                ];
            @endphp

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                @foreach($setupGroups as $group)
                    <div class="rounded-2xl border border-[#E5DED4] bg-gradient-to-br from-white to-[#FBFAF7] p-5 shadow-sm">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-[#205A44] text-white flex items-center justify-center shrink-0">
                                <i class="fas {{ $group['icon'] }}"></i>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-lg font-bold text-brand-primary">{{ $group['title'] }}</h4>
                                <p class="text-sm text-[#6B7280] mt-1 leading-6">{{ $group['description'] }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-5">
                            @foreach($group['links'] as $link)
                                <a href="{{ $link['route'] }}" class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl border border-[#E5DED4] bg-white hover:border-[#205A44] hover:text-[#205A44] transition-colors text-sm font-semibold text-brand-primary">
                                    <span>{{ $link['label'] }}</span>
                                    <i class="fas fa-arrow-right text-xs text-[#B3B5B4]"></i>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Branding Settings Tab -->
    <div id="tab-content-branding" class="tab-content" style="display: none;">
        <form id="branding-form" class="space-y-6">
            @csrf
            
            <!-- Logo & Icons -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4 pb-2 border-b border-[#E5DED4]">
                    <i class="fas fa-image mr-2"></i> Logo & Icons
                </h3>
                
                <div class="max-w-md">
                    <!-- Company Logo (will be used for favicon too) -->
                    <div>
                        <label class="block text-sm font-medium text-brand-primary mb-2">
                            Company Logo <span class="text-xs text-[#B3B5B4] font-normal">(This logo will also be used as favicon)</span>
                        </label>
                        @include('admin.company-settings.components.file-upload', [
                            'fileType' => 'logo',
                            'currentFile' => $companyFiles->where('file_type', 'logo')->where('is_active', true)->first(),
                            'accept' => 'image/*',
                            'maxSize' => '2MB',
                            'dimensions' => 'Max 2000x2000px (Recommended: Square format for favicon)'
                        ])
                        <p class="text-xs text-[#B3B5B4] mt-2">
                            <i class="fas fa-info-circle mr-1"></i> This logo will be used throughout the system and automatically as favicon.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Color Templates -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6 mb-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4 pb-2 border-b border-[#E5DED4]">
                    <i class="fas fa-paint-brush mr-2"></i> Color Templates
                </h3>
                
                <p class="text-sm text-[#B3B5B4] mb-4">Choose a pre-made color template or customize your own colors</p>
                
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6" id="color-templates-grid">
                    @php
                        $templates = \App\Services\ColorTemplateService::getAllTemplates();
                        $activeTemplate = $brandingSettings['color_template'] ?? 'royal_green';
                    @endphp
                    @foreach($templates as $key => $template)
                        <div class="template-card cursor-pointer border-2 rounded-lg p-3 transition-all hover:shadow-md {{ $activeTemplate === $key ? 'border-brand ring-2 ring-brand' : 'border-gray-200' }}"
                             data-template="{{ $key }}"
                             onclick="selectTemplate('{{ $key }}')">
                            <div class="w-full h-20 rounded mb-2" 
                                 style="background: linear-gradient(135deg, {{ $template['gradient_start'] }}, {{ $template['gradient_middle'] }}, {{ $template['gradient_end'] }});">
                            </div>
                            <p class="text-xs font-medium text-center text-brand-primary">{{ $template['display_name'] }}</p>
                            @if($activeTemplate === $key)
                                <p class="text-xs text-center text-brand-secondary mt-1">
                                    <i class="fas fa-check-circle"></i> Active
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-4 mb-4">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="use_gradient" id="use_gradient" 
                               {{ ($brandingSettings['colors']['use_gradient'] ?? true) ? 'checked' : '' }}
                               class="mr-2 w-4 h-4 text-brand-secondary border-gray-300 rounded focus:ring-brand">
                        <span class="text-sm text-brand-primary">Use Gradient Colors</span>
                    </label>
                </div>

                <input type="hidden" name="color_template" id="color_template" value="{{ $activeTemplate }}">
            </div>

            <!-- Color Scheme -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4 pb-2 border-b border-[#E5DED4]">
                    <i class="fas fa-palette mr-2"></i> Color Scheme
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @include('admin.company-settings.components.color-picker', [
                        'name' => 'primary_color',
                        'label' => 'Primary Color',
                        'value' => $brandingSettings['colors']['primary_color'] ?? '#205A44',
                        'required' => true,
                        'helpText' => 'Main brand color (hex format)'
                    ])
                    
                    @include('admin.company-settings.components.color-picker', [
                        'name' => 'secondary_color',
                        'label' => 'Secondary Color',
                        'value' => $brandingSettings['colors']['secondary_color'] ?? '#063A1C',
                        'required' => true,
                        'helpText' => 'Secondary brand color (hex format)'
                    ])
                    
                    @include('admin.company-settings.components.color-picker', [
                        'name' => 'accent_color',
                        'label' => 'Accent Color',
                        'value' => $brandingSettings['colors']['accent_color'] ?? '#15803d',
                        'required' => false,
                        'helpText' => 'Accent color for highlights'
                    ])
                    
                    @include('admin.company-settings.components.color-picker', [
                        'name' => 'background_color',
                        'label' => 'Background Color',
                        'value' => $brandingSettings['colors']['background_color'] ?? '#FFFFFF',
                        'required' => false,
                        'helpText' => 'Default background color'
                    ])
                    
                    @include('admin.company-settings.components.color-picker', [
                        'name' => 'text_color',
                        'label' => 'Text Color',
                        'value' => $brandingSettings['colors']['text_color'] ?? '#063A1C',
                        'required' => false,
                        'helpText' => 'Default text color'
                    ])
                    
                    @include('admin.company-settings.components.color-picker', [
                        'name' => 'link_color',
                        'label' => 'Link Color',
                        'value' => $brandingSettings['colors']['link_color'] ?? '#205A44',
                        'required' => false,
                        'helpText' => 'Color for links'
                    ])
                </div>

                <!-- Color Preview -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-brand-primary mb-3">Color Preview</h4>
                    <div id="color-preview" class="flex flex-wrap gap-4">
                        <div class="color-preview-item">
                            <div class="w-16 h-16 rounded-lg border-2 border-gray-300" id="preview-primary" style="background-color: {{ $brandingSettings['colors']['primary_color'] ?? '#205A44' }};"></div>
                            <p class="text-xs text-center mt-1">Primary</p>
                        </div>
                        <div class="color-preview-item">
                            <div class="w-16 h-16 rounded-lg border-2 border-gray-300" id="preview-secondary" style="background-color: {{ $brandingSettings['colors']['secondary_color'] ?? '#063A1C' }};"></div>
                            <p class="text-xs text-center mt-1">Secondary</p>
                        </div>
                        <div class="color-preview-item">
                            <div class="w-16 h-16 rounded-lg border-2 border-gray-300" id="preview-accent" style="background-color: {{ $brandingSettings['colors']['accent_color'] ?? '#15803d' }};"></div>
                            <p class="text-xs text-center mt-1">Accent</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Templates -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4 pb-2 border-b border-[#E5DED4]">
                    <i class="fas fa-envelope mr-2"></i> Email Templates
                </h3>
                
                <div class="space-y-4">
                    <div>
                        <label for="email_header_template" class="block text-sm font-medium text-brand-primary mb-2">Email Header Template</label>
                        <textarea name="email_header_template" id="email_header_template" rows="5"
                                  class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand font-mono text-sm text-brand-primary">{{ $brandingSettings['email']['email_header_template'] ?? '' }}</textarea>
                        <p class="text-xs text-[#B3B5B4] mt-1">HTML template for email header</p>
                    </div>

                    <div>
                        <label for="email_footer_template" class="block text-sm font-medium text-brand-primary mb-2">Email Footer Template</label>
                        <textarea name="email_footer_template" id="email_footer_template" rows="5"
                                  class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand font-mono text-sm text-brand-primary">{{ $brandingSettings['email']['email_footer_template'] ?? '' }}</textarea>
                        <p class="text-xs text-[#B3B5B4] mt-1">HTML template for email footer</p>
                    </div>

                    <div>
                        <label for="email_signature_template" class="block text-sm font-medium text-brand-primary mb-2">Email Signature Template</label>
                        <textarea name="email_signature_template" id="email_signature_template" rows="4"
                                  class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand font-mono text-sm text-brand-primary">{{ $brandingSettings['email']['email_signature_template'] ?? '' }}</textarea>
                        <p class="text-xs text-[#B3B5B4] mt-1">Default email signature</p>
                    </div>
                </div>
            </div>

            <!-- Advanced -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
                <h3 class="text-lg font-semibold text-brand-primary mb-4 pb-2 border-b border-[#E5DED4]">
                    <i class="fas fa-code mr-2"></i> Advanced
                </h3>
                
                <div>
                    <label for="custom_css" class="block text-sm font-medium text-brand-primary mb-2">Custom CSS</label>
                    <textarea name="custom_css" id="custom_css" rows="10"
                              class="w-full px-4 py-2 bg-white border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand font-mono text-sm text-brand-primary">{{ $brandingSettings['advanced']['custom_css'] ?? '' }}</textarea>
                    <p class="text-xs text-[#B3B5B4] mt-1">Custom CSS code for additional styling</p>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" class="px-6 py-2 bg-[#205A44] text-white rounded-lg hover:bg-[#15803d] transition-colors duration-200 font-medium">
                    <i class="fas fa-save mr-2"></i> Save Branding Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/admin/company-settings.js') }}"></script>
@endpush
