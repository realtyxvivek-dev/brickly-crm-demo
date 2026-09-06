@extends('layouts.app')

@section('title', ($template->exists ? 'Edit Template' : 'Create Template') . ' - ' . brand_name())
@section('page-title', $template->exists ? 'Edit Template' : 'Create Template')

@section('header-actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('template-management.index') }}" class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 text-sm font-semibold">
            <i class="fas fa-arrow-left mr-2"></i>Back
        </a>
        <a href="{{ route('integrations.meta-waba.index') }}" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg hover:bg-slate-50 text-sm font-semibold">
            <i class="fab fa-whatsapp mr-2"></i>WABA Settings
        </a>
    </div>
@endsection

@push('styles')
<style>
    .tm-shell { display:grid; grid-template-columns:minmax(0,1fr) 430px; gap:28px; align-items:start; }
    .tm-stack { display:flex; flex-direction:column; gap:22px; }
    .tm-card { background:#fff; border:1px solid #dbe3ee; border-radius:10px; box-shadow:0 2px 8px rgba(15,23,42,.08); padding:28px 30px; }
    .tm-card h2 { font-size:22px; line-height:1.2; color:#0f2544; font-weight:800; margin-bottom:12px; }
    .tm-help { color:#617598; font-size:14px; line-height:1.55; }
    .tm-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:26px 38px; }
    .tm-field label { display:block; font-size:16px; font-weight:800; color:#132744; margin-bottom:12px; }
    .tm-required { color:#ef4444; }
    .tm-input, .tm-select, .tm-textarea { width:100%; border:1px solid #d9e2ef; background:#f8fafc; border-radius:10px; min-height:58px; padding:0 16px; color:#020617; font-size:17px; outline:none; transition:border .18s, box-shadow .18s, background .18s; }
    .tm-textarea { min-height:220px; padding:18px 20px; resize:vertical; line-height:1.6; }
    .tm-input:focus, .tm-select:focus, .tm-textarea:focus { border-color:#10b981; box-shadow:0 0 0 4px rgba(16,185,129,.18); background:#fff; }
    .tm-counter { display:flex; justify-content:space-between; margin-top:10px; color:#8395b4; font-size:13px; font-weight:700; }
    .tm-category-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; margin-top:18px; }
    .tm-choice { border:1px solid #e5edf6; background:#fff; border-radius:10px; min-height:86px; padding:18px; display:flex; gap:14px; align-items:center; cursor:pointer; transition:.18s; }
    .tm-choice input { position:absolute; opacity:0; pointer-events:none; }
    .tm-choice-icon { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:#f8fafc; color:#64748b; font-size:18px; }
    .tm-choice-title { display:block; color:#64748b; font-weight:800; font-size:16px; }
    .tm-choice-sub { display:block; color:#94a3b8; font-weight:800; font-size:12px; letter-spacing:.06em; margin-top:3px; }
    .tm-choice:has(input:checked) { border-color:#10b981; background:#effdf7; }
    .tm-choice:has(input:checked) .tm-choice-icon { background:#c9f7df; color:#059669; }
    .tm-choice:has(input:checked) .tm-choice-title, .tm-choice:has(input:checked) .tm-choice-sub { color:#10b981; }
    .tm-media-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; }
    .tm-media { min-height:98px; flex-direction:column; justify-content:center; text-align:center; }
    .tm-toolbar { border:1px solid #e2e8f0; border-bottom:none; border-radius:12px 12px 0 0; min-height:54px; display:flex; align-items:center; gap:4px; padding:0 14px; background:#fff; color:#334155; }
    .tm-tool { width:32px; height:32px; display:inline-flex; align-items:center; justify-content:center; border:0; background:transparent; color:#334155; border-radius:8px; }
    .tm-tool:hover { background:#f1f5f9; }
    .tm-editor { border-radius:0; border-top-left-radius:0; border-top-right-radius:0; }
    .tm-editor-foot { border:1px solid #e2e8f0; border-top:none; border-radius:0 0 12px 12px; min-height:72px; display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#fff; }
    .tm-dot { width:8px; height:8px; border-radius:999px; background:#49cda0; }
    .tm-btn { min-height:42px; padding:0 18px; border-radius:10px; font-size:13px; font-weight:900; display:inline-flex; align-items:center; gap:8px; justify-content:center; border:1px solid #dbe3ee; background:#fff; color:#64748b; cursor:pointer; }
    .tm-btn.primary { background:#10b981; border-color:#10b981; color:#fff; }
    .tm-btn.soft { color:#059669; border-color:#dbe3ee; box-shadow:0 2px 8px rgba(15,23,42,.08); }
    .tm-btn.danger { color:#dc2626; }
    .tm-actions { position:sticky; bottom:0; z-index:5; background:rgba(248,250,252,.92); backdrop-filter:blur(10px); border:1px solid #e2e8f0; border-radius:12px; padding:14px; display:flex; justify-content:flex-end; gap:12px; }
    .tm-preview-wrap { position:sticky; top:20px; background:#fff; border:1px solid #e5edf6; border-radius:10px; box-shadow:0 2px 8px rgba(15,23,42,.08); padding:30px 18px; }
    .tm-phone { width:388px; height:764px; margin:0 auto; background:#edf4f0; border:5px solid #111; border-radius:52px; overflow:hidden; box-shadow:0 22px 50px rgba(15,23,42,.2); position:relative; }
    .tm-notch { position:absolute; top:0; left:50%; transform:translateX(-50%); width:120px; height:28px; background:#111; border-radius:0 0 18px 18px; z-index:2; }
    .tm-wa-head { height:106px; background:#075e50; color:#fff; display:flex; align-items:center; gap:16px; padding:34px 22px 14px; }
    .tm-brand-icon { width:36px; height:36px; border-radius:999px; background:#0f766e; display:flex; align-items:center; justify-content:center; color:#b7f7dc; }
    .tm-brand-name { font-weight:800; font-size:17px; }
    .tm-brand-sub { color:#c9f7df; font-size:12px; margin-top:2px; }
    .tm-wa-body { min-height:658px; padding:28px 16px; background-color:#edf4f0; background-image:radial-gradient(#cbd8d4 1.7px, transparent 1.7px); background-size:38px 38px; }
    .tm-today { width:max-content; margin:0 auto 12px; padding:6px 12px; border-radius:4px; background:#e3f2fd; color:#0277bd; font-size:11px; font-weight:900; box-shadow:0 1px 4px rgba(15,23,42,.18); }
    .tm-bubble { background:#fff; border:1px solid #dde4ec; border-radius:10px; box-shadow:0 2px 5px rgba(15,23,42,.15); overflow:hidden; }
    .tm-bubble-head { font-size:17px; font-weight:900; padding:16px; border-bottom:1px solid #e2e8f0; color:#020617; }
    .tm-bubble-body { padding:16px; color:#020617; font-size:16px; line-height:1.55; white-space:pre-wrap; word-break:break-word; }
    .tm-bubble-footer { color:#8395b4; font-size:13px; margin-top:12px; }
    .tm-bubble-time { text-align:right; color:#8b9bbb; font-size:12px; margin-top:10px; }
    .tm-preview-buttons { border-top:1px solid #edf2f7; }
    .tm-preview-button { min-height:42px; display:flex; align-items:center; justify-content:center; gap:8px; color:#0284c7; font-weight:800; font-size:14px; border-top:1px solid #edf2f7; }
    .tm-alert { padding:14px 16px; border-radius:10px; margin-bottom:16px; font-size:14px; font-weight:700; }
    .tm-alert.success { background:#dcfce7; color:#166534; border:1px solid #86efac; }
    .tm-alert.error { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
    @media (max-width:1280px) { .tm-shell { grid-template-columns:1fr; } .tm-preview-wrap { position:relative; top:0; } }
    @media (max-width:760px) { .tm-card { padding:22px 18px; } .tm-grid, .tm-category-grid { grid-template-columns:1fr; } .tm-media-grid { grid-template-columns:repeat(2,1fr); } .tm-phone { width:310px; height:620px; border-radius:42px; } .tm-wa-body { min-height:514px; } }
</style>
@endpush

@section('content')
<div class="tm-shell">
    <form id="templateBuilderForm" class="tm-stack" method="POST" action="{{ $template->exists ? route('template-management.update', $template->id) : route('template-management.store') }}">
        @csrf
        @if($template->exists)
            @method('PUT')
        @endif

        @if(session('success'))
            <div class="tm-alert success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="tm-alert error">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="tm-card">
            <div class="tm-grid">
                <div class="tm-field">
                    <label>Template Name <span class="tm-required">*</span></label>
                    <input class="tm-input" name="name" id="tplName" value="{{ $state['name'] }}" placeholder="admin_general_reminder" required pattern="[a-z0-9_]+">
                    <div class="tm-help mt-2">Lowercase letters, numbers and underscores only.</div>
                </div>
                <div class="tm-field">
                    <label>Template Language <span class="tm-required">*</span></label>
                    <select class="tm-select" name="language" id="tplLanguage" required>
                        @foreach(['en_US' => 'English (US)', 'en' => 'English', 'hi' => 'Hindi', 'hi_IN' => 'Hindi (India)'] as $value => $label)
                            <option value="{{ $value }}" @selected($state['language'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="tm-help mt-2">The language this template is written in.</div>
                </div>
                <div class="tm-field">
                    <label>Sector</label>
                    <select class="tm-select" name="sector" id="tplSector">
                        @foreach(['General', 'Real Estate', 'Finance', 'Education', 'Healthcare', 'Retail'] as $sector)
                            <option value="{{ $sector }}" @selected($state['sector'] === $sector)>{{ $sector }}</option>
                        @endforeach
                    </select>
                    <div class="tm-help mt-2">Industry sector this template belongs to.</div>
                </div>
                <div class="tm-field">
                    <label>Template Category</label>
                    <select class="tm-select" name="template_category" id="tplTemplateCategory">
                        @foreach(['Reminder', 'Follow Up', 'Lead Update', 'Payment', 'Booking', 'Support'] as $category)
                            <option value="{{ $category }}" @selected($state['template_category'] === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                    <div class="tm-help mt-2">Specific use-case within the selected sector.</div>
                </div>
            </div>
            <div class="mt-8">
                <label class="block text-base font-extrabold text-slate-900">WhatsApp Category <span class="tm-required">*</span></label>
                <div class="tm-category-grid">
                    @foreach([
                        'UTILITY' => ['Utility', 'fa-regular fa-message'],
                        'MARKETING' => ['Marketing', 'fa-regular fa-window-maximize'],
                        'AUTHENTICATION' => ['Authentication', 'fa-solid fa-shield-halved'],
                    ] as $value => [$label, $icon])
                        <label class="tm-choice">
                            <input type="radio" name="category" value="{{ $value }}" @checked($state['category'] === $value)>
                            <span class="tm-choice-icon"><i class="{{ $icon }}"></i></span>
                            <span><span class="tm-choice-title">{{ $label }}</span><span class="tm-choice-sub">SELECT CATEGORY</span></span>
                        </label>
                    @endforeach
                </div>
                <div class="tm-help mt-3">Classify your template based on its primary purpose.</div>
            </div>
            <div class="tm-field mt-8" style="max-width:420px;">
                <label>Wallet markup % (override)</label>
                <input class="tm-input" name="wallet_markup_override" id="tplMarkup" type="number" min="0" max="100" step="0.01" value="{{ $state['wallet_markup_override'] }}" placeholder="Leave blank to use the category markup">
                <div class="tm-help mt-2">Optional. Overrides category markup for this template only.</div>
            </div>
            @if($accounts->isNotEmpty())
                <div class="tm-field mt-8" style="max-width:520px;">
                    <label>Meta WABA Account</label>
                    <select class="tm-select" name="meta_waba_account_id">
                        <option value="">Use default account</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) $state['account_id'] === (string) $account->id)>
                                {{ $account->display_phone_number ?: $account->name ?: ('Account #' . $account->id) }}{{ $account->is_default ? ' (Default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <div class="tm-help mt-2">Template will be saved and submitted under this API number.</div>
                </div>
            @endif
        </section>

        <section class="tm-card">
            <h2>Template Header</h2>
            <p class="tm-help mb-8">Add an optional header to your template to make it stand out.</p>
            <div class="tm-grid">
                <div class="tm-field">
                    <label>Header Text</label>
                    <input class="tm-input" name="header_text" id="tplHeader" maxlength="60" value="{{ $state['header_text'] }}" placeholder="Friendly Reminder">
                    <div class="tm-counter"><span>Max 60 characters. Variables are not allowed in headers.</span><span><b id="headerCount">0</b> / 60</span></div>
                </div>
                <div class="tm-field">
                    <label>Media Header</label>
                    <input type="hidden" name="header_type" id="headerType" value="{{ $state['header_type'] }}">
                    <div class="tm-media-grid">
                        @foreach(['image' => ['Image', 'fa-regular fa-image'], 'video' => ['Video', 'fa-solid fa-video'], 'document' => ['Document', 'fa-regular fa-file-lines'], 'location' => ['Location', 'fa-solid fa-location-dot']] as $value => [$label, $icon])
                            <button type="button" class="tm-choice tm-media" data-header-type="{{ $value }}">
                                <span class="tm-choice-icon"><i class="{{ $icon }}"></i></span>
                                <span class="tm-choice-sub">{{ strtoupper($label) }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="tm-card">
            <h2>Message Body</h2>
            <p class="tm-help mb-8">This is the main content of your message. Use <span>@{{1}}</span> to add variables.</p>
            <div class="tm-toolbar" aria-label="Editor toolbar">
                @foreach([['B','Bold'], ['I','Italic'], ['U','Underline'], ['S','Strikethrough'], ['H2','Heading'], ['fa-solid fa-list-ul','Bullets'], ['fa-solid fa-list-ol','Numbered'], ['fa-solid fa-quote-right','Quote'], ['fa-solid fa-code','Code'], ['fa-solid fa-link','Link']] as $tool)
                    <button type="button" class="tm-tool" title="{{ $tool[1] }}">{!! str_starts_with($tool[0], 'fa-') ? '<i class="'.$tool[0].'"></i>' : '<span style="font-weight:800">'.$tool[0].'</span>' !!}</button>
                @endforeach
            </div>
            <textarea class="tm-textarea tm-editor" name="body_text" id="tplBody" required>{{ $state['body_text'] }}</textarea>
            <div class="tm-editor-foot">
                <span class="tm-dot"></span>
                <button class="tm-btn soft" type="button" id="addVariableBtn"><i class="fas fa-plus"></i>Add Variable</button>
            </div>
        </section>

        <section class="tm-card" id="variableExamplesCard">
            <h2>Variable Examples</h2>
            <div id="variableExamples" class="tm-grid mt-5"></div>
            <p class="tm-help mt-8"><em>Provide realistic examples for Meta's review process.</em></p>
        </section>

        <section class="tm-card">
            <h2>Template Footer (Optional)</h2>
            <p class="tm-help mb-8">Add a small footer text at the bottom of your message.</p>
            <input class="tm-input" name="footer_text" id="tplFooter" maxlength="60" value="{{ $state['footer_text'] }}" placeholder="We value your membership.">
            <div class="tm-counter"><span>Footer text is limited to 60 characters.</span><span><b id="footerCount">0</b> / 60</span></div>
        </section>

        <section class="tm-card">
            <h2>Interactive Buttons</h2>
            <p class="tm-help mb-8">Add interactive buttons to your message to drive engagement.</p>
            <input type="hidden" name="button_mode" id="buttonMode" value="{{ $state['button_mode'] }}">
            <div class="flex flex-wrap gap-3 mb-6">
                @foreach(['none' => 'None', 'cta' => 'Call To Action', 'quick' => 'Quick Replies', 'all' => 'All'] as $value => $label)
                    <button class="tm-btn" type="button" data-button-mode="{{ $value }}">{{ $label }}</button>
                @endforeach
            </div>
            <div id="ctaFields" class="tm-grid">
                <div class="tm-field">
                    <label>Phone Button</label>
                    <input class="tm-input" name="phone_button_text" id="phoneButtonText" maxlength="25" value="{{ $state['phone_button_text'] }}" placeholder="Call Now">
                    <input class="tm-input mt-3" name="phone_button_number" id="phoneButtonNumber" maxlength="20" value="{{ $state['phone_button_number'] }}" placeholder="+919999999999">
                </div>
                <div class="tm-field">
                    <label>Website Button</label>
                    <input class="tm-input" name="url_button_text" id="urlButtonText" maxlength="25" value="{{ $state['url_button_text'] }}" placeholder="Visit Website">
                    <input class="tm-input mt-3" name="url_button_url" id="urlButtonUrl" value="{{ $state['url_button_url'] }}" placeholder="https://example.com">
                </div>
            </div>
            <div id="quickFields" class="tm-grid mt-6">
                @for($i = 0; $i < 3; $i++)
                    <div class="tm-field">
                        <label>Quick Reply {{ $i + 1 }}</label>
                        <input class="tm-input quick-reply-input" name="quick_reply_buttons[]" maxlength="25" value="{{ $state['quick_reply_buttons'][$i] ?? '' }}" placeholder="{{ $i === 0 ? 'Interested' : ($i === 1 ? 'Not Now' : 'Talk to Team') }}">
                    </div>
                @endfor
            </div>
        </section>

        <div class="tm-actions">
            <button class="tm-btn" type="submit" name="intent" value="draft"><i class="fas fa-save"></i>Save Draft</button>
            <button class="tm-btn primary" type="submit" name="intent" value="submit"><i class="fas fa-paper-plane"></i>Submit for Review</button>
        </div>
    </form>

    <aside class="tm-preview-wrap">
        <div class="tm-phone">
            <div class="tm-notch"></div>
            <div class="tm-wa-head">
                <i class="fas fa-arrow-left"></i>
                <div class="tm-brand-icon"><i class="far fa-image"></i></div>
                <div>
                    <div class="tm-brand-name">Your Brand</div>
                    <div class="tm-brand-sub">Business Account</div>
                </div>
            </div>
            <div class="tm-wa-body">
                <div class="tm-today">TODAY</div>
                <div class="tm-bubble">
                    <div class="tm-bubble-head" id="previewHeader" style="display:none;"></div>
                    <div class="tm-bubble-body">
                        <div id="previewBody"></div>
                        <div class="tm-bubble-footer" id="previewFooter" style="display:none;"></div>
                        <div class="tm-bubble-time">10:57 AM</div>
                    </div>
                    <div class="tm-preview-buttons" id="previewButtons" style="display:none;"></div>
                </div>
            </div>
        </div>
        <p class="tm-help text-center mt-8">This is just a preview. The actual message may appear differently.</p>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const initialExamples = @json(array_values($state['body_examples'] ?? []));
    const els = {
        header: document.getElementById('tplHeader'),
        headerType: document.getElementById('headerType'),
        body: document.getElementById('tplBody'),
        footer: document.getElementById('tplFooter'),
        headerCount: document.getElementById('headerCount'),
        footerCount: document.getElementById('footerCount'),
        examples: document.getElementById('variableExamples'),
        examplesCard: document.getElementById('variableExamplesCard'),
        previewHeader: document.getElementById('previewHeader'),
        previewBody: document.getElementById('previewBody'),
        previewFooter: document.getElementById('previewFooter'),
        previewButtons: document.getElementById('previewButtons'),
        buttonMode: document.getElementById('buttonMode'),
        ctaFields: document.getElementById('ctaFields'),
        quickFields: document.getElementById('quickFields'),
    };

    const text = (value) => (value || '').toString();
    const escapeHtml = (value) => text(value).replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const variableToken = (number) => '{' + '{' + number + '}' + '}';
    const variables = () => [...new Set([...text(els.body.value).matchAll(/\{\{\s*(\d+)\s*\}\}/g)].map(match => Number(match[1])).filter(Boolean))].sort((a, b) => a - b);
    const exampleInputs = () => [...document.querySelectorAll('.variable-example-input')];

    function setHeaderType(type) {
        els.headerType.value = type;
        document.querySelectorAll('[data-header-type]').forEach(button => {
            const active = button.dataset.headerType === type;
            button.classList.toggle('is-active', active);
            button.style.borderColor = active ? '#10b981' : '';
            button.style.background = active ? '#effdf7' : '';
        });
        if (type !== 'text' && type !== 'none') {
            els.header.value = '';
        }
        updateAll();
    }

    function syncHeaderTypeFromText() {
        if (els.header.value.trim()) {
            setHeaderType('text');
        } else if (els.headerType.value === 'text') {
            setHeaderType('none');
        }
    }

    function renderExamples() {
        const found = variables();
        const existing = exampleInputs().map(input => input.value);
        if (!found.length) {
            els.examplesCard.style.display = 'none';
            els.examples.innerHTML = '';
            return;
        }
        els.examplesCard.style.display = '';
        els.examples.innerHTML = found.map((variable, index) => {
            const value = existing[index] ?? initialExamples[index] ?? '';
            return `<div class="tm-field">
                <label style="color:#10b981;text-transform:uppercase;font-size:13px;letter-spacing:.08em;">Variable ${variable}</label>
                <input class="tm-input variable-example-input" name="body_examples[]" value="${escapeHtml(value)}" placeholder="Example value for ${variableToken(variable)}">
            </div>`;
        }).join('');
        exampleInputs().forEach(input => input.addEventListener('input', updatePreview));
    }

    function previewBodyText() {
        let body = text(els.body.value) || 'Your message body will appear here.';
        exampleInputs().forEach((input, index) => {
            const variable = variables()[index];
            body = body.replace(new RegExp('\\{\\{\\s*' + variable + '\\s*\\}\\}', 'g'), input.value || variableToken(variable));
        });
        return body;
    }

    function buttonLabels() {
        const mode = els.buttonMode.value;
        const labels = [];
        if (['quick', 'all'].includes(mode)) {
            document.querySelectorAll('.quick-reply-input').forEach(input => {
                if (input.value.trim() && labels.length < 3) labels.push({icon: 'fa-reply', text: input.value.trim()});
            });
        }
        if (['cta', 'all'].includes(mode)) {
            const phone = document.getElementById('phoneButtonText').value.trim();
            const url = document.getElementById('urlButtonText').value.trim();
            if (phone && labels.length < 3) labels.push({icon: 'fa-phone', text: phone});
            if (url && labels.length < 3) labels.push({icon: 'fa-arrow-up-right-from-square', text: url});
        }
        return labels;
    }

    function updatePreview() {
        const headerType = els.headerType.value;
        const header = els.header.value.trim();
        if (headerType === 'text' && header) {
            els.previewHeader.textContent = header;
            els.previewHeader.style.display = '';
        } else if (['image', 'video', 'document', 'location'].includes(headerType)) {
            els.previewHeader.innerHTML = `<i class="fas fa-${headerType === 'image' ? 'image' : headerType === 'video' ? 'video' : headerType === 'document' ? 'file-lines' : 'location-dot'} mr-2"></i>${headerType.charAt(0).toUpperCase() + headerType.slice(1)} header`;
            els.previewHeader.style.display = '';
        } else {
            els.previewHeader.style.display = 'none';
        }

        els.previewBody.textContent = previewBodyText();
        const footer = els.footer.value.trim();
        els.previewFooter.textContent = footer;
        els.previewFooter.style.display = footer ? '' : 'none';

        const labels = buttonLabels();
        els.previewButtons.style.display = labels.length ? '' : 'none';
        els.previewButtons.innerHTML = labels.map(label => `<div class="tm-preview-button"><i class="fas ${label.icon}"></i>${escapeHtml(label.text)}</div>`).join('');
    }

    function updateCounts() {
        els.headerCount.textContent = els.header.value.length;
        els.footerCount.textContent = els.footer.value.length;
    }

    function setButtonMode(mode) {
        els.buttonMode.value = mode;
        document.querySelectorAll('[data-button-mode]').forEach(button => {
            const active = button.dataset.buttonMode === mode;
            button.classList.toggle('primary', active);
        });
        els.ctaFields.style.display = ['cta', 'all'].includes(mode) ? 'grid' : 'none';
        els.quickFields.style.display = ['quick', 'all'].includes(mode) ? 'grid' : 'none';
        updatePreview();
    }

    function addVariable() {
        const next = (variables().at(-1) || 0) + 1;
        const token = variableToken(next);
        const start = els.body.selectionStart ?? els.body.value.length;
        const end = els.body.selectionEnd ?? start;
        els.body.value = els.body.value.slice(0, start) + token + els.body.value.slice(end);
        els.body.focus();
        els.body.selectionStart = els.body.selectionEnd = start + token.length;
        updateAll();
    }

    function updateAll() {
        updateCounts();
        renderExamples();
        updatePreview();
    }

    document.querySelectorAll('[data-header-type]').forEach(button => button.addEventListener('click', () => setHeaderType(button.dataset.headerType)));
    document.querySelectorAll('[data-button-mode]').forEach(button => button.addEventListener('click', () => setButtonMode(button.dataset.buttonMode)));
    document.getElementById('addVariableBtn').addEventListener('click', addVariable);
    [els.body, els.footer].forEach(input => input.addEventListener('input', updateAll));
    els.header.addEventListener('input', () => { syncHeaderTypeFromText(); updateAll(); });
    document.querySelectorAll('.quick-reply-input, #phoneButtonText, #phoneButtonNumber, #urlButtonText, #urlButtonUrl').forEach(input => input.addEventListener('input', updatePreview));

    setHeaderType(els.headerType.value || (els.header.value.trim() ? 'text' : 'none'));
    setButtonMode(els.buttonMode.value || 'none');
    updateAll();
})();
</script>
@endpush
