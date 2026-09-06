@extends('finance-manager.layout')
@section('title', 'PO Detail - Finance')
@section('page_title', 'Purchase Order Detail')
@section('page_subtitle', 'Complete approved PO payment here. Expense entry is created automatically after payment.')
@section('content')
@include('purchase-orders.partials.styles')
<div class="po-shell">
    <div class="po-card">
        <div class="po-head">
            <div><h2 class="po-title">PO Detail</h2><p class="po-sub">Finance payment workspace.</p></div>
            <div class="po-actions">
                <a href="{{ route('finance-manager.purchase-orders.index') }}" class="po-btn po-btn-soft">Back</a>
                @if($order->isEditableByFinanceManager())
                    <a href="{{ route('finance-manager.purchase-orders.edit', $order) }}" class="po-btn po-btn-primary">Edit</a>
                @endif
            </div>
        </div>
        @include('purchase-orders.partials.messages')
    </div>
    @include('purchase-orders.partials.detail', ['order' => $order, 'context' => 'finance'])
</div>
@endsection
