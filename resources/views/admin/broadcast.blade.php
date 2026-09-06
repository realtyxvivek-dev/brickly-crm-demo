@extends('layouts.app')

@section('title', 'Announcements')
@section('page-title', 'Announcement Center')

@section('content')
<div class="space-y-5">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Announcements</h2>
                <p class="text-sm text-slate-500 mt-1">Notice likho, audience choose karo, preview dekho, phir send karo.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-semibold">
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700">Normal</span>
                <span class="rounded-full bg-amber-50 px-3 py-1.5 text-amber-800">Important</span>
                <span class="rounded-full bg-rose-50 px-3 py-1.5 text-rose-800">Urgent</span>
            </div>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <form id="announcementForm" class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 space-y-5">
            @csrf

            <section class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Create Notice</h3>
                        <p class="text-sm text-slate-500">Required fields only: title, message, priority, audience.</p>
                    </div>
                    <button type="button" id="previewAnnouncement" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-semibold hover:bg-slate-50 transition">
                        <i class="fas fa-eye mr-2"></i>Preview
                    </button>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Title</label>
                    <input type="text" name="title" id="announcementTitle" required class="w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:ring-emerald-600" placeholder="Example: Tomorrow office reporting at 11 AM">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Message</label>
                    <textarea name="message" id="announcementMessage" rows="5" required class="w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:ring-emerald-600" placeholder="Announcement text..."></textarea>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Priority</label>
                    <select name="priority" id="announcementPriority" class="w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:ring-emerald-600">
                        <option value="normal">Normal - notification only</option>
                        <option value="important">Important - popup once</option>
                        <option value="urgent">Urgent - must acknowledge</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Audience</label>
                    <select name="target_type" id="targetType" class="w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:ring-emerald-600">
                        <option value="all_users">All Users</option>
                        <option value="role_based">Specific Roles</option>
                        <option value="specific_users">Specific Users</option>
                    </select>
                </div>

                <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 cursor-pointer">
                    <input type="checkbox" id="bannerEnabled" name="banner_enabled" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <span>
                        <span class="block text-sm font-semibold text-slate-800">Top banner</span>
                        <span class="block text-xs text-slate-500">Page top par show hoga</span>
                    </span>
                </label>
            </section>

            <section class="hidden" id="roleTargetWrap">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Roles</label>
                <div id="roleTargetList" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"></div>
            </section>

            <section class="hidden" id="userTargetWrap">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Specific Users</label>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <input type="text" id="userSearch" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 focus:border-emerald-600 focus:ring-emerald-600" placeholder="Search user by name or email">
                    <div id="userTargetList" class="mt-3 max-h-56 overflow-auto grid gap-2"></div>
                </div>
            </section>

            <details class="rounded-lg border border-slate-200 bg-slate-50">
                <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-slate-800">Optional settings</summary>
                <div class="grid gap-4 border-t border-slate-200 p-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Action Button Label</label>
                        <input type="text" name="action_label" id="actionLabel" class="w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:ring-emerald-600" placeholder="Example: Open Policy">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Action URL</label>
                        <input type="text" name="action_url" id="actionUrl" inputmode="url" class="w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:ring-emerald-600" placeholder="/knowledge-base or https://...">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Start Time</label>
                        <input type="datetime-local" name="starts_at" id="startsAt" class="w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:ring-emerald-600">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">End Time</label>
                        <input type="datetime-local" name="ends_at" id="endsAt" class="w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-emerald-600 focus:ring-emerald-600">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Attachment</label>
                        <input type="file" name="attachment" id="attachment" class="w-full rounded-lg border border-dashed border-slate-300 px-4 py-3 bg-white focus:border-emerald-600 focus:ring-emerald-600">
                        <p class="text-xs text-slate-500 mt-2">PDF, image, DOC/DOCX, XLS/XLSX. Max 10 MB.</p>
                    </div>
                </div>
            </details>

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5">
                <button type="submit" id="submitAnnouncement" class="px-6 py-3 rounded-lg text-white font-semibold bg-emerald-700 hover:bg-emerald-800 transition">
                    <i class="fas fa-paper-plane mr-2"></i>Send Announcement
                </button>
                <span id="announcementStatus" class="text-sm text-slate-500"></span>
            </div>
        </form>

        <aside class="space-y-5">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-base font-bold text-slate-900">Quick Templates</h3>
                <p class="text-sm text-slate-500 mt-1">Template choose karke form quickly fill karo.</p>
                <div id="templateGrid" class="mt-4 grid gap-2"></div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-base font-bold text-slate-900">Latest Result</h3>
                <div id="analyticsEmpty" class="mt-3 text-sm text-slate-500">Recent announcement send/load hote hi summary yahan dikhegi.</div>
                <div id="analyticsCards" class="hidden mt-4 grid grid-cols-2 gap-2"></div>
            </div>
        </aside>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Recent Announcements</h2>
                <p class="text-sm text-slate-500 mt-1">Delivery, read, acknowledge aur CTA clicks track karo.</p>
            </div>
            <button type="button" id="refreshAnnouncements" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-semibold hover:bg-slate-50 transition">
                <i class="fas fa-rotate-right mr-2"></i>Refresh
            </button>
        </div>
        <div id="announcementHistory" class="space-y-3"></div>
    </div>
