@extends('layouts.admin')

@section('title', 'Kelola Turnamen')
@section('page-title', 'Kelola Turnamen')

@section('breadcrumb')
    <li class="breadcrumb-item active">Kelola Turnamen</li>
@endsection

@section('sweetalert-flash', true)

@section('content')
@include('admin.partials.turnamen-filter', [
    'filterRoute' => $filterRoute,
    'turnamen' => $turnamen,
    'turnamenList' => $turnamenList,
    'sweetAlert' => true,
    'requireTurnamenSelection' => true,
    'preserveParams' => ['tab' => $activeTab],
    'useSelect2TurnamenFilter' => true,
    'turnamenFilterMax' => 5,
])

@if ($turnamen)
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $activeTab === 'pemain' ? 'active' : '' }}"
               href="{{ route('admin.turnamen-operasi.index', array_filter(['id_turnamen' => $turnamen->id, 'tab' => 'pemain', 'search' => request('search'), 'status' => request('status'), 'sort' => request('sort'), 'dir' => request('dir')])) }}">
                <i class="bi bi-people me-1"></i> Pemain Terdaftar
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $activeTab === 'matchmaking' ? 'active' : '' }}"
               href="{{ route('admin.turnamen-operasi.index', ['id_turnamen' => $turnamen->id, 'tab' => 'matchmaking']) }}">
                <i class="bi bi-shuffle me-1"></i> Matchmaking & Skor
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $activeTab === 'klasemen' ? 'active' : '' }}"
               href="{{ route('admin.turnamen-operasi.index', ['id_turnamen' => $turnamen->id, 'tab' => 'klasemen']) }}">
                <i class="bi bi-bar-chart-steps me-1"></i> Klasemen
            </a>
        </li>
        @if (! $turnamen->isMahjong())
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === 'bracket' ? 'active' : '' }}"
                   href="{{ route('admin.turnamen-operasi.index', ['id_turnamen' => $turnamen->id, 'tab' => 'bracket']) }}">
                    <i class="bi bi-diagram-2 me-1"></i> Bracket
                </a>
            </li>
        @endif
    </ul>

    <div class="tab-content">
        @if ($activeTab === 'pemain')
            @include('admin.pemain.partials.registered-panel', [
                'filterRoute' => $filterRoute,
                'preserveTab' => 'pemain',
                'pemainEditFrom' => 'turnamen-operasi',
            ])
        @elseif ($activeTab === 'matchmaking')
            @include('admin.matchmaking.partials.workspace', [
                'bracketUrl' => route('admin.turnamen-operasi.index', [
                    'id_turnamen' => $turnamen->id,
                    'tab' => 'bracket',
                ]),
            ])
        @elseif ($activeTab === 'klasemen')
            @include('admin.standings.partials.standings-panel')
        @elseif ($activeTab === 'bracket')
            @include('admin.bracket.partials.bracket-panel')
        @endif
    </div>
@else
    <div class="alert alert-light border text-center mb-0">
        <i class="bi bi-funnel text-muted d-block mb-2 fs-4"></i>
        Pilih turnamen untuk mengelola pemain, matchmaking, klasemen, dan bracket.
    </div>
@endif
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" crossorigin="anonymous">
<style>
    .pemain-table-card,
    .pemain-table-card > .card-body {
        overflow: visible;
    }

    #pemain-table-wrapper .dropdown-menu {
        z-index: 1080;
    }

    .turnamen-filter-select2-wrap .select2-container {
        width: 100% !important;
    }

    .turnamen-filter-select2-dropdown .select2-results__options {
        max-height: 12.5rem;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" crossorigin="anonymous"></script>
@if ($turnamen && $activeTab === 'klasemen' && ! $turnamen->isMahjong())
    <script src="{{ asset('public/js/group-stage-history.js') }}"></script>
@endif
@if ($turnamen && $activeTab === 'klasemen')
    <script src="{{ asset('public/js/leaderboard.js') }}"></script>
@endif
@if ($turnamen && $activeTab === 'bracket')
    <script src="{{ asset('public/js/bracket.js') }}?v={{ @filemtime(base_path('public/js/bracket.js')) }}"></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initTurnamenFilterSelect();

    @if ($turnamen && $activeTab === 'pemain')
        BornPadelAdmin.initPemainActions();
    @endif

    @if ($turnamen && $activeTab === 'matchmaking')
        BornPadelAdmin.initMatchmakingActions();
        @if (! ($isMahjong ?? false))
            BornPadelAdmin.initScoreModal();
        @endif
    @endif

    @if (session('success'))
        BornPadelAdmin.showAlert(@json(session('success')), 'success');
    @endif

    @if (session('error'))
        BornPadelAdmin.showAlert(@json(session('error')), 'error');
    @endif

    @if (request('id_turnamen') && ! $turnamen)
        BornPadelAdmin.showAlert('Turnamen tidak ditemukan.', 'error');
    @endif

    @if (auth()->user()->isPanitia() && $turnamenList->isEmpty())
        BornPadelAdmin.showAlert('Akun panitia belum ditugaskan ke turnamen.', 'warning');
    @endif
});
</script>
@endpush
