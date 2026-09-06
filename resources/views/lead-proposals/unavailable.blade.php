<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proposal unavailable - {{ brand_name() }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-xl items-center px-5 py-12">
        <section class="w-full rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-700">
                <i class="fas fa-link-slash"></i>
            </div>
            <h1 class="text-2xl font-bold">Proposal unavailable</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $message ?? 'This proposal is no longer active.' }}</p>
            <p class="mt-5 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Please contact your advisor for a fresh link.</p>
        </section>
    </main>
</body>
</html>
