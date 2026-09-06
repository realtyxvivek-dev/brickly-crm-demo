@extends('layouts.app')
@section('title', 'Edit Prospect')
@section('page-title', 'Edit Prospect')

@section('content')
<div style="max-width:700px;margin:0 auto;">
    <div style="background:#fff;border-radius:16px;border:1px solid #e5e7eb;padding:28px;box-shadow:0 2px 8px rgba(0,0,0,.06);">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
            <div style="width:42px;height:42px;background:linear-gradient(135deg,#063A1C,#205A44);border-radius:11px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-user-edit" style="color:#fff;font-size:16px;"></i>
            </div>
            <div>
                <div style="font-size:17px;font-weight:700;color:#111827;">{{ $prospect->customer_name }}</div>
                <div style="font-size:12px;color:#6b7280;">Prospect edit karo</div>
            </div>
        </div>

        @if(session('success'))
        <div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
        @endif

        <form action="{{ route('sales-manager.prospects.update', $prospect->id) }}" method="POST">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Customer Name</label>
                    <input type="text" name="customer_name" value="{{ $prospect->customer_name }}"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13.5px;outline:none;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Phone</label>
                    <input type="text" name="phone" value="{{ $prospect->phone }}"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13.5px;outline:none;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Email</label>
                    <input type="email" name="email" value="{{ $prospect->email }}"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13.5px;outline:none;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Budget (₹)</label>
                    <input type="number" name="budget" value="{{ $prospect->budget }}"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13.5px;outline:none;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Preferred Location</label>
                    <input type="text" name="preferred_location" value="{{ $prospect->preferred_location }}"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13.5px;outline:none;">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Project</label>
                    <input type="text" name="project" value="{{ $prospect->project }}"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13.5px;outline:none;">
                </div>
                <div style="grid-column:1/-1;">
                    <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:5px;">Notes</label>
                    <textarea name="notes" rows="3"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:13.5px;outline:none;resize:vertical;">{{ $prospect->notes }}</textarea>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="submit"
                    style="flex:1;padding:10px;background:linear-gradient(135deg,#063A1C,#205A44);color:#fff;border:none;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="/prospects"
                    style="flex:1;padding:10px;background:#f3f4f6;color:#374151;border-radius:9px;font-size:14px;font-weight:600;text-decoration:none;text-align:center;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
