@extends('layouts.app')

@section('title', 'Import Project from URL - ' . brand_name())
@section('page-title', 'Import Project from URL')

@section('header-actions')
    <div class="flex items-center gap-3">
        <a href="{{ route('projects.import.index') }}" class="px-4 py-2 bg-white border border-[#205A44]/20 text-[#205A44] rounded-lg hover:bg-[#F3F7F4] transition-colors duration-200 text-sm font-medium">
            Import from Excel
        </a>
        <a href="{{ route('projects.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 text-sm font-medium">
            Back to Projects
        </a>
    </div>
@endsection

@push('styles')
<style>
    .url-import-shell {
        display: grid;
        grid-template-columns: minmax(320px, 0.9fr) minmax(0, 1.1fr);
        gap: 24px;
    }
    .url-import-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 24px;
        padding: 28px;
        box-shadow: 0 14px 36px rgba(6, 58, 28, 0.06);
    }
    .stage-list {
        display: grid;
        gap: 12px;
    }
    .stage-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        border-radius: 16px;
        background: #f8faf8;
        border: 1px solid #e5efe7;
    }
    .stage-row.is-active {
        background: linear-gradient(90deg, rgba(6, 58, 28, 0.08), rgba(32, 90, 68, 0.12));
        border-color: rgba(32, 90, 68, 0.22);
    }
    .stage-row.is-done {
        background: #f3f7f4;
        color: #205A44;
    }
    .summary-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 700;
        background: #f3f7f4;
        color: #205A44;
    }
    .status-box {
        border-radius: 18px;
        padding: 18px 20px;
        border: 1px solid #e5e7eb;
        background: #fafaf9;
    }
    @media (max-width: 1024px) {
        .url-import-shell {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="url-import-shell">
    <div class="url-import-card">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Smart URL import</h2>
            <p class="text-sm text-gray-500 mt-2">
                Phase 1 abhi `Housing` aur `PropertyPistol` support karta hai. URL se maximum data extract hoga, phir alag review screen me verify karke draft create hoga.
            </p>
        </div>

        <div class="space-y-4">
            <div>
                <label for="source_url" class="block text-sm font-semibold text-gray-900 mb-2">Project URL</label>
                <input id="source_url" type="url" class="block w-full border border-gray-300 rounded-xl px-4 py-3 bg-white text-sm" placeholder="https://housing.com/... or https://propertypistol.in/...">
            </div>

            <button id="extract-btn" type="button" class="px-5 py-3 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-xl text-sm font-semibold">
                Extract and Review
            </button>
        </div>

        <div class="mt-6">
            <h3 class="text-sm font-semibold text-gray-900 mb-2">Draft create kab block hoga</h3>
            <ul class="list-disc pl-5 text-sm text-gray-600 space-y-1">
                <li>Builder</li>
                <li>Project Name</li>
                <li>City</li>
                <li>Area / Locality</li>
            </ul>
            <p class="text-xs text-gray-500 mt-3">
                In 4 fields ke alawa baaki data missing ho to bhi partial draft create ho jayega.
            </p>
        </div>
    </div>

    <div class="url-import-card">
        <div class="flex items-start justify-between gap-6 mb-6">
            <div>
                <h3 class="text-xl font-semibold text-gray-900">Extraction Progress</h3>
                <p class="text-sm text-gray-500 mt-2">Percent ke saath stage labels dikhaye jayenge. Review screen extraction complete hone ke baad khulegi.</p>
            </div>
            <div class="summary-pill" id="progress-pill">0%</div>
        </div>

        <div class="stage-list" id="stage-list">
            @foreach (['Detecting source', 'Extracting basics', 'Extracting pricing', 'Extracting variants', 'Preparing review'] as $index => $label)
                <div class="stage-row {{ $index === 0 ? 'is-active' : '' }}" data-stage-index="{{ $index }}">
                    <span class="font-medium text-gray-900">{{ $label }}</span>
                    <span class="text-xs text-gray-500">Pending</span>
                </div>
            @endforeach
        </div>

        <div class="status-box mt-6" id="status-box">
            <div class="text-sm font-semibold text-gray-900 mb-2">Ready to extract</div>
            <p class="text-sm text-gray-500">URL paste karo, phir system source detect karke review token generate karega.</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const sourceInput = document.getElementById('source_url');
const extractBtn = document.getElementById('extract-btn');
const stageRows = Array.from(document.querySelectorAll('.stage-row'));
const progressPill = document.getElementById('progress-pill');
const statusBox = document.getElementById('status-box');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

const stageLabels = [
    'Detecting source',
    'Extracting basics',
    'Extracting pricing',
    'Extracting variants',
    'Preparing review',
];

let stageTimer = null;

extractBtn.addEventListener('click', async function () {
    if (!sourceInput.value.trim()) {
        alert('Project URL required hai.');
        return;
    }

    extractBtn.disabled = true;
    extractBtn.textContent = 'Extracting...';
    runStageAnimation();
    setStatus('Extraction started', 'Portal se data read karke review payload prepare kiya ja raha hai.');

    try {
        const response = await fetch('{{ route('projects.import-url.extract') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ source_url: sourceInput.value.trim() }),
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'URL extraction failed.');
        }

        finishStages(data.summary.progress_stage || 'Preparing review', data.summary.progress_percent || 100);
        setStatus('Review ready', `Source: ${data.summary.source_key || 'detected'} | Extracted fields: ${data.summary.stats?.extracted_fields || 0}`);
        window.location.href = data.review_url;
    } catch (error) {
        clearInterval(stageTimer);
        setStatus('Extraction failed', error.message, true);
        alert(error.message);
        resetStages();
    } finally {
        extractBtn.disabled = false;
        extractBtn.textContent = 'Extract and Review';
    }
});

function runStageAnimation() {
    resetStages();
    let currentIndex = 0;
    let progress = 8;
    progressPill.textContent = `${progress}%`;

    stageTimer = setInterval(() => {
        stageRows.forEach((row, index) => {
            const meta = row.querySelector('span:last-child');
            row.classList.remove('is-active', 'is-done');

            if (index < currentIndex) {
                row.classList.add('is-done');
                meta.textContent = 'Done';
            } else if (index === currentIndex) {
                row.classList.add('is-active');
                meta.textContent = 'Running';
            } else {
                meta.textContent = 'Pending';
            }
        });

        progress = Math.min(progress + 17, 92);
        progressPill.textContent = `${progress}%`;
        currentIndex = Math.min(currentIndex + 1, stageRows.length - 1);
    }, 500);
}

function finishStages(finalStage, percent) {
    clearInterval(stageTimer);
    stageRows.forEach((row) => {
        row.classList.remove('is-active');
        row.classList.add('is-done');
        row.querySelector('span:last-child').textContent = 'Done';
    });
    progressPill.textContent = `${percent}%`;
}

function resetStages() {
    clearInterval(stageTimer);
    progressPill.textContent = '0%';
    stageRows.forEach((row, index) => {
        row.classList.remove('is-active', 'is-done');
        if (index === 0) {
            row.classList.add('is-active');
        }
        row.querySelector('span:last-child').textContent = 'Pending';
    });
}

function setStatus(title, body, isError = false) {
    statusBox.className = `status-box mt-6 ${isError ? 'border-red-200 bg-red-50' : ''}`;
    statusBox.innerHTML = `
        <div class="text-sm font-semibold ${isError ? 'text-red-700' : 'text-gray-900'} mb-2">${title}</div>
        <p class="text-sm ${isError ? 'text-red-600' : 'text-gray-500'}">${body}</p>
    `;
}
</script>
@endpush
