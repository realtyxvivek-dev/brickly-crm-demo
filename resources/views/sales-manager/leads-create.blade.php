@extends('sales-manager.layout')

@section('title', 'Add Lead')
@section('page-title', 'Add Lead')

@section('content')
    @include('leads.partials.create-requirement-style', [
        'formAction' => route('sales-manager.leads.store'),
        'duplicateCheckUrl' => route('sales-manager.leads.check-duplicate'),
        'cancelUrl' => route('sales-manager.leads'),
        'submitLabel' => 'Create Lead',
        'pageTitle' => 'Add Lead',
        'pageSubtitle' => 'Create lead for yourself or direct team. Calling task will be assigned automatically.',
        'defaultAssignedTo' => auth()->id(),
        'ownerPlaceholder' => 'Select owner',
    ])
@endsection
