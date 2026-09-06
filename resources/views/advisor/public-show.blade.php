<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#143d31">
    <title>{{ $profile->user->name }} — Verified Advisor | {{ brand_name() }}</title>

    @php
        $avatarUrl = $profile->user->profile_picture_url
            ?? 'https://ui-avatars.com/api/?name=' . urlencode($profile->user->name) . '&background=f5ead6&color=173c34&size=400';
        $writtenReviews = $profile->reviews
            ->filter(fn ($review) => ($review->content_type ?? \App\Models\AdvisorPublicReview::CONTENT_TEXT) !== \App\Models\AdvisorPublicReview::CONTENT_VIDEO)
            ->values();
        $videoTestimonials = $profile->reviews
            ->filter(fn ($review) => ($review->content_type ?? \App\Models\AdvisorPublicReview::CONTENT_TEXT) === \App\Models\AdvisorPublicReview::CONTENT_VIDEO)
            ->values();
        $ratingReviews = $writtenReviews->filter(fn ($review) => (int) ($review->rating ?? 0) > 0)->values();
        $ratingAvg = $ratingReviews->count() > 0 ? round($ratingReviews->avg('rating'), 1) : null;
        $ratingCount = $ratingReviews->count();
        $featuredWrittenReviews = $writtenReviews->take(3);
        $remainingWrittenReviews = $writtenReviews->slice(3)->values();
        $featuredVideo = $videoTestimonials->first();
        $videoSupporting = $videoTestimonials->slice(1, 4)->values();
        $videoOverflowCount = max(0, $videoTestimonials->count() - 5);
        $metaDescription = $profile->bio
            ? Str::limit(strip_tags($profile->bio), 160)
            : ($profile->user->name . ' — Company verified property advisor.');

        $achievementCategories = ['achievement', 'certificate'];
        $customerCategories = ['with_customer', 'customer_photo', 'booking_moment'];

        $achievements = $profile->galleryItems->filter(fn ($g) => in_array($g->category, $achievementCategories, true))->values();
        $withCustomers = $profile->galleryItems->filter(fn ($g) => in_array($g->category, $customerCategories, true))->values();
        $workMoments = $profile->galleryItems->filter(fn ($g) => !in_array($g->category, array_merge($achievementCategories, $customerCategories), true))->values();

        $categoryLabels = [
            'achievement' => 'Achievement',
            'certificate' => 'Certificate',
            'with_customer' => 'With Customer',
            'customer_photo' => 'Customer Photo',
            'booking_moment' => 'Booking Moment',
            'site_visit' => 'Site Visit',
            'meeting' => 'Meeting',
        ];
        $portfolioValue = (float) ($profile->investor_portfolio_value ?? 0);
        $portfolioDisplay = $portfolioValue > 0
            ? rtrim(rtrim(number_format($portfolioValue, $portfolioValue == floor($portfolioValue) ? 0 : 1), '0'), '.') . '+ Cr.'
            : '0 Cr.';

        $statBand = [
            ['icon' => 'fa-handshake', 'value' => ($profile->successful_closures ?? 0) . '+', 'label' => 'Successful Closures'],
            ['icon' => 'fa-route', 'value' => ($profile->site_visits_handled ?? 0) . '+', 'label' => 'Site Visits Managed'],
            ['icon' => 'fa-ruler-combined', 'value' => ($profile->sqft_sold ?? 0) > 0 ? number_format($profile->sqft_sold) . '+' : '0+', 'label' => 'Sq.Ft Sold'],
            ['icon' => 'fa-people-group', 'value' => ($profile->happy_families_served ?? 0) . '+', 'label' => 'Happy Families'],
            ['icon' => 'fa-indian-rupee-sign', 'value' => $portfolioDisplay, 'label' => 'Investor Portfolio Value'],
            ['icon' => 'fa-user-tie', 'value' => ($profile->active_investors ?? 0) . '+', 'label' => 'Active Investors'],
            ['icon' => 'fa-globe', 'value' => ($profile->nri_investors_assisted ?? 0) . '+', 'label' => 'NRI Investors Assisted'],
            ['icon' => 'fa-calendar-check', 'value' => ($profile->bookings_this_quarter ?? 0) . '+', 'label' => 'Bookings This Quarter'],
        ];

        $firstName = \Illuminate\Support\Str::before($profile->user->name, ' ') ?: $profile->user->name;
        $galleryAll = $profile->galleryItems;
        $gallerySlides = $galleryAll
            ->map(function ($item) use ($categoryLabels) {
                return [
                    'image' => asset('storage/' . $item->image_path),
                    'tag' => $categoryLabels[$item->category] ?? 'Gallery',
                    'title' => trim((string) ($item->caption ?? '')) ?: ($categoryLabels[$item->category] ?? 'Gallery Highlight'),
                    'meta' => optional($item->created_at)->format('d M Y'),
                ];
            })
            ->values();
        $loanPartners = $profile->loanPartners ?? collect();
    @endphp

    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:title" content="{{ $profile->user->name }} — Verified Advisor">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:image" content="{{ $avatarUrl }}">
    <meta property="og:type" content="profile">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        :root {
            --ink: #0e3329;
            --ink-soft: #3f5b52;
            --muted: #6b7e77;
            --line: #e3e8e3;
            --line-soft: #eef2ee;
            --bg: #f5f6f2;
            --card: #ffffff;
            --accent: #0f5138;
            --accent-2: #2e7357;
            --sand: #f5ead6;
        }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--ink); }
        .reveal { opacity: 0; transform: translateY(8px); transition: opacity .45s ease, transform .45s ease; }
        .reveal.is-visible { opacity: 1; transform: none; }
        .lightbox { position: fixed; inset: 0; background: rgba(15,23,42,.88); display: none; align-items: center; justify-content: center; z-index: 60; padding: 1rem; cursor: zoom-out; }
        .lightbox.active { display: flex; animation: fadeIn .2s ease; }
        .lightbox-inner { position: relative; max-width: min(96vw, 1100px); }
        .lightbox img { max-width: 100%; max-height: 88vh; border-radius: 1rem; box-shadow: 0 30px 80px rgba(0,0,0,.45); cursor: default; }
        .lightbox-close { position: absolute; top: -10px; right: -10px; width: 36px; height: 36px; border-radius: 9999px; border: none; cursor: pointer; background: #fff; font-size: 1.1rem; box-shadow: 0 8px 24px rgba(0,0,0,.2); }
        @keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
        @media (prefers-reduced-motion: reduce) { .reveal { transition: none; opacity: 1; transform: none; } }
        .pb-safe { padding-bottom: max(4.5rem, env(safe-area-inset-bottom)); }

        /* Full-bleed content shell with responsive gutters (no 1280px cap). */
        .shell {
            width: 100%;
            margin-inline: auto;
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
        }
        @media (min-width: 640px) { .shell { padding-inline: 1.5rem; } }
        @media (min-width: 1024px) { .shell { padding-inline: 2rem; } }
        @media (min-width: 1280px) { .shell { padding-inline: 2.5rem; } }
        @media (min-width: 1536px) { .shell { padding-inline: 3rem; } }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(15, 51, 41, .04);
        }
        .card-pad { padding: 1.1rem 1.25rem; }
        @media (min-width: 1024px) { .card-pad { padding: 1.25rem 1.5rem; } }
        .sect-title { font-size: 0.95rem; font-weight: 700; letter-spacing: -0.01em; }
        .sect-sub { font-size: 0.78rem; color: var(--muted); }
        .divider { height: 1px; background: var(--line-soft); }
        .kbd-eyebrow { font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.14em; color: var(--muted); }
        .chip { display: inline-flex; align-items: center; gap: .35rem; padding: .28rem .65rem; border-radius: 999px; background: #eef4ef; color: var(--accent); font-size: .72rem; font-weight: 600; }
        .pill { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .7rem; border-radius: 999px; background: #f1f5f2; color: var(--ink); font-size: .78rem; font-weight: 500; border: 1px solid var(--line); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; padding: .6rem .95rem; border-radius: 12px; font-weight: 600; font-size: .83rem; transition: all .15s ease; white-space: nowrap; }
        .btn-primary { background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%); color: #fff; box-shadow: 0 6px 16px rgba(15, 81, 56, .25); }
        .btn-primary:hover { filter: brightness(1.05); }
        .btn-ghost { background: #fff; color: var(--ink); border: 1px solid var(--line); }
        .btn-ghost:hover { background: #f7faf8; }
        .btn-sand { background: var(--sand); color: var(--ink); }
        .btn-sand:hover { background: #ece6d6; }

        .hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #0b2e24 0%, #163f31 45%, #215843 100%);
            color: #fff;
        }
        .hero::before,
        .hero::after {
            content: '';
            position: absolute;
            border-radius: 9999px;
            filter: blur(80px);
            opacity: .35;
            pointer-events: none;
        }
        .hero::before { width: 280px; height: 280px; background: #2e7357; left: -80px; top: -60px; }
        .hero::after { width: 320px; height: 320px; background: #f5ead6; right: -90px; bottom: -100px; opacity: .18; }

        .avatar-ring {
            padding: 4px;
            background: linear-gradient(135deg, rgba(255,255,255,.35), rgba(245,234,214,.5));
            border-radius: 20px;
        }
        .stat-tile {
            display: flex; align-items: center; gap: .75rem;
            padding: .9rem 1rem;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
        }
        .stat-tile .ic {
            flex: none; width: 40px; height: 40px; border-radius: 10px;
            display: grid; place-items: center;
            background: linear-gradient(135deg, #eef4ef, #e2ece5);
            color: var(--accent);
        }
        .stat-tile .v { font-size: 1.25rem; font-weight: 700; line-height: 1; color: var(--ink); }
        .stat-tile .l { font-size: .72rem; color: var(--muted); margin-top: .2rem; }
        .stats-strip {
            display: flex;
            gap: .75rem;
            overflow-x: auto;
            padding-bottom: .2rem;
            scrollbar-width: none;
            -ms-overflow-style: none;
            scroll-behavior: smooth;
            scroll-snap-type: x proximity;
        }
        .stats-strip::-webkit-scrollbar { display: none; }
        .stats-strip .stat-tile {
            min-width: min(84vw, 300px);
            flex: 0 0 min(84vw, 300px);
            scroll-snap-align: start;
        }
        .desktop-stats-band {
            display: none;
        }
        @media (min-width: 640px) {
            .stats-strip .stat-tile {
                min-width: 260px;
                flex-basis: 260px;
            }
        }
        @media (min-width: 1024px) {
            .stats-strip {
                display: none;
            }
            .desktop-stats-band {
                display: grid;
                gap: .85rem;
                padding: .9rem 1.1rem 1rem;
                border-radius: 20px;
                background: linear-gradient(135deg, #063A1C 0%, #205A44 58%, #2b7a5c 100%);
                box-shadow: 0 18px 42px rgba(6, 58, 28, .16);
                border: 1px solid rgba(255,255,255,.08);
            }
            .desktop-stats-band .band-head {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 1rem;
                padding-bottom: .05rem;
            }
            .desktop-stats-band .band-kicker {
                font-size: .64rem;
                font-weight: 700;
                letter-spacing: .16em;
                text-transform: uppercase;
                color: rgba(232, 246, 238, .68);
            }
            .desktop-stats-band .band-title {
                margin-top: .22rem;
                font-size: 1.42rem;
                font-weight: 800;
                line-height: 1;
                letter-spacing: -.03em;
                color: #fff;
            }
            .desktop-stats-band .band-sub {
                font-size: .76rem;
                color: rgba(232, 246, 238, .78);
                text-align: right;
            }
            .desktop-stats-grid {
                display: grid;
                gap: 0;
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
            .desktop-stat {
                padding: .8rem .82rem .78rem;
                border-right: 1px solid rgba(255,255,255,.13);
                border-bottom: 1px solid rgba(255,255,255,.13);
            }
            .desktop-stat:nth-child(4n) {
                border-right: none;
            }
            .desktop-stat:nth-last-child(-n+4) {
                border-bottom: none;
            }
            .desktop-stat .v {
                font-size: 1.6rem;
                font-weight: 800;
                line-height: 1;
                letter-spacing: -.04em;
                color: #fff;
            }
            .desktop-stat .l {
                margin-top: .28rem;
                max-width: 13ch;
                font-size: .74rem;
                line-height: 1.3;
                color: rgba(232, 246, 238, .78);
            }
        }
        @media (min-width: 1280px) {
            .desktop-stats-band {
                padding-inline: 1.2rem;
            }
            .desktop-stats-grid {
                grid-template-columns: repeat(8, minmax(0, 1fr));
            }
            .desktop-stat {
                min-height: 88px;
                padding-inline: .9rem;
                border-bottom: none;
            }
            .desktop-stat:nth-child(4n) {
                border-right: 1px solid rgba(255,255,255,.13);
            }
            .desktop-stat:nth-child(8n) {
                border-right: none;
            }
        }

        .info-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .6rem 0; font-size: .85rem; }
        .info-row + .info-row { border-top: 1px solid var(--line-soft); }
        .info-row .k { color: var(--muted); }
        .info-row .v { color: var(--ink); font-weight: 600; text-align: right; }

        .tile-img {
            position: relative; overflow: hidden; border-radius: 12px; background: #f1f5f2; border: 1px solid var(--line);
        }
        .tile-img img { display: block; width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
        .tile-img:hover img { transform: scale(1.03); }
        .tile-cap { position: absolute; inset: auto 0 0 0; padding: .45rem .65rem; font-size: .7rem; font-weight: 600; color: #fff; background: linear-gradient(0deg, rgba(11,46,36,.85), rgba(11,46,36,0)); }
        .media-showcase {
            display: grid;
            gap: 1rem;
        }
        .media-stage {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, #eff5f0 0%, #f7faf8 100%);
            min-height: 340px;
            cursor: zoom-in;
        }
        .media-stage-image {
            width: 100%;
            height: 100%;
            min-height: 340px;
            object-fit: cover;
            display: block;
            transform: scale(1.01);
            transition: transform .8s cubic-bezier(0.22, 1, 0.36, 1), opacity .45s ease, filter .45s ease;
        }
        .media-stage.is-transitioning .media-stage-image {
            opacity: .35;
            transform: scale(1.08);
            filter: blur(8px);
        }
        .media-stage-overlay {
            position: absolute;
            inset: auto 0 0 0;
            padding: 1.25rem;
            background: linear-gradient(180deg, rgba(6,58,28,0) 0%, rgba(6,58,28,.82) 100%);
            color: #fff;
        }
        .media-stage-tag {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border-radius: 999px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.18);
            padding: .38rem .72rem;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .media-stage-title {
            margin-top: .75rem;
            max-width: 22ch;
            font-size: clamp(1.4rem, 2vw, 2.35rem);
            font-weight: 800;
            line-height: 1.04;
            letter-spacing: -.04em;
        }
        .media-stage-meta {
            margin-top: .4rem;
            font-size: .8rem;
            color: rgba(239, 248, 243, .82);
        }

        .media-zoom-hint {
            position: absolute;
            top: 14px;
            left: 14px;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .65rem;
            border-radius: 999px;
            background: rgba(10, 34, 22, .55);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: #fff;
            font-size: .66rem;
            font-weight: 700;
            letter-spacing: .05em;
            border: 1px solid rgba(255,255,255,.18);
            opacity: 0;
            transform: translateY(-4px);
            transition: opacity .3s ease, transform .3s ease;
        }
        .media-showcase:hover .media-zoom-hint { opacity: 1; transform: translateY(0); }
        @media (max-width: 640px) { .media-zoom-hint { display: none; } }

        /* Mobile: thumbnails fully hidden */
        .media-thumbs { display: none; }

        .media-thumb {
            position: relative;
            overflow: hidden;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #0f5138;
            text-align: left;
            cursor: pointer;
            transition: transform .5s cubic-bezier(0.22, 1, 0.36, 1), box-shadow .35s ease, border-color .35s ease, opacity .35s ease;
        }
        .media-thumb img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            transition: transform .65s cubic-bezier(0.22, 1, 0.36, 1), opacity .35s ease;
        }
        .media-thumb::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(6,58,28,0) 45%, rgba(6,58,28,.75) 100%);
            opacity: 0;
            transition: opacity .35s ease;
            z-index: 1;
            pointer-events: none;
        }
        .media-thumb:hover::before { opacity: 1; }
        .media-thumb:hover img { transform: scale(1.06); }
        .media-thumb.active::before { opacity: 1; }
        .media-thumb.active {
            border-color: rgba(16, 185, 129, .55);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, .28), 0 12px 28px rgba(15, 81, 56, .18);
        }
        .media-thumb.active img {
            transform: scale(1.08);
        }
        .media-thumb-copy {
            position: absolute;
            inset: auto .4rem .4rem .4rem;
            padding: .35rem .45rem .4rem;
            border-radius: 10px;
            z-index: 2;
            opacity: 0;
            transform: translateY(6px);
            transition: opacity .35s ease, transform .35s ease;
        }
        .media-thumb:hover .media-thumb-copy,
        .media-thumb.active .media-thumb-copy {
            opacity: 1;
            transform: translateY(0);
        }
        .media-thumb-tag {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: rgba(255,255,255,.22);
            color: #fff;
            padding: .18rem .45rem;
            font-size: .56rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            backdrop-filter: blur(6px);
        }
        .media-thumb-title {
            margin-top: .3rem;
            font-size: .7rem;
            font-weight: 700;
            line-height: 1.2;
            color: #fff;
        }
        .media-thumb-meta { display: none; }

        /* Desktop: 2-col uniform thumbnail grid on right */
        @media (min-width: 1024px) {
            .media-showcase {
                grid-template-columns: minmax(0, 1.65fr) minmax(260px, 1fr);
                align-items: stretch;
                gap: 1rem;
            }
            .media-stage {
                min-height: 560px;
            }
            .media-stage-image {
                min-height: 560px;
            }
            .media-thumbs {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: .55rem;
                max-height: 560px;
                overflow-y: auto;
                padding: 2px;
                scrollbar-width: thin;
                scrollbar-color: rgba(15, 81, 56, .35) transparent;
            }
            .media-thumbs::-webkit-scrollbar { width: 6px; }
            .media-thumbs::-webkit-scrollbar-track { background: transparent; }
            .media-thumbs::-webkit-scrollbar-thumb {
                background: rgba(15, 81, 56, .25);
                border-radius: 999px;
            }
            .media-thumbs::-webkit-scrollbar-thumb:hover {
                background: rgba(15, 81, 56, .45);
            }
            .media-thumb {
                aspect-ratio: 4 / 3;
            }
        }

        /* ===== Modern Gallery Enhancements ===== */
        .gallery-card {
            position: relative;
            overflow: hidden;
        }
        .gallery-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 15% 0%, rgba(16, 185, 129, .08), transparent 45%),
                radial-gradient(circle at 100% 100%, rgba(15, 81, 56, .06), transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        .gallery-card > * { position: relative; z-index: 1; }

        .gallery-head-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .28rem .65rem;
            border-radius: 999px;
            background: rgba(15, 81, 56, .08);
            color: var(--accent);
            font-size: .62rem;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .gallery-head-eyebrow .dot {
            width: .38rem;
            height: .38rem;
            border-radius: 999px;
            background: var(--accent);
            animation: gPulse 1.6s ease-in-out infinite;
        }
        @keyframes gPulse {
            0%, 100% { opacity: .35; transform: scale(.85); }
            50%      { opacity: 1;   transform: scale(1.15); }
        }

        .gallery-typer {
            display: inline-block;
            color: var(--accent);
            position: relative;
            min-width: 1ch;
        }
        .gallery-typer::after {
            content: '';
            display: inline-block;
            width: 2px;
            height: .95em;
            margin-left: 3px;
            background: currentColor;
            transform: translateY(2px);
            animation: gCaret 1s steps(2) infinite;
        }
        @keyframes gCaret { 50% { opacity: 0; } }

        .media-stage-image {
            animation: gKenBurns 14s ease-in-out infinite alternate;
        }
        .media-stage:hover .media-stage-image { animation-play-state: paused; }
        @keyframes gKenBurns {
            from { transform: scale(1.02) translate(0, 0); }
            to   { transform: scale(1.09) translate(-1.2%, -1.2%); }
        }

        .media-stage-overlay {
            background: linear-gradient(180deg, rgba(6,58,28,0) 0%, rgba(6,58,28,.55) 55%, rgba(6,58,28,.92) 100%);
        }

        .media-stage-title-text {
            display: inline-block;
            white-space: pre-wrap;
        }
        .media-stage-title-caret {
            display: inline-block;
            width: 3px;
            height: .9em;
            vertical-align: text-bottom;
            margin-left: 4px;
            background: #fff;
            animation: gCaret 1s steps(2) infinite;
        }

        .media-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,.85);
            color: var(--accent);
            border: 1px solid rgba(255,255,255,.7);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 10px 22px rgba(6, 58, 28, .22);
            transition: transform .3s ease, background .3s ease, opacity .3s ease;
            z-index: 3;
            opacity: 0;
        }
        .media-showcase:hover .media-nav,
        .media-stage:focus-within ~ .media-nav { opacity: 1; }
        .media-nav:hover { background: #fff; transform: translateY(-50%) scale(1.08); }
        .media-nav-prev { left: 14px; }
        .media-nav-next { right: 14px; }
        @media (max-width: 640px) {
            .media-nav { opacity: 1; width: 34px; height: 34px; }
            .media-nav-prev { left: 8px; }
            .media-nav-next { right: 8px; }
        }

        .media-counter {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            padding: .3rem .65rem;
            border-radius: 999px;
            background: rgba(10, 34, 22, .55);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: #fff;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .08em;
            border: 1px solid rgba(255,255,255,.18);
        }
        .media-counter b { font-weight: 800; }
        .media-counter .slash { opacity: .6; margin: 0 .1rem; }

        .media-progress {
            position: absolute;
            left: 0; right: 0; bottom: 0;
            height: 3px;
            background: rgba(255,255,255,.15);
            overflow: hidden;
            z-index: 3;
        }
        .media-progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #34d399, #a7f3d0);
            transition: width .1s linear;
            box-shadow: 0 0 10px rgba(167, 243, 208, .6);
        }

        .media-dots {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }
        .media-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: rgba(15, 81, 56, .22);
            transition: width .3s ease, background .3s ease;
            cursor: pointer;
            border: 0;
            padding: 0;
        }
        .media-dot.active {
            width: 22px;
            background: var(--accent);
        }

        .media-thumb {
            opacity: .8;
        }
        .media-thumb:hover { opacity: 1; }
        .media-thumb.active { opacity: 1; }
        .media-thumb.active::after {
            content: '';
            position: absolute;
            inset: auto 0 0 0;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .gallery-grid-tile {
            opacity: 0;
            transform: translateY(18px);
            animation: gFadeUp .7s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .gallery-grid-tile:nth-child(1) { animation-delay: .05s; }
        .gallery-grid-tile:nth-child(2) { animation-delay: .12s; }
        .gallery-grid-tile:nth-child(3) { animation-delay: .19s; }
        .gallery-grid-tile:nth-child(4) { animation-delay: .26s; }
        .gallery-grid-tile:nth-child(5) { animation-delay: .33s; }
        .gallery-grid-tile:nth-child(6) { animation-delay: .4s; }
        .gallery-grid-tile:nth-child(7) { animation-delay: .47s; }
        .gallery-grid-tile:nth-child(8) { animation-delay: .54s; }
        .gallery-grid-tile:nth-child(9) { animation-delay: .61s; }
        @keyframes gFadeUp {
            to { opacity: 1; transform: translateY(0); }
        }

        .tile-img {
            transition: transform .5s cubic-bezier(0.22, 1, 0.36, 1), box-shadow .35s ease, border-color .35s ease;
        }
        .tile-img:hover {
            transform: translateY(-4px);
            box-shadow: 0 18px 32px rgba(15, 81, 56, .14);
            border-color: rgba(15, 81, 56, .25);
        }

        @media (prefers-reduced-motion: reduce) {
            .media-stage-image,
            .gallery-grid-tile,
            .gallery-head-eyebrow .dot,
            .media-stage-title-caret,
            .gallery-typer::after { animation: none !important; }
            .gallery-grid-tile { opacity: 1; transform: none; }
        }

        /* ===== Builder Partners Section ===== */
        .builder-card {
            position: relative;
            overflow: hidden;
        }
        .builder-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 0% 0%, rgba(16, 185, 129, .06), transparent 45%),
                radial-gradient(circle at 100% 100%, rgba(15, 81, 56, .05), transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        .builder-card > * { position: relative; z-index: 1; }

        .featured-builders {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin-top: 1rem;
        }
        .featured-builder {
            position: relative;
            overflow: hidden;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, #ffffff 0%, #f7faf8 100%);
            padding: 1rem .9rem;
            text-align: center;
            transition: transform .45s cubic-bezier(0.22, 1, 0.36, 1), box-shadow .35s ease, border-color .35s ease;
        }
        .featured-builder::after {
            content: '';
            position: absolute;
            inset: auto 0 0 0;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #34d399);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .5s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .featured-builder:hover {
            transform: translateY(-4px);
            border-color: rgba(15, 81, 56, .25);
            box-shadow: 0 14px 28px rgba(15, 81, 56, .12);
        }
        .featured-builder:hover::after { transform: scaleX(1); }
        .featured-builder-logo {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto .55rem;
        }
        .featured-builder-logo img {
            max-height: 60px;
            max-width: 100%;
            object-fit: contain;
            filter: grayscale(.2);
            transition: filter .4s ease, transform .45s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .featured-builder:hover .featured-builder-logo img {
            filter: grayscale(0);
            transform: scale(1.06);
        }
        .featured-builder-name {
            font-size: .78rem;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -.01em;
        }
        .featured-builder-tag {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            margin-top: .3rem;
            padding: .15rem .5rem;
            border-radius: 999px;
            background: linear-gradient(90deg, #fbbf24, #f59e0b);
            color: #fff;
            font-size: .55rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .builder-marquee {
            position: relative;
            margin-top: 1rem;
            overflow: hidden;
            border-radius: 16px;
            background: linear-gradient(180deg, #f7faf8 0%, #ffffff 100%);
            border: 1px solid var(--line);
            padding: .9rem 0;
        }
        .builder-marquee::before,
        .builder-marquee::after {
            content: '';
            position: absolute;
            top: 0; bottom: 0;
            width: 60px;
            z-index: 2;
            pointer-events: none;
        }
        .builder-marquee::before { left: 0; background: linear-gradient(90deg, #f7faf8, transparent); }
        .builder-marquee::after  { right: 0; background: linear-gradient(270deg, #ffffff, transparent); }

        .builder-marquee-track {
            display: flex;
            gap: 2rem;
            width: max-content;
            animation: builderScroll 28s linear infinite;
        }
        .builder-marquee:hover .builder-marquee-track { animation-play-state: paused; }
        @keyframes builderScroll {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        .builder-logo-item {
            flex-shrink: 0;
            height: 52px;
            width: 130px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: .55;
            filter: grayscale(1);
            transition: opacity .35s ease, filter .35s ease, transform .35s ease;
        }
        .builder-logo-item:hover {
            opacity: 1;
            filter: grayscale(0);
            transform: translateY(-2px);
        }
        .builder-logo-item img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }
        .builder-logo-item-fallback {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .35rem .75rem;
            border-radius: 999px;
            border: 1px dashed rgba(15, 81, 56, .3);
            color: var(--accent);
            font-size: .72rem;
            font-weight: 700;
            white-space: nowrap;
        }

        @media (max-width: 640px) {
            .builder-marquee-track { animation-duration: 22s; gap: 1.4rem; }
            .builder-logo-item { width: 100px; height: 44px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .builder-marquee-track { animation: none; }
            .featured-builder,
            .featured-builder-logo img { transition: none !important; }
        }

        .loan-partner-card {
            position: relative;
            overflow: hidden;
        }
        .loan-partner-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 100% 0%, rgba(15, 81, 56, .06), transparent 42%),
                radial-gradient(circle at 0% 100%, rgba(16, 185, 129, .05), transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        .loan-partner-card > * { position: relative; z-index: 1; }
        .loan-partner-marquee {
            position: relative;
            margin-top: 1rem;
            overflow: hidden;
            border-radius: 18px;
            background: linear-gradient(180deg, #f7faf8 0%, #ffffff 100%);
            border: 1px solid var(--line);
            padding: .95rem 0;
        }
        .loan-partner-marquee::before,
        .loan-partner-marquee::after {
            content: '';
            position: absolute;
            top: 0; bottom: 0;
            width: 64px;
            z-index: 2;
            pointer-events: none;
        }
        .loan-partner-marquee::before { left: 0; background: linear-gradient(90deg, #f7faf8, transparent); }
        .loan-partner-marquee::after  { right: 0; background: linear-gradient(270deg, #ffffff, transparent); }
        .loan-partner-track {
            display: flex;
            gap: 1rem;
            width: max-content;
            animation: builderScroll 34s linear infinite;
        }
        .loan-partner-marquee:hover .loan-partner-track { animation-play-state: paused; }
        .loan-partner-item {
            flex-shrink: 0;
            width: 190px;
            min-height: 102px;
            border-radius: 18px;
            border: 1px solid rgba(15, 81, 56, .08);
            background: rgba(255,255,255,.92);
            box-shadow: 0 8px 20px rgba(15, 51, 41, .05);
            padding: .85rem .8rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform .3s ease, box-shadow .3s ease, border-color .3s ease;
        }
        .loan-partner-item:hover {
            transform: translateY(-3px);
            border-color: rgba(15, 81, 56, .18);
            box-shadow: 0 14px 28px rgba(15, 51, 41, .1);
        }
        .loan-partner-logo {
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .loan-partner-logo img {
            max-width: 100%;
            max-height: 34px;
            object-fit: contain;
        }
        .loan-partner-name {
            margin-top: .55rem;
            font-size: .73rem;
            font-weight: 800;
            color: var(--ink);
            line-height: 1.2;
        }
        .loan-partner-note {
            margin-top: .22rem;
            font-size: .66rem;
            color: var(--muted);
            line-height: 1.35;
            min-height: 1.8rem;
        }
        .loan-partner-meta {
            display: inline-flex;
            align-items: center;
            gap: .28rem;
            margin-top: .5rem;
            padding: .2rem .48rem;
            border-radius: 999px;
            background: #eef4ef;
            color: var(--accent);
            font-size: .58rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .07em;
        }
        .loan-partner-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line-soft);
        }
        .loan-partner-disclaimer {
            font-size: .72rem;
            color: var(--muted);
            max-width: 36rem;
            line-height: 1.45;
        }
        @media (max-width: 640px) {
            .loan-partner-track { gap: .85rem; animation-duration: 26s; }
            .loan-partner-item { width: 165px; min-height: 98px; }
            .loan-partner-actions { flex-direction: column; align-items: flex-start; }
        }
        @media (prefers-reduced-motion: reduce) {
            .loan-partner-track { animation: none; }
            .loan-partner-item { transition: none !important; }
        }

        /* Hero portrait card (photo with cream footer) */
        .portrait-card {
            width: 100%;
            max-width: 380px;
            padding: 10px;
            border-radius: 24px;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.18);
            box-shadow: 0 25px 60px rgba(7, 32, 24, .45);
            backdrop-filter: blur(6px);
        }
        @media (min-width: 1280px) { .portrait-card { max-width: 400px; } }
        .portrait-inner {
            overflow: hidden;
            border-radius: 18px;
            background: #f7f4ee;
        }
        .portrait-photo {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 4.3;
            overflow: hidden;
            background: #e9efe9;
        }
        .portrait-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 20%;
            display: block;
        }
        .portrait-foot { padding: 1rem 1.1rem 1.1rem; }
        .rating-pill {
            display: grid;
            place-items: center;
            min-width: 74px;
            padding: .5rem .75rem;
            border-radius: 14px;
            background: linear-gradient(135deg, #0f5138, #2e7357);
            color: #fff;
            box-shadow: 0 8px 18px rgba(15, 81, 56, .25);
        }
        .mini-tile {
            border-radius: 14px;
            padding: .85rem .9rem;
            text-align: center;
            box-shadow: 0 1px 2px rgba(15,51,41,.04);
            border: 1px solid rgba(15,51,41,.05);
        }

        .review-card { background: #fafcfa; border: 1px solid var(--line); border-radius: 14px; padding: .9rem 1rem; }
        .review-card + .review-card { margin-top: .65rem; }
        .stars { color: #d8a23f; letter-spacing: 1px; font-size: .8rem; }
        .stars .dim { opacity: .22; color: #98a89f; }
        .review-shell {
            display: grid;
            gap: 1rem;
        }
        .testimonial-panel {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdfb 100%);
            padding: 1rem;
        }
        .review-summary-card {
            border: 1px solid var(--line-soft);
            border-radius: 16px;
            background: #f7faf8;
            padding: .95rem 1rem;
        }
        .video-story {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #fff;
        }
        .video-showcase {
            display: grid;
            gap: .85rem;
        }
        .video-hero {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: #fff;
        }
        .video-hero .video-thumb {
            aspect-ratio: 16 / 9;
        }
        .video-hero .video-play span {
            width: 68px;
            height: 68px;
        }
        .video-hero-desktop {
            display: none;
        }
        .video-hero-player {
            aspect-ratio: 16 / 9;
            background: #000;
        }
        .video-hero-player iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }
        .video-hero-meta {
            position: absolute;
            inset: auto 0 0 0;
            padding: 1rem 1rem 1.05rem;
            background: linear-gradient(180deg, rgba(6,58,28,0) 0%, rgba(6,58,28,.82) 100%);
            color: #fff;
        }
        .video-hero-copy {
            padding: 1rem 1rem 1.05rem;
        }
        .video-hero-copy .name {
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.2;
            color: var(--ink);
        }
        .video-hero-copy .sub {
            margin-top: .32rem;
            font-size: .75rem;
            color: var(--muted);
        }
        .video-hero-copy .quote {
            margin-top: .5rem;
            font-size: .82rem;
            line-height: 1.5;
            color: var(--ink-soft);
        }
        .video-hero-meta .name {
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .video-hero-meta .sub {
            margin-top: .3rem;
            font-size: .75rem;
            color: rgba(239, 248, 243, .82);
        }
        .video-hero-meta .quote {
            margin-top: .45rem;
            max-width: 42ch;
            font-size: .8rem;
            line-height: 1.45;
            color: rgba(255,255,255,.92);
        }
        .video-mini-grid {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .video-mini {
            position: relative;
        }
        .video-mini .video-thumb {
            aspect-ratio: 16 / 9;
        }
        .video-mini .video-play span {
            width: 46px;
            height: 46px;
        }
        .video-mini .video-meta {
            padding: .7rem .75rem .8rem;
        }
        .video-mini .video-name {
            font-size: .82rem;
            font-weight: 700;
            line-height: 1.3;
            color: var(--ink);
        }
        .video-more-overlay {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            background: rgba(6,58,28,.62);
            color: #fff;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -.03em;
        }
        .video-thumb {
            position: relative;
            display: block;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            background: #dce7df;
        }
        .video-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .video-play {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            background: linear-gradient(180deg, rgba(6,58,28,.06), rgba(6,58,28,.26));
        }
        .video-play span {
            width: 58px;
            height: 58px;
            border-radius: 9999px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.88);
            color: var(--accent);
            box-shadow: 0 12px 28px rgba(6,58,28,.2);
        }
        .video-meta {
            padding: .85rem .95rem 1rem;
        }
        .video-modal {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,.88);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 70;
            padding: 1rem;
        }
        .video-modal.active { display: flex; }
        .video-modal-inner {
            position: relative;
            width: min(100%, 980px);
            aspect-ratio: 16 / 9;
        }
        .video-modal iframe {
            width: 100%;
            height: 100%;
            border: 0;
            border-radius: 18px;
            background: #000;
            box-shadow: 0 30px 80px rgba(0,0,0,.45);
        }
        .video-modal-close {
            position: absolute;
            top: -12px;
            right: -12px;
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 9999px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(0,0,0,.2);
            cursor: pointer;
        }
        .contact-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(15,23,42,.72);
            z-index: 75;
        }
        .contact-modal.active { display: flex; }
        .contact-modal-card {
            width: min(100%, 420px);
            border-radius: 22px;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: 0 30px 80px rgba(15,23,42,.22);
            padding: 1.2rem 1.2rem 1rem;
        }
        .contact-progress {
            height: 8px;
            border-radius: 9999px;
            background: #e6eee8;
            overflow: hidden;
        }
        .contact-progress-bar {
            width: 35%;
            height: 100%;
            border-radius: 9999px;
            background: linear-gradient(90deg, #0f5138, #2e7357);
            animation: contactProgress 1.2s ease-in-out infinite;
            transform-origin: left;
        }
        @keyframes contactProgress {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(340%); }
        }
        @media (min-width: 1024px) {
            .review-shell {
                grid-template-columns: minmax(0, 1.15fr) minmax(340px, .85fr);
            }
            .video-showcase {
                grid-template-columns: minmax(0, 1fr);
                align-items: start;
            }
            .video-mini-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
            .video-hero {
                display: flex;
                flex-direction: column;
            }
            .video-hero.is-desktop-enhanced > .video-thumb {
                display: none;
            }
            .video-hero-player {
                display: block;
            }
            .video-hero-desktop {
                display: block;
            }
        }

        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
</head>
<body class="antialiased">
    @php
        $previewMode = $previewMode ?? false;
        $canLeaveReview = !$previewMode && $profile->isPubliclyVisible();
        $currentUrl = $previewMode ? route('admin.advisor-profiles.preview', $profile) : url()->current();
    @endphp

    @if($previewMode)
        <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-center text-sm font-medium text-amber-800">
            CRM Preview: ye advisor ka draft/public profile layout hai. Yaha se review submit nahi hoga.
        </div>
    @endif

    {{-- Sticky Top Nav --}}
    <header class="sticky top-0 z-40 border-b border-[color:var(--line)] bg-white/90 backdrop-blur">
        <div class="shell flex items-center justify-between gap-3 py-2.5">
            <a href="#top" class="flex min-w-0 items-center gap-2 text-sm font-semibold text-[color:var(--ink)]">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-[linear-gradient(135deg,#0f5138,#2e7357)] text-white">
                    <i class="fas fa-handshake text-xs"></i>
                </span>
                <span class="truncate">{{ brand_name() }}</span>
                <span class="hidden text-[color:var(--muted)] md:inline">·</span>
                <span class="hidden text-[color:var(--muted)] md:inline">Advisor</span>
            </a>
            <div class="flex items-center gap-2">
                @if($profile->user->phone)
                    <a href="tel:{{ $profile->user->phone }}" class="btn btn-ghost hidden sm:inline-flex">
                        <i class="fas fa-phone text-xs"></i> Call
                    </a>
                @endif
                @if($profile->user->phone || $profile->user->email)
                    <button type="button" data-contact-card="{{ route('advisor.public.contact', $profile->public_slug) }}" class="btn btn-ghost hidden sm:inline-flex">
                        <i class="fas fa-address-book text-xs"></i> Add to Contacts
                    </button>
                @endif
                <button type="button" data-share data-share-title="{{ $profile->user->name }} — Advisor" data-share-text="{{ $metaDescription }}" class="btn btn-ghost hidden sm:inline-flex">
                    <i class="fas fa-share-nodes text-xs"></i> Share
                </button>
                @if($canLeaveReview)
                    <a href="{{ route('advisor.public.review.show', $profile->public_slug) }}" class="btn btn-primary">
                        <i class="fas fa-star text-xs"></i><span class="hidden sm:inline">Leave a Review</span><span class="sm:hidden">Review</span>
                    </a>
                @else
                    <span class="btn btn-primary cursor-default opacity-70">
                        <i class="fas fa-eye text-xs"></i><span class="hidden sm:inline">Preview Only</span><span class="sm:hidden">Preview</span>
                    </span>
                @endif
            </div>
        </div>
    </header>

    <main id="top">

        {{-- HERO: identity (left) + portrait card (right) --}}
        <section class="hero">
            <div class="shell relative grid gap-6 py-7 lg:grid-cols-[1.1fr_400px] lg:gap-10 lg:py-10 xl:grid-cols-[1.15fr_420px]">
                {{-- Identity --}}
                <div class="min-w-0 self-center">
                    <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-[#f5ead6] px-3 py-1.5 text-[11px] font-semibold text-[#173c34] shadow-sm">
                        <i class="fas fa-shield-halved"></i> Company Verified Advisor
                    </span>
                    <p class="mt-3 text-[11px] font-semibold uppercase tracking-[0.22em] text-[#d9eadf]">My Public Profile</p>
                    <h1 class="mt-1.5 text-3xl font-bold leading-tight tracking-tight sm:text-4xl lg:text-[2.75rem]">{{ $profile->user->name }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-[#d8e7dd]">
                        <span class="font-medium">{{ $profile->designation ?: $profile->user->getDisplayRoleName() }}</span>
                        @if(!empty($profile->service_areas))
                            <span class="opacity-50">•</span>
                            <span class="inline-flex items-center gap-1.5"><i class="fas fa-location-dot"></i>{{ implode(' · ', array_slice($profile->service_areas, 0, 3)) }}</span>
                        @endif
                    </div>
                    @if($profile->bio)
                        <p class="mt-3.5 max-w-2xl text-[0.92rem] leading-7 text-[#e7f1ea]">{{ \Illuminate\Support\Str::limit($profile->bio, 220) }}</p>
                    @endif
                    @if(!empty($profile->specialization_tags))
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @foreach(array_slice($profile->specialization_tags, 0, 6) as $tag)
                                <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-medium text-white backdrop-blur-sm">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                    <div class="mt-5 flex flex-wrap gap-2">
                        @if($profile->user->phone)
                            <a href="tel:{{ $profile->user->phone }}" class="btn btn-sand">
                                <i class="fas fa-phone"></i> Request Callback
                            </a>
                        @endif
                        @if($profile->user->phone || $profile->user->email)
                            <button type="button" data-contact-card="{{ route('advisor.public.contact', $profile->public_slug) }}" class="btn text-white ring-1 ring-white/20 bg-white/10 hover:bg-white/15 backdrop-blur-sm">
                                <i class="fas fa-address-book"></i> Add to Contacts
                            </button>
                        @endif
                        @if($canLeaveReview)
                            <a href="{{ route('advisor.public.review.show', $profile->public_slug) }}" class="btn text-white ring-1 ring-white/20 bg-white/10 hover:bg-white/15 backdrop-blur-sm">
                                <i class="fas fa-comment-dots"></i> Leave a Review
                            </a>
                        @else
                            <span class="btn cursor-default text-white ring-1 ring-white/20 bg-white/10 opacity-70">
                                <i class="fas fa-eye"></i> Preview Only
                            </span>
                        @endif
                        <button type="button" data-share data-share-title="{{ $profile->user->name }} — Advisor" data-share-text="{{ $metaDescription }}" class="btn text-white ring-1 ring-white/20 bg-white/10 hover:bg-white/15 backdrop-blur-sm">
                            <i class="fas fa-share-nodes"></i> Share
                        </button>
                    </div>
                </div>

                {{-- Portrait card --}}
                <div class="flex justify-center lg:justify-end">
                    <div class="portrait-card">
                        <div class="portrait-inner">
                            <div class="portrait-photo">
                                <img src="{{ $avatarUrl }}" alt="{{ $profile->user->name }}" loading="eager">
                            </div>
                            <div class="portrait-foot">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h2 class="text-[1.15rem] font-bold leading-tight text-[color:var(--ink)] sm:text-[1.25rem]">Trusted Local Advisor</h2>
                                        <p class="mt-1 text-[0.78rem] text-[color:var(--muted)]">
                                            {{ $profile->company_tenure_label }} with company
                                            <span class="opacity-60">•</span> Active with company
                                        </p>
                                    </div>
                                    @if($ratingAvg)
                                        <div class="rating-pill shrink-0">
                                            <div class="text-[0.95rem] font-bold leading-none">{{ $ratingAvg }} <i class="fas fa-star text-amber-300 text-[0.7rem]"></i></div>
                                            <div class="mt-1 text-[10px] text-[#dcebe2]">Avg Rating</div>
                                        </div>
                                    @endif
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-2.5">
                                    <div class="mini-tile bg-white">
                                        <div class="text-[1.35rem] font-bold leading-none text-[color:var(--ink)]">{{ ($profile->happy_families_served ?? 0) }}+</div>
                                        <div class="mt-1 text-[0.72rem] text-[color:var(--muted)]">Happy Families</div>
                                    </div>
                                    <div class="mini-tile bg-[#eef4ef]">
                                        <div class="text-[1.35rem] font-bold leading-none text-[color:var(--ink)]">{{ !empty($profile->languages) ? count($profile->languages) : 0 }}</div>
                                        <div class="mt-1 text-[0.72rem] text-[color:var(--muted)]">Languages</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- STATS STRIP --}}
        <section class="shell relative z-10 py-4 lg:py-5">
            <div class="desktop-stats-band reveal">
                <div class="band-head">
                    <div>
                        <div class="band-kicker">Performance Snapshot</div>
                        <div class="band-title">Trusted by Homebuyers and Investors</div>
                    </div>
                    <div class="band-sub">Verified public profile highlights</div>
                </div>
                <div class="desktop-stats-grid">
                    @foreach($statBand as $row)
                        <div class="desktop-stat">
                            <div class="v">{{ $row['value'] }}</div>
                            <div class="l">{{ $row['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="stats-strip" data-stats-strip>
                @foreach($statBand as $row)
                    <div class="stat-tile reveal">
                        <span class="ic"><i class="fas {{ $row['icon'] }}"></i></span>
                        <div class="min-w-0">
                            <div class="v">{{ $row['value'] }}</div>
                            <div class="l">{{ $row['label'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- CONTENT GRID --}}
        <div class="shell grid gap-5 pb-28 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-5 lg:pb-8 xl:grid-cols-[minmax(0,1fr)_400px]">

            {{-- LEFT: primary column --}}
            <div class="space-y-5">

                {{-- About --}}
                <section class="card reveal">
                    <div class="card-pad">
                        <div class="flex items-center justify-between">
                            <h2 class="sect-title">About {{ $firstName }}</h2>
                            <span class="kbd-eyebrow">Overview</span>
                        </div>
                        @if($profile->bio)
                            <p class="mt-2.5 text-[0.9rem] leading-7 text-[color:var(--ink-soft)]">{{ $profile->bio }}</p>
                        @else
                            <p class="mt-2.5 text-sm italic text-[color:var(--muted)]">Bio added soon.</p>
                        @endif

                        @if($profile->why_choose_me)
                            <div class="mt-4 rounded-xl border border-[color:var(--line-soft)] bg-[#f8faf8] p-3.5">
                                <div class="kbd-eyebrow mb-1.5"><i class="fas fa-circle-check mr-1 text-[color:var(--accent-2)]"></i> Why choose me</div>
                                <p class="text-[0.85rem] leading-6 text-[color:var(--ink-soft)]">{{ $profile->why_choose_me }}</p>
                            </div>
                        @endif
                    </div>
                </section>

                @php
                    $builderPartners = $profile->builders ?? collect();
                    $featuredBuilders = $builderPartners->filter(fn ($b) => (bool) ($b->pivot->is_featured ?? false))->take(4);
                    $marqueeBuilders = $builderPartners->values();
                @endphp

                @if($builderPartners->isNotEmpty())
                    <section id="builder-partners" class="card reveal builder-card">
                        <div class="card-pad">
                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <div>
                                    <span class="gallery-head-eyebrow"><span class="dot"></span> Trusted Partners</span>
                                    <h2 class="sect-title mt-2">
                                        <i class="fas fa-handshake mr-1.5 text-[color:var(--accent-2)]"></i>
                                        Builders I work with
                                    </h2>
                                    <p class="sect-sub mt-1">Verified developer partnerships â€” delivering trusted homes together.</p>
                                </div>
                                <span class="kbd-eyebrow">{{ $builderPartners->count() }} partner{{ $builderPartners->count() === 1 ? '' : 's' }}</span>
                            </div>

                            @if($featuredBuilders->isNotEmpty())
                                <div class="featured-builders">
                                    @foreach($featuredBuilders as $builder)
                                        <div class="featured-builder" title="{{ $builder->name }}{{ $builder->description ? ' â€” ' . $builder->description : '' }}">
                                            <div class="featured-builder-logo">
                                                @if($builder->logo_url)
                                                    <img src="{{ $builder->logo_url }}" alt="{{ $builder->name }}" loading="lazy">
                                                @else
                                                    <span class="text-gray-400 text-xs font-bold">{{ strtoupper(substr($builder->name, 0, 2)) }}</span>
                                                @endif
                                            </div>
                                            <div class="featured-builder-name">{{ $builder->name }}</div>
                                            <span class="featured-builder-tag">
                                                <i class="fas fa-star text-[8px]"></i> Featured
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if($marqueeBuilders->count() > 0)
                                <div class="builder-marquee" aria-label="Builder partners">
                                    <div class="builder-marquee-track">
                                        @foreach($marqueeBuilders as $builder)
                                            <div class="builder-logo-item" title="{{ $builder->name }}">
                                                @if($builder->logo_url)
                                                    <img src="{{ $builder->logo_url }}" alt="{{ $builder->name }}" loading="lazy">
                                                @else
                                                    <span class="builder-logo-item-fallback">{{ $builder->name }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                        @foreach($marqueeBuilders as $builder)
                                            <div class="builder-logo-item" aria-hidden="true">
                                                @if($builder->logo_url)
                                                    <img src="{{ $builder->logo_url }}" alt="" loading="lazy">
                                                @else
                                                    <span class="builder-logo-item-fallback">{{ $builder->name }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

                <section class="card reveal">
                    <div class="card-pad">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 class="sect-title">What Clients Say</h2>
                                <p class="sect-sub mt-0.5">Written reviews plus CRM-approved video testimonials.</p>
                            </div>
                            @if($canLeaveReview)
                                <a href="{{ route('advisor.public.review.show', $profile->public_slug) }}" class="btn btn-ghost">
                                    <i class="fas fa-star text-xs"></i> Leave a Review
                                </a>
                            @endif
                        </div>

                        <div class="review-shell mt-4">
                            <div class="testimonial-panel">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="kbd-eyebrow">Verified Reviews</div>
                                        <h3 class="mt-1 text-[1.1rem] font-bold text-[color:var(--ink)]">Recent written feedback</h3>
                                    </div>
                                    @if($ratingAvg)
                                        <div class="review-summary-card">
                                            <div class="flex items-center gap-2.5">
                                                <div class="text-2xl font-bold text-[color:var(--ink)]">{{ $ratingAvg }}</div>
                                                <div class="leading-tight">
                                                    <div class="stars">
                                                        @for($i = 1; $i <= 5; $i++)<i class="fas fa-star {{ $i <= round($ratingAvg) ? '' : 'dim' }}"></i>@endfor
                                                    </div>
                                                    <div class="text-[10px] text-[color:var(--muted)]">{{ $ratingCount }} {{ $ratingCount === 1 ? 'review' : 'reviews' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-4">
                                    @forelse($featuredWrittenReviews as $review)
                                        <article class="review-card">
                                            <header class="flex flex-wrap items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[0.88rem]">
                                                        <span class="font-semibold">{{ $review->customer_name }}</span>
                                                        @if($review->customer_phone_masked)
                                                            <span class="text-[color:var(--muted)]">·</span>
                                                            <span class="text-[color:var(--muted)]">{{ $review->customer_phone_masked }}</span>
                                                        @endif
                                                        @if($review->is_verified_customer)
                                                            <span class="chip"><i class="fas fa-circle-check"></i> Verified</span>
                                                        @endif
                                                        @if(($review->submission_source ?? \App\Models\AdvisorPublicReview::SOURCE_PUBLIC_FORM) === \App\Models\AdvisorPublicReview::SOURCE_ADVISOR_PANEL)
                                                            <span class="chip"><i class="fas fa-shield-halved"></i> CRM Approved</span>
                                                        @endif
                                                        @if($previewMode && $review->moderation_status !== \App\Models\AdvisorPublicReview::STATUS_APPROVED)
                                                            <span class="chip"><i class="fas fa-hourglass-half"></i> {{ ucfirst($review->moderation_status) }}</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-0.5 text-[11px] text-[color:var(--muted)]">
                                                        @if($review->project_name){{ $review->project_name }} · @endif{{ optional($review->created_at)->format('d M Y') }}
                                                    </p>
                                                </div>
                                                @if((int) ($review->rating ?? 0) > 0)
                                                    <div class="stars shrink-0">
                                                        @for($i = 1; $i <= 5; $i++)<i class="fas fa-star {{ $i <= $review->rating ? '' : 'dim' }}"></i>@endfor
                                                    </div>
                                                @endif
                                            </header>
                                            @if($review->review_text)
                                                <p class="mt-2 text-[0.88rem] leading-6 text-[color:var(--ink-soft)]">{{ $review->review_text }}</p>
                                            @endif
                                        </article>
                                    @empty
                                        <div class="rounded-xl border border-dashed border-[color:var(--line)] bg-[#fafcfa] p-6 text-center">
                                            <i class="fas fa-comments text-2xl text-[color:var(--muted)]"></i>
                                            <p class="mt-2 text-sm text-[color:var(--muted)]">Abhi tak koi approved written review nahi hai.</p>
                                            @if($canLeaveReview)
                                                <a href="{{ route('advisor.public.review.show', $profile->public_slug) }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-[color:var(--accent-2)] hover:underline">
                                                    Pehla review likho <i class="fas fa-arrow-right text-xs"></i>
                                                </a>
                                            @endif
                                        </div>
                                    @endforelse

                                    @foreach($remainingWrittenReviews as $review)
                                        <article class="review-card hidden" data-extra-review>
                                            <header class="flex flex-wrap items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[0.88rem]">
                                                        <span class="font-semibold">{{ $review->customer_name }}</span>
                                                        @if($review->customer_phone_masked)
                                                            <span class="text-[color:var(--muted)]">·</span>
                                                            <span class="text-[color:var(--muted)]">{{ $review->customer_phone_masked }}</span>
                                                        @endif
                                                        @if($review->is_verified_customer)
                                                            <span class="chip"><i class="fas fa-circle-check"></i> Verified</span>
                                                        @endif
                                                        @if(($review->submission_source ?? \App\Models\AdvisorPublicReview::SOURCE_PUBLIC_FORM) === \App\Models\AdvisorPublicReview::SOURCE_ADVISOR_PANEL)
                                                            <span class="chip"><i class="fas fa-shield-halved"></i> CRM Approved</span>
                                                        @endif
                                                        @if($previewMode && $review->moderation_status !== \App\Models\AdvisorPublicReview::STATUS_APPROVED)
                                                            <span class="chip"><i class="fas fa-hourglass-half"></i> {{ ucfirst($review->moderation_status) }}</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-0.5 text-[11px] text-[color:var(--muted)]">
                                                        @if($review->project_name){{ $review->project_name }} · @endif{{ optional($review->created_at)->format('d M Y') }}
                                                    </p>
                                                </div>
                                                @if((int) ($review->rating ?? 0) > 0)
                                                    <div class="stars shrink-0">
                                                        @for($i = 1; $i <= 5; $i++)<i class="fas fa-star {{ $i <= $review->rating ? '' : 'dim' }}"></i>@endfor
                                                    </div>
                                                @endif
                                            </header>
                                            @if($review->review_text)
                                                <p class="mt-2 text-[0.88rem] leading-6 text-[color:var(--ink-soft)]">{{ $review->review_text }}</p>
                                            @endif
                                        </article>
                                    @endforeach

                                    @if($remainingWrittenReviews->isNotEmpty())
                                        <button type="button" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-[color:var(--accent-2)] hover:underline" data-toggle-reviews>
                                            View All Reviews <i class="fas fa-arrow-right text-xs"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <div class="testimonial-panel">
                                @if($featuredVideo)
                                    @php
                                        $heroThumb = $featuredVideo->video_thumbnail_url ?: \App\Helpers\CollateralHelper::getYouTubeThumbnailUrl((string) $featuredVideo->video_url);
                                        $heroEmbedUrl = $featuredVideo->video_url ? \App\Helpers\CollateralHelper::getYouTubeEmbedUrl((string) $featuredVideo->video_url) : null;
                                        $heroName = trim((string) ($featuredVideo->customer_name ?? '')) ?: 'Client Video Testimonial';
                                        $heroTitle = trim((string) ($featuredVideo->project_name ?? ''))
                                            ?: (\App\Helpers\CollateralHelper::getYouTubeTitle((string) $featuredVideo->video_url) ?: 'Video Testimonial');
                                        $heroAutoplayUrl = $heroEmbedUrl
                                            ? $heroEmbedUrl . (str_contains($heroEmbedUrl, '?') ? '&' : '?') . 'autoplay=1&mute=1&playsinline=1&rel=0&controls=0&iv_load_policy=3&disablekb=1'
                                            : null;
                                    @endphp
                                    <div class="video-showcase">
                                        <article class="video-hero">
                                            <button type="button" class="video-thumb w-full text-left" @if($heroEmbedUrl) data-video-embed="{{ $heroEmbedUrl }}" @endif>
                                                @if($heroThumb)
                                                    <img src="{{ $heroThumb }}" alt="{{ $heroName }}" loading="lazy">
                                                @else
                                                    <div class="flex h-full items-center justify-center bg-[#dce7df] text-[color:var(--accent)]">
                                                        <i class="fas fa-video text-3xl"></i>
                                                    </div>
                                                @endif
                                                <div class="video-play">
                                                    <span><i class="fas fa-play ml-1"></i></span>
                                                </div>
                                                <div class="video-hero-meta">
                                                    <div class="sub">
                                                        {{ \Illuminate\Support\Str::limit($heroTitle, 70) }}
                                                    </div>
                                                    @if($featuredVideo->review_text)
                                                        <p class="quote">{{ \Illuminate\Support\Str::limit($featuredVideo->review_text, 110) }}</p>
                                                    @endif
                                                </div>
                                            </button>
                                        </article>

                                        <div class="video-mini-grid">
                                            @foreach($videoSupporting as $index => $review)
                                                @php
                                                    $thumb = $review->video_thumbnail_url ?: \App\Helpers\CollateralHelper::getYouTubeThumbnailUrl((string) $review->video_url);
                                                    $embedUrl = $review->video_url ? \App\Helpers\CollateralHelper::getYouTubeEmbedUrl((string) $review->video_url) : null;
                                                    $showMoreOverlay = $videoOverflowCount > 0 && $index === $videoSupporting->count() - 1;
                                                    $videoName = trim((string) ($review->customer_name ?? '')) ?: 'Client Video Testimonial';
                                                    $videoTitle = trim((string) ($review->project_name ?? ''))
                                                        ?: (\App\Helpers\CollateralHelper::getYouTubeTitle((string) $review->video_url) ?: 'Video Testimonial');
                                                @endphp
                                                <article class="video-story video-mini">
                                                    <button type="button" class="video-thumb w-full text-left" @if($embedUrl) data-video-embed="{{ $embedUrl }}" @endif>
                                                        @if($thumb)
                                                            <img src="{{ $thumb }}" alt="{{ $videoName }}" loading="lazy">
                                                        @else
                                                            <div class="flex h-full items-center justify-center bg-[#dce7df] text-[color:var(--accent)]">
                                                                <i class="fas fa-video text-xl"></i>
                                                            </div>
                                                        @endif
                                                        <div class="video-play">
                                                            <span><i class="fas fa-play ml-1 text-sm"></i></span>
                                                        </div>
                                                        @if($showMoreOverlay)
                                                            <div class="video-more-overlay">+{{ $videoOverflowCount }} more</div>
                                                        @endif
                                                    </button>
                                                    <div class="video-meta">
                                                        <div class="mt-1 text-[11px] text-[color:var(--muted)] line-clamp-2">{{ $videoTitle }}</div>
                                                    </div>
                                                </article>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="rounded-xl border border-dashed border-[color:var(--line)] bg-[#fafcfa] p-6 text-center">
                                        <i class="fas fa-video text-2xl text-[color:var(--muted)]"></i>
                                        <p class="mt-2 text-sm text-[color:var(--muted)]">Abhi tak koi approved video testimonial nahi hai.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>

                @if(false)
                {{-- Reviews --}}
                <section class="card reveal">
                    <div class="card-pad">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="sect-title">Verified Reviews</h2>
                                <p class="sect-sub mt-0.5">Customer mobile is masked for privacy.</p>
                            </div>
                            @if($ratingAvg)
                                <div class="flex items-center gap-2.5 rounded-xl bg-[#eef4ef] px-3 py-2">
                                    <div class="text-lg font-bold text-[color:var(--ink)]">{{ $ratingAvg }}</div>
                                    <div class="leading-tight">
                                        <div class="stars">
                                            @for($i = 1; $i <= 5; $i++)<i class="fas fa-star {{ $i <= round($ratingAvg) ? '' : 'dim' }}"></i>@endfor
                                        </div>
                                        <div class="text-[10px] text-[color:var(--muted)]">{{ $ratingCount }} {{ $ratingCount === 1 ? 'review' : 'reviews' }}</div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4">
                            @forelse($profile->reviews as $review)
                                <article class="review-card">
                                    <header class="flex flex-wrap items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[0.88rem]">
                                                <span class="font-semibold">{{ $review->customer_name }}</span>
                                                <span class="text-[color:var(--muted)]">·</span>
                                                <span class="text-[color:var(--muted)]">{{ $review->customer_phone_masked }}</span>
                                                @if($review->is_verified_customer)
                                                    <span class="chip"><i class="fas fa-circle-check"></i> Verified</span>
                                                @endif
                                            </div>
                                            <p class="mt-0.5 text-[11px] text-[color:var(--muted)]">
                                                @if($review->project_name){{ $review->project_name }} · @endif{{ optional($review->created_at)->format('d M Y') }}
                                            </p>
                                        </div>
                                        <div class="stars shrink-0">
                                            @for($i = 1; $i <= 5; $i++)<i class="fas fa-star {{ $i <= $review->rating ? '' : 'dim' }}"></i>@endfor
                                        </div>
                                    </header>
                                    <p class="mt-2 text-[0.88rem] leading-6 text-[color:var(--ink-soft)]">{{ $review->review_text }}</p>
                                </article>
                            @empty
                                <div class="rounded-xl border border-dashed border-[color:var(--line)] bg-[#fafcfa] p-6 text-center">
                                    <i class="fas fa-comments text-2xl text-[color:var(--muted)]"></i>
                                    <p class="mt-2 text-sm text-[color:var(--muted)]">Abhi tak koi approved review nahi hai.</p>
                                    @if($canLeaveReview)
                                        <a href="{{ route('advisor.public.review.show', $profile->public_slug) }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-[color:var(--accent-2)] hover:underline">
                                            Pehla review likho <i class="fas fa-arrow-right text-xs"></i>
                                        </a>
                                    @endif
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>

                @endif
                @if(false)
                {{-- Achievements --}}
                @if($achievements->count())
                    <section class="card reveal">
                        <div class="card-pad">
                            <div class="flex items-center justify-between">
                                <h2 class="sect-title"><i class="fas fa-trophy mr-1.5 text-amber-500"></i>Achievements &amp; Certificates</h2>
                                <span class="kbd-eyebrow">{{ $achievements->count() }} items</span>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($achievements as $item)
                                    @php $imgUrl = asset('storage/' . $item->image_path); @endphp
                                    <button type="button" data-lightbox="{{ $imgUrl }}" class="tile-img aspect-[4/3] text-left" aria-label="{{ $item->caption ?? 'Achievement' }}">
                                        <img src="{{ $imgUrl }}" alt="" loading="lazy">
                                        <div class="tile-cap">{{ $categoryLabels[$item->category] ?? 'Achievement' }}@if($item->caption) · {{ \Illuminate\Support\Str::limit($item->caption, 40) }}@endif</div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                {{-- With Customers --}}
                @if($withCustomers->count())
                    <section class="card reveal">
                        <div class="card-pad">
                            <div class="flex items-center justify-between">
                                <h2 class="sect-title"><i class="fas fa-users mr-1.5 text-[color:var(--accent-2)]"></i>With Customers</h2>
                                <span class="kbd-eyebrow">{{ $withCustomers->count() }} items</span>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($withCustomers as $item)
                                    @php $imgUrl = asset('storage/' . $item->image_path); @endphp
                                    <button type="button" data-lightbox="{{ $imgUrl }}" class="tile-img aspect-[4/3] text-left">
                                        <img src="{{ $imgUrl }}" alt="" loading="lazy">
                                        <div class="tile-cap">{{ $categoryLabels[$item->category] ?? 'Moment' }}@if($item->customer_consent_confirmed) · Consent ✓@endif</div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                {{-- Work Gallery --}}
                @if($workMoments->count())
                    <section id="gallery" class="card reveal">
                        <div class="card-pad">
                            <div class="flex items-center justify-between">
                                <h2 class="sect-title"><i class="fas fa-images mr-1.5 text-[color:var(--accent-2)]"></i>Work Moments</h2>
                                <span class="kbd-eyebrow">{{ $workMoments->count() }} items</span>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach($workMoments->take(9) as $item)
                                    @php $imgUrl = asset('storage/' . $item->image_path); @endphp
                                    <button type="button" data-lightbox="{{ $imgUrl }}" class="tile-img aspect-[4/3] text-left">
                                        <img src="{{ $imgUrl }}" alt="" loading="lazy">
                                        <div class="tile-cap">{{ $categoryLabels[$item->category] ?? 'Gallery' }}@if($item->caption) · {{ \Illuminate\Support\Str::limit($item->caption, 40) }}@endif</div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
                @endif

                @php
                    $builderPartners = $profile->builders ?? collect();
                    $featuredBuilders = $builderPartners->filter(fn ($b) => (bool) ($b->pivot->is_featured ?? false))->take(4);
                    $marqueeBuilders = $builderPartners->values();
                @endphp

                @if(false && $builderPartners->isNotEmpty())
                    <section id="builder-partners" class="card reveal builder-card">
                        <div class="card-pad">
                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <div>
                                    <span class="gallery-head-eyebrow"><span class="dot"></span> Trusted Partners</span>
                                    <h2 class="sect-title mt-2">
                                        <i class="fas fa-handshake mr-1.5 text-[color:var(--accent-2)]"></i>
                                        Builders I work with
                                    </h2>
                                    <p class="sect-sub mt-1">Verified developer partnerships — delivering trusted homes together.</p>
                                </div>
                                <span class="kbd-eyebrow">{{ $builderPartners->count() }} partner{{ $builderPartners->count() === 1 ? '' : 's' }}</span>
                            </div>

                            @if($featuredBuilders->isNotEmpty())
                                <div class="featured-builders">
                                    @foreach($featuredBuilders as $builder)
                                        <div class="featured-builder" title="{{ $builder->name }}{{ $builder->description ? ' — ' . $builder->description : '' }}">
                                            <div class="featured-builder-logo">
                                                @if($builder->logo_url)
                                                    <img src="{{ $builder->logo_url }}" alt="{{ $builder->name }}" loading="lazy">
                                                @else
                                                    <span class="text-gray-400 text-xs font-bold">{{ strtoupper(substr($builder->name, 0, 2)) }}</span>
                                                @endif
                                            </div>
                                            <div class="featured-builder-name">{{ $builder->name }}</div>
                                            <span class="featured-builder-tag">
                                                <i class="fas fa-star text-[8px]"></i> Featured
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if($marqueeBuilders->count() > 0)
                                <div class="builder-marquee" aria-label="Builder partners">
                                    <div class="builder-marquee-track">
                                        @foreach($marqueeBuilders as $builder)
                                            <div class="builder-logo-item" title="{{ $builder->name }}">
                                                @if($builder->logo_url)
                                                    <img src="{{ $builder->logo_url }}" alt="{{ $builder->name }}" loading="lazy">
                                                @else
                                                    <span class="builder-logo-item-fallback">{{ $builder->name }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                        {{-- Duplicate set for seamless infinite scroll --}}
                                        @foreach($marqueeBuilders as $builder)
                                            <div class="builder-logo-item" aria-hidden="true">
                                                @if($builder->logo_url)
                                                    <img src="{{ $builder->logo_url }}" alt="" loading="lazy">
                                                @else
                                                    <span class="builder-logo-item-fallback">{{ $builder->name }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif
                @if($loanPartners->isNotEmpty())
                    <section id="loan-partners" class="card reveal loan-partner-card">
                        <div class="card-pad">
                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <div>
                                    <span class="gallery-head-eyebrow"><span class="dot"></span> Finance Partners</span>
                                    <h2 class="sect-title mt-2">
                                        <i class="fas fa-building-columns mr-1.5 text-[color:var(--accent-2)]"></i>
                                        Home Loan Available With
                                    </h2>
                                    <p class="sect-sub mt-1">Loan assistance available through leading banks and housing finance partners.</p>
                                </div>
                                <span class="kbd-eyebrow">{{ $loanPartners->count() }} bank{{ $loanPartners->count() === 1 ? '' : 's' }}</span>
                            </div>

                            <div class="loan-partner-marquee" aria-label="Loan partners">
                                <div class="loan-partner-track">
                                    @foreach($loanPartners as $bank)
                                        <div class="loan-partner-item" title="{{ $bank->name }}{{ $bank->short_offer_text ? ' — ' . $bank->short_offer_text : '' }}">
                                            <div>
                                                <div class="loan-partner-logo">
                                                    @if($bank->logo_url)
                                                        <img src="{{ $bank->logo_url }}" alt="{{ $bank->name }}" loading="lazy">
                                                    @else
                                                        <span class="builder-logo-item-fallback">{{ $bank->name }}</span>
                                                    @endif
                                                </div>
                                                <div class="loan-partner-name">{{ $bank->name }}</div>
                                                <div class="loan-partner-note">{{ $bank->short_offer_text ?: 'Home Loan Available' }}</div>
                                            </div>
                                            <span class="loan-partner-meta"><i class="fas fa-check-circle text-[8px]"></i> {{ $bank->interest_rate_text ?: 'Partner' }}</span>
                                        </div>
                                    @endforeach
                                    @foreach($loanPartners as $bank)
                                        <div class="loan-partner-item" aria-hidden="true">
                                            <div>
                                                <div class="loan-partner-logo">
                                                    @if($bank->logo_url)
                                                        <img src="{{ $bank->logo_url }}" alt="" loading="lazy">
                                                    @else
                                                        <span class="builder-logo-item-fallback">{{ $bank->name }}</span>
                                                    @endif
                                                </div>
                                                <div class="loan-partner-name">{{ $bank->name }}</div>
                                                <div class="loan-partner-note">{{ $bank->short_offer_text ?: 'Home Loan Available' }}</div>
                                            </div>
                                            <span class="loan-partner-meta"><i class="fas fa-check-circle text-[8px]"></i> {{ $bank->interest_rate_text ?: 'Partner' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="loan-partner-actions">
                                <p class="loan-partner-disclaimer">Offers subject to bank policy and approval. Final eligibility, ROI, and processing depend on bank review and applicant profile.</p>
                                @if($profile->user->phone)
                                    <a href="tel:{{ $profile->user->phone }}" class="btn btn-primary">
                                        <i class="fas fa-phone"></i> Request Callback
                                    </a>
                                @endif
                            </div>
                        </div>
                    </section>
                @endif

                @if($gallerySlides->isNotEmpty())
                    @php $firstSlide = $gallerySlides->first(); @endphp
                    <section id="gallery-media" class="card reveal gallery-card">
                        <div class="card-pad">
                            <div class="flex items-start justify-between gap-3 flex-wrap">
                                <div class="min-w-0">
                                    <span class="gallery-head-eyebrow"><span class="dot"></span> Showcase</span>
                                    <h2 class="sect-title mt-2">
                                        <i class="fas fa-images mr-1.5 text-[color:var(--accent-2)]"></i>
                                        <span>A journey of</span>
                                        <span class="gallery-typer" data-gallery-typer aria-live="polite"></span>
                                    </h2>
                                    <p class="sect-sub mt-1">Handpicked highlights — curated moments that tell the story.</p>
                                </div>
                                <div class="flex items-center gap-3 flex-shrink-0">
                                    <div class="media-dots" data-media-dots aria-label="Slide navigation">
                                        @foreach($gallerySlides as $index => $slide)
                                            <button type="button" class="media-dot {{ $index === 0 ? 'active' : '' }}" data-media-dot data-index="{{ $index }}" aria-label="Go to slide {{ $index + 1 }}"></button>
                                        @endforeach
                                    </div>
                                    <span class="kbd-eyebrow">{{ $gallerySlides->count() }} items</span>
                                </div>
                            </div>

                            <div class="media-showcase mt-4 relative" data-media-showcase>
                                <div class="media-stage-wrap relative">
                                    <span class="media-zoom-hint"><i class="fas fa-expand text-[10px]"></i> Click to zoom</span>
                                    <div class="media-counter">
                                        <b data-media-current>01</b><span class="slash">/</span><span>{{ str_pad($gallerySlides->count(), 2, '0', STR_PAD_LEFT) }}</span>
                                    </div>

                                    <button
                                        type="button"
                                        class="media-stage"
                                        data-media-stage
                                        data-lightbox="{{ $firstSlide['image'] }}"
                                        aria-label="{{ $firstSlide['title'] }}"
                                    >
                                        <img src="{{ $firstSlide['image'] }}" alt="{{ $firstSlide['title'] }}" class="media-stage-image" data-media-stage-image loading="lazy">
                                        <div class="media-stage-overlay">
                                            <span class="media-stage-tag" data-media-stage-tag>{{ $firstSlide['tag'] }}</span>
                                            <div class="media-stage-title">
                                                <span class="media-stage-title-text" data-media-stage-title>{{ $firstSlide['title'] }}</span><span class="media-stage-title-caret" data-media-stage-caret></span>
                                            </div>
                                            <div class="media-stage-meta" data-media-stage-meta>{{ $firstSlide['meta'] }}</div>
                                        </div>
                                        <div class="media-progress">
                                            <div class="media-progress-bar" data-media-progress></div>
                                        </div>
                                    </button>

                                    @if($gallerySlides->count() > 1)
                                        <button type="button" class="media-nav media-nav-prev" data-media-prev aria-label="Previous slide">
                                            <i class="fas fa-chevron-left text-sm"></i>
                                        </button>
                                        <button type="button" class="media-nav media-nav-next" data-media-next aria-label="Next slide">
                                            <i class="fas fa-chevron-right text-sm"></i>
                                        </button>
                                    @endif
                                </div>

                                <div class="media-thumbs" data-media-thumbs>
                                    @foreach($gallerySlides as $index => $slide)
                                        <button
                                            type="button"
                                            class="media-thumb {{ $index === 0 ? 'active' : '' }}"
                                            data-media-thumb
                                            data-index="{{ $index }}"
                                            data-image="{{ $slide['image'] }}"
                                            data-tag="{{ $slide['tag'] }}"
                                            data-title="{{ $slide['title'] }}"
                                            data-meta="{{ $slide['meta'] }}"
                                            aria-label="{{ $slide['title'] }}"
                                        >
                                            <img src="{{ $slide['image'] }}" alt="{{ $slide['title'] }}" loading="lazy">
                                            <div class="media-thumb-copy">
                                                <span class="media-thumb-tag">{{ $slide['tag'] }}</span>
                                                <div class="media-thumb-title line-clamp-2">{{ $slide['title'] }}</div>
                                                <div class="media-thumb-meta">{{ $slide['meta'] }}</div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </section>
                @endif
            </div>

            {{-- RIGHT: sidebar --}}
            <aside class="space-y-5 lg:sticky lg:top-[68px] lg:self-start">

                {{-- Quick Info card --}}
                <section class="card reveal">
                    <div class="card-pad">
                        <h2 class="sect-title">Quick Info</h2>
                        <div class="mt-2">
                            <div class="info-row">
                                <span class="k">Role</span>
                                <span class="v">{{ $profile->designation ?: $profile->user->getDisplayRoleName() }}</span>
                            </div>
                            @if($profile->experience_years)
                                <div class="info-row">
                                    <span class="k">Real estate experience</span>
                                    <span class="v">{{ $profile->experience_years }}+ years</span>
                                </div>
                            @endif
                            <div class="info-row">
                                <span class="k">With company</span>
                                <span class="v">{{ $profile->company_tenure_label }}</span>
                            </div>
                            @if(!empty($profile->service_areas))
                                <div class="info-row">
                                    <span class="k">Service areas</span>
                                    <span class="v">{{ \Illuminate\Support\Str::limit(implode(' · ', $profile->service_areas), 50) }}</span>
                                </div>
                            @endif
                            @if(!empty($profile->languages))
                                <div class="info-row">
                                    <span class="k">Languages</span>
                                    <span class="v">{{ implode(', ', $profile->languages) }}</span>
                                </div>
                            @endif
                            @if($ratingAvg)
                                <div class="info-row">
                                    <span class="k">Rating</span>
                                    <span class="v">{{ $ratingAvg }} / 5 · {{ $ratingCount }}</span>
                                </div>
                            @endif
                            <div class="info-row">
                                <span class="k">Status</span>
                                <span class="v inline-flex items-center gap-1.5 text-[color:var(--accent-2)]">
                                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span> Active
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Tags card --}}
                @if(!empty($profile->specialization_tags))
                    <section class="card reveal">
                        <div class="card-pad">
                            <h2 class="sect-title">Specializations</h2>
                            <div class="mt-2.5 flex flex-wrap gap-1.5">
                                @foreach($profile->specialization_tags as $tag)
                                    <span class="pill">{{ $tag }}</span>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                {{-- Contact / CTA card --}}
                <section class="card reveal overflow-hidden" style="background: linear-gradient(155deg,#0b2e24 0%, #1a4a3a 55%, #2e7357 100%); color:#fff; border-color: transparent;">
                    <div class="card-pad">
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white/90 ring-1 ring-white/15">
                            <i class="fas fa-shield-halved"></i> Professional Support
                        </div>
                        <h2 class="mt-2.5 text-lg font-bold">Connect with {{ $firstName }}</h2>
                        <p class="mt-1.5 text-[0.83rem] leading-6 text-white/80">Site visit, shortlisting, ya final booking — transparent guidance milegi.</p>
                        <div class="mt-4 grid gap-2">
                        @if($profile->user->phone)
                            <a href="tel:{{ $profile->user->phone }}" class="btn btn-sand w-full"><i class="fas fa-phone"></i> Request Callback</a>
                        @endif
                        @if($profile->user->phone || $profile->user->email)
                            <button type="button" data-contact-card="{{ route('advisor.public.contact', $profile->public_slug) }}" class="btn w-full text-white ring-1 ring-white/20 bg-white/10 hover:bg-white/15"><i class="fas fa-address-book"></i> Add to Contacts</button>
                        @endif
                        @if($canLeaveReview)
                            <a href="{{ route('advisor.public.review.show', $profile->public_slug) }}" class="btn w-full text-white ring-1 ring-white/20 bg-white/10 hover:bg-white/15"><i class="fas fa-star"></i> Leave a Review</a>
                        @else
                                <span class="btn w-full cursor-default text-white ring-1 ring-white/20 bg-white/10 opacity-70"><i class="fas fa-eye"></i> Preview Only</span>
                            @endif
                        </div>
                        <div class="mt-3 flex items-center gap-2 text-[11px] text-white/70">
                            <i class="fas fa-lock"></i> Verified via {{ brand_name() }}
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </main>

    <footer class="border-t border-[color:var(--line)] bg-white">
        <div class="shell flex flex-col items-center justify-between gap-2 py-4 text-[11px] text-[color:var(--muted)] sm:flex-row">
            <div class="inline-flex items-center gap-1.5">
                <i class="fas fa-shield-halved text-[color:var(--accent-2)]"></i>
                Verified advisor profile · {{ brand_name() }}
            </div>
            <a href="#top" class="font-semibold text-[color:var(--ink)] hover:underline">Back to top ↑</a>
        </div>
    </footer>

    {{-- Mobile sticky actions --}}
    <div class="fixed inset-x-0 bottom-0 z-50 border-t border-[color:var(--line)] bg-white/95 py-2.5 shadow-[0_-8px_24px_rgba(15,51,41,0.08)] backdrop-blur-md lg:hidden pb-safe">
        <div class="shell flex gap-2">
            @if($profile->user->phone)
                <a href="tel:{{ $profile->user->phone }}" class="btn btn-primary flex-1"><i class="fas fa-phone"></i> Call Now</a>
            @endif
            @if($profile->user->phone || $profile->user->email)
                <button type="button" data-contact-card="{{ route('advisor.public.contact', $profile->public_slug) }}" class="btn btn-sand flex-1"><i class="fas fa-address-book"></i> Add to Contacts</button>
            @elseif($canLeaveReview)
                <a href="{{ route('advisor.public.review.show', $profile->public_slug) }}" class="btn btn-sand flex-1"><i class="fas fa-star"></i> Review</a>
            @else
                <span class="btn btn-sand flex-1 cursor-default opacity-70"><i class="fas fa-eye"></i> Preview</span>
            @endif
        </div>
    </div>

    <div id="lightbox" class="lightbox" role="dialog" aria-modal="true" aria-label="Image preview">
        <div class="lightbox-inner" onclick="event.stopPropagation()">
            <button type="button" class="lightbox-close" id="lightboxClose" aria-label="Close">&times;</button>
            <img id="lightboxImg" src="" alt="">
        </div>
    </div>

    <div id="videoModal" class="video-modal" role="dialog" aria-modal="true" aria-label="Video testimonial">
        <div class="video-modal-inner" onclick="event.stopPropagation()">
            <button type="button" class="video-modal-close" id="videoModalClose" aria-label="Close">&times;</button>
            <iframe id="videoModalFrame" src="" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
        </div>
    </div>

    <div id="contactModal" class="contact-modal" role="dialog" aria-modal="true" aria-label="Add to contacts">
        <div class="contact-modal-card" onclick="event.stopPropagation()">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-lg font-bold text-[color:var(--ink)]">Add to Contacts</div>
                    <div id="contactModalSubtext" class="mt-1 text-sm text-[color:var(--muted)]">Preparing contact card...</div>
                </div>
                <button type="button" id="contactModalClose" class="lightbox-close !static !w-9 !h-9 !text-base" aria-label="Close">&times;</button>
            </div>
            <div id="contactProgressWrap" class="mt-4">
                <div class="contact-progress">
                    <div class="contact-progress-bar"></div>
                </div>
            </div>
            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <button type="button" id="contactOpenAgain" class="btn btn-primary w-full hidden"><i class="fas fa-address-book"></i> Open Contact Card</button>
                <button type="button" id="contactDownloadAgain" class="btn btn-ghost w-full hidden"><i class="fas fa-download"></i> Download Again</button>
            </div>
        </div>
    </div>

    <script>
        (function() {
            document.querySelectorAll('.reveal').forEach(function(el) {
                if (!('IntersectionObserver' in window)) { el.classList.add('is-visible'); return; }
                var io = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); io.unobserve(entry.target); }
                    });
                }, { threshold: 0.06, rootMargin: '0px 0px -30px 0px' });
                io.observe(el);
            });
            var lb = document.getElementById('lightbox');
            var lbImg = document.getElementById('lightboxImg');
            function closeLb() {
                lb.classList.remove('active');
                lbImg.removeAttribute('src');
                document.body.style.overflow = '';
            }
            document.querySelectorAll('[data-lightbox]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    lbImg.src = btn.getAttribute('data-lightbox');
                    lb.classList.add('active');
                    document.body.style.overflow = 'hidden';
                });
            });
            lb.addEventListener('click', closeLb);
            document.getElementById('lightboxClose').addEventListener('click', closeLb);
            document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLb(); });

            var videoModal = document.getElementById('videoModal');
            var videoModalFrame = document.getElementById('videoModalFrame');
            function closeVideoModal() {
                videoModal.classList.remove('active');
                videoModalFrame.setAttribute('src', '');
                document.body.style.overflow = '';
            }
            document.querySelectorAll('[data-video-embed]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var embedUrl = btn.getAttribute('data-video-embed');
                    if (!embedUrl) {
                        return;
                    }
                    videoModalFrame.setAttribute('src', embedUrl + (embedUrl.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1');
                    videoModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                });
            });
            videoModal.addEventListener('click', closeVideoModal);
            document.getElementById('videoModalClose').addEventListener('click', closeVideoModal);

            var contactModal = document.getElementById('contactModal');
            var contactModalSubtext = document.getElementById('contactModalSubtext');
            var contactProgressWrap = document.getElementById('contactProgressWrap');
            var contactOpenAgain = document.getElementById('contactOpenAgain');
            var contactDownloadAgain = document.getElementById('contactDownloadAgain');
            var contactOpenTimer = null;
            var activeContactUrl = null;

            function triggerContactCard(url) {
                if (!url) {
                    return;
                }

                var link = document.createElement('a');
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            function closeContactModal() {
                if (contactOpenTimer) {
                    clearTimeout(contactOpenTimer);
                    contactOpenTimer = null;
                }

                contactModal.classList.remove('active');
                contactModalSubtext.textContent = 'Preparing contact card...';
                contactProgressWrap.classList.remove('hidden');
                contactOpenAgain.classList.add('hidden');
                contactDownloadAgain.classList.add('hidden');
                document.body.style.overflow = '';
            }

            document.querySelectorAll('[data-contact-card]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    activeContactUrl = btn.getAttribute('data-contact-card');
                    contactModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    contactModalSubtext.textContent = 'Preparing contact card...';
                    contactProgressWrap.classList.remove('hidden');
                    contactOpenAgain.classList.add('hidden');
                    contactDownloadAgain.classList.add('hidden');

                    if (contactOpenTimer) {
                        clearTimeout(contactOpenTimer);
                    }

                    contactOpenTimer = setTimeout(function() {
                        triggerContactCard(activeContactUrl);
                        contactModalSubtext.textContent = 'Opening contact card...';
                    }, 450);

                    setTimeout(function() {
                        if (!contactModal.classList.contains('active')) {
                            return;
                        }
                        contactModalSubtext.textContent = 'Contact card ready';
                        contactProgressWrap.classList.add('hidden');
                        contactOpenAgain.classList.remove('hidden');
                        contactDownloadAgain.classList.remove('hidden');
                    }, 1300);
                });
            });

            contactOpenAgain.addEventListener('click', function() {
                triggerContactCard(activeContactUrl);
            });
            contactDownloadAgain.addEventListener('click', function() {
                triggerContactCard(activeContactUrl);
            });
            contactModal.addEventListener('click', closeContactModal);
            document.getElementById('contactModalClose').addEventListener('click', closeContactModal);

            function enhanceFeaturedVideoHero() {
                if (window.innerWidth < 1024) {
                    return;
                }

                document.querySelectorAll('.video-hero').forEach(function(hero) {
                    if (hero.querySelector('.video-hero-desktop')) {
                        return;
                    }

                    var mobileTrigger = hero.querySelector('.video-thumb[data-video-embed]');
                    if (!mobileTrigger) {
                        return;
                    }

                    var embedUrl = mobileTrigger.getAttribute('data-video-embed');
                    if (!embedUrl) {
                        return;
                    }

                    var heroDesktop = document.createElement('div');
                    heroDesktop.className = 'video-hero-desktop';

                    var player = document.createElement('div');
                    player.className = 'video-hero-player';

                    var iframe = document.createElement('iframe');
                    iframe.src = embedUrl + (embedUrl.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1&mute=1&playsinline=1&rel=0&controls=0&iv_load_policy=3&disablekb=1';
                    iframe.title = 'Featured video testimonial';
                    iframe.allow = 'autoplay; encrypted-media; picture-in-picture';
                    iframe.setAttribute('allowfullscreen', 'allowfullscreen');
                    player.appendChild(iframe);

                    var heroMeta = hero.querySelector('.video-hero-meta');
                    var copy = document.createElement('div');
                    copy.className = 'video-hero-copy';
                    if (heroMeta) {
                        copy.innerHTML = heroMeta.innerHTML;
                    }

                    heroDesktop.appendChild(player);
                    heroDesktop.appendChild(copy);
                    hero.appendChild(heroDesktop);
                    hero.classList.add('is-desktop-enhanced');
                });
            }

            enhanceFeaturedVideoHero();

            function initMediaShowcase() {
                var showcase = document.querySelector('[data-media-showcase]');
                if (!showcase) {
                    return;
                }

                var stage = showcase.querySelector('[data-media-stage]');
                var stageImage = showcase.querySelector('[data-media-stage-image]');
                var stageTag = showcase.querySelector('[data-media-stage-tag]');
                var stageTitle = showcase.querySelector('[data-media-stage-title]');
                var stageMeta = showcase.querySelector('[data-media-stage-meta]');
                var stageCaret = showcase.querySelector('[data-media-stage-caret]');
                var progressBar = showcase.querySelector('[data-media-progress]');
                var counterEl = showcase.querySelector('[data-media-current]');
                var prevBtn = showcase.querySelector('[data-media-prev]');
                var nextBtn = showcase.querySelector('[data-media-next]');
                var dots = Array.prototype.slice.call(showcase.parentElement.querySelectorAll('[data-media-dot]'));
                var thumbs = Array.prototype.slice.call(showcase.querySelectorAll('[data-media-thumb]'));

                if (!stage || !stageImage || !thumbs.length) {
                    return;
                }

                var activeIndex = thumbs.findIndex(function(thumb) { return thumb.classList.contains('active'); });
                if (activeIndex < 0) activeIndex = 0;

                var SLIDE_MS = 5000;
                var autoTimer = null;
                var progressTimer = null;
                var progressStart = 0;
                var pauseUntil = 0;
                var titleTypeTimer = null;
                var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                function pad2(n) { return (n < 10 ? '0' : '') + n; }

                function typeStageTitle(text) {
                    if (!stageTitle) return;
                    if (titleTypeTimer) window.clearInterval(titleTypeTimer);

                    if (prefersReducedMotion) {
                        stageTitle.textContent = text;
                        return;
                    }

                    stageTitle.textContent = '';
                    var i = 0;
                    var str = String(text || '');
                    titleTypeTimer = window.setInterval(function() {
                        stageTitle.textContent = str.slice(0, ++i);
                        if (i >= str.length) {
                            window.clearInterval(titleTypeTimer);
                            titleTypeTimer = null;
                        }
                    }, 22);
                }

                function applySlide(index) {
                    var thumb = thumbs[index];
                    if (!thumb) return;

                    stage.classList.add('is-transitioning');
                    stage.setAttribute('data-lightbox', thumb.getAttribute('data-image') || '');
                    stage.setAttribute('aria-label', thumb.getAttribute('data-title') || 'Gallery image');

                    window.setTimeout(function() {
                        stageImage.src = thumb.getAttribute('data-image') || '';
                        stageImage.alt = thumb.getAttribute('data-title') || '';
                        if (stageTag) stageTag.textContent = thumb.getAttribute('data-tag') || 'Gallery';
                        if (stageMeta) stageMeta.textContent = thumb.getAttribute('data-meta') || '';

                        typeStageTitle(thumb.getAttribute('data-title') || 'Gallery Highlight');

                        thumbs.forEach(function(item, itemIndex) {
                            item.classList.toggle('active', itemIndex === index);
                        });
                        dots.forEach(function(dot, dotIndex) {
                            dot.classList.toggle('active', dotIndex === index);
                        });
                        if (counterEl) counterEl.textContent = pad2(index + 1);

                        stage.classList.remove('is-transitioning');

                        var activeThumb = thumbs[index];
                        if (activeThumb && typeof activeThumb.scrollIntoView === 'function') {
                            try {
                                activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                            } catch (e) { /* ignore */ }
                        }
                    }, 180);

                    activeIndex = index;
                    resetProgress();
                }

                function goTo(index) {
                    var total = thumbs.length;
                    var next = ((index % total) + total) % total;
                    applySlide(next);
                    scheduleNext();
                }

                function resetProgress() {
                    if (!progressBar) return;
                    progressBar.style.transition = 'none';
                    progressBar.style.width = '0%';
                    progressStart = Date.now();

                    if (progressTimer) window.clearInterval(progressTimer);
                    progressTimer = window.setInterval(function() {
                        if (Date.now() < pauseUntil) {
                            progressStart = Date.now();
                            progressBar.style.width = '0%';
                            return;
                        }
                        var elapsed = Date.now() - progressStart;
                        var pct = Math.min(100, (elapsed / SLIDE_MS) * 100);
                        progressBar.style.transition = 'width .15s linear';
                        progressBar.style.width = pct + '%';
                    }, 80);
                }

                function scheduleNext() {
                    if (autoTimer) window.clearTimeout(autoTimer);
                    autoTimer = window.setTimeout(function tick() {
                        if (Date.now() < pauseUntil) {
                            autoTimer = window.setTimeout(tick, 600);
                            return;
                        }
                        applySlide((activeIndex + 1) % thumbs.length);
                        scheduleNext();
                    }, SLIDE_MS);
                }

                thumbs.forEach(function(thumb, index) {
                    thumb.addEventListener('click', function() {
                        pauseUntil = Date.now() + 7000;
                        goTo(index);
                    });
                });

                dots.forEach(function(dot, index) {
                    dot.addEventListener('click', function() {
                        pauseUntil = Date.now() + 7000;
                        goTo(index);
                    });
                });

                if (prevBtn) {
                    prevBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        pauseUntil = Date.now() + 7000;
                        goTo(activeIndex - 1);
                    });
                }
                if (nextBtn) {
                    nextBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        pauseUntil = Date.now() + 7000;
                        goTo(activeIndex + 1);
                    });
                }

                document.addEventListener('keydown', function(e) {
                    if (!showcase.getBoundingClientRect) return;
                    var rect = showcase.getBoundingClientRect();
                    var visible = rect.bottom > 0 && rect.top < window.innerHeight;
                    if (!visible) return;
                    if (e.key === 'ArrowLeft') { pauseUntil = Date.now() + 7000; goTo(activeIndex - 1); }
                    if (e.key === 'ArrowRight') { pauseUntil = Date.now() + 7000; goTo(activeIndex + 1); }
                });

                showcase.addEventListener('mouseenter', function() { pauseUntil = Date.now() + 100000; });
                showcase.addEventListener('mouseleave', function() { pauseUntil = Date.now() + 1200; });
                showcase.addEventListener('touchstart', function() { pauseUntil = Date.now() + 7000; }, { passive: true });

                var touchStartX = 0;
                stage.addEventListener('touchstart', function(e) {
                    if (e.touches && e.touches[0]) touchStartX = e.touches[0].clientX;
                }, { passive: true });
                stage.addEventListener('touchend', function(e) {
                    if (!e.changedTouches || !e.changedTouches[0]) return;
                    var dx = e.changedTouches[0].clientX - touchStartX;
                    if (Math.abs(dx) > 40) {
                        pauseUntil = Date.now() + 7000;
                        goTo(activeIndex + (dx < 0 ? 1 : -1));
                    }
                }, { passive: true });

                typeStageTitle(thumbs[activeIndex].getAttribute('data-title') || 'Gallery Highlight');
                if (counterEl) counterEl.textContent = pad2(activeIndex + 1);
                resetProgress();
                scheduleNext();
            }

            function initGalleryTyper() {
                var el = document.querySelector('[data-gallery-typer]');
                if (!el) return;
                var tagsFromSlides = Array.prototype.slice.call(
                    document.querySelectorAll('[data-media-thumb]')
                ).map(function(t) { return (t.getAttribute('data-tag') || '').trim(); })
                .filter(function(v, i, a) { return v && a.indexOf(v) === i; });

                var words = tagsFromSlides.length
                    ? tagsFromSlides.concat(['Milestones', 'Moments'])
                    : ['Milestones', 'Moments', 'Achievements', 'Stories'];

                var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (prefersReduced) {
                    el.textContent = words[0];
                    return;
                }

                var wi = 0, ci = 0, deleting = false;

                function tick() {
                    var word = words[wi];
                    if (!deleting) {
                        ci++;
                        el.textContent = word.slice(0, ci);
                        if (ci === word.length) {
                            deleting = true;
                            return window.setTimeout(tick, 1400);
                        }
                        return window.setTimeout(tick, 80 + Math.random() * 40);
                    } else {
                        ci--;
                        el.textContent = word.slice(0, ci);
                        if (ci === 0) {
                            deleting = false;
                            wi = (wi + 1) % words.length;
                            return window.setTimeout(tick, 260);
                        }
                        return window.setTimeout(tick, 40);
                    }
                }

                tick();
            }

            initMediaShowcase();
            initGalleryTyper();

            document.querySelectorAll('[data-share]').forEach(function(btn) {
                btn.addEventListener('click', async function() {
                    try {
                        if (navigator.share) {
                            await navigator.share({ title: btn.getAttribute('data-share-title'), text: btn.getAttribute('data-share-text'), url: location.href });
                        } else {
                            await navigator.clipboard.writeText(location.href);
                            var o = btn.innerHTML;
                            btn.innerHTML = '<i class="fas fa-check"></i> Copied';
                            setTimeout(function() { btn.innerHTML = o; }, 1500);
                        }
                    } catch (e) {}
                });
            });

            var toggleReviewsButton = document.querySelector('[data-toggle-reviews]');
            if (toggleReviewsButton) {
                toggleReviewsButton.addEventListener('click', function() {
                    var expanded = toggleReviewsButton.getAttribute('data-expanded') === 'true';
                    document.querySelectorAll('[data-extra-review]').forEach(function(card) {
                        card.classList.toggle('hidden', expanded);
                    });
                    toggleReviewsButton.setAttribute('data-expanded', expanded ? 'false' : 'true');
                    toggleReviewsButton.innerHTML = expanded
                        ? 'View All Reviews <i class="fas fa-arrow-right text-xs"></i>'
                        : 'Show Less <i class="fas fa-arrow-up text-xs"></i>';
                });
            }

            var statsStrip = document.querySelector('[data-stats-strip]');
            if (statsStrip && window.matchMedia('(max-width: 1023px)').matches) {
                var direction = 1;
                var step = 1;
                var maxScrollLeft = Math.max(0, statsStrip.scrollWidth - statsStrip.clientWidth);
                var pauseUntil = 0;

                function updateBounds() {
                    maxScrollLeft = Math.max(0, statsStrip.scrollWidth - statsStrip.clientWidth);
                    if (statsStrip.scrollLeft > maxScrollLeft) {
                        statsStrip.scrollLeft = maxScrollLeft;
                    }
                }

                function tick() {
                    if (window.innerWidth >= 1024 || maxScrollLeft <= 0) {
                        return;
                    }

                    var now = Date.now();
                    if (now < pauseUntil) {
                        requestAnimationFrame(tick);
                        return;
                    }

                    statsStrip.scrollLeft += step * direction;

                    if (statsStrip.scrollLeft >= maxScrollLeft) {
                        statsStrip.scrollLeft = maxScrollLeft;
                        direction = -1;
                        pauseUntil = now + 900;
                    } else if (statsStrip.scrollLeft <= 0) {
                        statsStrip.scrollLeft = 0;
                        direction = 1;
                        pauseUntil = now + 900;
                    }

                    requestAnimationFrame(tick);
                }

                ['touchstart', 'pointerdown', 'wheel'].forEach(function(eventName) {
                    statsStrip.addEventListener(eventName, function() {
                        pauseUntil = Date.now() + 2500;
                    }, { passive: true });
                });

                window.addEventListener('resize', updateBounds, { passive: true });
                updateBounds();
                requestAnimationFrame(tick);
            }
        })();
    </script>
</body>
</html>
