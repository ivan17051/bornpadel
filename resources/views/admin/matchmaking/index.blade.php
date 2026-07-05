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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initMatchmakingActions();
    @if ($turnamen && ! ($isMahjong ?? false))
        BornPadelAdmin.initScoreModal();
    @endif
});
</script>
@endpush
