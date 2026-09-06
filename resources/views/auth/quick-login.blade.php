<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Quick Login | Brickly CRM Demo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: Inter, sans-serif; color: #0b2f25; background: #f8f4e9; }
        .topbar { height: 7px; background: linear-gradient(90deg, #063d2f, #0f8a61, #5ed39a); }
        .shell { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 44px 0 60px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 32px; }
        .brand { display: flex; align-items: center; gap: 13px; margin-bottom: 24px; font-weight: 800; font-size: 21px; }
        .mark { width: 42px; height: 42px; border-radius: 13px; background: linear-gradient(145deg, #064b39, #19a470); display: grid; place-items: center; color: white; box-shadow: 0 10px 28px rgba(6,75,57,.2); }
        h1 { margin: 0; font-size: clamp(32px, 5vw, 54px); letter-spacing: -2.5px; line-height: 1.02; }
        .lead { margin: 14px 0 0; max-width: 650px; color: #527067; font-size: 16px; line-height: 1.65; }
        .secure { display: inline-flex; align-items: center; gap: 8px; color: #0b6b4e; background: #e1f4e8; border: 1px solid #bee4ce; padding: 10px 14px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 15px; }
        .card { background: rgba(255,255,255,.78); border: 1px solid #dfe5dd; border-radius: 19px; padding: 18px; display: flex; align-items: center; gap: 15px; box-shadow: 0 8px 25px rgba(22,58,46,.05); transition: transform .18s, box-shadow .18s, border-color .18s; }
        .card:hover { transform: translateY(-3px); border-color: #77bd9d; box-shadow: 0 15px 36px rgba(22,58,46,.1); }
        .icon { flex: 0 0 48px; height: 48px; display: grid; place-items: center; border-radius: 14px; color: white; background: linear-gradient(145deg, #073e31, #14966a); }
        .copy { min-width: 0; flex: 1; }
        h2 { margin: 0 0 5px; font-size: 15px; }
        .desc { margin: 0; color: #668078; font-size: 11px; line-height: 1.4; }
        form { margin: 0; }
        button { width: 44px; height: 44px; border: 0; border-radius: 12px; color: #fff; background: #0b503d; cursor: pointer; transition: background .18s; }
        button:hover { background: #0f805c; }
        button:disabled { background: #b7c1bd; cursor: not-allowed; }
        button:focus-visible, footer a:focus-visible { outline: 3px solid #38b982; outline-offset: 3px; }
        footer { margin-top: 28px; display: flex; justify-content: space-between; align-items: center; color: #678078; font-size: 12px; }
        footer a { color: #0b6b4e; font-weight: 700; text-decoration: none; }
        @media (max-width: 900px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 620px) { header { display: block; } .secure { margin-top: 20px; } .grid { grid-template-columns: 1fr; } .shell { padding-top: 25px; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; } }
    </style>
</head>
<body>
<div class="topbar"></div>
<main class="shell">
    <header>
        <div>
            <div class="brand"><span class="mark"><i class="fa-solid fa-layer-group"></i></span> Brickly CRM</div>
            <h1>Choose a workspace</h1>
            <p class="lead">Explore the complete real-estate operating system from each team member's point of view.</p>
        </div>
        <span class="secure"><i class="fa-solid fa-circle-check"></i> Demo environment</span>
    </header>

    <section class="grid">
        @foreach ($roles as $slug => [$label, $description, $icon])
            @php($user = $users->get($slug))
            <article class="card">
                <span class="icon"><i class="fa-solid {{ $icon }}"></i></span>
                <div class="copy"><h2>{{ $label }}</h2><p class="desc">{{ $description }}</p></div>
                <form method="POST" action="{{ route('demo.quick-login.login', $slug) }}">
                    @csrf
                    <button type="submit" title="Login as {{ $label }}" aria-label="Login as {{ $label }}" @disabled(!$user)><i class="fa-solid fa-arrow-right"></i></button>
                </form>
            </article>
        @endforeach
    </section>

    <footer><span>Dummy data only · Brickly CRM</span><a href="{{ route('login') }}">Standard login</a></footer>
</main>
</body>
</html>
