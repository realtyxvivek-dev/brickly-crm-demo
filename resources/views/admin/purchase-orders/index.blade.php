@extends('layouts.app')
@section('title', 'PO Approvals')
@section('content')
@include('purchase-orders.partials.styles')
<div class="po-shell">
    <div class="po-card">
        <div class="po-head">
            <div>
                <h1 class="po-title">PO Approval Queue</h1>
                <p class="po-sub">Approve or reject submitted purchase requests.</p>
            </div>
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
    @include('purchase-orders.partials.list', ['orders' => $orders, 'routePrefix' => 'admin.purchase-orders'])
</div>
@endsection
