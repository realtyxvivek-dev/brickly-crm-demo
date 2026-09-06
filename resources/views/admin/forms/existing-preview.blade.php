<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $formName }} - Preview</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f8fafc; padding: 24px; color: #1a202c; }

        .preview-header {
            background: linear-gradient(135deg, #063A1C, #205A44);
            color: white;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .preview-header h2 { font-size: 16px; font-weight: 600; }
        .preview-header .badge {
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .form-body { background: white; border-radius: 10px; padding: 20px; border: 1px solid #e5e7eb; }
        .preview-section + .preview-section { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
        .preview-section-title { margin: 0 0 12px; color: #123d2c; font-size: 14px; font-weight: 800; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.full-width { grid-column: 1 / -1; }

        label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .required-star { color: #ef4444; font-size: 13px; }
        .readonly-badge {
            background: #f3f4f6;
            color: #6b7280;
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 10px;
            font-weight: 500;
        }

        input, select, textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #e5e7eb;
            border-radius: 7px;
            font-size: 13px;
            font-family: inherit;
            color: #4b5563;
            background: #f9fafb;
            outline: none;
            pointer-events: none; /* preview only */
        }
        input[readonly], input.readonly-field { background: #f3f4f6; color: #9ca3af; }
        textarea { min-height: 80px; resize: none; }

        .field-type-tag {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .preview-note {
            margin-top: 16px;
            background: #fefce8;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 12px;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        @media (max-width: 640px) { body { padding: 14px; } .form-grid { grid-template-columns: 1fr; } .form-group.full-width { grid-column: auto; } }
    </style>
</head>
<body>
    <div class="preview-header">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h2>{{ $formName }}</h2>
        <span class="badge">{{ count($fields) }} fields</span>
    </div>

    @php
        $fieldSections = collect($fields)->groupBy(fn ($field) => $field['section'] ?? 'Form Fields');
    @endphp
    <div class="form-body">
        @foreach($fieldSections as $sectionName => $sectionFields)
        <section class="preview-section">
            <h3 class="preview-section-title">{{ $sectionName }}</h3>
            <div class="form-grid">
            @foreach($sectionFields as $field)
                @php
                    $fieldType = $field['type'] ?? $field['field_type'] ?? 'text';
                    $isTextarea = $fieldType === 'textarea';
                    $isReadonly = !empty($field['readonly']);
                @endphp
                <div class="form-group {{ $isTextarea ? 'full-width' : '' }}">
                    <label>
                        {{ $field['label'] }}
                        @if(!empty($field['required']))
                            <span class="required-star">*</span>
                        @endif
                        @if($isReadonly)
                            <span class="readonly-badge">auto-filled</span>
                        @endif
                    </label>

                    @if($fieldType === 'textarea')
                        <textarea placeholder="Enter {{ strtolower($field['label']) }}..."></textarea>
                    @elseif($fieldType === 'select')
                        <select>
                            <option>Select {{ $field['label'] }}...</option>
                            @foreach($field['options'] ?? [] as $option)
                                <option>{{ $option }}</option>
                            @endforeach
                        </select>
                    @else
                        <input
                            type="{{ $fieldType }}"
                            placeholder="{{ $isReadonly ? 'Auto-filled' : 'Enter ' . strtolower($field['label']) . '...' }}"
                            {{ $isReadonly ? 'class=readonly-field' : '' }}
                        >
                    @endif

                    <span class="field-type-tag">Type: {{ strtoupper($fieldType) }}{{ !empty($field['required']) ? ' · Required' : ' · Optional' }}</span>
                </div>
            @endforeach
            </div>
        </section>
        @endforeach

        <div class="preview-note">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            This is a read-only preview of the form fields. Fields are not interactive.
        </div>
    </div>
</body>
</html>
