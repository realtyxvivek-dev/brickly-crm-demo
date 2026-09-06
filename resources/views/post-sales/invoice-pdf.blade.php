<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 16mm 12mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 10px; line-height: 1.35; }
        .invoice { width: 100%; }
        .invoice-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .invoice-table td, .invoice-table th { border: 1px solid #111; padding: 6px 7px; vertical-align: top; }
        .invoice-title { background: #d9ead3; font-size: 18px; font-weight: 700; letter-spacing: .4px; padding: 9px !important; text-align: center; }
        .section-title { font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .label { color: #333; font-size: 9px; font-weight: 700; text-transform: uppercase; }
        .value { font-size: 10px; font-weight: 700; }
        .muted { color: #555; }
        .meta-cell { width: 25%; }
        .details-cell { width: 50%; }
        .header-row th { background: #e2f0d9; font-size: 10px; font-weight: 700; text-align: center; vertical-align: middle; }
        .center { text-align: center; }
        .right { text-align: right; }
        .amount { text-align: right; white-space: nowrap; }
        .total-label { font-weight: 700; text-align: right; }
        .grand-total td { background: #d9ead3; font-size: 11px; font-weight: 700; }
        .signature { height: 66px; text-align: center; vertical-align: bottom !important; }
        .signature strong { display: block; margin-bottom: 28px; }
        .notes { min-height: 40px; }
    </style>
</head>
<body>
@php
    $cleanGstin = static fn ($value) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    $panFromGstin = static function ($gstin) use ($cleanGstin) {
        $gstin = $cleanGstin($gstin);
        return strlen($gstin) >= 12 ? substr($gstin, 2, 10) : null;
    };
    $projectName = $invoice->project?->name ?: optional($invoice->items->first()?->postSaleCase)->project_name;
    $gstAmount = (float) $invoice->gst_amount;
    $cgstAmount = round($gstAmount / 2, 2);
    $sgstAmount = round($gstAmount - $cgstAmount, 2);
    $numberToWords = static function ($amount) {
        $ones = [0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'];
        $tens = [2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty', 6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'];
        $toWords = static function ($number) use (&$toWords, $ones, $tens) {
            if ($number < 20) return $ones[$number];
            if ($number < 100) return $tens[(int) ($number / 10)] . ($number % 10 ? ' ' . $ones[$number % 10] : '');
            if ($number < 1000) return $ones[(int) ($number / 100)] . ' Hundred' . ($number % 100 ? ' ' . $toWords($number % 100) : '');
            if ($number < 100000) return $toWords((int) ($number / 1000)) . ' Thousand' . ($number % 1000 ? ' ' . $toWords($number % 1000) : '');
            if ($number < 10000000) return $toWords((int) ($number / 100000)) . ' Lakh' . ($number % 100000 ? ' ' . $toWords($number % 100000) : '');
            return $toWords((int) ($number / 10000000)) . ' Crore' . ($number % 10000000 ? ' ' . $toWords($number % 10000000) : '');
        };
        return $toWords(max(0, (int) round($amount))) . ' Rupees Only';
    };
@endphp

<div class="invoice">
    <table class="invoice-table">
        <tr><td colspan="4" class="invoice-title">TAX INVOICE</td></tr>
        <tr>
            <td colspan="2" rowspan="4" class="details-cell">
                <div class="section-title">Broker Name</div>
                <div class="value">{{ $invoice->seller_name ?: '-' }}</div>
                @if($invoice->seller_address)<div class="muted">{!! nl2br(e($invoice->seller_address)) !!}</div>@endif
            </td>
            <td class="label meta-cell">Date</td><td class="value meta-cell">{{ optional($invoice->invoice_date)->format('d-m-Y') ?: '-' }}</td>
        </tr>
        <tr><td class="label">Invoice No.</td><td class="value">{{ $invoice->invoice_number ?: '-' }}</td></tr>
        <tr><td class="label">PAN</td><td class="value">{{ $panFromGstin($invoice->seller_gstin) ?: '-' }}</td></tr>
        <tr><td class="label">GSTIN</td><td class="value">{{ $invoice->seller_gstin ?: '-' }}</td></tr>
        <tr>
            <td colspan="2" rowspan="4" class="details-cell">
                <div class="section-title">Developer Name and Address</div>
                <div class="value">{{ $invoice->buyer_name ?: '-' }}</div>
                @if($invoice->buyer_address)<div class="muted">{!! nl2br(e($invoice->buyer_address)) !!}</div>@endif
            </td>
            <td colspan="2" class="section-title">Bank Details</td>
        </tr>
        <tr><td colspan="2"><span class="label">Beneficiary Name:</span> <span class="value">{{ $invoice->seller_name ?: '-' }}</span></td></tr>
        <tr><td colspan="2"><span class="label">Beneficiary Account No:</span> <span class="value">{{ $invoice->bank_account ?: '-' }}</span></td></tr>
        <tr><td colspan="2"><span class="label">Beneficiary Bank / IFSC:</span> <span class="value">{{ $invoice->bank_name ?: '-' }}{{ $invoice->bank_ifsc ? ' / ' . $invoice->bank_ifsc : '' }}</span></td></tr>
        <tr>
            <td colspan="2"><span class="label">Project:</span> <span class="value">{{ $projectName ?: '-' }}</span></td>
            <td class="label">PAN</td><td class="value">{{ $panFromGstin($invoice->buyer_gstin) ?: '-' }}</td>
        </tr>
        <tr><td colspan="2">&nbsp;</td><td class="label">GSTIN</td><td class="value">{{ $invoice->buyer_gstin ?: '-' }}</td></tr>
    </table>

    <table class="invoice-table" style="margin-top: -1px;">
        <thead><tr class="header-row"><th style="width:8%;">S.No.</th><th style="width:42%;">Name of the Customer</th><th style="width:25%;">Unit No.</th><th style="width:25%;">Commission Amount<br>(Rs.)</th></tr></thead>
        <tbody>
            @forelse($invoice->items as $item)
                <tr><td class="center">{{ $loop->iteration }}</td><td><strong>{{ $item->postSaleCase?->customer_name ?: $item->description ?: '-' }}</strong></td><td>{{ $item->postSaleCase?->unit_label ?: '-' }}</td><td class="amount">{{ number_format((float) $item->amount, 2) }}</td></tr>
            @empty
                <tr><td class="center">1</td><td>-</td><td>-</td><td class="amount">0.00</td></tr>
            @endforelse
            <tr><td colspan="3" class="total-label">Total</td><td class="amount">{{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
            <tr><td colspan="3" class="total-label">CGST 9%</td><td class="amount">{{ number_format($cgstAmount, 2) }}</td></tr>
            <tr><td colspan="3" class="total-label">SGST 9%</td><td class="amount">{{ number_format($sgstAmount, 2) }}</td></tr>
            <tr><td colspan="3" class="total-label">IGST 18%</td><td class="amount">0.00</td></tr>
            @if((float) $invoice->tds_amount > 0)<tr><td colspan="3" class="total-label">Less: TDS</td><td class="amount">- {{ number_format((float) $invoice->tds_amount, 2) }}</td></tr>@endif
            <tr class="grand-total"><td colspan="3" class="total-label">Total Amount</td><td class="amount">{{ number_format((float) $invoice->net_receivable, 2) }}</td></tr>
            <tr><td colspan="4"><strong>Amount Chargeable (in Words):</strong> {{ $numberToWords($invoice->net_receivable) }}</td></tr>
            <tr><td colspan="4" class="notes"><strong>Remarks:</strong> {{ $invoice->notes ?: 'Commission invoice against the verified builder receivable.' }}</td></tr>
            <tr><td colspan="2"><strong>Terms:</strong> {{ $invoice->terms ?: '-' }}</td><td colspan="2" class="signature"><strong>For {{ $invoice->seller_name ?: 'Broker' }}</strong>(Authorized Signatory / Seal)</td></tr>
        </tbody>
    </table>
</div>
</body>
</html>
