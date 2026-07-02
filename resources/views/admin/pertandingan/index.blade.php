@extends('layouts.admin')

@section('title', 'Pertandingan & Skor')
@section('page-title', 'Pertandingan & Skor')

@section('breadcrumb')
    <li class="breadcrumb-item active">Pertandingan</li>
@endsection

@section('content')
@include('admin.partials.turnamen-filter', [
    'filterRoute' => route('admin.pertandingan.index'),
    'requireTurnamenSelection' => true,
])


<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.pertandingan.index') }}" class="row g-2 align-items-end">
            @if (request('id_turnamen'))
                <input type="hidden" name="id_turnamen" value="{{ request('id_turnamen') }}">
            @endif
            <div class="col-md-3">
                <label class="form-label small text-muted">Ronde</label>
                <select name="nama_ronde" class="form-select">
                    <option value="">Semua Ronde</option>
                    @foreach ($rondeOptions as $ronde)
                        <option value="{{ $ronde }}" {{ request('nama_ronde') === $ronde ? 'selected' : '' }}>{{ $ronde }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Grup</label>
                <select name="id_grup" class="form-select">
                    <option value="">Semua Grup</option>
                    @foreach ($grupList as $g)
                        <option value="{{ $g->id }}" {{ request('id_grup') == $g->id ? 'selected' : '' }}>{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                    <option value="ongoing" {{ request('status') === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="{{ route('admin.pertandingan.index', request()->only('id_turnamen')) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="card-header">
        <div class="row align-items-center g-2">
            <div class="col-md-6">
                <h5 class="card-title mb-0">Daftar Pertandingan</h5>
            </div>
            <div class="col-md-6 text-md-end">
                @if ($turnamen)
                    <span class="badge text-bg-secondary">{{ $turnamen->nama }}</span>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>Ronde</th>
                        <th>Grup</th>
                        <th>Pemain 1</th>
                        <th class="text-center">vs</th>
                        <th>Pemain 2</th>
                        <th>Skor</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pertandingan as $match)
                        <tr>
                            <td><span class="badge text-bg-info">{{ $match->nama_ronde }}</span></td>
                            <td>{{ $match->grup->nama ?? '—' }}</td>
                            <td>@include('admin.pertandingan.partials.match-side-label', ['match' => $match, 'side' => 1])</td>
                            <td class="text-center text-muted">vs</td>
                            <td>@include('admin.pertandingan.partials.match-side-label', ['match' => $match, 'side' => 2])</td>
                            <td>
                                @if ($match->skor->isNotEmpty())
                                    @foreach ($match->skor as $s)
                                        <span class="badge text-bg-light text-dark border me-1">
                                            {{ $s->skor_pemain1 }}-{{ $s->skor_pemain2 }}
                                        </span>
                                    @endforeach
                                    @if ($match->pemenang || $match->pesertaPemenang)
                                        <i class="bi bi-trophy-fill text-warning ms-1" title="{{ $match->winner_label }}"></i>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $match->status === 'completed' ? 'success' : ($match->status === 'ongoing' ? 'warning' : 'secondary') }}">
                                    {{ $match->status }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if ($match->status !== 'completed' && $match->isReadyForScoring())
                                    <button type="button"
                                            class="btn btn-sm btn-primary btn-input-score"
                                            data-id="{{ $match->id }}"
                                            data-show-url="{{ route('admin.pertandingan.show', $match) }}"
                                            data-store-url="{{ route('admin.pertandingan.score', $match) }}">
                                        <i class="bi bi-pencil-square me-1"></i> Input Skor
                                    </button>
                                @elseif ($match->status !== 'completed')
                                    <span class="badge text-bg-light text-dark border">Menunggu Pemain</span>
                                @else
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary btn-view-score"
                                            data-show-url="{{ route('admin.pertandingan.show', $match) }}">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                @if ($turnamen)
                                    Belum ada pertandingan.
                                @else
                                    Pilih turnamen untuk melihat pertandingan.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($pertandingan->hasPages())
        <div class="card-footer d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 py-3">
            @if ($pertandingan->total() > 0)
                <div class="small text-muted">
                    Menampilkan {{ $pertandingan->firstItem() }}–{{ $pertandingan->lastItem() }}
                    dari {{ $pertandingan->total() }} pertandingan
                </div>
            @endif
            <div class="ms-sm-auto">
                {{ $pertandingan->links() }}
            </div>
        </div>
    @endif
</div>

{{-- Score Input Modal --}}
<div class="modal fade" id="scoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trophy me-2"></i>Input Skor Pertandingan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="score-modal-meta" class="mb-3 small text-muted"></div>
                <div id="score-modal-readonly" class="d-none"></div>
                <form id="score-form">
                    <div class="row fw-semibold text-center mb-2">
                        <div class="col-4">Set</div>
                        <div class="col-4" id="score-p1-name">Pemain 1</div>
                        <div class="col-4" id="score-p2-name">Pemain 2</div>
                    </div>
                    <div id="score-sets-container"></div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-set">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Set
                        </button>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Best of 3 — pemenang harus menang 2 set. Tambah set hingga maksimal 3 jika diperlukan.
                    </div>
                    <div id="score-form-error" class="alert alert-danger small mt-2 d-none"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="btn-save-score">
                    <i class="bi bi-check-lg me-1"></i> Simpan & Selesaikan
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initScoreModal();
});
</script>
@endpush
