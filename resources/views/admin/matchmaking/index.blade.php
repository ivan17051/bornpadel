@extends('layouts.admin')

@section('title', 'Matchmaking Grup')
@section('page-title', 'Matchmaking Grup')

@section('breadcrumb')
    <li class="breadcrumb-item active">Matchmaking</li>
@endsection

@section('content')
@include('admin.partials.turnamen-filter', [
    'filterRoute' => route('admin.matchmaking.index'),
    'requireTurnamenSelection' => true,
])

@include('admin.matchmaking.partials.workspace')
@endsection

@if ($turnamen && ($isFriendly ?? false) && ($groupsEditable ?? false))
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/css/select2-bootstrap-5-theme.min.css" crossorigin="anonymous">
<style>
    #friendlyAssignModal .select2-container {
        width: 100% !important;
    }
</style>
@endpush
@endif

@push('scripts')
@if ($turnamen && ($isFriendly ?? false) && ($groupsEditable ?? false))
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" crossorigin="anonymous"></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initMatchmakingActions();
    @if ($turnamen && ! ($isMahjong ?? false))
        BornPadelAdmin.initScoreModal();
    @endif
});
</script>
@endpush
