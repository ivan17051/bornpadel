{{-- Mahjong group history: babak → ronde → groups. Pass $editable = true on matchmaking tab. --}}
@php
    $mahjongHistory = $mahjongHistory ?? collect();
    $idPrefix = $idPrefix ?? 'mahjong-history';
    $linkPemain = $linkPemain ?? false;
    $cardClass = $cardClass ?? 'card mb-3';
    $editable = $editable ?? false;
@endphp

@if ($mahjongHistory->isNotEmpty())
    <div class="{{ $cardClass }}" id="{{ $idPrefix }}-card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h6 class="mb-0">
                <i class="bi bi-clock-history me-1"></i> Riwayat Babak
            </h6>
            @if ($editable)
                <span class="small text-muted">Klik nomor ronde untuk mengubah skor.</span>
            @endif
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs flex-wrap" id="{{ $idPrefix }}-babak-tabs" role="tablist">
                @foreach ($mahjongHistory as $index => $babakSection)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $index === 0 ? 'active' : '' }}"
                                id="{{ $idPrefix }}-babak-{{ $babakSection['babak'] }}-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#{{ $idPrefix }}-babak-{{ $babakSection['babak'] }}"
                                type="button"
                                role="tab"
                                aria-controls="{{ $idPrefix }}-babak-{{ $babakSection['babak'] }}"
                                aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                            Babak {{ $babakSection['babak'] }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content pt-3" id="{{ $idPrefix }}-babak-content">
                @foreach ($mahjongHistory as $index => $babakSection)
                    <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                         id="{{ $idPrefix }}-babak-{{ $babakSection['babak'] }}"
                         role="tabpanel"
                         aria-labelledby="{{ $idPrefix }}-babak-{{ $babakSection['babak'] }}-tab">
                        @forelse ($babakSection['rondes'] as $rondeSection)
                            <div class="mb-4">
                                <h6 class="text-muted text-uppercase small mb-2">
                                    Ronde {{ $rondeSection['ronde'] }}
                                    <span class="fw-normal text-lowercase">
                                        — {{ $rondeSection['groups']->count() }} grup
                                    </span>
                                </h6>

                                <div class="accordion mahjong-history-ronde-accordion"
                                     id="{{ $idPrefix }}-b{{ $babakSection['babak'] }}-r{{ $rondeSection['ronde'] }}">
                                    @foreach ($rondeSection['groups'] as $historyGrup)
                                        @php
                                            $historyCollapseId = $idPrefix.'-g'.$historyGrup->id;
                                            $historyMembers = $historyGrup->members->values();
                                            $historyRounds = $editable ? $historyGrup->scoringRounds() : [];
                                            $historyColCount = 1 + $historyMembers->count();
                                        @endphp
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="{{ $historyCollapseId }}-heading">
                                                <button class="accordion-button collapsed"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#{{ $historyCollapseId }}"
                                                        aria-expanded="false"
                                                        aria-controls="{{ $historyCollapseId }}">
                                                    <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                                        <span>
                                                            <i class="bi bi-diagram-3 me-1"></i>{{ $historyGrup->nama }}
                                                        </span>
                                                        <span class="badge text-bg-secondary ms-auto">
                                                            {{ $historyMembers->count() }} pemain
                                                        </span>
                                                    </span>
                                                </button>
                                            </h2>
                                            <div id="{{ $historyCollapseId }}"
                                                 class="accordion-collapse collapse"
                                                 aria-labelledby="{{ $historyCollapseId }}-heading"
                                                 data-bs-parent="#{{ $idPrefix }}-b{{ $babakSection['babak'] }}-r{{ $rondeSection['ronde'] }}">
                                                <div class="accordion-body p-0">
                                                    @if ($editable)
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-bordered table-hover mb-0 align-middle mahjong-group-score-table"
                                                                   data-grup-id="{{ $historyGrup->id }}"
                                                                   data-grup-name="{{ $historyGrup->nama }}"
                                                                   data-update-url="{{ route('admin.matchmaking.mahjong-group-point-entries.update', $historyGrup) }}"
                                                                   data-score-scope="table">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th class="text-center mahjong-ronde-head">Ronde</th>
                                                                        @foreach ($historyMembers as $member)
                                                                            <th class="text-center mahjong-player-head"
                                                                                data-member-id="{{ $member->id }}"
                                                                                data-label="{{ $member->display_name }}">
                                                                                <div class="fw-semibold">
                                                                                    <span class="mahjong-player-name">{{ $member->display_name }}</span>
                                                                                </div>
                                                                            </th>
                                                                        @endforeach
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @forelse ($historyRounds as $roundIndex => $round)
                                                                        <tr class="mahjong-round-row" data-round="{{ $roundIndex + 1 }}">
                                                                            <td class="text-center mahjong-round-number-cell">
                                                                                <button type="button"
                                                                                        class="btn btn-link btn-sm text-decoration-none fw-semibold p-0 btn-mahjong-edit-ronde"
                                                                                        title="Edit ronde {{ $roundIndex + 1 }}">
                                                                                    <span class="mahjong-round-label">{{ $roundIndex + 1 }}</span>
                                                                                    <i class="bi bi-pencil-square ms-1"></i>
                                                                                </button>
                                                                            </td>
                                                                            @foreach ($historyMembers as $member)
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
                                                                            <td colspan="{{ $historyColCount }}" class="text-center text-muted py-3">
                                                                                Belum ada ronde.
                                                                            </td>
                                                                        </tr>
                                                                    @endforelse
                                                                </tbody>
                                                                @if ($historyRounds !== [])
                                                                    <tfoot class="table-light">
                                                                        <tr class="mahjong-adjustment-row">
                                                                            <th>Bonus/Penalti</th>
                                                                            @foreach ($historyMembers as $member)
                                                                                <th class="text-center">
                                                                                    <span class="mahjong-penyesuaian" data-member-id="{{ $member->id }}" data-poin="{{ (int) $member->poin_penyesuaian }}">
                                                                                        {{ (int) $member->poin_penyesuaian > 0 ? '+' : '' }}{{ (int) $member->poin_penyesuaian }}
                                                                                    </span>
                                                                                </th>
                                                                            @endforeach
                                                                        </tr>
                                                                        <tr class="mahjong-subtotal-row">
                                                                            <th>Subtotal</th>
                                                                            @foreach ($historyMembers as $member)
                                                                                @php
                                                                                    $memberEntries = $member->relationLoaded('poinEntries')
                                                                                        ? $member->poinEntries
                                                                                        : $member->poinEntries()->get();
                                                                                    $poinBabak = (int) $memberEntries->sum('poin') + (int) $member->poin_penyesuaian;
                                                                                    $wins = (int) $memberEntries->where('is_winner', true)->count();
                                                                                @endphp
                                                                                <th class="text-center">
                                                                                    <span class="mahjong-subtotal" data-member-id="{{ $member->id }}">
                                                                                        {{ $poinBabak }} ({{ $wins }})
                                                                                    </span>
                                                                                </th>
                                                                            @endforeach
                                                                        </tr>
                                                                        <tr class="mahjong-total-row">
                                                                            <th>Total</th>
                                                                            @foreach ($historyMembers as $member)
                                                                                <th class="text-center">
                                                                                    <span class="badge text-bg-primary mahjong-total-poin" data-member-id="{{ $member->id }}">
                                                                                        {{ $member->total_poin }}
                                                                                    </span>
                                                                                </th>
                                                                            @endforeach
                                                                        </tr>
                                                                    </tfoot>
                                                                @endif
                                                            </table>
                                                        </div>
                                                    @else
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-hover mb-0 align-middle">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th>Pemain</th>
                                                                        <th class="text-center" style="width:5rem" title="Jumlah menang (ronde)">W</th>
                                                                        <th class="text-center" style="width:8rem">Poin Babak</th>
                                                                        <th class="text-center" style="width:14rem">Entri Poin</th>
                                                                        <th class="text-center" style="width:7rem">Total akhir</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($historyGrup->members->sortByDesc(function ($member) {
                                                                        $entries = $member->relationLoaded('poinEntries')
                                                                            ? $member->poinEntries
                                                                            : $member->poinEntries()->get();
                                                                        $sum = (int) $entries->sum('poin') + (int) $member->poin_penyesuaian;

                                                                        return $sum !== (int) $member->poin_penyesuaian ? $sum : (int) $member->poin_babak;
                                                                    })->values() as $member)
                                                                        @php
                                                                            $memberEntries = $member->relationLoaded('poinEntries')
                                                                                ? $member->poinEntries
                                                                                : $member->poinEntries()->get();
                                                                            $poinBabak = (int) $memberEntries->sum('poin') + (int) $member->poin_penyesuaian;
                                                                            if ($poinBabak === (int) $member->poin_penyesuaian && (int) $member->poin_didapat !== 0) {
                                                                                $poinBabak = (int) $member->poin_babak;
                                                                            }
                                                                            $wins = (int) $memberEntries->where('is_winner', true)->count();
                                                                            $totalAkhir = $member->total_poin;
                                                                            $penyesuaian = (int) $member->poin_penyesuaian;
                                                                            $memberPemainIds = array_values(array_filter([
                                                                                (int) ($member->id_pemain ?: optional($member->turnamenPeserta)->id_pemain1),
                                                                            ]));
                                                                        @endphp
                                                                        <tr>
                                                                            <td class="fw-semibold">
                                                                                @if ($linkPemain)
                                                                                    <x-pemain-names :pemain-ids="$memberPemainIds" :nama="$member->display_name" />
                                                                                @else
                                                                                    {{ $member->display_name }}
                                                                                @endif
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <span class="badge text-bg-warning text-dark">{{ $wins }}</span>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <span class="badge text-bg-info">{{ $poinBabak }}</span>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <div class="d-flex flex-wrap justify-content-center gap-1">
                                                                                    @forelse ($memberEntries as $entry)
                                                                                        <span class="badge text-bg-light text-dark border {{ $entry->is_winner ? 'border-warning' : '' }}">
                                                                                            @if ($entry->is_winner)
                                                                                                <i class="bi bi-trophy-fill text-warning me-1" title="Pemenang ronde"></i>
                                                                                            @endif
                                                                                            {{ (int) $entry->poin > 0 ? '+' : '' }}{{ (int) $entry->poin }}
                                                                                        </span>
                                                                                    @empty
                                                                                        <span class="text-muted small">—</span>
                                                                                    @endforelse
                                                                                    @if ($penyesuaian !== 0)
                                                                                        <span class="badge text-bg-secondary" title="Bonus/penalti babak">
                                                                                            {{ $penyesuaian > 0 ? '+' : '' }}{{ $penyesuaian }}
                                                                                        </span>
                                                                                    @endif
                                                                                </div>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <span class="badge text-bg-primary">{{ $totalAkhir }}</span>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Tidak ada ronde tersimpan untuk babak ini.</p>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
