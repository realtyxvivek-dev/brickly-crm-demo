<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Detail Form</title>
    <style>
        :root { --green:#0f5138; --line:#dbe7e0; --text:#0f172a; --muted:#64748b; --soft:#f6faf8; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--soft); color:var(--text); font-family:Arial, sans-serif; }
        .page { width:min(980px,94vw); margin:24px auto 40px; }
        .hero, .card { background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:0 16px 42px rgba(15,23,42,.06); }
        .hero { padding:22px; display:flex; justify-content:space-between; gap:16px; align-items:flex-start; }
        .kicker { font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:var(--muted); }
        h1 { margin:7px 0 6px; font-size:30px; line-height:1.1; }
        .copy { color:var(--muted); font-size:14px; line-height:1.5; margin:0; }
        .chip { display:inline-flex; padding:8px 12px; border:1px solid var(--line); border-radius:999px; font-size:12px; font-weight:800; white-space:nowrap; }
        .alert { margin:16px 0; padding:13px 16px; border-radius:14px; background:#ecfdf3; border:1px solid #bbf7d0; color:#14532d; font-weight:800; }
        .card { margin-top:16px; padding:20px; }
        .section-title { margin:0 0 14px; font-size:18px; font-weight:900; }
        .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
        .field { display:flex; flex-direction:column; gap:7px; }
        label { font-size:11px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); }
        input, textarea { width:100%; border:1px solid var(--line); border-radius:12px; padding:12px 13px; font-size:14px; font-weight:700; color:var(--text); background:#fff; }
        input[readonly] { background:#f8fafc; color:#475569; }
        textarea { min-height:92px; resize:vertical; }
        .errors { margin:16px 0; padding:14px 18px; background:#fff1f2; border:1px solid #fecdd3; color:#be123c; border-radius:14px; font-weight:700; }
        .doc-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; align-items:center; padding:12px; border:1px solid var(--line); border-radius:14px; margin-bottom:10px; }
        .doc-name { font-weight:900; }
        .doc-state { margin-top:4px; color:var(--muted); font-size:12px; font-weight:700; }
        .actions { position:sticky; bottom:0; display:flex; justify-content:flex-end; gap:10px; padding:16px 0 0; background:linear-gradient(180deg,rgba(246,250,248,0),var(--soft) 40%); }
        button { border:0; border-radius:14px; padding:13px 20px; background:var(--green); color:#fff; font-size:15px; font-weight:900; cursor:pointer; }
        @media (max-width:720px) {
            .hero { flex-direction:column; }
            .grid, .doc-row { grid-template-columns:1fr; }
            h1 { font-size:24px; }
            .actions { justify-content:stretch; }
            button { width:100%; }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="hero">
            <div>
                <div class="kicker">Employee Detail Form</div>
                <h1>{{ $employee->name }}</h1>
                <p class="copy">Please fill your joining, KYC, bank and document details. You can edit this form until link expiry.</p>
            </div>
            <span class="chip">Expires {{ $link->expires_at?->format('d M Y, h:i A') }}</span>
        </section>

        @if(session('success'))
            <div class="alert">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="errors">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('employee-detail-form.store', $link->token) }}" enctype="multipart/form-data">
            @csrf

            <section class="card">
                <h2 class="section-title">Basic Details</h2>
                <div class="grid">
                    <div class="field"><label>Name</label><input type="text" value="{{ $employee->name }}" readonly></div>
                    <div class="field"><label>Phone</label><input type="text" value="{{ $employee->phone }}" readonly></div>
                    <div class="field"><label>Position</label><input type="text" value="{{ $profile->designation?->name ?: ($employee->role?->name ?? 'Employee') }}" readonly></div>
                    <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email', $employee->email) }}" @if($employee->email) readonly @endif></div>
                    <div class="field"><label>Date of Birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', $profile->date_of_birth?->toDateString()) }}"></div>
                </div>
            </section>

            <section class="card">
                <h2 class="section-title">Emergency & Address</h2>
                <div class="grid">
                    <div class="field"><label>Emergency Contact Name</label><input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profile->emergency_contact_name) }}"></div>
                    <div class="field"><label>Emergency Contact Phone</label><input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $profile->emergency_contact_phone) }}"></div>
                    <div class="field"><label>Current Address</label><textarea name="current_address">{{ old('current_address', $profile->current_address) }}</textarea></div>
                    <div class="field"><label>Permanent Address</label><textarea name="permanent_address">{{ old('permanent_address', $profile->permanent_address) }}</textarea></div>
                </div>
            </section>

            <section class="card">
                <h2 class="section-title">Bank & KYC</h2>
                <div class="grid">
                    <div class="field"><label>Account Holder Name</label><input type="text" name="account_holder_name" value="{{ old('account_holder_name', $profile->account_holder_name) }}" required></div>
                    <div class="field"><label>Bank Account Number</label><input type="text" name="bank_account_number" value="{{ old('bank_account_number', $profile->bank_account_number) }}" required></div>
                    <div class="field"><label>IFSC Code</label><input type="text" name="ifsc_code" value="{{ old('ifsc_code', $profile->ifsc_code) }}" required></div>
                    <div class="field"><label>PAN Number</label><input type="text" name="pan_number" value="{{ old('pan_number', $profile->pan_number) }}" required></div>
                    <div class="field"><label>Aadhaar Number</label><input type="text" name="aadhaar_number" value="{{ old('aadhaar_number', $profile->aadhaar_number) }}" required></div>
                </div>
            </section>

            <section class="card">
                <h2 class="section-title">Documents</h2>
                @foreach($documentFields as $field => $label)
                    @php($existing = $documents->get($field))
                    <div class="doc-row">
                        <div>
                            <div class="doc-name">{{ $label }}</div>
                            <div class="doc-state">{{ $existing ? 'Already uploaded. Choose file only if you want to replace it.' : 'Not uploaded yet.' }}</div>
                        </div>
                        <input type="file" name="documents[{{ $field }}]" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                @endforeach
            </section>

            <div class="actions">
                <button type="submit">Submit Details</button>
            </div>
        </form>
    </main>
</body>
</html>
