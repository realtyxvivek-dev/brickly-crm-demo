@extends('finance-manager.layout')
@section('title', 'Purchase Orders - Finance')
@section('page_title', 'Purchase Orders')
@section('page_subtitle', 'Create PO requests, pay approved POs, and track receiving.')
@section('content')
@include('purchase-orders.partials.styles')
<div class="po-shell">
    <div class="po-card">
        <div class="po-head">
            <div>
                <h2 class="po-title">PO Workspace</h2>
                <p class="po-sub">{{ $paymentQueueCount }} PO payment pending.</p>
            </div>
            <a href="{{ route('finance-manager.purchase-orders.create') }}" class="po-btn po-btn-primary">New PO Request</a>
        </div>
        @include('purchase-orders.partials.messages')
        <form method="GET" class="po-actions">
            <select name="status" class="po-btn po-btn-soft" onchange="this.form.submit()">
                <option value="all" @selected($status === 'all')>All Status</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>
    @include('purchase-orders.partials.list', ['orders' => $orders, 'routePrefix' => 'finance-manager.purchase-orders'])
</div>
@endsection
