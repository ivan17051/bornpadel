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
        $isMahjong = $isMahjong ?? $turnamen->isMahjong();
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
                        @if ($isMahjong && ($mahjongIsFinal ?? false))
                            <span class="badge bg-warning text-dark fs-6">Grup Final</span>
                        @endif
                    </div>
                    <p class="text-muted mb-0 small">
                        @if ($turnamen->isRegistrationOpen())
                            Pendaftaran masih dibuka. Tutup pendaftaran sebelum membuat grup.
                        @elseif ($isMahjong && ($mahjongIsFinal ?? false))
                            Grup final aktif. Input poin babak final lalu selesaikan turnamen untuk menentukan juara.
                        @elseif ($isMahjong && $canReshuffle)
                            Grup Mahjong aktif. Input poin per pemain, reshuffle kapan saja, atau lanjut ke babak berikutnya.
                        @elseif ($canRandomGrup && $isMahjong)
                            Pendaftaran ditutup. Buat grup Mahjong (4 pemain per grup, jumlah approved harus kelipatan 4).
                        @elseif ($canRandomGrup)
                            Pendaftaran ditutup. Atur min/max {{ $unitLabel }} per grup, lalu buat pembagian grup secara acak atau berdasarkan rating.
                        @elseif ($hasKnockoutBracket)
                            Fase grup selesai. Bracket knockout sudah dibuat.
                        @elseif ($canEndGroupStage && ! $isMahjong)
                            Semua pertandingan fase grup selesai. Klik "End Group Stage" untuk membuat bracket.
                        @elseif ($grup->isNotEmpty())
                            {{ $isMahjong ? 'Grup Mahjong sudah dibuat.' : 'Grup dan pertandingan fase grup sudah dibuat.' }}
                        @else
                            Turnamen tidak siap untuk matchmaking.
                        @endif
                    </p>
                </div>
                <div class="col-md-4">
                    @if ($canRandomGrup && ! $isMahjong)
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
                    @elseif ($canRandomGrup && $isMahjong)
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase small mb-2">Mahjong</h6>
                                <div id="group-split-preview"
                                     class="small text-muted"
                                     data-approved="{{ $approvedCount }}"
                                     data-mahjong="1">
                                    @if ($groupSplitPreview)
                                        {{ $approvedCount }} pemain → {{ $groupSplitPreview['group_count'] }} grup (4 + 4 + …)
                                    @else
                                        Jumlah pemain approved harus minimal 4 dan kelipatan 4.
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
                                data-mahjong="{{ $isMahjong ? '1' : '0' }}"
                                {{ $canRandomGrup ? '' : 'd-none' }}>
                            <i class="bi bi-shuffle me-1"></i> {{ $isMahjong ? 'Buat Grup' : 'Random Grup' }}
                        </button>
                        <button type="button"
                                class="btn btn-secondary btn-matchmaking-grup {{ $canRandomGrup ? '' : 'd-none' }}"
                                data-url="{{ route('admin.matchmaking.random-grup') }}"
                                data-turnamen="{{ $turnamen->id }}"
                                data-mode="by_rating"
                                data-mahjong="{{ $isMahjong ? '1' : '0' }}"
                                {{ $canRandomGrup ? '' : 'd-none' }}>
                            <i class="bi bi-bar-chart-steps me-1"></i> Grup by Rating
                        </button>
                        @if ($isMahjong && ($canReshuffle ?? false))
                            <button type="button"
                                    id="btn-reshuffle-groups"
                                    class="btn btn-outline-primary"
                                    data-url="{{ route('admin.matchmaking.reshuffle-groups') }}"
                                    data-turnamen="{{ $turnamen->id }}">
                                <i class="bi bi-arrow-repeat me-1"></i> Reshuffle Groups
                            </button>
                        @endif
                        <button type="button"
                                id="btn-end-group-stage"
                                class="btn btn-success {{ $canEndGroupStage ? '' : 'd-none' }}"
                                data-url="{{ route('admin.matchmaking.end-group-stage') }}"
                                data-turnamen="{{ $turnamen->id }}"
                                data-jenis="{{ $turnamen->jenis }}"
                                data-mahjong="{{ $isMahjong ? '1' : '0' }}"
                                data-max-lolos="{{ $activePlayerCount ?? $approvedCount }}"
                                {{ $canEndGroupStage ? '' : 'd-none' }}>
                            <i class="bi bi-flag me-1"></i> {{ $isMahjong ? 'Akhiri Babak' : 'Akhiri Fase Grup' }}
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
        @if ($isMahjong)
            @foreach ($grup as $g)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-diagram-3 me-2"></i>{{ $g->nama }}
                            @if ($g->babak)
                                <small class="text-muted fw-normal">— Babak {{ $g->babak }}</small>
                            @endif
                        </h5>
                        <span class="badge text-bg-info">{{ $g->members->count() }} pemain</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Pemain</th>
                                        <th class="text-center" style="width:7rem">Akumulasi</th>
                                        <th class="text-center" style="width:9rem">Poin Babak</th>
                                        <th class="text-center" style="width:7rem">Total</th>
                                        <th class="text-end" style="width:6rem"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($g->members as $member)
                                        <tr>
                                            <td class="fw-semibold">{{ $member->display_name }}</td>
                                            <td class="text-center text-muted">{{ (int) $member->poin_akumulasi }}</td>
                                            <td class="text-center">
                                                <input type="number"
                                                       class="form-control form-control-sm text-center mahjong-poin-input"
                                                       min="0"
                                                       value="{{ (int) $member->poin_didapat }}"
                                                       data-member-id="{{ $member->id }}"
                                                       data-url="{{ route('admin.matchmaking.mahjong-points', $member) }}">
                                            </td>
                                            <td class="text-center">
                                                <span class="badge text-bg-primary mahjong-total-poin" data-member-id="{{ $member->id }}">
                                                    {{ $member->total_poin }}
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-primary btn-save-mahjong-poin"
                                                        data-member-id="{{ $member->id }}">
                                                    Simpan
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
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
@endif

@if ($turnamen ?? null)
    <div class="modal fade" id="endGroupStageModal" tabindex="-1" aria-labelledby="endGroupStageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="endGroupStageModalLabel">
                        {{ ($isMahjong ?? false) ? 'Akhiri Babak' : 'Akhiri Fase Grup' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    @if ($isMahjong ?? false)
                        <p class="text-muted small">
                            Berapa banyak pemain untuk diloloskan ke babak selanjutnya?
                            Sistem akan mengambil pemain dengan total poin tertinggi dan membagi ulang ke grup berisi 4 pemain.
                            Jumlah lolos harus kelipatan 4, atau tepat 4 untuk grup final.
                        </p>
                        <div class="mb-0">
                            <label for="jumlah-lolos-input" class="form-label">Jumlah pemain lolos</label>
                            <input type="number"
                                   id="jumlah-lolos-input"
                                   class="form-control"
                                   min="4"
                                   max="{{ $activePlayerCount ?? $approvedCount }}"
                                   step="4"
                                   value="{{ min(max(4, ($activePlayerCount ?? $approvedCount) >= 8 ? 8 : 4), $activePlayerCount ?? $approvedCount) }}"
                                   required>
                        </div>
                    @else
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
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" id="btn-confirm-end-group-stage">
                        <i class="bi bi-flag me-1"></i> {{ ($isMahjong ?? false) ? 'Lanjutkan Babak' : 'Buat Bracket' }}
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
