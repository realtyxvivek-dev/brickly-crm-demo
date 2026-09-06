<div id="imageModal" class="fixed inset-0 z-50 hidden bg-black/92 p-4">
    <button type="button" id="imageModalClose" class="absolute right-5 top-5 grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white"><i class="fas fa-xmark"></i></button>
    <div class="grid h-full place-items-center">
        <img id="imageModalImg" src="" alt="Gallery preview" class="max-h-full max-w-full rounded-2xl object-contain">
    </div>
</div>

<script>
(() => {
    const eventUrl = @json($eventUrl);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const sessionKey = @json($sessionKey ?? 'customer-project-session');
    const openedEvent = @json($openedEvent ?? 'page_view');
    const openedSection = @json($openedSection ?? 'page');
    const openedMeta = @json($openedMeta ?? []);
    const includeVisitId = @json((bool) ($includeVisitId ?? false));
    const sessionId = localStorage.getItem(sessionKey) || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    const visitId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    localStorage.setItem(sessionKey, sessionId);

    let activeProjectId = null;
    let activeSection = openedSection;
    let activeFloorPlan = null;
    let activeSince = Date.now();

    function baseMeta(meta = {}) {
        return Object.assign(includeVisitId ? { visit_id: visitId } : {}, meta || {});
    }

    function send(payload, beacon = false) {
        const body = JSON.stringify(Object.assign({ session_id: sessionId }, payload, {
            meta: baseMeta(payload.meta || {}),
        }));
        if (beacon && navigator.sendBeacon) {
            navigator.sendBeacon(eventUrl + '?_token=' + encodeURIComponent(csrf), new Blob([body], { type: 'application/json' }));
            return;
        }
        fetch(eventUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body,
            keepalive: true,
        }).catch(() => null);
    }

    function track(eventName, projectId = null, section = null, meta = {}) {
        send({ event_name: eventName, project_id: projectId || null, section: section || activeSection, meta });
    }

    function clientHints() {
        return {
            screen_width: window.screen?.width || null,
            screen_height: window.screen?.height || null,
            viewport_width: window.innerWidth || null,
            viewport_height: window.innerHeight || null,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || null,
            language: navigator.language || null,
            platform: navigator.platform || null,
            max_touch_points: navigator.maxTouchPoints || 0,
            device_memory: navigator.deviceMemory || null,
        };
    }

    function flushDuration(beacon = false) {
        const now = Date.now();
        const duration = Math.max(0, now - activeSince);
        if (duration > 1000) {
            const meta = activeFloorPlan ? { floor_plan: activeFloorPlan } : {};
            send({ event_name: 'duration', project_id: activeProjectId, section: activeSection, duration_ms: duration, meta }, beacon);
        }
        activeSince = now;
    }

    track(openedEvent, null, openedSection, Object.assign({}, openedMeta, { client_hints: clientHints() }));

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            flushDuration();
            activeProjectId = entry.target.dataset.trackProject || null;
            activeSection = entry.target.dataset.trackSection || 'section';
            if (activeSection !== 'unit_plans') activeFloorPlan = null;
            track(activeProjectId && activeSection === 'project_card' ? 'project_view' : 'section_view', activeProjectId, activeSection);
        });
    }, { threshold: 0.45 });
    document.querySelectorAll('[data-track-section]').forEach((node) => observer.observe(node));

    document.querySelectorAll('.js-customer-project-track').forEach((node) => {
        node.addEventListener('click', () => {
            let meta = {};
            try { meta = JSON.parse(node.dataset.meta || '{}'); } catch (error) {}
            const eventName = node.dataset.event || 'cta_click';
            flushDuration();
            if (eventName === 'floor_plan_view' || eventName === 'unit_type_view') {
                activeFloorPlan = meta;
                activeProjectId = node.dataset.projectId || activeProjectId;
                activeSection = node.dataset.section || activeSection;
            }
            track(eventName, node.dataset.projectId || null, node.dataset.section || activeSection, meta);
        });
    });

    document.querySelectorAll('.unit-tab').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.unitTarget;
            const project = button.closest('[data-track-project]');
            const projectId = project?.dataset.trackProject || null;
            const label = button.textContent.trim();
            flushDuration();
            activeProjectId = projectId || activeProjectId;
            activeSection = 'unit_plans';
            activeFloorPlan = { unit_type: label, label, project_id: projectId };
            track('unit_type_view', projectId, 'unit_plans', activeFloorPlan);
            project.querySelectorAll('.unit-tab').forEach((tab) => {
                const active = tab === button;
                tab.classList.toggle('bg-blue-600', active);
                tab.classList.toggle('text-white', active);
                tab.classList.toggle('border-blue-600', active);
                tab.classList.toggle('bg-white', !active);
                tab.classList.toggle('text-slate-700', !active);
                tab.classList.toggle('border-slate-200', !active);
            });
            project.querySelectorAll('.unit-panel').forEach((panel) => panel.classList.toggle('active', panel.dataset.unitPanel === target));
        });
    });

    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('imageModalImg');
    document.querySelectorAll('[data-image-url]').forEach((button) => {
        button.addEventListener('click', () => {
            const url = button.dataset.imageUrl;
            if (!url) return;
            modalImg.src = url;
            modal.classList.remove('hidden');
        });
    });
    document.getElementById('imageModalClose')?.addEventListener('click', () => modal.classList.add('hidden'));
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) modal.classList.add('hidden');
    });

    document.querySelectorAll('.proposal-share-location').forEach((button) => {
        button.addEventListener('click', () => {
            if (!navigator.geolocation) {
                track('location_denied', button.dataset.projectId || null, 'location_permission', { exact_location: { permission: 'unavailable' } });
                return;
            }
            navigator.geolocation.getCurrentPosition((position) => {
                track('location_shared', button.dataset.projectId || null, 'location_permission', {
                    exact_location: {
                        permission: 'granted',
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy_m: position.coords.accuracy,
                    },
                    client_hints: clientHints(),
                });
            }, () => {
                track('location_denied', button.dataset.projectId || null, 'location_permission', { exact_location: { permission: 'denied' } });
            }, {
                enableHighAccuracy: false,
                timeout: 8000,
                maximumAge: 300000,
            });
        });
    });

    setInterval(() => flushDuration(), 15000);
    document.addEventListener('visibilitychange', () => { if (document.hidden) flushDuration(true); });
    window.addEventListener('pagehide', () => flushDuration(true));
})();
</script>
