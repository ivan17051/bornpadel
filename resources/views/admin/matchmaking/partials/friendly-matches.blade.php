@php
    $friendlyMatches = $friendlyMatches ?? collect();
    $canAddFriendlyMatch = $canAddFriendlyMatch ?? false;
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
@endphp

<div class="card mb-4" id="friendly-matches-panel"
     data-create-url="{{ route('admin.matchmaking.friendly-match.store') }}"
     data-turnamen="{{ $turnamen->id }}"
     data-groups='@json($groupsPayload)'>
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-lightning-charge me-1"></i> Pertandingan Friendly
        </h5>
        @if ($canAddFriendlyMatch)
            <button type="button" class="btn btn-sm btn-primary" id="btn-open-friendly-match-modal"
                    data-bs-toggle="modal" data-bs-target="#friendlyMatchModal">
                <i class="bi bi-plus-lg me-1"></i> Tambah Tanding
            </button>
        @endif
    </div>
    <div class="card-body p-0">
        @if ($friendlyMatches->isEmpty())
            <div class="p-4 text-center text-muted">
                Belum ada pertandingan. Tambahkan tanding antar grup dan pilih 2 pemain per sisi.
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
                                    @endif
                                </td>
                                <td class="text-center">{{ $scoreLabel }}</td>
                                <td>
                                    <span class="badge {{ $match->status === 'completed' ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $match->status === 'completed' ? 'Selesai' : 'Terjadwal' }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($match->status !== 'completed' && $match->isReadyForScoring())
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

@if ($canAddFriendlyMatch)
<div class="modal fade" id="friendlyMatchModal" tabindex="-1" aria-labelledby="friendlyMatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="friendlyMatchModalLabel">Tambah Pertandingan Friendly</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
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
                    <i class="bi bi-check-lg me-1"></i> Simpan Tanding
                </button>
            </div>
        </div>
    </div>
</div>
@endif
