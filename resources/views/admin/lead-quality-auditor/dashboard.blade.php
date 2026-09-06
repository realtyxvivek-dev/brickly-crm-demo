@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<main class="auditor-dashboard" aria-labelledby="auditorDashboardTitle">
    @include('admin.lead-quality-auditor.dashboard-workspace')
</main>

<style>
    .auditor-dashboard {
        min-height: calc(100vh - 92px);
        padding: 16px;
        background: #f4faf6;
    }
    .auditor-dashboard .lad-toolbar h1 {
        margin: 0;
        color: #073f2d;
        font-size: 22px;
        letter-spacing: 0;
    }
    @media (max-width: 768px) {
        .auditor-dashboard { padding: 10px; }
        .auditor-dashboard-workspace { min-height: calc(100vh - 165px); background-size: 110px 40px; }
    }
</style>
@endsection
