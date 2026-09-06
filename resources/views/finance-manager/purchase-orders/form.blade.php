@extends('finance-manager.layout')
@section('title', 'Purchase Request - Finance')
@section('page_title', $order->exists ? 'Edit Purchase Request' : 'New Purchase Request')
@section('page_subtitle', 'Create a simple PO request and submit it for Admin approval.')
@section('content')
@include('purchase-orders.partials.styles')
<div class="po-shell">
    <div class="po-card">
        <div class="po-head">
            <div><h2 class="po-title">{{ $order->exists ? 'Edit Request' : 'Create Request' }}</h2><p class="po-sub">Save as draft or submit directly for approval.</p></div>
            <a href="{{ route('finance-manager.purchase-orders.index') }}" class="po-btn po-btn-soft">Back</a>
        </div>
        @include('purchase-orders.partials.messages')
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
            @include('purchase-orders.partials.form-fields')
        </form>
    </div>
</div>
@endsection
