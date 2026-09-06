@extends(
    auth()->check() && (auth()->user()->isAssistantSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isSalesManager())
        ? 'sales-manager.layout'
        : (auth()->check() && auth()->user()->isTelecaller()
            ? 'telecaller.layout'
            : (auth()->check() && auth()->user()->isFinanceManager()
                ? 'finance-manager.layout'
                : 'layouts.app'))
)

@section('title', $item->title)
@section('page-title', 'Knowledge Topic')
@section('page-subtitle', $item->category?->name ?: 'Internal training topic')

@section('content')
<style>
    .kb-topic-shell { padding: 24px; display: grid; gap: 20px; }
    .kb-topic-panel { background: #fff; border: 1px solid #e6ede9; border-radius: 24px; box-shadow: 0 18px 48px rgba(15, 23, 42, .06); overflow: hidden; }
    .kb-topic-hero { padding: 28px; display: grid; gap: 16px; }
    .kb-topic-meta { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .kb-topic-chip { display: inline-flex; align-items: center; padding: 7px 12px; border-radius: 999px; font-weight: 800; font-size: 12px; background: #eff7f3; color: #2d5d4f; }
    .kb-topic-chip.due_soon { background:#fff7d6; color:#9b6a00; }
    .kb-topic-chip.overdue { background:#fff0f0; color:#b42318; }
    .kb-topic-chip.completed_late { background:#fff5eb; color:#b45309; }
    .kb-topic-chip.updated { background:#efe9ff; color:#6b21a8; }
    .kb-topic-title { margin: 0; font-size: 34px; line-height: 1.15; color: #113b2d; }
    .kb-topic-summary { margin: 0; color: #63786f; font-size: 17px; line-height: 1.7; max-width: 940px; }
    .kb-topic-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(320px, .8fr); gap: 20px; padding: 0 28px 28px; }
    .kb-topic-card { border: 1px solid #e4ece8; border-radius: 22px; padding: 22px; display: grid; gap: 16px; }
    .kb-topic-card h2 { margin: 0; font-size: 21px; color: #164031; }
    .kb-topic-card p { margin: 0; color: #63786f; line-height: 1.7; }
    .kb-article { color: #21352e; line-height: 1.8; font-size: 15px; }
    .kb-article p:first-child { margin-top: 0; }
    .kb-video-shell { aspect-ratio: 16 / 9; border-radius: 20px; overflow: hidden; background: #0d1f19; }
    .kb-video-shell iframe, .kb-video-shell video { width: 100%; height: 100%; border: 0; display: block; }
    .kb-pdf-cta { display: inline-flex; align-items: center; justify-content: center; gap: 10px; border-radius: 16px; padding: 14px 18px; background: #13513f; color: #fff; text-decoration: none; font-weight: 800; }
    .kb-preview-frame { width: 100%; min-height: 420px; border: 1px solid #e7eeeb; border-radius: 18px; }
    .kb-aside { display: grid; gap: 16px; align-content: start; }
    .kb-back-link { color: #13513f; font-weight: 800; text-decoration: none; }
    @media (max-width: 960px) { .kb-topic-grid { grid-template-columns: 1fr; } }
    @media (max-width: 720px) {
        .kb-topic-shell { padding: 16px; }
        .kb-topic-hero, .kb-topic-grid { padding-left: 16px; padding-right: 16px; }
        .kb-topic-title { font-size: 26px; }
        .kb-preview-frame { min-height: 320px; }
    }
</style>

<div class="kb-topic-shell" data-kb-topic data-progress-url="{{ route('knowledge-base.progress', $item->id) }}">
    <section class="kb-topic-panel">
        <div class="kb-topic-hero">
            <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; align-items:center;">
                <a href="{{ route('knowledge-base.index') }}" class="kb-back-link"><i class="fas fa-arrow-left" style="margin-right:8px;"></i>Back to Knowledge Base</a>
                @if($canManageKnowledgeBase)
                    <a href="{{ route('admin.knowledge-base.edit', $item) }}" class="kb-back-link">Edit Topic</a>
                @endif
            </div>
            <div class="kb-topic-meta">
                @if($assignment)
                    <span class="kb-topic-chip" style="background:#fff2df;color:#9a4d00;">Required</span>
                @endif
                <span class="kb-topic-chip {{ $item->display_status }}">{{ $statusLabel }}</span>
                @if($item->is_updated_for_user)
                    <span class="kb-topic-chip updated">Updated</span>
                @endif
                @if($item->category)
                    <span class="kb-topic-chip">{{ $item->category->name }}</span>
                @endif
                @if($item->project)
                    <span class="kb-topic-chip">{{ $item->project->name }}</span>
                @endif
                <span class="kb-topic-chip">Updated {{ optional($item->content_updated_at ?: $item->updated_at)->format('d M Y') }}</span>
                @if($assignment?->due_date)
                    <span class="kb-topic-chip {{ in_array($item->display_status, ['due_soon', 'overdue', 'completed_late'], true) ? $item->display_status : '' }}">Due {{ $assignment->due_date->format('d M Y') }}</span>
                @endif
            </div>
            <h1 class="kb-topic-title">{{ $item->title }}</h1>
            @if($item->short_summary)
                <p class="kb-topic-summary">{{ $item->short_summary }}</p>
            @endif
            @if($item->change_summary)
                <div style="padding:14px 16px;border:1px solid #e8dfc0;border-radius:16px;background:#fff9ec;color:#7a5b12;">
                    <strong>Latest update:</strong> {{ $item->change_summary }}
                </div>
            @endif
        </div>

        <div class="kb-topic-grid">
            <div style="display:grid; gap:20px;">
                @if(filled($item->article_content))
                    <article class="kb-topic-card" data-kb-article>
                        <h2>Article</h2>
                        <div class="kb-article">{!! nl2br(e($item->article_content)) !!}</div>
                    </article>
                @endif

                @if($videoMeta['type'] ?? false)
                    <article class="kb-topic-card">
                        <h2>Video</h2>
                        <div class="kb-video-shell">
                            @if(($videoMeta['type'] ?? null) === 'youtube')
                                <iframe id="kbYoutubePlayer" src="{{ $videoMeta['embed_url'] }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                            @elseif(($videoMeta['type'] ?? null) === 'html5')
                                <video id="kbHtml5Video" controls preload="metadata"><source src="{{ $videoMeta['embed_url'] }}"></video>
                            @else
                                <iframe src="{{ $videoMeta['embed_url'] }}" title="Knowledge video" allowfullscreen></iframe>
                            @endif
                        </div>
                    </article>
                @endif
            </div>

            <aside class="kb-aside">
                @if($item->thumbnail_url)
                    <div class="kb-topic-card" style="padding:0; overflow:hidden;">
                        <img src="{{ $item->thumbnail_url }}" alt="{{ $item->title }}" style="width:100%; display:block; aspect-ratio:16/9; object-fit:cover;">
                    </div>
                @endif

                @if($item->pdf_url)
                    <article class="kb-topic-card" data-kb-pdf>
                        <h2>PDF</h2>
                        <p>Open ya download karke SOP, checklist, ya supporting document dekh sakte ho.</p>
                        <a href="{{ $item->pdf_url }}" class="kb-pdf-cta" target="_blank" rel="noopener" data-kb-pdf-open><i class="fas fa-file-pdf"></i> Open PDF</a>
                        <iframe class="kb-preview-frame" src="{{ $item->pdf_url }}#toolbar=0"></iframe>
                    </article>
                @endif

                <article class="kb-topic-card">
                    <h2>Progress Rules</h2>
                    <p>Article 15 sec + basic scroll, video 35% watch, aur PDF open + 5 sec interaction ke baad complete mark hota hai.</p>
                </article>
            </aside>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-kb-topic]');
    if (!root) return;

    const progressUrl = root.dataset.progressUrl;
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const sent = new Set();

    const postProgress = (eventName, extra = {}) => {
        const payload = { event: eventName, ...extra };
        const dedupeKey = JSON.stringify(payload);
        if (sent.has(dedupeKey)) return;

        fetch(progressUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        }).then(() => sent.add(dedupeKey)).catch(() => {});
    };

    postProgress('opened');

    if (document.querySelector('[data-kb-article]')) {
        window.setTimeout(() => postProgress('article_tick', { seconds: 15 }), 15000);
        const article = document.querySelector('[data-kb-article]');
        const sendScroll = () => {
            const rect = article.getBoundingClientRect();
            const viewport = window.innerHeight || document.documentElement.clientHeight;
            const visible = Math.max(0, Math.min(rect.bottom, viewport) - Math.max(rect.top, 0));
            const percent = Math.max(0, Math.min(100, Math.round((visible / Math.max(rect.height, 1)) * 100)));
            if (percent >= 20) {
                postProgress('article_scroll', { percent });
                window.removeEventListener('scroll', sendScroll, { passive: true });
            }
        };
        window.addEventListener('scroll', sendScroll, { passive: true });
        sendScroll();
    }

    const pdfButton = document.querySelector('[data-kb-pdf-open]');
    if (pdfButton) {
        pdfButton.addEventListener('click', () => {
            postProgress('pdf_opened');
            window.setTimeout(() => postProgress('pdf_qualified', { seconds: 5 }), 5000);
        });
    }

    const html5Video = document.getElementById('kbHtml5Video');
    if (html5Video) {
        html5Video.addEventListener('timeupdate', () => {
            if (!html5Video.duration) return;
            const percent = Math.round((html5Video.currentTime / html5Video.duration) * 100);
            if (percent >= 35) {
                postProgress('video_progress', { percent, position: Math.round(html5Video.currentTime) });
            }
        });
    }

    const youtubeFrame = document.getElementById('kbYoutubePlayer');
    if (!youtubeFrame) return;

    const bootYoutube = () => {
        const player = new YT.Player('kbYoutubePlayer', {
            events: {
                onStateChange: () => {
                    window.clearInterval(window.__kbYoutubeProgressInterval);
                    window.__kbYoutubeProgressInterval = window.setInterval(() => {
                        const duration = player.getDuration?.() || 0;
                        const currentTime = player.getCurrentTime?.() || 0;
                        if (!duration) return;
                        const percent = Math.round((currentTime / duration) * 100);
                        if (percent >= 35) {
                            postProgress('video_progress', { percent, position: Math.round(currentTime) });
                            window.clearInterval(window.__kbYoutubeProgressInterval);
                        }
                    }, 2000);
                }
            }
        });
    };

    if (!window.YT || !window.YT.Player) {
        const script = document.createElement('script');
        script.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(script);
    }

    window.onYouTubeIframeAPIReady = bootYoutube;
    if (window.YT && window.YT.Player) {
        bootYoutube();
    }
});
</script>
@endsection
