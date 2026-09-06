<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Print Summary</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-slate-900 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-start border-b pb-4">
            <div>
                <h1 class="text-3xl font-bold">Expense Monthly Summary</h1>
                <p class="text-sm text-slate-500 mt-2">Month {{ sprintf('%02d', $month) }}/{{ $year }}</p>
            </div>
            <div class="text-sm text-slate-500">Generated {{ now()->format('d M Y h:i A') }}</div>
        </div>
        <div class="grid grid-cols-4 gap-4 my-6">
            <div class="border rounded-xl p-4"><div class="text-xs uppercase text-slate-500">Total Amount</div><div class="text-2xl font-bold mt-2">Rs {{ number_format($summary['total_amount'], 2) }}</div></div>
            <div class="border rounded-xl p-4"><div class="text-xs uppercase text-slate-500">Entries</div><div class="text-2xl font-bold mt-2">{{ $summary['entry_count'] }}</div></div>
            <div class="border rounded-xl p-4"><div class="text-xs uppercase text-slate-500">Average</div><div class="text-2xl font-bold mt-2">Rs {{ number_format($summary['average_amount'], 2) }}</div></div>
            <div class="border rounded-xl p-4"><div class="text-xs uppercase text-slate-500">Month</div><div class="text-2xl font-bold mt-2">{{ sprintf('%02d', $month) }}/{{ $year }}</div></div>
        </div>
        <div class="grid grid-cols-2 gap-6">
            <div>
                <h2 class="text-xl font-semibold mb-3">Company Totals</h2>
                @foreach($companyTotals as $row)
                    <div class="flex justify-between border rounded-lg p-3 mb-2"><span>{{ $row->company?->name }}</span><strong>Rs {{ number_format((float) $row->total_amount, 2) }}</strong></div>
                @endforeach
            </div>
            <div>
                <h2 class="text-xl font-semibold mb-3">Category Totals</h2>
                @foreach($categoryTotals as $row)
                    <div class="flex justify-between border rounded-lg p-3 mb-2"><span>{{ $row->category?->name }}</span><strong>Rs {{ number_format((float) $row->total_amount, 2) }}</strong></div>
                @endforeach
            </div>
        </div>
    </div>
    <script>window.print();</script>
</body>
</html>
