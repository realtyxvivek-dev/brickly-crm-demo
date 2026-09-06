@extends('layouts.app')

<?php $extensionsOnly = $extensionsOnly ?? false; ?>

@section('title', $extensionsOnly ? 'Extensions' : 'Mobile App Update')
@section('page-title', $extensionsOnly ? 'Extensions' : 'Mobile App Update')

@section('content')
<div class="w-full max-w-none space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    @unless($extensionsOnly)
    <div class="rounded-2xl border border-[#DCE8E0] bg-gradient-to-br from-[#0A1F18] to-[#205A44] p-6 text-white shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">Android app control</div>
                <h2 class="mt-2 text-2xl font-bold">1-click app update publish</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/75">
                    Latest APK upload hone ke baad yahi button dabao. System force update OFF rakhega aur sirf outdated Android users ko update notification bhejega.
                </p>
            </div>
            <form method="POST" action="{{ route('admin.mobile-app-update.quick-publish') }}" onsubmit="return confirm('Force update OFF rahega. Sirf outdated Android users ko update notification bhejna hai?');">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-[#0A1F18] shadow-sm hover:bg-[#F3F7F4]">
                    <i class="fas fa-paper-plane"></i>
                    Publish & Notify Outdated Users
                </button>
            </form>
        </div>

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-xl border border-white/10 bg-white/10 p-4">
                <div class="text-xs uppercase tracking-[0.14em] text-white/55">Latest Version</div>
                <div class="mt-2 text-xl font-bold">{{ $settings['versionName'] }}+{{ $settings['versionCode'] }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-4">
                <div class="text-xs uppercase tracking-[0.14em] text-white/55">Status</div>
                <div class="mt-2 text-xl font-bold">{{ $settings['active'] ? 'Active' : 'Inactive' }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-4">
                <div class="text-xs uppercase tracking-[0.14em] text-white/55">Force Update</div>
                <div class="mt-2 text-xl font-bold">{{ $settings['forceUpdate'] ? 'ON' : 'OFF' }}</div>
            </div>
            <div class="rounded-xl border border-white/10 bg-white/10 p-4">
                <div class="text-xs uppercase tracking-[0.14em] text-white/55">Released</div>
                <div class="mt-2 text-sm font-semibold">{{ $settings['releasedAt'] ?: 'Not set' }}</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-[#E5DED4] p-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-brand-primary">User Update Tracking</h3>
                <p class="text-sm text-[#6B7280] mt-1">Install confirm tab hota hai jab user updated app open karta hai. Download status Update Now click se track hota hai.</p>
            </div>
            <div class="rounded-xl bg-[#F3F7F4] px-4 py-3 text-sm font-semibold text-[#205A44]">
                Target: {{ $installStats['targetVersion'] }}
            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="rounded-xl border border-[#E5DED4] p-4">
                <div class="text-xs uppercase tracking-[0.12em] text-[#6B7280]">Tracked</div>
                <div class="mt-2 text-2xl font-bold text-brand-primary">{{ $installStats['trackedUsers'] }}</div>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="text-xs uppercase tracking-[0.12em] text-emerald-700">Updated</div>
                <div class="mt-2 text-2xl font-bold text-emerald-800">{{ $installStats['updated'] }}</div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <div class="text-xs uppercase tracking-[0.12em] text-amber-700">Old Version</div>
                <div class="mt-2 text-2xl font-bold text-amber-800">{{ $installStats['oldVersion'] }}</div>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                <div class="text-xs uppercase tracking-[0.12em] text-blue-700">Downloaded</div>
                <div class="mt-2 text-2xl font-bold text-blue-800">{{ $installStats['downloadedNotOpened'] }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs uppercase tracking-[0.12em] text-slate-600">Never Opened</div>
                <div class="mt-2 text-2xl font-bold text-slate-800">{{ $installStats['neverOpened'] }}</div>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-xl border border-[#E5DED4]">
            <table class="min-w-full divide-y divide-[#E5DED4] text-sm">
                <thead class="bg-[#F7F6F3] text-left text-xs uppercase tracking-[0.12em] text-[#6B7280]">
                    <tr>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Installed</th>
                        <th class="px-4 py-3">Download Click</th>
                        <th class="px-4 py-3">Last Open</th>
                        <th class="px-4 py-3">Clicks</th>
                        <th class="px-4 py-3">Diagnostics</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E5DED4] bg-white">
                    <?php if($installRows->isEmpty()): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-[#6B7280]">
                                Abhi koi Android app tracking data nahi hai. Users updated app open karenge tab data aayega.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach($installRows as $row): ?>
                        <?php
                            $user = $row['user'];
                            $installation = $row['installation'];
                            $diagnostic = $row['diagnostic'];
                            $badgeClass = $row['isUpdated']
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                : ($row['downloadedTarget'] ? 'bg-blue-50 text-blue-700 border-blue-200' : ($row['hasInstalled'] ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-50 text-slate-600 border-slate-200'));
                            $healthClass = match($diagnostic?->health_status) {
                                'green' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'yellow' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'red' => 'bg-red-50 text-red-700 border-red-200',
                                default => 'bg-slate-50 text-slate-600 border-slate-200',
                            };
                        ?>
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-brand-primary">{{ $user->name }}</div>
                                <div class="text-xs text-[#6B7280]">{{ $user->email }}</div>
                                <div class="text-xs text-[#9CA3AF]">{{ $user->role?->name ?? 'No role' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-bold {{ $badgeClass }}">
                                    {{ $row['status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-[#374151]">
                                @if($installation?->installed_version_code)
                                    {{ $installation->installed_version_name ?: 'App' }}+{{ $installation->installed_version_code }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[#374151]">
                                @if($installation?->last_download_clicked_at)
                                    <div>{{ $installation->last_download_version_name ?: 'App' }}+{{ $installation->last_download_version_code }}</div>
                                    <div class="text-xs text-[#6B7280]">{{ $installation->last_download_clicked_at->format('d M, h:i A') }}</div>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[#374151]">
                                {{ $installation?->last_opened_at ? $installation->last_opened_at->format('d M, h:i A') : '-' }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-[#374151]">
                                {{ $installation?->download_click_count ?? 0 }}
                            </td>
                            <td class="px-4 py-3 text-[#374151]">
                                <div class="flex flex-col gap-2">
                                    <span class="inline-flex w-max rounded-full border px-3 py-1 text-xs font-bold {{ $row['diagnosticsEnabled'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-600 border-slate-200' }}">
                                        {{ $row['diagnosticsEnabled'] ? 'Enabled' : 'Off' }}
                                    </span>
                                    @if($diagnostic)
                                        <span class="inline-flex w-max rounded-full border px-3 py-1 text-xs font-bold {{ $healthClass }}">
                                            Health: {{ ucfirst($diagnostic->health_status ?: 'unknown') }}
                                        </span>
                                        <div class="text-xs text-[#6B7280]">
                                            {{ $diagnostic->app_build_label ?: (($diagnostic->app_version_name && $diagnostic->app_version_code) ? $diagnostic->app_version_name . '+' . $diagnostic->app_version_code : 'App') }}
                                            · {{ $diagnostic->device_model ?: 'Device' }}
                                        </div>
                                        <div class="text-xs text-[#9CA3AF]">{{ $diagnostic->reported_at?->format('d M, h:i A') }}</div>
                                    @else
                                        <div class="text-xs text-[#9CA3AF]">No report yet</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="min-w-[280px] max-w-[340px] space-y-2">
                                    @if($row['isUpdated'])
                                        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">
                                            <i class="fas fa-check-circle"></i>
                                            Latest version
                                        </div>
                                    @else
                                        <div class="grid grid-cols-2 gap-2">
                                            <form method="POST" action="{{ route('admin.mobile-app-update.users.notify', $user) }}" onsubmit="return confirm('Is user ko normal update notification bhejna hai?');">
                                                @csrf
                                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#BFD3C8] bg-white px-3 py-2 text-xs font-bold text-brand-primary transition hover:border-[#205A44] hover:bg-[#F3F7F4]">
                                                    <i class="fas fa-paper-plane"></i>
                                                    Update
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.mobile-app-update.users.force', $user) }}" onsubmit="return confirm('Sirf is user ke liye force update enable karna hai?');">
                                                @csrf
                                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#8A2E2E] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#6E2424]">
                                                    <i class="fas fa-lock"></i>
                                                    Force
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                    @if($row['diagnosticsEnabled'])
                                        <form method="POST" action="{{ route('admin.mobile-app-update.users.diagnostics.disable', $user) }}" onsubmit="return confirm('Diagnostics disable karna hai?');">
                                            @csrf
                                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-slate-500 hover:bg-slate-50">
                                                <i class="fas fa-stethoscope"></i>
                                                Disable Diagnostics
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.mobile-app-update.users.diagnostics.enable', $user) }}" onsubmit="return confirm('Is user ke app me Diagnostics Center enable karna hai?');" class="grid grid-cols-[92px_1fr] gap-2">
                                            @csrf
                                            <select name="duration" class="h-9 rounded-lg border border-[#BFD3C8] bg-white px-2 text-xs font-bold text-brand-primary focus:border-[#205A44] focus:outline-none">
                                                <option value="24h">24h</option>
                                                <option value="7d">7d</option>
                                                <option value="none">No expiry</option>
                                            </select>
                                            <button type="submit" class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-[#205A44] px-3 text-xs font-bold text-white transition hover:bg-[#184533]">
                                                <i class="fas fa-stethoscope"></i>
                                                Diagnostics
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    @endunless

    @if($extensionsOnly)
        <div class="rounded-2xl border border-[#DCE8E0] bg-gradient-to-br from-[#0A1F18] to-[#205A44] p-6 text-white shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">Browser tools</div>
            <h2 class="mt-2 text-2xl font-bold">CRM Extensions</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-white/75">
                WhatsApp Web aur Facebook Lead Center extensions yahin se manage karo. Future browser extensions bhi isi page par add honge.
            </p>
        </div>
    @endif

    <div id="admin-extensions" class="{{ $extensionsOnly ? 'grid grid-cols-1 xl:grid-cols-2 gap-6' : 'grid grid-cols-1 xl:grid-cols-[minmax(520px,0.95fr)_minmax(520px,1.05fr)] gap-6' }}">
        @unless($extensionsOnly)
        <div class="bg-white rounded-2xl shadow-sm border border-[#E5DED4] p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-brand-primary">Update Settings</h3>
                    <p class="text-sm text-[#6B7280] mt-1">Naya APK build/upload ke time version aur message yahan update karo.</p>
                </div>
                <a href="{{ $settings['apkUrl'] }}" target="_blank" class="shrink-0 rounded-lg border border-[#E5DED4] px-3 py-2 text-xs font-semibold text-brand-primary hover:border-[#205A44]">
                    Open APK
                </a>
            </div>

            <form method="POST" action="{{ route('admin.mobile-app-update.update') }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-6">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-brand-primary mb-2">Version Code</label>
                    <input type="number" name="version_code" min="1" value="{{ old('version_code', $settings['versionCode']) }}" class="w-full rounded-lg border border-[#E5DED4] px-4 py-2" required>
                    <p class="text-xs text-[#6B7280] mt-1">Purane app se bada number rakho.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-brand-primary mb-2">Version Name</label>
                    <input type="text" name="version_name" value="{{ old('version_name', $settings['versionName']) }}" class="w-full rounded-lg border border-[#E5DED4] px-4 py-2" required>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-brand-primary mb-2">Upload Latest APK</label>
                    <input type="file" name="apk_file" accept=".apk" class="w-full rounded-lg border border-[#E5DED4] px-4 py-2">
                    <p class="text-xs text-[#6B7280] mt-1">Upload karne par `base-crm-latest.apk` replace hoga.</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-brand-primary mb-2">APK URL</label>
                    <input type="url" name="apk_url" value="{{ old('apk_url', $settings['apkUrl']) }}" class="w-full rounded-lg border border-[#E5DED4] px-4 py-2">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-brand-primary mb-2">Update Message</label>
                    <textarea name="message" rows="3" class="w-full rounded-lg border border-[#E5DED4] px-4 py-2" required>{{ old('message', $settings['message']) }}</textarea>
                </div>

                <label class="flex items-center gap-3 rounded-xl border border-[#E5DED4] px-4 py-3">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $settings['active']))>
                    <span>
                        <span class="block text-sm font-semibold text-brand-primary">Active</span>
                        <span class="block text-xs text-[#6B7280]">App update check enabled.</span>
                    </span>
                </label>

                <label class="flex items-center gap-3 rounded-xl border border-[#E5DED4] px-4 py-3">
                    <input type="checkbox" name="force_update" value="1" @checked(old('force_update', $settings['forceUpdate']))>
                    <span>
                        <span class="block text-sm font-semibold text-brand-primary">Force Update</span>
                        <span class="block text-xs text-[#6B7280]">Update ke bina app block rahega.</span>
                    </span>
                </label>

                <div class="md:col-span-2 flex flex-wrap items-center gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-[#205A44] text-white font-semibold">
                        <i class="fas fa-save"></i>
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
        @endunless

        <div class="space-y-4">
            <div class="bg-white border border-[#E5DED4] rounded-2xl p-5 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="font-bold text-brand-primary">WhatsApp Web Extension</div>
                        <p class="mt-1 text-[#6B7280]">Yahin se latest zip download karo aur Chrome/Edge me load unpacked ya zip extract karke test karo.</p>
                    </div>
                    @if($whatsAppExtension['available'])
                        <a href="{{ $whatsAppExtension['downloadRoute'] }}" class="inline-flex items-center gap-2 rounded-xl bg-[#205A44] px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#184533]">
                            <i class="fas fa-download"></i>
                            Download
                        </a>
                    @endif
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3">
                    <div class="rounded-xl bg-[#F7F6F3] p-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Package</div>
                        <div class="mt-1 font-semibold text-brand-primary">{{ $whatsAppExtension['name'] }}</div>
                    </div>
                    <div class="rounded-xl bg-[#F7F6F3] p-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Version</div>
                        <div class="mt-1 font-semibold text-brand-primary">v{{ $whatsAppExtension['version'] }}</div>
                    </div>
                    <div class="rounded-xl bg-[#F7F6F3] p-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Last Local Update</div>
                        <div class="mt-1 font-semibold text-brand-primary">
                            {{ $whatsAppExtension['updatedAt'] ? $whatsAppExtension['updatedAt']->format('d M Y, h:i A') : 'Not available' }}
                        </div>
                    </div>
                    <div class="rounded-xl bg-[#F7F6F3] p-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">CRM Base URL</div>
                        <div class="mt-1 font-semibold text-brand-primary break-all">{{ $whatsAppExtension['crmBaseUrl'] }}</div>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-[#E5DED4] bg-[#FBFCFA] p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-brand-primary">WhatsApp Event Stats</div>
                            <p class="mt-1 text-xs text-[#6B7280]">Yahin se dekh lo aaj kitne messages process hue, new lead bani ya re-enquiry hui.</p>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Last Processed</div>
                            <div class="mt-1 text-sm font-semibold text-brand-primary">
                                {{ $whatsAppExtension['stats']['last_processed_at'] ? $whatsAppExtension['stats']['last_processed_at']->format('d M, h:i A') : 'No event yet' }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-white border border-[#E5DED4] p-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Today Total</div>
                            <div class="mt-1 text-2xl font-bold text-brand-primary">{{ $whatsAppExtension['stats']['today_total'] }}</div>
                        </div>
                        <div class="rounded-xl bg-white border border-[#E5DED4] p-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Processed Total</div>
                            <div class="mt-1 text-2xl font-bold text-brand-primary">{{ $whatsAppExtension['stats']['processed_total'] }}</div>
                        </div>
                        <div class="rounded-xl bg-white border border-emerald-200 p-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-emerald-700">New Leads</div>
                            <div class="mt-1 text-2xl font-bold text-emerald-800">{{ $whatsAppExtension['stats']['today_new_leads'] }}</div>
                        </div>
                        <div class="rounded-xl bg-white border border-blue-200 p-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-blue-700">Re-enquiry</div>
                            <div class="mt-1 text-2xl font-bold text-blue-800">{{ $whatsAppExtension['stats']['today_reenquiries'] }}</div>
                        </div>
                    </div>
                    @if($whatsAppExtension['stats']['today_errors'] > 0)
                        <div class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
                            Today errors: {{ $whatsAppExtension['stats']['today_errors'] }}
                        </div>
                    @endif
                </div>

                <div class="mt-4 rounded-xl border border-[#E5DED4] bg-white p-4">
                    <div class="font-bold text-brand-primary">Recent Activity</div>
                    <div class="mt-3 space-y-3">
                        <?php if($whatsAppExtension['stats']['recent_events']->isEmpty()): ?>
                            <div class="rounded-xl border border-dashed border-[#D6D3CE] bg-[#FBFCFA] px-4 py-5 text-sm text-[#6B7280]">
                                Abhi tak koi WhatsApp Web event process nahi hua.
                            </div>
                        <?php endif; ?>
                        <?php foreach($whatsAppExtension['stats']['recent_events'] as $event): ?>
                            <?php
                                $decisionLabel = match($event['decision']) {
                                    'new_lead' => 'New Lead',
                                    'reenquiry' => 'Re-enquiry',
                                    'duplicate' => 'Duplicate',
                                    'error' => 'Error',
                                    default => ucfirst(str_replace('_', ' ', $event['decision'] ?? 'ignored')),
                                };
                                $decisionClass = match($event['decision']) {
                                    'new_lead' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'reenquiry' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'error' => 'bg-red-50 text-red-700 border-red-200',
                                    default => 'bg-slate-50 text-slate-600 border-slate-200',
                                };
                            ?>
                            <div class="rounded-xl border border-[#E5DED4] bg-[#FBFCFA] p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-brand-primary">
                                            {{ $event['lead_name'] ?: ($event['contact_name'] ?: $event['phone']) }}
                                        </div>
                                        <div class="mt-1 text-xs text-[#6B7280]">
                                            {{ $event['phone'] ?: 'No phone' }}
                                            @if(!empty($event['message_preview']))
                                                · {{ \Illuminate\Support\Str::limit($event['message_preview'], 60) }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $decisionClass }}">
                                            {{ $decisionLabel }}
                                        </span>
                                        <div class="mt-1 text-[11px] text-[#6B7280]">
                                            {{ $event['processed_at'] ? $event['processed_at']->format('d M, h:i A') : 'Pending' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-[#E5DED4] bg-white p-4">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-bold text-brand-primary">Extension Token</div>
                            <p class="mt-1 text-xs text-[#6B7280]">Generate karke popup me paste karo. Copy aur test yahin se ho jayega.</p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" id="waExtGenerateTokenBtn" class="inline-flex items-center gap-2 rounded-lg bg-[#205A44] px-3 py-2 text-xs font-bold text-white">
                                <i class="fas fa-key"></i>
                                Generate
                            </button>
                            <button type="button" id="waExtCopyTokenBtn" class="inline-flex items-center gap-2 rounded-lg border border-[#D6E2DA] bg-white px-3 py-2 text-xs font-bold text-brand-primary">
                                <i class="fas fa-copy"></i>
                                Copy
                            </button>
                            <button type="button" id="waExtTestConnectionBtn" class="inline-flex items-center gap-2 rounded-lg border border-[#D6E2DA] bg-white px-3 py-2 text-xs font-bold text-brand-primary">
                                <i class="fas fa-plug"></i>
                                Test Connection
                            </button>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="waExtTokenField" class="block text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Sanctum Token</label>
                        <textarea id="waExtTokenField" rows="4" class="mt-2 w-full rounded-xl border border-[#E5DED4] bg-[#F7F6F3] px-3 py-3 font-mono text-xs text-[#173128]" placeholder="Generate token here..."></textarea>
                    </div>

                    <div id="waExtTestResult" class="mt-3 hidden rounded-xl border px-3 py-3 text-xs"></div>
                </div>
            </div>

            <div id="facebook-lead-center-extension" class="bg-white border border-[#E5DED4] rounded-2xl p-5 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="font-bold text-brand-primary">Facebook Lead Center Extension</div>
                        <p class="mt-1 text-[#6B7280]">Facebook Lead Center ke visible rows scan karke CRM se compare karega. Ye webhook replacement nahi hai.</p>
                    </div>
                    @if($facebookLeadCenterExtension['available'])
                        <a href="{{ $facebookLeadCenterExtension['downloadRoute'] }}" class="inline-flex items-center gap-2 rounded-xl bg-[#205A44] px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#184533]">
                            <i class="fas fa-download"></i>
                            Download
                        </a>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3">
                    <div class="rounded-xl bg-[#F7F6F3] p-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Package</div>
                        <div class="mt-1 font-semibold text-brand-primary">{{ $facebookLeadCenterExtension['name'] }}</div>
                    </div>
                    <div class="rounded-xl bg-[#F7F6F3] p-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Version</div>
                        <div class="mt-1 font-semibold text-brand-primary">v{{ $facebookLeadCenterExtension['version'] }}</div>
                    </div>
                    <div class="rounded-xl bg-[#F7F6F3] p-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">CRM Base URL</div>
                        <div class="mt-1 font-semibold text-brand-primary break-all">{{ $facebookLeadCenterExtension['crmBaseUrl'] }}</div>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-[#E5DED4] bg-[#FBFCFA] p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-brand-primary">Lead Center Audit Stats</div>
                            <p class="mt-1 text-xs text-[#6B7280]">Extension scans, matched/missing rows aur recent audit activity yahan dikhegi.</p>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Last Scan</div>
                            <div class="mt-1 text-sm font-semibold text-brand-primary">
                                {{ $facebookLeadCenterExtension['stats']['last_scanned_at'] ? $facebookLeadCenterExtension['stats']['last_scanned_at']->format('d M, h:i A') : 'No scan yet' }}
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-white border border-[#E5DED4] p-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Today Scans</div>
                            <div class="mt-1 text-2xl font-bold text-brand-primary">{{ $facebookLeadCenterExtension['stats']['today_scans'] }}</div>
                        </div>
                        <div class="rounded-xl bg-white border border-[#E5DED4] p-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Rows Checked</div>
                            <div class="mt-1 text-2xl font-bold text-brand-primary">{{ $facebookLeadCenterExtension['stats']['today_rows'] }}</div>
                        </div>
                        <div class="rounded-xl bg-white border border-orange-200 p-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-orange-700">Today Missing</div>
                            <div class="mt-1 text-2xl font-bold text-orange-800">{{ $facebookLeadCenterExtension['stats']['today_missing'] }}</div>
                        </div>
                        <div class="rounded-xl bg-white border border-blue-200 p-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-blue-700">Total Scans</div>
                            <div class="mt-1 text-2xl font-bold text-blue-800">{{ $facebookLeadCenterExtension['stats']['processed_total'] }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-[#E5DED4] bg-white p-4">
                    @php
                        $scanQuality = $facebookLeadCenterExtension['stats']['latest_scan_quality'] ?? [];
                    @endphp
                    @if(!empty($scanQuality['expected_rows']) && empty($scanQuality['scan_complete']))
                        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            <div class="font-bold">Incomplete Facebook scan</div>
                            <div class="mt-1 text-xs">
                                Latest scan {{ $scanQuality['scanned_rows'] ?? 0 }}/{{ $scanQuality['expected_rows'] ?? 0 }} rows read kar paya.
                                Extension me <strong>Scan with details</strong> use karo ya Facebook side panels close karke rescan karo.
                            </div>
                        </div>
                    @endif

                    @if(($facebookLeadCenterExtension['stats']['missing_report_groups'] ?? collect())->count())
                        <div class="mb-4">
                            <div class="font-bold text-brand-primary">Missing Lead Report</div>
                            <div class="mt-3 overflow-x-auto rounded-xl border border-[#E5DED4]">
                                <table class="min-w-full divide-y divide-[#E5DED4] text-xs">
                                    <thead class="bg-[#F7F6F3] text-left uppercase tracking-[0.12em] text-[#6B7280]">
                                        <tr>
                                            <th class="px-3 py-2">Page</th>
                                            <th class="px-3 py-2">Form</th>
                                            <th class="px-3 py-2">Issue</th>
                                            <th class="px-3 py-2">Count</th>
                                            <th class="px-3 py-2">Sample</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#E5DED4] bg-white">
                                        <?php foreach($facebookLeadCenterExtension['stats']['missing_report_groups'] as $group): ?>
                                            <tr>
                                                <td class="px-3 py-2 font-semibold text-brand-primary">{{ $group['page_id'] }}</td>
                                                <td class="px-3 py-2 text-[#374151]">{{ $group['form_id'] }}</td>
                                                <td class="px-3 py-2 text-[#92400E]">{{ $group['issue'] }}</td>
                                                <td class="px-3 py-2 font-bold text-orange-800">{{ $group['count'] }}</td>
                                                <td class="px-3 py-2 text-[#6B7280]">{{ $group['sample'] }}</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="font-bold text-brand-primary">Recent Scan Rows</div>
                    <div class="mt-3 space-y-3">
                        <?php if($facebookLeadCenterExtension['stats']['recent_rows']->isEmpty()): ?>
                            <div class="rounded-xl border border-dashed border-[#D6D3CE] bg-[#FBFCFA] px-4 py-5 text-sm text-[#6B7280]">
                                Abhi tak Facebook Lead Center extension scan nahi hua.
                            </div>
                        <?php endif; ?>
                        <?php foreach($facebookLeadCenterExtension['stats']['recent_rows'] as $row): ?>
                            <?php
                                $statusClass = match($row['status']) {
                                    'in_crm', 'imported' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'webhook_received' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'missing' => 'bg-orange-50 text-orange-700 border-orange-200',
                                    'possible_duplicate' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                                    default => 'bg-slate-50 text-slate-600 border-slate-200',
                                };
                            ?>
                            <div class="rounded-xl border border-[#E5DED4] bg-[#FBFCFA] p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-brand-primary">{{ $row['lead_name'] ?: ($row['phone'] ?: ($row['email'] ?: 'Unreadable lead')) }}</div>
                                        <div class="mt-1 text-xs text-[#6B7280]">{{ $row['match_reason'] ?: 'No detail' }}</div>
                                        <div class="mt-1 text-[11px] text-[#9CA3AF]">
                                            Confidence: {{ strtoupper($row['confidence'] ?? 'none') }}
                                            @if(!empty($row['issue_group']))
                                                · {{ $row['issue_group'] }}
                                            @endif
                                        </div>
                                    </div>
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $statusClass }}">{{ ucwords(str_replace('_', ' ', $row['status'])) }}</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-[#E5DED4] bg-white p-4">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <div class="font-bold text-brand-primary">Extension Token</div>
                            <p class="mt-1 text-xs text-[#6B7280]">Generate karke Facebook extension popup me paste karo.</p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" id="fbLcGenerateTokenBtn" class="inline-flex items-center gap-2 rounded-lg bg-[#205A44] px-3 py-2 text-xs font-bold text-white">
                                <i class="fas fa-key"></i>
                                Generate
                            </button>
                            <button type="button" id="fbLcCopyTokenBtn" class="inline-flex items-center gap-2 rounded-lg border border-[#D6E2DA] bg-white px-3 py-2 text-xs font-bold text-brand-primary">
                                <i class="fas fa-copy"></i>
                                Copy
                            </button>
                            <button type="button" id="fbLcTestConnectionBtn" class="inline-flex items-center gap-2 rounded-lg border border-[#D6E2DA] bg-white px-3 py-2 text-xs font-bold text-brand-primary">
                                <i class="fas fa-plug"></i>
                                Test Connection
                            </button>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="fbLcTokenField" class="block text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Sanctum Token</label>
                        <textarea id="fbLcTokenField" rows="4" class="mt-2 w-full rounded-xl border border-[#E5DED4] bg-[#F7F6F3] px-3 py-3 font-mono text-xs text-[#173128]" placeholder="Generate token here..."></textarea>
                    </div>

                    <div id="fbLcTestResult" class="mt-3 hidden rounded-xl border px-3 py-3 text-xs"></div>
                </div>
            </div>

            @unless($extensionsOnly)
            <div class="bg-[#F7F4EE] border border-[#E5DED4] rounded-2xl p-5 text-sm text-[#374151]">
                <div class="font-bold text-brand-primary">Simple Process</div>
                <ol class="mt-3 space-y-3 list-decimal list-inside">
                    <li>New APK build/upload karo.</li>
                    <li>Version Code/Name save karo.</li>
                    <li><strong>Publish & Notify Users</strong> dabao.</li>
                    <li>User app open karega, Update Now dabayega.</li>
                </ol>
            </div>
            @endunless

            @unless($extensionsOnly)
            <div class="bg-white border border-[#E5DED4] rounded-2xl p-5 text-sm">
                <div class="font-bold text-brand-primary">APK URLs</div>
                <div class="mt-3 space-y-3">
                    <div class="rounded-xl bg-[#F7F6F3] p-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Install URL Used By App</div>
                        <a href="{{ $settings['apkUrl'] }}" target="_blank" class="mt-1 block break-all text-[#205A44] font-semibold">{{ $settings['apkUrl'] }}</a>
                    </div>
                    <div class="rounded-xl bg-[#F7F6F3] p-3">
                        <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Always Latest Copy</div>
                        <a href="{{ $settings['latestApkUrl'] }}" target="_blank" class="mt-1 block break-all text-[#205A44] font-semibold">{{ $settings['latestApkUrl'] }}</a>
                    </div>
                    @if(!empty($settings['versionedApkUrl']))
                        <div class="rounded-xl bg-[#F7F6F3] p-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Version Backup</div>
                            <a href="{{ $settings['versionedApkUrl'] }}" target="_blank" class="mt-1 block break-all text-[#205A44] font-semibold">{{ $settings['versionedApkUrl'] }}</a>
                        </div>
                    @endif
                </div>
            </div>
            @endunless

            @unless($extensionsOnly)
            <div class="bg-white border border-[#E5DED4] rounded-2xl p-5 text-sm">
                <div class="font-bold text-brand-primary">Current API Response</div>
                <pre class="mt-3 whitespace-pre-wrap rounded-xl bg-[#F7F6F3] p-4 text-xs text-[#374151]">{{ json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
            @endunless
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tokenField = document.getElementById('waExtTokenField');
    const generateBtn = document.getElementById('waExtGenerateTokenBtn');
    const copyBtn = document.getElementById('waExtCopyTokenBtn');
    const testBtn = document.getElementById('waExtTestConnectionBtn');
    const resultBox = document.getElementById('waExtTestResult');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function setResult(message, success) {
        resultBox.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800', 'border-red-200', 'bg-red-50', 'text-red-800');
        resultBox.classList.add(success ? 'border-emerald-200' : 'border-red-200');
        resultBox.classList.add(success ? 'bg-emerald-50' : 'bg-red-50');
        resultBox.classList.add(success ? 'text-emerald-800' : 'text-red-800');
        resultBox.textContent = message;
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload || {}),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || data.error || 'Request failed.');
        }

        return data;
    }

    generateBtn?.addEventListener('click', async function () {
        generateBtn.disabled = true;
        try {
            const data = await postJson(@json(route('admin.mobile-app-update.whatsapp-extension.generate-token')));
            tokenField.value = data.token || '';
            setResult(data.message || 'Token generated successfully.', true);
        } catch (error) {
            setResult(error.message, false);
        } finally {
            generateBtn.disabled = false;
        }
    });

    copyBtn?.addEventListener('click', async function () {
        const token = tokenField.value.trim();
        if (!token) {
            setResult('Pehle token generate karo.', false);
            return;
        }

        try {
            await navigator.clipboard.writeText(token);
            setResult('Token copied. Extension popup me paste kar do.', true);
        } catch (error) {
            setResult('Copy failed. Manual copy karo.', false);
        }
    });

    testBtn?.addEventListener('click', async function () {
        const token = tokenField.value.trim();
        if (!token) {
            setResult('Test se pehle token generate ya paste karo.', false);
            return;
        }

        testBtn.disabled = true;
        try {
            const data = await postJson(@json(route('admin.mobile-app-update.whatsapp-extension.test-connection')), { token });
            const userLine = data.user ? ` User: ${data.user.name} (${data.user.role || 'No role'}).` : '';
            setResult((data.message || 'Connection successful.') + userLine, true);
        } catch (error) {
            setResult(error.message, false);
        } finally {
            testBtn.disabled = false;
        }
    });

    const fbLcTokenField = document.getElementById('fbLcTokenField');
    const fbLcGenerateBtn = document.getElementById('fbLcGenerateTokenBtn');
    const fbLcCopyBtn = document.getElementById('fbLcCopyTokenBtn');
    const fbLcTestBtn = document.getElementById('fbLcTestConnectionBtn');
    const fbLcResultBox = document.getElementById('fbLcTestResult');

    function setFacebookLeadCenterResult(message, success) {
        fbLcResultBox.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800', 'border-red-200', 'bg-red-50', 'text-red-800');
        fbLcResultBox.classList.add(success ? 'border-emerald-200' : 'border-red-200');
        fbLcResultBox.classList.add(success ? 'bg-emerald-50' : 'bg-red-50');
        fbLcResultBox.classList.add(success ? 'text-emerald-800' : 'text-red-800');
        fbLcResultBox.textContent = message;
    }

    fbLcGenerateBtn?.addEventListener('click', async function () {
        fbLcGenerateBtn.disabled = true;
        try {
            const data = await postJson(@json(route('admin.mobile-app-update.facebook-lead-center-extension.generate-token')));
            fbLcTokenField.value = data.token || '';
            setFacebookLeadCenterResult(data.message || 'Token generated successfully.', true);
        } catch (error) {
            setFacebookLeadCenterResult(error.message, false);
        } finally {
            fbLcGenerateBtn.disabled = false;
        }
    });

    fbLcCopyBtn?.addEventListener('click', async function () {
        const token = fbLcTokenField.value.trim();
        if (!token) {
            setFacebookLeadCenterResult('Pehle token generate karo.', false);
            return;
        }

        try {
            await navigator.clipboard.writeText(token);
            setFacebookLeadCenterResult('Token copied. Facebook extension popup me paste kar do.', true);
        } catch (error) {
            setFacebookLeadCenterResult('Copy failed. Manual copy karo.', false);
        }
    });

    fbLcTestBtn?.addEventListener('click', async function () {
        const token = fbLcTokenField.value.trim();
        if (!token) {
            setFacebookLeadCenterResult('Test se pehle token generate ya paste karo.', false);
            return;
        }

        fbLcTestBtn.disabled = true;
        try {
            const data = await postJson(@json(route('admin.mobile-app-update.facebook-lead-center-extension.test-connection')), { token });
            const userLine = data.user ? ` User: ${data.user.name} (${data.user.role || 'No role'}).` : '';
            setFacebookLeadCenterResult((data.message || 'Connection successful.') + userLine, true);
        } catch (error) {
            setFacebookLeadCenterResult(error.message, false);
        } finally {
            fbLcTestBtn.disabled = false;
        }
    });
});
</script>
@endsection
