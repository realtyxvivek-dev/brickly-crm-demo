@extends(auth()->user()?->isFinanceManager() && !auth()->user()?->isAdmin() ? 'finance-manager.layout' : 'layouts.app')

@section('title', 'Data Intelligent - ' . brand_name())
@section('page-title', 'Data Intelligent')
@section('page-subtitle', '')
@section('page_title', 'Data Intelligent')
@section('page_subtitle', '')

@push('styles')
<style>
    .data-intel-shell {
        display: flex;
        flex-direction: column;
        gap: 18px;
        color: #17211d;
    }

    .data-intel-hero {
        border: 1px solid #e2e1dc;
        border-radius: 16px;
        background: #ffffff;
        padding: 24px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
    }

    .data-intel-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        width: fit-content;
        border: 1px solid #d8ebe4;
        border-radius: 999px;
        background: #eef9f5;
        color: #0b6b4f;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0;
    }

    .data-intel-title {
        margin-top: 14px;
        font-size: 28px;
        line-height: 1.12;
        font-weight: 800;
        color: #111827;
    }

    .data-intel-copy {
        margin-top: 10px;
        max-width: 720px;
        color: #5f6b66;
        font-size: 15px;
        line-height: 1.7;
    }

    .data-intel-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 520px));
        gap: 14px;
    }

    .data-intel-card {
        display: block;
        border: 1px solid #e2e1dc;
        border-radius: 12px;
        background: #ffffff;
        padding: 16px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .04);
        color: inherit;
        text-decoration: none;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .data-intel-card:hover {
        border-color: #b8d8cc;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .07);
        transform: translateY(-1px);
    }

    .data-intel-card.active {
        border-color: #b8d8cc;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
    }

    .data-intel-card-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: #f0f7f4;
        color: #0b6b4f;
    }

    .data-intel-card h4 {
        margin: 12px 0 6px;
        font-size: 15px;
        font-weight: 800;
        color: #111827;
    }

    .data-intel-card p {
        margin: 0;
        color: #69756f;
        font-size: 13px;
        line-height: 1.55;
    }

    @media (max-width: 900px) {
        .data-intel-grid {
            grid-template-columns: 1fr;
        }

        .data-intel-title {
            font-size: 24px;
        }
    }
</style>
@endpush

@section('content')
<div class="data-intel-shell">
    <section class="data-intel-hero">
        <div>
            <span class="data-intel-eyebrow">
                <i class="fas fa-brain"></i>
                Data Intelligent
            </span>
            <h1 class="data-intel-title">Reports workspace</h1>
            <p class="data-intel-copy">
                Revenue aur performance reports ko ek jagah se view, filter aur export karein.
            </p>
        </div>
    </section>

    <section class="data-intel-grid" aria-label="Available reports">
        @unless(auth()->user()?->isAdManager())
        <a class="data-intel-card active" href="{{ route('data-intelligence.advisor-performance') }}">
            <span class="data-intel-card-icon"><i class="fas fa-chart-line"></i></span>
            <h4>Advisor Performance & Revenue Contribution Report</h4>
            <p>Advisor-wise leads, incentive, visits, F2F, units, revenue contribution aur EP report.</p>
        </a>
        @endunless
        <a class="data-intel-card" href="{{ route('data-intelligence.lead-quality', array_filter(['source' => $leadQualityDefaultSource ?? '99acres'])) }}">
            <span class="data-intel-card-icon"><i class="fas fa-filter-circle-dollar"></i></span>
            <h4>Vendor Lead Quality Report</h4>
            <p>Source-wise live lead quality, user outcomes, junk/CNP evidence aur vendor replacement summary.</p>
        </a>
    </section>
</div>
@endsection
