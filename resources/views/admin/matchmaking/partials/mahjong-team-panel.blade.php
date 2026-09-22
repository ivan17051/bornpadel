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
                        <th class="text-center" style="width:6rem">Total Babak</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mahjongTeamStandings as $index => $row)
                        <tr class="{{ $index === 0 ? 'table-success' : '' }}">
                            <td class="text-muted">{{ $index + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ $row['nama'] }}</div>
                                @if (! empty($row['members']))
                                    <ul class="list-unstyled mb-0 mt-1 small text-muted">
                                        @foreach ($row['members'] as $member)
                                            <li>
                                                {{ $member['nama'] ?? '—' }}
                                                <span class="ms-1">{{ (int) ($member['poin_didapat'] ?? 0) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-primary">{{ (int) $row['total_poin'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-3">Belum ada tim.</td>
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
                <span class="text-muted fw-normal">— Babak {{ $currentBabak }}, Ronde seating {{ $currentRonde }}</span>
            </h6>
            <span class="badge text-bg-secondary">{{ $mahjongTeamMeja->count() }} meja</span>
        </div>
        <div class="card-body p-0">
            <div class="accordion matchmaking-groups-accordion mb-0" id="mahjong-team-meja-accordion">
                @foreach ($mahjongTeamMeja as $meja)
                    @php
                        $mejaCollapseId = 'mahjong-team-meja-'.$meja->id;
                        $mahjongMembers = $meja->seats
                            ->map(fn ($seat) => $seat->grupMember)
                            ->filter()
                            ->values();
                        $mahjongColCount = 1 + $mahjongMembers->count();
                        $mahjongShowAkumulasi = $mahjongMembers->contains(function ($member) {
                            return (int) $member->poin_akumulasi !== 0;
                        });
                        $mahjongEntryItems = [];
                        foreach ($mahjongMembers as $member) {
                            $entries = $member->relationLoaded('poinEntries')
                                ? $member->poinEntries
                                : $member->poinEntries()->get();
                            foreach ($entries as $entry) {
                                if ((int) $entry->id_meja !== (int) $meja->id) {
                                    continue;
                                }
                                $mahjongEntryItems[] = [
                                    'member_id' => (int) $member->id,
                                    'entry' => $entry,
                                    'ts' => optional($entry->created_at)->getTimestamp() ?? 0,
                                    'id' => (int) $entry->id,
                                ];
                            }
                        }
                        usort($mahjongEntryItems, function ($a, $b) {
                            return $a['ts'] <=> $b['ts'] ?: $a['id'] <=> $b['id'];
                        });
                        $mahjongRounds = [];
                        $mahjongUsedEntryIds = [];
                        foreach ($mahjongEntryItems as $item) {
                            if (isset($mahjongUsedEntryIds[$item['id']])) {
                                continue;
                            }
                            $round = [];
                            foreach ($mahjongMembers as $member) {
                                $round[(int) $member->id] = null;
                            }
                            $round[$item['member_id']] = $item['entry'];
                            $mahjongUsedEntryIds[$item['id']] = true;
                            foreach ($mahjongEntryItems as $other) {
                                if (isset($mahjongUsedEntryIds[$other['id']])) {
                                    continue;
                                }
                                if ($round[$other['member_id']] !== null) {
                                    continue;
                                }
                                if (abs($other['ts'] - $item['ts']) <= 3) {
                                    $round[$other['member_id']] = $other['entry'];
                                    $mahjongUsedEntryIds[$other['id']] = true;
                                }
                            }
                            $mahjongRounds[] = $round;
                        }
                    @endphp
                    <div class="accordion-item">
                        <h2 class="accordion-header d-flex align-items-stretch" id="{{ $mejaCollapseId }}-heading">
                            <button class="accordion-button"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#{{ $mejaCollapseId }}"
                                    aria-expanded="true"
                                    aria-controls="{{ $mejaCollapseId }}">
                                <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                    <span>
                                        <i class="bi bi-table me-1"></i>{{ $meja->nama }}
                                        @if ($meja->ronde)
                                            <small class="text-muted fw-normal">— Seating {{ $meja->ronde }}</small>
                                        @endif
                                    </span>
                                    <span class="badge text-bg-info ms-auto">{{ $mahjongMembers->count() }} pemain</span>
                                </span>
                            </button>
                            <div class="friendly-grup-header-actions d-flex align-items-center gap-1 px-2">
                                <button type="button"
                                        class="btn btn-sm btn-primary btn-mahjong-input-poin"
                                        data-grup-id="{{ $meja->id }}"
                                        data-grup-name="{{ $meja->nama }}"
                                        data-url="{{ route('admin.matchmaking.mahjong-team-meja-point-entries.store', $meja) }}"
                                        data-members='@json($mahjongMembers->map(fn ($m) => [
                                            "id" => $m->id,
                                            "name" => trim($m->display_name . (optional($m->grup)->nama ? " (" . $m->grup->nama . ")" : "")),
                                        ])->values())'
                                        title="Input poin untuk semua pemain di meja">
                                    <i class="bi bi-pencil-square me-1"></i>Input Poin
                                </button>
                            </div>
                        </h2>
                        <div id="{{ $mejaCollapseId }}"
                             class="accordion-collapse collapse show"
                             aria-labelledby="{{ $mejaCollapseId }}-heading">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-hover mb-0 align-middle mahjong-group-score-table"
                                           data-grup-id="{{ $meja->id }}"
                                           data-grup-name="{{ $meja->nama }}"
                                           data-update-url="{{ route('admin.matchmaking.mahjong-team-meja-point-entries.update', $meja) }}"
                                           data-adjust-url="{{ route('admin.matchmaking.mahjong-team-meja-point-adjustments.update', $meja) }}">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center mahjong-ronde-head">Ronde</th>
                                                @foreach ($mahjongMembers as $member)
                                                    <th class="text-center mahjong-player-head"
                                                        data-member-id="{{ $member->id }}"
                                                        data-label="{{ trim($member->display_name . (optional($member->grup)->nama ? ' (' . $member->grup->nama . ')' : '')) }}">
                                                        <div class="fw-semibold">
                                                            <span class="mahjong-player-name">{{ $member->display_name }}</span>
                                                        </div>
                                                        @if (optional($member->grup)->nama)
                                                            <div class="small text-muted fw-normal">{{ $member->grup->nama }}</div>
                                                        @endif
                                                        @if ($mahjongShowAkumulasi)
                                                            <div class="small text-muted fw-normal mt-1 mahjong-akumulasi" data-member-id="{{ $member->id }}">
                                                                <div>Akumulasi {{ (int) $member->poin_akumulasi }}</div>
                                                            </div>
                                                        @endif
                                                    </th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($mahjongRounds as $roundIndex => $round)
                                                <tr class="mahjong-round-row" data-round="{{ $roundIndex + 1 }}">
                                                    <td class="text-center mahjong-round-number-cell">
                                                        <button type="button"
                                                                class="btn btn-link btn-sm text-decoration-none fw-semibold p-0 btn-mahjong-edit-ronde"
                                                                title="Edit ronde {{ $roundIndex + 1 }}">
                                                            <span class="mahjong-round-label">{{ $roundIndex + 1 }}</span>
                                                            <i class="bi bi-pencil-square ms-1"></i>
                                                        </button>
                                                    </td>
                                                    @foreach ($mahjongMembers as $member)
                                                        @php
                                                            $entry = $round[(int) $member->id] ?? null;
                                                        @endphp
                                                        <td class="text-center mahjong-round-cell" data-member-id="{{ $member->id }}">
                                                            @if ($entry)
                                                                <span class="badge text-bg-light text-dark border mahjong-poin-entry {{ $entry->is_winner ? 'border-warning' : '' }}"
                                                                      data-entry-id="{{ $entry->id }}"
                                                                      data-poin="{{ (int) $entry->poin }}"
                                                                      data-is-winner="{{ $entry->is_winner ? '1' : '0' }}">
                                                                    @if ($entry->is_winner)
                                                                        <i class="bi bi-trophy-fill text-warning me-1" title="Pemenang ronde"></i>
                                                                    @endif
                                                                    {{ (int) $entry->poin > 0 ? '+' : '' }}{{ (int) $entry->poin }}
                                                                </span>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @empty
                                                <tr class="mahjong-round-empty">
                                                    <td colspan="{{ $mahjongColCount }}" class="text-center text-muted py-3">
                                                        Belum ada ronde.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr class="mahjong-adjustment-row">
                                                <th class="mahjong-adjustment-label-cell">
                                                    <button type="button"
                                                            class="btn btn-link btn-sm text-decoration-none fw-semibold p-0 btn-mahjong-edit-adjustment"
                                                            title="Edit bonus/penalti babak">
                                                        Bonus/Penalti
                                                        <i class="bi bi-pencil-square ms-1"></i>
                                                    </button>
                                                </th>
                                                @foreach ($mahjongMembers as $member)
                                                    <th class="text-center">
                                                        <span class="mahjong-penyesuaian" data-member-id="{{ $member->id }}" data-poin="{{ (int) $member->poin_penyesuaian }}">
                                                            {{ (int) $member->poin_penyesuaian > 0 ? '+' : '' }}{{ (int) $member->poin_penyesuaian }}
                                                        </span>
                                                    </th>
                                                @endforeach
                                            </tr>
                                            <tr class="mahjong-subtotal-row">
                                                <th>Subtotal</th>
                                                @foreach ($mahjongMembers as $member)
                                                    <th class="text-center">
                                                        <span class="mahjong-subtotal" data-member-id="{{ $member->id }}">
                                                            {{ (int) $member->poin_babak }} ({{ (int) $member->menang }})
                                                        </span>
                                                        <span class="d-none mahjong-poin-babak" data-member-id="{{ $member->id }}">{{ (int) $member->poin_babak }}</span>
                                                        <span class="d-none mahjong-menang" data-member-id="{{ $member->id }}">{{ (int) $member->menang }}</span>
                                                    </th>
                                                @endforeach
                                            </tr>
                                            @if ($mahjongShowAkumulasi)
                                                <tr class="mahjong-total-row">
                                                    <th>Total</th>
                                                    @foreach ($mahjongMembers as $member)
                                                        <th class="text-center">
                                                            <span class="badge text-bg-primary mahjong-total-poin" data-member-id="{{ $member->id }}">
                                                                {{ $member->total_poin }}
                                                            </span>
                                                        </th>
                                                    @endforeach
                                                </tr>
                                            @endif
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

