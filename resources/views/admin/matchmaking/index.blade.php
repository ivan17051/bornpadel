@extends('layouts.admin')

@section('title', 'Matchmaking Grup')
@section('page-title', 'Matchmaking Grup')

@section('breadcrumb')
    <li class="breadcrumb-item active">Matchmaking</li>
@endsection

@section('content')
@include('admin.partials.turnamen-filter', ['filterRoute' => route('admin.matchmaking.index')])

@if (! $turnamen)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        @if ($turnamenList->isEmpty())
            Belum ada turnamen.
            <a href="{{ route('admin.turnamen.create') }}">Buat turnamen</a> terlebih dahulu.
        @else
            Pilih turnamen dari filter di atas, atau
            <a href="{{ route('admin.turnamen.index') }}">buka turnamen aktif</a> (status open/ongoing).
        @endif
    </div>
@else
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ $turnamen->nama }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge bg-{{ $turnamen->status === 'open' ? 'success' : 'primary' }} fs-6">
                            Status: {{ strtoupper($turnamen->status) }}
                        </span>
                        <span class="badge text-bg-secondary fs-6">
                            {{ $approvedCount }} pemain approved
                        </span>
                    </div>
                    <p class="text-muted mb-0 small">
                        @if ($turnamen->isRegistrationOpen())
                            Pendaftaran masih dibuka. Tutup pendaftaran sebelum melakukan random grup.
                        @elseif ($canRandomGrup)
                            Pendaftaran ditutup. Buat pembagian grup secara acak atau kelompokkan pemain dengan rating serupa.
                        @elseif ($hasKnockoutBracket)
                            Fase grup selesai. Bracket knockout sudah dibuat.
                        @elseif ($canEndGroupStage)
                            Semua pertandingan fase grup selesai. Klik "End Group Stage" untuk membuat bracket.
                        @elseif ($grup->isNotEmpty())
                            Grup dan pertandingan fase grup sudah dibuat.
                        @else
                            Turnamen tidak siap untuk matchmaking.
                        @endif
                    </p>
                </div>
                <div class="col-md-4">
                    <div class="d-grid gap-2">
                        <button type="button"
                                id="btn-close-registration"
                                class="btn btn-warning {{ $canCloseRegistration ? '' : 'd-none' }}"
                                data-url="{{ route('admin.matchmaking.close-registration') }}"
                                data-turnamen="{{ $turnamen->id }}"
                                {{ $canCloseRegistration ? '' : 'd-none' }}>
                            <i class="bi bi-lock me-1"></i> Tutup Pendaftaran
                        </button>
                        <button type="button"
                                class="btn btn-primary btn-matchmaking-grup {{ $canRandomGrup ? '' : 'd-none' }}"
                                data-url="{{ route('admin.matchmaking.random-grup') }}"
                                data-turnamen="{{ $turnamen->id }}"
                                data-mode="random"
                                {{ $canRandomGrup ? '' : 'd-none' }}
                                title="{{ $canRandomGrup ? 'Acak pemain ke grup' : 'Hanya aktif saat pendaftaran ditutup' }}">
                            <i class="bi bi-shuffle me-1"></i> Random Grup
                        </button>
                        <button type="button"
                                class="btn btn-secondary btn-matchmaking-grup {{ $canRandomGrup ? '' : 'd-none' }}"
                                data-url="{{ route('admin.matchmaking.random-grup') }}"
                                data-turnamen="{{ $turnamen->id }}"
                                data-mode="by_rating"
                                {{ $canRandomGrup ? '' : 'd-none' }}
                                title="{{ $canRandomGrup ? 'Kelompokkan pemain dengan rating serupa' : 'Hanya aktif saat pendaftaran ditutup' }}">
                            <i class="bi bi-bar-chart-steps me-1"></i> Grup by Rating
                        </button>
                        <button type="button"
                                id="btn-end-group-stage"
                                class="btn btn-success {{ $canEndGroupStage ? '' : 'd-none' }}"
                                data-url="{{ route('admin.matchmaking.end-group-stage') }}"
                                data-turnamen="{{ $turnamen->id }}"
                                {{ $canEndGroupStage ? '' : 'd-none' }}
                                title="{{ $canEndGroupStage ? 'Buat bracket knockout dari top 2 tiap grup' : 'Semua pertandingan fase grup harus selesai' }}">
                            <i class="bi bi-flag me-1"></i> Akhiri Fase Grup
                        </button>
                        @if ($hasKnockoutBracket)
                            <a href="{{ route('admin.bracket.index', ['id_turnamen' => $turnamen->id]) }}" class="btn btn-outline-success">
                                <i class="bi bi-diagram-2 me-1"></i> Lihat Bracket
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($grup->isNotEmpty())
        @foreach ($grup as $g)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center row">
                    
                        <div class="col-md-6">
                            <h5 class="card-title mb-0"><i class="bi bi-diagram-3 me-2"></i>{{ $g->nama }}</h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="badge text-bg-info">{{ $g->pemain->count() }} pemain · {{ $g->pertandingan->count() }} pertandingan</span>
                        </div>
                    
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5 mb-3 mb-md-0">
                            <h6 class="text-muted text-uppercase small">Anggota Grup</h6>
                            <ul class="list-group list-group-flush">
                                @foreach ($g->pemain as $p)
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span>{{ $p->nama }}</span>
                                        <small class="text-muted">Rating {{ number_format($p->rating, 1) }}</small>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-md-7">
                            <h6 class="text-muted text-uppercase small">Jadwal Fase Grup (Round-Robin)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Pemain 1</th>
                                            <th>vs</th>
                                            <th>Pemain 2</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($g->pertandingan as $match)
                                            <tr>
                                                <td>{{ $match->pemain1->nama ?? '-' }}</td>
                                                <td class="text-center">vs</td>
                                                <td>{{ $match->pemain2->nama ?? '-' }}</td>
                                                <td><span class="badge bg-secondary">{{ $match->status }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initMatchmakingActions();
});
</script>
@endpush