</div>

<div id="announcementPreviewModal" class="fixed inset-0 bg-slate-950/50 hidden z-[2000] p-4">
    <div class="flex items-center justify-center min-h-full">
        <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Announcement Preview</h3>
                    <p class="text-sm text-slate-500">Popup preview for active users</p>
                </div>
                <button type="button" id="closePreviewModal" class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <div class="p-6">
                <div id="previewCard" class="rounded-3xl border border-slate-200 p-6 bg-slate-50"></div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('announcementForm');
    const roleWrap = document.getElementById('roleTargetWrap');
    const userWrap = document.getElementById('userTargetWrap');
    const targetType = document.getElementById('targetType');
    const roleList = document.getElementById('roleTargetList');
    const userList = document.getElementById('userTargetList');
    const userSearch = document.getElementById('userSearch');
    const historyRoot = document.getElementById('announcementHistory');
    const analyticsRoot = document.getElementById('analyticsCards');
    const analyticsEmpty = document.getElementById('analyticsEmpty');
    const statusNode = document.getElementById('announcementStatus');
    const previewModal = document.getElementById('announcementPreviewModal');
    const previewCard = document.getElementById('previewCard');
    const submitButton = document.getElementById('submitAnnouncement');
    const templateGrid = document.getElementById('templateGrid');
    const token = document.querySelector('meta[name="api-token"]')?.getAttribute('content') || '';

    const templates = [
        {
            id: 'general-update',
            name: 'General Update',
            description: 'Normal info update for all users.',
            priority: 'normal',
            target_type: 'all_users',
            banner_enabled: true,
            title: 'Daily Work Update',
            message: 'Team, aaj ke liye process aur reporting normal schedule par chalegi. Please assigned tasks aur updates time par complete karein.',
            action_label: '',
            action_url: '',
        },
        {
            id: 'hr-policy',
            name: 'HR Policy',
            description: 'Important acknowledgement template.',
            priority: 'important',
            target_type: 'all_users',
            banner_enabled: true,
            title: 'Updated HR Policy Notice',
            message: 'Please updated HR policy ko dhyan se padhein. Yeh update immediate effect se applicable hai. Sab users ko acknowledge karna required hai.',
            action_label: 'Open Policy',
            action_url: '/knowledge-base',
        },
        {
            id: 'urgent-alert',
            name: 'Urgent Alert',
            description: 'Sticky urgent popup for everyone.',
            priority: 'urgent',
            target_type: 'all_users',
            banner_enabled: true,
            title: 'Urgent Office Announcement',
            message: 'This is an urgent announcement. Please immediately latest instruction read karke acknowledge karein. Jab tak acknowledge nahi hota, reminder active rahega.',
            action_label: 'Review Now',
            action_url: '/dashboard',
        },
        {
            id: 'crm-target',
            name: 'CRM Target Update',
            description: 'Important template for CRM and sales roles.',
            priority: 'important',
            target_type: 'role_based',
            target_roles: ['crm', 'sales_manager', 'senior_manager', 'assistant_sales_manager', 'sales_executive'],
            banner_enabled: false,
            title: 'Target Update for Sales Team',
            message: 'Sales and CRM team, aaj ke updated targets aur priority follow-ups dashboard par available hain. Please current assignments review karke execute karein.',
            action_label: 'Open Dashboard',
            action_url: '/admin/dashboard',
        },
        {
            id: 'meeting-reminder',
            name: 'Meeting Reminder',
            description: 'Normal reminder for scheduled discussion.',
            priority: 'normal',
            target_type: 'role_based',
            target_roles: ['admin', 'crm', 'sales_manager'],
            banner_enabled: false,
            title: 'Internal Review Meeting Reminder',
            message: 'Reminder: aaj scheduled internal review meeting time par join karein. Discussion agenda aur pending action items saath lekar aayein.',
            action_label: 'Open Calendar',
            action_url: '/dashboard',
        },
        {
            id: 'holiday-notice',
            name: 'Holiday Notice',
            description: 'Banner-style holiday announcement.',
            priority: 'important',
            target_type: 'all_users',
            banner_enabled: true,
            title: 'Office Holiday Announcement',
            message: 'Please note: upcoming holiday ke liye office closed rahega. Regular work next working day se resume hoga. Team apne open tasks accordingly close kare.',
            action_label: '',
            action_url: '',
        },
    ];

    const state = {
        roles: [],
        users: [],
        announcements: [],
    };

    function headers(extra = {}) {
        return {
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`,
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            ...extra,
        };
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: headers(options.headers || {}),
        });

        const payload = await response.json();
        if (!response.ok || payload.success === false) {
            throw new Error(payload.message || `Request failed (${response.status})`);
        }

        return payload;
    }

    function renderRoles() {
        roleList.innerHTML = state.roles.map((role) => `
            <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 cursor-pointer hover:border-emerald-300 hover:bg-emerald-50 transition">
                <input type="checkbox" name="target_roles[]" value="${role.slug}" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span class="text-sm font-medium text-slate-700">${role.name}</span>
            </label>
        `).join('');
    }

    function renderTemplates() {
        templateGrid.innerHTML = templates.map((template) => `
            <button type="button" class="template-apply-btn w-full rounded-lg border border-slate-200 bg-slate-50 p-3 text-left hover:border-emerald-300 hover:bg-emerald-50 transition" data-template-id="${template.id}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-bold text-slate-900">${escapeHtml(template.name)}</div>
                        <div class="text-xs text-slate-500 mt-1">${escapeHtml(template.description)}</div>
                    </div>
                    <span class="shrink-0 px-2 py-1 rounded-full text-[10px] font-bold uppercase ${priorityTone(template.priority)}">${template.priority}</span>
                </div>
                <div class="mt-2 text-[11px] font-semibold text-slate-500">
                    ${template.target_type === 'all_users' ? 'All users' : escapeHtml((template.target_roles || []).join(', '))}
                </div>
            </button>
        `).join('');
    }

    function renderUsers() {
        const search = userSearch.value.trim().toLowerCase();
        const users = state.users.filter((user) => {
            if (!search) {
                return true;
            }

            return `${user.name} ${user.email} ${user.role?.name || ''}`.toLowerCase().includes(search);
        });

        userList.innerHTML = users.map((user) => `
            <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 cursor-pointer hover:border-emerald-300 hover:bg-emerald-50 transition">
                <input type="checkbox" name="target_user_ids[]" value="${user.id}" class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span>
                    <span class="block text-sm font-semibold text-slate-800">${user.name}</span>
                    <span class="block text-xs text-slate-500">${user.email} • ${user.role?.name || 'No role'}</span>
                </span>
            </label>
        `).join('');
    }

    function syncTargetVisibility() {
        const mode = targetType.value;
        roleWrap.classList.toggle('hidden', mode !== 'role_based');
        userWrap.classList.toggle('hidden', mode !== 'specific_users');
    }

    function setCheckedValues(selector, values) {
        const wanted = new Set(values || []);
        document.querySelectorAll(selector).forEach((node) => {
            node.checked = wanted.has(node.value);
        });
    }

    function applyTemplate(templateId) {
        const template = templates.find((item) => item.id === templateId);
        if (!template) {
            return;
        }

        document.getElementById('announcementTitle').value = template.title || '';
        document.getElementById('announcementMessage').value = template.message || '';
        document.getElementById('announcementPriority').value = template.priority || 'normal';
        document.getElementById('targetType').value = template.target_type || 'all_users';
        document.getElementById('bannerEnabled').checked = Boolean(template.banner_enabled);
        document.getElementById('actionLabel').value = template.action_label || '';
        document.getElementById('actionUrl').value = template.action_url || '';
        document.getElementById('startsAt').value = '';
        document.getElementById('endsAt').value = '';
        document.getElementById('attachment').value = '';

        syncTargetVisibility();
        setCheckedValues('input[name="target_roles[]"]', template.target_roles || []);
        setCheckedValues('input[name="target_user_ids[]"]', template.target_user_ids || []);
        statusNode.textContent = `${template.name} template applied.`;
    }

    function normalizeActionUrl(rawValue) {
        const value = String(rawValue || '').trim();
        if (!value) {
            return '';
        }

        if (/^(https?:)?\/\//i.test(value) || value.startsWith('/') || value.startsWith('#')) {
            return value;
        }

        if (/^[a-z0-9.-]+\.[a-z]{2,}(\/.*)?$/i.test(value)) {
            return `https://${value}`;
        }

        return value;
    }

    function priorityTone(priority) {
        if (priority === 'urgent') {
            return 'border-rose-200 bg-rose-50 text-rose-900';
        }
        if (priority === 'important') {
            return 'border-amber-200 bg-amber-50 text-amber-900';
        }
        return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    }

    function formatDateTime(value) {
        if (!value) {
            return 'Immediate';
        }

        return new Date(value).toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function buildSelectedAudienceLabel(item) {
        if (item.target_type === 'all_users') {
            return 'All users';
        }
        if (item.target_type === 'specific_users') {
            return `${(item.target_user_ids || []).length} specific users`;
        }
        return (item.target_roles || []).join(', ') || 'Role based';
    }

    function renderHistory() {
        if (!state.announcements.length) {
            historyRoot.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">No announcements yet.</div>';
            analyticsRoot.classList.add('hidden');
            analyticsEmpty.classList.remove('hidden');
            return;
        }

        const latest = state.announcements[0];
        const cards = [
            { label: 'Recipients', value: latest.analytics.total_recipients },
            { label: 'Delivered', value: latest.analytics.delivered },
            { label: 'Read', value: latest.analytics.read },
            { label: 'Acknowledged', value: latest.analytics.acknowledged },
            { label: 'Pending Ack', value: latest.analytics.pending_acknowledge },
            { label: 'CTA Clicks', value: latest.analytics.clicked_cta },
        ];

        analyticsEmpty.classList.add('hidden');
        analyticsRoot.classList.remove('hidden');
        analyticsRoot.innerHTML = cards.map((card) => `
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
                <div class="text-[11px] uppercase text-slate-500 font-semibold">${card.label}</div>
                <div class="mt-1 text-xl font-bold text-slate-900">${card.value}</div>
            </div>
        `).join('');

        historyRoot.innerHTML = state.announcements.map((item) => `
            <div class="rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <h3 class="text-base font-bold text-slate-900">${escapeHtml(item.title)}</h3>
                            <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase ${priorityTone(item.priority)}">${item.priority}</span>
                            <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-slate-100 text-slate-700">${item.status}</span>
                        </div>
                        <p class="text-sm text-slate-600 whitespace-pre-wrap">${escapeHtml(item.message)}</p>
                    </div>
                    <div class="text-xs text-slate-500 text-right">
                        <div>${formatDateTime(item.created_at)}</div>
                        <div class="mt-1">Audience: ${escapeHtml(buildSelectedAudienceLabel(item))}</div>
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-3 xl:grid-cols-6 mt-4">
                    <div class="rounded-lg bg-slate-50 p-3"><div class="text-[11px] text-slate-500 uppercase">Recipients</div><div class="text-lg font-bold text-slate-900">${item.analytics.total_recipients}</div></div>
                    <div class="rounded-lg bg-slate-50 p-3"><div class="text-[11px] text-slate-500 uppercase">Delivered</div><div class="text-lg font-bold text-slate-900">${item.analytics.delivered}</div></div>
                    <div class="rounded-lg bg-slate-50 p-3"><div class="text-[11px] text-slate-500 uppercase">Read</div><div class="text-lg font-bold text-slate-900">${item.analytics.read}</div></div>
                    <div class="rounded-lg bg-slate-50 p-3"><div class="text-[11px] text-slate-500 uppercase">Ack</div><div class="text-lg font-bold text-slate-900">${item.analytics.acknowledged}</div></div>
                    <div class="rounded-lg bg-slate-50 p-3"><div class="text-[11px] text-slate-500 uppercase">Pending</div><div class="text-lg font-bold text-slate-900">${item.analytics.pending_acknowledge}</div></div>
                    <div class="rounded-lg bg-slate-50 p-3"><div class="text-[11px] text-slate-500 uppercase">CTA</div><div class="text-lg font-bold text-slate-900">${item.analytics.clicked_cta}</div></div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                    <span>Banner: ${item.banner_enabled ? 'Yes' : 'No'}</span>
                    <span>Acknowledge: ${item.requires_acknowledge ? 'Required' : 'Read only'}</span>
                    <span>Start: ${formatDateTime(item.starts_at)}</span>
                    <span>End: ${item.ends_at ? formatDateTime(item.ends_at) : 'No expiry'}</span>
                    ${item.attachment_url ? `<a class="text-emerald-700 font-semibold" href="${item.attachment_url}" target="_blank" rel="noopener">Attachment: ${escapeHtml(item.attachment_name || 'Download')}</a>` : ''}
                    ${item.action_url ? `<a class="text-emerald-700 font-semibold" href="${item.action_url}" target="_blank" rel="noopener">CTA: ${escapeHtml(item.action_label || 'Open')}</a>` : ''}
                </div>
            </div>
        `).join('');
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function previewAnnouncement() {
        const priority = document.getElementById('announcementPriority').value;
        const title = document.getElementById('announcementTitle').value || 'Announcement title';
        const message = document.getElementById('announcementMessage').value || 'Announcement message preview.';
        const actionLabel = document.getElementById('actionLabel').value;

        previewCard.className = `rounded-3xl border p-6 ${priorityTone(priority)}`;
        previewCard.innerHTML = `
            <div class="flex items-center gap-2 mb-3">
                <span class="px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-[0.14em] bg-white/70">${priority}</span>
                <span class="text-xs font-semibold">${priority === 'normal' ? 'Notification only' : (priority === 'important' ? 'Popup once' : 'Must acknowledge')}</span>
            </div>
            <h4 class="text-2xl font-bold mb-3">${escapeHtml(title)}</h4>
            <p class="text-sm leading-7 whitespace-pre-wrap">${escapeHtml(message)}</p>
            <div class="mt-5 flex flex-wrap gap-3">
                ${actionLabel ? `<span class="px-4 py-2 rounded-full bg-white text-slate-800 font-semibold">${escapeHtml(actionLabel)}</span>` : ''}
                <span class="px-4 py-2 rounded-full border border-current/20 font-semibold">${priority === 'urgent' ? 'Acknowledge' : 'View Announcement'}</span>
            </div>
        `;

        previewModal.classList.remove('hidden');
    }

    async function loadAudienceOptions() {
        const payload = await request('/api/telecaller/broadcast/audience-options');
        state.roles = payload.roles || [];
        state.users = payload.users || [];
        renderRoles();
        renderUsers();
    }

    async function loadAnnouncements() {
        const payload = await request('/api/telecaller/broadcast/manage');
        state.announcements = payload.data || [];
        renderHistory();
    }

    async function submitAnnouncement(event) {
        event.preventDefault();
        submitButton.disabled = true;
        statusNode.textContent = 'Sending...';

        try {
            const data = new FormData(form);
            const normalizedActionUrl = normalizeActionUrl(document.getElementById('actionUrl').value);
            if (normalizedActionUrl) {
                data.set('action_url', normalizedActionUrl);
                document.getElementById('actionUrl').value = normalizedActionUrl;
            }
            if (targetType.value !== 'role_based') {
                data.delete('target_roles[]');
            }
            if (targetType.value !== 'specific_users') {
                data.delete('target_user_ids[]');
            }

            const response = await fetch('/api/telecaller/broadcast/send', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: data,
            });

            const payload = await response.json();
            if (!response.ok || payload.success === false) {
                throw new Error(payload.message || 'Failed to send announcement');
            }

            statusNode.textContent = `Announcement sent to ${payload.data.sent_to} users.`;
            form.reset();
            syncTargetVisibility();
            await loadAnnouncements();
        } catch (error) {
            statusNode.textContent = error.message;
        } finally {
            submitButton.disabled = false;
        }
    }

    targetType.addEventListener('change', syncTargetVisibility);
    userSearch.addEventListener('input', renderUsers);
    form.addEventListener('submit', submitAnnouncement);
    templateGrid.addEventListener('click', async (event) => {
        const applyButton = event.target.closest('.template-apply-btn');
        if (applyButton) {
            applyTemplate(applyButton.dataset.templateId);
            return;
        }
    });
    document.getElementById('previewAnnouncement').addEventListener('click', previewAnnouncement);
    document.getElementById('closePreviewModal').addEventListener('click', () => previewModal.classList.add('hidden'));
    previewModal.addEventListener('click', (event) => {
        if (event.target === previewModal) {
            previewModal.classList.add('hidden');
        }
    });
    document.getElementById('refreshAnnouncements').addEventListener('click', loadAnnouncements);

    (async () => {
        syncTargetVisibility();
        renderTemplates();
        await loadAudienceOptions();
        await loadAnnouncements();
    })();
})();
</script>
@endsection
