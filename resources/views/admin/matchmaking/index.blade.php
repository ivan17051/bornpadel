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

@if ($turnamen)
    @php
        $unitLabel = $unitLabel ?? ($turnamen->isDouble() ? 'pasangan' : 'pemain');
        $unitLabelTitle = ucfirst($unitLabel);
        $sideLabel = $turnamen->isDouble() ? 'Pasangan' : 'Pemain';
    @endphp
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">{{ $turnamen->nama }}</h5>
            <span class="badge text-bg-light text-dark border">{{ $turnamen->jenis_label }}</span>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge bg-{{ $turnamen->status === 'open' ? 'success' : 'primary' }} fs-6">
                            Status: {{ strtoupper($turnamen->status) }}
                        </span>
                        <span class="badge text-bg-secondary fs-6">
                            {{ $approvedCount }} {{ $unitLabel }} approved
                        </span>
                    </div>
                    <p class="text-muted mb-0 small">
                        @if ($turnamen->isRegistrationOpen())
                            Pendaftaran masih dibuka. Tutup pendaftaran sebelum melakukan random grup.
                        @elseif ($canRandomGrup)
                            Pendaftaran ditutup. Atur min/max {{ $unitLabel }} per grup, lalu buat pembagian grup secara acak atau berdasarkan rating.
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
                    @if ($canRandomGrup)
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase small mb-3">Pengaturan Grup</h6>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label for="min-pemain-grup" class="form-label small mb-1">Min / grup</label>
                                        <input type="number"
                                               id="min-pemain-grup"
                                               class="form-control form-control-sm"
                                               min="2"
                                               max="12"
                                               value="{{ $defaultMinPerGroup }}">
                                    </div>
                                    <div class="col-6">
                                        <label for="max-pemain-grup" class="form-label small mb-1">Max / grup</label>
                                        <input type="number"
                                               id="max-pemain-grup"
                                               class="form-control form-control-sm"
                                               min="2"
                                               max="12"
                                               value="{{ $defaultMaxPerGroup }}">
                                    </div>
                                </div>
                                <div id="group-split-preview"
                                     class="small text-muted"
                                     data-approved="{{ $approvedCount }}">
                                    @if ($groupSplitPreview)
                                        {{ $approvedCount }} {{ $unitLabel }} → {{ $groupSplitPreview['group_count'] }} grup ({{ $groupSplitPreview['label'] }})
                                    @else
                                        {{ ucfirst($unitLabel) }} tidak cukup untuk pembagian grup dengan batas ini.
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
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
                                data-jenis="{{ $turnamen->jenis }}"
                                {{ $canEndGroupStage ? '' : 'd-none' }}
                                title="{{ $canEndGroupStage ? 'Buat bracket knockout dari peserta lolos fase grup' : 'Semua pertandingan fase grup harus selesai' }}">
                            <i class="bi bi-flag me-1"></i> Akhiri Fase Grup
                        </button>
                        @if ($canCompleteTournament ?? false)
                            <button type="button"
                                    id="btn-complete-tournament"
                                    class="btn btn-dark"
                                    data-url="{{ route('admin.matchmaking.complete-tournament') }}"
                                    data-turnamen="{{ $turnamen->id }}">
                                <i class="bi bi-trophy me-1"></i> Selesaikan Turnamen
                            </button>
                        @endif
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
                            <span class="badge text-bg-info">{{ $g->members->count() }} {{ $unitLabel }} · {{ $g->pertandingan->count() }} pertandingan</span>
                        </div>
                    
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5 mb-3 mb-md-0">
                            <h6 class="text-muted text-uppercase small">Anggota Grup</h6>
                            <ul class="list-group list-group-flush">
                                @foreach ($g->members as $member)
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span>{{ $member->display_name }}</span>
                                        <small class="text-muted">
                                            @if ($turnamen->isDouble())
                                                Rating {{ number_format(optional($member->turnamenPeserta)->average_rating ?? 0, 1) }}
                                            @else
                                                Rating {{ number_format(optional($member->pemain)->rating ?? 0, 1) }}
                                            @endif
                                        </small>
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
                                            <th>{{ $sideLabel }} 1</th>
                                            <th>vs</th>
                                            <th>{{ $sideLabel }} 2</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($g->pertandingan as $match)
                                            <tr>
                                                <td>@include('admin.pertandingan.partials.match-side-label', ['match' => $match, 'side' => 1])</td>
                                                <td class="text-center">vs</td>
                                                <td>@include('admin.pertandingan.partials.match-side-label', ['match' => $match, 'side' => 2])</td>
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

@if ($turnamen ?? null)
    <div class="modal fade" id="endGroupStageModal" tabindex="-1" aria-labelledby="endGroupStageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="endGroupStageModalLabel">Akhiri Fase Grup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Tentukan berapa banyak {{ $unitLabel ?? 'peserta' }} teratas dari setiap grup yang lolos ke babak knockout.
                        Sistem akan memberikan <strong>BYE</strong> otomatis kepada unggulan jika jumlah lolos bukan pangkat dua.
                    </p>
                    <div class="mb-0">
                        <label for="jumlah-lolos-input" class="form-label">
                            Jumlah {{ $turnamen->isDouble() ? 'pasangan' : 'pemain' }} lolos per grup
                        </label>
                        <input type="number"
                               id="jumlah-lolos-input"
                               class="form-control"
                               min="1"
                               max="8"
                               value="2"
                               required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" id="btn-confirm-end-group-stage">
                        <i class="bi bi-flag me-1"></i> Buat Bracket
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initMatchmakingActions();
});
</script>
@endpush
