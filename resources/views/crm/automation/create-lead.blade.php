@extends('layouts.app')

@section('title', 'Create Lead - ' . brand_name())
@section('page-title', 'Create Lead')
@section('page-subtitle', '')

@section('content')
    @include('leads.partials.create-requirement-style', [
        'formAction' => route('crm.automation.leads.store'),
        'duplicateCheckUrl' => route('crm.automation.leads.check-duplicate'),
        'cancelUrl' => route('leads.index'),
        'submitLabel' => 'Create Lead',
        'pageTitle' => 'Create Lead',
        'pageSubtitle' => '',
    ])
@endsection
