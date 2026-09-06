<div class="bg-white border border-[#E5DED4] rounded-2xl p-5 text-sm shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="font-bold text-brand-primary">{{ $title }}</div>
            <p class="mt-1 text-[#6B7280]">{{ $description }}</p>
        </div>
        @if($extension['available'])
            <a href="{{ $extension['downloadRoute'] }}" class="inline-flex items-center gap-2 rounded-xl bg-[#205A44] px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#184533]">
                <i class="fas fa-download"></i>
                Download
            </a>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="rounded-xl bg-[#F7F6F3] p-3">
            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Package</div>
            <div class="mt-1 font-semibold text-brand-primary">{{ $extension['name'] }}</div>
        </div>
        <div class="rounded-xl bg-[#F7F6F3] p-3">
            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Version</div>
            <div class="mt-1 font-semibold text-brand-primary">v{{ $extension['version'] }}</div>
        </div>
        <div class="md:col-span-2 rounded-xl bg-[#F7F6F3] p-3">
            <div class="text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">CRM Base URL</div>
            <div class="mt-1 font-semibold text-brand-primary break-all">{{ $extension['crmBaseUrl'] }}</div>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-[#E5DED4] bg-white p-4">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <div class="font-bold text-brand-primary">Extension Token</div>
                <p class="mt-1 text-xs text-[#6B7280]">{{ $tokenHelp }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" id="{{ $key }}GenerateTokenBtn" class="inline-flex items-center gap-2 rounded-lg bg-[#205A44] px-3 py-2 text-xs font-bold text-white">
                    <i class="fas fa-key"></i>
                    Generate
                </button>
                <button type="button" id="{{ $key }}CopyTokenBtn" class="inline-flex items-center gap-2 rounded-lg border border-[#D6E2DA] bg-white px-3 py-2 text-xs font-bold text-brand-primary">
                    <i class="fas fa-copy"></i>
                    Copy
                </button>
                <button type="button" id="{{ $key }}TestConnectionBtn" class="inline-flex items-center gap-2 rounded-lg border border-[#D6E2DA] bg-white px-3 py-2 text-xs font-bold text-brand-primary">
                    <i class="fas fa-plug"></i>
                    Test Connection
                </button>
            </div>
        </div>

        <div class="mt-3">
            <label for="{{ $key }}TokenField" class="block text-xs font-semibold uppercase tracking-[0.12em] text-[#6B7280]">Sanctum Token</label>
            <textarea id="{{ $key }}TokenField" rows="4" class="mt-2 w-full rounded-xl border border-[#E5DED4] bg-[#F7F6F3] px-3 py-3 font-mono text-xs text-[#173128]" placeholder="Generate token here..."></textarea>
        </div>

        <div id="{{ $key }}TestResult" class="mt-3 hidden rounded-xl border px-3 py-3 text-xs"></div>
    </div>
</div>
