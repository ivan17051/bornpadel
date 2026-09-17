{{-- Mahjong Tim: teams + cross tables --}}
@php
    $mahjongTeamStandings = $mahjongTeamStandings ?? collect();
    $mahjongTeamMeja = $mahjongTeamMeja ?? collect();
    $mahjongTeamHistory = $mahjongTeamHistory ?? collect();
    $mahjongTeamAllowedAdvance = $mahjongTeamAllowedAdvance ?? [1];
    $currentBabak = (int) ($grup->max('babak') ?: 1);
    $currentRonde = (int) ($mahjongTeamMeja->max('ronde') ?: 0);
@endphp

<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="mb-0">
            <i class="bi bi-people-fill me-1"></i> Klasemen Tim
            <span class="text-muted fw-normal">— Babak {{ $currentBabak }}</span>
        </h6>
        <span class="badge text-bg-secondary">{{ $grup->count() }} tim aktif</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:3rem">#</th>
                        <th>Tim</th>
                        <th>Pemain</th>
                        <th class="text-center" style="width:6rem">Total Babak</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mahjongTeamStandings as $index => $row)
                        <tr>
                            <td class="text-muted">{{ $index + 1 }}</td>
                            <td class="fw-semibold">{{ $row['nama'] }}</td>
                            <td class="small text-muted">
                                {{ collect($row['members'])->pluck('nama')->join(', ') }}
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-primary">{{ (int) $row['total_poin'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-3">Belum ada tim.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 small text-muted border-top">
            Poin menumpuk antar ronde dalam babak yang sama, dan di-reset saat ganti babak.
        </div>
    </div>
</div>

@if ($mahjongTeamMeja->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="mb-0">
                <i class="bi bi-grid-3x3-gap me-1"></i> Meja Aktif
                <span class="text-muted fw-normal">— Babak {{ $currentBabak }}, Ronde {{ $currentRonde }}</span>
            </h6>
        </div>
        <div class="card-body">
            <div class="accordion" id="mahjong-team-meja-accordion">
                @foreach ($mahjongTeamMeja as $meja)
                    @php
                        $mejaCollapseId = 'mahjong-team-meja-'.$meja->id;
                    @endphp
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="{{ $mejaCollapseId }}-heading">
                            <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#{{ $mejaCollapseId }}"
                                    aria-expanded="false"
                                    aria-controls="{{ $mejaCollapseId }}">
                                <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                    <span><i class="bi bi-table me-1"></i>{{ $meja->nama }}</span>
                                    <span class="badge text-bg-secondary ms-auto">{{ $meja->seats->count() }} kursi</span>
                                </span>
                            </button>
                        </h2>
                        <div id="{{ $mejaCollapseId }}" class="accordion-collapse collapse"
                             data-bs-parent="#mahjong-team-meja-accordion">
                            <div class="accordion-body">
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Pemain</th>
                                                <th>Tim</th>
                                                <th class="text-center">Poin Babak</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($meja->seats as $seat)
                                                @php $member = $seat->grupMember; @endphp
                                                <tr>
                                                    <td class="fw-semibold">{{ optional($member)->display_name ?? '—' }}</td>
                                                    <td class="text-muted">{{ optional(optional($member)->grup)->nama ?? '—' }}</td>
                                                    <td class="text-center">
                                                        <span class="badge text-bg-primary">{{ (int) (optional($member)->poin_didapat ?? 0) }}</span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary btn-mahjong-team-meja-points"
                                        data-meja-id="{{ $meja->id }}"
                                        data-meja-nama="{{ $meja->nama }}"
                                        data-url="{{ route('admin.matchmaking.mahjong-team-meja-point-entries.store', $meja) }}"
                                        data-seats='@json($meja->seats->map(fn ($s) => [
                                            'id' => (int) $s->id_grup_member,
                                            'nama' => optional($s->grupMember)->display_name,
                                            'tim' => optional(optional($s->grupMember)->grup)->nama,
                                        ])->values())'>
                                    <i class="bi bi-pencil-square me-1"></i> Input Poin Meja
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

@if ($mahjongTeamHistory->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-clock-history me-1"></i> Riwayat Meja</h6>
        </div>
        <div class="card-body">
            @foreach ($mahjongTeamHistory as $babakSection)
                <h6 class="text-muted text-uppercase small">Babak {{ $babakSection['babak'] }}</h6>
                @foreach ($babakSection['rondes'] as $rondeSection)
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Ronde {{ $rondeSection['ronde'] }}</div>
                        <ul class="list-group list-group-flush border rounded mb-2">
                            @foreach ($rondeSection['meja'] as $histMeja)
                                <li class="list-group-item">
                                    <strong>{{ $histMeja->nama }}</strong>
                                    <div class="small text-muted">
                                        @foreach ($histMeja->seats as $seat)
                                            {{ optional($seat->grupMember)->display_name }}
                                            ({{ optional(optional($seat->grupMember)->grup)->nama }})@if (! $loop->last), @endif
                                        @endforeach
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
@endif

{{-- End babak modal for teams --}}
<div class="modal fade" id="mahjongTeamEndBabakModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Akhiri Babak (Mahjong Tim)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Pilih berapa tim yang lolos. Poin babak di-reset untuk babak berikutnya.
                    Pilih 1 untuk menentukan juara (tanpa peringkat individu).
                </p>
                <label class="form-label" for="mahjong-team-jumlah-lolos">Jumlah tim lolos</label>
                <select id="mahjong-team-jumlah-lolos" class="form-select">
                    @foreach ($mahjongTeamAllowedAdvance as $n)
                        <option value="{{ $n }}" @selected($n === ($mahjongTeamAllowedAdvance[0] ?? 1))>
                            {{ $n === 1 ? '1 (juara)' : $n.' tim' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="btn-confirm-mahjong-team-end-babak">
                    <i class="bi bi-eye me-1"></i> Lihat tim lolos
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mahjongTeamAdvancePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tim yang lolos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="mahjong-team-advance-preview-help"></p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Tim</th>
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody id="mahjong-team-advance-preview-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="btn-confirm-mahjong-team-advance-preview">
                    <i class="bi bi-check-lg me-1"></i> Konfirmasi
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mahjongTeamTiebreakModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pilih tim lolos (seri)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="mahjong-team-tiebreak-help"></p>
                <div id="mahjong-team-tiebreak-auto" class="mb-3 d-none"></div>
                <div class="list-group" id="mahjong-team-tiebreak-list"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="btn-confirm-mahjong-team-tiebreak">
                    <i class="bi bi-eye me-1"></i> Lanjut
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mahjongTeamMejaPointsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mahjong-team-meja-points-title">Input Poin Meja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Opsional: klik nama untuk menandai pemenang ronde, isi poin, lalu simpan.</p>
                <div id="mahjong-team-meja-points-fields" class="d-grid gap-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btn-save-mahjong-team-meja-points">
                    <i class="bi bi-check-lg me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>
