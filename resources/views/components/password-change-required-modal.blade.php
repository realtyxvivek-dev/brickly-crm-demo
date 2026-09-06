@auth
    @if(auth()->user()->mustChangePassword() && !request()->routeIs('account.password.*'))
        <div style="position: fixed; inset: 0; z-index: 2147483000; background: rgba(15, 23, 42, 0.68); display: flex; align-items: center; justify-content: center; padding: 18px;">
            <div role="dialog" aria-modal="true" aria-labelledby="passwordChangeRequiredTitle" style="width: min(460px, 100%); background: #fff; border-radius: 12px; padding: 26px; box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: #dcfce7; color: #0f5132; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                    <i class="fas fa-key" aria-hidden="true"></i>
                </div>
                <h2 id="passwordChangeRequiredTitle" style="margin: 0 0 10px; font-size: 22px; color: #0f172a;">Change your password</h2>
                <p style="margin: 0 0 22px; color: #475569; line-height: 1.55;">
                    Admin has requested a CRM password update for your account. You do not need to enter your current password.
                </p>
                <a href="{{ route('account.password.edit') }}" style="display: block; text-align: center; background: #0f5132; color: #fff; text-decoration: none; border-radius: 8px; padding: 13px 16px; font-weight: 800;">
                    Change password now
                </a>
            </div>
        </div>
    @endif
@endauth
