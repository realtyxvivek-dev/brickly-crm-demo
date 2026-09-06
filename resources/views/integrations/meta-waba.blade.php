@extends('layouts.app')

@php
    $metaWabaRoutePrefix = request()->routeIs('ad-manager.meta-waba.*') ? 'ad-manager.meta-waba.' : 'integrations.meta-waba.';
    $metaAutomationRoute = request()->routeIs('ad-manager.*') ? route('ad-manager.automation.index') : route('admin.whatsapp-automation.index');
    $metaWabaBackRoute = request()->routeIs('ad-manager.*') ? route('ad-manager.meta.index') : route('integrations.index');
@endphp

@section('title', 'Meta WABA Center - ' . brand_name())
@section('page-title', 'Meta WABA Center')

@section('header-actions')
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ $metaAutomationRoute }}" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-semibold">
            <i class="fas fa-robot mr-2"></i> Automations
        </a>
        <a href="{{ $metaWabaBackRoute }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-semibold">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>
    </div>
@endsection

@push('styles')
<style>
    .alert { padding: 14px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 14px; }
    .alert-success { background:#d1fae5; color:#065f46; border:1px solid #86efac; }
    .alert-error { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
    .waba-tab { border:1px solid #d1d5db; color:#374151; background:#fff; }
    .waba-tab.active { border-color:#047857; color:#065f46; background:#ecfdf5; }
    .waba-card { background:#fff; border:1px solid #e5e7eb; border-radius:18px; box-shadow:0 10px 28px rgba(15,23,42,.06); }
    .meta-waba-shell { width:100%; max-width:none; margin:0; }
</style>
@endpush

@section('content')
<div class="meta-waba-shell space-y-6">
    <div id="message-container" style="display:none;">
        <div id="message-alert" class="alert"></div>
    </div>
    @php
        $privacyUrl = $settings->privacy_policy_url ?: route('legal.privacy');
        $termsUrl = $settings->terms_url ?: route('legal.terms');
        $dataDeletionUrl = $settings->data_deletion_url ?: route('legal.data-deletion');
        $stepConnected = filled($settings->waba_id) && filled($settings->phone_number_id);
        $stepVerified = (bool) $settings->is_verified;
        $stepTemplates = filled($settings->templates_synced_at);
        $stepStatus = $settings->connection_status ?: 'manual';
    @endphp

    <div class="waba-card p-6 bg-gradient-to-br from-emerald-50 via-white to-slate-50">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-2xl bg-green-600 text-white flex items-center justify-center text-2xl">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Meta WABA Control Center</h2>
                        <p class="text-sm text-gray-600">Templates, bulk campaigns, automation and official Cloud API setup.</p>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="px-3 py-1 text-xs font-bold rounded-full {{ $settings->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                    {{ $settings->is_active ? 'Active' : 'Inactive' }}
                </span>
                <span class="px-3 py-1 text-xs font-bold rounded-full {{ $settings->is_verified ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                    {{ $settings->is_verified ? 'Verified' : 'Not Verified' }}
                </span>
                <span class="px-3 py-1 text-xs font-bold rounded-full {{ $defaultSender === 'meta_waba' ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700' }}">
                    Default: {{ $defaultSender === 'meta_waba' ? 'Meta WABA' : 'Third party' }}
                </span>
                <span class="px-3 py-1 text-xs font-bold rounded-full bg-slate-100 text-slate-700">
                    Status: {{ str_replace('_', ' ', ucfirst($stepStatus)) }}
                </span>
            </div>
        </div>
    </div>

    <div class="waba-card p-5">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900">Connected API Numbers</h3>
                <p class="text-sm text-gray-500">Current working sender safe rahega. Naya number add/test karne ke liye New account mode use karo.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($accounts as $account)
                    <a href="{{ route($metaWabaRoutePrefix . 'index', ['account_id' => $account->id]) }}"
                       class="px-3 py-2 rounded-xl border text-sm font-bold {{ $selectedAccount?->id === $account->id ? 'border-green-500 bg-green-50 text-green-800' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                        {{ $account->display_phone_number ?: $account->name ?: ('Account #' . $account->id) }}
                        @if($account->is_default)
                            <span class="ml-1 text-xs text-blue-700">(Default)</span>
                        @endif
                    </a>
                @endforeach
                <a href="{{ route($metaWabaRoutePrefix . 'index', ['new_account' => 1]) }}"
                   class="px-3 py-2 rounded-xl border border-blue-200 bg-blue-50 text-blue-700 text-sm font-bold">
                    <i class="fas fa-plus mr-1"></i> Add API Number
                </a>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" class="waba-tab active px-4 py-2 rounded-xl text-sm font-bold" data-tab="setup">Setup</button>
        <button type="button" class="waba-tab px-4 py-2 rounded-xl text-sm font-bold" data-tab="templates">Templates</button>
        <button type="button" class="waba-tab px-4 py-2 rounded-xl text-sm font-bold" data-tab="campaigns">Bulk Campaigns</button>
        <button type="button" class="waba-tab px-4 py-2 rounded-xl text-sm font-bold" data-tab="calls">Calls</button>
        <button type="button" class="waba-tab px-4 py-2 rounded-xl text-sm font-bold" data-tab="logs">Logs & Automation</button>
    </div>

    <section id="tab-setup" class="tab-panel">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
            <div class="waba-card p-6 xl:col-span-2">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Connect WhatsApp</h3>
                        <p class="text-sm text-gray-500 mt-1">Meta reviewer/client apne Meta login se Business, WABA aur phone select karega. CRM returned IDs save karega.</p>
                    </div>
                    <button id="connect-whatsapp-btn" type="button" class="px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                        <i class="fab fa-whatsapp mr-2"></i>Connect WhatsApp
                    </button>
                </div>
                <div class="mt-5 grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
                    <div class="rounded-lg border p-3 {{ $stepConnected ? 'border-green-200 bg-green-50 text-green-800' : 'border-gray-200 bg-gray-50 text-gray-700' }}"><b>1. Connect</b><br>{{ $stepConnected ? 'Done' : 'Pending' }}</div>
                    <div class="rounded-lg border p-3 {{ $stepVerified ? 'border-green-200 bg-green-50 text-green-800' : 'border-gray-200 bg-gray-50 text-gray-700' }}"><b>2. Verify</b><br>{{ $stepVerified ? 'Done' : 'Pending' }}</div>
                    <div class="rounded-lg border p-3 {{ filled($settings->phone_number_id) ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-gray-200 bg-gray-50 text-gray-700' }}"><b>3. Register</b><br>PIN if needed</div>
                    <div class="rounded-lg border p-3 {{ $stepTemplates ? 'border-green-200 bg-green-50 text-green-800' : 'border-gray-200 bg-gray-50 text-gray-700' }}"><b>4. Templates</b><br>{{ $stepTemplates ? $settings->last_template_sync_count . ' synced' : 'Pending' }}</div>
                    <div class="rounded-lg border p-3 border-gray-200 bg-gray-50 text-gray-700"><b>5. Send test</b><br>Approved template</div>
                </div>
                @if($settings->last_error)
                    <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                        <b>Action required:</b> {{ $settings->last_error }}
                    </div>
                @endif
            </div>
            <div class="waba-card p-6">
                <h3 class="text-lg font-bold text-gray-900">Reviewer How To Test</h3>
                <ol class="mt-3 space-y-2 text-sm text-gray-700 list-decimal list-inside">
                    <li>Open Integrations → Meta WABA.</li>
                    <li>Click Connect WhatsApp and finish Meta popup.</li>
                    <li>Click Verify, then Sync Templates.</li>
                    <li>Send one approved template test.</li>
                    <li>Reply from WhatsApp and check logs/conversation.</li>
                </ol>
                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <a class="px-2 py-1 rounded bg-blue-50 text-blue-700" href="{{ $privacyUrl }}" target="_blank">Privacy</a>
                    <a class="px-2 py-1 rounded bg-blue-50 text-blue-700" href="{{ $termsUrl }}" target="_blank">Terms</a>
                    <a class="px-2 py-1 rounded bg-blue-50 text-blue-700" href="{{ $dataDeletionUrl }}" target="_blank">Data deletion</a>
                </div>
            </div>
        </div>

        <div class="waba-card p-6">
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Official Meta Cloud API Setup</h3>
                    <p class="text-sm text-gray-500">Meta App Settings + WABA credentials yahan save honge. Manual setup advanced fallback ke liye available hai.</p>
                </div>
                <a href="{{ asset('docs/meta-waba-setup-guide.html') }}" target="_blank" class="px-3 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-sm font-semibold">
                    <i class="fas fa-book-open mr-2"></i> Guide
                </a>
            </div>

            <form id="meta-waba-form">
                @csrf
                @if($selectedAccount && !request()->boolean('new_account'))
                    <input type="hidden" name="meta_waba_account_id" value="{{ $selectedAccount->id }}">
                @endif
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Account label</label>
                        <input type="text" name="name" value="{{ request()->boolean('new_account') ? '' : ($settings->name ?? '') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Base Infra - Sales number">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Meta App ID</label>
                        <input type="text" name="meta_app_id" value="{{ $settings->meta_app_id }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="App ID from Meta Developers">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Embedded Signup Configuration ID</label>
                        <input type="text" name="embedded_signup_configuration_id" value="{{ $settings->embedded_signup_configuration_id }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Configuration ID from Embedded Signup Builder">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">WABA ID</label>
                        <input type="text" name="waba_id" value="{{ $settings->waba_id }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Business Account ID</label>
                        <input type="text" name="business_account_id" value="{{ $settings->business_account_id }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Meta Business ID</label>
                        <input type="text" name="meta_business_id" value="{{ $settings->meta_business_id }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Phone Number ID</label>
                        <input type="text" name="phone_number_id" value="{{ $settings->phone_number_id }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Graph Version</label>
                        <input type="text" name="graph_version" value="{{ $settings->graph_version ?: 'v20.0' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Access Token</label>
                        <div class="flex gap-2">
                            <input id="access-token" type="password" name="access_token" value="{{ $settings->access_token }}" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg">
                            <button type="button" onclick="toggleSecretField('access-token', this)" class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg" title="Show / hide token"><i class="fas fa-eye"></i></button>
                            <button type="button" onclick="copyFieldValue('access-token', 'Access token copied.')" class="px-3 py-2 bg-blue-100 text-blue-700 rounded-lg" title="Copy token"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">App Secret <span class="text-gray-400">(optional)</span></label>
                        <div class="flex gap-2">
                            <input id="app-secret" type="password" name="app_secret" value="{{ $settings->app_secret }}" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg">
                            <button type="button" onclick="toggleSecretField('app-secret', this)" class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg" title="Show / hide secret"><i class="fas fa-eye"></i></button>
                            <button type="button" onclick="copyFieldValue('app-secret', 'App secret copied.')" class="px-3 py-2 bg-blue-100 text-blue-700 rounded-lg" title="Copy secret"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Webhook URL</label>
                        <div class="flex gap-2">
                            <input id="webhook-url" type="text" readonly value="{{ url('/api/webhooks/meta-waba') }}" class="flex-1 px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg">
                            <button type="button" onclick="copyFieldValue('webhook-url', 'Webhook URL copied.')" class="px-3 py-2 bg-blue-100 text-blue-700 rounded-lg"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Webhook Verify Token</label>
                        <input type="text" name="webhook_verify_token" value="{{ $settings->webhook_verify_token }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Privacy Policy URL</label>
                        <input type="url" name="privacy_policy_url" value="{{ $privacyUrl }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Terms URL</label>
                        <input type="url" name="terms_url" value="{{ $termsUrl }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Data Deletion URL</label>
                        <input type="url" name="data_deletion_url" value="{{ $dataDeletionUrl }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-5">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" {{ $settings->is_active ? 'checked' : '' }} class="w-4 h-4 text-green-600 border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700">Activate Meta WABA</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" name="make_default_sender" value="1" {{ $defaultSender === 'meta_waba' ? 'checked' : '' }} class="w-4 h-4 text-green-600 border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700">Use Meta WABA as default sender</span>
                    </label>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="submit" class="px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold"><i class="fas fa-save mr-2"></i>{{ request()->boolean('new_account') ? 'Save New API Number' : 'Save WABA' }}</button>
                    @if($selectedAccount)
                        <button type="button" onclick="setDefaultWaba()" class="px-5 py-2 bg-slate-800 text-white rounded-lg hover:bg-slate-900 font-semibold"><i class="fas fa-star mr-2"></i>Set Default</button>
                    @endif
                    <button type="button" onclick="verifyWaba()" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold"><i class="fas fa-check-circle mr-2"></i>Verify</button>
                    <button type="button" onclick="syncTemplates()" class="px-5 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-semibold"><i class="fas fa-sync-alt mr-2"></i>Sync Templates</button>
                    <button type="button" onclick="disconnectWaba()" class="px-5 py-2 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg hover:bg-rose-100 font-semibold"><i class="fas fa-unlink mr-2"></i>Disconnect</button>
                </div>
            </form>

            <div class="mt-6 border border-amber-200 bg-amber-50 rounded-xl p-4">
                <h4 class="font-bold text-amber-900">Cloud API phone registration</h4>
                <p class="text-sm text-amber-800 mt-1">Agar send par #200 permission/account error aaye, phone number ko Cloud API ke liye register karo. Two-step verification OFF ho to PIN blank chhod sakte ho; ON ho to wahi 6-digit PIN enter karo.</p>
                <form id="register-phone-form" class="mt-3 flex flex-col sm:flex-row gap-3">
                    @csrf
                    @if($selectedAccount)
                        <input type="hidden" name="meta_waba_account_id" value="{{ $selectedAccount->id }}">
                    @endif
                    <input type="password" name="pin" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" class="px-3 py-2 border border-amber-300 rounded-lg bg-white" placeholder="6-digit PIN (optional)">
                    <button type="submit" class="px-5 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-semibold">
                        <i class="fas fa-plug mr-2"></i>Register Phone
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section id="tab-templates" class="tab-panel hidden">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="waba-card p-6">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 mb-5">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Create Meta Template</h3>
                        <p class="text-sm text-gray-500">Template Meta review me jayega. Approved hone ke baad bulk/automation me use hoga.</p>
                    </div>
                    <div class="text-xs text-slate-600 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2">
                        <strong>Rule:</strong> name lowercase, numbers aur underscore only. Body variables @{{1}}, @{{2}} format me.
                    </div>
                </div>
                <form id="create-template-form" class="space-y-5">
                    @csrf
                    @if($selectedAccount)
                        <input type="hidden" name="meta_waba_account_id" value="{{ $selectedAccount->id }}">
                    @endif
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="md:col-span-1">
                            <label class="block text-xs font-bold text-gray-600 mb-1">Template name</label>
                            <input type="text" name="name" required placeholder="site_visit_offer" class="w-full px-3 py-2 border border-gray-300 rounded-lg" autocomplete="off">
                            <p class="text-[11px] text-gray-500 mt-1">Example: <b>site_visit_offer</b>. Space/capital allowed nahi hai.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Category</label>
                            <select name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="MARKETING">Marketing - offers, promotions, follow-up</option>
                                <option value="UTILITY">Utility - order, appointment, updates</option>
                                <option value="AUTHENTICATION">Authentication - OTP/code only</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Language</label>
                            <select name="language" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="en_US">English (US) - en_US</option>
                                <option value="en">English - en</option>
                                <option value="hi">Hindi - hi</option>
                                <option value="hi_IN">Hindi India - hi_IN</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
                        <div class="xl:col-span-3 space-y-4">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-bold text-gray-600">Header text</label>
                                    <span id="template-header-count" class="text-[11px] text-gray-400">0/60</span>
                                </div>
                                <input type="text" name="header_text" maxlength="60" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Optional: Base Infra Update">
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-bold text-gray-600">Body text</label>
                                    <span id="template-body-count" class="text-[11px] text-gray-400">0/1024</span>
                                </div>
                                <textarea name="body_text" required rows="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Hi @{{1}}, thanks for your interest in @{{2}}. Our team will call you shortly."></textarea>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" onclick="insertTemplateVariable()" class="px-3 py-1.5 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-xs font-bold">+ Variable</button>
                                    <button type="button" onclick="applyTemplatePreset('site_visit')" class="px-3 py-1.5 bg-slate-50 text-slate-700 border border-slate-200 rounded-lg text-xs font-bold">Site visit preset</button>
                                    <button type="button" onclick="applyTemplatePreset('payment')" class="px-3 py-1.5 bg-slate-50 text-slate-700 border border-slate-200 rounded-lg text-xs font-bold">Payment preset</button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Variable sample values</label>
                                <textarea name="body_samples" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Vivek&#10;Noida Project"></textarea>
                                <p id="template-variable-help" class="text-xs text-gray-500 mt-1">Body me variables add karoge to sample values line-wise do.</p>
                            </div>
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-xs font-bold text-gray-600">Footer text</label>
                                    <span id="template-footer-count" class="text-[11px] text-gray-400">0/60</span>
                                </div>
                                <input type="text" name="footer_text" maxlength="60" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Optional: Reply STOP to opt out">
                            </div>
                        </div>
                        <div class="xl:col-span-2">
                            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4 sticky top-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="text-sm font-black text-slate-900">Live preview</div>
                                    <span id="template-preview-status" class="px-2 py-1 rounded-full bg-slate-100 text-slate-600 text-[11px] font-bold">Draft</span>
                                </div>
                                <div class="bg-white rounded-2xl border border-emerald-100 shadow-sm p-4">
                                    <div id="preview-header" class="font-bold text-slate-900 mb-2 hidden"></div>
                                    <div id="preview-body" class="text-sm text-slate-800 whitespace-pre-wrap">Body preview yahan dikhega.</div>
                                    <div id="preview-footer" class="text-xs text-slate-500 mt-3 hidden"></div>
                                    <div id="preview-buttons" class="mt-4 space-y-2"></div>
                                </div>
                                <div id="template-checklist" class="mt-3 space-y-1 text-xs text-slate-600"></div>
                            </div>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Template buttons</label>
                                <p class="text-xs text-gray-500">Total max 3 buttons. Quick replies first, phir call ya website CTA.</p>
                            </div>
                            <button type="button" onclick="addQuickReplyButton()" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-xs font-bold hover:bg-blue-700">
                                <i class="fas fa-plus mr-1"></i>Add quick reply
                            </button>
                        </div>

                        <div id="quick-reply-button-list" class="space-y-2">
                            <div class="quick-reply-row flex gap-2">
                                <input type="text" name="quick_reply_buttons[]" maxlength="25" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-white" placeholder="Quick reply: Interested">
                                <button type="button" onclick="removeQuickReplyButton(this)" class="px-3 py-2 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg"><i class="fas fa-times"></i></button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                            <div class="rounded-xl border border-white bg-white p-3">
                                <div class="text-xs font-bold text-gray-600 mb-2">Call phone CTA</div>
                                <input type="text" name="phone_button_text" maxlength="25" class="w-full px-3 py-2 border border-gray-300 rounded-lg mb-2" placeholder="Button text: Call now">
                                <input type="text" name="phone_button_number" maxlength="20" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="{{ $settings->display_phone_number ?: '+919919944407' }}">
                            </div>
                            <div class="rounded-xl border border-white bg-white p-3">
                                <div class="text-xs font-bold text-gray-600 mb-2">Website URL CTA</div>
                                <input type="text" name="url_button_text" maxlength="25" class="w-full px-3 py-2 border border-gray-300 rounded-lg mb-2" placeholder="Button text: View project">
                                <input type="url" name="url_button_url" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="https://example.com">
                            </div>
                        </div>
                    </div>
                    <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs text-amber-800">
                        Meta review tip: promotional text MARKETING me rakho, appointment/order updates UTILITY me rakho. Misleading promise, loan guarantee, ya unsupported claim avoid karo.
                    </div>
                    <button type="submit" class="w-full px-5 py-3 bg-green-700 text-white rounded-xl hover:bg-green-800 font-bold">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Template to Meta
                    </button>
                </form>
            </div>

            <div class="waba-card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Templates</h3>
                        <p class="text-sm text-gray-500">Latest synced Meta WABA templates.</p>
                    </div>
                    <button type="button" onclick="syncTemplates(this)" class="px-3 py-2 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg text-sm font-bold">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh Status
                    </button>
                </div>
                <div class="space-y-3 max-h-[520px] overflow-y-auto">
                    @forelse($templates as $template)
                        <div class="border border-gray-100 rounded-xl p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-bold text-sm text-gray-900">{{ $template->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $template->language }} @if($template->category) · {{ $template->category }} @endif</div>
                                </div>
                                <span class="px-2 py-1 text-xs font-bold rounded-full {{ $template->is_active ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                    {{ $template->status ?: ($template->is_active ? 'APPROVED' : 'PENDING') }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ $template->content }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No templates synced yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section id="tab-campaigns" class="tab-panel hidden">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="waba-card p-6">
                <h3 class="text-lg font-bold text-gray-900">Start Data Bank Campaign</h3>
                <p class="text-sm text-gray-500 mb-5">Folder select karo, approved template select karo, preview dekho, phir sending start karo.</p>
                <form id="campaign-form" class="space-y-4">
                    @csrf
                    @if($selectedAccount)
                        <input type="hidden" name="meta_waba_account_id" value="{{ $selectedAccount->id }}">
                    @endif
                    <input type="hidden" name="name" id="campaign-name-input">
                    <input type="hidden" name="audience_type" value="tag">
                    <input type="hidden" name="rate_limit_per_minute" value="30">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
                            <div class="text-xs font-black uppercase tracking-wide text-emerald-700">1. Data Bank Folder</div>
                            <label class="sr-only" for="campaign-folder-select">Data Bank Folder</label>
                            <select id="campaign-folder-select" name="tag_id" required class="mt-3 w-full px-3 py-2 border border-emerald-200 bg-white rounded-lg">
                                <option value="">Select folder</option>
                                @foreach($tags as $tag)
                                    <option value="{{ $tag->id }}">{{ $tag->name }}{{ isset($tag->leads_count) ? ' (' . $tag->leads_count . ')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rounded-xl border border-blue-100 bg-blue-50/60 p-4">
                            <div class="text-xs font-black uppercase tracking-wide text-blue-700">2. Approved Template</div>
                            <label class="sr-only" for="campaign-template-select">Approved template</label>
                            <select id="campaign-template-select" name="template_id" required class="mt-3 w-full px-3 py-2 border border-blue-200 bg-white rounded-lg">
                                <option value="">Select template</option>
                                @foreach($approvedTemplates as $template)
                                    <option value="{{ $template->id }}" data-name="{{ $template->name }}">{{ $template->name }} ({{ $template->language }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-black uppercase tracking-wide text-slate-600">3. Preview & Start</div>
                            <p class="mt-3 text-sm text-slate-600">Preview count check karke valid leads par template start hoga.</p>
                        </div>
                    </div>

                    <details class="rounded-xl border border-gray-200 bg-white p-4">
                        <summary class="cursor-pointer text-sm font-bold text-gray-700">Advanced options</summary>
                        <div class="mt-4 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <input type="text" name="city" class="px-3 py-2 border border-gray-300 rounded-lg" placeholder="City">
                                <select name="source" class="px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Any source</option>
                                    @foreach($sources as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg">
                                    <option value="">Any status</option>
                                    <option value="new">New</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="interested">Interested</option>
                                    <option value="closed">Closed</option>
                                </select>
                                <input type="text" name="search" class="px-3 py-2 border border-gray-300 rounded-lg" placeholder="Search">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1">Variable mapping</label>
                                <textarea name="variable_mapping" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="1=name&#10;2=city"></textarea>
                                <p class="text-xs text-gray-500 mt-1">Template body variables ke liye mapping: `1=name`, `2=city`, ya static value.</p>
                            </div>
                        </div>
                    </details>

                    <div id="campaign-preview" class="hidden border border-emerald-200 bg-emerald-50 rounded-xl p-4 text-sm"></div>
                    <div class="flex gap-3">
                        <button type="button" onclick="previewCampaign()" class="flex-1 px-5 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-bold">
                            <i class="fas fa-search mr-2"></i>Preview
                        </button>
                        <button type="submit" class="flex-1 px-5 py-3 bg-green-700 text-white rounded-xl hover:bg-green-800 font-bold">
                            <i class="fas fa-paper-plane mr-2"></i>Start Sending
                        </button>
                    </div>
                </form>
            </div>

            <div class="waba-card p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Recent Campaigns</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b">
                                <th class="py-2">Campaign</th>
                                <th class="py-2">Status</th>
                                <th class="py-2">Recipients</th>
                                <th class="py-2">Sent/Failed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($campaigns as $campaign)
                                <tr class="border-b">
                                    <td class="py-3">
                                        <div class="font-bold text-gray-900">{{ $campaign->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $campaign->template?->name }}</div>
                                    </td>
                                    <td class="py-3">{{ ucfirst($campaign->status) }}</td>
                                    <td class="py-3">{{ $campaign->total_recipients }}</td>
                                    <td class="py-3">{{ $campaign->sent_count }} / {{ $campaign->failed_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-gray-500">No campaigns yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section id="tab-calls" class="tab-panel hidden">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="waba-card p-6">
                <div class="flex items-start justify-between gap-4 mb-5">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">WABA Call Settings</h3>
                        <p class="text-sm text-gray-500">CRM preferences save honge. Agar Meta API live toggles block kare to WhatsApp Manager se manage karo.</p>
                    </div>
                    <button type="button" onclick="refreshCallSettings()" class="px-3 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-lg text-sm font-bold">
                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
                    <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
                        <div class="text-xs font-bold uppercase text-slate-500">Phone</div>
                        <div class="mt-1 font-bold text-slate-900">{{ $settings->display_phone_number ?: ($settings->phone_number_id ?: '-') }}</div>
                    </div>
                    <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4">
                        <div class="text-xs font-bold uppercase text-amber-700">Missed today</div>
                        <div class="mt-1 text-2xl font-black text-amber-800">{{ $callStats['today_missed'] ?? 0 }}</div>
                    </div>
                    <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4">
                        <div class="text-xs font-bold uppercase text-rose-700">Unmatched</div>
                        <div class="mt-1 text-2xl font-black text-rose-800">{{ $callStats['unmatched'] ?? 0 }}</div>
                    </div>
                </div>

                <form id="call-settings-form" class="space-y-4">
                    @csrf
                    @if($selectedAccount)
                        <input type="hidden" name="meta_waba_account_id" value="{{ $selectedAccount->id }}">
                    @endif
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                        <span>
                            <span class="block font-semibold text-slate-900">Allow voice calls</span>
                            <span class="text-xs text-slate-500">WhatsApp voice calls ko CRM preference me enable mark kare.</span>
                        </span>
                        <input type="checkbox" name="voice_calls_enabled" value="1" {{ $settings->voice_calls_enabled ? 'checked' : '' }} class="w-5 h-5 text-green-600 rounded">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                        <span>
                            <span class="block font-semibold text-slate-900">Display call buttons</span>
                            <span class="text-xs text-slate-500">WhatsApp profile/message UI me call buttons show preference.</span>
                        </span>
                        <input type="checkbox" name="display_call_buttons" value="1" {{ $settings->display_call_buttons ? 'checked' : '' }} class="w-5 h-5 text-green-600 rounded">
                    </label>
                    <label class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3">
                        <span>
                            <span class="block font-semibold text-slate-900">Allow callbacks</span>
                            <span class="text-xs text-slate-500">Missed call ke baad callback request tracking enable preference.</span>
                        </span>
                        <input type="checkbox" name="callbacks_enabled" value="1" {{ $settings->callbacks_enabled ? 'checked' : '' }} class="w-5 h-5 text-green-600 rounded">
                    </label>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Call hours / note</label>
                        <textarea name="call_hours" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder='{"mon-fri":"10:00-19:00"}'>{{ is_array($settings->call_hours) ? json_encode($settings->call_hours, JSON_PRETTY_PRINT) : '' }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Temporarily stop incoming calls until</label>
                        <input type="datetime-local" name="call_pause_until" value="{{ optional($settings->call_pause_until)->format('Y-m-d\\TH:i') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="px-5 py-2 bg-green-700 text-white rounded-lg hover:bg-green-800 font-semibold">
                            <i class="fas fa-save mr-2"></i>Save Call Preferences
                        </button>
                        <a href="https://business.facebook.com/latest/whatsapp_manager/phone_numbers/?business_id={{ $settings->business_account_id }}&asset_id={{ $settings->waba_id }}" target="_blank" class="px-5 py-2 bg-slate-100 text-slate-700 border border-slate-200 rounded-lg hover:bg-slate-200 font-semibold">
                            <i class="fas fa-external-link-alt mr-2"></i>Manage in WhatsApp Manager
                        </a>
                    </div>
                </form>
            </div>

            <div class="waba-card p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Recent WABA Call Events</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b">
                                <th class="py-2">Event</th>
                                <th class="py-2">Lead / Phone</th>
                                <th class="py-2">Assigned</th>
                                <th class="py-2">Task</th>
                                <th class="py-2">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentCallEvents as $event)
                                <tr class="border-b">
                                    <td class="py-3">
                                        <div class="font-bold text-gray-900">{{ $event->event_type_label }}</div>
                                        <div class="text-xs text-gray-500">{{ $event->status_label }}</div>
                                    </td>
                                    <td class="py-3">
                                        @if($event->lead)
                                            <a href="{{ route('leads.show', $event->lead) }}" class="font-bold text-green-700 hover:underline">{{ $event->lead->name }}</a>
                                        @else
                                            <span class="font-bold text-rose-700">Unmatched</span>
                                        @endif
                                        <div class="text-xs text-gray-500">{{ $event->phone ? '+' . $event->phone : '-' }}</div>
                                    </td>
                                    <td class="py-3">{{ $event->assignedTo?->name ?? '-' }}</td>
                                    <td class="py-3">{{ $event->telecallerTask ? ('#' . $event->telecallerTask->id . ' · ' . ucfirst($event->telecallerTask->status)) : '-' }}</td>
                                    <td class="py-3">{{ optional($event->occurred_at)->format('d M, h:i A') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-gray-500">No WABA call events captured yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="waba-card p-6 mt-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Unmatched WABA Calls</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b">
                            <th class="py-2">Phone</th>
                            <th class="py-2">Event</th>
                            <th class="py-2">Customer</th>
                            <th class="py-2">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($unmatchedCallEvents as $event)
                            <tr class="border-b">
                                <td class="py-3 font-bold">{{ $event->phone ? '+' . $event->phone : '-' }}</td>
                                <td class="py-3">{{ $event->event_type_label }} · {{ $event->status_label }}</td>
                                <td class="py-3">{{ $event->customer_name ?: '-' }}</td>
                                <td class="py-3">{{ optional($event->occurred_at)->format('d M Y, h:i A') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-gray-500">No unmatched WABA calls.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section id="tab-logs" class="tab-panel hidden">
        <div class="waba-card p-6">
            <h3 class="text-lg font-bold text-gray-900">Automation & Logs</h3>
            <p class="text-sm text-gray-500 mb-5">Automation engine existing module me available hai. Bulk campaign logs recent campaign table me visible hain; detailed per-recipient logs database me store ho rahe hain.</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ $metaAutomationRoute }}" class="px-4 py-3 bg-purple-600 text-white rounded-xl font-bold">
                    <i class="fas fa-robot mr-2"></i>Open WhatsApp Automations
                </a>
                <button type="button" onclick="syncTemplates()" class="px-4 py-3 bg-green-50 text-green-700 border border-green-200 rounded-xl font-bold">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh Template Status
                </button>
            </div>
        </div>
    </section>

    <div class="waba-card p-6">
        <h3 class="text-lg font-bold text-gray-900 mb-2">Test Approved Template</h3>
        <p class="text-sm text-gray-500 mb-4">Synced approved template select karo; language auto-fill ho jayegi.</p>
        <form id="test-template-form" class="grid grid-cols-1 md:grid-cols-4 gap-3">
            @csrf
            @if($selectedAccount)
                <input type="hidden" name="meta_waba_account_id" value="{{ $selectedAccount->id }}">
            @endif
            <input type="text" name="phone" required class="px-3 py-2 border border-gray-300 rounded-lg" placeholder="+919876543210">
            <select id="test-template-select" name="template_name" required class="px-3 py-2 border border-gray-300 rounded-lg">
                <option value="">Select approved template</option>
                @foreach($approvedTemplates as $template)
                    <option value="{{ $template->name }}" data-language="{{ $template->language ?: 'en_US' }}">{{ $template->name }} ({{ $template->language ?: 'en_US' }})</option>
                @endforeach
            </select>
            <input id="test-template-language" type="text" name="language" class="px-3 py-2 border border-gray-300 rounded-lg" placeholder="en_US">
            <button type="submit" class="px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold" {{ $approvedTemplates->isEmpty() ? 'disabled' : '' }}>
                <i class="fas fa-paper-plane mr-2"></i>Send Test
            </button>
        </form>
        @if($approvedTemplates->isEmpty())
            <p class="mt-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                Approved template nahi mila. Pehle Sync Templates click karo, ya Meta me template approved hone ka wait karo.
            </p>
        @endif
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const embeddedSignupSettings = {
    appId: @json($settings->meta_app_id),
    configurationId: @json($settings->embedded_signup_configuration_id),
    graphVersion: @json($settings->graph_version ?: 'v20.0'),
};
let lastEmbeddedSignupResponse = null;

window.addEventListener('message', (event) => {
    if (!event.origin.endsWith('facebook.com')) return;
    try {
        const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        if (data?.type === 'WA_EMBEDDED_SIGNUP') {
            lastEmbeddedSignupResponse = data;
        }
    } catch (error) {
        // Ignore non-JSON browser messages.
    }
});

if (embeddedSignupSettings.appId) {
    window.fbAsyncInit = function () {
        FB.init({
            appId: embeddedSignupSettings.appId,
            cookie: true,
            autoLogAppEvents: true,
            xfbml: true,
            version: embeddedSignupSettings.graphVersion,
        });
    };
    (function (document, tag, id) {
        if (document.getElementById(id)) return;
        const element = document.createElement(tag);
        element.id = id;
        element.src = 'https://connect.facebook.net/en_US/sdk.js';
        document.getElementsByTagName(tag)[0].parentNode.insertBefore(element, document.getElementsByTagName(tag)[0]);
    }(document, 'script', 'facebook-jssdk'));
}

function showMessage(message, type) {
    const container = document.getElementById('message-container');
    const alert = document.getElementById('message-alert');
    alert.className = `alert alert-${type}`;
    alert.textContent = message || (type === 'success' ? 'Done.' : 'Request failed.');
    container.style.display = 'block';
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function jsonFetch(url, form) {
    const body = form?.__formData ? form.__formData : (form ? new FormData(form) : undefined);
    return fetch(url, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
        body
    }).then(async response => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || data.error || 'Request failed.');
        }
        return data;
    });
}

document.getElementById('connect-whatsapp-btn')?.addEventListener('click', function () {
    if (!embeddedSignupSettings.appId || !embeddedSignupSettings.configurationId) {
        return showMessage('Meta App ID aur Embedded Signup Configuration ID save karo, phir Connect WhatsApp click karo.', 'error');
    }
    if (!window.FB) {
        return showMessage('Meta SDK load ho raha hai. 2 seconds baad dobara try karo.', 'error');
    }

    FB.login(function (response) {
        const authResponse = response?.authResponse || {};
        const form = new FormData();
        form.append('_token', csrfToken);
        @if($selectedAccount)
            form.append('meta_waba_account_id', '{{ $selectedAccount->id }}');
        @endif
        @if(request()->boolean('new_account'))
            form.append('create_new_account', '1');
        @endif
        if (authResponse.code) form.append('code', authResponse.code);
        if (lastEmbeddedSignupResponse) {
            form.append('raw_response', JSON.stringify(lastEmbeddedSignupResponse));
            const embeddedData = lastEmbeddedSignupResponse.data || {};
            if (embeddedData.waba_id) form.append('waba_id', embeddedData.waba_id);
            if (embeddedData.phone_number_id) form.append('phone_number_id', embeddedData.phone_number_id);
            if (embeddedData.business_id) form.append('business_id', embeddedData.business_id);
            if (embeddedData.display_phone_number) form.append('display_phone_number', embeddedData.display_phone_number);
        }

        jsonFetch('{{ route($metaWabaRoutePrefix . "embedded.finish") }}', {__formData: form})
            .then(data => showMessage(data.message || 'WhatsApp connected.', 'success'))
            .catch(error => showMessage(error.message, 'error'));
    }, {
        config_id: embeddedSignupSettings.configurationId,
        response_type: 'code',
        override_default_response_type: true,
        extras: {
            setup: {},
            feature: 'whatsapp_embedded_signup',
            sessionInfoVersion: '3',
        },
    });
});

function activateWabaTab(tabName, updateUrl = true) {
    const button = document.querySelector(`.waba-tab[data-tab="${tabName}"]`);
    const panel = document.getElementById('tab-' + tabName);
    if (!button || !panel) return false;

    document.querySelectorAll('.waba-tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(item => item.classList.add('hidden'));
    button.classList.add('active');
    panel.classList.remove('hidden');

    if (updateUrl) {
        const url = new URL(window.location.href);
        url.searchParams.delete('tab');
        url.hash = tabName;
        window.history.replaceState({}, '', url.toString());
    }

    return true;
}

document.querySelectorAll('.waba-tab').forEach(button => {
    button.addEventListener('click', () => activateWabaTab(button.dataset.tab));
});

const initialWabaTab = new URLSearchParams(window.location.search).get('tab') || window.location.hash.replace('#', '');
if (initialWabaTab) {
    activateWabaTab(initialWabaTab, false);
}

function copyFieldValue(fieldId, successMessage) {
    const field = document.getElementById(fieldId);
    if (!field || !field.value) return showMessage('Nothing to copy.', 'error');
    navigator.clipboard.writeText(field.value).then(() => showMessage(successMessage, 'success'));
}

function toggleSecretField(fieldId, button) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    const shouldShow = field.type === 'password';
    field.type = shouldShow ? 'text' : 'password';
    const icon = button?.querySelector('i');
    if (icon) {
        icon.className = shouldShow ? 'fas fa-eye-slash' : 'fas fa-eye';
    }
}

function addQuickReplyButton() {
    const list = document.getElementById('quick-reply-button-list');
    if (!list) return;
    if (list.querySelectorAll('.quick-reply-row').length >= 3) {
        return showMessage('Meta template me max 3 buttons allowed hain.', 'error');
    }

    const row = document.createElement('div');
    row.className = 'quick-reply-row flex gap-2';
    row.innerHTML = `
        <input type="text" name="quick_reply_buttons[]" maxlength="25" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-white" placeholder="Quick reply button text">
        <button type="button" onclick="removeQuickReplyButton(this)" class="px-3 py-2 bg-rose-50 text-rose-700 border border-rose-200 rounded-lg"><i class="fas fa-times"></i></button>
    `;
    list.appendChild(row);
    updateTemplatePreview();
}

function removeQuickReplyButton(button) {
    const list = document.getElementById('quick-reply-button-list');
    const rows = list ? list.querySelectorAll('.quick-reply-row') : [];
    if (rows.length <= 1) {
        const input = button.closest('.quick-reply-row')?.querySelector('input');
        if (input) input.value = '';
        return;
    }
    button.closest('.quick-reply-row')?.remove();
    updateTemplatePreview();
}

function templateForm() {
    return document.getElementById('create-template-form');
}

function normalizeTemplateName(value) {
    return (value || '')
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_+|_+$/g, '');
}

function extractTemplateVariables(body) {
    const matches = [...(body || '').matchAll(/\{\{(\d+)\}\}/g)].map(match => Number(match[1]));
    return [...new Set(matches)].sort((a, b) => a - b);
}

function sampleLines(value) {
    return (value || '').split(/\r?\n/).map(line => line.trim()).filter(Boolean);
}

function replaceVariablesForPreview(body, samples) {
    return (body || '').replace(/\{\{(\d+)\}\}/g, (_, index) => samples[Number(index) - 1] || ('{' + '{' + index + '}' + '}'));
}

function insertTemplateVariable() {
    const form = templateForm();
    const body = form?.querySelector('[name="body_text"]');
    if (!body) return;
    const variables = extractTemplateVariables(body.value);
    const next = variables.length ? Math.max(...variables) + 1 : 1;
    const token = '{' + '{' + next + '}' + '}';
    const start = body.selectionStart || body.value.length;
    const end = body.selectionEnd || body.value.length;
    body.value = body.value.slice(0, start) + token + body.value.slice(end);
    body.focus();
    body.setSelectionRange(start + token.length, start + token.length);
    updateTemplatePreview();
}

function applyTemplatePreset(type) {
    const form = templateForm();
    if (!form) return;
    const values = type === 'payment'
        ? {
            name: 'payment_link_reminder',
            category: 'UTILITY',
            header: 'Payment Link',
            body: 'Hi {{1}}, your secure payment link for {{2}} is ready. Amount: {{3}}. Please complete it here: {{4}}',
            samples: 'Vivek\nBase Infra Booking\nRs. 299\nhttps://example.com/pay',
            footer: 'Ignore if already paid'
        }
        : {
            name: 'site_visit_confirmation',
            category: 'UTILITY',
            header: 'Site Visit Confirmed',
            body: 'Hi {{1}}, your site visit for {{2}} is scheduled on {{3}}. Our advisor {{4}} will assist you.',
            samples: 'Vivek\nBase Infra\n25 Jul, 11:00 AM\nSantosh',
            footer: 'Reply STOP to opt out'
        };

    form.querySelector('[name="name"]').value = values.name;
    form.querySelector('[name="category"]').value = values.category;
    form.querySelector('[name="header_text"]').value = values.header;
    form.querySelector('[name="body_text"]').value = values.body;
    form.querySelector('[name="body_samples"]').value = values.samples;
    form.querySelector('[name="footer_text"]').value = values.footer;
    updateTemplatePreview();
}

function updateTemplatePreview() {
    const form = templateForm();
    if (!form) return;
    const header = form.querySelector('[name="header_text"]')?.value || '';
    const body = form.querySelector('[name="body_text"]')?.value || '';
    const footer = form.querySelector('[name="footer_text"]')?.value || '';
    const samples = sampleLines(form.querySelector('[name="body_samples"]')?.value || '');
    const variables = extractTemplateVariables(body);
    const quickReplies = [...form.querySelectorAll('[name="quick_reply_buttons[]"]')].map(input => input.value.trim()).filter(Boolean);
    const phoneText = form.querySelector('[name="phone_button_text"]')?.value.trim();
    const urlText = form.querySelector('[name="url_button_text"]')?.value.trim();

    const headerBox = document.getElementById('preview-header');
    const bodyBox = document.getElementById('preview-body');
    const footerBox = document.getElementById('preview-footer');
    const buttonsBox = document.getElementById('preview-buttons');
    const checklist = document.getElementById('template-checklist');
    const status = document.getElementById('template-preview-status');

    document.getElementById('template-header-count').textContent = `${header.length}/60`;
    document.getElementById('template-body-count').textContent = `${body.length}/1024`;
    document.getElementById('template-footer-count').textContent = `${footer.length}/60`;

    headerBox.textContent = header;
    headerBox.classList.toggle('hidden', !header);
    bodyBox.textContent = replaceVariablesForPreview(body, samples) || 'Body preview yahan dikhega.';
    footerBox.textContent = footer;
    footerBox.classList.toggle('hidden', !footer);

    const buttons = [...quickReplies];
    if (phoneText) buttons.push(phoneText);
    if (urlText) buttons.push(urlText);
    buttonsBox.innerHTML = buttons.slice(0, 3).map(text => `<div class="text-center rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700">${text}</div>`).join('');

    const checks = [];
    checks.push(body.trim() ? ['ok', 'Body text ready'] : ['bad', 'Body text required']);
    checks.push(variables.length === samples.length ? ['ok', `${variables.length} variable sample matched`] : ['bad', `${variables.length} variable, ${samples.length} sample value`]);
    checks.push(buttons.length <= 3 ? ['ok', `${buttons.length} / 3 buttons`] : ['bad', 'Buttons max 3 allowed']);
    checks.push(normalizeTemplateName(form.querySelector('[name="name"]')?.value) === form.querySelector('[name="name"]')?.value ? ['ok', 'Name format valid'] : ['bad', 'Name will be auto-fixed']);

    checklist.innerHTML = checks.map(([type, text]) => `<div class="${type === 'ok' ? 'text-emerald-700' : 'text-amber-700'}"><i class="fas ${type === 'ok' ? 'fa-check-circle' : 'fa-triangle-exclamation'} mr-1"></i>${text}</div>`).join('');
    const hasError = checks.some(([type]) => type === 'bad');
    status.textContent = hasError ? 'Check' : 'Ready';
    status.className = `px-2 py-1 rounded-full text-[11px] font-bold ${hasError ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}`;
}

document.getElementById('meta-waba-form').addEventListener('submit', function (event) {
    event.preventDefault();
    jsonFetch('{{ route($metaWabaRoutePrefix . "update") }}', this)
        .then(data => showMessage(data.message, 'success'))
        .catch(error => showMessage(error.message, 'error'));
});

document.getElementById('register-phone-form')?.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!confirm('Phone number ko Meta Cloud API ke liye register karna hai?')) return;
    jsonFetch('{{ route($metaWabaRoutePrefix . "register-phone") }}', this)
        .then(data => showMessage(data.message || 'Phone registered.', 'success'))
        .catch(error => showMessage(error.message, 'error'));
});

function selectedAccountFormData() {
    const form = new FormData();
    form.append('_token', csrfToken);
    @if($selectedAccount)
        form.append('meta_waba_account_id', '{{ $selectedAccount->id }}');
    @endif
    return {__formData: form};
}

function verifyWaba() {
    jsonFetch('{{ route($metaWabaRoutePrefix . "verify") }}', selectedAccountFormData())
        .then(data => showMessage(data.message || 'Verified.', 'success'))
        .catch(error => showMessage(error.message, 'error'));
}

function syncTemplates(button = null) {
    const originalHtml = button?.innerHTML;
    if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Refreshing...';
    }
    jsonFetch('{{ route($metaWabaRoutePrefix . "templates.sync") }}', selectedAccountFormData())
        .then(data => {
            showMessage(data.message || 'Templates synced.', 'success');
            setTimeout(() => window.location.reload(), 900);
        })
        .catch(error => {
            showMessage(error.message, 'error');
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        });
}

function setDefaultWaba() {
    if (!confirm('Selected API number ko default Meta sender banana hai?')) return;
    jsonFetch('{{ route($metaWabaRoutePrefix . "set-default") }}', selectedAccountFormData())
        .then(data => showMessage(data.message || 'Default sender updated.', 'success'))
        .catch(error => showMessage(error.message, 'error'));
}

function disconnectWaba() {
    if (!confirm('CRM se active WABA disconnect karna hai? Old conversations delete nahi hongi.')) return;
    jsonFetch('{{ route($metaWabaRoutePrefix . "disconnect") }}', selectedAccountFormData())
        .then(data => showMessage(data.message || 'WABA disconnected.', 'success'))
        .catch(error => showMessage(error.message, 'error'));
}

document.getElementById('create-template-form').addEventListener('submit', function (event) {
    event.preventDefault();
    const nameField = this.querySelector('[name="name"]');
    nameField.value = normalizeTemplateName(nameField.value);

    const body = this.querySelector('[name="body_text"]').value || '';
    const variables = extractTemplateVariables(body);
    const samples = sampleLines(this.querySelector('[name="body_samples"]').value || '');
    const buttonCount = [...this.querySelectorAll('[name="quick_reply_buttons[]"]')].filter(input => input.value.trim()).length
        + (this.querySelector('[name="phone_button_text"]').value.trim() ? 1 : 0)
        + (this.querySelector('[name="url_button_text"]').value.trim() ? 1 : 0);

    if (variables.length !== samples.length) {
        return showMessage(`Template variables ${variables.length} hain, sample values ${samples.length}. Please line-wise sample match karo.`, 'error');
    }
    if (buttonCount > 3) {
        return showMessage('Meta template me total max 3 buttons allowed hain.', 'error');
    }
    if (!nameField.value) {
        return showMessage('Template name lowercase letters, numbers aur underscore me required hai.', 'error');
    }

    jsonFetch('{{ route($metaWabaRoutePrefix . "templates.create") }}', this)
        .then(data => showMessage(data.message, 'success'))
        .catch(error => showMessage(error.message, 'error'));
});

templateForm()?.addEventListener('input', function (event) {
    if (event.target?.name === 'name') {
        event.target.value = normalizeTemplateName(event.target.value);
    }
    updateTemplatePreview();
});
updateTemplatePreview();

function selectedOptionText(select) {
    if (!select || !select.value) return '';
    return select.options[select.selectedIndex]?.textContent?.replace(/\s*\(\d+\)\s*$/, '').trim() || '';
}

function updateCampaignName() {
    const form = document.getElementById('campaign-form');
    const folderName = selectedOptionText(form?.querySelector('[name="tag_id"]'));
    const templateSelect = form?.querySelector('[name="template_id"]');
    const templateName = templateSelect?.options[templateSelect.selectedIndex]?.dataset?.name || selectedOptionText(templateSelect);
    const nameInput = document.getElementById('campaign-name-input');
    if (!nameInput) return;

    const now = new Date();
    const stamp = now.toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
    nameInput.value = [folderName, templateName, stamp].filter(Boolean).join(' - ');
}

document.getElementById('campaign-form')?.addEventListener('change', function (event) {
    if (event.target?.name === 'tag_id' || event.target?.name === 'template_id') {
        updateCampaignName();
    }
});

function previewCampaign() {
    const form = document.getElementById('campaign-form');
    const box = document.getElementById('campaign-preview');
    updateCampaignName();
    if (!form.reportValidity()) return;

    jsonFetch('{{ route($metaWabaRoutePrefix . "campaigns.preview") }}', form)
        .then(data => {
            const preview = data.preview || {};
            box.innerHTML = `
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div><b>${preview.matched || 0}</b><br><span class="text-gray-600">Matched</span></div>
                    <div><b>${preview.valid || 0}</b><br><span class="text-gray-600">Valid</span></div>
                    <div><b>${preview.invalid || 0}</b><br><span class="text-gray-600">Invalid</span></div>
                    <div><b>${preview.opted_out || 0}</b><br><span class="text-gray-600">Opt-out</span></div>
                    <div><b>${preview.duplicates || 0}</b><br><span class="text-gray-600">Duplicates</span></div>
                </div>
                ${(preview.valid || 0) < 1 ? '<div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 font-semibold text-amber-800">Is folder me send karne layak valid leads nahi hain.</div>' : ''}`;
            box.classList.remove('hidden');
        })
        .catch(error => showMessage(error.message, 'error'));
}

document.getElementById('campaign-form').addEventListener('submit', function (event) {
    event.preventDefault();
    updateCampaignName();
    if (!this.reportValidity()) return;
    if (!confirm('Selected folder ke valid leads ko ye template send hoga. Start kare?')) return;
    jsonFetch('{{ route($metaWabaRoutePrefix . "campaigns.store") }}', this)
        .then(data => {
            showMessage(data.message, 'success');
            setTimeout(() => window.location.reload(), 800);
        })
        .catch(error => showMessage(error.message, 'error'));
});

document.getElementById('call-settings-form')?.addEventListener('submit', function (event) {
    event.preventDefault();
    jsonFetch('{{ route($metaWabaRoutePrefix . "calls.update") }}', this)
        .then(data => showMessage(data.message || 'Call preferences saved.', 'success'))
        .catch(error => showMessage(error.message, 'error'));
});

function refreshCallSettings() {
    jsonFetch('{{ route($metaWabaRoutePrefix . "calls.refresh") }}', selectedAccountFormData())
        .then(data => showMessage(data.message || 'Call settings refreshed.', 'success'))
        .catch(error => showMessage(error.message, 'error'));
}

document.getElementById('test-template-form').addEventListener('submit', function (event) {
    event.preventDefault();
    jsonFetch('{{ route($metaWabaRoutePrefix . "test-template") }}', this)
        .then(data => showMessage(data.success ? 'Test template sent.' : (data.error || 'Send failed.'), data.success ? 'success' : 'error'))
        .catch(error => showMessage(error.message, 'error'));
});

document.getElementById('test-template-select')?.addEventListener('change', function () {
    const language = this.options[this.selectedIndex]?.dataset?.language || 'en_US';
    document.getElementById('test-template-language').value = language;
});
</script>
@endsection