@if ($mahjongTeamHistory->isNotEmpty())
    <div class="card mb-3" id="mahjong-team-history-card">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-clock-history me-1"></i> Riwayat Meja</h6>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs flex-wrap" id="mahjong-team-history-babak-tabs" role="tablist">
                @foreach ($mahjongTeamHistory as $index => $babakSection)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $index === 0 ? 'active' : '' }}"
                                id="mahjong-team-history-babak-{{ $babakSection['babak'] }}-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#mahjong-team-history-babak-{{ $babakSection['babak'] }}"
                                type="button"
                                role="tab"
                                aria-controls="mahjong-team-history-babak-{{ $babakSection['babak'] }}"
                                aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                            Babak {{ $babakSection['babak'] }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content pt-3" id="mahjong-team-history-babak-content">
                @foreach ($mahjongTeamHistory as $index => $babakSection)
                    <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                         id="mahjong-team-history-babak-{{ $babakSection['babak'] }}"
                         role="tabpanel"
                         aria-labelledby="mahjong-team-history-babak-{{ $babakSection['babak'] }}-tab">
                        <div class="accordion mahjong-team-history-ronde-accordion"
                             id="mahjong-team-history-b{{ $babakSection['babak'] }}">
                            @foreach ($babakSection['rondes'] as $rondeIndex => $rondeSection)
                                @php
                                    $rondeCollapseId = 'mahjong-team-history-b'.$babakSection['babak'].'-r'.$rondeSection['ronde'];
                                    $rondeExpanded = $rondeIndex === $babakSection['rondes']->count() - 1;
                                @endphp
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="{{ $rondeCollapseId }}-heading">
                                        <button class="accordion-button {{ $rondeExpanded ? '' : 'collapsed' }}"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#{{ $rondeCollapseId }}"
                                                aria-expanded="{{ $rondeExpanded ? 'true' : 'false' }}"
                                                aria-controls="{{ $rondeCollapseId }}">
                                            <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                                <span>
                                                    <i class="bi bi-layers me-1"></i>Ronde {{ $rondeSection['ronde'] }}
                                                </span>
                                                <span class="badge text-bg-secondary ms-auto">
                                                    {{ $rondeSection['meja']->count() }} meja
                                                </span>
                                            </span>
                                        </button>
                                    </h2>
                                    <div id="{{ $rondeCollapseId }}"
                                         class="accordion-collapse collapse {{ $rondeExpanded ? 'show' : '' }}"
                                         aria-labelledby="{{ $rondeCollapseId }}-heading"
                                         data-bs-parent="#mahjong-team-history-b{{ $babakSection['babak'] }}">
                                        <div class="accordion-body">
                                            @foreach ($rondeSection['meja'] as $histMeja)
                                                @php
                                                    $histMembers = $histMeja->seatedMembers();
                                                    $histRounds = $histMeja->scoringRounds();
                                                    $histColCount = 1 + $histMembers->count();
                                                    $histTotals = [];
                                                    $histWins = [];
                                                    foreach ($histMembers as $histMember) {
                                                        $histTotals[(int) $histMember->id] = 0;
                                                        $histWins[(int) $histMember->id] = 0;
                                                    }
                                                    foreach ($histRounds as $histRound) {
                                                        foreach ($histRound as $histMemberId => $histEntry) {
                                                            if (! $histEntry) {
                                                                continue;
                                                            }
                                                            $histTotals[(int) $histMemberId] = ($histTotals[(int) $histMemberId] ?? 0) + (int) $histEntry->poin;
                                                            if ($histEntry->is_winner) {
                                                                $histWins[(int) $histMemberId] = ($histWins[(int) $histMemberId] ?? 0) + 1;
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                <div class="mb-3 {{ $loop->last ? 'mb-0' : '' }}">
                                                    <h6 class="small fw-semibold mb-2">
                                                        <i class="bi bi-table me-1"></i>{{ $histMeja->nama }}
                                                        <span class="text-muted fw-normal">— {{ $histMembers->count() }} pemain</span>
                                                    </h6>
                                                    <div class="table-responsive border rounded">
                                                        <table class="table table-sm table-bordered table-hover mb-0 align-middle">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th class="text-center" style="width:4.5rem">Ronde</th>
                                                                    @foreach ($histMembers as $histMember)
                                                                        <th class="text-center">
                                                                            <div class="fw-semibold">{{ $histMember->display_name }}</div>
                                                                            @if (optional($histMember->grup)->nama)
                                                                                <div class="small text-muted fw-normal">{{ $histMember->grup->nama }}</div>
                                                                            @endif
                                                                        </th>
                                                                    @endforeach
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @forelse ($histRounds as $histRoundIndex => $histRound)
                                                                    <tr>
                                                                        <td class="text-center fw-semibold text-muted">{{ $histRoundIndex + 1 }}</td>
                                                                        @foreach ($histMembers as $histMember)
                                                                            @php
                                                                                $histEntry = $histRound[(int) $histMember->id] ?? null;
                                                                            @endphp
                                                                            <td class="text-center">
                                                                                @if ($histEntry)
                                                                                    <span class="badge text-bg-light text-dark border {{ $histEntry->is_winner ? 'border-warning' : '' }}">
                                                                                        @if ($histEntry->is_winner)
                                                                                            <i class="bi bi-trophy-fill text-warning me-1" title="Pemenang ronde"></i>
                                                                                        @endif
                                                                                        {{ (int) $histEntry->poin > 0 ? '+' : '' }}{{ (int) $histEntry->poin }}
                                                                                    </span>
                                                                                @else
                                                                                    <span class="text-muted">—</span>
                                                                                @endif
                                                                            </td>
                                                                        @endforeach
                                                                    </tr>
                                                                @empty
                                                                    <tr>
                                                                        <td colspan="{{ $histColCount }}" class="text-center text-muted py-3">
                                                                            Belum ada ronde poin.
                                                                        </td>
                                                                    </tr>
                                                                @endforelse
                                                            </tbody>
                                                            @if ($histRounds !== [])
                                                                <tfoot class="table-light">
                                                                    <tr>
                                                                        <th>Subtotal</th>
                                                                        @foreach ($histMembers as $histMember)
                                                                            <th class="text-center">
                                                                                {{ (int) ($histTotals[(int) $histMember->id] ?? 0) }}
                                                                                ({{ (int) ($histWins[(int) $histMember->id] ?? 0) }})
                                                                            </th>
                                                                        @endforeach
                                                                    </tr>
                                                                </tfoot>
                                                            @endif
                                                        </table>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
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
                        <option value="{{ $n }}" {{ $n === ($mahjongTeamAllowedAdvance[0] ?? 1) ? 'selected' : '' }}>
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
