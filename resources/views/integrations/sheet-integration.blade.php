@extends('layouts.app')
@section('title', 'Sheet Integration')

@push('styles')
<style>
.si-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    max-width: 780px;
    margin: 0 auto;
}
@media(max-width: 640px) { .si-grid { grid-template-columns: 1fr; } }

.si-card {
    background: #fff;
    border: 2px solid #e9ecef;
    border-radius: 18px;
    padding: 32px 28px 28px;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: block;
    transition: all .2s;
    position: relative;
    overflow: hidden;
}
.si-card:hover {
    border-color: var(--c);
    box-shadow: 0 10px 30px rgba(0,0,0,.1);
    transform: translateY(-3px);
    color: inherit;
    text-decoration: none;
}
.si-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 5px;
    background: var(--c);
}
.si-icon {
    width: 60px; height: 60px;
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
    margin-bottom: 18px;
    background: var(--bg);
}
.si-title { font-size: 19px; font-weight: 700; color: #212529; margin: 0 0 8px; }
.si-desc  { font-size: 13px; color: #6c757d; margin: 0 0 18px; line-height: 1.65; }
.si-features { list-style: none; padding: 0; margin: 0 0 22px; }
.si-features li {
    font-size: 12px; color: #495057;
    padding: 4px 0;
    display: flex; align-items: center; gap: 8px;
}
.si-features li::before { content: '✓'; font-weight: 700; color: var(--c); flex-shrink: 0; }
.si-btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: 9px;
    font-size: 13px; font-weight: 600;
    background: var(--c); color: #fff; border: none;
    transition: opacity .15s;
}
.si-card:hover .si-btn { opacity: .88; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4 px-4">

    {{-- Header --}}
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('integrations.index') }}"
           class="btn btn-sm btn-outline-secondary rounded-circle p-0 d-flex align-items-center justify-content-center"
           style="width:36px;height:36px;flex-shrink:0;">
            <i class="fas fa-arrow-left" style="font-size:12px;"></i>
        </a>
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-table me-2 text-success"></i>Sheet Integration
            </h5>
            <div class="text-muted small">Apna use case chuno — aage ka process automatic khulega</div>
        </div>
    </div>

    <div class="si-grid">

        {{-- Google Sheet Sync (Lead Import + Smart Import + Form Integration merged) --}}
        <a href="{{ route('integrations.sheet-sync') }}" class="si-card"
           style="--c:#0f9d58;--bg:#e8f5e9;">
            <div class="si-icon">
                <i class="fab fa-google-drive" style="color:#0f9d58;"></i>
            </div>
            <p class="si-title">Google Sheet Sync</p>
            <p class="si-desc">
                Google Sheet ya form se leads CRM mein laao — periodic sync, bulk import ya real-time webhook, teeno support karta hai.
            </p>
            <ul class="si-features">
                <li>Periodic Sync — Sheet URL se auto-sync</li>
                <li>Form Webhook — Google Form / website form live</li>
                <li>Column mapping support</li>
                <li>Import history</li>
            </ul>
            <span class="si-btn">
                <i class="fas fa-arrow-right"></i> Select & Open
            </span>
        </a>

        {{-- Meta Sheet (Facebook + Google Sheet) --}}
        <a href="{{ route('integrations.meta-sheet.index') }}" class="si-card"
           style="--c:#1877f2;--bg:#e7f0fd;">
            <div class="si-icon">
                <i class="fab fa-facebook" style="color:#1877f2;"></i>
            </div>
            <p class="si-title">Meta Sheet</p>
            <p class="si-desc">
                Facebook/Meta Lead Ads ke responses Google Sheet mein collect karo, phir wahan se automatically CRM mein import karo.
            </p>
            <ul class="si-features">
                <li>Facebook Lead Ads connect karo</li>
                <li>Google Sheet as middle storage</li>
                <li>Auto-sync from sheet to CRM</li>
                <li>Column mapping support</li>
            </ul>
            <span class="si-btn">
                <i class="fas fa-arrow-right"></i> Open
            </span>
        </a>

    </div>

</div>
@endsection
