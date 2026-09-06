@extends('layouts.app')

@section('title', 'Insight Sheet')

@section('content')
<div class="insight-shell" data-can-edit="{{ $canEditInsightSheet ? '1' : '0' }}" data-can-export="{{ $canExportInsightSheet ? '1' : '0' }}" data-can-manage-access="{{ $canManageInsightSheetAccess ? '1' : '0' }}" data-can-transfer="{{ $canTransferInsightSheetLead ? '1' : '0' }}" data-user-id="{{ auth()->id() }}">
    <div class="insight-toolbar">
        <div>
            <h1>Insight Sheet</h1>
        </div>
        <div class="insight-actions">
            <div class="insight-phone-search" role="search">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input form="insightFilters" type="search" name="search" inputmode="tel" autocomplete="off" placeholder="Search by mobile number" aria-label="Search lead by mobile number">
                <button form="insightFilters" type="submit" aria-label="Search"><i class="fas fa-arrow-right" aria-hidden="true"></i></button>
            </div>
            <button type="button" id="insightFullscreen" class="insight-btn secondary" title="Full screen"><i class="fas fa-expand"></i></button>
            <select id="insightDensity" class="insight-toolbar-select" title="Density">
                <option value="compact">Compact</option>
                <option value="comfortable">Comfortable</option>
            </select>
            <label class="insight-page-size" title="Rows per page">
                <span>Rows</span>
                <select id="insightPageSize" class="insight-toolbar-select" aria-label="Rows per page">
                    <option value="100">100</option>
                    <option value="250">250</option>
                    <option value="500">500</option>
                </select>
            </label>
            <nav class="insight-pager" aria-label="Insight Sheet pages">
                <button type="button" class="insight-btn secondary" id="insightPrev" aria-label="Previous page" title="Previous page"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                <span id="insightPage">Page 1</span>
                <button type="button" class="insight-btn secondary" id="insightNext" aria-label="Next page" title="Next page"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
            </nav>
            <button type="button" id="insightResetLayout" class="insight-btn secondary" title="Reset layout"><i class="fas fa-rotate-left"></i></button>
            <button type="button" id="insightToggleSearchFilters" class="insight-btn secondary" aria-controls="insightFilters" aria-expanded="false">
                <i class="fas fa-filter"></i> Filters <i id="insightFilterChevron" class="fas fa-chevron-down" aria-hidden="true"></i>
            </button>
            <button type="button" id="insightToggleColumnFilters" class="insight-btn secondary"><i class="fas fa-filter-circle-xmark"></i> Column Filters</button>
            <button type="button" id="insightChooseColumns" class="insight-btn secondary" aria-controls="insightColumnChooser" aria-expanded="false">
                <i class="fas fa-table-columns"></i> Columns
            </button>
            @if($canManageInsightSheetAccess)
                <button type="button" id="insightAccessBtn" class="insight-btn secondary"><i class="fas fa-user-shield"></i> Access</button>
            @endif
            @if($canExportInsightSheet)
                @if($canManageInsightSheetAccess)
                    <label class="insight-export-meta"><input type="checkbox" id="insightExportMeta"> Audit columns</label>
                @endif
                <button type="button" class="insight-btn" data-export="filtered"><i class="fas fa-file-export"></i> Export Filtered</button>
                <button type="button" class="insight-btn secondary" data-export="full"><i class="fas fa-download"></i> Export Full</button>
            @endif
            @if($canEditInsightSheet)
                <button type="button" id="insightSaveAll" class="insight-btn primary"><i class="fas fa-save"></i> Save Changes</button>
            @endif
        </div>
    </div>

    <form id="insightFilters" class="insight-filters" hidden>
        <select name="source" id="insightSource"><option value="">All Sources</option></select>
        <select name="status" id="insightStatus"><option value="">All Status</option></select>
        <select name="advisor" id="insightAdvisor"><option value="">All Advisors</option></select>
        <input type="date" name="start_date">
        <input type="date" name="end_date">
        <button type="submit" class="insight-btn primary"><i class="fas fa-filter"></i> Filter</button>
        <button type="button" id="insightClear" class="insight-btn secondary">Clear</button>
    </form>

    <div class="insight-grid-frame">
        <div id="insightLoading" class="insight-loading" role="status" aria-live="polite">
            <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i>
            <strong id="insightLoadingText">Loading data...</strong>
        </div>
        <div class="insight-grid-wrap">
            <table class="insight-grid" id="insightGrid">
                <thead>
                    <tr>
                        @foreach($columns as $column)
                            <th data-column="{{ $column['key'] }}">
                                <span>{{ $column['label'] }}</span>
                                <button type="button" class="insight-column-menu-btn" data-column-menu="{{ $column['key'] }}" aria-label="Column menu {{ $column['label'] }}">
                                    <i class="fas fa-ellipsis-vertical"></i>
                                </button>
                                <button type="button" class="insight-column-lock" data-column-lock="{{ $column['key'] }}" aria-label="Lock {{ $column['label'] }}">
                                    <i class="fas fa-lock-open"></i>
                                </button>
                                <button type="button" class="insight-column-filter" data-column-filter="{{ $column['key'] }}" aria-label="Filter {{ $column['label'] }}">
                                    <i class="fas fa-filter"></i>
                                </button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="insight-bottom-status" role="status" aria-live="polite" aria-atomic="true">
        <span class="insight-bottom-status-item"><i class="fas fa-table-list" aria-hidden="true"></i><span id="insightCount">Loading...</span></span>
        <span class="insight-bottom-status-divider" aria-hidden="true"></span>
        <span class="insight-bottom-status-item"><i class="fas fa-circle-check" aria-hidden="true"></i><span id="insightSaveState">Ready</span></span>
    </div>

    <div id="insightColumnFilterMenu" class="insight-column-filter-menu" hidden>
        <div class="filter-menu-title">Filter</div>
        <div class="filter-command-row">
            <button type="button" class="filter-command" data-sort="asc"><i class="fas fa-arrow-down-a-z"></i> Sort A-Z</button>
            <button type="button" class="filter-command" data-sort="desc"><i class="fas fa-arrow-down-z-a"></i> Sort Z-A</button>
        </div>
        <div class="filter-command-row">
            <button type="button" class="filter-command" id="insightFreezeFromMenu"><i class="fas fa-lock"></i> Freeze</button>
            <button type="button" class="filter-command" id="insightHideColumn"><i class="fas fa-eye-slash"></i> Hide</button>
            <button type="button" class="filter-command" id="insightAutoFitColumn"><i class="fas fa-arrows-left-right"></i> Auto fit</button>
        </div>
        <input type="search" id="insightColumnFilterSearch" placeholder="Search">
        <div id="insightDateFilterRange" class="filter-date-range" hidden>
            <div class="date-quick-row">
                <button type="button" data-date-quick="today">Today</button>
                <button type="button" data-date-quick="yesterday">Yesterday</button>
                <button type="button" data-date-quick="week">This Week</button>
                <button type="button" data-date-quick="month">This Month</button>
            </div>
            <label>From <input type="date" id="insightDateFilterFrom"></label>
            <label>To <input type="date" id="insightDateFilterTo"></label>
        </div>
        <label class="filter-check filter-check-all">
            <input type="checkbox" id="insightColumnFilterSelectAll" checked>
            <span>(Select All)</span>
        </label>
        <div id="insightColumnFilterOptions" class="filter-options"></div>
        <div class="filter-menu-actions">
            <button type="button" class="insight-btn primary" id="insightApplyColumnFilter">OK</button>
            <button type="button" class="insight-btn secondary" id="insightClearColumnFilter">Clear</button>
            <button type="button" class="insight-btn secondary" id="insightCancelColumnFilter">Cancel</button>
        </div>
    </div>

    <div id="insightColumnChooserBackdrop" class="insight-column-chooser-backdrop" hidden></div>
    <aside id="insightColumnChooser" class="insight-column-chooser" role="dialog" aria-modal="true" aria-labelledby="insightColumnChooserTitle" hidden>
        <div class="column-chooser-head">
            <div>
                <h2 id="insightColumnChooserTitle">Choose columns</h2>
                <p><strong id="insightVisibleColumnCount">0</strong> of {{ count($columns) }} visible</p>
            </div>
            <button type="button" id="insightCloseColumnChooser" class="audit-close" aria-label="Close columns"><i class="fas fa-times"></i></button>
        </div>
        <input type="search" id="insightColumnChooserSearch" class="column-chooser-search" placeholder="Search columns" aria-label="Search columns">
        <div id="insightColumnChooserList" class="column-chooser-list">
            @foreach($columns as $column)
                <label class="column-choice" data-column-choice="{{ strtolower($column['label']) }}">
                    <input type="checkbox" value="{{ $column['key'] }}">
                    <span>{{ $column['label'] }}</span>
                </label>
            @endforeach
        </div>
        <div class="column-chooser-actions">
            <button type="button" id="insightShowAllColumns" class="insight-btn secondary"><i class="fas fa-eye"></i> Show all</button>
            <button type="button" id="insightCancelColumnChooser" class="insight-btn secondary">Cancel</button>
            <button type="button" id="insightSaveColumns" class="insight-btn primary"><i class="fas fa-save"></i> Save columns</button>
        </div>
    </aside>

    <div id="insightDirtyBar" class="insight-dirty-bar" hidden>
        <span><strong id="insightPendingCount">0</strong> change(s) pending</span>
        <button type="button" id="insightRetryFailed" class="insight-btn secondary">Retry Failed</button>
        <button type="button" id="insightDiscardChanges" class="insight-btn secondary">Discard</button>
        @if($canEditInsightSheet)
            <button type="button" id="insightSavePending" class="insight-btn primary">Save All</button>
        @endif
    </div>

    <aside id="insightAuditPanel" class="insight-audit-panel" hidden>
        <div class="audit-head">
            <div>
                <h2 id="auditPanelTitle">Cell Audit</h2>
                <p id="auditCellTitle">Select a cell</p>
            </div>
            <button type="button" id="auditClose" class="audit-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="audit-section audit-cell-context">
            <label>Current Value</label>
            <div id="auditCurrentValue" class="audit-value"></div>
        </div>
        <div class="audit-section audit-cell-context">
            <label>CRM Source Value</label>
            <div id="auditSourceValue" class="audit-value"></div>
        </div>
        <div class="audit-section audit-cell-context">
            <label>Override</label>
            <div id="auditOverrideValue" class="audit-value muted">No override</div>
            @if($canEditInsightSheet)
                <button type="button" id="auditResetCell" class="insight-btn secondary audit-reset-btn" hidden>Reset to CRM value</button>
            @endif
        </div>
        <div class="audit-section">
            <label id="auditHistoryLabel">History</label>
            @if($isLeadQualityAuditor)
                <div id="internalRemarkComposer" class="internal-remark-composer" hidden>
                    <textarea id="internalRemarkInput" class="insight-textarea" rows="4" maxlength="5000" placeholder="Add internal audit remark"></textarea>
                    <button type="button" id="internalRemarkSave" class="insight-btn primary">Add Remark</button>
                    <span id="internalRemarkStatus" class="internal-remark-status" role="status"></span>
                </div>
            @endif
            <div id="auditHistory" class="audit-history">No history</div>
        </div>
    </aside>

    <aside id="insightLeadDetailsPanel" class="insight-audit-panel insight-lead-details-panel" hidden>
        <div class="audit-head">
            <div>
                <h2 id="leadDetailsTitle">Lead Details</h2>
                <p id="leadDetailsSubtitle">Loading...</p>
            </div>
            <div class="lead-detail-head-actions">
                <button type="button" id="leadDetailsEdit" class="audit-close" aria-label="Edit customer details" title="Edit customer details" hidden><i class="fas fa-pen"></i></button>
                <button type="button" id="leadDetailsClose" class="audit-close" aria-label="Close lead details"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div id="leadDetailsContent" class="lead-details-content">Loading lead details...</div>
    </aside>

    @if($canTransferInsightSheetLead)
    <div id="insightTransferModal" class="insight-modal" role="dialog" aria-modal="true" aria-labelledby="insightTransferTitle" hidden>
        <form id="insightTransferForm" class="insight-modal-card insight-transfer-card">
            <div class="audit-head">
                <div>
                    <h2 id="insightTransferTitle">Transfer Lead</h2>
                    <p id="insightTransferLead">Select the new lead owner.</p>
                </div>
                <button type="button" id="insightTransferClose" class="audit-close" aria-label="Close transfer"><i class="fas fa-times"></i></button>
            </div>
            <input type="hidden" name="row_key" id="insightTransferRowKey">
            <label class="insight-transfer-field">
                <span>New owner</span>
                <select name="assigned_to" id="insightTransferOwner" required>
                    <option value="">Select team member</option>
                    @foreach($transferUsers as $transferUser)
                        <option value="{{ $transferUser->id }}">{{ $transferUser->name }} — {{ $transferUser->role?->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="insight-transfer-field">
                <span>Transfer reason</span>
                <textarea name="notes" id="insightTransferNotes" rows="4" maxlength="1000" required placeholder="Why is this lead being transferred?"></textarea>
            </label>
            <p id="insightTransferStatus" class="insight-transfer-status" role="status" aria-live="polite"></p>
            <div class="filter-menu-actions insight-transfer-actions">
                <button type="button" id="insightTransferCancel" class="insight-btn secondary">Cancel</button>
                <button type="submit" id="insightTransferSubmit" class="insight-btn primary"><i class="fas fa-right-left"></i> Transfer Lead</button>
            </div>
        </form>
    </div>
    @endif

    @if($canManageInsightSheetAccess)
    <div id="insightAccessModal" class="insight-modal" hidden>
        <div class="insight-modal-card">
            <div class="audit-head">
                <div>
                    <h2>Insight Sheet Access</h2>
                    <p>Grant role permissions for internal users.</p>
                </div>
                <button type="button" id="accessClose" class="audit-close"><i class="fas fa-times"></i></button>
            </div>
            <div id="insightAccessList" class="access-list">Loading...</div>
            <div class="filter-menu-actions">
                <button type="button" id="accessSave" class="insight-btn primary">Save Access</button>
                <button type="button" id="accessCancel" class="insight-btn secondary">Cancel</button>
            </div>
        </div>
    </div>
    @endif
</div>

<style>
    /* The quality-auditor workspace keeps the sheet edge-to-edge after its sidebar is hidden. */
    @media (min-width: 821px) {
        body.layout-lead-quality-auditor.sidebar-hidden #mainContent,
        body.layout-lead-quality-auditor.sidebar-hidden.nav-text #mainContent,
        body.layout-lead-quality-auditor.sidebar-hidden.nav-icons #mainContent {
            margin-left: 0 !important;
            width: 100vw !important;
        }
        body.layout-lead-quality-auditor.sidebar-hidden #mainContent > .container {
            padding: 10px !important;
        }
    }
    .insight-shell [hidden] { display: none !important; }
    html:has(.insight-shell), body:has(.insight-shell) { overscroll-behavior-x: none; }
    .insight-shell { display: flex; flex-direction: column; gap: 10px; height: calc(100vh - 34px); min-height: 680px; }
    @media (min-width: 769px) {
        /* Keep one scroll owner: the sheet grid. Nested page scrolling causes wheel and trackpad jitter. */
        #mainContent.insight-sheet-active { overflow: hidden !important; }
        #mainContent.insight-sheet-active > .container { height: 100%; padding: 10px !important; }
        #mainContent.insight-sheet-active .insight-shell { height: 100%; min-height: 0; }
    }
    .insight-toolbar, .insight-filters {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; box-shadow: 0 1px 8px rgba(15,23,42,.06);
    }
    .insight-toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: center; min-height: 58px; padding: 10px 12px 10px 36px; }
    .insight-toolbar > div:first-child { flex: 0 0 auto; }
    .insight-toolbar h1 { margin: 0; font-size: 20px; line-height: 1.2; font-weight: 800; color: #0f172a; white-space: nowrap; }
    .insight-actions { display: flex; flex: 1 1 auto; min-width: 0; gap: 6px; flex-wrap: nowrap; align-items: center; justify-content: flex-start; overflow-x: auto; scrollbar-width: thin; }
    .insight-phone-search { display: inline-flex; align-items: center; flex: 0 0 230px; min-height: 34px; overflow: hidden; border: 1px solid #94a3b8; border-radius: 7px; background: #fff; color: #64748b; }
    .insight-phone-search > i { padding-left: 9px; font-size: 12px; }
    .insight-phone-search input { width: 100%; min-width: 0; border: 0; outline: 0; padding: 7px 6px; color: #0f172a; font-size: 12px; }
    .insight-phone-search button { align-self: stretch; width: 34px; border: 0; background: #0f5132; color: #fff; cursor: pointer; }
    .insight-phone-search:focus-within { outline: 2px solid #16a34a; outline-offset: 2px; }
    .insight-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .insight-filters[hidden] { display: none; }
    #insightFilterChevron { font-size: 10px; transition: transform .18s ease; }
    #insightToggleSearchFilters[aria-expanded="true"] #insightFilterChevron { transform: rotate(180deg); }
    @media (prefers-reduced-motion: reduce) { #insightFilterChevron { transition: none; } }
    .insight-toolbar-select {
        border: 1px solid #cbd5e1; background: #fff; color: #0f172a; border-radius: 7px; padding: 6px 9px; font-weight: 700; font-size: 12px; min-height: 34px;
    }
    .insight-page-size { display: inline-flex; align-items: center; gap: 6px; color: #475569; font-size: 12px; font-weight: 800; }
    .insight-page-size .insight-toolbar-select { min-width: 72px; }
    .insight-export-meta { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #475569; }
    .insight-filters input, .insight-filters select {
        border: 1px solid #d1d5db; border-radius: 7px; padding: 8px 10px; min-height: 38px; font-size: 13px; background: #fff;
    }
    .insight-filters input[type="search"] { min-width: 240px; }
    .insight-btn {
        border: 1px solid #cbd5e1; background: #fff; color: #0f172a; border-radius: 7px; padding: 6px 9px; font-weight: 700; font-size: 12px;
        display: inline-flex; align-items: center; gap: 5px; min-height: 34px; white-space: nowrap; cursor: pointer; transition: background-color .15s ease, border-color .15s ease, color .15s ease;
    }
    .insight-btn.primary { background: #0f5132; color: #fff; border-color: #0f5132; }
    .insight-btn.secondary { background: #f8fafc; }
    .insight-btn:hover:not(:disabled) { background: #f1f5f9; border-color: #94a3b8; }
    .insight-btn.primary:hover:not(:disabled) { background: #0b3d2e; border-color: #0b3d2e; }
    .insight-btn:focus-visible, .insight-toolbar-select:focus-visible { outline: 2px solid #16a34a; outline-offset: 2px; }
    .insight-btn:disabled { cursor: not-allowed; opacity: .5; }
    .insight-pager { display: flex; align-items: center; gap: 4px; padding: 0 6px; border-inline: 1px solid #e2e8f0; }
    .insight-pager .insight-btn { width: 30px; height: 30px; min-height: 30px; padding: 0; justify-content: center; }
    .insight-pager .insight-btn:disabled { cursor: not-allowed; opacity: .42; }
    #insightPage { min-width: 72px; text-align: center; font-weight: 700; white-space: nowrap; }
    .insight-grid-frame { flex: 1 1 auto; min-height: 0; position: relative; }
    .insight-grid-wrap {
        height: 100%; min-height: 0; overflow: auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
        overscroll-behavior: none; scroll-behavior: auto; touch-action: pan-x pan-y; -webkit-overflow-scrolling: touch;
        will-change: scroll-position; transform: translateZ(0); backface-visibility: hidden;
        scrollbar-color: transparent transparent;
    }
    .insight-grid-wrap:hover, .insight-grid-wrap.scrollbar-active { scrollbar-color: #94a3b8 #f1f5f9; }
    .insight-grid-wrap::-webkit-scrollbar { width: 7px; height: 7px; }
    .insight-grid-wrap::-webkit-scrollbar-track { background: transparent; }
    .insight-grid-wrap::-webkit-scrollbar-thumb { background: transparent; border: 2px solid transparent; border-radius: 8px; }
    .insight-grid-wrap:hover::-webkit-scrollbar-track, .insight-grid-wrap.scrollbar-active::-webkit-scrollbar-track { background: #f1f5f9; }
    .insight-grid-wrap:hover::-webkit-scrollbar-thumb, .insight-grid-wrap.scrollbar-active::-webkit-scrollbar-thumb { background: #94a3b8; border-color: #f1f5f9; }
    .insight-grid-wrap:hover::-webkit-scrollbar-thumb:hover, .insight-grid-wrap.scrollbar-active::-webkit-scrollbar-thumb:hover { background: #64748b; }
    .insight-bottom-status {
        min-height: 30px; display: flex; align-items: center; gap: 10px; padding: 0 10px; margin-top: -3px;
        border: 1px solid #e2e8f0; border-radius: 0 0 8px 8px; background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 700;
    }
    .insight-bottom-status-item { display: inline-flex; align-items: center; gap: 5px; min-width: 0; }
    .insight-bottom-status-item i { color: #0f766e; font-size: 10px; }
    .insight-bottom-status-divider { width: 1px; height: 13px; background: #dbe3ed; }
    .insight-loading {
        align-items: center; background: rgba(255,255,255,.9); color: #0f5132; display: flex; flex-direction: column; gap: 10px;
        inset: 0; justify-content: center; min-height: 220px; position: absolute; z-index: 20;
    }
    .insight-loading i { font-size: 28px; }
    .insight-loading strong { color: #334155; font-size: 13px; }
    .insight-loading.is-error i { animation: none; color: #dc2626; }
    .insight-grid { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; font-size: 12px; }
    .insight-grid th {
        position: sticky; top: 0; z-index: 2; background: #0b3d2e; color: #fff; text-align: left; padding: 9px 10px; border-right: 1px solid rgba(255,255,255,.18);
        white-space: nowrap; min-width: 140px; font-size: 11px; letter-spacing: 0;
    }
    .insight-grid th { height: 38px; }
    .insight-grid th > span { display: inline-block; padding-right: 52px; }
    .insight-grid th[data-column="view"], .insight-grid td[data-column="view"] { min-width: 185px; width: 185px; }
    .insight-grid th[data-column="all_remarks"], .insight-grid td[data-column="all_remarks"] { min-width: 130px; width: 130px; }
    .insight-grid th[data-column="internal_remark"], .insight-grid td[data-column="internal_remark"] { min-width: 150px; width: 150px; }
    .insight-view-cell { padding: 6px 8px !important; text-align: center; }
    .insight-view-lead { min-height: 28px; padding: 5px 8px; border: 1px solid #b8d8c7; border-radius: 6px; background: #f0fdf4; color: #0f5132; font-size: 11px; font-weight: 800; cursor: pointer; }
    .insight-view-lead:hover { background: #dcfce7; border-color: #5ca879; }
    .insight-view-lead i { margin-right: 4px; }
    .insight-row-actions { display: flex; align-items: center; justify-content: center; gap: 5px; }
    .insight-transfer-lead { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; }
    .insight-transfer-lead:hover { background: #dbeafe; border-color: #60a5fa; }
    .insight-all-remarks { border-color: #bfd0e6; background: #f3f8ff; color: #174b7a; }
    .insight-all-remarks:hover { background: #e5f0ff; border-color: #6f9dc7; }
    .insight-internal-remark { border-color: #d8c5a4; background: #fff8e8; color: #78520a; }
    .internal-remark-actions { display: flex; align-items: center; justify-content: center; gap: 5px; }
    .internal-remark-actions .insight-internal-remark { flex: 1 1 auto; min-width: 0; }
    .insight-internal-history { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 30px; width: 30px; height: 30px; border: 1px solid #bfd0e6; border-radius: 6px; background: #f3f8ff; color: #174b7a; cursor: pointer; }
    .insight-internal-history:hover { background: #e5f0ff; border-color: #6f9dc7; }
    .insight-internal-history i { margin: 0; }
    .crm-status-actions { display: flex; align-items: center; gap: 5px; min-height: 100%; }
    .crm-status-actions .insight-cell { flex: 1 1 auto; min-width: 0; }
    .insight-completion-view { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 28px; width: 28px; height: 28px; border: 1px solid #b8d8c7; border-radius: 6px; background: #f0fdf4; color: #0f5132; cursor: pointer; }
    .insight-completion-view:hover { background: #dcfce7; border-color: #5ca879; }
    .insight-textarea { width: 100%; resize: vertical; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; font: inherit; margin: 0 0 8px; }
    .insight-column-filter, .insight-column-lock, .insight-column-menu-btn {
        display: inline-flex; align-items: center; justify-content: center; position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
        width: 22px; height: 22px; border: 0; border-radius: 4px; background: rgba(255,255,255,.12); color: #fff; cursor: pointer; opacity: 0; z-index: 3;
    }
    .insight-column-lock { right: 32px; }
    .insight-column-menu-btn { right: 58px; }
    .insight-grid.filters-on .insight-column-filter, .insight-grid th:hover .insight-column-filter, .insight-column-filter.active { opacity: 1; }
    .insight-grid th:hover .insight-column-lock, .insight-column-lock.active, .insight-grid th:hover .insight-column-menu-btn { opacity: 1; }
    .insight-column-filter.active { background: #f59e0b; color: #111827; }
    .insight-column-lock.active { background: #fff; color: #0b3d2e; }
    .insight-column-resizer { position: absolute; top: 0; right: 0; width: 5px; height: 100%; cursor: col-resize; z-index: 1; }
    .insight-column-resizing, .insight-column-resizing * { cursor: col-resize !important; user-select: none; }
    .filter-command-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-bottom: 8px; }
    .filter-command { border: 1px solid #e5e7eb; background: #f8fafc; border-radius: 6px; padding: 6px; font-size: 11px; font-weight: 800; color: #334155; cursor: pointer; }
    .date-quick-row { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; min-width: 0; }
    .date-quick-row button { border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; padding: 6px; font-size: 11px; font-weight: 800; color: #334155; min-width: 0; width: 100%; }
    .insight-column-filter-menu {
        position: fixed; z-index: 1105; width: min(290px, calc(100vw - 24px)); max-height: calc(100vh - 24px); overflow: auto; padding: 10px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;
        box-shadow: 0 16px 40px rgba(15,23,42,.18);
    }
    .insight-column-filter-menu, .insight-column-filter-menu * { box-sizing: border-box; }
    .filter-menu-title { font-size: 12px; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
    .insight-column-filter-menu input { width: 100%; border: 1px solid #cbd5e1; border-radius: 7px; padding: 8px 9px; font-size: 13px; }
    .insight-column-chooser-backdrop { position: fixed; inset: 0; z-index: 1108; background: rgba(15,23,42,.28); }
    .insight-column-chooser {
        position: fixed; inset: 0 0 0 auto; z-index: 1109; display: flex; flex-direction: column; width: min(390px, 100vw); padding: 18px;
        background: #fff; border-left: 1px solid #e2e8f0; box-shadow: -18px 0 50px rgba(15,23,42,.18);
    }
    .column-chooser-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding-bottom: 12px; }
    .column-chooser-head h2 { margin: 0; color: #0f172a; font-size: 18px; }
    .column-chooser-head p { margin: 4px 0 0; color: #64748b; font-size: 12px; }
    .column-chooser-search { width: 100%; min-height: 40px; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 7px; font-size: 13px; }
    .column-chooser-list { flex: 1 1 auto; min-height: 0; overflow: auto; margin: 12px 0; border-block: 1px solid #e2e8f0; }
    .column-choice { display: flex; align-items: center; gap: 10px; min-height: 42px; padding: 8px 4px; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-size: 13px; font-weight: 700; cursor: pointer; }
    .column-choice input { width: 17px; height: 17px; accent-color: #0f5132; }
    .column-choice.is-filtered-out { display: none; }
    .column-chooser-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
    .filter-check { display: flex; align-items: center; gap: 7px; min-height: 24px; margin: 0; font-size: 13px; color: #0f172a; }
    .filter-check input { width: auto; }
    .filter-check-all { margin: 9px 0 5px; font-weight: 700; }
    .filter-date-range { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 8px; padding: 8px; border: 1px solid #e5e7eb; border-radius: 7px; background: #f8fafc; min-width: 0; }
    .filter-date-range[hidden] { display: none !important; }
    .filter-date-range label { display: flex; flex-direction: column; gap: 4px; font-size: 11px; font-weight: 800; color: #475569; min-width: 0; }
    .filter-date-range input { min-width: 0; padding: 7px; }
    .filter-options { max-height: 230px; overflow: auto; border: 1px solid #e5e7eb; border-radius: 7px; padding: 6px 8px; background: #fff; }
    .filter-menu-actions { display: flex; gap: 8px; margin-top: 10px; }
    .insight-grid td {
        border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; padding: 0; min-width: 140px; max-width: 260px; background: #fff;
    }
    .insight-grid th[data-column="serial"], .insight-grid td[data-column="serial"] { min-width: 70px; max-width: 70px; }
    .insight-grid .locked-column { position: sticky; z-index: 4; box-shadow: none; border-right: 1px solid #cbd5e1; }
    .insight-grid th.locked-column { z-index: 6; }
    .insight-grid td.locked-column { background: #fff; }
    .insight-grid tr.filtered-out, .insight-grid .hidden-column { display: none; }
    .insight-grid tbody tr:hover td { background: #f8fafc; }
    .insight-cell {
        position: relative; min-height: 38px; padding: 8px 10px; outline: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .insight-cell:focus { box-shadow: inset 0 0 0 2px #22c55e; background: #f0fdf4; }
    .insight-cell.selected { box-shadow: inset 0 0 0 2px #0ea5e9; background: #f0f9ff; }
    .insight-cell.overridden { background: #fff7ed; }
    .insight-cell.overridden::after, .insight-cell.pending::after, .insight-cell.failed::after {
        content: ''; position: absolute; top: 5px; right: 5px; width: 7px; height: 7px; border-radius: 999px; background: #f97316;
    }
    .insight-cell.pending { background: #eff6ff; }
    .insight-cell.pending::after { background: #2563eb; }
    .insight-cell.failed { background: #fee2e2; box-shadow: inset 0 0 0 1px #dc2626; }
    .insight-cell.failed::after { background: #dc2626; }
    .insight-cell-meta { display: flex; justify-content: space-between; gap: 8px; padding: 3px 8px 6px; color: #94a3b8; font-size: 10px; }
    .insight-reset { border: 0; background: transparent; color: #dc2626; cursor: pointer; font-size: 10px; padding: 0; }
    .insight-shell.density-comfortable .insight-cell { min-height: 54px; padding: 12px 12px; }
    .insight-shell.fullscreen-mode {
        position: fixed; inset: 0; z-index: 999; height: 100vh; min-height: 0; padding: 12px; background: #f8fafc;
    }
    .insight-dirty-bar {
        position: sticky; bottom: 0; z-index: 80; display: flex; justify-content: flex-end; align-items: center; gap: 10px;
        background: #0f172a; color: #fff; border-radius: 8px; padding: 10px 12px; box-shadow: 0 -8px 24px rgba(15,23,42,.18);
    }
    .insight-audit-panel {
        position: fixed; top: 0; right: 0; width: min(420px, 94vw); height: 100vh; z-index: 1001; background: #fff;
        border-left: 1px solid #e5e7eb; box-shadow: -20px 0 50px rgba(15,23,42,.18); padding: 18px; overflow: auto;
    }
    .audit-head { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; margin-bottom: 14px; }
    .audit-head h2 { margin: 0; font-size: 18px; color: #0f172a; }
    .audit-head p { margin: 4px 0 0; font-size: 12px; color: #64748b; }
    .audit-close { border: 0; background: #f1f5f9; color: #334155; width: 34px; height: 34px; border-radius: 7px; cursor: pointer; }
    .audit-section { margin-bottom: 14px; }
    .audit-section label { display: block; margin-bottom: 5px; font-size: 11px; color: #64748b; font-weight: 900; text-transform: uppercase; }
    .audit-value { min-height: 34px; padding: 9px; border: 1px solid #e5e7eb; border-radius: 7px; background: #f8fafc; white-space: pre-wrap; font-size: 13px; color: #0f172a; }
    .audit-value.muted { color: #94a3b8; }
    .audit-reset-btn { margin-top: 8px; }
    .audit-history { display: flex; flex-direction: column; gap: 8px; font-size: 12px; color: #475569; }
    .audit-event { border: 1px solid #e5e7eb; border-radius: 7px; padding: 8px; background: #fff; }
    .audit-event strong { color: #0f172a; }
    .remark-event { border-left: 3px solid #0f766e; }
    .remark-event-meta { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 5px; color: #64748b; font-size: 11px; }
    .remark-event-text { white-space: pre-wrap; color: #0f172a; line-height: 1.45; }
    .internal-remark-composer { margin-bottom: 14px; padding: 12px; border: 1px solid #b8d8c7; border-radius: 7px; background: #f0fdf4; }
    .internal-remark-composer[hidden] { display: none !important; }
    .internal-remark-status { display: inline-block; margin-left: 8px; color: #0f766e; font-size: 11px; font-weight: 700; }
    .internal-remark-status.is-error { color: #b42318; }
    .internal-remark-latest { display: inline-flex; margin-left: 6px; padding: 2px 5px; border-radius: 3px; background: #dcfce7; color: #166534; font-size: 9px; font-weight: 900; text-transform: uppercase; }
    .insight-lead-details-panel { width: min(440px, 96vw); }
    .lead-detail-head-actions { display: flex; gap: 7px; }
    .lead-detail-section { margin-bottom: 18px; }
    .lead-detail-section h3 { margin: 0 0 8px; color: #0f5132; font-size: 12px; letter-spacing: 0; text-transform: uppercase; }
    .lead-detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .lead-detail-item { min-width: 0; padding: 9px; border: 1px solid #e2e8f0; border-radius: 7px; background: #f8fafc; }
    .lead-detail-item span { display: block; color: #64748b; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .lead-detail-item strong { display: block; margin-top: 4px; color: #0f172a; font-size: 12px; line-height: 1.35; overflow-wrap: anywhere; }
    .lead-detail-remark { padding: 10px; border: 1px solid #dbeafe; border-left: 3px solid #0f766e; border-radius: 7px; background: #f8fafc; white-space: pre-wrap; color: #0f172a; font-size: 12px; line-height: 1.45; }
    .lead-remark-history, .lead-timeline { display: grid; gap: 8px; }
    .lead-history-entry { padding: 9px 10px; border: 1px solid #dbe4df; border-radius: 7px; background: #fff; }
    .lead-history-meta { display: flex; justify-content: space-between; gap: 8px; color: #64748b; font-size: 10px; }
    .lead-history-meta strong { color: #0f5132; }
    .lead-history-entry p { margin: 6px 0 0; color: #1e293b; font-size: 12px; line-height: 1.45; white-space: pre-wrap; }
    .lead-timeline-entry { position: relative; padding: 0 0 13px 22px; border-left: 2px solid #bbd7c8; margin-left: 6px; }
    .lead-timeline-entry:last-child { padding-bottom: 0; }
    .lead-timeline-dot { position: absolute; top: 2px; left: -7px; width: 12px; height: 12px; border: 2px solid #fff; border-radius: 50%; background: #16865b; box-shadow: 0 0 0 1px #86b99f; }
    .lead-timeline-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
    .lead-timeline-top strong { color: #0f172a; font-size: 12px; }
    .lead-timeline-top time { flex: 0 0 auto; color: #64748b; font-size: 9px; }
    .lead-timeline-meta { margin-top: 3px; color: #64748b; font-size: 10px; }
    .lead-timeline-stage { display: inline-block; margin-left: 5px; padding: 1px 5px; border: 1px solid #bbd7c8; border-radius: 4px; background: #f0fdf4; color: #166534; font-weight: 800; }
    .lead-timeline-text { margin-top: 5px; color: #334155; font-size: 11px; line-height: 1.4; white-space: pre-wrap; }
    .lead-detail-editor { display: grid; gap: 14px; }
    .lead-edit-group h3 { margin: 0 0 8px; color: #0f5132; font-size: 12px; text-transform: uppercase; }
    .lead-edit-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .lead-edit-field { min-width: 0; }
    .lead-edit-field.is-wide { grid-column: 1 / -1; }
    .lead-edit-field label { display: block; margin-bottom: 4px; color: #64748b; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .lead-edit-field input, .lead-edit-field select, .lead-edit-field textarea { box-sizing: border-box; width: 100%; padding: 8px 9px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #0f172a; font: inherit; font-size: 12px; }
    .lead-edit-field textarea { min-height: 70px; resize: vertical; }
    .lead-edit-field.is-overridden input, .lead-edit-field.is-overridden select, .lead-edit-field.is-overridden textarea { border-color: #f59e0b; background: #fffbeb; }
    .lead-edit-multi { border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; }
    .lead-edit-multi summary { display: flex; min-height: 38px; box-sizing: border-box; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 9px; cursor: pointer; list-style: none; color: #0f172a; font-size: 12px; }
    .lead-edit-multi summary::-webkit-details-marker { display: none; }
    .lead-edit-multi summary span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .lead-edit-multi[open] summary { border-bottom: 1px solid #e2e8f0; }
    .lead-edit-multi-panel { padding: 8px; }
    .lead-edit-project-options { max-height: 180px; margin-top: 7px; overflow-y: auto; border: 1px solid #e2e8f0; }
    .lead-edit-project-option { display: flex !important; align-items: center; gap: 7px; margin: 0 !important; padding: 7px 8px; border-bottom: 1px solid #f1f5f9; color: #334155 !important; font-size: 12px !important; font-weight: 600 !important; text-transform: none !important; cursor: pointer; }
    .lead-edit-project-option:last-child { border-bottom: 0; }
    .lead-edit-project-option input { width: 15px !important; padding: 0 !important; }
    .lead-edit-field.is-overridden .lead-edit-multi { border-color: #f59e0b; background: #fffbeb; }
    .lead-editor-actions { position: sticky; bottom: 0; display: flex; justify-content: flex-end; gap: 8px; padding: 10px 0 2px; background: #fff; }
    .lead-detail-error { color: #b42318; font-size: 11px; }
    .completion-activity { padding-bottom: 16px; border-bottom: 1px solid #e2e8f0; }
    .completion-meta { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 9px; color: #64748b; font-size: 11px; }
    .completion-meta strong { color: #334155; }
    .completion-photos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 7px; margin-top: 9px; }
    .completion-photo { display: block; overflow: hidden; border: 1px solid #dbe4df; border-radius: 6px; background: #f8fafc; color: #0f5132; text-decoration: none; font-size: 10px; font-weight: 800; text-align: center; }
    .completion-photo img { display: block; width: 100%; aspect-ratio: 1.25; object-fit: cover; background: #e2e8f0; }
    .completion-photo span { display: block; padding: 5px; }
    .completion-no-proof { margin-top: 9px; color: #94a3b8; font-size: 11px; }
    .insight-modal { position: fixed; inset: 0; z-index: 1002; background: rgba(15,23,42,.45); display: flex; align-items: center; justify-content: center; padding: 18px; }
    .insight-modal-card { width: min(860px, 96vw); max-height: 90vh; overflow: auto; background: #fff; border-radius: 10px; padding: 18px; box-shadow: 0 24px 80px rgba(15,23,42,.25); }
    .insight-transfer-card { width: min(480px, 96vw); }
    .insight-transfer-field { display: grid; gap: 6px; margin-top: 14px; color: #334155; font-size: 12px; font-weight: 800; }
    .insight-transfer-field select, .insight-transfer-field textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 7px; padding: 9px 10px; background: #fff; color: #0f172a; font: inherit; font-weight: 500; }
    .insight-transfer-field select:focus-visible, .insight-transfer-field textarea:focus-visible { outline: 2px solid #16a34a; outline-offset: 2px; }
    .insight-transfer-status { min-height: 18px; margin: 10px 0 0; color: #b91c1c; font-size: 12px; font-weight: 700; }
    .insight-transfer-actions { justify-content: flex-end; }
    .access-list { display: grid; gap: 8px; }
    .access-row { display: grid; grid-template-columns: 1fr repeat(3, 120px); gap: 8px; align-items: center; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; }
    .access-row strong { color: #0f172a; }
    .access-check { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 800; color: #475569; }
    @media (prefers-reduced-motion: reduce) { .insight-grid-wrap { scroll-behavior: auto; } .insight-btn { transition: none; } }
    @media (max-width: 768px) {
        .insight-shell { height: auto; }
        .insight-toolbar { align-items: flex-start; flex-direction: column; }
        .insight-actions { width: 100%; justify-content: flex-start; padding-bottom: 4px; }
        .insight-filters input, .insight-filters select, .insight-filters .insight-btn { width: 100%; }
        .access-row { grid-template-columns: 1fr; }
    }
</style>

<script>
(() => {
    const columns = @json($columns);
    const accountVisibleColumns = @json($visibleInsightSheetColumns);
    const shell = document.querySelector('.insight-shell');
    document.getElementById('mainContent')?.classList.add('insight-sheet-active');
    const canEdit = shell.dataset.canEdit === '1';
    const canExport = shell.dataset.canExport === '1';
    const canManageAccess = shell.dataset.canManageAccess === '1';
    const canTransfer = shell.dataset.canTransfer === '1';
    const isLeadQualityAuditor = @json($isLeadQualityAuditor);
    const prefKey = `insight-sheet:${shell.dataset.userId || 'guest'}:master:v2`;
    const routes = {
        data: @json(route('admin.insight-sheet.data')),
        audit: @json(route('admin.insight-sheet.audit')),
        remarks: @json(route('admin.insight-sheet.remarks')),
        details: @json(route('admin.insight-sheet.details')),
        transfer: @json(route('admin.insight-sheet.transfer')),
        detailsOverride: @json(route('admin.insight-sheet.details.override')),
        completionDetails: @json(route('admin.insight-sheet.completion-details')),
        access: @json(route('admin.insight-sheet.access')),
        accessUpdate: @json(route('admin.insight-sheet.access.update')),
        layout: @json(route('admin.insight-sheet.layout.update')),
        save: @json(route('admin.insight-sheet.cells.save')),
        bulk: @json(route('admin.insight-sheet.cells.bulk-save')),
        reset: @json(route('admin.insight-sheet.cells.reset')),
        export: @json(route('admin.insight-sheet.export')),
    };
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const requestedPage = Number.parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10);
    let page = Number.isInteger(requestedPage) && requestedPage > 0 ? requestedPage : 1;
    let lastPage = 1;
    let currentTotal = 0;
    const pending = new Map();
    const columnFilters = {};
    const dateRangeFilters = {};
    let serverColumnFilterValues = {};
    let currentLeadDetails = null;
    let currentLeadRowKey = null;
    let serverColumnFilterKeys = {};
    const serverSideFilterColumns = new Set(['crm_status', 'advisor']);
    const preferences = loadPreferences();
    const serverSideColumnFilters = Object.fromEntries(
        Object.entries(preferences.serverSideColumnFilters || {})
            .filter(([column]) => serverSideFilterColumns.has(column))
            .map(([column, values]) => [column, new Set(Array.isArray(values) ? values : [])])
    );
    Object.entries(serverSideColumnFilters).forEach(([column, values]) => {
        if (values.size > 0) columnFilters[column] = new Set(values);
    });
    const lockedColumns = new Set(preferences.lockedColumns || ['serial', 'source']);
    const defaultHiddenColumns = ['stage', 'ad_id_type', 'alternate_number', 'shared_link', 'customer_profiling', 'profession'];
    const hiddenColumns = new Set(Array.isArray(accountVisibleColumns)
        ? columns.map(column => column.key).filter(key => !accountVisibleColumns.includes(key))
        : (preferences.hiddenColumns || []));
    if (preferences.defaultHiddenColumnsVersion !== 6) {
        defaultHiddenColumns.forEach(column => hiddenColumns.add(column));
    }
    // Saved layouts created before these action columns existed must not hide them.
    hiddenColumns.delete('view');
    hiddenColumns.delete('all_remarks');
    if (isLeadQualityAuditor) ['internal_stage', 'internal_remark'].forEach(column => hiddenColumns.delete(column));
    const columnWidths = preferences.columnWidths || {};
    let currentFilterColumn = null;
    let currentFilterSelection = null;
    let selectedCell = null;
    let selectedAuditTarget = null;
    let resizing = null;

    const filters = document.getElementById('insightFilters');
    const startDateFilter = filters.elements.namedItem('start_date');
    const endDateFilter = filters.elements.namedItem('end_date');
    const searchFilterToggle = document.getElementById('insightToggleSearchFilters');
    const grid = document.getElementById('insightGrid');
    const gridWrap = document.querySelector('.insight-grid-wrap');
    const tbody = document.querySelector('#insightGrid tbody');
    const count = document.getElementById('insightCount');
    const saveState = document.getElementById('insightSaveState');
    const pageLabel = document.getElementById('insightPage');
    const previousPage = document.getElementById('insightPrev');
    const nextPage = document.getElementById('insightNext');
    const loading = document.getElementById('insightLoading');
    const loadingText = document.getElementById('insightLoadingText');
    const filterMenu = document.getElementById('insightColumnFilterMenu');
    const filterSearch = document.getElementById('insightColumnFilterSearch');
    const filterSelectAll = document.getElementById('insightColumnFilterSelectAll');
    const filterOptions = document.getElementById('insightColumnFilterOptions');
    const dateRangeBox = document.getElementById('insightDateFilterRange');
    const dateRangeFrom = document.getElementById('insightDateFilterFrom');
    const dateRangeTo = document.getElementById('insightDateFilterTo');
    const dirtyBar = document.getElementById('insightDirtyBar');
    const pendingCount = document.getElementById('insightPendingCount');
    const auditPanel = document.getElementById('insightAuditPanel');
    const densitySelect = document.getElementById('insightDensity');
    const pageSizeSelect = document.getElementById('insightPageSize');
    const columnChooser = document.getElementById('insightColumnChooser');
    const columnChooserBackdrop = document.getElementById('insightColumnChooserBackdrop');
    const columnChooserSearch = document.getElementById('insightColumnChooserSearch');
    const columnChooserList = document.getElementById('insightColumnChooserList');
    const saveColumnsButton = document.getElementById('insightSaveColumns');
    pageLabel.textContent = `Page ${page}`;

    let scrollbarHideTimer = null;
    let queuedHorizontalScroll = 0;
    let horizontalScrollFrame = null;
    const revealGridScrollbar = () => {
        gridWrap.classList.add('scrollbar-active');
        clearTimeout(scrollbarHideTimer);
        scrollbarHideTimer = setTimeout(() => gridWrap.classList.remove('scrollbar-active'), 900);
    };
    gridWrap.addEventListener('scroll', revealGridScrollbar, {passive: true});
    gridWrap.addEventListener('wheel', event => {
        revealGridScrollbar();
        const horizontalDelta = Math.abs(event.deltaX) > Math.abs(event.deltaY)
            ? event.deltaX
            : (event.shiftKey ? event.deltaY : 0);
        if (Math.abs(horizontalDelta) < 1 || gridWrap.scrollWidth <= gridWrap.clientWidth) return;

        event.preventDefault();
        queuedHorizontalScroll += horizontalDelta;
        if (horizontalScrollFrame) return;
        horizontalScrollFrame = requestAnimationFrame(() => {
            gridWrap.scrollLeft += queuedHorizontalScroll;
            queuedHorizontalScroll = 0;
            horizontalScrollFrame = null;
        });
    }, {passive: false});

    if (preferences.density === 'comfortable') {
        shell.classList.add('density-comfortable');
        densitySelect.value = 'comfortable';
    }
    if (['100', '250', '500'].includes(String(preferences.perPage))) {
        pageSizeSelect.value = String(preferences.perPage);
    }

    function loadPreferences() {
        try {
            return JSON.parse(localStorage.getItem(prefKey) || '{}') || {};
        } catch (error) {
            return {};
        }
    }

    function savePreferences() {
        localStorage.setItem(prefKey, JSON.stringify({
            lockedColumns: [...lockedColumns],
            hiddenColumns: [...hiddenColumns],
            defaultHiddenColumnsVersion: 6,
            columnWidths,
            density: densitySelect?.value || 'compact',
            perPage: pageSizeSelect?.value || '100',
            serverSideColumnFilters: Object.fromEntries(
                Object.entries(serverSideColumnFilters).map(([column, values]) => [column, [...values]])
            )
        }));
    }

    function visibleColumnKeys() {
        return columns.map(column => column.key).filter(key => !hiddenColumns.has(key));
    }

    async function saveColumnLayout() {
        const response = await fetch(routes.layout, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify({visible_columns: visibleColumnKeys()}),
        });
        if (!response.ok) throw new Error('Unable to save column layout');
        savePreferences();
    }

    function updateColumnChooserCount() {
        const checked = columnChooserList.querySelectorAll('input[type="checkbox"]:checked').length;
        document.getElementById('insightVisibleColumnCount').textContent = checked;
        saveColumnsButton.disabled = checked === 0;
    }

    function setColumnChooserOpen(open) {
        columnChooser.hidden = !open;
        columnChooserBackdrop.hidden = !open;
        document.getElementById('insightChooseColumns').setAttribute('aria-expanded', open ? 'true' : 'false');
        if (!open) return;
        columnChooserSearch.value = '';
        columnChooserList.querySelectorAll('.column-choice').forEach(label => label.classList.remove('is-filtered-out'));
        columnChooserList.querySelectorAll('input[type="checkbox"]').forEach(input => input.checked = !hiddenColumns.has(input.value));
        updateColumnChooserCount();
        columnChooserSearch.focus();
    }

    function params(extra = {}) {
        const data = new FormData(filters);
        const query = new URLSearchParams();
        for (const [key, value] of data.entries()) {
            if (value !== '') query.set(key, value);
        }
        Object.entries(serverSideColumnFilters).forEach(([column, values]) => {
            if (!(values instanceof Set) || values.size === 0) return;
            const valueKeys = serverColumnFilterKeys[column] || {};
            values.forEach(value => {
                query.append(`column_filters[${column}][]`, valueKeys[value] || value);
            });
        });
        query.set('per_page', pageSizeSelect.value);
        query.set('page', extra.page || page);
        return query;
    }

    function syncPageUrl() {
        const url = new URL(window.location.href);
        page > 1 ? url.searchParams.set('page', String(page)) : url.searchParams.delete('page');
        window.history.replaceState(null, '', url);
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    }

    async function loadRows() {
        count.textContent = 'Loading...';
        loading.hidden = false;
        loading.classList.remove('is-error');
        loading.querySelector('i').className = 'fas fa-circle-notch fa-spin';
        loadingText.textContent = 'Loading data...';
        grid.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(`${routes.data}?${params()}`, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Failed to load Insight Sheet');
            const payload = await response.json();
            lastPage = payload.pagination.last_page || 1;
            page = payload.pagination.current_page || 1;
            if (page > lastPage) {
                page = lastPage;
                syncPageUrl();
                return await loadRows();
            }
            syncPageUrl();
            currentTotal = payload.pagination.total || 0;
            serverColumnFilterValues = payload.filters?.column_values || {};
            serverColumnFilterKeys = payload.filters?.column_value_keys || {};
            renderFilterOptions(payload.filters || {});
            renderRows(payload.rows || []);
            gridWrap.scrollTop = 0;
            applyColumnFilters();
            applyLockedColumns();
            updateCount();
            pageLabel.textContent = `Page ${page} of ${lastPage} | ${currentTotal.toLocaleString()} rows`;
            previousPage.disabled = page <= 1;
            nextPage.disabled = page >= lastPage;
            loading.hidden = true;
        } catch (error) {
            loading.classList.add('is-error');
            loading.querySelector('i').className = 'fas fa-circle-exclamation';
            loadingText.textContent = 'Unable to load data';
            throw error;
        } finally {
            grid.setAttribute('aria-busy', 'false');
        }
    }

    function renderFilterOptions(options) {
        const source = document.getElementById('insightSource');
        const status = document.getElementById('insightStatus');
        if (source.options.length <= 1 && options.sources) {
            Object.entries(options.sources).forEach(([value, label]) => source.insertAdjacentHTML('beforeend', `<option value="${esc(value)}">${esc(label)}</option>`));
        }
        if (status.options.length <= 1 && options.statuses) {
            Object.entries(options.statuses).forEach(([value, label]) => status.insertAdjacentHTML('beforeend', `<option value="${esc(value)}">${esc(label)}</option>`));
        }
        const advisor = document.getElementById('insightAdvisor');
        if (advisor.options.length <= 1 && options.advisors) {
            options.advisors.forEach(user => advisor.insertAdjacentHTML('beforeend', `<option value="${esc(user.id)}">${esc(user.name)}</option>`));
        }
    }

    function renderRows(rows) {
        tbody.innerHTML = rows.map(row => `<tr data-row-key="${esc(row.row_key)}">${columns.map(column => {
            const cell = row.cells[column.key] || {value: '', source_value: '', is_overridden: false};
            if (column.key === 'view') {
                const transferButton = canTransfer ? `<button type="button" class="insight-view-lead insight-transfer-lead" data-transfer-lead="${esc(row.row_key)}" data-transfer-customer="${esc(row.cells.customer?.value || 'Lead')}" data-transfer-number="${esc(row.cells.number?.value || '')}"><i class="fas fa-right-left"></i><span>Transfer</span></button>` : '';
                return `<td data-column="view" class="insight-view-cell"><div class="insight-row-actions"><button type="button" class="insight-view-lead" data-view-lead="${esc(row.row_key)}"><i class="fas fa-eye"></i><span>Details</span></button>${transferButton}</div></td>`;
            }
            if (column.key === 'all_remarks') {
                return `<td data-column="all_remarks" class="insight-view-cell"><button type="button" class="insight-view-lead insight-all-remarks" data-all-remarks="${esc(row.row_key)}"><i class="fas fa-comment-alt"></i><span>All Remarks</span></button></td>`;
            }
            if (column.key === 'internal_remark') {
                return `<td data-column="internal_remark" class="insight-view-cell"><div class="internal-remark-actions"><button type="button" class="insight-view-lead insight-internal-remark" data-internal-remark="${esc(row.row_key)}" title="Add internal remark"><i class="fas fa-comment-medical"></i><span>Remark</span></button><button type="button" class="insight-internal-history" data-internal-remark-history="${esc(row.row_key)}" title="Internal remark history" aria-label="View internal remark history"><i class="fas fa-history"></i></button></div></td>`;
            }
            const isEditable = canEdit && (!isLeadQualityAuditor || column.key === 'internal_stage');
            const cls = cell.is_overridden ? 'overridden' : '';
            const title = cell.is_overridden ? `Edited${cell.edited_by_name ? ' by ' + cell.edited_by_name : ''}` : 'Live CRM';
            if (column.key === 'crm_status' && row.completion_type) {
                return `<td data-column="crm_status"><div class="crm-status-actions"><div class="insight-cell ${cls}" contenteditable="${isEditable ? 'true' : 'false'}" data-original="${esc(cell.value)}" data-source-value="${esc(cell.source_value || '')}" data-overridden="${cell.is_overridden ? '1' : '0'}" data-edited-by="${esc(cell.edited_by_name || cell.edited_by || '')}" data-edited-at="${esc(cell.edited_at || '')}" title="${esc(title)}">${esc(cell.value)}</div><button type="button" class="insight-completion-view" data-completion-details="${esc(row.row_key)}" title="View ${esc(row.completion_type)} completion form" aria-label="View completion details"><i class="fas fa-eye"></i></button></div></td>`;
            }
            return `<td data-column="${esc(column.key)}">
                <div class="insight-cell ${cls}" contenteditable="${isEditable ? 'true' : 'false'}"
                    data-original="${esc(cell.value)}"
                    data-source-value="${esc(cell.source_value || '')}"
                    data-overridden="${cell.is_overridden ? '1' : '0'}"
                    data-edited-by="${esc(cell.edited_by_name || cell.edited_by || '')}"
                    data-edited-at="${esc(cell.edited_at || '')}"
                    title="${esc(title)}">${esc(cell.value)}</div>
            </td>`;
        }).join('')}</tr>`).join('');
        applyHiddenColumns();
        applyColumnWidths();
    }

    function applyLockedColumns() {
        const lockedInOrder = columns.map(column => column.key).filter(key => lockedColumns.has(key));
        document.querySelectorAll('#insightGrid th, #insightGrid td').forEach(cell => {
            cell.classList.remove('locked-column');
            cell.style.left = '';
        });

        let left = 0;
        lockedInOrder.forEach(column => {
            const header = document.querySelector(`#insightGrid th[data-column="${CSS.escape(column)}"]`);
            const width = header?.offsetWidth || 140;
            document.querySelectorAll(`#insightGrid th[data-column="${CSS.escape(column)}"], #insightGrid td[data-column="${CSS.escape(column)}"]`).forEach(cell => {
                cell.classList.add('locked-column');
                cell.style.left = `${left}px`;
            });
            left += width;
        });

        document.querySelectorAll('.insight-column-lock').forEach(button => {
            const locked = lockedColumns.has(button.dataset.columnLock);
            button.classList.toggle('active', locked);
            button.innerHTML = locked ? '<i class="fas fa-lock"></i>' : '<i class="fas fa-lock-open"></i>';
        });
        savePreferences();
    }

    function applyHiddenColumns() {
        columns.forEach(column => {
            const hidden = hiddenColumns.has(column.key);
            document.querySelectorAll(`#insightGrid th[data-column="${CSS.escape(column.key)}"], #insightGrid td[data-column="${CSS.escape(column.key)}"]`).forEach(cell => {
                cell.classList.toggle('hidden-column', hidden);
            });
        });
    }

    function applyColumnWidths() {
        Object.entries(columnWidths).forEach(([column, width]) => {
            document.querySelectorAll(`#insightGrid th[data-column="${CSS.escape(column)}"], #insightGrid td[data-column="${CSS.escape(column)}"]`).forEach(cell => {
                cell.style.minWidth = `${width}px`;
                cell.style.maxWidth = `${width}px`;
                cell.style.width = `${width}px`;
            });
        });
    }

    function addHeaderResizers() {
        document.querySelectorAll('#insightGrid th').forEach(th => {
            if (th.querySelector('.insight-column-resizer')) return;
            th.insertAdjacentHTML('beforeend', '<span class="insight-column-resizer"></span>');
        });
    }

    function updateCount() {
        const hidden = tbody.querySelectorAll('tr.filtered-out').length;
        const visible = tbody.querySelectorAll('tr').length - hidden;
        const active = Object.values(columnFilters).filter(values => values instanceof Set && values.size).length
            + Object.values(dateRangeFilters).filter(range => range && (range.from || range.to)).length;
        count.textContent = active ? `${visible} visible on this page (${currentTotal} total)` : `${currentTotal} row(s)`;
    }

    function applyColumnFilters() {
        const activeFilters = Object.entries(columnFilters).filter(([, values]) => values instanceof Set && values.size);
        const activeDateRanges = Object.entries(dateRangeFilters).filter(([, range]) => range && (range.from || range.to));
        tbody.querySelectorAll('tr').forEach(row => {
            const valueMismatch = activeFilters.some(([column, values]) => {
                const cell = row.querySelector(`td[data-column="${CSS.escape(column)}"] .insight-cell`);
                return !cell || !values.has(cell.textContent.trim());
            });
            const dateMismatch = activeDateRanges.some(([column, range]) => {
                const cell = row.querySelector(`td[data-column="${CSS.escape(column)}"] .insight-cell`);
                const value = cell?.textContent.trim() || '';
                if (!value) return true;
                if (range.from && value < range.from) return true;
                if (range.to && value > range.to) return true;
                return false;
            });
            const hide = valueMismatch || dateMismatch;
            row.classList.toggle('filtered-out', hide);
        });
        document.querySelectorAll('.insight-column-filter').forEach(button => {
            const column = button.dataset.columnFilter;
            const range = dateRangeFilters[column];
            button.classList.toggle('active', (columnFilters[column] instanceof Set && columnFilters[column].size > 0) || Boolean(range && (range.from || range.to)));
        });
    }

    function isDateColumn(column) {
        return column === 'date' || column.endsWith('_date') || column.includes('date');
    }

    function columnValues(column) {
        const configuredValues = Array.isArray(serverColumnFilterValues[column]) ? serverColumnFilterValues[column] : [];
        const pageValues = [...tbody.querySelectorAll(`td[data-column="${CSS.escape(column)}"] .insight-cell`)]
            .map(cell => cell.textContent.trim());

        return [...new Set([...configuredValues, ...pageValues])]
            .filter(value => value !== null && value !== undefined)
            .sort((a, b) => a.localeCompare(b, undefined, {numeric: true, sensitivity: 'base'}));
    }

    function renderColumnFilterOptions(column, search = '') {
        const values = columnValues(column);
        const selected = currentFilterSelection || new Set(
            serverSideColumnFilters[column] instanceof Set
                ? serverSideColumnFilters[column]
                : (columnFilters[column] instanceof Set ? columnFilters[column] : values)
        );
        const needle = search.toLowerCase();
        const visibleValues = values.filter(value => value.toLowerCase().includes(needle));
        filterOptions.innerHTML = visibleValues.map(value => `
            <label class="filter-check" data-filter-option>
                <input type="checkbox" value="${esc(value)}" ${selected.has(value) ? 'checked' : ''}>
                <span>${esc(value || '(Blank)')}</span>
            </label>
        `).join('') || '<div class="filter-check">No values</div>';
        filterSelectAll.checked = visibleValues.length > 0 && visibleValues.every(value => selected.has(value));
        filterSelectAll.indeterminate = visibleValues.some(value => selected.has(value)) && !filterSelectAll.checked;
    }

    function openColumnFilter(button) {
        auditPanel.hidden = true;
        currentFilterColumn = button.dataset.columnFilter;
        const allValues = columnValues(currentFilterColumn);
        currentFilterSelection = new Set(
            serverSideColumnFilters[currentFilterColumn] instanceof Set
                ? serverSideColumnFilters[currentFilterColumn]
                : (columnFilters[currentFilterColumn] instanceof Set ? columnFilters[currentFilterColumn] : allValues)
        );
        const label = button.closest('th')?.querySelector('span')?.textContent || 'Column';
        filterMenu.querySelector('.filter-menu-title').textContent = `Filter ${label}`;
        filterSearch.value = '';
        dateRangeBox.hidden = !isDateColumn(currentFilterColumn);
        const range = currentFilterColumn === 'date'
            ? (dateRangeFilters.date || {from: startDateFilter.value, to: endDateFilter.value})
            : (dateRangeFilters[currentFilterColumn] || {});
        dateRangeFrom.value = range.from || '';
        dateRangeTo.value = range.to || '';
        renderColumnFilterOptions(currentFilterColumn);
        const rect = button.getBoundingClientRect();
        filterMenu.hidden = false;
        filterMenu.style.left = `${Math.max(12, Math.min(rect.left, window.innerWidth - filterMenu.offsetWidth - 12))}px`;
        filterMenu.style.top = `${Math.max(12, Math.min(rect.bottom + 6, window.innerHeight - filterMenu.offsetHeight - 12))}px`;
        filterSearch.focus();
    }

    function closeColumnFilter() {
        filterMenu.hidden = true;
        currentFilterColumn = null;
        currentFilterSelection = null;
    }

    function sortRows(column, direction) {
        const rows = [...tbody.querySelectorAll('tr')];
        rows.sort((a, b) => {
            const av = a.querySelector(`td[data-column="${CSS.escape(column)}"] .insight-cell`)?.textContent.trim() || '';
            const bv = b.querySelector(`td[data-column="${CSS.escape(column)}"] .insight-cell`)?.textContent.trim() || '';
            return direction === 'desc'
                ? bv.localeCompare(av, undefined, {numeric: true, sensitivity: 'base'})
                : av.localeCompare(bv, undefined, {numeric: true, sensitivity: 'base'});
        });
        rows.forEach(row => tbody.appendChild(row));
    }

    function autoFitColumn(column) {
        const headerText = document.querySelector(`#insightGrid th[data-column="${CSS.escape(column)}"] span`)?.textContent || '';
        const values = [...tbody.querySelectorAll(`td[data-column="${CSS.escape(column)}"] .insight-cell`)].map(cell => cell.textContent.trim());
        const longest = [headerText, ...values].reduce((best, value) => value.length > best.length ? value : best, '');
        columnWidths[column] = Math.max(80, Math.min(360, longest.length * 8 + 42));
        applyColumnWidths();
        applyLockedColumns();
        savePreferences();
    }

    function setSelectedCell(cell) {
        document.querySelectorAll('.insight-cell.selected').forEach(item => item.classList.remove('selected'));
        selectedCell = cell;
        if (selectedCell) selectedCell.classList.add('selected');
    }

    function moveSelection(dx, dy) {
        if (!selectedCell) return;
        const td = selectedCell.closest('td');
        const tr = selectedCell.closest('tr');
        const visibleColumns = columns.map(column => column.key).filter(key => !hiddenColumns.has(key));
        const rows = [...tbody.querySelectorAll('tr:not(.filtered-out)')];
        const colIndex = visibleColumns.indexOf(td.dataset.column);
        const rowIndex = rows.indexOf(tr);
        const nextColumn = visibleColumns[Math.max(0, Math.min(visibleColumns.length - 1, colIndex + dx))];
        const nextRow = rows[Math.max(0, Math.min(rows.length - 1, rowIndex + dy))];
        const next = nextRow?.querySelector(`td[data-column="${CSS.escape(nextColumn)}"] .insight-cell`);
        if (next) {
            setSelectedCell(next);
            next.focus();
        }
    }

    async function openAuditForCell(cell) {
        const td = cell.closest('td');
        const tr = cell.closest('tr');
        selectedAuditTarget = {rowKey: tr.dataset.rowKey, columnKey: td.dataset.column};
        document.getElementById('auditPanelTitle').textContent = 'Cell Audit';
        document.getElementById('internalRemarkComposer')?.setAttribute('hidden', '');
        document.querySelectorAll('.audit-cell-context').forEach(section => section.hidden = false);
        document.getElementById('auditHistoryLabel').textContent = 'History';
        document.getElementById('auditCellTitle').textContent = `${td.dataset.column} · ${tr.dataset.rowKey}`;
        document.getElementById('auditCurrentValue').textContent = cell.textContent.trim();
        document.getElementById('auditSourceValue').textContent = cell.dataset.sourceValue || '';
        document.getElementById('auditOverrideValue').textContent = 'Loading...';
        document.getElementById('auditHistory').textContent = 'Loading...';
        document.getElementById('auditResetCell')?.toggleAttribute('hidden', cell.dataset.overridden !== '1');
        auditPanel.hidden = false;

        const query = new URLSearchParams({sheet_key: 'master', row_key: tr.dataset.rowKey, column_key: td.dataset.column});
        const response = await fetch(`${routes.audit}?${query}`, {headers: {'Accept': 'application/json'}});
        if (!response.ok) {
            document.getElementById('auditHistory').textContent = 'Failed to load audit';
            return;
        }
        const payload = await response.json();
        document.getElementById('auditSourceValue').textContent = payload.source_value || '';
        document.getElementById('auditOverrideValue').textContent = payload.override
            ? `${payload.override.value || ''}\n${payload.override.edited_by || ''} ${payload.override.edited_at || ''}`.trim()
            : 'No override';
        document.getElementById('auditOverrideValue').classList.toggle('muted', !payload.override);
        const history = payload.history || [];
        document.getElementById('auditHistory').innerHTML = history.length
            ? history.map(item => `<div class="audit-event"><strong>${esc(item.edited_by)}</strong> · ${esc(item.edited_at || '')}<br>Old: ${esc(item.old_value)}<br>New: ${esc(item.new_value)}</div>`).join('')
            : 'No history';
    }

    async function openRemarkHistory(cell) {
        const tr = cell.closest('tr');
        const customer = tr.querySelector('td[data-column="customer"] .insight-cell')?.textContent.trim() || 'Lead';
        selectedAuditTarget = null;
        document.getElementById('auditPanelTitle').textContent = 'All Remarks';
        document.getElementById('internalRemarkComposer')?.setAttribute('hidden', '');
        document.querySelectorAll('.audit-cell-context').forEach(section => section.hidden = true);
        document.getElementById('auditCellTitle').textContent = `${customer} · all remarks`;
        document.getElementById('auditHistoryLabel').textContent = 'Remark History';
        document.getElementById('auditHistory').textContent = 'Loading remarks...';
        document.getElementById('auditResetCell')?.setAttribute('hidden', '');
        auditPanel.hidden = false;

        const response = await fetch(`${routes.remarks}?${new URLSearchParams({row_key: tr.dataset.rowKey})}`, {headers: {'Accept': 'application/json'}});
        if (!response.ok) {
            document.getElementById('auditHistory').textContent = 'Remarks could not be loaded.';
            return;
        }
        const history = (await response.json()).history || [];
        document.getElementById('auditHistory').innerHTML = history.length
            ? history.map(item => `<div class="audit-event remark-event"><div class="remark-event-meta"><strong>${esc(item.actor)}</strong><span>${esc(item.recorded_at || '')}</span></div><div class="remark-event-text">${esc(item.remark)}</div><div class="remark-event-meta"><span>${esc(item.source)}</span></div></div>`).join('')
            : 'No saved remarks for this lead.';
    }

    function formatAuditDate(value) {
        if (!value) return '';
        const parsed = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) return String(value);
        return parsed.toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    async function openInternalRemarks(rowKey, saved = false, historyOnly = false) {
        selectedAuditTarget = {rowKey, columnKey: 'internal_remark'};
        const row = tbody.querySelector(`tr[data-row-key="${CSS.escape(rowKey)}"]`);
        const customer = row?.querySelector('td[data-column="customer"] .insight-cell')?.textContent.trim() || 'Lead';
        const status = document.getElementById('internalRemarkStatus');
        document.getElementById('auditPanelTitle').textContent = historyOnly ? 'Internal Remark History' : 'Internal Remarks';
        document.querySelectorAll('.audit-cell-context').forEach(section => section.hidden = true);
        document.getElementById('auditCellTitle').textContent = `${customer} · ${rowKey}`;
        document.getElementById('auditHistoryLabel').textContent = 'Internal Remark Timeline';
        document.getElementById('auditHistory').textContent = 'Loading internal remarks...';
        document.getElementById('internalRemarkComposer')?.toggleAttribute('hidden', historyOnly);
        document.getElementById('internalRemarkInput').value = '';
        status.textContent = saved ? 'Remark saved' : '';
        status.classList.remove('is-error');
        auditPanel.hidden = false;
        const response = await fetch(`${routes.audit}?${new URLSearchParams({sheet_key: 'master', row_key: rowKey, column_key: 'internal_remark'})}`, {headers: {'Accept': 'application/json'}});
        if (!response.ok) {
            document.getElementById('auditHistory').textContent = 'Internal remarks could not be loaded.';
            return;
        }
        const history = ((await response.json()).history || []).filter(item => item.new_value);
        document.getElementById('auditHistory').innerHTML = history.length
            ? history.map((item, index) => `<div class="audit-event remark-event"><div class="remark-event-meta"><strong>${esc(item.edited_by)}${index === 0 ? '<span class="internal-remark-latest">Latest</span>' : ''}</strong><span>${esc(formatAuditDate(item.edited_at))}</span></div><div class="remark-event-text">${esc(item.new_value)}</div></div>`).join('')
            : 'No internal remarks added.';
    }

    function detailGrid(items) {
        const entries = Object.entries(items || {});
        if (!entries.length) return '<div class="audit-value muted">No details saved.</div>';
        return `<div class="lead-detail-grid">${entries.map(([label, value]) => `<div class="lead-detail-item"><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`).join('')}</div>`;
    }

    function renderRemarkHistory(items) {
        if (!items?.length) return '<div class="audit-value muted">No remark history.</div>';
        return `<div class="lead-remark-history">${items.map(item => `<article class="lead-history-entry"><div class="lead-history-meta"><strong>${esc(item.actor || 'Unknown user')}</strong><span>${esc(formatAuditDate(item.recorded_at))}</span></div><p>${esc(item.remark)}</p></article>`).join('')}</div>`;
    }

    function renderLeadTimeline(items) {
        if (!items?.length) return '<div class="audit-value muted">No lead activity found.</div>';
        return `<div class="lead-timeline">${items.map(item => `<article class="lead-timeline-entry"><span class="lead-timeline-dot"></span><div class="lead-timeline-top"><strong>${esc(item.title)}</strong><time>${esc(formatAuditDate(item.recorded_at))}</time></div><div class="lead-timeline-meta">${esc(item.actor || 'System')}${item.stage ? `<span class="lead-timeline-stage">${esc(item.stage)}</span>` : ''}</div>${item.description ? `<div class="lead-timeline-text">${esc(item.description)}</div>` : ''}${item.remark ? `<div class="lead-timeline-text"><strong>Remark:</strong> ${esc(item.remark)}</div>` : ''}</article>`).join('')}</div>`;
    }

    function renderLeadDetails(lead) {
        document.getElementById('leadDetailsTitle').textContent = lead.customer || 'Lead Details';
        document.getElementById('leadDetailsSubtitle').textContent = `${lead.status || 'No status'} · ${lead.advisor || 'Unassigned'}`;
        document.getElementById('leadDetailsEdit').hidden = !lead.can_override;
        document.getElementById('leadDetailsContent').innerHTML = `
            <section class="lead-detail-section"><h3>Contact</h3>${detailGrid(lead.contact || {'Phone': lead.phone, 'Email': lead.email, 'Source': lead.source})}</section>
            <section class="lead-detail-section"><h3>Customer Profiling</h3>${detailGrid(lead.profile)}</section>
            <section class="lead-detail-section"><h3>Requirements</h3>${detailGrid(lead.requirements)}</section>
            ${Object.keys(lead.additional || {}).length ? `<section class="lead-detail-section"><h3>Additional Form Details</h3>${detailGrid(lead.additional)}</section>` : ''}
            ${Object.keys(lead.sales_update || {}).length ? `<section class="lead-detail-section"><h3>Latest Sales Update</h3>${detailGrid(lead.sales_update)}</section>` : ''}
            <section class="lead-detail-section"><h3>Latest Remark</h3><div class="lead-detail-remark">${esc(lead.latest_remark || 'No remark')}</div></section>
            <section class="lead-detail-section"><h3>Remark History</h3>${renderRemarkHistory(lead.remark_history || [])}</section>
            <section class="lead-detail-section"><h3>Complete Lead Journey</h3>${renderLeadTimeline(lead.timeline || [])}</section>`;
    }

    function openLeadDetailsEditor() {
        if (!currentLeadDetails?.can_override) return;
        const groups = {};
        (currentLeadDetails.editable_fields || []).forEach(field => (groups[field.group] ||= []).push(field));
        const control = field => {
            const options = currentLeadDetails.editor_options?.[field.key];
            if (Array.isArray(options)) {
                const multiple = field.key === 'project';
                const selected = multiple
                    ? String(field.value || '').split(',').map(value => value.trim()).filter(Boolean)
                    : [String(field.value || '')];
                if (multiple) {
                    const summary = selected.length ? selected.join(', ') : 'Select projects';
                    return `<details class="lead-edit-multi"><summary><span data-project-summary>${esc(summary)}</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary><div class="lead-edit-multi-panel"><input type="search" data-project-search placeholder="Search projects"><div class="lead-edit-project-options">${options.map(option => `<label class="lead-edit-project-option" data-project-option="${esc(String(option).toLowerCase())}"><input type="checkbox" name="project" value="${esc(option)}" ${selected.includes(String(option)) ? 'checked' : ''}><span>${esc(option)}</span></label>`).join('')}</div></div></details>`;
                }
                return `<select id="lead-edit-${esc(field.key)}" name="${esc(field.key)}" ${multiple ? 'multiple' : ''}><option value="" ${selected.length ? '' : 'selected'}>${multiple ? 'Select projects' : 'Select value'}</option>${options.map(option => `<option value="${esc(option)}" ${selected.includes(String(option)) ? 'selected' : ''}>${esc(String(option).replaceAll('_', ' '))}</option>`).join('')}</select>`;
            }
            const wide = ['address', 'other_requirements', 'manager_remark'].includes(field.key);
            return wide
                ? `<textarea id="lead-edit-${esc(field.key)}" name="${esc(field.key)}">${esc(field.value || '')}</textarea>`
                : `<input id="lead-edit-${esc(field.key)}" name="${esc(field.key)}" value="${esc(field.value || '')}">`;
        };
        document.getElementById('leadDetailsEdit').hidden = true;
        document.getElementById('leadDetailsContent').innerHTML = `<form id="leadDetailsEditor" class="lead-detail-editor">${Object.entries(groups).map(([group, fields]) => `<section class="lead-edit-group"><h3>${esc(group)}</h3><div class="lead-edit-grid">${fields.map(field => { const wide = ['address', 'other_requirements', 'manager_remark', 'project'].includes(field.key); return `<div class="lead-edit-field ${wide ? 'is-wide' : ''} ${field.is_overridden ? 'is-overridden' : ''}"><label for="lead-edit-${esc(field.key)}">${esc(field.label)}</label>${control(field)}</div>`; }).join('')}</div></section>`).join('')}<div id="leadDetailsEditError" class="lead-detail-error" role="status"></div><div class="lead-editor-actions"><button type="button" id="leadDetailsEditCancel" class="insight-btn secondary">Cancel</button><button type="submit" class="insight-btn primary"><i class="fas fa-save"></i> Save Details</button></div></form>`;
        syncAuditorTypeOptions();
    }

    function syncAuditorTypeOptions() {
        const category = document.getElementById('lead-edit-category')?.value || '';
        const type = document.getElementById('lead-edit-type');
        if (!type || !currentLeadDetails?.editor_type_options) return;
        const selected = type.value;
        const options = currentLeadDetails.editor_type_options[category] || currentLeadDetails.editor_options?.type || [];
        type.innerHTML = `<option value="">Select value</option>${options.map(option => `<option value="${esc(option)}" ${String(option) === selected ? 'selected' : ''}>${esc(option)}</option>`).join('')}`;
    }

    async function saveLeadDetailsOverride(form) {
        const values = Object.fromEntries(new FormData(form).entries());
        const projects = [...form.querySelectorAll('input[name="project"]:checked')].map(option => option.value).filter(Boolean);
        if (form.querySelector('input[name="project"]')) values.project = projects.join(', ');
        const saveButton = form.querySelector('button[type="submit"]');
        const error = document.getElementById('leadDetailsEditError');
        saveButton.disabled = true;
        error.textContent = '';
        try {
            const response = await fetch(routes.detailsOverride, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify({row_key: currentLeadRowKey, values})});
            if (!response.ok) throw new Error('Details save nahi ho payi.');
            currentLeadDetails = await response.json();
            renderLeadDetails(currentLeadDetails);
        } catch (exception) {
            error.textContent = exception.message;
            saveButton.disabled = false;
        }
    }

    async function openLeadDetails(rowKey) {
        const panel = document.getElementById('insightLeadDetailsPanel');
        const title = document.getElementById('leadDetailsTitle');
        const subtitle = document.getElementById('leadDetailsSubtitle');
        const content = document.getElementById('leadDetailsContent');
        title.textContent = 'Lead Details';
        subtitle.textContent = 'Loading...';
        content.textContent = 'Loading lead details...';
        document.getElementById('leadDetailsEdit').hidden = true;
        panel.hidden = false;

        const response = await fetch(`${routes.details}?${new URLSearchParams({row_key: rowKey})}`, {headers: {'Accept': 'application/json'}});
        if (!response.ok) {
            subtitle.textContent = 'Unable to load';
            content.textContent = 'Lead details could not be loaded.';
            return;
        }
        const lead = await response.json();
        currentLeadRowKey = rowKey;
        currentLeadDetails = lead;
        renderLeadDetails(lead);
    }

    async function openCompletionDetails(rowKey) {
        const panel = document.getElementById('insightLeadDetailsPanel');
        const title = document.getElementById('leadDetailsTitle');
        const subtitle = document.getElementById('leadDetailsSubtitle');
        const content = document.getElementById('leadDetailsContent');
        title.textContent = 'Completion Details';
        subtitle.textContent = 'Loading submitted form...';
        content.textContent = 'Loading completion details...';
        panel.hidden = false;

        const response = await fetch(`${routes.completionDetails}?${new URLSearchParams({row_key: rowKey})}`, {headers: {'Accept': 'application/json'}});
        if (!response.ok) {
            subtitle.textContent = 'Unable to load';
            content.textContent = 'Completion form details could not be loaded.';
            return;
        }
        const payload = await response.json();
        const activities = payload.activities || [];
        title.textContent = payload.customer || 'Completion Details';
        subtitle.textContent = `${activities.length} completed ${activities.length === 1 ? 'activity' : 'activities'}`;
        content.innerHTML = activities.length ? activities.map(activity => {
            const photos = (activity.proof_photos || []).map((photo, index) => `<a class="completion-photo" href="${esc(photo)}" target="_blank" rel="noopener"><img src="${esc(photo)}" alt="Proof photo ${index + 1}" loading="lazy"><span>Proof ${index + 1}</span></a>`).join('');
            return `<section class="lead-detail-section completion-activity"><h3>${esc(activity.title)}</h3><div class="completion-meta"><strong>${esc(activity.completed_by || 'Sales user')}</strong><span>${esc(formatAuditDate(activity.completed_at))}</span></div>${detailGrid(activity.fields)}${photos ? `<div class="completion-photos">${photos}</div>` : '<div class="completion-no-proof">No proof photo saved.</div>'}</section>`;
        }).join('') : '<div class="audit-value muted">No completed meeting or visit form found.</div>';
    }

    async function openAccessModal() {
        if (!canManageAccess) return;
        const modal = document.getElementById('insightAccessModal');
        const list = document.getElementById('insightAccessList');
        modal.hidden = false;
        list.textContent = 'Loading...';
        let payload = null;
        try {
            const response = await fetch(routes.access, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Failed to load access');
            payload = await response.json();
        } catch (error) {
            list.innerHTML = '<div class="audit-value">Access list load nahi ho payi. Page refresh karke dobara try karo.</div>';
            return;
        }
        list.innerHTML = (payload.roles || []).map(role => {
            const rolePermissions = Array.isArray(role.permissions) ? role.permissions : [];
            const has = permission => rolePermissions.includes(permission);
            return `<div class="access-row" data-role-id="${role.id}">
                <div><strong>${esc(role.name)}</strong><div>${esc(role.slug)}</div></div>
                <label class="access-check"><input type="checkbox" value="insight_sheet.view" ${has('insight_sheet.view') ? 'checked' : ''}> View</label>
                <label class="access-check"><input type="checkbox" value="insight_sheet.edit" ${has('insight_sheet.edit') ? 'checked' : ''}> Edit</label>
                <label class="access-check"><input type="checkbox" value="insight_sheet.export" ${has('insight_sheet.export') ? 'checked' : ''}> Export</label>
            </div>`;
        }).join('') || 'No roles found';
    }

    async function saveAccessModal() {
        const rows = [...document.querySelectorAll('#insightAccessList .access-row')].map(row => ({
            role_id: row.dataset.roleId,
            permissions: [...row.querySelectorAll('input:checked')].map(input => input.value)
        }));
        const response = await fetch(routes.accessUpdate, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify({roles: rows})
        });
        if (!response.ok) {
            document.getElementById('insightAccessList').insertAdjacentHTML('afterbegin', '<div class="audit-value">Failed to save access</div>');
            return;
        }
        document.getElementById('insightAccessModal').hidden = true;
    }

    function csvEscape(value) {
        const text = String(value ?? '');
        return /[",\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
    }

    function exportVisibleRows() {
        const includeMeta = document.getElementById('insightExportMeta')?.checked;
        const visibleColumns = columns.filter(column => !hiddenColumns.has(column.key));
        const header = visibleColumns.map(column => column.label);
        if (includeMeta) {
            visibleColumns.forEach(column => {
                header.push(`${column.label} CRM Source Value`, `${column.label} Edited By`, `${column.label} Edited At`);
            });
        }
        const lines = [header.map(csvEscape).join(',')];
        [...tbody.querySelectorAll('tr:not(.filtered-out)')].forEach(row => {
            const values = visibleColumns.map(column => row.querySelector(`td[data-column="${CSS.escape(column.key)}"] .insight-cell`)?.textContent.trim() || '');
            if (includeMeta) {
                visibleColumns.forEach(column => {
                    const cell = row.querySelector(`td[data-column="${CSS.escape(column.key)}"] .insight-cell`);
                    values.push(cell?.dataset.sourceValue || '', cell?.dataset.editedBy || '', cell?.dataset.editedAt || '');
                });
            }
            lines.push(values.map(csvEscape).join(','));
        });
        const blob = new Blob(['\uFEFF' + lines.join('\r\n')], {type: 'text/csv;charset=utf-8'});
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `insight-sheet-filtered-${new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)}.csv`;
        document.body.appendChild(link);
        link.click();
        URL.revokeObjectURL(link.href);
        link.remove();
    }

    async function saveCell(cellEl) {
        if (!canEdit) return;
        const td = cellEl.closest('td');
        const tr = cellEl.closest('tr');
        const value = cellEl.textContent.trim();
        const original = cellEl.dataset.original ?? '';
        if (value === original) return;
        const key = `${tr.dataset.rowKey}:${td.dataset.column}`;
        pending.set(key, {row_key: tr.dataset.rowKey, column_key: td.dataset.column, value});
        cellEl.classList.add('pending');
        updateDirtyBar();
        saveState.textContent = 'Saving...';

        try {
            const response = await fetch(routes.save, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
                body: JSON.stringify({sheet_key: 'master', row_key: tr.dataset.rowKey, column_key: td.dataset.column, value})
            });
            if (!response.ok) throw new Error('Save failed');
            cellEl.dataset.original = value;
            cellEl.dataset.overridden = '1';
            cellEl.classList.remove('pending', 'failed');
            cellEl.classList.add('overridden');
            cellEl.title = 'Edited';
            pending.delete(key);
            updateDirtyBar();
            saveState.textContent = pending.size ? `${pending.size} pending` : 'Saved';
        } catch (error) {
            cellEl.classList.remove('pending');
            cellEl.classList.add('failed');
            updateDirtyBar();
            saveState.textContent = 'Failed';
        }
    }

    async function resetCell(button) {
        const td = button.closest('td');
        const tr = button.closest('tr');
        const cell = td.querySelector('.insight-cell');
        saveState.textContent = 'Resetting...';
        const response = await fetch(routes.reset, {
            method: 'DELETE',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify({sheet_key: 'master', row_key: tr.dataset.rowKey, column_key: td.dataset.column})
        });
        if (!response.ok) {
            saveState.textContent = 'Reset failed';
            return;
        }
        const payload = await response.json();
        cell.textContent = payload.source_value || '';
        cell.dataset.original = payload.source_value || '';
        cell.dataset.sourceValue = payload.source_value || '';
        cell.dataset.overridden = '0';
        cell.classList.remove('overridden', 'pending', 'failed');
        cell.title = 'Live CRM';
        saveState.textContent = 'Reset';
        updateDirtyBar();
        if (selectedAuditTarget && selectedAuditTarget.rowKey === tr.dataset.rowKey && selectedAuditTarget.columnKey === td.dataset.column) {
            openAuditForCell(cell);
        }
    }

    function updateDirtyBar() {
        const failed = tbody.querySelectorAll('.insight-cell.failed').length;
        pendingCount.textContent = pending.size + failed;
        dirtyBar.hidden = pending.size === 0 && failed === 0;
    }

    function setSearchFiltersOpen(open) {
        filters.hidden = !open;
        searchFilterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) filters.querySelector('select, input')?.focus();
    }

    function setTransferModalOpen(open, button = null) {
        const modal = document.getElementById('insightTransferModal');
        const form = document.getElementById('insightTransferForm');
        if (!modal || !form) return;
        modal.hidden = !open;
        if (!open) {
            form.reset();
            document.getElementById('insightTransferStatus').textContent = '';
            return;
        }
        document.getElementById('insightTransferRowKey').value = button?.dataset.transferLead || '';
        document.getElementById('insightTransferLead').textContent = `${button?.dataset.transferCustomer || 'Lead'}${button?.dataset.transferNumber ? ' • ' + button.dataset.transferNumber : ''}`;
        document.getElementById('insightTransferOwner').focus();
    }

    searchFilterToggle.addEventListener('click', () => setSearchFiltersOpen(filters.hidden));
    filters.addEventListener('submit', event => {
        event.preventDefault();
        if (startDateFilter.value && endDateFilter.value) {
            dateRangeFilters.date = {from: startDateFilter.value, to: endDateFilter.value};
        } else {
            delete dateRangeFilters.date;
        }
        page = 1;
        setSearchFiltersOpen(false);
        loadRows();
    });
    document.getElementById('insightClear').addEventListener('click', () => {
        filters.reset();
        Object.keys(columnFilters).forEach(key => delete columnFilters[key]);
        Object.keys(dateRangeFilters).forEach(key => delete dateRangeFilters[key]);
        Object.keys(serverSideColumnFilters).forEach(key => delete serverSideColumnFilters[key]);
        setSearchFiltersOpen(false);
        savePreferences();
        page = 1;
        loadRows();
    });
    previousPage.addEventListener('click', () => {
        if (page > 1) { page--; loadRows(); }
    });
    nextPage.addEventListener('click', () => {
        if (page < lastPage) { page++; loadRows(); }
    });
    document.querySelectorAll('[data-export]').forEach(button => {
        button.addEventListener('click', () => {
            if (button.dataset.export === 'filtered') {
                exportVisibleRows();
                return;
            }
            const query = params();
            query.set('full', button.dataset.export === 'full' ? '1' : '0');
            if (document.getElementById('insightExportMeta')?.checked) query.set('include_meta', '1');
            window.location.href = `${routes.export}?${query}`;
        });
    });
    document.getElementById('insightSaveAll')?.addEventListener('click', () => {
        document.querySelectorAll('.insight-cell[contenteditable="true"]').forEach(saveCell);
    });
    document.getElementById('insightToggleColumnFilters').addEventListener('click', () => {
        grid.classList.toggle('filters-on');
    });
    document.querySelector('#insightGrid thead').addEventListener('click', event => {
        const menuButton = event.target.closest('.insight-column-menu-btn');
        if (menuButton) {
            event.preventDefault();
            event.stopPropagation();
            openColumnFilter({dataset: {columnFilter: menuButton.dataset.columnMenu}, closest: selector => menuButton.closest(selector), getBoundingClientRect: () => menuButton.getBoundingClientRect()});
            return;
        }
        const lockButton = event.target.closest('.insight-column-lock');
        if (lockButton) {
            event.preventDefault();
            event.stopPropagation();
            const column = lockButton.dataset.columnLock;
            if (lockedColumns.has(column)) {
                lockedColumns.delete(column);
            } else {
                lockedColumns.add(column);
            }
            applyLockedColumns();
            savePreferences();
            return;
        }
        const button = event.target.closest('.insight-column-filter');
        if (button) {
            event.preventDefault();
            event.stopPropagation();
            openColumnFilter(button);
        }
    });
    document.querySelector('#insightGrid thead').addEventListener('mousedown', event => {
        if (event.target.closest('.insight-column-filter, .insight-column-lock, .insight-column-menu-btn')) return;
        const handle = event.target.closest('.insight-column-resizer');
        if (!handle) return;
        const th = handle.closest('th');
        resizing = {column: th.dataset.column, startX: event.clientX, startWidth: th.offsetWidth};
        document.body.classList.add('insight-column-resizing');
        event.preventDefault();
    });
    document.addEventListener('mousemove', event => {
        if (!resizing) return;
        columnWidths[resizing.column] = Math.max(70, resizing.startWidth + event.clientX - resizing.startX);
        applyColumnWidths();
        applyLockedColumns();
    });
    document.addEventListener('mouseup', () => {
        if (resizing) savePreferences();
        resizing = null;
        document.body.classList.remove('insight-column-resizing');
    });
    window.addEventListener('resize', applyLockedColumns);
    document.querySelectorAll('[data-sort]').forEach(button => {
        button.addEventListener('click', () => {
            if (!currentFilterColumn) return;
            sortRows(currentFilterColumn, button.dataset.sort);
            closeColumnFilter();
        });
    });
    document.getElementById('insightFreezeFromMenu').addEventListener('click', () => {
        if (!currentFilterColumn) return;
        lockedColumns.has(currentFilterColumn) ? lockedColumns.delete(currentFilterColumn) : lockedColumns.add(currentFilterColumn);
        applyLockedColumns();
        closeColumnFilter();
    });
    document.getElementById('insightHideColumn').addEventListener('click', async () => {
        if (!currentFilterColumn) return;
        if (visibleColumnKeys().length <= 1) return;
        const column = currentFilterColumn;
        hiddenColumns.add(column);
        applyHiddenColumns();
        applyLockedColumns();
        closeColumnFilter();
        try {
            await saveColumnLayout();
            saveState.textContent = 'Column layout saved';
        } catch (error) {
            hiddenColumns.delete(column);
            applyHiddenColumns();
            applyLockedColumns();
            saveState.textContent = 'Could not save column layout';
        }
    });
    document.getElementById('insightAutoFitColumn').addEventListener('click', () => {
        if (!currentFilterColumn) return;
        autoFitColumn(currentFilterColumn);
        closeColumnFilter();
    });
    document.querySelectorAll('[data-date-quick]').forEach(button => {
        button.addEventListener('click', () => {
            const now = new Date();
            const fmt = date => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            let start = new Date(now);
            let end = new Date(now);
            if (button.dataset.dateQuick === 'yesterday') {
                start.setDate(start.getDate() - 1);
                end.setDate(end.getDate() - 1);
            }
            if (button.dataset.dateQuick === 'week') {
                const day = start.getDay() || 7;
                start.setDate(start.getDate() - day + 1);
            }
            if (button.dataset.dateQuick === 'month') {
                start = new Date(now.getFullYear(), now.getMonth(), 1);
                end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            }
            dateRangeFrom.value = fmt(start);
            dateRangeTo.value = fmt(end);
        });
    });
    document.getElementById('insightApplyColumnFilter').addEventListener('click', () => {
        if (!currentFilterColumn) return;
        const allValues = columnValues(currentFilterColumn);
        const selected = new Set(currentFilterSelection || [...filterOptions.querySelectorAll('input[type="checkbox"]:checked')].map(input => input.value));
        if (currentFilterColumn === 'date' && (dateRangeFrom.value || dateRangeTo.value)) {
            if (!dateRangeFrom.value || !dateRangeTo.value) {
                const missingInput = dateRangeFrom.value ? dateRangeTo : dateRangeFrom;
                missingInput.setCustomValidity('Select both From and To dates');
                missingInput.reportValidity();
                return;
            }
            dateRangeFilters.date = {from: dateRangeFrom.value, to: dateRangeTo.value};
            startDateFilter.value = dateRangeFrom.value;
            endDateFilter.value = dateRangeTo.value;
            delete columnFilters.date;
            page = 1;
            loadRows().catch(() => { count.textContent = 'Failed to load'; });
            closeColumnFilter();
            return;
        }
        if (serverSideFilterColumns.has(currentFilterColumn)) {
            if (selected.size === allValues.length) {
                delete columnFilters[currentFilterColumn];
                delete serverSideColumnFilters[currentFilterColumn];
            } else {
                columnFilters[currentFilterColumn] = selected;
                serverSideColumnFilters[currentFilterColumn] = selected;
                document.getElementById('insightStatus').value = '';
            }
            savePreferences();
            page = 1;
            loadRows().catch(() => { count.textContent = 'Failed to load'; });
            closeColumnFilter();
            return;
        }
        if (selected.size === allValues.length) {
            delete columnFilters[currentFilterColumn];
        } else {
            columnFilters[currentFilterColumn] = selected;
        }
        if (isDateColumn(currentFilterColumn) && (dateRangeFrom.value || dateRangeTo.value)) {
            dateRangeFilters[currentFilterColumn] = {from: dateRangeFrom.value, to: dateRangeTo.value};
        } else {
            delete dateRangeFilters[currentFilterColumn];
        }
        applyColumnFilters();
        updateCount();
        closeColumnFilter();
    });
    document.getElementById('insightClearColumnFilter').addEventListener('click', () => {
        const clearingPrimaryDate = currentFilterColumn === 'date';
        if (currentFilterColumn) delete columnFilters[currentFilterColumn];
        if (currentFilterColumn) delete serverSideColumnFilters[currentFilterColumn];
        if (currentFilterColumn) delete dateRangeFilters[currentFilterColumn];
        dateRangeFrom.value = '';
        dateRangeTo.value = '';
        if (clearingPrimaryDate) {
            startDateFilter.value = '';
            endDateFilter.value = '';
            page = 1;
            loadRows().catch(() => { count.textContent = 'Failed to load'; });
        } else if (serverSideFilterColumns.has(currentFilterColumn)) {
            savePreferences();
            page = 1;
            loadRows().catch(() => { count.textContent = 'Failed to load'; });
        } else {
            applyColumnFilters();
            updateCount();
        }
        closeColumnFilter();
    });
    document.getElementById('insightCancelColumnFilter').addEventListener('click', closeColumnFilter);
    filterSearch.addEventListener('input', () => {
        if (currentFilterColumn) renderColumnFilterOptions(currentFilterColumn, filterSearch.value);
    });
    [dateRangeFrom, dateRangeTo].forEach(input => input.addEventListener('input', () => {
        input.setCustomValidity('');
    }));
    filterSearch.addEventListener('keydown', event => {
        if (event.key === 'Enter') document.getElementById('insightApplyColumnFilter').click();
        if (event.key === 'Escape') closeColumnFilter();
    });
    filterSelectAll.addEventListener('change', () => {
        filterOptions.querySelectorAll('input[type="checkbox"]').forEach(input => {
            input.checked = filterSelectAll.checked;
            if (currentFilterSelection) {
                filterSelectAll.checked ? currentFilterSelection.add(input.value) : currentFilterSelection.delete(input.value);
            }
        });
        filterSelectAll.indeterminate = false;
    });
    filterOptions.addEventListener('change', event => {
        if (currentFilterSelection && event.target.matches('input[type="checkbox"]')) {
            event.target.checked ? currentFilterSelection.add(event.target.value) : currentFilterSelection.delete(event.target.value);
        }
        const options = [...filterOptions.querySelectorAll('input[type="checkbox"]')];
        const checked = options.filter(input => input.checked);
        filterSelectAll.checked = options.length > 0 && checked.length === options.length;
        filterSelectAll.indeterminate = checked.length > 0 && checked.length < options.length;
    });
    document.getElementById('insightChooseColumns').addEventListener('click', () => setColumnChooserOpen(true));
    document.getElementById('insightCloseColumnChooser').addEventListener('click', () => setColumnChooserOpen(false));
    document.getElementById('insightCancelColumnChooser').addEventListener('click', () => setColumnChooserOpen(false));
    columnChooserBackdrop.addEventListener('click', () => setColumnChooserOpen(false));
    columnChooserSearch.addEventListener('input', () => {
        const query = columnChooserSearch.value.trim().toLowerCase();
        columnChooserList.querySelectorAll('.column-choice').forEach(label => {
            label.classList.toggle('is-filtered-out', query !== '' && !label.dataset.columnChoice.includes(query));
        });
    });
    columnChooserList.addEventListener('change', updateColumnChooserCount);
    document.getElementById('insightShowAllColumns').addEventListener('click', () => {
        columnChooserList.querySelectorAll('input[type="checkbox"]').forEach(input => input.checked = true);
        updateColumnChooserCount();
    });
    saveColumnsButton.addEventListener('click', async () => {
        const visible = new Set([...columnChooserList.querySelectorAll('input[type="checkbox"]:checked')].map(input => input.value));
        if (visible.size === 0) return;
        const previousHidden = new Set(hiddenColumns);
        hiddenColumns.clear();
        columns.forEach(column => { if (!visible.has(column.key)) hiddenColumns.add(column.key); });
        saveColumnsButton.disabled = true;
        saveColumnsButton.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving...';
        try {
            await saveColumnLayout();
            applyHiddenColumns();
            applyLockedColumns();
            saveState.textContent = 'Column layout saved';
            setColumnChooserOpen(false);
        } catch (error) {
            hiddenColumns.clear();
            previousHidden.forEach(column => hiddenColumns.add(column));
            saveState.textContent = 'Could not save column layout';
        } finally {
            saveColumnsButton.innerHTML = '<i class="fas fa-save"></i> Save columns';
            updateColumnChooserCount();
        }
    });
    document.addEventListener('keydown', event => {
        if (event.ctrlKey && event.shiftKey && event.key.toLowerCase() === 'l') {
            event.preventDefault();
            grid.classList.toggle('filters-on');
        }
        if (event.key === 'Escape') {
            closeColumnFilter();
            setColumnChooserOpen(false);
            setTransferModalOpen(false);
            auditPanel.hidden = true;
            shell.classList.remove('fullscreen-mode');
        }
        if (!selectedCell) return;
        if (['ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp', 'Tab', 'Enter'].includes(event.key)) {
            if (document.activeElement === filterSearch) return;
            event.preventDefault();
            if (event.key === 'ArrowRight' || event.key === 'Tab') moveSelection(1, 0);
            if (event.key === 'ArrowLeft') moveSelection(-1, 0);
            if (event.key === 'ArrowDown' || event.key === 'Enter') moveSelection(0, 1);
            if (event.key === 'ArrowUp') moveSelection(0, -1);
        }
        if (event.ctrlKey && event.key.toLowerCase() === 'c' && selectedCell) {
            navigator.clipboard?.writeText(selectedCell.textContent.trim());
        }
    });
    document.addEventListener('click', event => {
        if (!filterMenu.hidden && !filterMenu.contains(event.target) && !event.target.closest('.insight-column-filter, .insight-column-menu-btn')) closeColumnFilter();
    });
    tbody.addEventListener('focusout', event => {
        if (event.target.classList.contains('insight-cell')) saveCell(event.target);
    });
    tbody.addEventListener('click', event => {
        const transferButton = event.target.closest('[data-transfer-lead]');
        if (transferButton) {
            setTransferModalOpen(true, transferButton);
            return;
        }
        const viewButton = event.target.closest('[data-view-lead]');
        if (viewButton) {
            openLeadDetails(viewButton.dataset.viewLead);
            return;
        }
        const completionButton = event.target.closest('[data-completion-details]');
        if (completionButton) {
            openCompletionDetails(completionButton.dataset.completionDetails);
            return;
        }
        const remarksButton = event.target.closest('[data-all-remarks]');
        if (remarksButton) {
            openRemarkHistory(remarksButton.closest('td'));
            return;
        }
        const internalRemarkButton = event.target.closest('[data-internal-remark]');
        if (internalRemarkButton) {
            openInternalRemarks(internalRemarkButton.dataset.internalRemark);
            return;
        }
        const internalRemarkHistoryButton = event.target.closest('[data-internal-remark-history]');
        if (internalRemarkHistoryButton) {
            openInternalRemarks(internalRemarkHistoryButton.dataset.internalRemarkHistory, false, true);
            return;
        }
        const cell = event.target.closest('.insight-cell');
        if (cell) {
            setSelectedCell(cell);
            if (['remark_1', 'remark_2', 'remark_3', 'last_remark'].includes(cell.closest('td')?.dataset.column)) {
                openRemarkHistory(cell);
            }
        }
        if (event.target.classList.contains('insight-reset')) resetCell(event.target);
    });
    tbody.addEventListener('dblclick', event => {
        const cell = event.target.closest('.insight-cell');
        if (!cell) return;
        if (isLeadQualityAuditor && cell.closest('td')?.dataset.column === 'internal_stage') return;
        setSelectedCell(cell);
        openAuditForCell(cell);
    });
    tbody.addEventListener('contextmenu', event => {
        const cell = event.target.closest('.insight-cell');
        if (!cell) return;
        if (isLeadQualityAuditor && cell.closest('td')?.dataset.column === 'internal_stage') return;
        event.preventDefault();
        setSelectedCell(cell);
        openAuditForCell(cell);
    });

    document.getElementById('auditClose').addEventListener('click', () => { auditPanel.hidden = true; document.getElementById('internalRemarkComposer')?.setAttribute('hidden', ''); });
    document.getElementById('internalRemarkSave')?.addEventListener('click', async () => {
        const value = document.getElementById('internalRemarkInput').value.trim();
        if (!value || !selectedAuditTarget) return;
        const rowKey = selectedAuditTarget.rowKey;
        const button = document.getElementById('internalRemarkSave');
        const status = document.getElementById('internalRemarkStatus');
        button.disabled = true;
        status.textContent = 'Saving...';
        status.classList.remove('is-error');
        try {
            const response = await fetch(routes.save, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify({sheet_key: 'master', row_key: rowKey, column_key: 'internal_remark', value})});
            if (!response.ok) throw new Error('Save failed');
            document.getElementById('internalRemarkInput').value = '';
            openInternalRemarks(rowKey, true);
        } catch (error) {
            status.textContent = 'Remark save nahi hua. Dobara try karein.';
            status.classList.add('is-error');
        } finally {
            button.disabled = false;
        }
    });
    document.getElementById('leadDetailsClose').addEventListener('click', () => document.getElementById('insightLeadDetailsPanel').hidden = true);
    document.getElementById('insightTransferClose')?.addEventListener('click', () => setTransferModalOpen(false));
    document.getElementById('insightTransferCancel')?.addEventListener('click', () => setTransferModalOpen(false));
    document.getElementById('insightTransferModal')?.addEventListener('click', event => {
        if (event.target.id === 'insightTransferModal') setTransferModalOpen(false);
    });
    document.getElementById('insightTransferForm')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        const button = document.getElementById('insightTransferSubmit');
        const status = document.getElementById('insightTransferStatus');
        const data = new FormData(form);
        status.textContent = '';
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Transferring...';
        try {
            const response = await fetch(routes.transfer, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token},
                body: JSON.stringify({
                    row_key: data.get('row_key'),
                    assigned_to: data.get('assigned_to'),
                    notes: data.get('notes'),
                }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validationMessage = Object.values(payload.errors || {}).flat()[0];
                throw new Error(validationMessage || payload.message || 'Lead transfer failed.');
            }
            setTransferModalOpen(false);
            saveState.textContent = payload.message || 'Lead transferred';
            page = 1;
            await loadRows();
        } catch (error) {
            status.textContent = error.message || 'Lead transfer failed. Please try again.';
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-right-left"></i> Transfer Lead';
        }
    });
    document.getElementById('leadDetailsEdit').addEventListener('click', openLeadDetailsEditor);
    document.getElementById('leadDetailsContent').addEventListener('click', event => {
        if (event.target.closest('#leadDetailsEditCancel') && currentLeadDetails) renderLeadDetails(currentLeadDetails);
    });
    document.getElementById('leadDetailsContent').addEventListener('change', event => {
        if (event.target.matches('#lead-edit-category')) syncAuditorTypeOptions();
        if (event.target.matches('input[name="project"]')) {
            const field = event.target.closest('.lead-edit-field');
            const selected = [...field.querySelectorAll('input[name="project"]:checked')].map(input => input.value);
            field.querySelector('[data-project-summary]').textContent = selected.length ? selected.join(', ') : 'Select projects';
        }
    });
    document.getElementById('leadDetailsContent').addEventListener('input', event => {
        if (!event.target.matches('[data-project-search]')) return;
        const query = event.target.value.trim().toLowerCase();
        event.target.closest('.lead-edit-multi').querySelectorAll('[data-project-option]').forEach(option => {
            option.hidden = query !== '' && !option.dataset.projectOption.includes(query);
        });
    });
    document.getElementById('leadDetailsContent').addEventListener('submit', event => {
        if (!event.target.matches('#leadDetailsEditor')) return;
        event.preventDefault();
        saveLeadDetailsOverride(event.target);
    });
    document.getElementById('auditResetCell')?.addEventListener('click', () => {
        if (!selectedAuditTarget) return;
        const cell = tbody.querySelector(`tr[data-row-key="${CSS.escape(selectedAuditTarget.rowKey)}"] td[data-column="${CSS.escape(selectedAuditTarget.columnKey)}"] .insight-cell`);
        if (!cell) return;
        resetCell({closest: selector => selector === 'td' ? cell.closest('td') : cell.closest('tr')});
    });
    document.getElementById('insightFullscreen').addEventListener('click', () => {
        shell.classList.toggle('fullscreen-mode');
        document.getElementById('insightFullscreen').innerHTML = shell.classList.contains('fullscreen-mode') ? '<i class="fas fa-compress"></i>' : '<i class="fas fa-expand"></i>';
    });
    densitySelect.addEventListener('change', () => {
        shell.classList.toggle('density-comfortable', densitySelect.value === 'comfortable');
        savePreferences();
    });
    pageSizeSelect.addEventListener('change', () => {
        page = 1;
        savePreferences();
        loadRows().catch(() => { count.textContent = 'Failed to load'; });
    });
    document.getElementById('insightResetLayout').addEventListener('click', async () => {
        const previousHidden = new Set(hiddenColumns);
        lockedColumns.clear();
        ['serial', 'source'].forEach(key => lockedColumns.add(key));
        hiddenColumns.clear();
        defaultHiddenColumns.forEach(column => hiddenColumns.add(column));
        Object.keys(columnWidths).forEach(key => delete columnWidths[key]);
        densitySelect.value = 'compact';
        shell.classList.remove('density-comfortable');
        applyHiddenColumns();
        applyColumnWidths();
        applyLockedColumns();
        try {
            await saveColumnLayout();
            saveState.textContent = 'Layout reset';
        } catch (error) {
            hiddenColumns.clear();
            previousHidden.forEach(column => hiddenColumns.add(column));
            applyHiddenColumns();
            applyLockedColumns();
            saveState.textContent = 'Could not reset layout';
        }
    });
    document.getElementById('insightSavePending')?.addEventListener('click', () => {
        document.querySelectorAll('.insight-cell.pending, .insight-cell.failed').forEach(saveCell);
    });
    document.getElementById('insightRetryFailed').addEventListener('click', () => {
        document.querySelectorAll('.insight-cell.failed').forEach(saveCell);
    });
    document.getElementById('insightDiscardChanges').addEventListener('click', () => {
        pending.clear();
        document.querySelectorAll('.insight-cell.pending, .insight-cell.failed').forEach(cell => {
            cell.textContent = cell.dataset.original || '';
            cell.classList.remove('pending', 'failed');
        });
        updateDirtyBar();
        saveState.textContent = 'Ready';
    });
    document.getElementById('insightAccessBtn')?.addEventListener('click', openAccessModal);
    document.getElementById('accessSave')?.addEventListener('click', saveAccessModal);
    document.getElementById('accessClose')?.addEventListener('click', () => document.getElementById('insightAccessModal').hidden = true);
    document.getElementById('accessCancel')?.addEventListener('click', () => document.getElementById('insightAccessModal').hidden = true);

    addHeaderResizers();
    applyHiddenColumns();
    applyColumnWidths();
    loadRows().catch(() => { count.textContent = 'Failed to load'; });
})();
</script>
@endsection
