@php
    $chatLayout = (function () {
        $user = auth()->user();

        if ($user && ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager())) {
            return 'sales-manager.layout';
        }

        if ($user && $user->isSalesExecutive()) {
            return 'sales-executive.layout';
        }

        return 'layouts.app';
    })();
@endphp

@extends($chatLayout)

@section('title', 'WhatsApp Chat - ' . brand_name())
@section('page-title', 'WhatsApp Chat')
@section('page-subtitle', 'Conversations with CRM context')

@section('content')
@php
$initialConversations = $conversations->map(function ($conversation) {
    $latestMessage = $conversation->getLatestMessage();
    $activeAssignment = $conversation->lead?->activeAssignments->first();
    $assignedUser = $conversation->assignedTo ?: $activeAssignment?->assignedTo;
    $wabaAccount = $conversation->metaWabaAccount;
    $wabaPhone = $wabaAccount?->display_phone_number ?: $wabaAccount?->name;
    $wabaCanSend = \App\Models\SystemSettings::get('whatsapp_default_sender', 'third_party') !== 'meta_waba'
        || ($wabaAccount
            ? ((bool) $wabaAccount->is_active && (bool) $wabaAccount->is_verified)
            : (bool) \App\Models\MetaWabaAccount::defaultAccount()?->is_verified);
    return [
        'id' => $conversation->id,
        'phone_number' => $conversation->phone_number,
        'contact_name' => $conversation->contact_name,
        'user_id' => $conversation->user_id,
        'user_name' => $conversation->user?->name,
        'status' => $conversation->status ?: 'open',
        'assigned_user' => $assignedUser ? ['id' => $assignedUser->id, 'name' => $assignedUser->name] : null,
        'lead' => $conversation->lead ? [
            'id' => $conversation->lead->id,
            'name' => $conversation->lead->name,
            'status' => $conversation->lead->status,
            'source' => $conversation->lead->source_label,
            'phone' => $conversation->lead->phone,
            'url' => route('leads.show', $conversation->lead->id),
        ] : null,
        'unread_count' => $conversation->getUnreadCount(),
        'latest_message' => $latestMessage ? [
            'message' => $latestMessage->message,
            'type' => data_get($latestMessage->api_response, 'message_type', 'text'),
            'created_at' => $latestMessage->created_at->format('Y-m-d H:i:s'),
        ] : null,
        'updated_at' => $conversation->updated_at->format('Y-m-d H:i:s'),
        'meta_waba_account_id' => $wabaAccount?->id,
        'waba_display_phone_number' => $wabaPhone,
        'waba_phone_number_id' => $wabaAccount?->phone_number_id,
        'waba_is_verified' => (bool) $wabaAccount?->is_verified,
        'waba_is_active' => (bool) $wabaAccount?->is_active,
        'waba_can_send' => $wabaCanSend,
        'waba_settings_url' => $wabaAccount
            ? route('integrations.meta-waba.index', ['account_id' => $wabaAccount->id])
            : route('integrations.meta-waba.index'),
    ];
})->values();
$wabaAccountOptions = ($wabaAccounts ?? collect())->map(fn($account) => [
    'id' => $account->id,
    'label' => $account->display_phone_number ?: $account->name ?: ('Account #' . $account->id),
    'phone_number_id' => $account->phone_number_id,
    'is_default' => (bool) $account->is_default,
    'is_verified' => (bool) $account->is_verified,
    'is_active' => (bool) $account->is_active,
    'settings_url' => route('integrations.meta-waba.index', ['account_id' => $account->id]),
])->values();
@endphp
<style>
.header.main-header{display:none!important}
#mainContent > .container{padding:0!important;max-width:none!important}
html,body{height:100%;margin:0}
body.chat-page-active{overflow:hidden}

/* ── Page Shell ── */
.wa-page{margin:-20px -20px 0 -20px;padding:10px 12px 0;background:#f0f2f5;height:100dvh;min-height:100vh;overflow:hidden}
.wa-shell{display:grid;grid-template-columns:340px minmax(0,1fr) 280px;gap:10px;height:100%;min-height:0}
.wa-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;min-height:0}
.wa-sidebar,.wa-chat,.wa-context{display:flex;flex-direction:column;min-height:0;height:100%}
.wa-hidden{display:none!important}

