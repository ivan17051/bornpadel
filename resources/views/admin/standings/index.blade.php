@extends('layouts.admin')

@section('title', 'Klasemen')
@section('page-title', 'Klasemen')

@section('breadcrumb')
    <li class="breadcrumb-item active">Klasemen</li>
@endsection

@section('content')
@include('admin.partials.turnamen-filter', [
    'filterRoute' => route('admin.standings.index'),
    'requireTurnamenSelection' => true,
    'turnamen' => $turnamen,
    'turnamenList' => $turnamenList,
])

@if ($turnamen)
    @include('admin.standings.partials.standings-panel')
@else
    <div class="alert alert-light border text-center mb-0">
        <i class="bi bi-funnel text-muted d-block mb-2 fs-4"></i>
        Pilih turnamen untuk melihat klasemen grup.
    </div>
@endif
@endsection

@push('scripts')
@if ($turnamen && ! $turnamen->isMahjong() && ! $turnamen->isFriendly())
<script src="{{ asset('public/js/group-stage-history.js') }}"></script>
@endif
@if ($turnamen)
<script src="{{ asset('public/js/leaderboard.js') }}"></script>
@endif
@endpush
