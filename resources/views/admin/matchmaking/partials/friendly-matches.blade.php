@php
    $friendlyMatches = $friendlyMatches ?? collect();
    $canAddFriendlyMatch = $canAddFriendlyMatch ?? false;
    $friendlyService = app(\App\Services\FriendlyMatchmakingService::class);
    $groupsPayload = $grup->map(function ($g) {
        return [
            'id' => $g->id,
            'nama' => $g->nama,
            'members' => $g->members->map(function ($m) {
                return [
                    'id' => (int) $m->id_pemain,
                    'nama' => optional($m->pemain)->nama ?? 'Pemain',
                ];
            })->values(),
        ];
    })->values();
    $showFriendlyModal = $canAddFriendlyMatch || $friendlyMatches->isNotEmpty();
@endphp

<div class="card mb-4" id="friendly-matches-panel"
     data-create-url="{{ route('admin.matchmaking.friendly-match.store') }}"
     data-assign-url-template="{{ route('admin.matchmaking.friendly-match.pairs', ['pertandingan' => '__ID__']) }}"
     data-turnamen="{{ $turnamen->id }}"
     data-groups='@json($groupsPayload)'>
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="card-title mb-0">
            <i class="bi bi-lightning-charge me-1"></i> Pertandingan Group Match
        </h5>
        @if ($canAddFriendlyMatch)
            <button type="button" class="btn btn-sm btn-primary ms-auto" id="btn-open-friendly-match-modal"
                    data-bs-toggle="modal" data-bs-target="#friendlyMatchModal"
                    data-mode="create">
                <i class="bi bi-plus-lg me-1"></i> Tambah Tanding
            </button>
        @endif
    </div>
    <div class="card-body p-0">
        @if ($friendlyMatches->isEmpty())
            <div class="p-4 text-center text-muted">
                Belum ada slot pertandingan. Buat grup terlebih dahulu — slot antar grup dibuat otomatis.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Grup</th>
                            <th>Pasangan</th>
                            <th class="text-center">Skor</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($friendlyMatches as $match)
                            @php
                                $scoreLabel = $match->skor->isEmpty()
                                    ? '—'
                                    : $match->skor->map(fn ($s) => $s->skor_pemain1 . '-' . $s->skor_pemain2)->implode(', ');
                                $pairsAssigned = $match->hasFriendlyPairsAssigned();
                                $canAssign = $friendlyService->canAssignPairs($match);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ optional($match->grup1)->nama }} vs {{ optional($match->grup2)->nama }}</div>
                                </td>
                                <td>
                                    <div>{{ $match->side1_label }}</div>
                                    <div class="text-muted small">vs {{ $match->side2_label }}</div>
                                    @if ($match->status === 'completed' && $match->winner_label)
                                        <div class="small text-success mt-1">
                                            <i class="bi bi-trophy me-1"></i>{{ $match->winner_label }}
                                        </div>
                                    @elseif (! $pairsAssigned)
                                        <div class="small text-warning mt-1">
                                            <i class="bi bi-people me-1"></i>Belum diisi pasangan
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">{{ $scoreLabel }}</td>
                                <td>
                                    @if ($match->status === 'completed')
                                        <span class="badge text-bg-success">Selesai</span>
                                    @elseif ($pairsAssigned)
                                        <span class="badge text-bg-secondary">Terjadwal</span>
                                    @else
                                        <span class="badge text-bg-warning text-dark">Menunggu pasangan</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($canAssign)
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary btn-assign-friendly-pairs"
                                                data-bs-toggle="modal"
                                                data-bs-target="#friendlyMatchModal"
                                                data-mode="assign"
                                                data-match-id="{{ $match->id }}"
                                                data-grup1="{{ $match->id_grup1 }}"
                                                data-grup2="{{ $match->id_grup2 }}"
                                                data-side1='@json(array_values(array_filter([(int) $match->id_pemain1, (int) $match->id_pemain1_partner])))'
                                                data-side2='@json(array_values(array_filter([(int) $match->id_pemain2, (int) $match->id_pemain2_partner])))'>
                                            <i class="bi bi-people me-1"></i>
                                            {{ $pairsAssigned ? 'Ubah Pasangan' : 'Isi Pasangan' }}
                                        </button>
                                    @endif
                                    @if ($match->status !== 'completed' && $pairsAssigned)
                                        <button type="button"
                                                class="btn btn-sm btn-primary btn-input-score"
                                                data-id="{{ $match->id }}"
                                                data-show-url="{{ route('admin.pertandingan.show', $match) }}"
                                                data-store-url="{{ route('admin.pertandingan.score', $match) }}">
                                            <i class="bi bi-pencil-square me-1"></i> Input Skor
                                        </button>
                                    @elseif ($match->can_edit_score ?? false)
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary btn-input-score"
                                                data-id="{{ $match->id }}"
                                                data-show-url="{{ route('admin.pertandingan.show', $match) }}"
                                                data-store-url="{{ route('admin.pertandingan.score', $match) }}">
                                            <i class="bi bi-pencil-square me-1"></i> Edit Skor
                                        </button>
                                    @elseif ($match->skor->isNotEmpty())
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary btn-view-score"
                                                data-show-url="{{ route('admin.pertandingan.show', $match) }}">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    @endif
                                    @if ($match->status !== 'completed' && $match->skor->isEmpty())
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger btn-delete-friendly-match"
                                                data-url="{{ route('admin.matchmaking.friendly-match.destroy', $match) }}"
                                                data-turnamen="{{ $turnamen->id }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@if ($showFriendlyModal)
<div class="modal fade" id="friendlyMatchModal" tabindex="-1" aria-labelledby="friendlyMatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="friendlyMatchModalLabel">Tambah Pertandingan Group Match</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small" id="friendly-match-help">
                    Pilih 2 grup yang bertanding, lalu pilih 2 pemain dari masing-masing grup.
                    Pemain boleh bermain berulang; anggota grup boleh tidak ikut tanding.
                </p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Grup 1</label>
                        <select id="friendly-grup1" class="form-select"></select>
                        <label class="form-label mt-3">Pemain Grup 1 (pilih 2)</label>
                        <div id="friendly-side1-players" class="border rounded p-2" style="max-height: 12rem; overflow:auto;"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Grup 2</label>
                        <select id="friendly-grup2" class="form-select"></select>
                        <label class="form-label mt-3">Pemain Grup 2 (pilih 2)</label>
                        <div id="friendly-side2-players" class="border rounded p-2" style="max-height: 12rem; overflow:auto;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btn-save-friendly-match">
                    <i class="bi bi-check-lg me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>
@endif
