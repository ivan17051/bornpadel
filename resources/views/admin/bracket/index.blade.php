@extends('layouts.admin')

@section('title', 'Bracket Knockout')
@section('page-title', 'Bracket Knockout')

@section('breadcrumb')
    <li class="breadcrumb-item active">Bracket</li>
@endsection

@section('content')
@include('admin.partials.turnamen-filter', [
    'filterRoute' => route('admin.bracket.index'),
    'requireTurnamenSelection' => true,
    'turnamen' => $turnamen,
    'turnamenList' => $turnamenList,
])

@if ($turnamen)
    <x-tournament-bracket :bracket="$bracket" :turnamen="$turnamen" :refreshable="true" />
@else
    <div class="alert alert-light border text-center mb-0">
        <i class="bi bi-funnel text-muted d-block mb-2 fs-4"></i>
        Pilih turnamen untuk melihat bracket knockout.
    </div>
@endif
@endsection

@push('scripts')
@if ($turnamen)
<script src="{{ asset('public/js/bracket.js') }}"></script>
@endif
@endpush
