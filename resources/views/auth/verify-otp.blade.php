<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verify OTP - {{ brand_name() }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; background: linear-gradient(135deg, #063A1C 0%, #205A44 50%, #0f4a2e 100%); display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #fff; border-radius: 20px; padding: 48px 44px; width: 100%; max-width: 440px; box-shadow: 0 25px 60px rgba(0,0,0,0.25); }
        .logo-section { text-align: center; margin-bottom: 32px; }
        .logo-icon { width: 56px; height: 56px; background: linear-gradient(135deg, #063A1C, #205A44); border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 700; margin-bottom: 12px; }
        .logo-text { font-size: 18px; font-weight: 700; color: #1a202c; }
        .email-badge { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 10px 16px; text-align: center; font-size: 13px; color: #166534; margin-bottom: 28px; font-weight: 500; }
        h2 { font-size: 22px; font-weight: 700; color: #1a202c; margin-bottom: 8px; text-align: center; }
        .subtitle { font-size: 14px; color: #718096; text-align: center; margin-bottom: 28px; line-height: 1.6; }
        .otp-inputs { display: flex; gap: 10px; justify-content: center; margin-bottom: 28px; }
        .otp-inputs input { width: 52px; height: 58px; text-align: center; font-size: 22px; font-weight: 700; border: 2px solid #e5e7eb; border-radius: 10px; color: #1a202c; font-family: 'Inter', sans-serif; outline: none; transition: border-color 0.2s; }
        .otp-inputs input:focus { border-color: #205A44; background: #f0fdf4; }
        .btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #063A1C, #205A44); color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: opacity 0.2s; }
        .btn:hover { opacity: 0.92; }
        .resend-section { text-align: center; margin-top: 20px; font-size: 14px; color: #718096; }
        .resend-section a { color: #205A44; text-decoration: none; font-weight: 600; }
        .back-link { text-align: center; margin-top: 12px; font-size: 14px; }
        .back-link a { color: #9ca3af; text-decoration: none; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        #timer { color: #205A44; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-section">
            <div class="logo-icon">B</div>
            <div class="logo-text">{{ brand_name() }}</div>
        </div>

        <h2>Enter OTP</h2>
        <p class="subtitle">We've sent a 6-digit OTP to your email address. Please enter it below.</p>

        <div class="email-badge">
            <i class="fas fa-envelope" style="margin-right:6px;"></i> {{ $email }}
        </div>

        @if(session('success'))
            <div class="alert-success"><i class="fas fa-check-circle" style="margin-right:6px;"></i> {{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert-error">
                @foreach($errors->all() as $error)
                    <div><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i> {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.verify-otp') }}" id="otpForm">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <input type="hidden" name="otp" id="otpHidden">

            <div class="otp-inputs">
                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]">
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-check-circle" style="margin-right:8px;"></i> Verify OTP
            </button>
        </form>

        <div class="resend-section" style="margin-top:20px;">
            <span id="timerText">Resend OTP in <span id="timer">10:00</span></span>
            <span id="resendLink" style="display:none;">
                Didn't receive OTP?
                <form method="POST" action="{{ route('password.resend-otp') }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <button type="submit" style="background:none;border:none;color:#205A44;font-weight:600;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;">Resend OTP</button>
                </form>
            </span>
        </div>

        <div class="back-link">
            <a href="{{ route('password.forgot') }}"><i class="fas fa-arrow-left" style="margin-right:4px;"></i> Back</a>
        </div>
    </div>

    <script>
        // OTP digit navigation
        const digits = document.querySelectorAll('.otp-digit');
        digits.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const val = e.target.value.replace(/\D/g, '');
                e.target.value = val ? val[0] : '';
                if (val && index < digits.length - 1) {
                    digits[index + 1].focus();
                }
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    digits[index - 1].focus();
                }
            });
            input.addEventListener('paste', (e) => {
                const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                digits.forEach((d, i) => { d.value = paste[i] || ''; });
                e.preventDefault();
            });
        });

        // On submit, combine digits into hidden input
        document.getElementById('otpForm').addEventListener('submit', (e) => {
            const otp = Array.from(digits).map(d => d.value).join('');
            document.getElementById('otpHidden').value = otp;
        });

        // Countdown timer (10 minutes)
        let seconds = 600;
        const timerEl = document.getElementById('timer');
        const timerText = document.getElementById('timerText');
        const resendLink = document.getElementById('resendLink');

        const countdown = setInterval(() => {
            seconds--;
            const m = Math.floor(seconds / 60).toString().padStart(2, '0');
            const s = (seconds % 60).toString().padStart(2, '0');
            timerEl.textContent = `${m}:${s}`;
            if (seconds <= 0) {
                clearInterval(countdown);
                timerText.style.display = 'none';
                resendLink.style.display = 'inline';
            }
        }, 1000);
    </script>
</body>
</html>
