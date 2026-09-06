<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $publicPage?->hero_title ?: $project->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-950: #063a1c;
            --green-900: #0d4a29;
            --green-800: #205a44;
            --green-700: #2d6a50;
            --green-soft: #e8f3ec;
            --green-wash: #f5fbf7;
            --cream: #f7f4ee;
            --border: #d8e8dd;
            --text-900: #173126;
            --text-700: #5d7268;
            --white: #ffffff;
            --shadow: 0 16px 32px rgba(8, 52, 28, 0.05);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: "Instrument Sans", system-ui, sans-serif;
            color: var(--text-900);
            background: linear-gradient(180deg, #fbfcfb 0%, #f1f6f2 100%);
            overflow-x: hidden;
        }

        /* ── Premium Animations ── */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(32px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50%       { transform: translateY(-10px) rotate(1deg); }
        }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 16px 28px rgba(8, 52, 28, 0.18); }
            50%       { box-shadow: 0 20px 40px rgba(8, 52, 28, 0.32); }
        }

        @keyframes shimmer-btn {
            0%   { background-position: -200% center; }
            100% { background-position: 200% center; }
        }

        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.94); }
            to   { opacity: 1; transform: scale(1); }
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(24px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        @keyframes border-glow {
            0%, 100% { border-color: rgba(32, 90, 68, 0.12); }
            50%       { border-color: rgba(32, 90, 68, 0.35); }
        }

        @keyframes price-tick {
            0%   { transform: scale(1); }
            40%  { transform: scale(1.07); }
            100% { transform: scale(1); }
        }

        .wf-hero { padding: 14px 0 18px; }
        .animate-in {
            animation: fadeSlideUp 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .animate-in-delay-1 { animation-delay: 0.1s; }
        .animate-in-delay-2 { animation-delay: 0.2s; }
        .animate-in-delay-3 { animation-delay: 0.32s; }
        .animate-in-delay-4 { animation-delay: 0.44s; }
        .animate-in-delay-5 { animation-delay: 0.56s; }
        .animate-in-delay-6 { animation-delay: 0.68s; }
        .animate-in-delay-7 { animation-delay: 0.80s; }

        .section-card { animation: none; }

        /* ── Floating decorative orb (hero) ── */
        .wf-stage {
            animation: none;
        }

        /* ── Premium Button Styles ── */
        .btn {
            border: 0;
            cursor: pointer;
            border-radius: 999px;
            padding: 14px 18px;
            font: inherit;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1),
                        box-shadow 0.25s ease,
                        background 0.25s ease;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.01em;
            line-height: 1.1;
            text-decoration: none;
        }

        .btn:link,
        .btn:visited,
        .btn:hover,
        .btn:active {
            text-decoration: none;
        }

        .btn::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                105deg,
                transparent 40%,
                rgba(255, 255, 255, 0.22) 50%,
                transparent 60%
            );
            background-size: 300% 100%;
            background-position: -100% center;
            transition: background-position 0.5s ease;
            pointer-events: none;
        }

        .btn:hover::before {
            background-position: 200% center;
        }

        .btn-primary {
            color: #ffffff;
            background: linear-gradient(135deg, var(--green-800), var(--green-950));
            box-shadow: 0 12px 24px rgba(8, 52, 28, 0.14);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 28px rgba(8, 52, 28, 0.18);
        }

        .btn-primary:active {
            transform: translateY(-1px) scale(0.99);
            box-shadow: 0 12px 24px rgba(8, 52, 28, 0.20);
        }

        .btn-secondary {
            color: var(--green-900);
            background: var(--white);
            border: 1px solid var(--border);
            transition: transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1),
                        box-shadow 0.22s ease,
                        border-color 0.22s ease,
                        background 0.22s ease;
        }

        .btn-secondary:hover {
            transform: translateY(-1px);
            border-color: rgba(13, 74, 41, 0.40);
            box-shadow: 0 10px 22px rgba(8, 52, 28, 0.10);
            background: #f8fdf9;
        }

        .btn-secondary::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: inherit;
            opacity: 0;
            box-shadow: 0 0 0 0 rgba(13, 74, 41, 0.12);
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .btn-secondary:hover::after {
            opacity: 1;
        }

        .section-jump-wrap {
            padding: 0 0 18px;
        }

        .section-jump {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 18px 22px;
            border-radius: 24px;
            border: 1px solid rgba(32, 90, 68, 0.1);
            background: rgba(255, 255, 255, 0.82);
            box-shadow: var(--shadow);
        }

        .section-jump a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 16px;
            border-radius: 999px;
            border: 1px solid rgba(32, 90, 68, 0.12);
            background: #fff;
            color: var(--text-900);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .section-jump a:hover {
            border-color: rgba(13, 74, 41, 0.3);
            background: #f8fdf9;
        }

        .section-intro-card {
            padding: 28px;
            border-radius: 28px;
            border: 1px solid rgba(32, 90, 68, 0.08);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.94), rgba(245, 251, 247, 0.98));
            box-shadow: var(--shadow);
        }

        .section-intro-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green-900);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .section-intro-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(320px, 0.9fr);
            gap: 22px;
            align-items: start;
        }

        .section-intro-copy h2 {
            margin: 0 0 12px;
            font-size: 30px;
            line-height: 1.08;
        }

        .section-intro-copy p {
            margin: 0;
            color: var(--text-700);
            line-height: 1.7;
        }

        .section-badge-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .section-badge-grid span {
            display: inline-flex;
            align-items: center;
            min-height: 38px;
            padding: 10px 14px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid rgba(32, 90, 68, 0.12);
            color: var(--green-900);
            font-size: 13px;
            font-weight: 700;
        }

        .tower-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 14px;
        }

        .tower-detail-card {
            border: 1px solid rgba(32, 90, 68, 0.12);
            border-radius: 20px;
            background: #fff;
            padding: 16px;
            box-shadow: 0 14px 34px rgba(15, 82, 52, 0.06);
        }

        .tower-detail-name {
            margin: 0 0 12px;
            color: #0f2d22;
            font-size: 17px;
            font-weight: 900;
        }

        .tower-detail-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .tower-detail-metric {
            border: 1px solid rgba(32, 90, 68, 0.10);
            border-radius: 14px;
            background: #f6faf7;
            padding: 10px;
        }

        .tower-detail-metric span {
            display: block;
            color: #687970;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .tower-detail-metric strong {
            display: block;
            margin-top: 4px;
            color: #063f2a;
            font-size: 21px;
            line-height: 1;
        }

        .highlights-list {
            display: grid;
            gap: 12px;
        }

        .highlights-list article {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 15px 16px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(32, 90, 68, 0.08);
        }

        .highlights-list article strong {
            display: inline-flex;
            flex: 0 0 28px;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green-900);
            font-size: 12px;
        }

        .gallery-preview-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .price-list-shell {
            border: 1px solid rgba(187, 35, 44, 0.12);
            border-radius: 28px;
            overflow: hidden;
            background: #fff;
            box-shadow: var(--shadow);
            max-width: 100%;
        }

        .price-list-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 22px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
            background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(247,250,248,0.92));
        }

        .price-list-toolbar h3 {
            margin: 0;
            font-size: 24px;
            line-height: 1.2;
            color: #102018;
        }

        .price-list-toolbar p {
            margin: 8px 0 0;
            color: var(--text-700);
            font-size: 14px;
        }

        .price-list-table-wrap {
            overflow-x: auto;
        }

        .price-list-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 840px;
        }

        .price-list-table thead th {
            padding: 15px 18px;
            background: linear-gradient(135deg, #c62828, #e53935);
            color: #fff;
            font-size: 13px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            text-align: left;
            white-space: nowrap;
        }

        .price-list-table tbody td {
            padding: 16px 18px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
            vertical-align: middle;
        }

        .price-list-table tbody tr:last-child td {
            border-bottom: none;
        }

        .price-list-main {
            display: grid;
            gap: 4px;
        }

        .price-list-main strong {
            font-size: 15px;
            color: #102018;
        }

        .price-list-subtle {
            font-size: 12px;
            color: var(--text-700);
        }

        .price-list-price {
            font-size: 16px;
            font-weight: 700;
            color: #102018;
        }

        .price-list-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .price-list-status--value {
            color: #117a4c;
            background: #eefcf4;
            border-color: rgba(17, 122, 76, 0.18);
        }

        .price-list-status--popular {
            color: #b54708;
            background: #fff4e5;
            border-color: rgba(181, 71, 8, 0.18);
        }

        .price-list-status--available {
            color: #2457c5;
            background: #eef4ff;
            border-color: rgba(36, 87, 197, 0.18);
        }

        .price-list-status--hold {
            color: #7c3aed;
            background: #f4efff;
            border-color: rgba(124, 58, 237, 0.18);
        }

        .price-list-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 96px;
            padding: 10px 16px;
            border-radius: 14px;
            background: linear-gradient(135deg, #c62828, #e53935);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 14px 28px rgba(198, 40, 40, 0.2);
        }

        .price-list-foot {
            display: grid;
            gap: 14px;
            padding: 18px 22px 22px;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
            background: linear-gradient(180deg, rgba(250,252,251,0.92), rgba(255,255,255,0.98));
        }

        .price-list-pills {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .price-list-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            border-radius: 14px;
            border: 1px solid rgba(32, 90, 68, 0.12);
            background: #fff;
            color: #102018;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            padding: 10px 12px;
        }

        .price-list-footnote {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            color: var(--text-700);
            font-size: 13px;
        }

        .price-list-footnote strong {
            color: #c62828;
            font-size: 14px;
        }

        .gallery-preview-card {
            display: block;
            overflow: hidden;
            border-radius: 22px;
            background: #fff;
            border: 1px solid rgba(32, 90, 68, 0.08);
            text-decoration: none;
            box-shadow: var(--shadow);
        }

        .gallery-preview-card img {
            display: block;
            width: 100%;
            aspect-ratio: 1.08;
            object-fit: cover;
        }

        .gallery-preview-card div {
            padding: 12px 14px 14px;
        }

        .gallery-preview-card span {
            display: block;
            color: var(--text-700);
            font-size: 13px;
            line-height: 1.5;
        }

        .amenity-preview-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .amenity-preview-card {
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid rgba(32, 90, 68, 0.08);
            background: #fff;
            box-shadow: var(--shadow);
        }

        .amenity-preview-card img {
            display: block;
            width: 100%;
            aspect-ratio: 1.18;
            object-fit: cover;
        }

        .amenity-preview-card div {
            padding: 14px 16px 16px;
        }

        .amenity-preview-card strong,
        .gallery-preview-card strong {
            display: block;
            margin-bottom: 6px;
            font-size: 16px;
        }

        .cta-band {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(300px, 0.9fr);
            gap: 22px;
            align-items: start;
            padding: 26px;
            border-radius: 28px;
            background: linear-gradient(135deg, rgba(13, 74, 41, 0.96), rgba(32, 90, 68, 0.94));
            color: #fff;
            box-shadow: 0 24px 40px rgba(8, 52, 28, 0.14);
        }

        .cta-band h2,
        .cta-band h3 {
            margin: 0 0 10px;
            color: #fff;
        }

        .cta-band p,
        .cta-band li {
            color: rgba(255, 255, 255, 0.82);
        }

        .cta-band ul {
            margin: 14px 0 0;
            padding-left: 18px;
            display: grid;
            gap: 8px;
        }

        .cta-band-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }

        .cta-band-actions .btn-secondary {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .cta-band-actions .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.18);
        }

        .support-stack {
            display: grid;
            gap: 12px;
        }

        .support-card {
            padding: 16px 18px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.14);
        }

        .support-card span {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.72);
        }

        /* ── Pulsing CTA ── */
        .btn-primary.pulse {
            animation: none;
        }

        /* ── Hero Title Entrance ── */
        .wf-title {
            animation: none;
        }

        /* ── Card hover premium lift ── */
        .wf-facts,
        .wf-why,
        .info-panel,
        .facts-panel,
        .advisor-card {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                        box-shadow 0.3s ease;
        }

        .wf-facts:hover,
        .wf-why:hover,
        .info-panel:hover,
        .facts-panel:hover,
        .advisor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 34px rgba(8, 52, 28, 0.08);
        }

        /* ── Media tile hover ── */
        .wf-media-card,
        .media-card,
        .landmark-card {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                        box-shadow 0.3s ease,
                        border-color 0.3s ease;
        }

        .wf-media-card:hover,
        .media-card:hover,
        .landmark-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(8, 52, 28, 0.09);
            border-color: rgba(32, 90, 68, 0.35);
        }

        /* ── Tab transitions ── */
        .unit-tab,
        .size-tab,
        .media-toggle {
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* ── Unit tab click ripple ── */
        .unit-tab:active,
        .size-tab:active {
            transform: scale(0.95);
        }

        /* ── Hero media stage crossfade ── */
        #wf-hero-stage-image {
            transition: opacity 0.45s ease, transform 0.45s ease;
        }

        #wf-hero-stage-image:not([src]) {
            opacity: 0;
            transform: scale(1.04);
        }

        /* ── Price number tick on load ── */
        .metric-primary strong {
            animation: price-tick 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.8s both;
        }

        /* ── Facts grid stagger on scroll ── */
        .wf-fact {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1),
                        opacity 0.3s ease;
        }

        .wf-fact:hover {
            transform: translateY(-3px);
        }

        /* ── Floating badge animation ── */
        .brand-badge {
            animation: none;
        }

        /* ── Map box ── */
        .map-box {
            animation: none;
        }

        /* ── Advisor card premium shine ── */
        .advisor-card::before {
            content: none;
        }

        /* ── Scroll reveal (IntersectionObserver driven) ── */
        .reveal {
            opacity: 1;
            transform: none;
            transition: none;
        }
        .reveal.visible {
            opacity: 1;
            transform: none;
        }

        .wf-hero {
            padding: 14px 0 18px;
        }

        .wf-shell {
            display: grid;
            gap: 16px;
        }

        .wf-topbar {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 92px;
            gap: 16px;
            align-items: center;
        }

        .wf-crumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            color: var(--text-700);
            font-size: 10px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 10px;
            opacity: 0.8;
        }

        .wf-crumbs span:not(:last-child)::after {
            content: "/";
            margin-left: 6px;
            opacity: 0.4;
        }

        .wf-title {
            margin: 0;
            max-width: 11ch;
            font-size: clamp(26px, 3.2vw, 48px);
            line-height: 0.92;
            letter-spacing: -0.05em;
            color: var(--text-900);
        }

        .wf-meta {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            color: var(--text-700);
            font-size: 14px;
        }

        .wf-meta strong {
            color: var(--text-900);
        }

        .wf-meta span {
            position: relative;
        }

        .wf-meta span + span {
            padding-left: 14px;
        }

        .wf-meta span + span::before {
            content: "";
            position: absolute;
            left: 0;
            top: 50%;
            width: 4px;
            height: 4px;
            border-radius: 999px;
            background: rgba(97, 116, 104, 0.55);
            transform: translateY(-50%);
        }

        .wf-rating {
            margin-top: 10px;
            color: #d6a637;
            font-size: 13px;
            letter-spacing: 0.12em;
        }

        .wf-rating span {
            color: var(--text-700);
            letter-spacing: 0;
            margin-left: 10px;
            font-size: 14px;
        }

        .wf-builder-tile {
            width: 92px;
            height: 92px;
            border-radius: 22px;
            border: 1px solid rgba(23, 49, 38, 0.10);
            background: linear-gradient(135deg, #d8efe0 0%, #f6fbf7 100%);
            box-shadow: 0 8px 18px rgba(16, 39, 27, 0.05);
            display: grid;
            place-items: center;
            overflow: hidden;
            justify-self: end;
            align-self: start;
        }

        .wf-builder-tile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .wf-builder-tile span {
            font-weight: 800;
            font-size: 22px;
            color: var(--green-900);
            letter-spacing: -0.04em;
        }

        .wf-hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.95fr) minmax(300px, 0.92fr);
            gap: 14px;
            align-items: start;
        }

        .wf-stage {
            position: relative;
            min-height: 300px;
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid var(--border);
            background: var(--white);
            box-shadow: 0 14px 28px rgba(8, 52, 28, 0.06);
        }

        .wf-stage.is-switching img {
            opacity: 0.9;
            transform: scale(1.015);
        }

        .wf-stage img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: opacity 0.28s ease, transform 0.45s ease;
        }

        .wf-actions {
            position: absolute;
            top: 16px;
            right: 16px;
            display: flex;
            gap: 10px;
            z-index: 2;
        }

        .wf-btn {
            min-height: 42px;
            padding: 0 16px;
            border-radius: 14px;
            border: 1px solid rgba(16, 39, 27, 0.08);
            background: rgba(255,255,255,0.96);
            color: var(--text-900);
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 24px rgba(16, 39, 27, 0.08);
        }

        .wf-media-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .wf-side-column {
            display: grid;
            gap: 12px;
            align-content: start;
        }

        .wf-media-card {
            position: relative;
            min-height: 138px;
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid var(--border);
            background: var(--white);
            box-shadow: 0 10px 20px rgba(16, 39, 27, 0.05);
            cursor: pointer;
        }

        .wf-media-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .wf-media-card.active {
            outline: 2px solid rgba(13, 74, 41, 0.34);
            outline-offset: 2px;
        }

        .wf-media-card.skeleton {
            background:
                linear-gradient(90deg, #edf3ee 25%, #f8fbf8 50%, #edf3ee 75%);
            background-size: 220% 100%;
            animation: shimmer 1.8s linear infinite;
            cursor: default;
        }

        .wf-media-card.skeleton::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(16, 39, 27, 0.02), rgba(16, 39, 27, 0.08));
        }

        .wf-media-card.skeleton::after {
            content: "Image Placeholder";
            position: absolute;
            inset: auto 0 50% 0;
            transform: translateY(50%);
            text-align: center;
            color: #6f8277;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .wf-tag {
            position: absolute;
            left: 12px;
            bottom: 12px;
            z-index: 2;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.96);
            color: var(--text-900);
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 10px 24px rgba(16, 39, 27, 0.08);
        }

        .wf-bottom-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .wf-mobile-facts {
            display: none;
        }

        .wf-facts,
        .wf-why {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow);
        }

        .wf-facts {
            padding: 20px 22px;
        }

        .wf-facts-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .wf-facts-head strong {
            font-size: clamp(24px, 2.3vw, 38px);
            line-height: 1;
            letter-spacing: -0.05em;
        }

        .wf-chip {
            min-height: 42px;
            padding: 0 16px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(29, 101, 71, 0.2);
            color: var(--green-900);
            background: #fffefb;
            font-size: 14px;
            font-weight: 700;
        }

        .wf-facts-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px 20px;
            padding-top: 16px;
        }

        .wf-fact span {
            display: block;
            color: var(--text-700);
            font-size: 12px;
            margin-bottom: 6px;
        }

        .wf-fact strong {
            display: block;
            font-size: 18px;
            line-height: 1.34;
            letter-spacing: -0.03em;
        }

        .wf-right {
            display: grid;
            gap: 12px;
        }

        .wf-why {
            padding: 18px 20px;
        }

        .wf-mobile-inventory {
            display: none;
            padding: 18px 20px;
            border-radius: 22px;
            border: 1px solid rgba(32, 90, 68, 0.10);
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(247, 251, 248, 0.98));
            box-shadow: 0 14px 28px rgba(8, 52, 28, 0.06);
        }

        .wf-mobile-inventory h3 {
            margin: 0;
            font-size: 18px;
            line-height: 1.1;
            letter-spacing: -0.04em;
            color: var(--green-950);
        }

        .wf-mobile-inventory p {
            margin: 8px 0 0;
            color: var(--text-700);
            font-size: 13px;
            line-height: 1.45;
        }

        .inventory-table {
            margin-top: 14px;
            display: grid;
            gap: 10px;
        }

        .inventory-row {
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            padding: 12px 12px 12px 14px;
            border-radius: 16px;
            border: 1px solid rgba(32, 90, 68, 0.08);
            background: #f9fcfa;
        }

        .inventory-size {
            display: contents;
        }

        .inventory-badge {
            min-width: 0;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--green-800), var(--green-950));
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            line-height: 1;
            letter-spacing: 0.01em;
            font-weight: 800;
            box-shadow: 0 8px 18px rgba(8, 52, 28, 0.12);
        }

        .inventory-meta {
            min-width: 0;
        }

        .inventory-meta strong {
            display: block;
            font-size: 14px;
            line-height: 1.1;
            letter-spacing: -0.03em;
            color: var(--green-950);
        }

        .inventory-meta span {
            display: block;
            margin-top: 4px;
            color: var(--text-700);
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .inventory-price {
            font-size: 15px;
            line-height: 1;
            letter-spacing: -0.03em;
            color: var(--green-950);
            font-weight: 800;
            white-space: nowrap;
        }

        .inventory-check-btn {
            min-height: 38px;
            min-width: 112px;
            padding: 0 14px;
            font-size: 10px;
            width: 112px;
            white-space: nowrap;
            box-shadow: 0 10px 20px rgba(8, 52, 28, 0.14);
            gap: 6px;
            border-radius: 999px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-weight: 800;
            justify-self: end;
        }

        .inventory-check-btn::before {
            content: none;
        }

        .inventory-check-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px rgba(8, 52, 28, 0.14);
        }

        .inventory-check-btn span {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            position: relative;
            z-index: 1;
        }

        .inventory-check-btn span::after {
            content: "›";
            font-size: 11px;
            line-height: 1;
        }

        .wf-why h3 {
            margin: 0 0 10px;
            font-size: 18px;
            line-height: 1.16;
            letter-spacing: -0.04em;
        }

        .wf-why ul {
            margin: 0;
            padding-left: 16px;
            color: var(--text-700);
            display: grid;
            gap: 8px;
            line-height: 1.45;
        }

        .wf-view-more {
            margin-top: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 18px;
            border-radius: 12px;
            border: 1px solid rgba(29, 101, 71, 0.18);
            background: var(--green-wash);
            color: var(--green-900);
            font-size: 14px;
            font-weight: 700;
        }

        .wf-cta {
            border-radius: 22px;
            padding: 14px 18px;
            background: linear-gradient(135deg, #1f6b4c 0%, #0f452b 100%);
            color: #ffffff;
            text-align: center;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.03em;
            box-shadow: 0 12px 22px rgba(15, 69, 43, 0.12);
        }

        @media (min-width: 1101px) {
            .hero {
                padding: 12px 0 14px;
            }

            .hero-topbar {
                padding-bottom: 10px;
            }

            .hero-crumbs {
                margin-bottom: 6px;
            }

            .hero-heading {
                font-size: clamp(24px, 3vw, 42px);
            }

            .hero-meta {
                margin-top: 6px;
                gap: 6px 14px;
                font-size: 13px;
            }

            .brand-tile {
                width: 74px;
                height: 74px;
                border-radius: 18px;
            }

            .brand-tile span {
                font-size: 18px;
            }

            .hero-main-grid,
            .bottom-strip {
                gap: 12px;
            }

            .lead-visual {
                min-height: min(38svh, 330px);
            }

            .floating-actions {
                top: 12px;
                right: 12px;
                gap: 8px;
            }

            .floating-actions .btn {
                padding: 10px 14px;
                font-size: 13px;
            }

            .media-rail {
                gap: 10px;
                grid-auto-rows: minmax(148px, 1fr);
            }

            .media-tile {
                min-height: 148px;
                border-radius: 18px;
            }

            .tile-tag {
                left: 10px;
                bottom: 10px;
                padding: 8px 12px;
                font-size: 12px;
            }

            .bottom-strip {
                padding-top: 12px;
            }

            .facts-panel,
            .info-panel {
                border-radius: 22px;
            }

            .facts-panel {
                padding: 16px 18px;
            }

            .price-line {
                gap: 10px;
                padding-bottom: 12px;
            }

            .price-line strong {
                font-size: clamp(20px, 2vw, 28px);
            }

            .price-pill {
                min-height: 36px;
                padding: 0 12px;
                font-size: 12px;
            }

            .facts-grid {
                gap: 12px 18px;
                padding-top: 14px;
            }

            .fact span {
                margin-bottom: 4px;
                font-size: 11px;
            }

            .fact strong {
                font-size: 15px;
                line-height: 1.26;
            }

            .hero-side {
                gap: 10px;
            }

            .info-panel {
                padding: 16px 18px;
            }

            .info-panel h3 {
                margin-bottom: 8px;
                font-size: 18px;
            }

            .bullet-list {
                gap: 8px;
                font-size: 13px;
                line-height: 1.42;
            }

            .info-link {
                min-height: 38px;
                margin-top: 10px;
                padding: 0 14px;
                font-size: 13px;
            }

            .hero-cta-panel {
                border-radius: 18px;
                padding: 12px 14px;
                font-size: 15px;
            }
        }

        .hero-card {
            display: block;
        }

        .hero-copy {
            padding: 52px 52px 46px;
        }

        .hero-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .hero-badges span {
            padding: 9px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.1);
        }

        .hero-copy h1 {
            margin: 0 0 10px;
            font-size: clamp(40px, 5vw, 78px);
            line-height: 0.9;
            font-weight: 700;
            letter-spacing: -0.04em;
        }

        .hero-copy p {
            margin: 0;
            max-width: 42ch;
            color: rgba(255,255,255,0.84);
            line-height: 1.6;
            font-size: 16px;
        }

        .hero-cta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 22px 0 18px;
        }

        .hero-cta .btn-secondary {
            background: rgba(255,255,255,0.10);
            border-color: rgba(255,255,255,0.14);
            color: var(--white);
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .hero-stat {
            border-radius: 18px;
            padding: 16px 18px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.08);
        }

        .hero-stat span {
            display: block;
            margin-bottom: 6px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.72);
        }

        .hero-stat strong {
            font-size: 22px;
            line-height: 1.2;
            letter-spacing: -0.03em;
        }

        .hero-media {
            position: relative;
            overflow: hidden;
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }

        .hero-media-tabs {
            position: absolute;
            left: 22px;
            right: 22px;
            bottom: 22px;
            z-index: 2;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 0;
        }

        .media-tab {
            border: 0;
            cursor: pointer;
            border-radius: 999px;
            padding: 9px 13px;
            font: inherit;
            font-weight: 600;
            background: rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
        }

        .media-tab.active {
            background: var(--white);
            color: var(--green-900);
        }

        .media-rail .media-tab {
            border-radius: 22px;
            padding: 0;
            background: transparent;
            display: block;
            width: 100%;
            height: 100%;
            backdrop-filter: none;
        }

        .hero-media-stage {
            position: relative;
            flex: 1;
            min-height: 100%;
            overflow: hidden;
            background: rgba(255,255,255,0.14);
        }

        .hero-media-stage img {
            width: 100%;
            height: 100%;
            min-height: 520px;
            object-fit: cover;
        }

        .hero-media-stage::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(4, 30, 16, 0.04) 0%, rgba(4, 30, 16, 0.36) 100%);
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 24px;
            align-items: start;
        }

        .section-card,
        .advisor-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: var(--shadow);
        }

        .section-card {
            padding: 0;
            margin-bottom: 20px;
            background: transparent;
            border: 0;
            box-shadow: none;
            min-width: 0;
        }

        .section-head {
            margin-bottom: 16px;
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .section-head h2 {
            margin: 0 0 4px;
            font-size: clamp(24px, 2.4vw, 34px);
            line-height: 1.05;
            color: var(--green-900);
            letter-spacing: -0.04em;
        }

        .section-head p {
            margin: 0;
            color: var(--text-700);
            max-width: 46ch;
            line-height: 1.5;
            font-size: 15px;
        }

        .unit-shell {
            overflow: hidden;
            border-radius: 28px;
            border: 1px solid rgba(32, 90, 68, 0.12);
            background: linear-gradient(180deg, rgba(248, 252, 249, 0.92) 0%, rgba(255, 255, 255, 0.98) 14%, #ffffff 100%);
            box-shadow: 0 18px 46px rgba(16, 70, 50, 0.08);
            padding: 26px;
        }

        .unit-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding: 0 0 18px;
            background: transparent;
            border-bottom: 1px solid rgba(32, 90, 68, 0.10);
        }

        .unit-tabs,
        .hero-plan-tabs {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .unit-tab,
        .size-tab {
            border: 0;
            cursor: pointer;
            font: inherit;
            font-weight: 800;
        }

        .unit-tab {
            border-radius: 999px;
            padding: 12px 18px;
            background: var(--white);
            color: var(--green-900);
            border: 1px solid var(--border);
            box-shadow: 0 8px 20px rgba(13, 52, 35, 0.04);
        }

        .unit-tab.active {
            background: linear-gradient(135deg, var(--green-800), var(--green-950));
            color: var(--white);
            border-color: transparent;
        }

        .unit-panel { display: none; }
        .unit-panel.active { display: block; }

        .size-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding: 20px 0 0;
            border-bottom: 1px solid rgba(32, 90, 68, 0.10);
        }

        .size-tabs {
            display: inline-flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .size-tab {
            background: #f5faf7;
            color: var(--text-700);
            padding: 12px 16px;
            font-size: 16px;
            border: 1px solid rgba(32, 90, 68, 0.10);
            border-radius: 999px;
        }

        .size-tab.active {
            color: var(--white);
            background: linear-gradient(135deg, var(--green-800), var(--green-950));
            border-color: transparent;
        }

        .size-hint {
            color: var(--text-700);
            font-size: 13px;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(242, 247, 244, 0.9);
            border: 1px solid rgba(32, 90, 68, 0.08);
        }

        .variant-panel {
            display: none;
            padding: 22px 0 0;
        }

        .variant-panel.active {
            display: grid;
            grid-template-columns: 320px minmax(0, 1fr);
            gap: 20px;
        }

        .variant-metrics {
            padding: 20px;
            border: 1px solid rgba(32, 90, 68, 0.10);
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(247, 251, 248, 0.96) 0%, rgba(255,255,255,0.98) 100%);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.65);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .metric {
            border-radius: 16px;
            padding: 14px;
            background: #f7faf8;
            border: 1px solid rgba(32, 90, 68, 0.08);
        }

        .metric-primary {
            padding: 16px 18px;
            background: linear-gradient(135deg, rgba(233, 244, 237, 0.98), rgba(247, 251, 248, 1));
            border-color: rgba(32, 90, 68, 0.12);
        }

        .metrics-secondary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .metric span {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-700);
        }

        .metric strong {
            font-size: 20px;
            line-height: 1.3;
            letter-spacing: -0.03em;
        }

        .metric-primary strong {
            font-size: 34px;
            line-height: 1.05;
            letter-spacing: -0.05em;
            color: var(--green-950);
        }

        .variant-status-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 14px;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 14px;
            border-radius: 999px;
            background: #e8f3ec;
            color: var(--green-900);
            border: 1px solid rgba(32, 90, 68, 0.10);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .status-chip.status-sold-out,
        .status-chip.status-sold {
            background: #fdf0f0;
            color: #9f2d2d;
            border-color: rgba(159, 45, 45, 0.16);
        }

        .status-chip.status-hold {
            background: #fff7e5;
            color: #8a5a08;
            border-color: rgba(138, 90, 8, 0.16);
        }

        .plan-actions {
            display: grid;
            gap: 10px;
        }

        .unit-mobile-summary {
            display: none;
        }

        .unit-mobile-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .unit-mobile-price {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .unit-mobile-price span,
        .unit-mobile-spec span {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-500);
            font-weight: 700;
        }

        .unit-mobile-price strong {
            font-size: 24px;
            line-height: 1;
            letter-spacing: -0.04em;
            color: var(--green-950);
        }

        .unit-mobile-specs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .unit-mobile-spec {
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px solid rgba(32, 90, 68, 0.10);
            background: #f8fbf9;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .unit-mobile-spec strong {
            font-size: 15px;
            line-height: 1.2;
            color: var(--green-950);
        }

        .inventory-wa-btn {
            position: relative;
            overflow: hidden;
            gap: 8px;
            min-height: 48px;
            padding: 0 16px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .inventory-wa-btn::before {
            content: '';
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.22);
            box-shadow: inset 0 0 0 4px rgba(255, 255, 255, 0.15);
            display: inline-block;
        }

        .variant-actions .btn {
            text-align: center;
            justify-content: center;
        }

        .variant-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .inventory-call-btn {
            width: 100%;
        }

        .plan-view {
            padding: 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
            border: 1px solid rgba(32, 90, 68, 0.10);
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(250, 252, 251, 0.98) 100%);
        }

        .plan-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .plan-head strong {
            font-size: 22px;
            letter-spacing: -0.04em;
        }

        .plan-head span {
            color: var(--text-700);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .floor-plan {
            position: relative;
            display: block;
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid rgba(32, 90, 68, 0.08);
            background: #f7f4ee;
        }

        .floor-plan img {
            width: 100%;
            height: 390px;
            object-fit: cover;
        }

        .plan-caption {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            color: var(--text-700);
            font-weight: 500;
            font-size: 14px;
        }

        .finance-shell {
            display: grid;
            gap: 14px;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98), #f7fbf8);
            box-shadow: 0 14px 26px rgba(8, 52, 28, 0.05);
        }

        .finance-topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .finance-heading {
            display: grid;
            gap: 8px;
        }

        .finance-kicker {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            background: var(--green-soft);
            color: var(--green-900);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .finance-kicker::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--green-700);
            box-shadow: 0 0 0 4px rgba(45, 106, 80, 0.12);
        }

        .finance-heading h3 {
            margin: 0;
            font-size: clamp(24px, 2.1vw, 34px);
            line-height: 1.04;
            letter-spacing: -0.04em;
        }

        .finance-heading p,
        .finance-footnote {
            margin: 0;
            color: var(--text-700);
            line-height: 1.55;
        }

        .finance-count {
            color: var(--text-700);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            white-space: nowrap;
            padding-top: 6px;
        }

        .finance-marquee {
            position: relative;
            overflow: hidden;
            border-radius: 22px;
            border: 1px solid rgba(32, 90, 68, 0.08);
            background: linear-gradient(180deg, rgba(248, 251, 249, 0.96), #ffffff);
            padding: 12px 0;
        }

        .finance-marquee::before,
        .finance-marquee::after {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            width: 36px;
            z-index: 2;
            pointer-events: none;
        }

        .finance-marquee::before {
            left: 0;
            background: linear-gradient(90deg, #f7fbf8, transparent);
        }

        .finance-marquee::after {
            right: 0;
            background: linear-gradient(270deg, #ffffff, transparent);
        }

        .finance-track {
            display: flex;
            gap: 12px;
            width: max-content;
            animation: builderScroll 42s linear infinite;
        }

        .finance-marquee:hover .finance-track {
            animation-play-state: paused;
        }

        .finance-card {
            flex-shrink: 0;
            width: 196px;
            min-height: 150px;
            padding: 14px 14px 12px;
            border-radius: 18px;
            border: 1px solid rgba(32, 90, 68, 0.10);
            background: #ffffff;
            box-shadow: 0 12px 24px rgba(8, 52, 28, 0.05);
            display: grid;
            align-content: space-between;
            gap: 10px;
        }

        .finance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(8, 52, 28, 0.08);
        }

        .finance-logo {
            width: 100%;
            min-height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #f5faf7 0%, #ffffff 100%);
            border: 1px solid rgba(32, 90, 68, 0.08);
            display: grid;
            place-items: center;
            padding: 8px;
            text-align: center;
            color: var(--green-900);
            font-size: 19px;
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .finance-logo img {
            max-width: 100%;
            max-height: 34px;
            object-fit: contain;
            display: block;
        }

        .finance-body strong {
            display: block;
            font-size: 17px;
            line-height: 1.08;
            letter-spacing: -0.04em;
        }

        .finance-body span {
            display: block;
            margin-top: 4px;
            color: var(--text-700);
            font-size: 13px;
        }

        .finance-chip {
            width: fit-content;
            min-height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eef5f0;
            color: var(--green-900);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: none;
        }

        .finance-chip::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--green-700);
        }

        .finance-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding-top: 6px;
            border-top: 1px solid rgba(32, 90, 68, 0.10);
        }

        .finance-cta {
            flex-shrink: 0;
        }

        .media-shell {
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid var(--border);
            background: var(--white);
            box-shadow: var(--shadow);
            padding: 24px;
            min-width: 0;
            max-width: 100%;
        }

        .other-charges-shell {
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid var(--border);
            background: var(--white);
            box-shadow: var(--shadow);
            padding: 22px 24px;
        }

        .other-charges-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 14px;
        }

        .other-charges-head h3 {
            margin: 0;
            font-size: 24px;
            letter-spacing: -0.04em;
            line-height: 1.05;
        }

        .other-charges-head span,
        .other-charges-note {
            color: var(--text-700);
            font-size: 14px;
        }

        .other-charges-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid rgba(32,90,68,0.10);
        }

        .other-charges-table th,
        .other-charges-table td {
            text-align: left;
            padding: 14px 0;
            border-bottom: 1px solid rgba(32,90,68,0.10);
        }

        .other-charges-table th {
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-700);
        }

        .other-charges-table td strong {
            display: block;
            font-size: 16px;
            color: var(--text-900);
        }

        .other-charges-table td:last-child,
        .other-charges-table th:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--green-900);
        }

        .other-charges-note {
            margin-top: 12px;
        }

        .media-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            padding: 0 0 18px;
            background: transparent;
            border-bottom: 1px solid rgba(32, 90, 68, 0.10);
        }

        .media-tabs {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .media-toggle {
            border: 0;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            border-radius: 999px;
            padding: 10px 15px;
            background: #f7faf8;
            color: var(--green-900);
            border: 1px solid rgba(32, 90, 68, 0.08);
        }

        .media-toggle.active {
            background: linear-gradient(135deg, var(--green-800), var(--green-950));
            color: var(--white);
            border-color: transparent;
        }

        .media-hint {
            color: var(--text-700);
            font-size: 13px;
            font-weight: 500;
        }

        .media-panel {
            display: none;
            padding: 20px 0 0;
            min-width: 0;
            max-width: 100%;
        }

        .media-panel.active {
            display: block;
        }

        .media-filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .media-subtoggle {
            border: 0;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            border-radius: 999px;
            padding: 8px 13px;
            background: #f7faf8;
            color: var(--green-900);
            border: 1px solid rgba(32, 90, 68, 0.08);
        }

        .media-subtoggle.active {
            background: rgba(32, 90, 68, 0.10);
            border-color: rgba(32, 90, 68, 0.16);
        }

        .media-subpanel {
            display: none;
        }

        .media-subpanel.active {
            display: block;
        }

        .media-grid,
        .landmark-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .media-panel[data-media-content="images"] .media-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .media-card,
        .landmark-card {
            display: block;
            padding: 18px;
            border-radius: 20px;
            border: 1px solid rgba(32, 90, 68, 0.08);
            background: #fbfcfb;
        }

        .media-card strong,
        .landmark-card strong {
            display: block;
            font-size: 17px;
            margin-bottom: 4px;
            color: var(--green-900);
            letter-spacing: -0.02em;
        }

        .media-card p,
        .landmark-card p,
        .landmark-card span {
            margin: 0;
            color: var(--text-700);
            line-height: 1.5;
            font-size: 14px;
        }

        .media-card img {
            width: 100%;
            height: 180px;
            margin-top: 12px;
            border-radius: 16px;
            object-fit: cover;
        }

        .media-card-media {
            position: relative;
            width: 100%;
            margin-top: 12px;
            border-radius: 18px;
            overflow: hidden;
            aspect-ratio: 16 / 9;
            background: linear-gradient(180deg, #edf4ef, #dbe8df);
        }

        .media-card-media img {
            width: 100%;
            height: 100%;
            margin-top: 0;
            border-radius: 0;
            object-fit: cover;
            display: block;
        }

        .media-card-play {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            background: linear-gradient(180deg, rgba(6, 30, 16, 0.08), rgba(6, 30, 16, 0.22));
        }

        .media-card-play-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 48px;
            padding: 0 18px 0 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.92);
            color: var(--green-950);
            box-shadow: 0 14px 28px rgba(8, 52, 28, 0.16);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.02em;
            backdrop-filter: blur(10px);
        }

        .media-card-play-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--green-800), var(--green-950));
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            line-height: 1;
            padding-left: 2px;
            flex: 0 0 auto;
        }

        .media-card--video {
            padding: 16px;
        }

        .media-card--video strong {
            margin-bottom: 6px;
        }

        .media-card--video p {
            font-size: 13px;
        }

        .media-card--video strong,
        .media-card--video p {
            display: none !important;
        }

        .media-card.is-playing .media-card-play,
        .featured-video-card.is-playing .media-card-play {
            display: none;
        }

        .inline-video-player {
            position: relative;
            width: 100%;
            height: 100%;
            background: #000;
        }

        .inline-video-player iframe,
        .inline-video-player video {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: #000;
        }

        .inline-video-close {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.94);
            color: var(--green-950);
            font: inherit;
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(8, 52, 28, 0.14);
        }

        .featured-video-shell {
            padding: 18px;
            border-radius: 24px;
            border: 1px solid rgba(32, 90, 68, 0.10);
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(247, 251, 248, 0.98));
            box-shadow: 0 14px 28px rgba(8, 52, 28, 0.06);
        }

        .featured-video-mobile {
            display: block;
        }

        .featured-video-desktop {
            display: none;
            margin-bottom: 18px;
        }

        .unit-side-rail {
            display: grid;
            gap: 18px;
            align-content: start;
            min-width: 0;
        }

        .featured-video-card {
            display: grid;
            gap: 14px;
        }

        .featured-video-copy h3 {
            margin: 0 0 6px;
            font-size: 22px;
            color: var(--green-900);
            letter-spacing: -0.03em;
        }

        .featured-video-copy p {
            margin: 0;
            color: var(--text-700);
            line-height: 1.5;
            font-size: 14px;
        }

        .video-modal {
            position: fixed;
            inset: 0;
            z-index: 1200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(8, 20, 13, 0.78);
            backdrop-filter: blur(8px);
        }

        .video-modal.visible {
            display: flex;
        }

        .video-modal-dialog {
            position: relative;
            width: min(980px, 100%);
            display: grid;
            gap: 12px;
        }

        .video-modal-close {
            justify-self: end;
            width: 44px;
            height: 44px;
            border: 0;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.96);
            color: var(--green-950);
            font: inherit;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 12px 24px rgba(8, 52, 28, 0.18);
        }

        .video-modal-frame {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            overflow: hidden;
            border-radius: 24px;
            background: #000;
            box-shadow: 0 24px 48px rgba(8, 20, 13, 0.28);
        }

        .video-modal-frame iframe,
        .video-modal-frame video {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: #000;
        }

        .media-list {
            display: grid;
            gap: 14px;
        }

        .media-doc {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(32, 90, 68, 0.08);
            background: #fbfcfb;
        }

        .media-doc-copy {
            min-width: 0;
        }

        .media-doc-copy strong {
            display: block;
            margin: 0;
            font-size: 17px;
            color: var(--green-900);
            letter-spacing: -0.02em;
        }

        .media-doc-copy p {
            margin: 4px 0 0;
            color: var(--text-700);
            font-size: 14px;
            line-height: 1.45;
        }

        .media-doc-action {
            flex: 0 0 auto;
            min-height: 42px;
            padding: 0 16px;
        }

        .media-doc-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(32, 90, 68, 0.12), rgba(32, 90, 68, 0.04));
            color: var(--green-900);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            flex: 0 0 auto;
        }

        .media-doc-main {
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1 1 auto;
        }

        .media-card--visual {
            padding: 10px;
        }

        .media-card--visual img {
            height: 220px;
            margin-top: 0;
            border-radius: 18px;
        }

        .media-card--visual strong,
        .media-card--visual p {
            display: none !important;
        }

        .media-empty {
            padding: 18px;
            border-radius: 18px;
            border: 1px dashed rgba(32, 90, 68, 0.14);
            background: #fbfcfb;
            color: var(--text-700);
            text-align: center;
        }

        .comparison-shell {
            overflow: hidden;
            border-radius: 22px;
            border: 1px solid rgba(32, 90, 68, 0.08);
            background: #fcfdfc;
            max-width: 100%;
            min-width: 0;
        }

        .comparison-wrap {
            display: block;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: rgba(32, 90, 68, 0.38) rgba(32, 90, 68, 0.08);
            max-width: 100%;
            min-width: 0;
        }

        .comparison-wrap::-webkit-scrollbar {
            height: 10px;
        }

        .comparison-wrap::-webkit-scrollbar-track {
            background: rgba(32, 90, 68, 0.08);
            border-radius: 999px;
        }

        .comparison-wrap::-webkit-scrollbar-thumb {
            background: rgba(32, 90, 68, 0.38);
            border-radius: 999px;
        }

        .comparison-table {
            width: max-content;
            min-width: 880px;
            border-collapse: collapse;
            background: var(--white);
        }

        .comparison-table th,
        .comparison-table td {
            padding: 13px 15px;
            border-bottom: 1px solid rgba(32, 90, 68, 0.08);
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }

        .comparison-table thead th {
            background: var(--green-wash);
            color: var(--green-900);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .comparison-table tbody tr:hover {
            background: #f9fcfa;
        }

        .comparison-table strong {
            color: var(--green-900);
        }

        .media-panel[data-media-content="comparison"] {
            overflow: hidden;
            max-width: 100%;
        }

        .comparison-scroll-hint {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 10px 14px 12px;
            color: var(--text-700);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .comparison-scroll-hint::before,
        .comparison-scroll-hint::after {
            content: "";
            width: 28px;
            height: 1px;
            background: rgba(32, 90, 68, 0.22);
        }

        .map-box {
            overflow: hidden;
            border-radius: 24px;
            border: 1px solid var(--border);
            background: var(--green-wash);
            min-height: 220px;
            display: grid;
            place-items: center;
            box-shadow: var(--shadow);
        }

        .map-box iframe {
            width: 100%;
            height: 320px;
            border: 0;
        }

        .advisor-card {
            position: sticky;
            top: 24px;
            padding: 22px;
            box-shadow: var(--shadow);
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(255,255,255,0.97) 0%, rgba(246,250,248,0.97) 100%);
        }

        .advisor-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }

        .advisor-mark {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 17px;
            font-weight: 700;
            color: var(--white);
            background: linear-gradient(135deg, var(--green-800), var(--green-950));
        }

        .advisor-head strong {
            display: block;
            font-size: 22px;
            letter-spacing: -0.03em;
        }

        .advisor-head p,
        .advisor-copy,
        .notice {
            margin: 0;
            color: var(--text-700);
            line-height: 1.55;
        }

        .advisor-actions {
            display: grid;
            gap: 10px;
            margin: 18px 0;
        }

        .notice {
            padding-top: 14px;
            border-top: 1px solid var(--border);
            font-size: 13px;
        }

        .container {
            width: min(100% - 56px, 1720px);
            margin: 0 auto;
        }

        @media (max-width: 1100px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .wf-topbar,
            .wf-hero-grid {
                grid-template-columns: 1fr;
            }

            .wf-builder-tile {
                justify-self: start;
            }

            .advisor-card {
                position: static;
            }
        }

        @media (min-width: 1101px) {
            .featured-video-mobile {
                display: none;
            }

            .featured-video-desktop {
                display: block;
            }
        }

        @media (max-width: 960px) {
            .container {
                width: calc(100% - 20px);
            }

            .hero-main-grid,
            .bottom-strip,
            .hero-topbar,
            .variant-panel.active {
                grid-template-columns: 1fr;
            }

            .brand-tile {
                justify-self: start;
            }

            .variant-metrics {
                padding-right: 20px;
            }
        }

        @media (max-width: 720px) {
            html,
            body {
                width: 100%;
                max-width: 100%;
                overflow-x: clip;
            }

            .topbar-inner,
            .unit-toolbar,
            .size-row,
            .plan-caption,
            .plan-head,
            .media-toolbar {
                align-items: flex-start;
            }

            .wf-shell,
            .wf-hero-grid,
            .wf-hero-grid > *,
            .wf-side-column,
            .wf-stage,
            .wf-media-grid,
            .wf-mobile-inventory,
            .content-grid,
            .content-grid > *,
            .section-card,
            .section-intro-card,
            .section-intro-grid,
            .facts-panel,
            .price-list-shell,
            .price-list-toolbar,
            .price-list-table-wrap {
                min-width: 0;
                max-width: 100%;
            }

            .hero {
                padding-top: 16px;
            }

            .wf-hero {
                padding-top: 10px;
            }

            .wf-topbar {
                grid-template-columns: 1fr 70px;
                gap: 12px;
            }

            .wf-builder-tile {
                width: 62px;
                height: 62px;
                border-radius: 15px;
            }

            .wf-title {
                font-size: clamp(24px, 8vw, 34px);
            }

            .wf-crumbs {
                display: none;
            }

            .wf-meta {
                font-size: 13px;
                gap: 6px 14px;
            }

            .wf-facts-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .wf-media-grid {
                display: flex;
                width: 100%;
                gap: 10px;
                overflow-x: auto;
                overflow-y: hidden;
                flex-wrap: nowrap;
                padding-bottom: 4px;
                scroll-snap-type: x proximity;
                -webkit-overflow-scrolling: touch;
            }

            .wf-media-grid::-webkit-scrollbar {
                display: none;
            }

            .wf-right {
                order: 0;
            }

            .wf-bottom-grid {
                display: none;
            }

            .wf-mobile-facts {
                display: block;
            }

            .wf-why,
            .wf-cta {
                display: none;
            }

            .wf-mobile-inventory {
                display: block;
                padding: 16px;
            }

            .wf-stage {
                min-height: 220px;
            }

            .wf-actions {
                display: none;
            }

            .wf-btn,
            .wf-view-more {
                width: 100%;
            }

            .wf-btn {
                min-height: 40px;
                padding: 0 12px;
                border-radius: 12px;
                font-size: 13px;
            }

            .wf-chip {
                width: auto;
                min-height: 34px;
                padding: 0 12px;
                font-size: 12px;
            }

            .hero-card,
            .advisor-card,
            .unit-shell,
            .media-shell,
            .map-box {
                border-radius: 24px;
            }

            .hero-topbar,
            .facts-panel,
            .info-panel,
            .hero-copy,
            .advisor-card,
            .variant-metrics,
            .plan-view,
            .unit-shell,
            .media-shell {
                padding-left: 18px;
                padding-right: 18px;
            }

            .hero-topbar {
                grid-template-columns: 1fr 70px;
                gap: 12px;
                padding-top: 16px;
                padding-bottom: 10px;
            }

            .hero-heading {
                font-size: clamp(24px, 8vw, 34px);
            }

            .hero-crumbs {
                display: none;
            }

            .brand-tile {
                width: 62px;
                height: 62px;
                border-radius: 15px;
            }

            .facts-grid,
            .media-grid,
            .landmark-grid,
            .media-rail {
                grid-template-columns: 1fr;
            }

            .lead-visual,
            .floor-plan img {
                min-height: 260px;
            }

            .btn {
                width: 100%;
            }

            .floating-actions,
            .variant-actions {
                display: grid;
            }

            .wf-media-card {
                flex: 0 0 132px;
                min-height: 84px;
                border-radius: 14px;
                scroll-snap-align: start;
            }

            .wf-tag {
                left: 10px;
                bottom: 10px;
                padding: 5px 9px;
                font-size: 10px;
                border-radius: 999px;
                box-shadow: 0 6px 14px rgba(16, 39, 27, 0.10);
            }

            .wf-facts {
                padding: 16px;
            }

            .wf-facts-head {
                align-items: flex-start;
                gap: 10px;
                padding-bottom: 12px;
            }

            .wf-facts-head strong {
                font-size: clamp(20px, 7vw, 30px);
            }

            .wf-fact strong {
                font-size: 16px;
            }

            .unit-toolbar {
                padding-bottom: 14px;
            }

            .size-row {
                gap: 10px;
                padding-top: 14px;
            }

            .size-tabs {
                width: 100%;
                display: flex;
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 4px;
                scrollbar-width: none;
            }

            .size-tabs::-webkit-scrollbar {
                display: none;
            }

            .size-tab {
                flex: 0 0 auto;
                padding: 10px 14px;
                font-size: 10.5px;
            }

            .unit-tab {
                padding: 10px 14px;
                font-size: 10.5px;
            }

            .size-hint {
                align-self: stretch;
                width: 100%;
                text-align: center;
                padding: 8px 10px;
                font-size: 12px;
            }

            .size-hint {
                display: none;
            }

            .variant-panel {
                padding-top: 16px;
            }

            .variant-metrics,
            .plan-view {
                padding: 16px;
                border-radius: 20px;
            }

            .unit-mobile-summary {
                display: grid;
                gap: 12px;
            }

            .metrics-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
            }

            .metrics-secondary {
                display: contents;
                margin-top: 0;
            }

            .metric {
                min-height: 84px;
                padding: 10px 9px;
                border-radius: 14px;
            }

            .metric-primary {
                min-height: 84px;
                padding: 10px 9px;
            }

            .metric span {
                margin-bottom: 4px;
                font-size: 10px;
                letter-spacing: 0.05em;
            }

            .metric strong {
                font-size: 12px;
                line-height: 1.2;
                letter-spacing: -0.03em;
            }

            .metric-primary strong {
                font-size: 18px;
                line-height: 1.1;
            }

            .variant-status-row {
                justify-content: flex-start;
                margin-top: 10px;
            }

            .variant-metrics {
                display: none;
            }

            .status-chip {
                min-height: 30px;
                padding: 0 12px;
                font-size: 11px;
            }

            .plan-view {
                gap: 12px;
                padding: 14px;
            }

            .floor-plan {
                border-radius: 18px;
            }

            .floor-plan img {
                height: 220px;
                min-height: 220px;
            }

            .plan-caption {
                gap: 8px;
                font-size: 12px;
            }

            .plan-caption span:last-child {
                display: none;
            }

            .plan-actions {
                gap: 10px;
            }

            .variant-actions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .variant-actions .btn {
                min-height: 46px;
                padding: 0 10px;
                font-size: 12px;
            }

            .inventory-wa-btn::before {
                width: 14px;
                height: 14px;
            }

            .unit-mobile-price strong {
                font-size: 22px;
            }

            .unit-mobile-spec {
                padding: 10px 12px;
                border-radius: 14px;
            }

            .unit-mobile-spec strong {
                font-size: 13px;
            }
        }

        @media (max-width: 520px) {
            .price-list-foot,
            .wf-facts-head .wf-chip {
                display: none !important;
            }

            .container {
                width: calc(100% - 14px);
            }

            .section-jump {
                padding: 10px;
            }

            .section-jump a {
                min-height: 34px;
                padding: 0 11px;
                font-size: 11px;
            }

            .section-intro-card,
            .media-shell,
            .unit-shell,
            .price-list-shell {
                border-radius: 18px;
            }

            .section-intro-card {
                padding: 16px;
            }

            .wf-facts-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .wf-fact strong {
                font-size: 16px;
                line-height: 1.28;
            }

            .wf-topbar {
                grid-template-columns: minmax(0, 1fr) 58px;
                gap: 10px;
            }

            .wf-title {
                max-width: none;
                font-size: clamp(22px, 8vw, 30px);
                line-height: 0.98;
            }

            .wf-meta {
                margin-top: 6px;
                gap: 6px 10px;
                font-size: 12px;
            }

            .wf-meta span + span {
                padding-left: 10px;
            }

            .wf-builder-tile {
                width: 58px;
                height: 58px;
                border-radius: 14px;
            }

            .wf-builder-tile span {
                font-size: 18px;
            }

            .wf-stage {
                min-height: 196px;
            }

            .wf-hero-grid {
                gap: 10px;
            }

            .wf-why {
                padding: 14px 16px;
            }

            .inventory-table {
                gap: 8px;
            }

            .inventory-row {
                grid-template-columns: 70px minmax(0, 1fr) auto;
                gap: 8px;
                padding: 10px 10px 10px 12px;
                border-radius: 14px;
            }

            .inventory-badge {
                min-height: 30px;
                padding: 0 8px;
                font-size: 11px;
            }

            .inventory-meta strong {
                font-size: 13px;
            }

            .inventory-meta span {
                font-size: 10px;
            }

            .inventory-check-btn {
                min-height: 34px;
                min-width: 98px;
                width: 98px;
                padding: 0 10px;
                font-size: 9px;
                gap: 6px;
            }

            .inventory-check-btn::before {
                width: 12px;
                height: 12px;
            }

            .wf-why h3 {
                font-size: 16px;
            }

            .wf-why ul {
                gap: 6px;
                font-size: 13px;
            }

            .wf-facts-grid {
                gap: 10px;
            }

            .wf-fact strong {
                font-size: 15px;
            }

            .unit-shell {
                padding-top: 16px;
                padding-bottom: 16px;
            }

            .floor-plan img {
                height: 184px;
                min-height: 184px;
            }

            .variant-actions .btn {
                font-size: 12px;
            }

            .unit-mobile-head {
                gap: 8px;
            }

            .unit-mobile-price span,
            .unit-mobile-spec span {
                font-size: 10px;
            }

            .unit-mobile-price strong {
                font-size: 20px;
            }

            .unit-mobile-specs {
                gap: 8px;
            }

            .unit-mobile-spec strong {
                font-size: 12px;
            }

            .status-chip {
                min-height: 28px;
                font-size: 10px;
                padding: 0 10px;
            }

            .section-intro-copy h2,
            .section-head h2 {
                font-size: 22px;
                line-height: 1.08;
            }

            .price-list-toolbar {
                padding: 14px;
            }

            .price-list-table-wrap {
                margin: 0 8px;
                padding: 0;
                border: 1px solid rgba(187, 35, 44, 0.12);
                border-radius: 14px;
                background: #fff;
                box-shadow: 0 8px 18px rgba(16, 39, 27, 0.06);
                width: auto;
                max-width: calc(100% - 16px);
                overflow-x: auto;
                overflow-y: hidden;
                overscroll-behavior-x: contain;
                -webkit-overflow-scrolling: touch;
            }

            .price-list-main {
                gap: 1px;
            }

            .price-list-pills {
                grid-template-columns: 1fr;
            }

            .price-list-footnote strong {
                font-size: 13px;
            }

            .price-list-table {
                min-width: 700px;
                width: 700px;
            }

            .price-list-table thead th,
            .price-list-table tbody td {
                padding: 9px 10px;
            }

            .price-list-table thead th {
                font-size: 10px;
            }

            .price-list-main strong,
            .price-list-price {
                font-size: 12px;
            }

            .price-list-subtle {
                font-size: 9px;
            }

            .price-list-status {
                padding: 5px 8px;
                font-size: 9px;
            }

            .price-list-cta {
                min-height: 32px;
                padding: 0 10px;
                font-size: 10px;
            }

            .price-list-foot {
                padding: 12px 14px 16px;
            }

            .price-list-footnote {
                font-size: 11px;
            }

            .media-panel[data-media-content="images"] .media-grid {
                grid-auto-columns: 84%;
            }

            .media-panel[data-media-content="images"] .media-card {
                padding: 10px;
            }

            .media-card--visual img {
                height: 240px;
                object-fit: contain;
                background: #eef3ef;
            }

            .content-grid,
            .section-card,
            .section-intro-card,
            .section-intro-grid,
            .facts-panel {
                min-width: 0;
                max-width: 100%;
            }
        }

        .travel-time-card {
            display: none !important;
            margin-top: 18px;
            padding: 24px;
            border: 1px solid rgba(14, 82, 60, 0.12);
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(244,250,246,0.96));
            box-shadow: 0 20px 60px rgba(14, 82, 60, 0.08);
        }

        .travel-time-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 18px;
        }

        .travel-time-head h3 {
            margin: 0;
            font-size: 1.65rem;
            line-height: 1.05;
        }

        .travel-time-head p,
        .travel-time-hint,
        .travel-time-trust,
        .travel-time-state {
            margin: 0;
            color: #60756c;
            font-size: 0.95rem;
        }

        .travel-time-origins {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .travel-origin-chip {
            min-height: 46px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid rgba(14, 82, 60, 0.14);
            background: #fff;
            color: #14382d;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        .travel-origin-chip small {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            color: #6b8078;
        }

        .travel-origin-chip.current {
            background: rgba(14, 82, 60, 0.08);
        }

        .travel-search-shell {
            position: relative;
        }

        .travel-search-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
        }

        .travel-search-input {
            width: 100%;
            min-height: 56px;
            border-radius: 18px;
            border: 1px solid rgba(14, 82, 60, 0.14);
            background: #fff;
            padding: 0 16px;
            font-size: 1rem;
            color: #12392d;
        }

        .travel-current-btn,
        .travel-clear-btn {
            min-height: 48px;
            padding: 0 16px;
            border-radius: 16px;
            border: 1px solid rgba(14, 82, 60, 0.14);
            background: #fff;
            color: #14382d;
            font-weight: 700;
        }

        .travel-clear-btn {
            margin-top: 12px;
        }

        .travel-suggestions {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 8px);
            z-index: 10;
            padding: 8px;
            border-radius: 18px;
            background: #fff;
            border: 1px solid rgba(14, 82, 60, 0.12);
            box-shadow: 0 20px 50px rgba(13, 55, 40, 0.12);
            display: none;
        }

        .travel-suggestions.visible {
            display: block;
        }

        .travel-suggestion {
            width: 100%;
            padding: 12px 14px;
            border: 0;
            border-radius: 14px;
            background: transparent;
            text-align: left;
            color: #14382d;
            cursor: pointer;
        }

        .travel-suggestion:hover,
        .travel-suggestion.active {
            background: rgba(14, 82, 60, 0.08);
        }

        .travel-selected-origin {
            margin-top: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            background: rgba(14, 82, 60, 0.05);
            border: 1px solid rgba(14, 82, 60, 0.08);
            color: #14382d;
            display: none;
        }

        .travel-selected-origin.visible {
            display: block;
        }

        .travel-results {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .travel-result-card,
        .travel-result-skeleton {
            min-height: 110px;
            padding: 18px;
            border-radius: 22px;
            border: 1px solid rgba(14, 82, 60, 0.12);
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .travel-result-card .eyebrow {
            font-size: 0.82rem;
            color: #6b8078;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
        }

        .travel-result-card strong {
            font-size: 1.6rem;
            line-height: 1;
            color: #14382d;
        }

        .travel-result-card span {
            color: #60756c;
            font-weight: 600;
        }

        .travel-result-skeleton {
            position: relative;
            overflow: hidden;
        }

        .travel-result-skeleton::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.8), transparent);
            animation: shimmer 1.2s infinite;
        }

        .travel-result-skeleton div {
            height: 14px;
            border-radius: 999px;
            background: rgba(14, 82, 60, 0.08);
        }

        .travel-result-skeleton div:nth-child(2) {
            height: 34px;
            width: 55%;
        }

        .travel-cta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }

        .travel-cta-row a,
        .travel-cta-row button {
            min-height: 48px;
            padding: 0 18px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .travel-cta-row .primary {
            background: #155f43;
            color: #fff;
        }

        .travel-cta-row .secondary {
            background: #fff;
            color: #14382d;
            border: 1px solid rgba(14, 82, 60, 0.14);
        }

        @keyframes shimmer {
            from { transform: translateX(-100%); }
            to { transform: translateX(100%); }
        }

        @keyframes builderScroll {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }

        @media (max-width: 768px) {
            .price-list-foot,
            .wf-facts-head .wf-chip {
                display: none !important;
            }

            .section-jump-wrap {
                padding-bottom: 14px;
            }

            .section-jump {
                flex-wrap: nowrap;
                gap: 8px;
                padding: 12px;
                border-radius: 18px;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }

            .section-jump::-webkit-scrollbar {
                display: none;
            }

            .section-jump a {
                flex: 0 0 auto;
                min-height: 38px;
                padding: 0 13px;
                font-size: 12px;
                white-space: nowrap;
            }

            .section-intro-card {
                padding: 18px;
                border-radius: 22px;
            }

            .section-intro-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .section-intro-copy h2,
            .section-head h2 {
                font-size: 24px;
            }

            .section-intro-copy p,
            .section-head p {
                font-size: 14px;
                line-height: 1.6;
                max-width: none;
            }

            .section-head {
                align-items: flex-start;
                gap: 12px;
            }

            .section-head .btn,
            .price-list-toolbar .btn {
                width: 100%;
            }

            .price-list-shell {
                border-radius: 22px;
            }

            .price-list-toolbar {
                padding: 16px;
                align-items: flex-start;
            }

            .price-list-toolbar h3 {
                font-size: 18px;
            }

            .price-list-toolbar p {
                margin-top: 6px;
                font-size: 13px;
                line-height: 1.5;
            }

            .price-list-table-wrap {
                overflow-x: auto;
                overflow-y: hidden;
                padding: 10px 10px 8px;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }

            .price-list-table {
                min-width: 780px;
                width: 780px;
            }

            .price-list-table thead {
                display: table-header-group;
            }

            .price-list-table tbody {
                display: table-row-group;
            }

            .price-list-table tr {
                display: table-row;
            }

            .price-list-table tbody tr {
                border: 0;
                border-radius: 0;
                overflow: visible;
                background: transparent;
                box-shadow: none;
            }

            .price-list-table tbody td {
                display: table-cell;
                width: auto;
                padding: 10px 12px;
                text-align: left;
                vertical-align: middle;
            }

            .price-list-table thead th {
                padding: 10px 12px;
                font-size: 10.5px;
                letter-spacing: 0.07em;
            }

            .price-list-main {
                gap: 2px;
                justify-items: start;
                text-align: left;
            }

            .price-list-main strong {
                font-size: 13px;
            }

            .price-list-subtle {
                font-size: 10px;
                line-height: 1.35;
            }

            .price-list-price {
                font-size: 13px;
                white-space: nowrap;
            }

            .price-list-status {
                padding: 6px 10px;
                font-size: 10px;
                white-space: nowrap;
            }

            .price-list-cta {
                min-width: 0;
                width: auto;
                min-height: 34px;
                padding: 0 12px;
                border-radius: 12px;
                font-size: 10.5px;
                white-space: nowrap;
            }

            .price-list-foot {
                padding: 14px 16px 16px;
                gap: 12px;
            }

            .price-list-pills {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .price-list-pill {
                min-height: 40px;
                font-size: 11px;
                padding: 8px 10px;
            }

            .price-list-footnote {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
                font-size: 12px;
            }

            .media-panel[data-media-content="images"] .media-card strong,
            .media-panel[data-media-content="images"] .media-card p {
                display: none !important;
            }

            .featured-video-mobile .section-head,
            .featured-video-mobile .featured-video-copy {
                display: none !important;
            }

            .media-panel[data-media-content="images"] .media-grid {
                display: grid;
                grid-template-columns: none;
                grid-auto-flow: column;
                grid-auto-columns: calc(50% - 6px);
                gap: 12px;
                overflow-x: auto;
                overflow-y: hidden;
                padding-bottom: 4px;
                scroll-snap-type: x proximity;
                -webkit-overflow-scrolling: touch;
            }

            .media-panel[data-media-content="images"] .media-grid::-webkit-scrollbar {
                display: none;
            }

            .media-panel[data-media-content="images"] .media-card {
                min-width: 0;
                scroll-snap-align: start;
            }

            .media-filter-group {
                gap: 6px;
                margin-bottom: 14px;
            }

            .media-subtoggle {
                padding: 6px 11px;
                font-size: 9.5px;
                line-height: 1.1;
                font-weight: 700;
            }

            .media-toolbar {
                display: block;
                overflow: visible;
                padding-bottom: 14px;
            }

            .media-tabs {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 6px;
                width: 100%;
                white-space: normal;
            }

            .media-toggle {
                width: 100%;
                min-width: 0;
                padding: 7px 3px;
                font-size: 10.5px;
                line-height: 1.1;
                text-align: center;
                white-space: nowrap;
            }

            .media-doc {
                display: grid;
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 14px;
            }

            .media-panel[data-media-content="pdfs"] .media-list {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 10px;
            }

            .media-panel[data-media-content="pdfs"] .media-doc {
                align-content: start;
                gap: 10px;
                padding: 12px 10px;
                border-radius: 16px;
            }

            .media-doc-main {
                align-items: flex-start;
                gap: 8px;
                flex-direction: column;
            }

            .media-doc-icon {
                width: 34px;
                height: 34px;
                border-radius: 10px;
                font-size: 10px;
            }

            .media-doc-copy strong {
                font-size: 12px;
                line-height: 1.2;
            }

            .media-panel[data-media-content="pdfs"] .media-doc-copy p {
                display: none;
            }

            .media-doc-action {
                width: 100%;
                min-height: 34px;
                padding: 0 8px;
                font-size: 11px;
            }

            .media-card--video {
                padding: 12px;
            }

            .media-card--video strong {
                font-size: 14px;
                line-height: 1.2;
                margin-bottom: 4px;
            }

            .media-card--video p {
                font-size: 12px;
                line-height: 1.35;
            }

            .media-card-play-badge {
                min-height: 40px;
                padding: 0 14px 0 12px;
                gap: 8px;
                font-size: 11px;
            }

            .media-card-play-icon {
                width: 24px;
                height: 24px;
                font-size: 10px;
            }

            .comparison-shell,
            .comparison-wrap,
            .media-panel[data-media-content="comparison"] {
                width: 100%;
                max-width: 100%;
                min-width: 0;
            }

            .comparison-wrap {
                overflow-x: auto;
                overflow-y: hidden;
                overscroll-behavior-x: contain;
                touch-action: pan-x;
                padding-bottom: 6px;
                contain: inline-size;
            }

            .comparison-table {
                min-width: 760px;
            }

            .comparison-scroll-hint {
                display: flex;
            }

            .travel-time-card {
                padding: 18px;
                border-radius: 22px;
            }

            .travel-time-head,
            .travel-search-row,
            .travel-results {
                grid-template-columns: 1fr;
                display: grid;
            }

            .travel-time-head {
                gap: 10px;
            }

            .travel-result-card strong {
                font-size: 1.35rem;
            }

            .media-shell {
                padding: 16px;
            }

            .finance-shell {
                padding: 16px;
                gap: 16px;
            }

            .finance-topbar,
            .finance-bottom {
                display: grid;
                grid-template-columns: 1fr;
            }

            .finance-count {
                padding-top: 0;
            }

            .finance-card {
                width: 172px;
                min-height: 150px;
            }

            .finance-track {
                gap: 12px;
                animation-duration: 26s;
            }

            .finance-cta .btn {
                width: 100%;
            }

            .other-charges-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .other-charges-table th:nth-child(2) {
                width: 44%;
            }
        }
    </style>
</head>
<body>
@php
    $sectionEventUrl = $isPreview
        ? route('projects.public-pages.preview-events', $project)
        : route('projects.public-share.events', $shareLink->token);
    $visibleGallery = $assets->where('asset_type', 'gallery_image')->values();
    $heroGallery = $visibleGallery->take(3)->values();
    $heroImage = $publicPage?->hero_cover_url ?: ($heroGallery->first()?->file_url ?: config('project_public_page.defaults.hero_image'));
    $heroSubtitle = $publicPage?->hero_subtitle ?: $project->formatted_location;
    $intro = $publicPage?->short_intro ?: 'A clean project brief with plans, media, and direct advisor access.';
    $configSummary = $unitTypes->pluck('name')->join(', ') ?: 'Available now';
    $primaryMediaAssets = $assets->whereNotIn('asset_type', ['gallery_image'])->take(4)->values();
    $firstVariant = $unitTypes->flatMap(fn($unitType) => $unitType->sizeVariants)->first();
    $travelTime = $travelTimeWidget ?? [];
    $builderMark = strtoupper(substr($project->builder?->name ?: $project->name, 0, 2));
    $heroThumbs = $heroGallery->take(4)->values();
    $galleryAssets = $assets->where('asset_type', 'gallery_image')->values();
    $videoAssets = $assets->where('asset_type', 'video')->values();
    $pdfAssets = $assets->whereIn('asset_type', ['brochure', 'price_sheet'])->values();
    $tourAssets = $assets->where('asset_type', 'tour_360')->values();
    $photosRailImage = $galleryAssets->skip(1)->first()?->file_url
        ?: $heroGallery->first()?->file_url
        ?: $firstVariant?->floor_plan_image_url
        ?: $heroImage;
    $videosRailImage = $videoAssets->first()?->preview_image_url
        ?: $tourAssets->first()?->preview_image_url
        ?: $galleryAssets->skip(2)->first()?->file_url
        ?: $heroImage;
    $heroSlots = collect([
        [
            'key' => 'overview',
            'label' => 'Overview',
            'image' => $heroImage,
            'active' => true,
        ],
        [
            'key' => 'photos',
            'label' => $galleryAssets->count() > 1 ? '+' . $galleryAssets->count() . ' Photos' : 'Photos',
            'image' => $galleryAssets->count() > 1 ? $photosRailImage : null,
            'active' => false,
        ],
        [
            'key' => 'videos',
            'label' => $videoAssets->count() > 0 ? '+' . $videoAssets->count() . ' Videos' : 'Videos',
            'image' => ($videoAssets->count() > 0 || $tourAssets->count() > 0) ? $videosRailImage : null,
            'active' => false,
        ],
    ])->filter(fn($slot) => filled($slot['image']))->values();
    $classifyMediaGroup = function (?string $text, string $type = 'image'): string {
        $value = strtolower(trim((string) $text));

        if ($type === 'video') {
            if (str_contains($value, 'site') || str_contains($value, 'construction') || str_contains($value, 'progress') || str_contains($value, 'update')) {
                return 'site';
            }

            return 'walkthrough';
        }

        if (str_contains($value, 'interior') || str_contains($value, 'bedroom') || str_contains($value, 'living') || str_contains($value, 'kitchen') || str_contains($value, 'lobby')) {
            return 'interior';
        }

        if (str_contains($value, 'exterior') || str_contains($value, 'facade') || str_contains($value, 'elevation') || str_contains($value, 'tower')) {
            return 'exterior';
        }

        if (str_contains($value, 'amenity') || str_contains($value, 'club') || str_contains($value, 'gym') || str_contains($value, 'pool') || str_contains($value, 'garden')) {
            return 'amenities';
        }

        return 'real';
    };
    $imageItems = $galleryAssets->map(function ($asset) use ($classifyMediaGroup) {
        $hintSource = trim(implode(' ', array_filter([
            $asset->title,
            $asset->tracking_key,
            data_get($asset->meta, 'category'),
            data_get($asset->meta, 'tags.0'),
        ])));

        $group = $classifyMediaGroup($hintSource, 'image');
        $groupLabel = match ($group) {
            'interior' => 'Interior',
            'exterior' => 'Exterior',
            'amenities' => 'Amenities',
            default => 'Real Images',
        };

        return [
            'id' => 'asset-' . $asset->id,
            'title' => $asset->title ?: 'Project Image',
            'image_url' => $asset->file_url,
            'href' => $asset->file_url,
            'group' => $group,
            'label' => $groupLabel,
            'tracking' => [
                'asset_id' => $asset->id,
                'tracking_key' => $asset->tracking_key,
                'panel' => 'images',
            ],
        ];
    });
    if ($imageItems->isEmpty() && $publicPage?->hero_cover_url) {
        $imageItems = collect([[
            'id' => 'hero-cover',
            'title' => ($publicPage?->hero_title ?: $project->name) . ' Cover',
            'image_url' => $publicPage->hero_cover_url,
            'href' => $publicPage->hero_cover_url,
            'group' => 'real',
            'label' => 'Real Images',
            'tracking' => [
                'asset_id' => null,
                'tracking_key' => 'hero_cover',
                'panel' => 'images',
            ],
        ]]);
    }
    $imageGroups = collect([
        'all' => $imageItems,
        'real' => $imageItems->where('group', 'real')->values(),
        'interior' => $imageItems->where('group', 'interior')->values(),
        'exterior' => $imageItems->where('group', 'exterior')->values(),
        'amenities' => $imageItems->where('group', 'amenities')->values(),
    ]);
    $imageFilterLabels = [
        'all' => 'All Images',
        'real' => 'Real Images',
        'interior' => 'Interior',
        'exterior' => 'Exterior',
        'amenities' => 'Amenities',
    ];
    $videoItems = $videoAssets->map(function ($asset) use ($classifyMediaGroup, $isPreview, $project, $shareLink) {
        $hintSource = trim(implode(' ', array_filter([
            $asset->title,
            $asset->tracking_key,
            data_get($asset->meta, 'category'),
            data_get($asset->meta, 'tags.0'),
        ])));

        $assetRoute = $isPreview
            ? route('projects.public-pages.asset', [$project, $asset])
            : route('projects.public-share.asset', [$shareLink->token, $asset]);

        $playerUrl = $asset->source_type === 'external'
            ? ($asset->external_url ?: $assetRoute)
            : ($asset->file_url ?: $assetRoute);

        return [
            'id' => 'video-' . $asset->id,
            'title' => $asset->title ?: 'Project Video',
            'copy' => 'Open walkthrough video.',
            'image_url' => $asset->preview_image_url,
            'href' => $playerUrl,
            'fallback_url' => $assetRoute,
            'group' => $classifyMediaGroup($hintSource, 'video'),
            'is_featured' => (bool) $asset->is_featured,
            'tracking' => [
                'asset_id' => $asset->id,
                'tracking_key' => $asset->tracking_key,
                'panel' => 'videos',
            ],
        ];
    });
    $videoGroups = collect([
        'all' => $videoItems,
        'walkthrough' => $videoItems->where('group', 'walkthrough')->values(),
        'site' => $videoItems->where('group', 'site')->values(),
    ]);
    $featuredVideoItem = $videoItems->first(fn ($item) => data_get($item, 'is_featured'));
    if (!$featuredVideoItem) {
        $featuredVideoItem = $videoItems->first();
    }
    $videoFilterLabels = [
        'all' => 'All Videos',
        'walkthrough' => 'Walkthrough',
        'site' => 'Site Update',
    ];
    $facts = [
        ['label' => 'Project Status', 'value' => $project->project_status ? ucfirst(str_replace('_', ' ', $project->project_status)) : 'Project brief'],
        ['label' => 'Possession', 'value' => $project->possession_date ? $project->possession_date->format('M Y') : 'On request'],
        ['label' => 'RERA', 'value' => $project->rera_no ?: 'Shared on request'],
        ['label' => 'Unit Config', 'value' => $configSummary],
        ['label' => 'Size', 'value' => $firstVariant?->builtup_area_sqft ? 'Up to ' . number_format((float) $firstVariant->builtup_area_sqft, 0) . ' Sq. Ft.' : 'Multiple layouts'],
        ['label' => 'Location', 'value' => $project->formatted_location ?: 'Premium location'],
    ];
    $towerStats = $project->towers
        ->filter(fn($tower) => $tower->floor_count || $tower->unit_count)
        ->map(fn($tower) => [
            'name' => $tower->tower_name ?: ($tower->tower_number ? 'Tower ' . $tower->tower_number : 'Tower'),
            'floor_count' => $tower->floor_count,
            'unit_count' => $tower->unit_count,
        ])
        ->values();
    $inventoryRows = $unitTypes->flatMap(function ($unitType) use ($publicPage, $project) {
        return $unitType->sizeVariants
            ->filter(fn($variant) => $variant->visible_on_public_page && $variant->status !== 'hidden')
            ->map(function ($variant) use ($unitType, $publicPage, $project) {
                $projectName = $publicPage?->hero_title ?: $project->name;
                $sizeLine = trim((string) ($variant->size_label ?: $unitType->name));
                $message = 'Hi, I want inventory details for ' . $unitType->name
                    . ($sizeLine && $sizeLine !== $unitType->name ? ' (' . $sizeLine . ')' : '')
                    . ' in ' . $projectName . '.';

                return [
                    'title' => $unitType->name,
                    'subtitle' => $sizeLine !== '' && $sizeLine !== $unitType->name ? $sizeLine : null,
                    'price' => $variant->is_price_on_request
                        ? 'On Request'
                        : str_replace(['Rs.', 'Rs ', 'Rs'], '₹', ($variant->formatted_final_price ?: 'On Request')),
                    'whatsapp_url' => 'https://wa.me/919919944401?text=' . rawurlencode($message),
                ];
            });
    })->values();
    $formatIndianMoney = function ($value) {
        $number = (float) $value;
        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('en_IN', \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
            $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);

            return $formatter->format($number);
        }

        $parts = explode('.', (string) round($number, 2));
        $integer = $parts[0];
        $lastThree = substr($integer, -3);
        $rest = substr($integer, 0, -3);
        if ($rest !== '') {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',';
        }

        return $rest . $lastThree;
    };
    $otherCharges = collect($publicPage?->other_charges ?? [])->map(function ($charge) use ($formatIndianMoney) {
        $type = data_get($charge, 'type');
        $value = trim((string) data_get($charge, 'value'));

        $formatted = match ($type) {
            'fixed' => $value !== '' ? '₹' . $formatIndianMoney($value) : 'On Request',
            'per_sqft' => $value !== '' ? '₹' . rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . ' / sq.ft.' : 'On Request',
            'percent' => $value !== '' ? rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') . '%' : 'On Request',
            default => 'On Request',
        };

        return [
            'name' => data_get($charge, 'name'),
            'type_label' => match ($type) {
                'fixed' => 'Fixed',
                'per_sqft' => 'Per Sq.ft.',
                'percent' => 'Percent',
                default => 'On Request',
            },
            'formatted_value' => $formatted,
        ];
    })->filter(fn ($charge) => filled($charge['name']))->values();
    $comparisonVariants = $unitTypes->flatMap(function ($unitType) {
        return $unitType->sizeVariants
            ->filter(fn($variant) => $variant->visible_on_public_page && $variant->status !== 'hidden')
            ->map(fn($variant) => ['unit_type' => $unitType, 'variant' => $variant]);
    })->values();
    $priceSheetAsset = $pdfAssets->first(fn ($asset) => $asset->asset_type === 'price_sheet') ?: $pdfAssets->first();
    $estimateMonthlyEmi = function ($amount) {
        $principal = (float) $amount;
        if ($principal <= 0) {
            return null;
        }

        $annualRate = 0.0875;
        $monthlyRate = $annualRate / 12;
        $months = 240;
        $emi = ($principal * $monthlyRate * ((1 + $monthlyRate) ** $months)) / ((((1 + $monthlyRate) ** $months) - 1) ?: 1);

        return $emi > 0 ? $emi : null;
    };
    $priceListRows = $comparisonVariants->take(6)->values()->map(function ($row, $index) use ($estimateMonthlyEmi, $formatIndianMoney) {
        $variant = $row['variant'];
        $priceValue = $variant->final_price ?: $variant->calculated_price;
        $emiValue = $priceValue ? $estimateMonthlyEmi($priceValue) : null;
        $statusTone = match (true) {
            (bool) $variant->is_featured && $index === 0 => 'value',
            (bool) $variant->is_featured => 'popular',
            $variant->status === 'hold' => 'hold',
            default => 'available',
        };
        $statusLabel = match ($statusTone) {
            'value' => 'Best Value',
            'popular' => 'Popular',
            'hold' => 'Limited',
            default => 'Available',
        };

        return [
            'unit_type' => $row['unit_type']->name,
            'size_label' => $variant->size_label ?: 'On request',
            'size_subtitle' => $variant->carpet_area_sqft ? number_format((float) $variant->carpet_area_sqft, 0) . ' sq.ft carpet area' : 'Carpet area on request',
            'price' => $variant->is_price_on_request
                ? 'On Request'
                : '₹' . $formatIndianMoney($priceValue),
            'emi' => $emiValue ? '₹' . $formatIndianMoney(round($emiValue)) . '/mo' : 'EMI on request',
            'status_tone' => $statusTone,
            'status_label' => $statusLabel,
            'plan_url' => $variant->id,
            'variant_id' => $variant->id,
        ];
    });
    $projectHighlights = collect(is_array($project?->project_highlights) ? $project->project_highlights : preg_split('/\r\n|\r|\n/', (string) ($project?->project_highlights ?? '')))
        ->map(fn ($item) => trim((string) $item))
        ->filter()
        ->values();
    $featuredBadges = collect($publicPage?->featured_badges ?? [])
        ->map(fn ($item) => trim((string) $item))
        ->filter()
        ->values();
    $overviewHighlights = $featuredBadges
        ->merge($projectHighlights)
        ->unique(fn ($item) => mb_strtolower($item))
        ->take(8)
        ->values();
    $galleryPreviewItems = $imageItems->take(4)->values();
    $amenityPreviewItems = ($imageGroups['amenities'] ?? collect())->take(6)->values();
    $downloadAssets = $pdfAssets->take(3)->values();
    $ctaPhone = $publicPage?->show_call && $publicPage?->call_phone ? 'tel:' . $publicPage->call_phone : null;
    $ctaWhatsapp = $publicPage?->show_whatsapp && $publicPage?->whatsapp_number
        ? 'https://wa.me/' . preg_replace('/\D+/', '', $publicPage->whatsapp_number)
        : null;
    $ctaVisit = $publicPage?->show_book_visit ? $publicPage?->book_visit_url : null;
    $ctaCallback = $publicPage?->show_request_callback ? $publicPage?->callback_url : null;
    $travelCueLabels = collect($travelTime['popular_origins'] ?? [])
        ->map(fn ($origin) => trim((string) data_get($origin, 'label')))
        ->filter()
        ->take(4)
        ->values();
@endphp

<main>
    <section class="wf-hero hero" data-track-section="hero">
        <div class="container">
            <div class="wf-shell">
                <div class="wf-topbar animate-in animate-in-delay-1">
                    <div>
                        <div class="wf-crumbs">
                            <span>Home</span>
                            <span>{{ $project->city ?: 'Projects' }}</span>
                            <span>{{ $project->area ?: 'Locality' }}</span>
                            <span>{{ $publicPage?->hero_title ?: $project->name }}</span>
                        </div>
                        <h1 class="wf-title">{{ $publicPage?->hero_title ?: $project->name }}</h1>
                        <div class="wf-meta">
                            <strong>{{ $project->formatted_location ?: 'Premium location' }}</strong>
                            <span>See on Map</span>
                            <span>{{ $project->builder?->name ?: 'Verified builder' }}</span>
                        </div>
                        <div class="wf-rating">&#9733;&#9733;&#9733;&#9733;&#9734; <span>5 Ratings</span></div>
                    </div>
                    <div class="wf-builder-tile">
                        @if($publicPage?->builder_logo_url)
                            <img src="{{ $publicPage->builder_logo_url }}" alt="{{ $project->builder?->name }}">
                        @else
                            <span>{{ $builderMark }}</span>
                        @endif
                    </div>
                </div>

                <div class="wf-hero-grid">
                    <div class="wf-stage animate-in animate-in-delay-2">
                        <div class="wf-actions">
                            @if($firstVariant)
                                <a class="wf-btn js-track-link pulse" data-event="details_pdf_download" data-section="hero" data-meta='@json(["cta" => "hero_pdf", "variant_id" => $firstVariant->id])' href="{{ $isPreview ? route('projects.public-pages.variant-pdf', [$project, $firstVariant]) : route('projects.public-share.variant-pdf', [$shareLink->token, $firstVariant]) }}">Details PDF</a>
                            @endif
                            @if($publicPage?->show_whatsapp && $publicPage?->whatsapp_number)
                                <a class="wf-btn js-track-link pulse" data-event="cta_click" data-section="hero" data-meta='@json(["cta" => "hero_whatsapp"])' href="https://wa.me/{{ preg_replace('/\D+/', '', $publicPage->whatsapp_number) }}" target="_blank">WhatsApp</a>
                            @endif
                        </div>
                        <img id="wf-hero-stage-image" src="{{ $heroImage }}" alt="{{ $project->name }}">
                    </div>

                    <div class="wf-side-column animate-in animate-in-delay-3">
                        <div class="wf-media-grid reveal">
                            @foreach($heroSlots as $slot)
                                <button type="button" class="wf-media-tab wf-media-card {{ $slot['active'] ? 'active' : '' }}" data-hero-src="{{ $slot['image'] }}">
                                    <img src="{{ $slot['image'] }}" alt="{{ $slot['label'] }}">
                                    <span class="wf-tag">{{ $slot['label'] }}</span>
                                </button>
                            @endforeach
                        </div>

                        <div class="wf-right reveal">
                            <div class="wf-why">
                                <h3>Why you should consider {{ $publicPage?->hero_title ?: $project->name }}</h3>
                                <ul>
                                    <li>{{ $intro }}</li>
                                </ul>
                                <a class="wf-view-more" href="#unit-plans">View More</a>
                            </div>

                            <div class="wf-mobile-inventory">
                                <h3>Inventory Snapshot</h3>
                                @if($inventoryRows->isNotEmpty())
                                    <div class="inventory-table">
                                        @foreach($inventoryRows->take(4) as $inventoryRow)
                                            <div class="inventory-row">
                                                <div class="inventory-size">
                                                    <span class="inventory-badge">{{ $inventoryRow['title'] }}</span>
                                                    <div class="inventory-meta">
                                                        <strong>{{ $inventoryRow['price'] }}</strong>
                                                        @if($inventoryRow['subtitle'])
                                                            <span>{{ $inventoryRow['subtitle'] }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <a class="btn btn-primary inventory-check-btn js-track-link" data-event="cta_click" data-section="hero_inventory" data-meta='@json(["cta" => "check_inventory", "size" => $inventoryRow["title"], "price" => $inventoryRow["price"]])' href="{{ $inventoryRow['whatsapp_url'] }}" target="_blank" rel="noopener"><span>Check</span></a>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="inventory-table">
                                        <div class="inventory-row">
                                            <div class="inventory-size">
                                                <span class="inventory-badge">Live</span>
                                                <div class="inventory-meta">
                                                    <strong>Inventory shared on request</strong>
                                                    <span>Chat with our team for live availability.</span>
                                                </div>
                                            </div>
                                            <a class="btn btn-primary inventory-check-btn js-track-link" data-event="cta_click" data-section="hero_inventory" data-meta='@json(["cta" => "check_inventory_fallback"])' href="https://wa.me/919919944401?text={{ rawurlencode('Hi, I want live inventory details for ' . ($publicPage?->hero_title ?: $project->name) . '.') }}" target="_blank" rel="noopener"><span>Check</span></a>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="wf-cta">
                                @if($publicPage?->show_book_visit && $publicPage?->book_visit_url)
                                    <a class="js-track-link" data-event="cta_click" data-section="hero" data-meta='@json(["cta" => "book_visit_top"])' href="{{ $publicPage->book_visit_url }}" target="_blank">Request More Information or a Callback</a>
                                @else
                                    Request More Information or a Callback
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wf-bottom-grid reveal">
                    <div class="wf-facts">
                        <div class="wf-facts-head">
                            <strong>{!! $startingFrom ? '&#8377;' . number_format($startingFrom) : 'Price On Request' !!}</strong>
                            <span class="wf-chip">Price Insights</span>
                        </div>

                        <div class="wf-facts-grid">
                            @foreach($facts as $fact)
                                <div class="wf-fact">
                                    <span>{{ $fact['label'] }}</span>
                                    <strong>{{ $fact['value'] }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <div class="container section-jump-wrap">
        <nav class="section-jump reveal" aria-label="Project page sections">
            <a href="#project-overview">Overview</a>
            @if($priceListRows->isNotEmpty())
                <a href="#project-pricing">Price List</a>
            @endif
            <a href="#unit-plans">Plans</a>
            @if($amenityPreviewItems->isNotEmpty())
                <a href="#project-amenities">Amenities</a>
            @endif
            <a href="#project-location">Location</a>
            <a href="#project-downloads">Downloads</a>
        </nav>
    </div>

    <section class="container content-grid" id="unit-plans">
        <div>
            <section class="section-card reveal section-anchor" id="project-overview" data-track-section="overview">
                <div class="section-intro-card">
                    <div class="section-intro-grid">
                        <div class="section-intro-copy">
                            <span class="section-intro-kicker">Project Overview</span>
                            <h2>{{ $publicPage?->hero_title ?: $project->name }} at a glance</h2>
                            <p>{{ $intro }}</p>
                        </div>
                        <div class="facts-panel">
                            <div class="section-head" style="margin-bottom:14px;">
                                <h3>Quick facts</h3>
                            </div>
                            <div class="wf-facts-grid">
                                @foreach(collect($facts)->take(4) as $fact)
                                    <div class="wf-fact">
                                        <span>{{ $fact['label'] }}</span>
                                        <strong>{{ $fact['value'] }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    @if($overviewHighlights->isNotEmpty())
                        <div class="badge-cluster" style="margin-top:18px;">
                            @foreach($overviewHighlights as $highlight)
                                <span class="chip">{{ $highlight }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            @if($towerStats->isNotEmpty())
                <section class="section-card reveal section-anchor tower-details-section" id="tower-details" data-track-section="tower_details">
                    <div class="section-head">
                        <div>
                            <h2>Tower Details</h2>
                            <p>Building-wise floor and unit summary.</p>
                        </div>
                    </div>
                    <div class="tower-details-grid">
                        @foreach($towerStats as $towerStat)
                            <div class="tower-detail-card">
                                <h3 class="tower-detail-name">{{ $towerStat['name'] }}</h3>
                                <div class="tower-detail-metrics">
                                    @if($towerStat['floor_count'])
                                        <div class="tower-detail-metric">
                                            <span>Total Floors</span>
                                            <strong>{{ number_format((int) $towerStat['floor_count']) }}</strong>
                                        </div>
                                    @endif
                                    @if($towerStat['unit_count'])
                                        <div class="tower-detail-metric">
                                            <span>Total Units</span>
                                            <strong>{{ number_format((int) $towerStat['unit_count']) }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($priceListRows->isNotEmpty())
                <section class="section-card reveal section-anchor" id="project-pricing" data-track-section="price_list">
                    <div class="section-head">
                        <div>
                            <h2>{{ $publicPage?->hero_title ?: $project->name }} Price List</h2>
                            <p>Quick unit comparison before plans, gallery, and brochure review.</p>
                        </div>
                        @if($priceSheetAsset)
                            @php
                                $priceSheetTrackingMeta = [
                                    'cta' => 'download_price_sheet',
                                    'asset_id' => $priceSheetAsset->id,
                                    'asset_type' => 'price_sheet',
                                    'asset_title' => $priceSheetAsset->title ?: 'Price Sheet',
                                ];
                            @endphp
                            <a class="btn btn-secondary js-track-link" data-event="price_sheet_download" data-section="price_list" data-meta='@json($priceSheetTrackingMeta)' href="{{ $isPreview ? route('projects.public-pages.asset', [$project, $priceSheetAsset]) : route('projects.public-share.asset', [$shareLink->token, $priceSheetAsset]) }}" target="_blank" rel="noopener">Download Price Sheet</a>
                        @endif
                    </div>
                    <div class="price-list-shell">
                        <div class="price-list-toolbar">
                            <div>
                                <h3>Selected unit pricing</h3>
                                <p>Approx EMI is indicative only. Final commercials and availability can change.</p>
                            </div>
                        </div>
                        <div class="price-list-table-wrap">
                            <table class="price-list-table">
                                <thead>
                                    <tr>
                                        <th>Unit Type</th>
                                        <th>Size</th>
                                        <th>Base Price</th>
                                        <th>Approx EMI</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($priceListRows as $row)
                                        <tr>
                                            <td>
                                                <div class="price-list-main">
                                                    <strong>{{ $row['unit_type'] }}</strong>
                                                    <span class="price-list-subtle">{{ $row['size_subtitle'] }}</span>
                                                </div>
                                            </td>
                                            <td class="price-list-price">{{ $row['size_label'] }}</td>
                                            <td class="price-list-price">{{ $row['price'] }}</td>
                                            <td class="price-list-price">{{ $row['emi'] }}</td>
                                            <td>
                                                <span class="price-list-status price-list-status--{{ $row['status_tone'] }}">{{ $row['status_label'] }}</span>
                                            </td>
                                            <td>
                                                <a class="price-list-cta js-track-link" data-event="details_pdf_download" data-section="price_list" data-meta='@json(["variant_id" => $row["variant_id"], "cta" => "view_plan"])' href="{{ $isPreview ? route('projects.public-pages.variant-pdf', [$project, $row['plan_url']]) : route('projects.public-share.variant-pdf', [$shareLink->token, $row['plan_url']]) }}">View Plan</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="price-list-foot">
                            <div class="price-list-pills">
                                <div class="price-list-pill">Zero Brokerage</div>
                                <div class="price-list-pill">Payment Plan Available</div>
                                <div class="price-list-pill">Home Loan Support</div>
                                <div class="price-list-pill">Price updated on request</div>
                            </div>
                            <div class="price-list-footnote">
                                <span>* EMI is approximate and prices are subject to availability.</span>
                                <strong>Compare units before booking</strong>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            @if($featuredVideoItem)
                <section class="section-card reveal featured-video-mobile" data-track-section="featured_video" style="margin-bottom:18px;">
                    <div class="section-head">
                        <div>
                            <h2>Featured Video</h2>
                            <p>Quick project walkthrough before unit plans.</p>
                        </div>
                    </div>
                    <div class="featured-video-shell">
                        <a class="featured-video-card js-track-link js-inline-video" data-event="video_start" data-section="featured_video" data-meta='@json($featuredVideoItem["tracking"])' data-video-url="{{ $featuredVideoItem['href'] }}" data-video-fallback="{{ $featuredVideoItem['fallback_url'] }}" data-video-title="{{ $featuredVideoItem['title'] }}" href="{{ $featuredVideoItem['href'] }}" rel="noopener">
                            <div class="featured-video-copy">
                                <h3>{{ $featuredVideoItem['title'] }}</h3>
                                <p>{{ $featuredVideoItem['copy'] }}</p>
                            </div>
                            <div class="media-card-media">
                                <img src="{{ $featuredVideoItem['image_url'] }}" alt="{{ $featuredVideoItem['title'] }}">
                                <div class="media-card-play">
                                    <span class="media-card-play-badge">
                                        <span class="media-card-play-icon">&#9658;</span>
                                        Play Video
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                </section>
            @endif

            <section class="section-card reveal" data-track-section="unit_plans">
                <div class="section-head">
                    <h2>Unit Plans & Details</h2>
                </div>

                <div class="unit-shell">
                    <div class="unit-toolbar">
                        <div class="unit-tabs">
                            @foreach($unitTypes as $unitIndex => $unitType)
                                <button type="button" class="unit-tab {{ $unitIndex === 0 ? 'active' : '' }}" data-tab="{{ $unitType->id }}">{{ $unitType->name }}</button>
                            @endforeach
                        </div>
                    </div>

                    @foreach($unitTypes as $unitIndex => $unitType)
                        @php
                            $variants = $unitType->sizeVariants->filter(fn($variant) => $variant->visible_on_public_page && $variant->status !== 'hidden')->values();
                        @endphp
                        <div class="unit-panel {{ $unitIndex === 0 ? 'active' : '' }}" data-panel="{{ $unitType->id }}">
                            <div class="size-row">
                                <div class="size-tabs">
                                    @foreach($variants as $variantIndex => $variant)
                                        <button type="button" class="size-tab {{ $variantIndex === 0 ? 'active' : '' }}" data-variant-target="{{ $unitType->id }}-{{ $variant->id }}">{{ $variant->size_label }}</button>
                                    @endforeach
                                </div>
                                <div class="size-hint">{{ count($variants) }} plan option{{ count($variants) === 1 ? '' : 's' }}</div>
                            </div>

                            @foreach($variants as $variantIndex => $variant)
                                @php
                                    $inventoryMessage = rawurlencode('Hi, I want inventory details for ' . $unitType->name . ' - ' . $variant->size_label . ' in ' . ($project->name ?: 'this project'));
                                @endphp
                                <article class="variant-panel {{ $variantIndex === 0 ? 'active' : '' }}" data-variant-panel="{{ $unitType->id }}-{{ $variant->id }}">
                                    <div class="variant-metrics">
                                        <div class="metrics-grid">
                                            <div class="metric metric-primary">
                                                <span>Price</span>
                                                <strong>{{ $variant->is_price_on_request ? 'On Request' : ($variant->formatted_final_price ?: 'On Request') }}</strong>
                                            </div>
                                        </div>
                                        <div class="metrics-secondary">
                                            <div class="metric">
                                                <span>Built-up Area</span>
                                                <strong>{{ $variant->builtup_area_sqft ? number_format((float) $variant->builtup_area_sqft, 0) . ' Sq.ft.' : 'N/A' }}</strong>
                                            </div>
                                            <div class="metric">
                                                <span>Carpet Area</span>
                                                <strong>{{ $variant->carpet_area_sqft ? number_format((float) $variant->carpet_area_sqft, 0) . ' Sq.ft.' : 'Not shared' }}</strong>
                                            </div>
                                        </div>
                                        <div class="variant-status-row">
                                            <span class="status-chip status-{{ \Illuminate\Support\Str::slug((string) $variant->status) }}">
                                                {{ ucfirst(str_replace('_', ' ', $variant->status)) }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="plan-view">
                                        <a href="{{ $variant->floor_plan_image_url }}" class="floor-plan js-floor-plan" data-event="floor_plan_view" data-meta='@json(["variant_id" => $variant->id])' target="_blank">
                                            <img src="{{ $variant->floor_plan_image_url }}" alt="{{ $variant->size_label }}">
                                        </a>
                                        <div class="plan-caption">
                                            <span>{{ $unitType->name }} · {{ $variant->size_label }}</span>
                                            <span>{{ $variant->is_price_on_request ? 'Price on request' : 'Ready to download' }}</span>
                                        </div>
                                        <div class="unit-mobile-summary">
                                            <div class="unit-mobile-head">
                                                <div class="unit-mobile-price">
                                                    <span>Price</span>
                                                    <strong>{{ $variant->is_price_on_request ? 'On Request' : ($variant->formatted_final_price ?: 'On Request') }}</strong>
                                                </div>
                                                <span class="status-chip status-{{ \Illuminate\Support\Str::slug((string) $variant->status) }}">
                                                    {{ ucfirst(str_replace('_', ' ', $variant->status)) }}
                                                </span>
                                            </div>
                                            <div class="unit-mobile-specs">
                                                <div class="unit-mobile-spec">
                                                    <span>Built-up</span>
                                                    <strong>{{ $variant->builtup_area_sqft ? number_format((float) $variant->builtup_area_sqft, 0) . ' Sq.ft.' : 'N/A' }}</strong>
                                                </div>
                                                <div class="unit-mobile-spec">
                                                    <span>Carpet</span>
                                                    <strong>{{ $variant->carpet_area_sqft ? number_format((float) $variant->carpet_area_sqft, 0) . ' Sq.ft.' : 'Not shared' }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="plan-actions">
                                            <div class="variant-actions">
                                                <a class="btn btn-primary inventory-wa-btn js-track-link" data-event="cta_click" data-section="unit_plans" data-meta='@json(["cta" => "check_inventory", "variant_id" => $variant->id])' href="https://wa.me/919919944401?text={{ $inventoryMessage }}" target="_blank">Check Inventory</a>
                                                <a class="btn btn-secondary js-track-link" data-event="details_pdf_download" data-section="unit_plans" data-meta='@json(["variant_id" => $variant->id])' href="{{ $isPreview ? route('projects.public-pages.variant-pdf', [$project, $variant]) : route('projects.public-share.variant-pdf', [$shareLink->token, $variant]) }}">Download Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="section-card section-anchor" id="project-media" data-track-section="media">
                <div class="section-head">
                    <div>
                        <h2>Gallery, Media & Downloads</h2>
                        <p>Images, walkthroughs, brochures, and comparison sheets in one place.</p>
                    </div>
                </div>

                <div class="media-shell">
                    <div class="media-toolbar">
                        <div class="media-tabs">
                            <button type="button" class="media-toggle active" data-media-panel="images">Images</button>
                            <button type="button" class="media-toggle" data-media-panel="videos">Video</button>
                            <button type="button" class="media-toggle" data-media-panel="pdfs">PDF</button>
                            <button type="button" class="media-toggle" data-media-panel="tours">360 Tour</button>
                            <button type="button" class="media-toggle" data-media-panel="comparison">Comparison</button>
                        </div>
                    </div>

                    <div class="media-panel active" data-media-content="images">
                        @if($imageItems->isNotEmpty())
                            <div class="media-filter-group">
                                @foreach($imageFilterLabels as $filterKey => $filterLabel)
                                    @if(($imageGroups[$filterKey] ?? collect())->isNotEmpty())
                                        <button type="button" class="media-subtoggle {{ $filterKey === 'all' ? 'active' : '' }}" data-media-subtoggle="images-{{ $filterKey }}">{{ $filterLabel }}</button>
                                    @endif
                                @endforeach
                            </div>
                            @foreach($imageFilterLabels as $filterKey => $filterLabel)
                                @php
                                    $groupItems = $imageGroups[$filterKey] ?? collect();
                                @endphp
                                @if($groupItems->isNotEmpty())
                                    <div class="media-subpanel {{ $filterKey === 'all' ? 'active' : '' }}" data-media-subpanel="images-{{ $filterKey }}">
                                        <div class="media-grid">
                                            @foreach($groupItems as $item)
                                                <a class="media-card media-card--visual js-track-link" data-event="{{ in_array(($item['tracking']['asset_type'] ?? null), ['price_sheet', 'brochure'], true) ? 'brochure_download' : 'section_view' }}" data-section="media" data-meta='@json($item["tracking"])' href="{{ $item['href'] }}" target="_blank" aria-label="{{ $item['title'] }}">
                                                    <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] }}">
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="media-empty">Images will appear here once project visuals are added.</div>
                        @endif
                    </div>

                    <div class="media-panel" data-media-content="videos">
                        @if($videoItems->isNotEmpty())
                            <div class="media-filter-group">
                                @foreach($videoFilterLabels as $filterKey => $filterLabel)
                                    @if(($videoGroups[$filterKey] ?? collect())->isNotEmpty())
                                        <button type="button" class="media-subtoggle {{ $filterKey === 'all' ? 'active' : '' }}" data-media-subtoggle="videos-{{ $filterKey }}">{{ $filterLabel }}</button>
                                    @endif
                                @endforeach
                            </div>
                            @foreach($videoFilterLabels as $filterKey => $filterLabel)
                                @php
                                    $groupItems = $videoGroups[$filterKey] ?? collect();
                                @endphp
                                @if($groupItems->isNotEmpty())
                                    <div class="media-subpanel {{ $filterKey === 'all' ? 'active' : '' }}" data-media-subpanel="videos-{{ $filterKey }}">
                                        <div class="media-grid">
                                            @foreach($groupItems as $item)
                                                <a class="media-card media-card--video js-track-link js-inline-video" data-event="video_start" data-section="media" data-meta='@json($item["tracking"])' data-video-url="{{ $item['href'] }}" data-video-fallback="{{ $item['fallback_url'] }}" data-video-title="{{ $item['title'] }}" href="{{ $item['href'] }}" rel="noopener">
                                                    <strong>{{ $item['title'] }}</strong>
                                                    <p>{{ $item['copy'] }}</p>
                                                    <div class="media-card-media">
                                                        <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] }}">
                                                        <div class="media-card-play">
                                                            <span class="media-card-play-badge">
                                                                <span class="media-card-play-icon">&#9658;</span>
                                                                Play Video
                                                            </span>
                                                        </div>
                                                    </div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @else
                            <div class="media-empty">Videos will appear here once project videos are added.</div>
                        @endif
                    </div>

                    <div class="media-panel" data-media-content="pdfs">
                        <div class="media-list">
                            @foreach($pdfAssets as $asset)
                                <div class="media-doc">
                                    <div class="media-doc-main">
                                        <div class="media-doc-icon">PDF</div>
                                        <div class="media-doc-copy">
                                            <strong>{{ $asset->title ?: ucwords(str_replace('_', ' ', $asset->asset_type)) }}</strong>
                                            <p>Brochure or price sheet</p>
                                        </div>
                                    </div>
                                    @php
                                        $mediaDownloadTrackingMeta = [
                                            'asset_id' => $asset->id,
                                            'asset_type' => $asset->asset_type,
                                            'asset_title' => $asset->title ?: 'Project PDF',
                                            'tracking_key' => $asset->tracking_key,
                                            'panel' => 'pdfs',
                                            'cta' => 'media_download',
                                        ];
                                    @endphp
                                    <a class="btn btn-secondary media-doc-action js-track-link" data-event="{{ $asset->asset_type === 'price_sheet' ? 'price_sheet_download' : 'brochure_download' }}" data-section="media" data-meta='@json($mediaDownloadTrackingMeta)' href="{{ $isPreview ? route('projects.public-pages.asset', [$project, $asset]) : route('projects.public-share.asset', [$shareLink->token, $asset]) }}" target="_blank">Download</a>
                                </div>
                            @endforeach

                            @if($firstVariant)
                                <div class="media-doc">
                                    <div class="media-doc-main">
                                        <div class="media-doc-icon">PDF</div>
                                        <div class="media-doc-copy">
                                            <strong>Details PDF</strong>
                                            <p>Floor plan, area, and pricing sheet</p>
                                        </div>
                                    </div>
                                    <a class="btn btn-secondary media-doc-action js-track-link" data-event="details_pdf_download" data-section="media" data-meta='@json(["variant_id" => $firstVariant->id, "panel" => "pdfs"])' href="{{ $isPreview ? route('projects.public-pages.variant-pdf', [$project, $firstVariant]) : route('projects.public-share.variant-pdf', [$shareLink->token, $firstVariant]) }}">Download</a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="media-panel" data-media-content="tours">
                        <div class="media-grid">
                            @foreach($tourAssets as $asset)
                                <a class="media-card js-track-link" data-event="tour_360_open" data-section="media" data-meta='@json(["asset_id" => $asset->id, "tracking_key" => $asset->tracking_key, "panel" => "tours"])' href="{{ $isPreview ? route('projects.public-pages.asset', [$project, $asset]) : route('projects.public-share.asset', [$shareLink->token, $asset]) }}" target="_blank">
                                    <strong>{{ $asset->title ?: '360 Tour' }}</strong>
                                    <p>Open immersive tour.</p>
                                    <img src="{{ $asset->preview_image_url }}" alt="{{ $asset->title }}">
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="media-panel" data-media-content="comparison">
                        <div class="comparison-shell">
                            <div class="comparison-wrap">
                                <table class="comparison-table">
                                    <thead>
                                        <tr>
                                            <th>Unit Type</th>
                                            <th>Size</th>
                                            <th>Built-up Area</th>
                                            <th>Carpet Area</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th>PDF</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($comparisonVariants as $row)
                                            @php
                                                $variant = $row['variant'];
                                            @endphp
                                            <tr>
                                                <td><strong>{{ $row['unit_type']->name }}</strong></td>
                                                <td>{{ $variant->size_label }}</td>
                                                <td>{{ $variant->builtup_area_sqft ? number_format((float) $variant->builtup_area_sqft, 0) . ' Sq.ft.' : 'N/A' }}</td>
                                                <td>{{ $variant->carpet_area_sqft ? number_format((float) $variant->carpet_area_sqft, 0) . ' Sq.ft.' : 'Not shared' }}</td>
                                                <td>{{ $variant->is_price_on_request ? 'On Request' : ($variant->formatted_final_price ?: 'On Request') }}</td>
                                                <td>{{ ucfirst(str_replace('_', ' ', $variant->status)) }}</td>
                                                <td>
                                                    <a class="js-track-link" data-event="details_pdf_download" data-section="media" data-meta='@json(["variant_id" => $variant->id, "panel" => "comparison"])' href="{{ $isPreview ? route('projects.public-pages.variant-pdf', [$project, $variant]) : route('projects.public-share.variant-pdf', [$shareLink->token, $variant]) }}">Open PDF</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="comparison-scroll-hint">Swipe left and right</div>
                        </div>
                    </div>
                </div>
            </section>

            @if($amenityPreviewItems->isNotEmpty())
                <section class="section-card reveal section-anchor" id="project-amenities" data-track-section="amenities_preview">
                    <div class="section-head">
                        <div>
                            <h2>Amenities & Lifestyle</h2>
                            <p>Public-facing amenity visuals pulled from the current project gallery.</p>
                        </div>
                    </div>
                    <div class="amenity-preview-grid">
                        @foreach($amenityPreviewItems as $item)
                            <a class="media-card js-track-link" data-event="section_view" data-section="amenities_preview" data-meta='@json(["group" => $item["group"], "label" => $item["label"]])' href="{{ $item['href'] }}" target="_blank" rel="noopener">
                                <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] }}">
                                <div class="media-card-copy">
                                    <strong>{{ $item['title'] }}</strong>
                                    <p>{{ $item['label'] }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($otherCharges->isNotEmpty())
                <section class="section-card reveal" data-track-section="other_charges">
                    <div class="other-charges-shell">
                        <div class="other-charges-head">
                            <h3>Other Charges</h3>
                            <span>Additional project-level charges</span>
                        </div>
                        <table class="other-charges-table">
                            <thead>
                                <tr>
                                    <th>Charge</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($otherCharges as $charge)
                                    <tr>
                                        <td><strong>{{ $charge['name'] }}</strong></td>
                                        <td>{{ $charge['type_label'] }}</td>
                                        <td>{{ $charge['formatted_value'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="other-charges-note">Charges are indicative and may vary.</p>
                    </div>
                </section>
            @endif

            @php
                $financeCtaUrl = $publicPage?->callback_url ?: ($publicPage?->book_visit_url ?: ($publicPage?->show_call && $publicPage?->call_phone ? 'tel:' . $publicPage->call_phone : null));
                $financeCtaLabel = $publicPage?->callback_url ? 'Request Callback' : ($publicPage?->book_visit_url ? 'Check Eligibility' : 'Talk to Advisor');
            @endphp

            @if(($loanPartners ?? collect())->isNotEmpty())
                <section class="section-card reveal" data-track-section="finance_partners">
                    <div class="finance-shell">
                        <div class="finance-topbar">
                            <div class="finance-heading">
                                <span class="finance-kicker">Finance Partners</span>
                                <h3>Home Loan Available With</h3>
                                <p>Loan assistance available through leading banks and housing finance partners.</p>
                            </div>
                            <div class="finance-count">{{ $loanPartners->count() }} Banks</div>
                        </div>

                        <div class="finance-marquee" aria-label="Finance partners">
                            <div class="finance-track">
                                @foreach($loanPartners as $bank)
                                    <article class="finance-card" title="{{ $bank->name }}{{ $bank->short_offer_text ? ' — ' . $bank->short_offer_text : '' }}">
                                        <div class="finance-logo">
                                            @if($bank->logo_url)
                                                <img src="{{ $bank->logo_url }}" alt="{{ $bank->name }}" loading="lazy">
                                            @else
                                                <span>{{ strtoupper(\Illuminate\Support\Str::of($bank->name)->replaceMatches('/[^A-Za-z0-9 ]+/', '')->explode(' ')->filter()->map(fn($word) => substr($word, 0, 1))->take(3)->join('')) }}</span>
                                            @endif
                                        </div>
                                        <div class="finance-body">
                                            <strong>{{ $bank->name }}</strong>
                                            <span>{{ $bank->short_offer_text ?: 'Home Loan Available' }}</span>
                                        </div>
                                        <span class="finance-chip">{{ $bank->interest_rate_text ?: 'Partner' }}</span>
                                    </article>
                                @endforeach
                                @foreach($loanPartners as $bank)
                                    <article class="finance-card" aria-hidden="true">
                                        <div class="finance-logo">
                                            @if($bank->logo_url)
                                                <img src="{{ $bank->logo_url }}" alt="" loading="lazy">
                                            @else
                                                <span>{{ strtoupper(\Illuminate\Support\Str::of($bank->name)->replaceMatches('/[^A-Za-z0-9 ]+/', '')->explode(' ')->filter()->map(fn($word) => substr($word, 0, 1))->take(3)->join('')) }}</span>
                                            @endif
                                        </div>
                                        <div class="finance-body">
                                            <strong>{{ $bank->name }}</strong>
                                            <span>{{ $bank->short_offer_text ?: 'Home Loan Available' }}</span>
                                        </div>
                                        <span class="finance-chip">{{ $bank->interest_rate_text ?: 'Partner' }}</span>
                                    </article>
                                @endforeach
                            </div>
                        </div>

                        <div class="finance-bottom">
                            <p class="finance-footnote">Offers subject to bank policy and approval. Final eligibility, ROI, and processing depend on bank review and applicant profile.</p>
                            @if($financeCtaUrl)
                                <div class="finance-cta">
                                    <a class="btn btn-primary js-track-link" data-event="cta_click" data-section="finance_partners" data-meta='@json(["cta" => "finance_callback"])' href="{{ $financeCtaUrl }}" @if(!str_starts_with($financeCtaUrl, 'tel:')) target="_blank" @endif>{{ $financeCtaLabel }}</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            <div class="wf-mobile-facts reveal">
                <div class="wf-facts">
                    <div class="wf-facts-head">
                        <strong>{!! $startingFrom ? '&#8377;' . number_format($startingFrom) : 'Price On Request' !!}</strong>
                        <span class="wf-chip">Price Insights</span>
                    </div>

                    <div class="wf-facts-grid">
                        @foreach($facts as $fact)
                            <div class="wf-fact">
                                <span>{{ $fact['label'] }}</span>
                                <strong>{{ $fact['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <section class="section-card section-anchor" id="project-location" data-track-section="location">
                <div class="section-head">
                    <div>
                        <h2>Location & Travel Cues</h2>
                        <p>Map access, landmarks, and quick travel context for buyers.</p>
                    </div>
                </div>

                @php
                    $mapEmbedMarkup = null;
                    $mapEmbedUrl = null;
                    $projectLatitude = $publicPage?->latitude;
                    $projectLongitude = $publicPage?->longitude;
                    $projectZoom = (int) ($publicPage?->map_zoom ?: 15);
                    $rawMapValue = trim((string) ($publicPage?->map_embed ?? ''));

                    if ($rawMapValue !== '' && str_contains($rawMapValue, '<iframe')) {
                        $mapEmbedMarkup = $rawMapValue;
                    } elseif (is_numeric($projectLatitude) && is_numeric($projectLongitude)) {
                        $mapEmbedUrl = 'https://www.google.com/maps?q=' . $projectLatitude . ',' . $projectLongitude . '&z=' . $projectZoom . '&hl=en&output=embed';
                    } elseif ($rawMapValue !== '' && filter_var($rawMapValue, FILTER_VALIDATE_URL)) {
                        $mapEmbedUrl = str_contains($rawMapValue, 'output=embed')
                            ? $rawMapValue
                            : $rawMapValue . (str_contains($rawMapValue, '?') ? '&' : '?') . 'output=embed';
                    }
                @endphp

                <div class="map-box">
                    @if($mapEmbedMarkup)
                        {!! $mapEmbedMarkup !!}
                    @elseif($mapEmbedUrl)
                        <iframe
                            src="{{ $mapEmbedUrl }}"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen
                            title="Project location map">
                        </iframe>
                    @elseif($publicPage?->map_embed)
                        <div>{{ $publicPage->map_embed }}</div>
                    @else
                        <div>{{ $project->formatted_location ?: 'Location to be updated' }}</div>
                    @endif
                </div>

                @if($landmarks->isNotEmpty())
                    <div class="landmark-grid" style="margin-top:16px;">
                        @foreach($landmarks as $landmark)
                            <div class="landmark-card">
                                <strong>{{ $landmark->label }}</strong>
                                <p>{{ $landmark->type ?: 'Landmark' }}</p>
                                <span>{{ $landmark->distance_text ?: 'Nearby' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="travel-time-card" id="travel-time-widget"
                    data-enabled='{{ ($travelTime["enabled"] ?? false) ? "true" : "false" }}'
                    data-suggestions-url='{{ e((string) ($travelTime["suggestions_url"] ?? "")) }}'
                    data-route-url='{{ e((string) ($travelTime["route_url"] ?? "")) }}'
                    data-min-query='{{ (int) data_get($travelTime, "ui.min_query_length", 3) }}'
                    data-debounce='{{ (int) data_get($travelTime, "ui.search_debounce_ms", 350) }}'
                    data-project='@json($travelTime["destination"] ?? null)'
                    data-origins='@json($travelTime["popular_origins"] ?? [])'
                    data-trust='{{ e((string) ($travelTime["trust_text"] ?? "Approx travel time based on map data")) }}'
                    data-fallback='{{ e((string) ($travelTime["fallback_message"] ?? "Travel time will be available once project location is added.")) }}'>
                    <div class="travel-time-head">
                        <div>
                            <h3>How far is this project from you?</h3>
                            <p>{{ $travelTime['enabled'] ?? false ? 'Choose a quick origin or search your own starting point.' : ($travelTime['fallback_message'] ?? 'Travel time will be available once project location is added.') }}</p>
                        </div>
                    </div>

                    @if(($travelTime['enabled'] ?? false) && !empty($travelTime['popular_origins']))
                        <div class="travel-time-origins">
                            @foreach($travelTime['popular_origins'] as $origin)
                                <button type="button" class="travel-origin-chip" data-origin='@json($origin)'>
                                    <span>{{ $origin['label'] }}</span>
                                    @if(!empty($origin['category']))
                                        <small>{{ $origin['category'] }}</small>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if($travelTime['enabled'] ?? false)
                        <div class="travel-search-shell">
                            <div class="travel-search-row">
                                <input type="text" class="travel-search-input" id="travel-search-input" placeholder="{{ $travelTime['placeholder'] ?? 'Enter your location' }}" autocomplete="off">
                                <button type="button" class="travel-current-btn" id="travel-current-btn">Use my current location</button>
                            </div>
                            <p class="travel-time-hint" style="margin-top:8px;">{{ $travelTime['helper_hint'] ?? 'e.g. Hazratganj, Airport' }}</p>
                            <div class="travel-suggestions" id="travel-suggestions"></div>
                        </div>

                        <div class="travel-selected-origin" id="travel-selected-origin"></div>
                        <div class="travel-time-state" id="travel-time-state" style="margin-top:14px;"></div>
                        <div class="travel-results" id="travel-results" hidden></div>
                        <div class="travel-cta-row" id="travel-cta-row" hidden>
                            @if($publicPage?->show_book_visit && $publicPage?->book_visit_url)
                                <a class="primary js-track-link" data-event="cta_click" data-section="travel_time" data-meta='@json(["cta" => "schedule_site_visit"])' href="{{ $publicPage->book_visit_url }}" target="_blank">Schedule Site Visit</a>
                            @endif
                            @if($publicPage?->show_whatsapp && $publicPage?->whatsapp_number)
                                <a class="secondary js-track-link" id="travel-whatsapp-link" data-event="cta_click" data-section="travel_time" data-meta='@json(["cta" => "travel_whatsapp"])' href="https://wa.me/{{ preg_replace('/\D+/', '', $publicPage->whatsapp_number) }}" target="_blank">Get Directions on WhatsApp</a>
                            @endif
                            <a class="secondary js-track-link" id="travel-directions-link" data-event="cta_click" data-section="travel_time" data-meta='@json(["cta" => "travel_open_directions"])' href="#" target="_blank" rel="noopener">Open Directions</a>
                        </div>
                        <button type="button" class="travel-clear-btn" id="travel-clear-btn" hidden>Clear</button>
                        <p class="travel-time-trust" style="margin-top:14px;">{{ $travelTime['trust_text'] ?? 'Approx travel time based on map data' }}</p>
                    @endif
                </div>
            </section>

            <section class="section-card reveal section-anchor" id="project-downloads" data-track-section="cta_support">
                <div class="cta-band">
                    <div>
                        <span class="section-intro-kicker">Consult & Downloads</span>
                        <h2>Get the project brief, pricing help, and site-visit support</h2>
                        <p>Use direct customer actions only. Hidden CRM sections stay hidden automatically if no public data is available.</p>
                        @if($travelCueLabels->isNotEmpty())
                            <ul>
                                @foreach($travelCueLabels as $travelCueLabel)
                                    <li>{{ $travelCueLabel }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div>
                        <div class="cta-band-actions">
                            @if($ctaPhone)
                                <a class="btn btn-primary js-track-link" data-event="cta_click" data-section="cta_support" data-meta='@json(["cta" => "call_advisor"])' href="{{ $ctaPhone }}">Call Advisor</a>
                            @endif
                            @if($ctaWhatsapp)
                                <a class="btn btn-secondary js-track-link" data-event="cta_click" data-section="cta_support" data-meta='@json(["cta" => "whatsapp_advisor"])' href="{{ $ctaWhatsapp }}" target="_blank" rel="noopener">WhatsApp</a>
                            @endif
                            @if($ctaVisit)
                                <a class="btn btn-secondary js-track-link" data-event="cta_click" data-section="cta_support" data-meta='@json(["cta" => "book_visit"])' href="{{ $ctaVisit }}" target="_blank" rel="noopener">Book Visit</a>
                            @endif
                            @if($ctaCallback)
                                <a class="btn btn-secondary js-track-link" data-event="cta_click" data-section="cta_support" data-meta='@json(["cta" => "callback"])' href="{{ $ctaCallback }}" target="_blank" rel="noopener">Request Callback</a>
                            @endif
                            @foreach($downloadAssets as $asset)
                                @php
                                    $supportDownloadTrackingMeta = [
                                        'cta' => 'download_asset',
                                        'asset_id' => $asset->id,
                                        'asset_type' => $asset->asset_type,
                                        'asset_title' => $asset->title ?: 'Download PDF',
                                    ];
                                @endphp
                                <a class="btn btn-secondary js-track-link" data-event="{{ $asset->asset_type === 'price_sheet' ? 'price_sheet_download' : 'brochure_download' }}" data-section="cta_support" data-meta='@json($supportDownloadTrackingMeta)' href="{{ $isPreview ? route('projects.public-pages.asset', [$project, $asset]) : route('projects.public-share.asset', [$shareLink->token, $asset]) }}" target="_blank" rel="noopener">{{ $asset->title ?: 'Download PDF' }}</a>
                            @endforeach
                            @if($firstVariant)
                                <a class="btn btn-secondary js-track-link" data-event="details_pdf_download" data-section="cta_support" data-meta='@json(["cta" => "download_details_pdf", "variant_id" => $firstVariant->id])' href="{{ $isPreview ? route('projects.public-pages.variant-pdf', [$project, $firstVariant]) : route('projects.public-share.variant-pdf', [$shareLink->token, $firstVariant]) }}">Details PDF</a>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="unit-side-rail">
            @if($featuredVideoItem)
                <section class="section-card reveal featured-video-desktop" data-track-section="featured_video_desktop">
                    <div class="section-head">
                        <div>
                            <h2>Featured Video</h2>
                            <p>Quick project walkthrough before unit plans.</p>
                        </div>
                    </div>
                    <div class="featured-video-shell">
                        <a class="featured-video-card js-track-link js-inline-video" data-event="video_start" data-section="featured_video" data-meta='@json($featuredVideoItem["tracking"])' data-video-url="{{ $featuredVideoItem['href'] }}" data-video-fallback="{{ $featuredVideoItem['fallback_url'] }}" data-video-title="{{ $featuredVideoItem['title'] }}" href="{{ $featuredVideoItem['href'] }}" rel="noopener">
                            <div class="featured-video-copy">
                                <h3>{{ $featuredVideoItem['title'] }}</h3>
                                <p>{{ $featuredVideoItem['copy'] }}</p>
                            </div>
                            <div class="media-card-media">
                                <img src="{{ $featuredVideoItem['image_url'] }}" alt="{{ $featuredVideoItem['title'] }}">
                                <div class="media-card-play">
                                    <span class="media-card-play-badge">
                                        <span class="media-card-play-icon">&#9658;</span>
                                        Play Video
                                    </span>
                                </div>
                            </div>
                        </a>
                    </div>
                </section>
            @endif

        <aside class="advisor-card reveal" data-track-section="advisor_cta">
            <div class="advisor-head">
                <div class="advisor-mark">{{ strtoupper(substr($advisorName, 0, 2)) }}</div>
                <div>
                    <strong>{{ $advisorName }}</strong>
                    <p>{{ $project->builder?->name ?: 'Property Desk Advisor' }}</p>
                </div>
            </div>

            <div class="advisor-actions">
                @if($publicPage?->show_call && $publicPage?->call_phone)
                    <a class="btn btn-primary js-track-link" data-event="cta_click" data-section="advisor_cta" data-meta='@json(["cta" => "call"])' href="tel:{{ $publicPage->call_phone }}">Call Advisor</a>
                @endif
                @if($publicPage?->show_whatsapp && $publicPage?->whatsapp_number)
                    <a class="btn btn-secondary js-track-link" data-event="cta_click" data-section="advisor_cta" data-meta='@json(["cta" => "whatsapp"])' href="https://wa.me/{{ preg_replace('/\D+/', '', $publicPage->whatsapp_number) }}" target="_blank">WhatsApp Advisor</a>
                @endif
                @if($publicPage?->show_book_visit && $publicPage?->book_visit_url)
                    <a class="btn btn-secondary js-track-link" data-event="cta_click" data-section="advisor_cta" data-meta='@json(["cta" => "book_visit"])' href="{{ $publicPage->book_visit_url }}" target="_blank">Book Visit</a>
                @endif
                @if($publicPage?->show_request_callback && $publicPage?->callback_url)
                    <a class="btn btn-secondary js-track-link" data-event="cta_click" data-section="advisor_cta" data-meta='@json(["cta" => "callback"])' href="{{ $publicPage->callback_url }}" target="_blank">Request Callback</a>
                @endif
            </div>
        </aside>
        </div>
    </section>
</main>

<div class="video-modal" id="inline-video-modal" aria-hidden="true">
    <div class="video-modal-dialog">
        <button type="button" class="video-modal-close" id="inline-video-close" aria-label="Close video">&times;</button>
        <div class="video-modal-frame" id="inline-video-frame"></div>
    </div>
</div>

<script>
    (() => {
        const eventUrl = @json($sectionEventUrl);
        const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const sessionKey = 'project-public-page-session';
        const sessionId = localStorage.getItem(sessionKey) || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        localStorage.setItem(sessionKey, sessionId);
        const visitId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
        const isPreviewMode = @json($isPreview);
        const clientHints = {
            screen_width: window.screen?.width || window.innerWidth || null,
            screen_height: window.screen?.height || window.innerHeight || null,
            language: navigator.language || null,
        };
        const downloadEvents = new Set(['price_sheet_download', 'brochure_download', 'details_pdf_download']);
        const seenSections = new Set();

        function buildTrackingMeta(meta = {}) {
            return Object.assign({
                visit_id: visitId,
                preview: isPreviewMode,
                client_hints: clientHints,
            }, meta || {});
        }

        function track(eventName, section, meta = {}, durationMs = null) {
            return fetch(eventUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    event_name: eventName,
                    section,
                    session_id: sessionId,
                    duration_ms: durationMs,
                    meta: buildTrackingMeta(meta),
                }),
                keepalive: true,
            }).catch(() => null);
        }

        track('page_view', 'hero', {});

        let pageVisibleAt = Date.now();

        const flushDuration = (force = false) => {
            if (document.hidden && !force) {
                return;
            }

            const now = Date.now();
            const durationMs = Math.max(0, now - pageVisibleAt);
            pageVisibleAt = now;

            if (durationMs < 3000 && !force) {
                return;
            }

            track('duration', document.body.dataset.trackSection || 'page', {}, durationMs);
        };

        const durationHeartbeat = window.setInterval(() => {
            if (!document.hidden) {
                flushDuration();
            }
        }, 15000);

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                flushDuration(true);
                return;
            }

            pageVisibleAt = Date.now();
        });

        window.addEventListener('beforeunload', () => {
            flushDuration(true);
            window.clearInterval(durationHeartbeat);
        });

        const cleanDatasetString = (value) => {
            if (typeof value !== 'string') {
                return value;
            }

            let normalized = value.trim();
            if (normalized.startsWith('"') && normalized.endsWith('"')) {
                normalized = normalized.slice(1, -1);
            }

            return normalized.replace(/\\\//g, '/');
        };

        const travelWidget = document.getElementById('travel-time-widget');
        if (travelWidget && travelWidget.dataset.enabled === 'true') {
            const suggestionsUrl = cleanDatasetString(travelWidget.dataset.suggestionsUrl);
            const routeUrl = cleanDatasetString(travelWidget.dataset.routeUrl);
            const minQueryLength = Number(travelWidget.dataset.minQuery || 3);
            const debounceMs = Number(travelWidget.dataset.debounce || 350);
            const searchInput = document.getElementById('travel-search-input');
            const suggestionsBox = document.getElementById('travel-suggestions');
            const selectedOriginBox = document.getElementById('travel-selected-origin');
            const resultsBox = document.getElementById('travel-results');
            const stateBox = document.getElementById('travel-time-state');
            const clearBtn = document.getElementById('travel-clear-btn');
            const currentBtn = document.getElementById('travel-current-btn');
            const ctaRow = document.getElementById('travel-cta-row');
            const directionsLink = document.getElementById('travel-directions-link');
            const whatsappLink = document.getElementById('travel-whatsapp-link');
            let debounceTimer = null;
            let highlightedIndex = -1;
            let currentSuggestions = [];
            let selectedOrigin = null;

            const showState = (message = '') => {
                stateBox.textContent = message;
            };

            const hideSuggestions = () => {
                suggestionsBox.classList.remove('visible');
                suggestionsBox.innerHTML = '';
                currentSuggestions = [];
                highlightedIndex = -1;
            };

            const resetTravelWidget = (preserveInput = false) => {
                selectedOrigin = null;
                if (!preserveInput) {
                    searchInput.value = '';
                }
                selectedOriginBox.classList.remove('visible');
                selectedOriginBox.innerHTML = '';
                resultsBox.hidden = true;
                resultsBox.innerHTML = '';
                ctaRow.hidden = true;
                clearBtn.hidden = true;
                directionsLink.setAttribute('href', '#');
                showState('');
                hideSuggestions();
            };

            const renderResults = (payload) => {
                const cards = [];
                ['drive', 'walk'].forEach((mode) => {
                    const row = payload[mode];
                    if (!row) {
                        return;
                    }
                    cards.push(`
                        <article class="travel-result-card">
                            <span class="eyebrow">${row.label}</span>
                            <strong>${row.display_time}</strong>
                            <span>${row.display_distance}</span>
                        </article>
                    `);
                });

                if (!cards.length) {
                    resultsBox.hidden = true;
                    ctaRow.hidden = true;
                    showState(payload.message || 'Travel time unavailable for this route.');
                    return;
                }

                resultsBox.innerHTML = cards.join('');
                resultsBox.hidden = false;
                ctaRow.hidden = false;
                clearBtn.hidden = false;
                showState('');
                if (payload.directions_url) {
                    directionsLink.setAttribute('href', payload.directions_url);
                    if (whatsappLink) {
                        const url = new URL(whatsappLink.getAttribute('href'));
                        url.searchParams.set('text', `Directions to ${payload.destination?.label || 'project'}: ${payload.directions_url}`);
                        whatsappLink.setAttribute('href', url.toString());
                    }
                }
            };

            const showLoadingResults = (label) => {
                selectedOriginBox.classList.add('visible');
                selectedOriginBox.innerHTML = `<strong>${label}</strong>`;
                resultsBox.hidden = false;
                resultsBox.innerHTML = `
                    <div class="travel-result-skeleton"><div></div><div></div><div></div></div>
                    <div class="travel-result-skeleton"><div></div><div></div><div></div></div>
                `;
                ctaRow.hidden = true;
                clearBtn.hidden = false;
                showState('Checking route...');
            };

            const fetchRoute = (origin) => {
                selectedOrigin = origin;
                showLoadingResults(origin.label);

                return fetch(routeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ origin }),
                })
                    .then((response) => response.json())
                    .then((payload) => {
                        selectedOriginBox.classList.add('visible');
                        selectedOriginBox.innerHTML = `<strong>${payload.origin?.label || origin.label}</strong>${origin.category ? `<div style="margin-top:4px;color:#60756c;">${origin.category}</div>` : ''}`;
                        renderResults(payload);
                    })
                    .catch(() => {
                        resultsBox.hidden = true;
                        ctaRow.hidden = true;
                        showState('Travel time unavailable for this route.');
                    });
            };

            const renderSuggestions = (results) => {
                currentSuggestions = results;
                if (!results.length) {
                    suggestionsBox.innerHTML = `<div class="travel-suggestion active">No matching places found</div>`;
                    suggestionsBox.classList.add('visible');
                    highlightedIndex = -1;
                    return;
                }

                suggestionsBox.innerHTML = results.map((result, index) => `
                    <button type="button" class="travel-suggestion ${index === 0 ? 'active' : ''}" data-index="${index}">
                        ${result.label}
                    </button>
                `).join('');
                suggestionsBox.classList.add('visible');
                highlightedIndex = 0;
            };

            const requestSuggestions = (query) => {
                showState('Searching locations...');
                const url = new URL(suggestionsUrl, window.location.origin);
                url.searchParams.set('q', query);
                fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
                    .then((response) => response.json())
                    .then((payload) => {
                        showState('');
                        renderSuggestions(payload.results || []);
                    })
                    .catch(() => {
                        showState('No matching places found');
                    });
            };

            travelWidget.querySelectorAll('.travel-origin-chip').forEach((button) => {
                button.addEventListener('click', () => {
                    const origin = Object.assign({ source: 'popular' }, JSON.parse(button.dataset.origin || '{}'));
                    searchInput.value = origin.label || '';
                    hideSuggestions();
                    fetchRoute(origin);
                });
            });

            searchInput.addEventListener('input', () => {
                const query = searchInput.value.trim();
                resetTravelWidget(true);
                if (query.length < minQueryLength) {
                    return;
                }
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => requestSuggestions(query), debounceMs);
            });

            searchInput.addEventListener('keydown', (event) => {
                if (!currentSuggestions.length) {
                    return;
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    highlightedIndex = Math.min(currentSuggestions.length - 1, highlightedIndex + 1);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    highlightedIndex = Math.max(0, highlightedIndex - 1);
                } else if (event.key === 'Enter' && highlightedIndex >= 0) {
                    event.preventDefault();
                    const origin = Object.assign({ source: 'search' }, currentSuggestions[highlightedIndex]);
                    searchInput.value = origin.label;
                    hideSuggestions();
                    fetchRoute(origin);
                    return;
                } else {
                    return;
                }

                suggestionsBox.querySelectorAll('.travel-suggestion').forEach((button, index) => {
                    button.classList.toggle('active', index === highlightedIndex);
                });
            });

            suggestionsBox.addEventListener('click', (event) => {
                const option = event.target.closest('.travel-suggestion[data-index]');
                if (!option) {
                    return;
                }
                const origin = Object.assign({ source: 'search' }, currentSuggestions[Number(option.dataset.index)]);
                searchInput.value = origin.label;
                hideSuggestions();
                fetchRoute(origin);
            });

            clearBtn.addEventListener('click', () => resetTravelWidget());
            currentBtn.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    return;
                }

                navigator.geolocation.getCurrentPosition((position) => {
                    const origin = {
                        label: 'Current location',
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        source: 'current_location',
                    };
                    searchInput.value = origin.label;
                    fetchRoute(origin);
                }, () => {
                    // Silent fail by design.
                }, {
                    enableHighAccuracy: true,
                    maximumAge: 60000,
                    timeout: 10000,
                });
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('.travel-search-shell')) {
                    hideSuggestions();
                }
            });
        } else if (travelWidget) {
            const fallback = travelWidget.dataset.fallback || 'Travel time will be available once project location is added.';
            const stateBox = document.getElementById('travel-time-state');
            if (stateBox) {
                stateBox.textContent = fallback;
            }
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const sectionKey = entry.target.dataset.trackSection || 'section';
                    if (seenSections.has(sectionKey)) {
                        return;
                    }
                    seenSections.add(sectionKey);
                    track('section_view', sectionKey, {});
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.45 });

        document.querySelectorAll('[data-track-section]').forEach((section) => observer.observe(section));

        document.querySelectorAll('.unit-tab').forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.dataset.tab;
                document.querySelectorAll('.unit-tab').forEach((tab) => tab.classList.toggle('active', tab === button));
                document.querySelectorAll('.unit-panel').forEach((panel) => panel.classList.toggle('active', panel.dataset.panel === id));
            });
        });

        document.querySelectorAll('.size-tab').forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.dataset.variantTarget;
                const panel = button.closest('.unit-panel');
                panel.querySelectorAll('.size-tab').forEach((tab) => tab.classList.toggle('active', tab === button));
                panel.querySelectorAll('.variant-panel').forEach((item) => item.classList.toggle('active', item.dataset.variantPanel === target));
            });
        });

        document.querySelectorAll('.wf-media-tab').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.wf-media-tab').forEach((tab) => tab.classList.toggle('active', tab === button));
                const src = button.dataset.heroSrc;
                if (src) {
                    document.getElementById('wf-hero-stage-image').src = src;
                }
            });
        });

        const heroStage = document.querySelector('.wf-stage');
        const heroStageImage = document.getElementById('wf-hero-stage-image');
        const heroMediaTabs = Array.from(document.querySelectorAll('.wf-media-tab'));
        const mobileHeroCarousel = window.matchMedia('(max-width: 980px)');

        if (heroStage && heroStageImage && heroMediaTabs.length > 1) {
            let heroIndex = Math.max(0, heroMediaTabs.findIndex((tab) => tab.classList.contains('active')));
            let heroAutoplay = null;
            let touchStartX = 0;
            let touchDeltaX = 0;

            const setHeroSlide = (nextIndex) => {
                const normalizedIndex = (nextIndex + heroMediaTabs.length) % heroMediaTabs.length;
                const activeTab = heroMediaTabs[normalizedIndex];
                if (!activeTab) {
                    return;
                }

                heroIndex = normalizedIndex;
                heroMediaTabs.forEach((tab) => tab.classList.toggle('active', tab === activeTab));

                const src = activeTab.dataset.heroSrc;
                if (!src || heroStageImage.getAttribute('src') === src) {
                    return;
                }

                heroStage.classList.add('is-switching');
                window.setTimeout(() => {
                    heroStageImage.setAttribute('src', src);
                }, 110);
                window.setTimeout(() => {
                    heroStage.classList.remove('is-switching');
                }, 320);
            };

            const stopHeroAutoplay = () => {
                if (heroAutoplay) {
                    window.clearInterval(heroAutoplay);
                    heroAutoplay = null;
                }
            };

            const startHeroAutoplay = () => {
                stopHeroAutoplay();
                if (!mobileHeroCarousel.matches || document.hidden) {
                    return;
                }

                heroAutoplay = window.setInterval(() => {
                    setHeroSlide(heroIndex + 1);
                }, 3200);
            };

            heroMediaTabs.forEach((tab, index) => {
                tab.addEventListener('click', () => {
                    setHeroSlide(index);
                    startHeroAutoplay();
                });
            });

            heroStage.addEventListener('touchstart', (event) => {
                touchStartX = event.touches[0]?.clientX || 0;
                touchDeltaX = 0;
                stopHeroAutoplay();
            }, { passive: true });

            heroStage.addEventListener('touchmove', (event) => {
                const currentX = event.touches[0]?.clientX || 0;
                touchDeltaX = currentX - touchStartX;
            }, { passive: true });

            heroStage.addEventListener('touchend', () => {
                if (Math.abs(touchDeltaX) > 42) {
                    setHeroSlide(touchDeltaX < 0 ? heroIndex + 1 : heroIndex - 1);
                }
                startHeroAutoplay();
            });

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopHeroAutoplay();
                } else {
                    startHeroAutoplay();
                }
            });

            const syncHeroCarouselMode = () => {
                if (!mobileHeroCarousel.matches) {
                    stopHeroAutoplay();
                    heroStage.classList.remove('is-switching');
                    return;
                }
                startHeroAutoplay();
            };

            if (typeof mobileHeroCarousel.addEventListener === 'function') {
                mobileHeroCarousel.addEventListener('change', syncHeroCarouselMode);
            } else if (typeof mobileHeroCarousel.addListener === 'function') {
                mobileHeroCarousel.addListener(syncHeroCarouselMode);
            }

            syncHeroCarouselMode();
        }

        document.querySelectorAll('.media-toggle').forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.dataset.mediaPanel;
                document.querySelectorAll('.media-toggle').forEach((tab) => tab.classList.toggle('active', tab === button));
                document.querySelectorAll('[data-media-content]').forEach((panel) => {
                    panel.classList.toggle('active', panel.dataset.mediaContent === target);
                });
            });
        });

        document.querySelectorAll('.media-subtoggle').forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.dataset.mediaSubtoggle;
                const panel = button.closest('[data-media-content]');
                if (!panel) return;

                panel.querySelectorAll('.media-subtoggle').forEach((tab) => tab.classList.toggle('active', tab === button));
                panel.querySelectorAll('.media-subpanel').forEach((subpanel) => {
                    subpanel.classList.toggle('active', subpanel.dataset.mediaSubpanel === target);
                });
            });
        });

        document.querySelectorAll('.js-track-link').forEach((link) => {
            link.addEventListener('click', () => {
                const eventName = link.dataset.event || 'section_view';
                const section = link.dataset.section || 'media';
                const meta = JSON.parse(link.dataset.meta || '{}');

                if (downloadEvents.has(eventName)) {
                    try {
                        const url = new URL(link.href, window.location.origin);
                        url.searchParams.set('section', section);
                        url.searchParams.set('cta', meta.cta || 'download');
                        url.searchParams.set('session_id', sessionId);
                        url.searchParams.set('visit_id', visitId);
                        url.searchParams.set('preview', isPreviewMode ? '1' : '0');
                        link.href = url.toString();
                    } catch (error) {
                        // Leave href unchanged on malformed URLs.
                    }
                    return;
                }

                track(eventName, section, meta);
            });
        });

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        document.querySelectorAll('.reveal').forEach((el) => revealObserver.observe(el));

        document.querySelectorAll('.js-floor-plan').forEach((link) => {
            link.addEventListener('click', (event) => {
                const meta = JSON.parse(link.dataset.meta || '{}');
                track('floor_plan_view', 'unit_plans', meta);
                if (link.getAttribute('href') === '#') {
                    event.preventDefault();
                }
            });
        });

        const inlineVideoMarkup = new WeakMap();
        let activeInlineVideo = null;

        const toEmbedVideoUrl = (rawUrl) => {
            if (!rawUrl) {
                return null;
            }

            try {
                const url = new URL(rawUrl, window.location.origin);
                const host = url.hostname.replace(/^www\./, '');

                if (host.includes('youtube.com')) {
                    const videoId = url.searchParams.get('v');
                    if (videoId) {
                        return `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
                    }
                    if (url.pathname.startsWith('/embed/')) {
                        return `${url.origin}${url.pathname}${url.search ? `${url.search}&autoplay=1` : '?autoplay=1'}`;
                    }
                }

                if (host === 'youtu.be') {
                    const videoId = url.pathname.replace('/', '');
                    if (videoId) {
                        return `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
                    }
                }

                if (host.includes('vimeo.com')) {
                    const videoId = url.pathname.split('/').filter(Boolean).pop();
                    if (videoId) {
                        return `https://player.vimeo.com/video/${videoId}?autoplay=1`;
                    }
                }

                return null;
            } catch (error) {
                return null;
            }
        };

        const isDirectVideoFile = (rawUrl) => /\.(mp4|webm|ogg|m3u8)(\?.*)?$/i.test(rawUrl || '');

        const restoreInlineVideo = (link) => {
            if (!link) {
                return;
            }
            const mediaBox = link.querySelector('.media-card-media');
            if (!mediaBox || !inlineVideoMarkup.has(mediaBox)) {
                return;
            }
            mediaBox.innerHTML = inlineVideoMarkup.get(mediaBox);
            link.classList.remove('is-playing');
            if (activeInlineVideo === link) {
                activeInlineVideo = null;
            }
        };

        const buildInlineVideoMarkup = ({ sourceUrl, fallbackUrl, title }) => {
            const embedUrl = toEmbedVideoUrl(sourceUrl);

            if (embedUrl) {
                return `<div class="inline-video-player"><button type="button" class="inline-video-close" aria-label="Close video">&times;</button><iframe src="${embedUrl}" title="${title || 'Project video'}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>`;
            }

            if (isDirectVideoFile(sourceUrl)) {
                return `<div class="inline-video-player"><button type="button" class="inline-video-close" aria-label="Close video">&times;</button><video controls autoplay playsinline preload="metadata"><source src="${sourceUrl}"></video></div>`;
            }

            if (fallbackUrl && isDirectVideoFile(fallbackUrl)) {
                return `<div class="inline-video-player"><button type="button" class="inline-video-close" aria-label="Close video">&times;</button><video controls autoplay playsinline preload="metadata"><source src="${fallbackUrl}"></video></div>`;
            }

            return null;
        };

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && activeInlineVideo) {
                restoreInlineVideo(activeInlineVideo);
            }
        });

        document.querySelectorAll('.js-inline-video').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const mediaBox = link.querySelector('.media-card-media');
                const sourceUrl = link.dataset.videoUrl || link.getAttribute('href');
                const fallbackUrl = link.dataset.videoFallback || link.getAttribute('href');
                const title = link.dataset.videoTitle || 'Project video';

                if (!mediaBox) {
                    if (fallbackUrl) {
                        window.location.href = fallbackUrl;
                    }
                    return;
                }

                if (activeInlineVideo && activeInlineVideo !== link) {
                    restoreInlineVideo(activeInlineVideo);
                }

                if (!inlineVideoMarkup.has(mediaBox)) {
                    inlineVideoMarkup.set(mediaBox, mediaBox.innerHTML);
                }

                const playerMarkup = buildInlineVideoMarkup({ sourceUrl, fallbackUrl, title });
                if (!playerMarkup) {
                    window.location.href = fallbackUrl || sourceUrl;
                    return;
                }

                mediaBox.innerHTML = playerMarkup;
                link.classList.add('is-playing');
                activeInlineVideo = link;
            });
        });

        document.addEventListener('click', (event) => {
            const closeButton = event.target.closest('.inline-video-close');
            if (!closeButton) {
                return;
            }
            const link = closeButton.closest('.js-inline-video');
            if (link) {
                restoreInlineVideo(link);
            }
        });
    })();
</script>
</body>
</html>
