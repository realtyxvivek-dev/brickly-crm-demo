@extends(auth()->user()?->isAssistantSalesManager() || auth()->user()?->isSalesManager() || auth()->user()?->isSeniorManager() ? 'sales-manager.layout' : 'layouts.app')

@section('title', 'Change Password')

@section('content')
<div style="max-width: 560px; margin: 40px auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);">
    <h1 style="margin: 0 0 10px; font-size: 24px; color: #063A1C;">Change your password</h1>
    <p style="margin: 0 0 24px; color: #64748b; line-height: 1.5;">
        @if($currentPasswordRequired)
            Enter your current password and choose a new secure password.
        @else
            Admin has requested a password change. Current password is not required for this update.
        @endif
    </p>

    @if(session('success'))
        <div style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; padding: 12px 14px; border-radius: 8px; margin-bottom: 18px;">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('account.password.update') }}">
        @csrf

        @if($currentPasswordRequired)
            <div style="margin-bottom: 16px;">
                <label for="current_password" style="display: block; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Current password</label>
                <input id="current_password" type="password" name="current_password" autocomplete="current-password" style="width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 12px 14px;">
                @error('current_password')
                    <div style="color: #dc2626; font-size: 13px; margin-top: 6px;">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div style="margin-bottom: 16px;">
            <label for="password" style="display: block; font-weight: 700; color: #0f172a; margin-bottom: 8px;">New password</label>
            <input id="password" type="password" name="password" autocomplete="new-password" required style="width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 12px 14px;">
            @error('password')
                <div style="color: #dc2626; font-size: 13px; margin-top: 6px;">{{ $message }}</div>
            @enderror
        </div>

        <div style="margin-bottom: 22px;">
            <label for="password_confirmation" style="display: block; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required style="width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 12px 14px;">
        </div>

        <button type="submit" style="width: 100%; border: 0; border-radius: 8px; padding: 13px 18px; background: #0f5132; color: #fff; font-weight: 800; cursor: pointer;">
            Update password
        </button>
    </form>
</div>
@endsection
