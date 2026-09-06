@extends('layouts.app')

@section('title', 'Facebook OAuth Connector - ' . brand_name())
@section('page-title', 'Facebook OAuth Connector')

@section('content')
<div class="w-full space-y-5">
    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</div>
    @endif

    <div class="rounded-xl bg-gradient-to-r from-[#063A1C] via-[#0f6b34] to-[#16803f] px-6 py-5 text-white shadow-sm">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-white/70">Parallel connector</p>
                <h1 class="mt-1 text-2xl font-black">Facebook Login for Business</h1>
                <p class="mt-2 max-w-5xl text-sm leading-6 text-white/85">OAuth connector is isolated for Meta App Review testing. The existing manual token Facebook Lead Ads flow is not changed.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-3">
                <a href="{{ route('integrations.facebook-lead-ads.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/15">
                    <i class="fab fa-facebook"></i>
                    Old Manual Flow
                </a>
                @if($isConfigured)
                    <a href="{{ route('integrations.facebook-connector.connect') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-[#0b3b1d] shadow-sm transition hover:bg-emerald-50">
                        <i class="fas fa-link"></i>
                        Connect Facebook
                    </a>
                @else
                    <button type="button" class="inline-flex cursor-not-allowed items-center gap-2 rounded-lg bg-white/70 px-4 py-2.5 text-sm font-semibold text-[#0b3b1d]/70 shadow-sm" title="Complete Meta app settings before connecting.">
                        <i class="fas fa-link"></i>
                        Connect Facebook
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-12">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
            <div class="text-xs font-bold uppercase text-slate-500">App Config</div>
            <div class="mt-2 text-lg font-black {{ $isConfigured ? 'text-green-700' : 'text-amber-700' }}">{{ $isConfigured ? 'Ready' : 'Action Needed' }}</div>
            <div class="mt-1 text-sm text-slate-500">Graph {{ $graphVersion }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-6">
            <div class="text-xs font-bold uppercase text-slate-500">Webhook URL</div>
            <div class="mt-2 rounded-lg bg-slate-50 px-3 py-2 font-mono text-sm font-semibold text-slate-800">
                <span class="break-all">{{ $webhookUrl }}</span>
            </div>
            <div class="mt-3 text-xs font-bold uppercase text-slate-500">Recommended single Page webhook</div>
            <div class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 font-mono text-sm font-semibold text-emerald-900">
                <span class="break-all">{{ url('/api/webhooks/facebook/page') }}</span>
            </div>
            <p class="mt-2 text-xs text-slate-500">Use this dispatcher URL when one Meta app must support old manual leads and the OAuth connector together.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
            <div class="text-xs font-bold uppercase text-slate-500">Mode</div>
            <div class="mt-2 text-lg font-black text-slate-900">Per-page control</div>
            <div class="mt-1 text-sm text-slate-500">Default is sandbox. Enable lead creation only after review approval.</div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-900">Codebase Readiness</h2>
                <p class="mt-1 text-sm text-slate-500">This panel confirms the Meta app values required for the Facebook Login for Business connector.</p>
            </div>
            <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-bold {{ $isConfigured ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                {{ $isConfigured ? 'Ready to connect' : 'Waiting for Meta config' }}
            </span>
        </div>
        <div class="grid gap-3 lg:grid-cols-4">
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase text-slate-500">App ID / Secret</div>
                <div class="mt-2 text-sm font-bold {{ $appCredentialsConfigured ? 'text-green-700' : 'text-amber-700' }}">{{ $appCredentialsConfigured ? 'Present' : 'Missing' }}</div>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase text-slate-500">Login Config ID</div>
                <div class="mt-2 text-sm font-bold {{ $loginConfigConfigured ? 'text-green-700' : 'text-amber-700' }}">{{ $loginConfigConfigured ? 'Present' : 'Pending from Meta' }}</div>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase text-slate-500">Webhook Verify Token</div>
                <div class="mt-2 text-sm font-bold {{ $webhookTokenConfigured ? 'text-green-700' : 'text-amber-700' }}">{{ $webhookTokenConfigured ? 'Present' : 'Missing' }}</div>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase text-slate-500">Redirect URI</div>
                <div class="mt-2 break-all font-mono text-xs font-semibold text-slate-800">{{ $redirectUri }}</div>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-900">Meta Permission Usage</h2>
                <p class="mt-1 text-sm text-slate-500">These are the permissions used by this connector during the review demo flow.</p>
            </div>
            <span class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">Review helper</span>
        </div>
        <div class="grid gap-3 lg:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase text-slate-500">pages_show_list</div>
                <p class="mt-2 text-sm text-slate-700">Shows Facebook Pages the user can connect to {{ brand_name() }}.</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase text-slate-500">pages_read_engagement</div>
                <p class="mt-2 text-sm text-slate-700">Reads basic Page details needed to identify connected Pages.</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase text-slate-500">pages_manage_metadata</div>
                <p class="mt-2 text-sm text-slate-700">Subscribes the selected Page to the leadgen webhook.</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase text-slate-500">business_management</div>
                <p class="mt-2 text-sm text-slate-700">Fetches business-owned Pages selected during Facebook Login.</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase text-slate-500">leads_retrieval</div>
                <p class="mt-2 text-sm text-slate-700">Receives and stores leadgen webhook events in sandbox mode.</p>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-black text-slate-900">Connected Pages</h2>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $connections->sum(fn ($connection) => $connection->pages->count()) }} Pages</span>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($connections as $connection)
                <div class="p-6">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="text-sm font-bold text-slate-900">{{ $connection->meta_user_name ?: 'Facebook User' }}</div>
                            <div class="text-xs text-slate-500">Status: {{ ucfirst($connection->status) }} - Connected: {{ optional($connection->last_connected_at)->format('d M Y H:i') ?: 'N/A' }}</div>
                        </div>
                        @if($connection->status === 'connected')
                            <form method="POST" action="{{ route('integrations.facebook-connector.disconnect') }}">
                                @csrf
                                <input type="hidden" name="connection_id" value="{{ $connection->id }}">
                                <button class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100" type="submit">Disconnect</button>
                            </form>
                        @endif
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @forelse($connection->pages as $page)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="font-bold text-slate-900">{{ $page->page_name ?: 'Page ' . $page->page_id }}</div>
                                <div class="mt-1 text-xs text-slate-500">Page ID: {{ $page->page_id }}</div>
                                <div class="mt-3">
                                    @if($page->leadgen_subscribed)
                                        <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-bold text-green-800">Leadgen subscribed</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Not subscribed</span>
                                    @endif
                                </div>
                                @if($page->last_error)
                                    <div class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{{ $page->last_error }}</div>
                                @endif
                                <div class="mt-4 rounded-lg border border-slate-200 bg-white p-3">
                                    <div class="text-xs font-bold uppercase text-slate-500">Lead mode</div>
                                    <div class="mt-2 text-sm font-bold {{ $page->lead_mode === 'create_leads' ? 'text-green-700' : 'text-amber-700' }}">
                                        {{ $page->lead_mode === 'create_leads' ? 'Create CRM leads' : 'Sandbox store-only' }}
                                    </div>
                                    <form method="POST" action="{{ route('integrations.facebook-connector.pages.mode', $page) }}" class="mt-3 space-y-3">
                                        @csrf
                                        <select name="lead_mode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                            <option value="sandbox" @selected($page->lead_mode !== 'create_leads')>Sandbox store-only</option>
                                            <option value="create_leads" @selected($page->lead_mode === 'create_leads')>Create CRM leads</option>
                                        </select>
                                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                                            <input type="checkbox" name="auto_assign_leads" value="1" class="rounded border-slate-300" @checked($page->auto_assign_leads)>
                                            Auto assign new leads
                                        </label>
                                        <button class="w-full rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" type="submit">
                                            Save mode
                                        </button>
                                    </form>
                                </div>
                                <form method="POST" action="{{ route('integrations.facebook-connector.pages.subscribe', $page) }}" class="mt-4">
                                    @csrf
                                    <button class="w-full rounded-lg bg-[#0b3b1d] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#205A44]" type="submit">
                                        Subscribe leadgen
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('integrations.facebook-connector.pages.forms.refresh', $page) }}" class="mt-3">
                                    @csrf
                                    <button class="w-full rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100" type="submit">
                                        Sync lead forms
                                    </button>
                                </form>
                                <div class="mt-4 rounded-lg border border-slate-200 bg-white p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="text-xs font-bold uppercase text-slate-500">Lead forms</div>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ $page->forms->count() }}</span>
                                    </div>
                                    <div class="mt-3 space-y-2">
                                        @forelse($page->forms as $form)
                                            <div class="rounded-lg bg-slate-50 px-3 py-2">
                                                <div class="text-sm font-bold text-slate-800">{{ $form->form_name ?: 'Form ' . $form->form_id }}</div>
                                                <div class="mt-1 break-all font-mono text-xs text-slate-500">{{ $form->form_id }}</div>
                                                @if($form->status)
                                                    <div class="mt-1 text-xs font-semibold text-slate-500">Status: {{ $form->status }}</div>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="rounded-lg border border-dashed border-slate-300 px-3 py-3 text-xs text-slate-500">
                                                No forms synced yet. Click Sync lead forms after Page access is connected.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">No Pages returned by Meta for this connection.</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-slate-500">No OAuth connection yet.</div>
            @endforelse
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="text-lg font-black text-slate-900">Recent OAuth Webhook Events</h2>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $recentEvents->count() }} Events</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase text-slate-500">
                    <tr>
                        <th class="px-6 py-3">Time</th>
                        <th class="px-6 py-3">Page</th>
                        <th class="px-6 py-3">Form</th>
                        <th class="px-6 py-3">Leadgen</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">CRM Lead</th>
                        <th class="px-6 py-3">Details</th>
                        <th class="px-6 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentEvents as $event)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-3 text-slate-500">{{ $event->created_at->format('d M Y H:i') }}</td>
                            <td class="px-6 py-3 font-semibold text-slate-800">{{ $event->page_id ?: 'N/A' }}</td>
                            <td class="px-6 py-3">{{ $event->form_id ?: 'N/A' }}</td>
                            <td class="px-6 py-3 font-mono text-xs">{{ $event->leadgen_id ?: 'N/A' }}</td>
                            <td class="px-6 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold
                                    @if($event->status === 'lead_created') bg-green-100 text-green-800
                                    @elseif($event->status === 'duplicate') bg-blue-100 text-blue-800
                                    @elseif($event->status === 'failed') bg-red-100 text-red-800
                                    @elseif($event->status === 'fetched') bg-purple-100 text-purple-800
                                    @else bg-slate-100 text-slate-700 @endif">
                                    {{ str_replace('_', ' ', ucfirst($event->status)) }}
                                </span>
                                @if($event->error)
                                    <div class="mt-2 max-w-xs text-xs text-red-600">{{ $event->error }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                @if($event->crmLead)
                                    <a class="text-sm font-bold text-emerald-700 hover:underline" href="{{ route('leads.show', $event->crmLead) }}" target="_blank" rel="noopener">
                                        #{{ $event->crmLead->id }} {{ $event->crmLead->name }}
                                    </a>
                                @else
                                    <span class="text-sm text-slate-400">N/A</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                <details class="group">
                                    <summary class="cursor-pointer text-xs font-bold text-emerald-700">View payload</summary>
                                    <pre class="mt-2 max-h-64 w-[32rem] max-w-[80vw] overflow-auto rounded-lg bg-slate-950 p-3 text-xs leading-5 text-slate-100">{{ json_encode([
                                        'webhook_payload' => $event->raw_payload,
                                        'lead_payload' => $event->lead_payload,
                                        'field_data' => $event->field_data,
                                        'mapped_data' => $event->mapped_data,
                                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            </td>
                            <td class="px-6 py-3">
                                @if($event->page?->lead_mode === 'create_leads' && !$event->crm_lead_id && !in_array($event->status, ['lead_created', 'duplicate'], true))
                                    <form method="POST" action="{{ route('integrations.facebook-connector.events.process', $event) }}">
                                        @csrf
                                        <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50" type="submit">Process</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-6 py-6 text-center text-slate-500" colspan="8">No OAuth webhook event yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-black text-slate-900">Compliance Links</h2>
                <p class="mt-1 text-sm text-slate-500">Public URLs used for Meta App Review and user data requests.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>
                <a class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms of Service</a>
                <a class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" href="{{ route('legal.data-deletion') }}" target="_blank" rel="noopener">Data Deletion</a>
            </div>
        </div>
    </div>
</div>
@endsection
