@extends('layouts.app')

@section('title', 'Storage Manager')
@section('page-title', 'Storage Manager')

@push('styles')
<style>
    .storage-page{display:flex;flex-direction:column;gap:16px;color:#10231a}
    .storage-card{background:#fff;border:1px solid #e4ebe5;border-radius:18px;box-shadow:0 12px 32px rgba(16,35,26,.06)}
    .storage-hero{padding:22px 26px;display:flex;align-items:center;justify-content:space-between;gap:16px}
    .storage-eyebrow{font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;color:#65766d;margin-bottom:6px}
    .storage-title{margin:0;font-size:28px;line-height:1.1;font-weight:900;color:#031b10}
    .storage-subtitle{margin-top:7px;color:#52677a;font-size:13px}
    .storage-tabs{display:flex;gap:8px;flex-wrap:wrap}
    .storage-tab{padding:10px 13px;border:1px solid #d6ded8;border-radius:999px;color:#10231a;text-decoration:none;font-weight:900;font-size:13px}
    .storage-tab.active{background:#06633b;color:#fff;border-color:#06633b}
    .storage-stats{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:12px}
    .storage-stat{padding:16px;border-radius:16px;border:1px solid #e4ebe5;background:#fff}
    .storage-stat span{display:block;color:#64748b;font-size:11px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
    .storage-stat strong{display:block;margin-top:8px;font-size:24px;color:#061c12}
    .storage-filter{padding:16px}
    .storage-filter form{display:grid;grid-template-columns:minmax(220px,1.2fr) repeat(4,minmax(130px,.7fr)) auto;gap:10px;align-items:end}
    .storage-label{display:block;margin-bottom:6px;color:#65766d;font-size:10px;font-weight:900;letter-spacing:.13em;text-transform:uppercase}
    .storage-input,.storage-select{width:100%;min-height:42px;border:1px solid #d6ded8;border-radius:12px;padding:9px 11px;background:#fff;color:#0f172a}
    .storage-btn{border:0;border-radius:11px;padding:10px 14px;font:inherit;font-size:13px;font-weight:900;text-decoration:none;cursor:pointer;white-space:nowrap}
    .storage-btn.primary{background:#06633b;color:#fff}.storage-btn.secondary{background:#fff;border:1px solid #d6ded8;color:#10231a}.storage-btn.danger{background:#e11d48;color:#fff}.storage-btn.warn{background:#d97706;color:#fff}
    .storage-section-head{padding:16px 18px;display:flex;justify-content:space-between;gap:12px;align-items:center;border-bottom:1px solid #edf2ef}
    .storage-section-title{margin:0;font-size:21px;font-weight:900;color:#031b10}.storage-section-meta{font-size:12px;color:#64748b;font-weight:800}
    .storage-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;padding:16px}
    .storage-item{border:1px solid #edf2ef;border-radius:16px;background:#fcfffd;overflow:hidden}
    .storage-preview{height:150px;background:#f8fafc;display:grid;place-items:center;border-bottom:1px solid #edf2ef}
    .storage-preview img{width:100%;height:100%;object-fit:cover}.storage-preview audio{width:92%}
    .storage-file-icon{font-size:38px;color:#64748b}
    .storage-item-body{padding:13px;display:flex;flex-direction:column;gap:9px}
    .storage-name{font-weight:900;color:#061c12;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.storage-muted{font-size:12px;color:#64748b}
    .storage-pills{display:flex;gap:6px;flex-wrap:wrap}.storage-pill{padding:5px 8px;border-radius:999px;font-size:11px;font-weight:900;background:#f1f5f9;color:#475569}.storage-pill.green{background:#ecfdf5;color:#047857}.storage-pill.amber{background:#fff7ed;color:#b45309}.storage-pill.red{background:#fff1f2;color:#be123c}
    .storage-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.storage-actions form{display:inline}
    .storage-table{width:100%;border-collapse:collapse}.storage-table th,.storage-table td{padding:13px 16px;border-bottom:1px solid #edf2ef;text-align:left;font-size:13px;vertical-align:middle}.storage-table th{font-size:11px;text-transform:uppercase;letter-spacing:.12em;color:#64748b;background:#fbfdfc}
    .storage-empty{padding:36px 18px;text-align:center;color:#64748b}
    @media(max-width:1000px){.storage-stats,.storage-filter form{grid-template-columns:1fr}.storage-hero{flex-direction:column;align-items:flex-start}}
</style>
@endpush

@section('content')
@php
    $tabs = [
        'overview' => 'Overview',
        'photos' => 'Photos',
        'recordings' => 'Call Recordings',
        'trash' => 'Trash',
        'rules' => 'Cleanup Rules',
    ];
@endphp

<div class="storage-page">
    @include('attendance._flash')

    <section class="storage-card storage-hero">
        <div>
            <div class="storage-eyebrow">Admin Tools</div>
            <h1 class="storage-title">Storage Manager</h1>
            <div class="storage-subtitle">Review CRM photos and call recordings, then move unwanted files to trash safely.</div>
        </div>
        <div class="storage-tabs">
            @foreach($tabs as $tabKey => $label)
                <a class="storage-tab {{ $tab === $tabKey ? 'active' : '' }}" href="{{ route('admin.storage-manager.index', array_merge(request()->except('page'), ['tab' => $tabKey])) }}">{{ $label }}</a>
            @endforeach
        </div>
    </section>

    <section class="storage-stats">
        <div class="storage-stat"><span>Total Storage</span><strong>{{ $formatBytes($overview['total_size']) }}</strong></div>
        <div class="storage-stat"><span>Photos</span><strong>{{ $formatBytes($overview['photos_size']) }}</strong></div>
        <div class="storage-stat"><span>Recordings</span><strong>{{ $formatBytes($overview['recordings_size']) }}</strong></div>
        <div class="storage-stat"><span>Trash</span><strong>{{ $formatBytes($overview['trash_size']) }}</strong></div>
        <div class="storage-stat"><span>Old Files</span><strong>{{ $overview['old_files_count'] }}</strong></div>
    </section>

    <section class="storage-card storage-filter">
        <form method="GET" action="{{ route('admin.storage-manager.index') }}">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div>
                <label class="storage-label">Search</label>
                <input class="storage-input" name="search" value="{{ $filters['search'] }}" placeholder="User, lead, phone, file">
            </div>
            <div>
                <label class="storage-label">Type</label>
                <select class="storage-select" name="type">
                    <option value="all" @selected($filters['type'] === 'all')>All</option>
                    <option value="photo" @selected($filters['type'] === 'photo')>Photos</option>
                    <option value="recording" @selected($filters['type'] === 'recording')>Recordings</option>
                </select>
            </div>
            <div>
                <label class="storage-label">Module</label>
                <select class="storage-select" name="module">
                    @foreach(['all' => 'All', 'attendance' => 'Attendance', 'meeting' => 'Meeting', 'site_visit' => 'Site Visit', 'call' => 'Call'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['module'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="storage-label">From</label>
                <input class="storage-input" type="date" name="from_date" value="{{ $filters['from_date'] }}">
            </div>
            <div>
                <label class="storage-label">To</label>
                <input class="storage-input" type="date" name="to_date" value="{{ $filters['to_date'] }}">
            </div>
            <div class="storage-actions">
                <a class="storage-btn secondary" href="{{ route('admin.storage-manager.index', ['tab' => $tab]) }}">Reset</a>
                <button class="storage-btn primary">Filter</button>
            </div>
        </form>
    </section>

    @if(in_array($tab, ['overview', 'photos', 'recordings'], true))
        @php
            $mediaSections = match ($tab) {
                'photos' => [
                    ['title' => 'Photos', 'items' => $items->where('media_type', 'photo')->values()],
                ],
                'recordings' => [
                    ['title' => 'Call Recordings', 'items' => $items->where('media_type', 'recording')->values()],
                ],
                default => [
                    ['title' => 'Call Recordings', 'items' => $items->where('media_type', 'recording')->values()],
                    ['title' => 'Photos', 'items' => $items->where('media_type', 'photo')->values()],
                ],
            };
        @endphp

        <form id="bulk-trash-form" method="POST" action="{{ route('admin.storage-manager.bulk-trash') }}">
            @csrf
        </form>
        @foreach($mediaSections as $section)
            <section class="storage-card">
                <div class="storage-section-head">
                    <div>
                        <h2 class="storage-section-title">{{ $section['title'] }}</h2>
                        <div class="storage-section-meta">{{ $section['items']->count() }} active file(s)</div>
                    </div>
                    @if($loop->first)
                        <button class="storage-btn danger" type="submit" form="bulk-trash-form">Move Selected to Trash</button>
                    @endif
                </div>
                <div class="storage-grid">
                    @forelse($section['items'] as $item)
                        <article class="storage-item">
                            <div class="storage-preview">
                                @if($item['media_type'] === 'photo')
                                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener"><img src="{{ $item['url'] }}" alt="{{ $item['label'] }}" loading="lazy"></a>
                                @elseif($item['can_trash'])
                                    <audio controls preload="none" src="{{ $item['url'] }}"></audio>
                                @else
                                    <span class="storage-file-icon"><i class="fas fa-link"></i></span>
                                @endif
                            </div>
                            <div class="storage-item-body">
                                <label class="storage-name">
                                    @if($item['can_trash'] && !$item['is_protected'])
                                        <input type="checkbox" name="media_keys[]" value="{{ $item['key'] }}" form="bulk-trash-form">
                                    @endif
                                    {{ $item['file_name'] }}
                                </label>
                                <div class="storage-muted">{{ $item['owner_name'] ?: 'No owner' }} · {{ $item['related_label'] ?: 'No relation' }}</div>
                                <div class="storage-muted">{{ optional($item['created_at'])->format('d M Y h:i A') }} · {{ $formatBytes($item['file_size']) }}</div>
                                <div class="storage-pills">
                                    <span class="storage-pill green">{{ str_replace('_', ' ', $item['module']) }}</span>
                                    <span class="storage-pill">{{ $item['media_type'] }}</span>
                                    @if($item['is_protected'])
                                        <span class="storage-pill red">Protected</span>
                                    @endif
                                </div>
                                @if($item['protected_reason'])
                                    <div class="storage-muted">{{ $item['protected_reason'] }}</div>
                                @endif
                                <div class="storage-actions">
                                    <a class="storage-btn secondary" href="{{ $item['url'] }}" target="_blank" rel="noopener">{{ $item['media_type'] === 'recording' ? 'Open' : 'Preview' }}</a>
                                    @if($item['can_trash'] && !$item['is_protected'])
                                        <form method="POST" action="{{ route('admin.storage-manager.trash') }}">
                                            @csrf
                                            <input type="hidden" name="media_key" value="{{ $item['key'] }}">
                                            <button class="storage-btn danger" type="submit">Trash</button>
                                        </form>
                                    @else
                                        <button class="storage-btn secondary" disabled>Locked</button>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="storage-empty">No media files found.</div>
                    @endforelse
                </div>
            </section>
        @endforeach
    @elseif($tab === 'trash')
        <section class="storage-card">
            <div class="storage-section-head">
                <div>
                    <h2 class="storage-section-title">Trash</h2>
                    <div class="storage-section-meta">Restore files or permanently delete them after review.</div>
                </div>
            </div>
            <div style="overflow-x:auto">
                <table class="storage-table">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Module</th>
                            <th>Size</th>
                            <th>Trashed</th>
                            <th>Delete After</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trashItems as $trashItem)
                            <tr>
                                <td>
                                    <div class="storage-name">{{ $trashItem->file_name ?: basename($trashItem->file_path) }}</div>
                                    <div class="storage-muted">{{ $trashItem->meta['owner'] ?? 'No owner' }} · {{ $trashItem->meta['related'] ?? '' }}</div>
                                </td>
                                <td>{{ str_replace('_', ' ', $trashItem->module) }}</td>
                                <td>{{ $formatBytes($trashItem->file_size) }}</td>
                                <td>{{ optional($trashItem->trashed_at)->format('d M Y') }}</td>
                                <td>{{ optional($trashItem->delete_after)->format('d M Y') }}</td>
                                <td>
                                    <div class="storage-actions">
                                        <form method="POST" action="{{ route('admin.storage-manager.restore', $trashItem) }}">
                                            @csrf
                                            <button class="storage-btn primary">Restore</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.storage-manager.destroy', $trashItem) }}" onsubmit="return confirm('Permanent delete? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="storage-btn danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="storage-empty">Trash is empty.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section class="storage-card">
            <div class="storage-section-head">
                <div>
                    <h2 class="storage-section-title">Cleanup Rules</h2>
                    <div class="storage-section-meta">Recommended defaults for future automation.</div>
                </div>
            </div>
            <div class="storage-grid">
                <div class="storage-item"><div class="storage-item-body"><div class="storage-name">Attendance photos</div><div class="storage-muted">Review/delete after 90 days unless linked to an issue.</div></div></div>
                <div class="storage-item"><div class="storage-item-body"><div class="storage-name">Call recordings</div><div class="storage-muted">Review/delete after 180 days unless linked to closed/booked lead.</div></div></div>
                <div class="storage-item"><div class="storage-item-body"><div class="storage-name">Protected proof</div><div class="storage-muted">Booking, closing, KYC, and payment proof should stay locked.</div></div></div>
            </div>
        </section>
    @endif
</div>
@endsection