/* ── Sidebar ── */
.wa-sidebar-head{padding:16px 16px 12px;background:#fff;border-bottom:1px solid #f1f3f5;flex-shrink:0}
.wa-sidebar-title-row{display:flex;align-items:center;justify-content:space-between;gap:8px}
.wa-sidebar-title{font-size:1.25rem;font-weight:800;color:#111827;letter-spacing:-0.02em}
.wa-sidebar-actions{display:flex;gap:6px}
.wa-sidebar-icon-btn{width:36px;height:36px;border-radius:10px;border:none;background:#f3f4f6;color:#374151;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.85rem;transition:background 0.15s}
.wa-sidebar-icon-btn:active{background:#e5e7eb}
.wa-search{margin-top:10px;width:100%;border:1px solid #e5e7eb;border-radius:12px;padding:10px 14px 10px 36px;background:#f9fafb url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'/%3E%3C/svg%3E") 10px center / 16px no-repeat;color:#111827;outline:none;font-size:0.88rem;transition:border-color 0.15s}
.wa-search:focus{border-color:#059669}
.wa-search::placeholder{color:#9ca3af}
.wa-account-filter{margin-top:8px;width:100%;border:1px solid #e5e7eb;border-radius:12px;padding:9px 12px;background:#fff;color:#111827;outline:none;font-size:0.82rem;font-weight:700}
.wa-account-filter:focus{border-color:#059669}

/* ── Conversation List ── */
.wa-list{flex:1;overflow:auto;padding:6px;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
.wa-item{padding:12px;border-radius:14px;cursor:pointer;border:1px solid transparent;margin-bottom:2px;transition:background 0.12s}
.wa-item:hover{background:#f9fafb}
.wa-item:active{background:#f3f4f6}
.wa-item.active{background:#ecfdf5;border-color:#d1fae5}
.wa-item-row{display:flex;gap:12px;align-items:flex-start}
.wa-avatar{width:46px;height:46px;border-radius:50%;background:linear-gradient(135deg,#059669,#047857);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;letter-spacing:-0.02em}
.wa-item-body{flex:1;min-width:0}
.wa-item-top{display:flex;align-items:baseline;justify-content:space-between;gap:8px}
.wa-item-name{font-size:0.92rem;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wa-item-time{font-size:0.68rem;font-weight:600;color:#9ca3af;white-space:nowrap;flex-shrink:0}
.wa-item-time.has-unread{color:#059669}
.wa-item-bottom{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:3px}
.wa-item-preview{font-size:0.8rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;line-height:1.4}
.wa-item-unread{min-width:20px;height:20px;padding:0 6px;border-radius:10px;background:#059669;color:#fff;font-size:0.68rem;font-weight:800;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;line-height:1}
.wa-item-badges{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}

/* ── Badges ── */
.wa-badge{display:inline-flex;align-items:center;gap:3px;padding:2px 8px;border-radius:6px;font-size:0.65rem;font-weight:700;letter-spacing:0.01em;line-height:1.5}
.wa-badge.lead{background:#eff6ff;color:#1d4ed8}
.wa-badge.owner{background:#f5f3ff;color:#6d28d9}
.wa-badge.status{background:#ecfdf5;color:#059669}
.wa-badge.source{background:#fffbeb;color:#b45309}
.wa-badge.muted{background:#f3f4f6;color:#6b7280}
.wa-badge.waba{background:#dcfce7;color:#166534}
.wa-badge.waba-warn{background:#fff7ed;color:#c2410c}

/* ── Chat Head ── */
.wa-chat-head{padding:14px 16px;border-bottom:1px solid #f1f3f5;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-shrink:0;background:#fff}
.wa-chat-head-left{display:flex;align-items:center;gap:12px;min-width:0;flex:1}
.wa-chat-head-link{text-decoration:none;color:inherit;border-radius:14px;transition:background .18s ease,transform .18s ease}
.wa-chat-head-link.is-clickable{cursor:pointer}
.wa-chat-head-link.is-clickable:hover{background:rgba(6,58,28,.05)}
.wa-chat-head-link.is-clickable:active{transform:translateY(1px)}
.wa-chat-head-link.is-disabled{pointer-events:none}
.wa-chat-head-info{min-width:0}
.wa-chat-head-name{font-size:1rem;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wa-chat-head-phone{font-size:0.78rem;color:#6b7280;margin-top:1px}

/* ── Messages ── */
.wa-msgs{flex:1;overflow:auto;padding:16px;background:#efeae2 url("data:image/svg+xml,%3Csvg width='60' height='60' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M30 5 L35 10 L30 15 L25 10Z' fill='%23d6cfc4' opacity='0.15'/%3E%3C/svg%3E");min-height:0;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;touch-action:pan-y}
.wa-msgs-inner{width:100%;max-width:none;min-height:100%;display:flex;flex-direction:column;justify-content:flex-end;gap:3px}
.wa-row{display:flex;width:100%;margin-bottom:1px}
.wa-row.out{justify-content:flex-end}
.wa-row.in{justify-content:flex-start}
.wa-bubble{max-width:min(72%,520px);padding:8px 10px 6px;border-radius:10px;position:relative;box-shadow:0 1px 1px rgba(0,0,0,0.06)}
.wa-row.out .wa-bubble{background:#d9fdd3;color:#111827;border-top-right-radius:3px}
.wa-row.in .wa-bubble{background:#fff;color:#111827;border-top-left-radius:3px}
.wa-bubble audio,.wa-bubble video{width:100%;display:block;border-radius:8px}
.wa-bubble p{margin:0}
.wa-meta{display:flex;justify-content:flex-end;align-items:center;gap:4px;margin-top:2px;font-size:0.68rem;color:#667781}
.wa-row.out .wa-meta{color:#5f8a65}

/* ── Compose Bar ── */
.wa-compose-wrap{flex-shrink:0;background:#f0f2f5;border-top:1px solid #e5e7eb}
.wa-send-warning{display:none;margin:8px 12px 0;padding:9px 11px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:0.78rem;font-weight:700;line-height:1.4}
.wa-send-warning a{color:#1d4ed8;text-decoration:none;font-weight:800}
.wa-send-warning.is-visible{display:block}
.wa-compose{padding:10px 12px;display:grid;grid-template-columns:auto 1fr auto;gap:8px;align-items:end;background:#f0f2f5}
.wa-input{width:100%;min-height:44px;max-height:110px;border:none;border-radius:10px;padding:10px 14px;background:#fff;outline:none;resize:none;font-size:0.9rem;color:#111827;box-shadow:0 1px 2px rgba(0,0,0,0.05)}
.wa-input::placeholder{color:#9ca3af}
.wa-send-btn{width:44px;height:44px;border-radius:50%;border:none;background:#059669;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;transition:background 0.15s;flex-shrink:0}
.wa-send-btn:active{background:#047857}
.wa-quick-replies{display:flex;gap:6px;overflow:auto;padding:8px 12px;background:#f8fafc;border-top:1px solid #e5e7eb;flex-shrink:0}
.wa-quick-chip{border:1px solid #d1fae5;background:#ecfdf5;color:#047857;border-radius:999px;padding:6px 10px;font-size:0.78rem;font-weight:800;white-space:nowrap;cursor:pointer}

/* ── Buttons ── */
.wa-btn{border:none;border-radius:12px;padding:10px 16px;font-weight:700;cursor:pointer;font-size:0.85rem;transition:opacity 0.15s}
.wa-btn:active{opacity:0.85}
.wa-btn.primary{background:#059669;color:#fff}
.wa-btn.soft{background:#f3f4f6;color:#374151}
.wa-btn.outline{background:#fff;color:#374151;border:1px solid #e5e7eb}
.wa-btn.danger{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}

/* ── Context Panel ── */
.wa-panel{padding:16px;overflow:auto;-webkit-overflow-scrolling:touch}
.wa-panel-card{border:1px solid #f1f3f5;border-radius:14px;padding:14px;margin-bottom:10px;background:#fafafa}
.wa-empty{height:100%;display:flex;align-items:center;justify-content:center;text-align:center;color:#6b7280;padding:24px}
.wa-kv{display:grid;grid-template-columns:80px 1fr;gap:6px 10px;font-size:0.82rem}
.wa-kv dt{color:#9ca3af;font-weight:600}
.wa-kv dd{margin:0;color:#111827;font-weight:500}

/* ── Modals ── */
.wa-modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:16px;z-index:60}
.wa-modal{width:min(100%,640px);max-height:90vh;overflow:hidden;background:#fff;border-radius:20px;box-shadow:0 24px 48px rgba(0,0,0,.2)}
.wa-modal-head{padding:16px 20px;border-bottom:1px solid #f1f3f5}
.wa-modal-body{padding:16px 20px;overflow:auto;max-height:65vh;-webkit-overflow-scrolling:touch}

/* ── Mobile-only elements (hidden on desktop) ── */
.wa-mobile-filters,.wa-mobile-drawer-close,.wa-empty-actions,.wa-mobile-chat-back,.wa-mobile-chat-plus{display:none}
.wa-modal-back-btn{display:none!important}

/* ── Desktop breakpoints ── */
@media(max-width:1280px){.wa-shell{grid-template-columns:300px minmax(0,1fr) 250px}.wa-bubble{max-width:min(76%,480px)}}
@media(max-width:1100px){.wa-shell{grid-template-columns:300px minmax(0,1fr)}.wa-context{display:none}}

/* ═══════════════════════════════════════════════════
   MOBILE (≤ 900px) - Professional WhatsApp-style
   ═══════════════════════════════════════════════════ */
@media(max-width:900px){
    body.app-webview-mode #mainContent > .container {
        padding-top: 0 !important;
    }
    .wa-page{
        margin:-20px -12px 0 -12px;
        padding:0;
        height:calc(100dvh - 68px);
        min-height:calc(100vh - 68px);
        background:#fff;
        overflow:hidden;
    }
    .wa-shell{
        grid-template-columns:1fr;
        height:100%;
        min-height:100%;
        gap:0;
    }
    .wa-card{border-radius:0;border:none;box-shadow:none}
    .wa-context{display:none}

    /* ── Sidebar ── */
    .wa-sidebar{
        display:flex;
        height:100%;
        min-height:100%;
        border-radius:0;
        overflow:hidden;
        border:none;
        box-shadow:none;
        background:#fff;
    }
    .wa-sidebar-head{
        padding:0;
        background:linear-gradient(135deg,#075e54,#128c7e);
        border:none;
        flex-shrink:0;
    }
    .wa-sidebar-title-row{
        padding:16px 16px 10px;
    }
    body.app-webview-mode .wa-sidebar-title-row { padding-top: 16px; }
    .wa-sidebar-title{color:#fff;font-size:1.35rem}
    .wa-sidebar-icon-btn{background:rgba(255,255,255,.15);color:#fff}
    .wa-sidebar-icon-btn:active{background:rgba(255,255,255,.25)}
    .wa-search{
        margin:0 16px;
        width:calc(100% - 32px);
        background:rgba(255,255,255,.18);
        border:none;
        color:#fff;
        padding:10px 14px 10px 38px;
        border-radius:10px;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='rgba(255,255,255,0.65)' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'/%3E%3C/svg%3E");
        background-position:12px center;
        background-size:16px;
        background-repeat:no-repeat;
        font-size:0.88rem;
    }
    .wa-search:focus{border:none;background-color:rgba(255,255,255,.25)}
    .wa-search::placeholder{color:rgba(255,255,255,.65)}
    .wa-account-filter{
        margin:8px 16px 0;
        width:calc(100% - 32px);
        border:none;
        background:rgba(255,255,255,.18);
        color:#fff;
    }
    .wa-account-filter option{color:#111827}

    /* ── Mobile Filters ── */
    .wa-mobile-filters{
        display:flex;
        gap:6px;
        overflow-x:auto;
        padding:10px 16px 14px;
        flex-shrink:0;
        background:linear-gradient(135deg,#075e54,#128c7e);
        scrollbar-width:none;
    }
    .wa-mobile-filters::-webkit-scrollbar{display:none}
    .wa-mobile-chip{
        border:1px solid rgba(255,255,255,.25);
        background:transparent;
        color:rgba(255,255,255,.85);
        border-radius:20px;
        padding:6px 14px;
        font-size:0.78rem;
        font-weight:600;
        white-space:nowrap;
        cursor:pointer;
        transition:all 0.15s;
    }
    .wa-mobile-chip.active{
        background:#fff;
        color:#075e54;
        border-color:#fff;
        font-weight:700;
    }

    /* ── Conversation List (mobile) ── */
    .wa-list{
        flex:1 1 auto;
        min-height:0;
        background:#fff;
        padding:0;
        margin-top:0;
    }
    .wa-item{
        padding:12px 16px;
        border-radius:0;
        margin-bottom:0;
        border-bottom:1px solid #f3f4f6;
    }
    .wa-item:active{background:#f3f4f6}
    .wa-item.active{background:#f0fdf4;border-color:#d1fae5}
    .wa-item-badges{display:none}

    /* ── Chat toggle ── */
    .wa-chat{display:none;background:#efeae2}
    .wa-page.wa-has-active-chat .wa-sidebar{display:none}
    .wa-page.wa-has-active-chat .wa-chat{display:flex}
    .wa-page.wa-has-active-chat .wa-chat{border-radius:0;border:none;box-shadow:none}
    .wa-mobile-drawer-close{display:none!important}

    /* ── Chat Back Button ── */
    .wa-mobile-chat-back{
        display:inline-flex;
        width:38px;
        height:38px;
        align-items:center;
        justify-content:center;
        border-radius:50%;
        border:none;
        background:transparent;
        color:#fff;
        flex-shrink:0;
        font-size:1rem;
    }
    .wa-mobile-chat-back:active{background:rgba(255,255,255,.15)}

    /* ── Chat Plus Button ── */
    .wa-mobile-chat-plus{
        display:inline-flex;
        width:38px;
        height:38px;
        align-items:center;
        justify-content:center;
        border-radius:50%;
        border:none;
        background:transparent;
        color:#fff;
        flex-shrink:0;
        margin-left:auto;
        font-size:0.95rem;
    }

    /* ── Chat Head (mobile) ── */
    .wa-chat-head{
        flex-direction:row;
        align-items:center;
        gap:10px;
        padding:0 12px;
        min-height:60px;
        background:linear-gradient(135deg,#075e54,#128c7e);
        border:none;
    }
    body.app-webview-mode .wa-chat-head { min-height:60px; padding-top:0; padding-bottom:0; }
    .wa-chat-head > .flex.gap-2{display:none!important}
    .wa-chat-head .wa-avatar{width:38px;height:38px;font-size:0.85rem;background:rgba(255,255,255,.2);border-radius:50%}
    .wa-chat-head-name{color:#fff;font-size:0.95rem}
    .wa-chat-head-phone{color:rgba(255,255,255,.7);font-size:0.72rem}
    #chatHeaderTags{display:none!important}

    /* ── Messages (mobile) ── */
    .wa-msgs{padding:8px 10px 14px}
    .wa-msgs-inner{gap:2px}
    .wa-bubble{max-width:85%;padding:6px 8px 4px;border-radius:8px}
    .wa-row.out .wa-bubble{border-top-right-radius:2px}
    .wa-row.in .wa-bubble{border-top-left-radius:2px}
    .wa-meta{font-size:0.62rem;margin-top:1px}

    /* ── Compose (mobile) ── */
    .wa-compose{
        padding:6px 8px;
        grid-template-columns:1fr auto;
        background:#f0f2f5;
        border-top:none;
        gap:6px;
    }
    .wa-compose .wa-btn.soft{display:none!important}
    .wa-input{min-height:40px;border-radius:22px;padding:8px 14px;font-size:0.88rem}
    .wa-send-btn{width:40px;height:40px;font-size:0.9rem}

    /* ── Active chat ── */
    #activeChat{height:100%;min-height:0}

    /* ── Empty State (mobile) ── */
    #emptyState{
        padding:24px 20px;
        background:#efeae2;
    }
    #emptyState > div{
        display:flex;
        flex-direction:column;
        align-items:center;
        justify-content:center;
        padding:32px 20px;
        border-radius:16px;
        background:rgba(255,255,255,.85);
    }
    #emptyState .wa-avatar{
        width:56px;
        height:56px;
        margin-bottom:16px!important;
        font-size:1.5rem;
    }
    #emptyState .text-xl{font-size:1.2rem;line-height:1.3}
    #emptyState .wa-empty-actions{
        display:flex;
        gap:8px;
        margin-top:16px;
        width:100%;
    }
    #emptyState .wa-empty-actions .wa-btn{
        flex:1;
        min-height:44px;
        border-radius:12px;
    }

    /* ── Modal mobile ── */
    .wa-modal-bg{
        padding:0;
    }
    .wa-modal{
        width:100%;
        max-width:100%;
        max-height:100dvh;
        min-height:100dvh;
        border-radius:0;
        display:flex;
        flex-direction:column;
    }
    .wa-modal-head{
        padding:0;
        border-bottom:1px solid #e5e7eb;
        flex-shrink:0;
    }
    .wa-modal-head .flex.items-center.justify-between{
        display:flex!important;
        flex-direction:row;
        align-items:center;
        gap:12px;
        padding:14px 16px;
    }
    .wa-modal-back-btn{
        display:flex!important;
    }
    .wa-modal-close-desktop{
        display:none!important;
    }
    .wa-modal-body{
        flex:1;
        max-height:none;
        padding:16px;
        overflow:auto;
    }
}
</style>
<div class="wa-page"><div class="wa-shell">
<aside id="waSidebar" class="wa-card wa-sidebar"><div class="wa-sidebar-head"><div class="wa-sidebar-title-row"><span class="wa-sidebar-title">Chats</span><div class="wa-sidebar-actions"><button class="wa-sidebar-icon-btn wa-mobile-drawer-close" type="button" onclick="closeConversationDrawer()"><i class="fas fa-times"></i></button><button class="wa-sidebar-icon-btn" type="button" onclick="openAddContactModal()" aria-label="New chat"><i class="fas fa-comment-medical"></i></button></div></div><input id="searchConversations" class="wa-search" type="text" placeholder="Search or start new chat"><select id="wabaAccountFilter" class="wa-account-filter" autocomplete="off" onchange="setWabaAccountFilter(this.value)"><option value="all">All WhatsApp Numbers</option>@foreach($wabaAccountOptions as $account)<option value="{{ $account['id'] }}">{{ $account['label'] }}{{ $account['is_default'] ? ' (Default)' : '' }}{{ !$account['is_verified'] ? ' - Not verified' : '' }}</option>@endforeach</select><div class="wa-mobile-filters"><button type="button" class="wa-mobile-chip active" data-chat-filter="all" onclick="setConversationVisibilityFilter('all')">All</button><button type="button" class="wa-mobile-chip" data-chat-filter="unread" onclick="setConversationVisibilityFilter('unread')">Unread</button><button type="button" class="wa-mobile-chip" data-chat-filter="my_leads" onclick="setConversationVisibilityFilter('my_leads')">My Leads</button></div></div><div id="conversationsList" class="wa-list"></div></aside>
<section class="wa-card wa-chat"><div id="emptyState" class="wa-empty"><div><div class="wa-avatar mx-auto mb-4" style="width:64px;height:64px;font-size:1.8rem"><i class="fab fa-whatsapp"></i></div><div class="text-xl font-semibold text-slate-900 mb-2">WhatsApp Conversations</div><div class="text-sm text-slate-500">Select a chat to start messaging</div><div class="wa-empty-actions"><button class="wa-btn primary" type="button" onclick="openAddContactModal()"><i class="fas fa-plus mr-2"></i>New Chat</button></div></div></div><div id="activeChat" class="wa-hidden h-full flex flex-col min-h-0"><div class="wa-chat-head"><button class="wa-mobile-chat-back" type="button" onclick="handleMobileChatBack()" aria-label="Back to chats"><i class="fas fa-arrow-left"></i></button><a id="chatLeadLink" class="wa-chat-head-left wa-chat-head-link is-disabled" href="javascript:void(0)" aria-disabled="true"><div id="chatAvatar" class="wa-avatar">W</div><div class="wa-chat-head-info"><div id="chatContactName" class="wa-chat-head-name">Contact</div><div id="chatPhoneNumber" class="wa-chat-head-phone"></div><div id="chatHeaderTags" class="flex flex-wrap gap-2 mt-2"></div></div></a><button class="wa-mobile-chat-plus" type="button" onclick="openTemplateModal()" aria-label="Open templates"><i class="fas fa-ellipsis-v"></i></button><div class="flex gap-2"><a id="chatWabaSettingsLink" class="wa-btn outline" href="{{ route('integrations.meta-waba.index') }}" target="_blank"><i class="fas fa-sliders-h mr-2"></i>API Settings</a><button class="wa-btn outline" type="button" onclick="openTemplateModal()"><i class="fas fa-file-alt mr-2"></i>Templates</button><button class="wa-btn danger" type="button" onclick="deleteCurrentConversation()"><i class="fas fa-trash mr-2"></i>Delete</button></div></div><div id="messagesContainer" class="wa-msgs"><div class="wa-msgs-inner"></div></div><div id="mobileTemplateFabAnchor"></div>@if(($quickReplies ?? collect())->isNotEmpty())<div class="wa-quick-replies">@foreach($quickReplies as $reply)<button type="button" class="wa-quick-chip" onclick="insertQuickReply({{ $reply->id }})">{{ $reply->title }}</button>@endforeach</div>@endif<div class="wa-compose-wrap"><div id="wabaSendWarning" class="wa-send-warning"></div><div class="wa-compose"><button class="wa-btn soft" type="button" onclick="openTemplateModal()"><i class="fas fa-file-alt"></i></button><textarea id="messageInput" class="wa-input" rows="1" placeholder="Type a message..."></textarea><button id="sendButton" class="wa-send-btn" type="button" onclick="sendMessage()"><i class="fas fa-paper-plane"></i></button></div></div></div></section>
<aside class="wa-card wa-context"><div id="contextEmptyState" class="wa-empty"><div><div class="text-lg font-semibold text-slate-900 mb-2">CRM context</div><div>Lead tag, owner, source, and linked record will show here.</div></div></div><div id="contextPanel" class="wa-hidden wa-panel"><div class="wa-panel-card"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Linked Lead</div><div id="contextLeadName" class="text-lg font-semibold text-slate-900">-</div><div class="mt-4"><dl class="wa-kv"><dt>Status</dt><dd id="contextLeadStatus">-</dd><dt>Source</dt><dd id="contextLeadSource">-</dd><dt>Assigned</dt><dd id="contextLeadOwner">-</dd><dt>Phone</dt><dd id="contextLeadPhone">-</dd></dl></div><a id="contextLeadLink" href="#" class="wa-btn outline wa-hidden inline-flex mt-4"><i class="fas fa-external-link-alt mr-2"></i>Open Lead</a></div><div class="wa-panel-card"><div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Conversation Owner</div><div id="contextConversationOwner" class="text-base font-semibold text-slate-900">-</div></div></div></aside>
</div></div>
<div id="addContactModal" class="wa-modal-bg wa-hidden"><div class="wa-modal"><div class="wa-modal-head"><div class="flex items-center justify-between"><button class="wa-modal-back-btn" type="button" onclick="closeAddContactModal()" aria-label="Close" style="width:38px;height:38px;border-radius:50%;border:none;background:#f3f4f6;color:#374151;display:none;align-items:center;justify-content:center;flex-shrink:0;font-size:0.95rem;cursor:pointer"><i class="fas fa-arrow-left"></i></button><div style="flex:1;min-width:0"><div class="text-lg font-bold text-slate-900">New Conversation</div><div class="text-sm text-slate-500 mt-1">Search a lead or enter a phone number</div></div><button class="wa-modal-close-desktop" type="button" onclick="closeAddContactModal()" style="width:34px;height:34px;border-radius:10px;border:none;background:#f3f4f6;color:#374151;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.85rem;flex-shrink:0"><i class="fas fa-times"></i></button></div></div><div class="wa-modal-body"><input id="leadSearch" class="w-full border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500" style="border-radius:12px;font-size:0.9rem" type="text" oninput="searchLeads(this.value)" placeholder="Search leads by name or phone"><div id="leadsList" class="max-h-72 overflow-auto border border-slate-200 mt-3" style="border-radius:12px"></div><div class="border-t border-slate-100 pt-4 mt-4"><div style="font-size:0.78rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px">Or enter manually</div><form id="addContactForm" onsubmit="event.preventDefault(); createConversation();"><div class="grid grid-cols-1 md:grid-cols-3 gap-3"><input id="newPhoneNumber" class="border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500" style="border-radius:12px;font-size:0.9rem" type="text" placeholder="Phone number"><input id="newContactName" class="border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500" style="border-radius:12px;font-size:0.9rem" type="text" placeholder="Contact name"><button class="wa-btn primary" type="submit" style="border-radius:12px;min-height:48px;font-size:0.9rem">Create Conversation</button></div></form></div></div></div></div>
<div id="templateModal" class="wa-modal-bg wa-hidden"><div class="wa-modal"><div class="wa-modal-head"><div class="flex items-center justify-between"><button class="wa-modal-back-btn" type="button" onclick="closeTemplateModal()" aria-label="Close" style="width:38px;height:38px;border-radius:50%;border:none;background:#f3f4f6;color:#374151;display:none;align-items:center;justify-content:center;flex-shrink:0;font-size:0.95rem;cursor:pointer"><i class="fas fa-arrow-left"></i></button><div style="flex:1;min-width:0"><div class="text-lg font-bold text-slate-900">Message Templates</div><div class="text-sm text-slate-500 mt-1">Send approved WhatsApp templates</div></div><div class="flex gap-2"><button id="syncTemplatesBtn" class="wa-btn outline" type="button" onclick="syncTemplates()" style="border-radius:10px;padding:8px 14px;font-size:0.8rem"><i class="fas fa-sync-alt mr-1"></i>Sync</button><button class="wa-modal-close-desktop" type="button" onclick="closeTemplateModal()" style="width:34px;height:34px;border-radius:10px;border:none;background:#f3f4f6;color:#374151;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.85rem;flex-shrink:0"><i class="fas fa-times"></i></button></div></div></div><div class="wa-modal-body"><input id="templateSearch" class="w-full border border-slate-200 px-4 py-3 outline-none focus:border-emerald-500" style="border-radius:12px;font-size:0.9rem" type="text" oninput="renderTemplatesList(this.value)" placeholder="Search templates"><div id="templatesList" class="grid gap-3 md:grid-cols-2 mt-4"></div></div></div></div>
<script>
document.body.classList.add('chat-page-active');
['#chatbotWidget','#chatbotToggle','#chatbotWindow'].forEach((selector)=>document.querySelector(selector)?.remove());
const currentUserId={{ auth()->id() }},persistedConversationKey='crm.whatsapp.activeConversation',persistedWabaFilterKey='crm.whatsapp.selectedWabaAccount',initialConversations=@json($initialConversations),quickReplies=@json(($quickReplies ?? collect())->map(fn($reply)=>['id'=>$reply->id,'title'=>$reply->title,'message'=>$reply->message])->values()),wabaAccounts=@json($wabaAccountOptions);let conversationsCache=initialConversations,currentConversationId=null,currentConversationData=null,messagePollingInterval=null,conversationSearchTerm='',conversationVisibilityFilter='all',wabaAccountFilter='all';window.templatesData=[];
function setMobileChatState(hasActive){const page=document.querySelector('.wa-page');if(!page)return;page.classList.toggle('wa-has-active-chat',!!hasActive)}
function getCsrfToken(){return document.querySelector('meta[name="csrf-token"]')?.content||''}function saveConversationId(id){id?localStorage.setItem(persistedConversationKey,String(id)):localStorage.removeItem(persistedConversationKey)}function readConversationId(){return localStorage.getItem(persistedConversationKey)}function escapeHtml(text){const div=document.createElement('div');div.textContent=text??'';return div.innerHTML}function isOutgoingMessage(message){return['sent','send','outgoing','from_me'].includes(String(message?.direction||'').toLowerCase())}function getStatusIcon(status){switch(String(status||'').toLowerCase()){case'read':return'check-double text-sky-300';case'delivered':return'check-double';case'failed':return'exclamation-circle';default:return'check'}}function badge(label,variant,icon=''){return`<span class="wa-badge ${variant}">${icon?`<i class="${icon}"></i>`:''}${escapeHtml(label)}</span>`}function conversationName(conversation){return conversation.contact_name||conversation.lead?.name||conversation.phone_number||'Unknown Contact'}function conversationPreview(conversation){const latest=conversation.latest_message;if(!latest){return'No messages yet'}return latest.message||latest.type||'Message'}function formatListTime(dateString){if(!dateString){return''}const date=new Date(String(dateString).replace(' ','T'));if(Number.isNaN(date.getTime())){return''}const now=new Date();return date.toDateString()===now.toDateString()?date.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'}):date.toLocaleDateString('en-IN',{day:'2-digit',month:'short'})}function formatMessageTime(dateString){if(!dateString){return''}const date=new Date(String(dateString).replace(' ','T'));if(Number.isNaN(date.getTime())){return''}return date.toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'})}
function wabaShortLabel(conversation){const label=conversation?.waba_display_phone_number||conversation?.waba_phone_number_id||'Unknown WABA';const digits=String(label).replace(/\D/g,'');return digits.length>=5?digits.slice(-5):label}
function wabaBadge(conversation){if(!conversation?.meta_waba_account_id&&!conversation?.waba_display_phone_number){return badge('Unknown WABA','muted','fab fa-whatsapp')}const label=wabaShortLabel(conversation);return badge(label,conversation.waba_can_send?'waba':'waba-warn','fab fa-whatsapp')}
function updateSendAvailability(conversation){currentConversationData=conversation||null;const input=document.getElementById('messageInput'),button=document.getElementById('sendButton'),warning=document.getElementById('wabaSendWarning'),settings=document.getElementById('chatWabaSettingsLink');const canSend=!conversation||conversation.waba_can_send!==false;if(settings){settings.href=conversation?.waba_settings_url||'{{ route('integrations.meta-waba.index') }}'}if(input){input.disabled=!canSend;input.placeholder=canSend?'Type a message...':'This API number is not verified for sending'}if(button){button.disabled=!canSend}if(warning){if(canSend){warning.classList.remove('is-visible');warning.innerHTML=''}else{const url=conversation?.waba_settings_url||'{{ route('integrations.meta-waba.index') }}';warning.innerHTML=`This API number (${escapeHtml(conversation?.waba_display_phone_number||'Unknown WABA')}) is not verified for sending. <a href="${escapeHtml(url)}" target="_blank">Open API Settings</a>`;warning.classList.add('is-visible')}}}
function normalizeWabaFilter(value){const raw=String(value||'all');if(raw==='all'){return'all'}return wabaAccounts.some((account)=>String(account.id)===raw)?raw:'all'}
function persistWabaFilter(value){wabaAccountFilter=normalizeWabaFilter(value);localStorage.setItem(persistedWabaFilterKey,wabaAccountFilter);const select=document.getElementById('wabaAccountFilter');if(select&&select.value!==wabaAccountFilter){select.value=wabaAccountFilter}const url=new URL(window.location.href);if(wabaAccountFilter==='all'){url.searchParams.delete('waba_account')}else{url.searchParams.set('waba_account',wabaAccountFilter)}window.history.replaceState({},'',url.toString())}
function setWabaAccountFilter(value){const previous=wabaAccountFilter;persistWabaFilter(value||'all');if(previous!==wabaAccountFilter){currentConversationId=null;currentConversationData=null;saveConversationId(null);document.getElementById('activeChat').classList.add('wa-hidden');document.getElementById('emptyState').classList.remove('wa-hidden');document.getElementById('contextPanel').classList.add('wa-hidden');document.getElementById('contextEmptyState').classList.remove('wa-hidden');updateSendAvailability(null)}refreshConversations(true)}
function conversationMatchesVisibilityFilter(conversation){if(conversationVisibilityFilter==='unread'){return Number(conversation.unread_count||0)>0}if(conversationVisibilityFilter==='my_leads'){return Number(conversation.assigned_user?.id||conversation.user_id||0)===Number(currentUserId)}return true}
function filterConversations(list){const term=conversationSearchTerm.trim().toLowerCase();return list.filter((conversation)=>{if(!conversationMatchesVisibilityFilter(conversation)){return false}if(!term){return true}return [conversationName(conversation),conversation.phone_number,conversation.user_name,conversation.assigned_user?.name,conversation.lead?.name,conversation.lead?.status,conversation.lead?.source,conversationPreview(conversation)].filter(Boolean).some((value)=>String(value).toLowerCase().includes(term))})}
function updateConversationFilterUi(){document.querySelectorAll('[data-chat-filter]').forEach((button)=>button.classList.toggle('active',button.dataset.chatFilter===conversationVisibilityFilter))}
function setConversationVisibilityFilter(filter){conversationVisibilityFilter=filter||'all';updateConversationFilterUi();renderConversationsList(conversationsCache)}
function closeConversationDrawer(){if(window.innerWidth<=900){setMobileChatState(false)}else{document.getElementById('waSidebar')?.classList.remove('mobile-open')}}
function openConversationDrawer(){if(window.innerWidth<=900){setMobileChatState(false)}else{document.getElementById('waSidebar')?.classList.add('mobile-open')}}
function toggleConversationDrawer(){if(window.innerWidth<=900){setMobileChatState(false);return}const sidebar=document.getElementById('waSidebar');if(!sidebar)return;sidebar.classList.toggle('mobile-open')}
function handleMobileChatBack(){if(window.innerWidth<=900){setMobileChatState(false)}}
function renderConversationsList(list){const container=document.getElementById('conversationsList'),conversations=filterConversations(list);if(!conversations.length){container.innerHTML='<div class="wa-empty"><div><div style="font-size:2rem;margin-bottom:12px;opacity:.35"><i class="fas fa-comments"></i></div><div class="text-base font-semibold text-slate-900 mb-1">No conversations</div><div class="text-sm text-slate-500">Try a different search or start a new chat.</div></div></div>';return}container.innerHTML=conversations.map((conversation)=>{conversation=applySelectedWabaContext(conversation);const active=String(conversation.id)===String(currentConversationId),unread=Number(conversation.unread_count||0),timeStr=formatListTime(conversation.latest_message?.created_at||conversation.updated_at);return `<div class="wa-item ${active?'active':''}" data-conversation-id="${conversation.id}" onclick="loadConversation(${conversation.id})"><div class="wa-item-row"><div class="wa-avatar">${escapeHtml(conversationName(conversation).charAt(0).toUpperCase())}</div><div class="wa-item-body"><div class="wa-item-top"><span class="wa-item-name">${escapeHtml(conversationName(conversation))}</span><span class="wa-item-time ${unread>0?'has-unread':''}">${timeStr}</span></div><div class="wa-item-bottom"><span class="wa-item-preview">${escapeHtml(conversationPreview(conversation))}</span>${unread>0?`<span class="wa-item-unread">${unread}</span>`:''}</div><div class="wa-item-badges">${wabaBadge(conversation)}${conversation.lead?badge(conversation.lead.status||'Lead','lead'):''}${conversation.assigned_user?.name?badge(conversation.assigned_user.name,'owner'):''}${conversation.lead?.source?badge(conversation.lead.source,'source'):''}</div></div></div></div>`}).join('')}
function renderChatMessageContent(message,isOutgoing){const type=String(message?.type||'text').toLowerCase(),mediaUrl=message?.media_url||'',mimeType=message?.mime_type||'';if(type==='audio'&&mediaUrl){return `<div style="min-width:200px"><audio controls preload="none" style="width:100%;height:36px;border-radius:18px"><source src="${escapeHtml(mediaUrl)}" ${mimeType?`type="${escapeHtml(mimeType)}"`:''}></audio>${message.message?`<p style="font-size:0.82rem;margin-top:4px">${escapeHtml(message.message)}</p>`:''}</div>`}if((type==='image'||type==='sticker')&&mediaUrl){const mw=type==='sticker'?'150px':'240px';return `<div><img src="${escapeHtml(mediaUrl)}" alt="${escapeHtml(message.message||type)}" style="max-width:${mw};max-height:260px;border-radius:8px;display:block;object-fit:cover" loading="lazy">${message.message?`<p style="font-size:0.82rem;margin-top:4px">${escapeHtml(message.message)}</p>`:''}</div>`}if(type==='video'&&mediaUrl){return `<div><video controls preload="metadata" style="max-width:260px;border-radius:8px;display:block;background:#000"><source src="${escapeHtml(mediaUrl)}" ${mimeType?`type="${escapeHtml(mimeType)}"`:''}></video>${message.message?`<p style="font-size:0.82rem;margin-top:4px">${escapeHtml(message.message)}</p>`:''}</div>`}if(type==='document'&&mediaUrl){return `<a href="${escapeHtml(mediaUrl)}" target="_blank" rel="noopener noreferrer" style="display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;background:${isOutgoing?'rgba(0,0,0,.06)':'#f3f4f6'};text-decoration:none;color:inherit"><i class="fas fa-file-alt" style="font-size:1.2rem;opacity:.7"></i><span style="font-size:0.82rem;font-weight:600">${escapeHtml(message.message||'Document')}</span></a>`}return `<p style="font-size:0.88rem;line-height:1.45;margin:0;white-space:pre-wrap;word-break:break-word">${escapeHtml(message.message||'')}</p>`}
function renderMessages(messages){const inner=document.querySelector('#messagesContainer .wa-msgs-inner');if(!messages.length){inner.innerHTML='<div class="wa-empty"><div><div style="font-size:2rem;margin-bottom:10px;opacity:.3"><i class="fas fa-comment-dots"></i></div><div class="text-base font-semibold text-slate-900 mb-1">No messages yet</div><div class="text-sm text-slate-500">Send a message to start the conversation.</div></div></div>';return}inner.innerHTML=messages.map((message)=>{const out=isOutgoingMessage(message);return `<div class="wa-row ${out?'out':'in'}" data-message-id="${message.id}"><div class="wa-bubble">${renderChatMessageContent(message,out)}<div class="wa-meta"><span>${formatMessageTime(message.created_at)}</span>${out?`<i class="fas fa-${getStatusIcon(message.status)}"></i>`:''}</div></div></div>`}).join('');document.getElementById('messagesContainer').scrollTop=document.getElementById('messagesContainer').scrollHeight}
function updateContext(conversation){document.getElementById('emptyState').classList.add('wa-hidden');document.getElementById('activeChat').classList.remove('wa-hidden');document.getElementById('contextEmptyState').classList.add('wa-hidden');document.getElementById('contextPanel').classList.remove('wa-hidden');setMobileChatState(true);updateSendAvailability(conversation);document.getElementById('chatAvatar').textContent=conversationName(conversation).charAt(0).toUpperCase();document.getElementById('chatContactName').textContent=conversationName(conversation);document.getElementById('chatPhoneNumber').textContent=conversation.phone_number||'';document.getElementById('chatHeaderTags').innerHTML=[wabaBadge(conversation),conversation.lead?badge(`Lead ${conversation.lead.status||''}`.trim(),'lead','fas fa-link'):badge('Unlinked','muted','fas fa-unlink'),conversation.lead?.source?badge(conversation.lead.source,'source','fas fa-layer-group'):'',conversation.assigned_user?.name?badge(conversation.assigned_user.name,'owner','fas fa-user-tie'):'',conversation.user_name?badge(`Chat: ${conversation.user_name}`,'owner','fas fa-user'):'',Number(conversation.unread_count||0)>0?badge(`${conversation.unread_count} unread`,'status','fas fa-bell'):''].join('');document.getElementById('contextLeadName').textContent=conversation.lead?.name||'No linked lead';document.getElementById('contextLeadStatus').textContent=conversation.lead?.status||'-';document.getElementById('contextLeadSource').textContent=conversation.lead?.source||'-';document.getElementById('contextLeadOwner').textContent=conversation.assigned_user?.name||'-';document.getElementById('contextLeadPhone').textContent=conversation.lead?.phone||conversation.phone_number||'-';document.getElementById('contextConversationOwner').textContent=conversation.user_name||'-';const link=document.getElementById('contextLeadLink');const chatLeadLink=document.getElementById('chatLeadLink');if(conversation.lead?.url){link.classList.remove('wa-hidden');link.href=conversation.lead.url;chatLeadLink.href=conversation.lead.url;chatLeadLink.setAttribute('aria-disabled','false');chatLeadLink.classList.remove('is-disabled');chatLeadLink.classList.add('is-clickable')}else{link.classList.add('wa-hidden');link.removeAttribute('href');chatLeadLink.href='javascript:void(0)';chatLeadLink.setAttribute('aria-disabled','true');chatLeadLink.classList.add('is-disabled');chatLeadLink.classList.remove('is-clickable')}document.querySelectorAll('.wa-item').forEach((item)=>item.classList.toggle('active',String(item.dataset.conversationId)===String(conversation.id)))}
function refreshConversations(keepSelection=true){const params=new URLSearchParams();if(wabaAccountFilter&&wabaAccountFilter!=='all'){params.set('account_id',wabaAccountFilter)}const url='{{ route("chat.conversations.index") }}'+(params.toString()?`?${params.toString()}`:'');return fetch(url,{headers:{'Accept':'application/json','X-CSRF-TOKEN':getCsrfToken()},credentials:'same-origin'}).then((response)=>response.json()).then((data)=>{if(!data.success){return[]}conversationsCache=Array.isArray(data.data)?data.data:[];renderConversationsList(conversationsCache);if(keepSelection){const preferred=currentConversationId||readConversationId();if(preferred&&conversationsCache.some((item)=>String(item.id)===String(preferred))&&String(currentConversationId)!==String(preferred)){loadConversation(preferred,{fromRefresh:true})}else if(preferred&&!conversationsCache.some((item)=>String(item.id)===String(preferred))){currentConversationId=null;currentConversationData=null;saveConversationId(null);document.getElementById('activeChat').classList.add('wa-hidden');document.getElementById('emptyState').classList.remove('wa-hidden');document.getElementById('contextPanel').classList.add('wa-hidden');document.getElementById('contextEmptyState').classList.remove('wa-hidden');updateSendAvailability(null)}}return conversationsCache}).catch((error)=>{console.error('Error refreshing conversations:',error);return[]})}
function loadConversation(conversationId,options={}){currentConversationId=String(conversationId);saveConversationId(currentConversationId);const params=new URLSearchParams();if(wabaAccountFilter&&wabaAccountFilter!=='all'){params.set('account_id',wabaAccountFilter)}const url=`{{ route('chat.conversations.show', '') }}/${conversationId}`+(params.toString()?`?${params.toString()}`:'');fetch(url,{headers:{'Accept':'application/json','X-CSRF-TOKEN':getCsrfToken()},credentials:'same-origin'}).then((response)=>response.json()).then((data)=>{if(!data.success||!data.data?.conversation){throw new Error(data.message||'Conversation not found')}updateContext(applySelectedWabaContext(data.data.conversation));renderMessages(Array.isArray(data.data.messages)?data.data.messages:[]);if(window.innerWidth<=900){setMobileChatState(true)}else{closeConversationDrawer()}if(!options.fromRefresh){refreshConversations(false)}}).catch((error)=>{console.error('Error loading conversation:',error);alert(error.message||'Failed to load conversation')})}
function addMessageToUI(message){const inner=document.querySelector('#messagesContainer .wa-msgs-inner'),out=isOutgoingMessage(message),row=document.createElement('div');row.className=`wa-row ${out?'out':'in'}`;row.setAttribute('data-message-id',message.id);row.innerHTML=`<div class="wa-bubble">${renderChatMessageContent(message,out)}<div class="wa-meta"><span>${formatMessageTime(message.created_at)}</span>${out?`<i class="fas fa-${getStatusIcon(message.status)}"></i>`:''}</div></div>`;inner.appendChild(row);document.getElementById('messagesContainer').scrollTop=document.getElementById('messagesContainer').scrollHeight}
function syncMessagesFromAPI(conversationId){const params=new URLSearchParams();if(wabaAccountFilter&&wabaAccountFilter!=='all'){params.set('account_id',wabaAccountFilter)}const url=`{{ route('chat.conversations.show', '') }}/${conversationId}/sync-messages`+(params.toString()?`?${params.toString()}`:'');fetch(url,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':getCsrfToken()},credentials:'same-origin'}).then((response)=>response.json()).then((data)=>{if(!data.success||!Array.isArray(data.data?.messages)){return}data.data.messages.forEach((message)=>{if(!document.querySelector(`#messagesContainer [data-message-id="${message.id}"]`)){addMessageToUI(message)}})}).catch((error)=>console.error('Error syncing messages:',error))}
function selectedWabaAccount(){if(!wabaAccountFilter||wabaAccountFilter==='all'){return null}return wabaAccounts.find((account)=>String(account.id)===String(wabaAccountFilter))||null}
function selectedAccountPayload(){const account=selectedWabaAccount();return account?{account_id:account.id}:{}}
function applySelectedWabaContext(conversation){const account=selectedWabaAccount();if(!account||!conversation){return conversation}return {...conversation,meta_waba_account_id:account.id,waba_display_phone_number:account.label,waba_phone_number_id:account.phone_number_id,waba_is_verified:!!account.is_verified,waba_is_active:!!account.is_active,waba_can_send:!!(account.is_active&&account.is_verified),waba_settings_url:account.settings_url}}
function sendMessage(){if(!currentConversationId){alert('Please select a conversation first.');return}if(currentConversationData&&currentConversationData.waba_can_send===false){alert('This API number is not verified for sending. Open API Settings and verify/register this number first.');updateSendAvailability(currentConversationData);return}const input=document.getElementById('messageInput'),message=input.value.trim();if(!message){return}const button=document.getElementById('sendButton');button.disabled=true;button.innerHTML='<i class="fas fa-spinner fa-spin"></i>';fetch('{{ route("chat.messages.send") }}',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':getCsrfToken()},credentials:'same-origin',body:JSON.stringify({conversation_id:currentConversationId,message,...selectedAccountPayload()})}).then((response)=>response.json()).then((data)=>{if(!data.success){throw new Error(data.error||data.message||'Failed to send message')}input.value='';input.style.height='auto';loadConversation(currentConversationId,{fromRefresh:true})}).catch((error)=>{console.error('Error sending message:',error);alert(error.message||'Error sending message')}).finally(()=>{button.innerHTML='<i class="fas fa-paper-plane"></i>';button.disabled=!!(currentConversationData&&currentConversationData.waba_can_send===false)})}
function insertQuickReply(id){const reply=(quickReplies||[]).find((item)=>Number(item.id)===Number(id));if(!reply){return}const input=document.getElementById('messageInput');input.value=reply.message||'';input.focus();input.style.height='auto';input.style.height=`${input.scrollHeight}px`}
async function searchLeads(query){const list=document.getElementById('leadsList');list.innerHTML='<div class="p-4 text-center text-gray-500 text-sm">Loading...</div>';try{const response=await fetch(`{{ route('chat.leads.index') }}?search=${encodeURIComponent(query)}`,{headers:{'Accept':'application/json','X-CSRF-TOKEN':getCsrfToken()},credentials:'same-origin'}),data=await response.json(),leads=Array.isArray(data.data)?data.data:[];if(!leads.length){list.innerHTML='<div class="p-4 text-center text-gray-500 text-sm">No leads found</div>';return}list.innerHTML=leads.map((lead)=>`<button type="button" onclick="selectLead('${escapeHtml(lead.phone)}','${escapeHtml((lead.name || '').replace(/'/g,'&#39;'))}')" class="w-full border-b border-slate-100 p-4 text-left hover:bg-emerald-50"><div class="flex items-center justify-between gap-3"><div><div class="font-semibold text-slate-900">${escapeHtml(lead.name||lead.phone)}</div><div class="text-sm text-slate-500 mt-1">${escapeHtml(lead.phone||'')}</div></div><span class="wa-badge status">${escapeHtml(lead.status||'new')}</span></div></button>`).join('')}catch(error){console.error('Error loading leads:',error);list.innerHTML='<div class="p-4 text-center text-red-500 text-sm">Error loading leads</div>'}}
function selectLead(phone,name){document.getElementById('newPhoneNumber').value=phone;document.getElementById('newContactName').value=name;createConversation()}
function createConversation(){const phone=document.getElementById('newPhoneNumber').value.trim(),name=document.getElementById('newContactName').value.trim();fetch('{{ route("chat.conversations.create") }}',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':getCsrfToken()},credentials:'same-origin',body:JSON.stringify({phone_number:phone,contact_name:name||null})}).then((response)=>response.json()).then((data)=>{if(!data.success||!data.data?.id){throw new Error(data.message||'Failed to create conversation')}closeAddContactModal();currentConversationId=String(data.data.id);saveConversationId(currentConversationId);return refreshConversations(false).then(()=>loadConversation(data.data.id))}).catch((error)=>{console.error('Error creating conversation:',error);alert(error.message||'Error creating conversation')})}
function openAddContactModal(){document.getElementById('addContactModal').classList.remove('wa-hidden');searchLeads('')}
function closeAddContactModal(){document.getElementById('addContactModal').classList.add('wa-hidden');document.getElementById('addContactForm').reset()}
function openTemplateModal(){if(!currentConversationId){alert('Please select a conversation first.');return}document.getElementById('templateModal').classList.remove('wa-hidden');loadTemplates()}
function closeTemplateModal(){document.getElementById('templateModal').classList.add('wa-hidden')}
function renderTemplatesList(searchTerm=''){const container=document.getElementById('templatesList'),term=searchTerm.trim().toLowerCase(),templates=Array.isArray(window.templatesData)?window.templatesData:[],filtered=term?templates.filter((template)=>[template.name,template.template_id,template.category,template.language,template.content].filter(Boolean).some((value)=>String(value).toLowerCase().includes(term))):templates;if(!filtered.length){container.innerHTML='<div class="col-span-full p-6 text-center text-slate-500" style="font-size:0.9rem">No templates found.</div>';return}container.innerHTML=filtered.map((template)=>`<div style="border:1px solid #e5e7eb;border-radius:14px;padding:14px;background:#fafafa"><div style="font-size:0.92rem;font-weight:700;color:#111827">${escapeHtml(template.name||template.template_id||'Unnamed Template')}</div><div style="font-size:0.82rem;color:#6b7280;margin-top:6px;line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden">${escapeHtml(template.content||'No content available')}</div><div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:8px">${template.category?badge(template.category,'source'):''}${template.language?badge(template.language,'lead'):''}</div><div style="margin-top:10px;display:flex;justify-content:flex-end"><button class="wa-btn primary" type="button" onclick="sendTemplate('${escapeHtml(template.template_id)}')" style="padding:8px 18px;border-radius:10px;font-size:0.82rem">Send</button></div></div>`).join('')}
function templateAccountQuery(){const params=new URLSearchParams();const account=selectedWabaAccount();if(account){params.set('account_id',account.id)}return params.toString()}
function loadTemplates(){const query=templateAccountQuery();const url='{{ route("chat.templates.index") }}'+(query?`?${query}`:'');fetch(url,{headers:{'Accept':'application/json','X-CSRF-TOKEN':getCsrfToken()},credentials:'same-origin'}).then((response)=>response.json()).then((data)=>{if(!data.success){throw new Error(data.message||'Failed to load templates')}window.templatesData=Array.isArray(data.data)?data.data:[];renderTemplatesList(document.getElementById('templateSearch').value||'')}).catch((error)=>{console.error('Error loading templates:',error);document.getElementById('templatesList').innerHTML=`<div class="col-span-full p-6 text-center text-red-500">${escapeHtml(error.message||'Error loading templates')}</div>`})}
function syncTemplates(){const button=document.getElementById('syncTemplatesBtn'),query=templateAccountQuery();button.disabled=true;button.innerHTML='<i class="fas fa-spinner fa-spin mr-2"></i>Syncing';fetch('{{ route("chat.templates.sync") }}'+(query?`?${query}`:''),{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':getCsrfToken()},credentials:'same-origin'}).then((response)=>response.json()).then((data)=>{if(!data.success){throw new Error(data.message||'Failed to sync templates')}loadTemplates()}).catch((error)=>{console.error('Error syncing templates:',error);alert(error.message||'Failed to sync templates')}).finally(()=>{button.disabled=false;button.innerHTML='<i class="fas fa-sync-alt mr-2"></i>Sync'})}
function sendTemplate(templateId){fetch('{{ route("chat.messages.template") }}',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':getCsrfToken()},credentials:'same-origin',body:JSON.stringify({conversation_id:currentConversationId,template_id:templateId,parameters:{},...selectedAccountPayload()})}).then((response)=>response.json()).then((data)=>{if(!data.success){throw new Error(data.error||data.message||'Failed to send template')}closeTemplateModal();loadConversation(currentConversationId,{fromRefresh:true})}).catch((error)=>{console.error('Error sending template:',error);alert(error.message||'Failed to send template')})}
function deleteCurrentConversation(){if(!currentConversationId){return}if(!confirm('Delete this conversation?')){return}fetch(`{{ route('chat.conversations.delete', '') }}/${currentConversationId}`,{method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':getCsrfToken()},credentials:'same-origin'}).then((response)=>response.json()).then((data)=>{if(!data.success){throw new Error(data.message||'Failed to delete conversation')}const deleted=currentConversationId;currentConversationId=null;saveConversationId(null);setMobileChatState(false);document.getElementById('activeChat').classList.add('wa-hidden');document.getElementById('emptyState').classList.remove('wa-hidden');document.getElementById('contextPanel').classList.add('wa-hidden');document.getElementById('contextEmptyState').classList.remove('wa-hidden');refreshConversations(false).then((list)=>{const next=list.find((item)=>String(item.id)!==String(deleted));if(next){loadConversation(next.id)}})}).catch((error)=>{console.error('Error deleting conversation:',error);alert(error.message||'Error deleting conversation')})}
function startMessagePolling(){if(messagePollingInterval){clearInterval(messagePollingInterval)}messagePollingInterval=setInterval(()=>{refreshConversations(false);if(currentConversationId){syncMessagesFromAPI(currentConversationId)}},10000)}
document.getElementById('searchConversations').addEventListener('input',function(){conversationSearchTerm=this.value||'';renderConversationsList(conversationsCache)});document.getElementById('messageInput').addEventListener('input',function(){this.style.height='auto';this.style.height=`${this.scrollHeight}px`});document.getElementById('messageInput').addEventListener('keydown',function(event){if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage()}});document.getElementById('addContactModal').addEventListener('click',function(event){if(event.target===this){closeAddContactModal()}});document.getElementById('templateModal').addEventListener('click',function(event){if(event.target===this){closeTemplateModal()}});const queryWabaFilter=new URLSearchParams(window.location.search).get('waba_account'),storedWabaFilter=localStorage.getItem(persistedWabaFilterKey),domWabaFilter=document.getElementById('wabaAccountFilter')?.value||'all';persistWabaFilter(queryWabaFilter||storedWabaFilter||domWabaFilter||'all');updateConversationFilterUi();const bootChat=()=>{const isMobileChatView=window.innerWidth<=900,savedConversationId=readConversationId(),defaultConversationId=savedConversationId&&conversationsCache.some((item)=>String(item.id)===String(savedConversationId))?savedConversationId:conversationsCache[0]?.id;if(isMobileChatView){setMobileChatState(false)}else if(defaultConversationId){loadConversation(defaultConversationId,{fromRefresh:true})}else{setMobileChatState(false)}};if(wabaAccountFilter&&wabaAccountFilter!=='all'){refreshConversations(false).then(()=>bootChat())}else{renderConversationsList(conversationsCache);bootChat()}startMessagePolling();
</script>
@endsection
