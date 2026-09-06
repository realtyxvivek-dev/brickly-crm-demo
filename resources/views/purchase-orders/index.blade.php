@extends(auth()->user()?->isAssistantSalesManager() || auth()->user()?->isSeniorManager() ? 'sales-manager.layout' : 'layouts.app')
@section('title', 'My Purchase Requests')
@section('page-title', 'Purchase Requests')
@section('content')
@include('purchase-orders.partials.styles')
<div class="po-shell">
    <div class="po-card">
        <div class="po-head">
            <div>
                <h1 class="po-title">My Purchase Requests</h1>
            </div>
            <a href="{{ route('purchase-orders.create') }}" class="po-btn po-btn-primary">New PO Request</a>
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
    @include('purchase-orders.partials.list', ['orders' => $orders, 'routePrefix' => 'purchase-orders'])
</div>
@endsection
