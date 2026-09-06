@extends(auth()->user()?->isAssistantSalesManager() || auth()->user()?->isSeniorManager() ? 'sales-manager.layout' : 'layouts.app')
@section('title', 'Purchase Request Detail')
@section('page-title', 'Purchase Request')
@section('content')
@include('purchase-orders.partials.styles')
<div class="po-shell">
    <div class="po-card">
        <div class="po-head">
            <div><h1 class="po-title">Purchase Request Detail</h1><p class="po-sub">Request, approval, and payment status.</p></div>
            <div class="po-actions">
                <a class="po-btn po-btn-soft" href="{{ route('purchase-orders.index') }}">Back</a>
                @if($order->isEditableByCreator())
                    <a class="po-btn po-btn-primary" href="{{ route('purchase-orders.edit', $order) }}">Edit</a>
                @endif
            </div>
        </div>
        @include('purchase-orders.partials.messages')
    </div>
    @include('purchase-orders.partials.detail', ['order' => $order, 'context' => 'user'])
</div>
@endsection
