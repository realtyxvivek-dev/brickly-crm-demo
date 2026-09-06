@extends('layouts.app')

@section('title', 'Password Change Pending')
@section('page-title', 'Password Change Report')
@section('page-subtitle', 'Pending aur changed users ka password audit')

@section('content')
<div style="padding:24px;">
    <div style="display:flex;gap:16px;align-items:stretch;flex-wrap:wrap;margin-bottom:20px;">
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:18px;min-width:220px;">
            <div style="font-size:12px;text-transform:uppercase;color:#9a3412;font-weight:900;">Pending</div>
            <div style="font-size:34px;font-weight:900;color:#7c2d12;margin-top:6px;">{{ $pendingCount }}</div>
        </div>
        <div style="background:#ecfdf5;border:1px solid #bbf7d0;border-radius:14px;padding:18px;min-width:220px;">
            <div style="font-size:12px;text-transform:uppercase;color:#047857;font-weight:900;">Changed</div>
            <div style="font-size:34px;font-weight:900;color:#065f46;margin-top:6px;">{{ $changedCount }}</div>
        </div>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;padding:18px;min-width:220px;">
            <div style="font-size:12px;text-transform:uppercase;color:#1d4ed8;font-weight:900;">Total Active</div>
            <div style="font-size:34px;font-weight:900;color:#1e3a8a;margin-top:6px;">{{ $totalCount }}</div>
        </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        @foreach(['pending' => 'Pending', 'changed' => 'Changed', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.password-change-pending.index', array_merge(request()->except('page', 'status'), ['status' => $key])) }}" style="text-decoration:none;border-radius:999px;padding:9px 14px;font-weight:900;font-size:13px;{{ $status === $key ? 'background:#0f5132;color:#fff;' : 'background:#fff;color:#334155;border:1px solid #e2e8f0;' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.password-change-pending.index') }}" style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;margin-bottom:18px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, email, phone..." style="flex:1;min-width:240px;border:1px solid #d1d5db;border-radius:10px;padding:11px 12px;">
            <select name="role_id" style="min-width:220px;border:1px solid #d1d5db;border-radius:10px;padding:11px 12px;background:#fff;">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) request('role_id') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            <button type="submit" style="background:#0f5132;color:#fff;border:0;border-radius:10px;padding:11px 16px;font-weight:900;">Search</button>
            <a href="{{ route('admin.password-change-pending.index') }}" style="color:#64748b;text-decoration:none;font-weight:700;">Clear</a>
    </form>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid #e5e7eb;">
                    <th style="text-align:left;padding:13px;font-size:12px;color:#64748b;text-transform:uppercase;">User</th>
                    <th style="text-align:left;padding:13px;font-size:12px;color:#64748b;text-transform:uppercase;">Role</th>
                    <th style="text-align:left;padding:13px;font-size:12px;color:#64748b;text-transform:uppercase;">Phone</th>
                    <th style="text-align:left;padding:13px;font-size:12px;color:#64748b;text-transform:uppercase;">Requested At</th>
                    <th style="text-align:left;padding:13px;font-size:12px;color:#64748b;text-transform:uppercase;">Requested By</th>
                    <th style="text-align:left;padding:13px;font-size:12px;color:#64748b;text-transform:uppercase;">Changed At</th>
                    <th style="text-align:left;padding:13px;font-size:12px;color:#64748b;text-transform:uppercase;">Status</th>
                    <th style="text-align:right;padding:13px;font-size:12px;color:#64748b;text-transform:uppercase;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    @php
                        $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];
                        $requestedAt = $preferences['password_change_requested_at'] ?? null;
                        $requestedBy = $preferences['password_change_requested_by'] ?? null;
                        $changedAt = $preferences['password_changed_at'] ?? null;
                    @endphp
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:13px;">
                            <div style="font-weight:900;color:#111827;">{{ $user->name }}</div>
                            <div style="font-size:12px;color:#64748b;">{{ $user->email }}</div>
                        </td>
                        <td style="padding:13px;color:#334155;">{{ $user->role?->name ?: '-' }}</td>
                        <td style="padding:13px;color:#334155;">{{ $user->phone ?: '-' }}</td>
                        <td style="padding:13px;color:#334155;">{{ $requestedAt ? \Illuminate\Support\Carbon::parse($requestedAt)->format('d M Y, h:i A') : 'Old/untracked request' }}</td>
                        <td style="padding:13px;color:#334155;">{{ $requestedBy ? ($requesters[$requestedBy] ?? ('User #' . $requestedBy)) : '-' }}</td>
                        <td style="padding:13px;color:#334155;">{{ $changedAt ? \Illuminate\Support\Carbon::parse($changedAt)->format('d M Y, h:i A') : '-' }}</td>
                        <td style="padding:13px;">
                            @if($changedAt)
                                <span style="display:inline-flex;border-radius:999px;background:#dcfce7;color:#047857;padding:5px 9px;font-size:12px;font-weight:900;">Changed</span>
                            @else
                                <span style="display:inline-flex;border-radius:999px;background:#ffedd5;color:#9a3412;padding:5px 9px;font-size:12px;font-weight:900;">Not Confirmed</span>
                            @endif
                        </td>
                        <td style="padding:13px;text-align:right;">
                            <a href="{{ route('users.show', $user) }}" style="display:inline-flex;align-items:center;gap:6px;background:#0f5132;color:#fff;text-decoration:none;border-radius:9px;padding:9px 12px;font-size:12px;font-weight:900;">
                                <i class="fas fa-user"></i> Open
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding:52px;text-align:center;color:#64748b;">
                            <div style="font-size:18px;font-weight:900;color:#0f172a;">No unconfirmed password changes</div>
                            <div style="margin-top:6px;font-size:13px;">Sab active users ka password change confirm ho chuka hai.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
