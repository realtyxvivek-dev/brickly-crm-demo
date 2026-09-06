@extends('layouts.app')

@section('title', 'Add Lead - ' . brand_name())
@section('page-title', 'Add Lead')
@section('page-subtitle', '')

@section('content')
    @include('leads.partials.create-requirement-style', [
        'formAction' => route('leads.store'),
        'duplicateCheckUrl' => route('leads.manual-check-duplicate'),
        'cancelUrl' => route('leads.index'),
        'submitLabel' => 'Create Lead',
        'pageTitle' => 'Add Lead',
        'pageSubtitle' => '',
    ])
@endsection
