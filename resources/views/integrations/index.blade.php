@extends('layouts.app')

@section('title', 'Integrations - ' . brand_name())
@section('page-title', 'Integrations')

@section('header-actions')
    <button onclick="showComingSoonNotification('Configuration')" class="px-4 py-2 bg-[#006BA6] text-white rounded-lg hover:bg-[#005985] transition-colors duration-200 text-sm font-medium">
        <i class="fas fa-cog mr-2"></i>
        Configuration
    </button>
@endsection

@section('content')
<style>
    .integration-logo-text {
        color: #fff;
        font-size: 1.15rem;
        font-weight: 900;
        letter-spacing: 0;
        line-height: 1;
    }

    .integration-action {
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
    }
</style>

<div class="w-full max-w-none mx-0 integrations-blue-theme">
    <!-- Integrations Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <!-- Email Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.email') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#4f46e5] flex items-center justify-center">
                        <i class="fas fa-envelope text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Email</h3>
                <p class="text-sm text-gray-500 mb-4">Email integration for sending and receiving emails</p>
                <div class="flex items-center mb-4">
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Coming Soon</span>
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.configuration') }}'" 
                        class="integration-action w-full px-4 py-2 bg-[#4f46e5] text-white rounded-lg hover:bg-[#4338ca] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Configuration
                </button>
            </div>
        </div>

        <!-- Calendar Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.calendar') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#7c3aed] flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Calendar</h3>
                <p class="text-sm text-gray-500 mb-4">Calendar integration for scheduling and events</p>
                <div class="flex items-center mb-4">
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Coming Soon</span>
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.configuration') }}'" 
                        class="integration-action w-full px-4 py-2 bg-[#7c3aed] text-white rounded-lg hover:bg-[#6d28d9] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Configuration
                </button>
            </div>
        </div>

        <!-- WhatsApp API Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.whatsapp') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#25D366] flex items-center justify-center">
                        <i class="fab fa-whatsapp text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">WhatsApp API</h3>
                <p class="text-sm text-gray-500 mb-4">WhatsApp Business API integration via Engage API</p>
                @php
                    $whatsappSettings = \App\Models\WhatsAppApiSettings::getSettings();
                @endphp
                <div class="flex items-center mb-4">
                    @if($whatsappSettings->is_active && $whatsappSettings->is_verified)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active & Verified</span>
                    @elseif($whatsappSettings->is_active)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Active (Not Verified)</span>
                    @elseif($whatsappSettings->api_token)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Configured</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Not Configured</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.whatsapp') }}'" 
                        class="integration-action w-full px-4 py-2 bg-[#128C7E] text-white rounded-lg hover:bg-[#075E54] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Configuration
                </button>
            </div>
        </div>

        <!-- Meta WABA Cloud API Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.meta-waba.index') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#25D366] flex items-center justify-center">
                        <i class="fab fa-whatsapp text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Meta WABA Cloud API</h3>
                <p class="text-sm text-gray-500 mb-4">Official Meta WhatsApp setup with webhook, templates, and test tools</p>
                @php
                    try {
                        $metaWabaSettings = \App\Models\MetaWabaSettings::getSettings();
                    } catch (\Exception $e) {
                        $metaWabaSettings = null;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($metaWabaSettings?->is_active && $metaWabaSettings?->is_verified)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active & Verified</span>
                    @elseif($metaWabaSettings?->is_active)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Active (Not Verified)</span>
                    @elseif($metaWabaSettings?->phone_number_id || $metaWabaSettings?->access_token)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Configured</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Not Configured</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.meta-waba.index') }}'"
                        class="integration-action w-full px-4 py-2 bg-[#128C7E] text-white rounded-lg hover:bg-[#075E54] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Configure WABA
                </button>
            </div>
        </div>

        <!-- Click2API WhatsApp Webhook -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.click2api-whatsapp') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#0f766e] flex items-center justify-center">
                        <i class="fas fa-link text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Click2API Webhook</h3>
                <p class="text-sm text-gray-500 mb-4">Webhook URL and challenge test for Click2API WhatsApp delivery reports</p>
                <div class="flex items-center mb-4">
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Ready</span>
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.click2api-whatsapp') }}'"
                        class="integration-action w-full px-4 py-2 bg-[#0f766e] text-white rounded-lg hover:bg-[#115e59] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-arrow-right mr-2"></i>
                    Open Setup
                </button>
            </div>
        </div>

        <!-- Sheet Integration (hub: Lead Import + Smart Import + Meta Sheet + Form Integration) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.sheet-integration') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#0F9D58] flex items-center justify-center">
                        <span class="integration-logo-text">GS</span>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Sheet Integration</h3>
                <p class="text-sm text-gray-500 mb-4">Lead Import, Smart Import, Meta Sheet aur Form Integration — sab ek jagah</p>
                @php
                    try {
                        $sheetTotalActive = \App\Models\GoogleSheetsConfig::where('is_active', true)->count();
                    } catch (\Exception $e) {
                        $sheetTotalActive = 0;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($sheetTotalActive > 0)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">{{ $sheetTotalActive }} Active</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Not Configured</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.sheet-integration') }}'"
                        class="integration-action w-full px-4 py-2 bg-[#0F9D58] text-white rounded-lg hover:bg-[#0b8043] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-arrow-right mr-2"></i>
                    Open
                </button>
            </div>
        </div>

        <!-- Facebook Lead Ads (standalone – direct webhook + Graph API) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.facebook-lead-ads.index') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#0b6b34] flex items-center justify-center">
                        <i class="fab fa-facebook text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Facebook Lead Ads</h3>
                <p class="text-sm text-gray-500 mb-4">Direct webhook + Graph API. One-click form mapping; leads sync here first (standalone).</p>
                @php
                    try {
                        $fbLeadAdsSettings = \App\Models\FbLeadAdsSettings::getSettings();
                        $fbLeadAdsConfigured = (!empty($fbLeadAdsSettings->page_access_token) && !empty($fbLeadAdsSettings->page_id))
                            || \App\Models\FbPage::whereNotNull('page_access_token')->exists();
                    } catch (\Exception $e) {
                        $fbLeadAdsConfigured = false;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($fbLeadAdsConfigured)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Configured</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Not configured</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.facebook-lead-ads.index') }}'" 
                        class="integration-action w-full px-4 py-2 bg-[#0b6b34] text-white rounded-lg hover:bg-[#09582b] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Configure
                </button>
            </div>
        </div>

        <!-- Facebook OAuth Connector (parallel App Review flow) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.facebook-connector.index') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#1877F2] flex items-center justify-center">
                        <i class="fab fa-facebook-f text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Facebook OAuth Connector</h3>
                <p class="text-sm text-gray-500 mb-4">App Review-ready Login for Business connector. Old manual token flow remains separate.</p>
                @php
                    try {
                        $metaOauthConnected = \App\Models\MetaOauthConnection::where('status', 'connected')->exists();
                    } catch (\Exception $e) {
                        $metaOauthConnected = false;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($metaOauthConnected)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Connected</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Not Connected</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.facebook-connector.index') }}'"
                        class="integration-action w-full px-4 py-2 bg-[#1877F2] text-white rounded-lg hover:bg-[#166fe5] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-link mr-2"></i>
                    Open Connector
                </button>
            </div>
        </div>

        <!-- Custom Website Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.website.index') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#0891b2] flex items-center justify-center">
                        <i class="fas fa-globe text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Custom Website</h3>
                <p class="text-sm text-gray-500 mb-4">Flexible JSON website lead intake with editable mapping, live preview, fallback assignment, and Mini Postman testing.</p>
                @php
                    try {
                        $websiteIntegrationCount = \App\Models\WebsiteIntegration::count();
                    } catch (\Exception $e) {
                        $websiteIntegrationCount = 0;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($websiteIntegrationCount > 0)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">{{ $websiteIntegrationCount }} Configured</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Not Configured</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.website.index') }}'"
                        class="integration-action w-full px-4 py-2 bg-[#0891b2] text-white rounded-lg hover:bg-[#0e7490] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Configure
                </button>
            </div>
        </div>

        <!-- BulkSMSPlans IVR Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.bulksmsplans-ivr.index') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#001A2A] flex items-center justify-center">
                        <i class="fas fa-headset text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">BulkSMSPlans IVR</h3>
                <p class="text-sm text-gray-500 mb-4">Inbound IVR webhook with token/IP security, lead creation, call logs, and missed-call tasks</p>
                @php
                    try {
                        $bulkSmsPlansIvrActive = \App\Models\BulkSmsPlansIvrSetting::getSettings()->is_enabled ?? false;
                    } catch (\Exception $e) {
                        $bulkSmsPlansIvrActive = false;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($bulkSmsPlansIvrActive)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.bulksmsplans-ivr.index') }}'"
                        class="integration-action w-full px-4 py-2 bg-[#001A2A] text-white rounded-lg hover:bg-[#00324f] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i> Configuration
                </button>
            </div>
        </div>

        <!-- Magic Bricks Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.magic-bricks') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#e31e24] flex items-center justify-center">
                        <span class="integration-logo-text">MB</span>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Magic Bricks</h3>
                <p class="text-sm text-gray-500 mb-4">Magic Bricks real estate platform integration</p>
                <div class="flex items-center mb-4">
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Coming Soon</span>
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.configuration') }}'" 
                        class="integration-action w-full px-4 py-2 bg-[#e31e24] text-white rounded-lg hover:bg-[#b91c1c] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Configuration
                </button>
            </div>
        </div>

        <!-- Housing Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.housing') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#6d28d9] flex items-center justify-center">
                        <span class="integration-logo-text">H</span>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Housing</h3>
                <p class="text-sm text-gray-500 mb-4">Housing.com real estate platform integration</p>
                <div class="flex items-center mb-4">
                    <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Coming Soon</span>
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.configuration') }}'" 
                        class="integration-action w-full px-4 py-2 bg-[#6d28d9] text-white rounded-lg hover:bg-[#5b21b6] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Configuration
                </button>
            </div>
        </div>

        <!-- 99acres Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.99acres.index') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#ef3e36] flex items-center justify-center">
                        <span class="integration-logo-text">99</span>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">99acres</h3>
                <p class="text-sm text-gray-500 mb-4">Push webhook for direct 99acres lead intake with duplicate handling</p>
                @php
                    try {
                        $ninetyNineAcresSettings = \App\Models\NinetyNineAcresSetting::getSettings();
                    } catch (\Exception $e) {
                        $ninetyNineAcresSettings = null;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($ninetyNineAcresSettings?->is_enabled)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                    @elseif($ninetyNineAcresSettings?->api_key)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Configured</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Not Configured</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.99acres.index') }}'"
                        class="integration-action w-full px-4 py-2 bg-[#ef3e36] text-white rounded-lg hover:bg-[#dc2626] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Configure
                </button>
            </div>
        </div>

        <!-- Pabbly Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.pabbly') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#f97316] flex items-center justify-center">
                        <span class="integration-logo-text">P</span>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Pabbly</h3>
                <p class="text-sm text-gray-500 mb-4">Pabbly webhook integration for lead automation</p>
                @php
                    try {
                        $pabblySettings = \App\Models\PabblyIntegrationSettings::getSettings();
                        $pabblyIsActive = $pabblySettings->is_active ?? false;
                    } catch (\Exception $e) {
                        $pabblyIsActive = false;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($pabblyIsActive)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.pabbly') }}'" 
                        class="integration-action w-full px-4 py-2 bg-[#f97316] text-white rounded-lg hover:bg-[#ea580c] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Configuration
                </button>
            </div>
        </div>

        <!-- MCube Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.mcube.index') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#fb923c] flex items-center justify-center">
                        <span class="integration-logo-text">MC</span>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">MCube</h3>
                <p class="text-sm text-gray-500 mb-4">Auto-capture call leads, assign agents & save recordings via MCube webhook</p>
                @php
                    try {
                        $mcubeActive = \App\Models\McubeSetting::getSettings()->is_enabled ?? false;
                    } catch (\Exception $e) {
                        $mcubeActive = false;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($mcubeActive)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.mcube.index') }}'"
                        class="integration-action w-full px-4 py-2 bg-[#fb923c] text-white rounded-lg hover:bg-[#f97316] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i> Configuration
                </button>
            </div>
        </div>

        <!-- MCube Outbound Integration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('integrations.mcube.outbound') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#2563eb] flex items-center justify-center">
                        <i class="fas fa-phone-volume text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">MCube Outbound</h3>
                <p class="text-sm text-gray-500 mb-4">Click-to-call, fresh lead auto-call, outbound token and test settings</p>
                @php
                    try {
                        $mcubeOutboundActive = \App\Models\McubeSetting::getSettings()->outbound_enabled ?? false;
                    } catch (\Exception $e) {
                        $mcubeOutboundActive = false;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($mcubeOutboundActive)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('integrations.mcube.outbound') }}'"
                        class="integration-action w-full px-4 py-2 bg-[#2563eb] text-white rounded-lg hover:bg-[#1d4ed8] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i> Configuration
                </button>
            </div>
        </div>


        <!-- Lead Assignment System -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-200 cursor-pointer" onclick="window.location.href='{{ route('lead-assignment.index') }}'">
            <div class="p-6">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 rounded-full bg-[#0f766e] flex items-center justify-center">
                        <i class="fas fa-users-cog text-white text-2xl"></i>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Lead Assignment</h3>
                <p class="text-sm text-gray-500 mb-4">Manage lead assignments and sales executive configurations</p>
                @php
                    try {
                        $telecallerCount = \App\Models\User::whereHas('role', function($q) {
                            $q->where('name', 'telecaller');
                        })->count();
                        $leadAssignmentActive = $telecallerCount > 0;
                    } catch (\Exception $e) {
                        $leadAssignmentActive = false;
                        $telecallerCount = 0;
                    }
                @endphp
                <div class="flex items-center mb-4">
                    @if($leadAssignmentActive)
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">{{ $telecallerCount }} Sales Executives</span>
                    @else
                        <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">No Sales Executives</span>
                    @endif
                </div>
                <button onclick="event.stopPropagation(); window.location.href='{{ route('lead-assignment.index') }}'" 
                        class="integration-action w-full px-4 py-2 bg-[#0f766e] text-white rounded-lg hover:bg-[#115e59] transition-colors duration-200 text-sm font-medium">
                    <i class="fas fa-cog mr-2"></i>
                    Manage
                </button>
            </div>
        </div>
    </div>
</div>


@push('scripts')
<script>
    function showComingSoonNotification(integrationName) {
        alert(integrationName + ' Integration\n\nComing Soon!\n\nThis integration is currently under development and will be available soon.');
    }
</script>
@endpush
@endsection
