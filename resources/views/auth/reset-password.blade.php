<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password - {{ brand_name() }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; background: linear-gradient(135deg, #063A1C 0%, #205A44 50%, #0f4a2e 100%); display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #fff; border-radius: 20px; padding: 48px 44px; width: 100%; max-width: 440px; box-shadow: 0 25px 60px rgba(0,0,0,0.25); }
        .logo-section { text-align: center; margin-bottom: 32px; }
        .logo-icon { width: 56px; height: 56px; background: linear-gradient(135deg, #063A1C, #205A44); border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 700; margin-bottom: 12px; }
        .logo-text { font-size: 18px; font-weight: 700; color: #1a202c; }
        h2 { font-size: 22px; font-weight: 700; color: #1a202c; margin-bottom: 8px; text-align: center; }
        .subtitle { font-size: 14px; color: #718096; text-align: center; margin-bottom: 32px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .input-wrapper { position: relative; }
        .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 15px; }
        .toggle-btn { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #9ca3af; font-size: 15px; padding: 0; }
        input[type=password], input[type=text] { width: 100%; padding: 13px 42px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-family: 'Inter', sans-serif; color: #1a202c; transition: border-color 0.2s; outline: none; }
        input:focus { border-color: #205A44; }
        .strength-bar { height: 4px; border-radius: 2px; background: #e5e7eb; margin-top: 8px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 2px; width: 0%; transition: width 0.3s, background 0.3s; }
        .strength-text { font-size: 11px; color: #9ca3af; margin-top: 4px; }
        .btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #063A1C, #205A44); color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: opacity 0.2s; }
        .btn:hover { opacity: 0.92; }
        .alert-error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .requirements { font-size: 12px; color: #9ca3af; margin-top: 6px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-section">
            <div class="logo-icon">B</div>
            <div class="logo-text">{{ brand_name() }}</div>
        </div>

        <h2>Set New Password</h2>
        <p class="subtitle">Choose a strong password for your account.</p>

        @if($errors->any())
            <div class="alert-error">
                @foreach($errors->all() as $error)
                    <div><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i> {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="password">New Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password" name="password" required minlength="8" placeholder="Enter new password" oninput="checkStrength(this.value)">
                    <button type="button" class="toggle-btn" onclick="togglePass('password', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-text" id="strengthText"></div>
                <div class="requirements">Minimum 8 characters</div>
            </div>

            <div class="form-group">
                <label for="password_confirmation">Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="Re-enter new password">
                    <button type="button" class="toggle-btn" onclick="togglePass('password_confirmation', this)">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-shield-alt" style="margin-right:8px;"></i> Reset Password
            </button>
        </form>
    </div>

    <script>
        function togglePass(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function checkStrength(val) {
            const fill = document.getElementById('strengthFill');
            const text = document.getElementById('strengthText');
            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const levels = [
                { w: '0%', bg: '#e5e7eb', label: '' },
                { w: '25%', bg: '#ef4444', label: 'Weak' },
                { w: '50%', bg: '#f59e0b', label: 'Fair' },
                { w: '75%', bg: '#3b82f6', label: 'Good' },
                { w: '100%', bg: '#16a34a', label: 'Strong' },
            ];
            const lvl = val.length === 0 ? 0 : score;
            fill.style.width = levels[lvl].w;
            fill.style.background = levels[lvl].bg;
            text.textContent = levels[lvl].label;
            text.style.color = levels[lvl].bg;
        }
    </script>
</body>
</html>
