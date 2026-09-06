<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password - {{ brand_name() }}</title>
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
        .subtitle { font-size: 14px; color: #718096; text-align: center; margin-bottom: 32px; line-height: 1.6; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; }
        .input-wrapper { position: relative; }
        .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 15px; }
        input[type=email] { width: 100%; padding: 13px 14px 13px 42px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 14px; font-family: 'Inter', sans-serif; color: #1a202c; transition: border-color 0.2s; outline: none; }
        input[type=email]:focus { border-color: #205A44; }
        .btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #063A1C, #205A44); color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: opacity 0.2s; }
        .btn:hover { opacity: 0.92; }
        .back-link { text-align: center; margin-top: 20px; font-size: 14px; color: #718096; }
        .back-link a { color: #205A44; text-decoration: none; font-weight: 600; }
        .alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .alert-error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-section">
            <div class="logo-icon">B</div>
            <div class="logo-text">{{ brand_name() }}</div>
        </div>

        <h2>Forgot Password?</h2>
        <p class="subtitle">Enter your registered email address and we'll send you a 6-digit OTP to reset your password.</p>

        @if(session('success'))
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert-error">
                @foreach($errors->all() as $error)
                    <div><i class="fas fa-exclamation-circle"></i> {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.send-otp') }}">
            @csrf
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus placeholder="Enter your email address">
                </div>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-paper-plane" style="margin-right:8px;"></i> Send OTP
            </button>
        </form>

        <div class="back-link">
            <a href="{{ route('login') }}"><i class="fas fa-arrow-left" style="margin-right:4px;"></i> Back to Login</a>
        </div>
    </div>
</body>
</html>
