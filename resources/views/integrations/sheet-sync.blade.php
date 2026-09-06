@extends('layouts.app')
@section('title', 'Google Sheet Sync')

@push('styles')
<style>
.ss-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    max-width: 700px;
    margin: 0 auto;
}
@media(max-width: 600px) { .ss-grid { grid-template-columns: 1fr; } }

.ss-item { position: relative; cursor: pointer; }
.ss-item input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }

.ss-card {
    border: 2px solid #e9ecef;
    border-radius: 16px;
    padding: 26px 20px 22px;
    background: #fff;
    transition: all .2s;
    cursor: pointer;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.ss-card:hover {
    border-color: #adb5bd;
    box-shadow: 0 6px 20px rgba(0,0,0,.08);
    transform: translateY(-2px);
}
.ss-item input:checked + .ss-card {
    border-color: var(--c);
    background: var(--bg);
    box-shadow: 0 6px 20px rgba(0,0,0,.1);
    transform: translateY(-2px);
}
.ss-check {
    position: absolute;
    top: 12px; right: 12px;
    width: 22px; height: 22px;
    border-radius: 50%;
    background: var(--c);
    color: #fff;
    font-size: 10px;
    display: none;
    align-items: center;
    justify-content: center;
}
.ss-item input:checked ~ .ss-check { display: flex; }

.ss-ico {
    width: 52px; height: 52px;
    border-radius: 14px;
    background: var(--bg);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    margin-bottom: 14px;
    flex-shrink: 0;
}
.ss-title { font-size: 16px; font-weight: 700; color: #212529; margin: 0 0 6px; }
.ss-desc  { font-size: 12px; color: #6c757d; margin: 0 0 14px; line-height: 1.6; flex-grow: 1; }
.ss-tags  { display: flex; flex-wrap: wrap; gap: 5px; }
.ss-tag   { font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 20px; background: var(--bg); color: var(--c); border: 1px solid var(--c)30; }

.continue-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    max-width: 860px;
    margin: 28px auto 0;
    padding: 14px 24px;
    background: #212529;
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    opacity: .4;
    pointer-events: none;
}
.continue-btn.ready { opacity: 1; pointer-events: all; }
.continue-btn:hover { background: #343a40; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4 px-4">

    {{-- Header --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('integrations.sheet-integration') }}"
           class="btn btn-sm btn-outline-secondary rounded-circle p-0 d-flex align-items-center justify-content-center"
           style="width:36px;height:36px;flex-shrink:0;">
            <i class="fas fa-arrow-left" style="font-size:12px;"></i>
        </a>
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-table me-2 text-success"></i>Google Sheet Sync
            </h5>
            <div class="text-muted small">Kaise sync karna chahte ho? Ek option select karo</div>
        </div>
    </div>

    {{-- Type Selector --}}
    <div class="ss-grid">

        {{-- 1. Periodic Sync (Lead Import) --}}
        <label class="ss-item" style="--c:#0f9d58;--bg:#e8f5e9;">
            <input type="radio" name="sync_type" value="{{ route('lead-import.index') }}">
            <div class="ss-card">
                <div class="ss-ico"><i class="fas fa-sync-alt" style="color:#0f9d58;"></i></div>
                <p class="ss-title">Periodic Sync</p>
                <p class="ss-desc">
                    Google Sheet URL do — CRM automatically har kuch minutes mein naye rows uthata rehta hai. Ek baar setup, baki automatic.
                </p>
                <div class="ss-tags">
                    <span class="ss-tag">Sheet URL</span>
                    <span class="ss-tag">Auto-sync</span>
                    <span class="ss-tag">Column mapping</span>
                </div>
            </div>
            <div class="ss-check"><i class="fas fa-check"></i></div>
        </label>

        {{-- 2. Form Webhook (Form Integration) --}}
        <label class="ss-item" style="--c:#fd7e14;--bg:#fff3e0;">
            <input type="radio" name="sync_type" value="{{ route('integrations.form-integration.index') }}">
            <div class="ss-card">
                <div class="ss-ico"><i class="fab fa-wpforms" style="color:#fd7e14;"></i></div>
                <p class="ss-title">Form Webhook</p>
                <p class="ss-desc">
                    Google Form ya website form connect karo — form submit hote hi lead real-time CRM mein aa jaati hai. Script copy karo aur lagao.
                </p>
                <div class="ss-tags">
                    <span class="ss-tag">Real-time</span>
                    <span class="ss-tag">Google Form</span>
                    <span class="ss-tag">Website form</span>
                </div>
            </div>
            <div class="ss-check"><i class="fas fa-check"></i></div>
        </label>

    </div>

    {{-- Continue Button --}}
    <button id="continueBtn" class="continue-btn" onclick="goNext()">
        <i class="fas fa-arrow-right"></i>
        <span id="continueTxt">Ek option select karo</span>
    </button>

</div>
@endsection

@push('scripts')
<script>
let selectedUrl = null;

const labels = {
    '{{ route('lead-import.index') }}':                    'Periodic Sync kholna hai',
    '{{ route('integrations.form-integration.index') }}':  'Form Webhook setup karna hai',
};

document.querySelectorAll('input[name=sync_type]').forEach(r => {
    r.addEventListener('change', function () {
        selectedUrl = this.value;
        const btn = document.getElementById('continueBtn');
        btn.classList.add('ready');
        document.getElementById('continueTxt').textContent = labels[selectedUrl] + ' — Continue';
    });
});

function goNext() {
    if (selectedUrl) window.location.href = selectedUrl;
}
</script>
@endpush
