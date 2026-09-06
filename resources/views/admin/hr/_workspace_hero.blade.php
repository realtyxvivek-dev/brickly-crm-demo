@php
    $eyebrow = $eyebrow ?? 'Employee / HR Setup';
    $title = $title ?? '';
    $subtitle = $subtitle ?? null;
    $stats = $stats ?? [];
    $action = $action ?? null;
@endphp

@once
<style>
    .hr-setup-page {
        --hr-green: #0f5c3f;
        --hr-green-dark: #0b3f2e;
        --hr-muted: #62736a;
        --hr-line: #dce5df;
        --hr-soft: #f5f8f5;
        display: flex;
        flex-direction: column;
        gap: 18px;
        width: 100%;
        max-width: none;
        color: #10231a;
    }

    .hr-setup-hero {
        background:
            radial-gradient(circle at top right, rgba(46, 125, 50, 0.10), transparent 30%),
            linear-gradient(180deg, #ffffff 0%, #fbfdfb 100%);
        border: 1px solid var(--hr-line);
        border-radius: 22px;
        box-shadow: 0 12px 28px rgba(17, 36, 24, 0.06);
        overflow: hidden;
    }

    .hr-setup-hero-inner {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 24px;
        padding: 28px 30px 24px;
        flex-wrap: wrap;
    }

    .hr-setup-eyebrow {
        margin-bottom: 10px;
        color: var(--hr-green);
        font-size: 10.5px;
        font-weight: 900;
        letter-spacing: .16em;
        text-transform: uppercase;
    }

    .hr-setup-title {
        margin: 0;
        color: var(--hr-green-dark);
        font-family: 'Poppins', 'Inter', sans-serif;
        font-size: 38px;
        font-weight: 900;
        letter-spacing: -.075em;
        line-height: 1;
    }

    .hr-setup-subtitle {
        max-width: 780px;
        margin: 12px 0 0;
        color: var(--hr-muted);
        font-size: 14px;
        font-weight: 600;
        line-height: 1.7;
    }

    .hr-setup-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .hr-setup-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        padding: 10px 18px;
        border-radius: 10px;
        background: var(--hr-green);
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        box-shadow: 0 12px 24px rgba(15, 92, 63, 0.18);
    }

    .hr-setup-primary:hover { color: #fff; background: #0b4f36; }

    .hr-setup-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        padding: 0 30px 28px;
    }

    .hr-setup-stat {
        min-height: 92px;
        padding: 16px 18px;
        border: 1px solid var(--hr-line);
        border-radius: 16px;
        background: rgba(255,255,255,.86);
        box-shadow: 0 8px 18px rgba(17, 36, 24, 0.035);
    }

    .hr-setup-stat-label {
        color: var(--hr-muted);
        font-size: 10.5px;
        font-weight: 900;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    .hr-setup-stat-value {
        display: block;
        margin-top: 9px;
        color: #071426;
        font-size: 30px;
        font-weight: 900;
        letter-spacing: -.04em;
        line-height: 1;
    }

    .hr-setup-stat-note {
        margin-top: 7px;
        color: var(--hr-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .hr-setup-card {
        background: #fff;
        border: 1px solid var(--hr-line);
        border-radius: 22px;
        box-shadow: 0 12px 28px rgba(17, 36, 24, 0.05);
    }

    .hr-setup-page > .bg-white.rounded-xl,
    .hr-setup-page > form.bg-white.rounded-xl,
    .hr-setup-page > .grid .bg-white.rounded-xl {
        border-color: var(--hr-line) !important;
        border-radius: 20px !important;
        box-shadow: 0 10px 24px rgba(17, 36, 24, 0.045) !important;
    }

    .hr-setup-page > .grid .bg-white.rounded-xl {
        padding: 18px !important;
    }

    .hr-setup-help {
        background: #fbfdfb;
        border: 1px solid var(--hr-line);
        border-radius: 20px;
        padding: 16px;
    }

    .hr-setup-section-title {
        margin: 0 0 14px;
        color: var(--hr-green-dark);
        font-size: 18px;
        font-weight: 900;
        letter-spacing: -.02em;
    }

    .hr-setup-section-copy {
        margin: -8px 0 16px;
        color: var(--hr-muted);
        font-size: 13px;
        font-weight: 600;
        line-height: 1.55;
    }

    .hr-setup-help h2 {
        margin: 0 0 12px;
        color: var(--hr-green-dark);
        font-size: 14px;
        font-weight: 900;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .hr-setup-help .grid > div {
        border-color: var(--hr-line) !important;
        border-radius: 16px !important;
        padding: 14px !important;
        background: #fff !important;
    }

    .hr-setup-help p {
        margin: 0;
        color: var(--hr-muted);
        font-size: 12.5px;
        font-weight: 600;
        line-height: 1.55;
    }

    .hr-setup-help p + p { margin-top: 4px; }

    .hr-setup-page input[type="text"],
    .hr-setup-page input[type="number"],
    .hr-setup-page input[type="month"],
    .hr-setup-page input[type="date"],
    .hr-setup-page input[type="time"],
    .hr-setup-page input[type="file"],
    .hr-setup-page select,
    .hr-setup-page textarea {
        min-height: 42px;
        border-color: var(--hr-line) !important;
        border-radius: 12px !important;
        background: #fff;
        color: #10231a;
        font-size: 14px;
        font-weight: 600;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    .hr-setup-page input:focus,
    .hr-setup-page select:focus,
    .hr-setup-page textarea:focus {
        outline: none;
        border-color: rgba(15, 92, 63, .45) !important;
        box-shadow: 0 0 0 4px rgba(15, 92, 63, .08);
    }

    .hr-setup-page label {
        color: #253b31;
        font-weight: 700;
    }

    .hr-setup-page button[type="submit"],
    .hr-setup-page button[id$="Btn"] {
        min-height: 42px;
        border-radius: 12px !important;
        font-weight: 850 !important;
    }

    .hr-setup-page button[type="submit"] {
        background: var(--hr-green) !important;
        color: #fff !important;
        border: 1px solid var(--hr-green) !important;
        box-shadow: 0 10px 20px rgba(15, 92, 63, .14);
    }

    .hr-setup-page a[href],
    .hr-setup-page button {
        transition: transform .15s ease, box-shadow .15s ease, background .15s ease, border-color .15s ease;
    }

    .hr-setup-page a[href]:hover,
    .hr-setup-page button:hover {
        transform: translateY(-1px);
    }

    .hr-setup-page table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .hr-setup-page thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #f6faf7 !important;
        color: #52685e !important;
        font-size: 10.5px !important;
        font-weight: 900 !important;
        letter-spacing: .13em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .hr-setup-page th,
    .hr-setup-page td {
        padding: 13px 16px !important;
        border-bottom: 1px solid rgba(220, 229, 223, .9) !important;
        vertical-align: middle;
    }

    .hr-setup-page tbody tr:hover {
        background: #fbfdfb;
    }

    .hr-setup-page tbody tr:last-child td {
        border-bottom: 0 !important;
    }

    .hr-setup-page .overflow-x-auto {
        scrollbar-width: thin;
        scrollbar-color: rgba(15, 92, 63, .35) rgba(220, 229, 223, .45);
    }

    .hr-setup-page .overflow-x-auto::-webkit-scrollbar {
        height: 8px;
    }

    .hr-setup-page .overflow-x-auto::-webkit-scrollbar-thumb {
        background: rgba(15, 92, 63, .35);
        border-radius: 999px;
    }

    .hr-setup-page .overflow-x-auto::-webkit-scrollbar-track {
        background: rgba(220, 229, 223, .45);
        border-radius: 999px;
    }

    @media (max-width: 768px) {
        .hr-setup-hero-inner { padding: 20px; }
        .hr-setup-title { font-size: 30px; letter-spacing: -.055em; }
        .hr-setup-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 0 20px 20px; }
        .hr-setup-stat { min-height: 82px; padding: 14px; }
        .hr-setup-stat-value { font-size: 24px; }
        .hr-setup-card { border-radius: 18px; }
        .hr-setup-card.p-6,
        .hr-setup-card .p-6 { padding: 16px !important; }
        .hr-setup-help { padding: 14px; }
        .hr-setup-help .grid { gap: 10px !important; }
        .hr-setup-page th,
        .hr-setup-page td { padding: 11px 12px !important; }
    }
</style>
@endonce

<section class="hr-setup-hero">
    <div class="hr-setup-hero-inner">
        <div>
            <div class="hr-setup-eyebrow">{{ $eyebrow }}</div>
            <h1 class="hr-setup-title">{{ $title }}</h1>
            @if($subtitle)
                <p class="hr-setup-subtitle">{{ $subtitle }}</p>
            @endif
        </div>

        @if(!empty($action['url']) && !empty($action['label']))
            <div class="hr-setup-actions">
                <a href="{{ $action['url'] }}" class="hr-setup-primary">
                    @if(!empty($action['icon']))<span>{{ $action['icon'] }}</span>@endif
                    {{ $action['label'] }}
                </a>
            </div>
        @endif
    </div>

    @if(!empty($stats))
        <div class="hr-setup-stats">
            @foreach($stats as $stat)
                <div class="hr-setup-stat">
                    <div class="hr-setup-stat-label">{{ $stat['label'] ?? '' }}</div>
                    <strong class="hr-setup-stat-value">{{ $stat['value'] ?? '0' }}</strong>
                    @if(!empty($stat['note']))
                        <div class="hr-setup-stat-note">{{ $stat['note'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>
