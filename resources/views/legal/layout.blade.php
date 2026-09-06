<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - {{ brand_name() }} Legal</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#111827;line-height:1.6}
        .wrap{max-width:900px;margin:0 auto;padding:40px 20px}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:32px;box-shadow:0 12px 35px rgba(15,23,42,.06)}
        h1{margin:0 0 8px;font-size:32px;line-height:1.2}
        h2{margin-top:28px;font-size:20px}
        p,li{font-size:15px;color:#374151}
        .muted{color:#6b7280;font-size:14px;margin-bottom:28px}
        a{color:#047857;text-decoration:none}
        a:hover{text-decoration:underline}
        .nav{display:flex;gap:14px;flex-wrap:wrap;margin-top:28px;padding-top:20px;border-top:1px solid #e5e7eb}
    </style>
</head>
<body>
    <main class="wrap">
        <section class="card">
            @yield('content')
            <div class="nav">
                <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
                <a href="{{ route('legal.terms') }}">Terms of Service</a>
                <a href="{{ route('legal.data-deletion') }}">Data Deletion</a>
            </div>
        </section>
    </main>
</body>
</html>
