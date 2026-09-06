@extends('layouts.app')
@section('title', 'PO Approval Detail')
@section('content')
@include('purchase-orders.partials.styles')
<div class="po-shell">
    <div class="po-card">
        <div class="po-head">
            <div><h1 class="po-title">PO Approval Detail</h1><p class="po-sub">Review the request, then approve or reject it.</p></div>
            <a href="{{ route('admin.purchase-orders.index') }}" class="po-btn po-btn-soft">Back</a>
        </div>
        @include('purchase-orders.partials.messages')
    </div>
    @include('purchase-orders.partials.detail', ['order' => $order, 'context' => 'admin'])
</div>
@endsection
