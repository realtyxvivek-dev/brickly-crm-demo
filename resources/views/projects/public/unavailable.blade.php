<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Link Unavailable</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Inter, system-ui, sans-serif;
            background: linear-gradient(180deg, #f7f4ef 0%, #edf3ee 100%);
            color: #163022;
            padding: 24px;
        }
        .card {
            max-width: 520px;
            padding: 32px;
            border-radius: 28px;
            background: rgba(255,255,255,0.86);
            border: 1px solid rgba(32,90,68,0.12);
            text-align: center;
            box-shadow: 0 24px 50px rgba(6,58,28,0.08);
        }
        h1 { margin: 0 0 12px; }
        p { margin: 0; color: #5f7468; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Link Unavailable</h1>
        <p>{{ $message ?? 'This project page is not available right now.' }}</p>
    </div>
</body>
</html>
