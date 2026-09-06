@php
    $label = \App\Models\PurchaseOrder::statuses()[$status] ?? ucfirst(str_replace('_', ' ', $status));
    $bad = in_array($status, ['rejected', 'cancelled', 'deleted'], true);
    $warn = in_array($status, ['draft', 'submitted', 'payment_pending', 'paid', 'delete_requested'], true);
@endphp
<span class="po-chip {{ $bad ? 'po-chip-bad' : ($warn ? 'po-chip-warn' : '') }}">{{ $label }}</span>
