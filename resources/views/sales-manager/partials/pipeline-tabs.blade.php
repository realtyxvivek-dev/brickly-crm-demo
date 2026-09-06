@php
    $asmPipelineTabs = [
        [
            'status' => '',
            'label' => 'All Lead',
            'icon' => 'fas fa-address-book',
            'count_id' => 'asmLeadPipelineCountAll',
        ],
        [
            'status' => 'follow_up',
            'label' => 'Follow Up',
            'icon' => 'fas fa-phone-volume',
            'count_id' => 'asmLeadPipelineCountFollowUp',
        ],
        [
            'status' => 'meeting',
            'label' => 'Meeting',
            'icon' => 'fas fa-handshake',
            'count_id' => 'asmLeadPipelineCountMeeting',
        ],
        [
            'status' => 'visit',
            'label' => 'Visit',
            'icon' => 'fas fa-location-dot',
            'count_id' => 'asmLeadPipelineCountVisit',
        ],
        [
            'status' => 'cnp',
            'label' => 'CNP',
            'icon' => 'fas fa-phone-slash',
            'count_id' => 'asmLeadPipelineCountCnp',
        ],
    ];
@endphp

@once
    @push('styles')
    <style>
        .asm-pipeline-tabs-shell {
            margin-bottom: 16px;
        }
        .asm-pipeline-tabs {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px;
            border-radius: 999px;
            background: linear-gradient(180deg, #ffffff 0%, #f7faf8 100%);
            border: 1px solid #dbe7de;
            box-shadow: 0 10px 26px rgba(6, 58, 28, 0.08);
            overflow-x: auto;
            max-width: 100%;
        }
        .asm-pipeline-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 56px;
            padding: 11px 16px;
            border-radius: 999px;
            color: #5f6c7b;
            text-decoration: none;
            font-size: 0.92rem;
            font-weight: 700;
            white-space: nowrap;
            transition: all 0.2s ease;
            border: 0;
            background: transparent;
        }
        .asm-pipeline-tab:hover {
            color: #205A44;
            background: #eff7f2;
        }
        .asm-pipeline-tab.is-active {
            background: linear-gradient(135deg, #063A1C, #205A44);
            color: #fff;
            box-shadow: 0 10px 22px rgba(6, 58, 28, 0.18);
        }
        .asm-pipeline-tab-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .asm-pipeline-tab-count {
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef5f0;
            color: #205A44;
            font-size: 0.74rem;
            font-weight: 800;
        }
        .asm-pipeline-tab.is-active .asm-pipeline-tab-count {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
        }
        .asm-filter-shell {
            background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(247,250,248,0.98) 100%);
            border: 1px solid #dbe7de;
            border-radius: 24px;
            box-shadow: 0 16px 36px rgba(6, 58, 28, 0.08);
            padding: 18px;
            margin-bottom: 20px;
        }
        .asm-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            align-items: end;
        }
        .asm-filter-grid.asm-filter-grid--three {
            grid-template-columns: minmax(0, 1.5fr) repeat(2, minmax(170px, 0.8fr));
        }
        .asm-filter-grid.asm-filter-grid--five {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }
        .asm-filter-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }
        .asm-filter-field label {
            font-size: 0.78rem;
            font-weight: 800;
            color: #587060;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .asm-filter-input,
        .asm-filter-select {
            width: 100%;
            min-height: 52px;
            border-radius: 16px;
            border: 1px solid #d7e2da;
            background: #ffffff;
            padding: 0 16px;
            font-size: 0.95rem;
            color: #173427;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .asm-filter-input::placeholder {
            color: #8b9a90;
        }
        .asm-filter-input:focus,
        .asm-filter-select:focus {
            outline: none;
            border-color: #205A44;
            box-shadow: 0 0 0 4px rgba(32, 90, 68, 0.10);
            background: #fff;
        }
        .asm-filter-date-range {
            display: none;
            gap: 10px;
            grid-column: span 2;
        }
        .asm-filter-date-range.show {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        @media (max-width: 768px) {
            .asm-pipeline-tabs-shell {
                margin-bottom: 12px;
            }
            .asm-pipeline-tabs {
                display: flex;
                width: 100%;
                gap: 8px;
                padding: 6px;
                overflow-x: auto;
                overflow-y: hidden;
                -ms-overflow-style: none;
                scrollbar-width: none;
                justify-content: flex-start;
            }
            .asm-pipeline-tabs::-webkit-scrollbar {
                display: none;
            }
            .asm-pipeline-tab {
                flex: 0 0 auto;
                min-width: max-content;
                padding: 11px 14px;
                font-size: 0.84rem;
                gap: 7px;
            }
            .asm-pipeline-tab-label {
                gap: 6px;
            }
            .asm-pipeline-tab-label i {
                display: none;
            }
            .asm-pipeline-tab-count {
                min-width: 20px;
                height: 20px;
                padding: 0 5px;
                font-size: 0.7rem;
            }
            .asm-filter-shell {
                padding: 14px;
                border-radius: 20px;
            }
            .asm-filter-grid,
            .asm-filter-grid.asm-filter-grid--three,
            .asm-filter-grid.asm-filter-grid--five {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }
            .asm-filter-date-range,
            .asm-filter-date-range.show {
                grid-column: 1 / -1;
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 520px) {
            .asm-filter-grid,
            .asm-filter-grid.asm-filter-grid--three,
            .asm-filter-grid.asm-filter-grid--five {
                grid-template-columns: 1fr;
            }
        }
        @media (min-width: 769px) {
            .asm-pipeline-tabs-shell {
                display: none;
            }
        }
    </style>
    @endpush
@endonce

<div class="asm-pipeline-tabs-shell">
    <nav class="asm-pipeline-tabs" aria-label="ASM pipeline navigation">
        @foreach($asmPipelineTabs as $tab)
            <button
                type="button"
                class="asm-pipeline-tab"
                data-pipeline-status="{{ $tab['status'] }}"
                onclick="applyAsmLeadPipelineFilter('{{ $tab['status'] }}')"
            >
                <span class="asm-pipeline-tab-label">
                    <i class="{{ $tab['icon'] }}"></i>
                    {{ $tab['label'] }}
                </span>
                <span class="asm-pipeline-tab-count" id="{{ $tab['count_id'] }}">0</span>
            </button>
        @endforeach
    </nav>
</div>
