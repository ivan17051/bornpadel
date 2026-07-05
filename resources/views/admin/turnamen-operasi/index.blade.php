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
])

@if ($turnamen)
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $activeTab === 'pemain' ? 'active' : '' }}"
               href="{{ route('admin.turnamen-operasi.index', array_filter(['id_turnamen' => $turnamen->id, 'tab' => 'pemain', 'search' => request('search'), 'status' => request('status')])) }}">
                <i class="bi bi-people me-1"></i> Pemain Terdaftar
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $activeTab === 'matchmaking' ? 'active' : '' }}"
               href="{{ route('admin.turnamen-operasi.index', ['id_turnamen' => $turnamen->id, 'tab' => 'matchmaking']) }}">
                <i class="bi bi-shuffle me-1"></i> Matchmaking Grup
            </a>
        </li>
    </ul>

    <div class="tab-content">
        @if ($activeTab === 'pemain')
            @include('admin.pemain.partials.registered-panel', [
                'filterRoute' => $filterRoute,
                'preserveTab' => 'pemain',
            ])
        @else
            @include('admin.matchmaking.partials.workspace')
        @endif
    </div>
@else
    <div class="alert alert-light border text-center mb-0">
        <i class="bi bi-funnel text-muted d-block mb-2 fs-4"></i>
        Pilih turnamen untuk mengelola pemain dan matchmaking.
    </div>
@endif
@endsection

@push('styles')
<style>
    .pemain-table-card,
    .pemain-table-card > .card-body {
        overflow: visible;
    }

    #pemain-table-wrapper .dropdown-menu {
        z-index: 1080;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initPemainActions();
    BornPadelAdmin.initMatchmakingActions();

    @if ($turnamen && $activeTab === 'matchmaking' && ! ($isMahjong ?? false))
        BornPadelAdmin.initScoreModal();
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
