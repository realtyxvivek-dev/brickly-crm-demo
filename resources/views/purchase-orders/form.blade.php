@extends(auth()->user()?->isAssistantSalesManager() || auth()->user()?->isSeniorManager() ? 'sales-manager.layout' : 'layouts.app')
@section('title', 'Purchase Request')
@section('page-title', 'Purchase Request')
@section('content')
@include('purchase-orders.partials.styles')
<div class="po-shell">
    <div class="po-card">
        <div class="po-head">
            <div>
                <h1 class="po-title">{{ $order->exists ? 'Edit Purchase Request' : 'New Purchase Request' }}</h1>
            </div>
            <a href="{{ route('purchase-orders.index') }}" class="po-btn po-btn-soft">Back</a>
        </div>
        @include('purchase-orders.partials.messages')
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
            @include('purchase-orders.partials.form-fields')
        </form>
    </div>
</div>
@endsection
