<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Under Maintenance</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .container {
            text-align: center;
            padding: 60px 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }
        
        .icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }
        
        h1 {
            font-size: 42px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        p {
            font-size: 18px;
            margin-bottom: 15px;
            line-height: 1.6;
            opacity: 0.95;
        }
        
        .message {
            font-size: 16px;
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            border-left: 4px solid white;
        }
        
        .footer {
            font-size: 14px;
            margin-top: 40px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>System Under Maintenance</h1>
        <p>We're currently performing scheduled maintenance to improve your experience.</p>
        <div class="message">
            {{ $message ?? 'We are currently performing scheduled maintenance. Please check back soon.' }}
        </div>
        <div class="footer">
            Thank you for your patience.<br>
            We'll be back shortly.
        </div>
    </div>
</body>
</html>
