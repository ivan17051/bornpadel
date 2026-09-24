{{-- Mahjong Tim meja history: babak → ronde → meja. Pass $editable = true on matchmaking tab. --}}
@php
    $mahjongTeamHistory = $mahjongTeamHistory ?? collect();
    $idPrefix = $idPrefix ?? 'mahjong-team-history';
    $linkPemain = $linkPemain ?? false;
    $cardClass = $cardClass ?? 'card mb-3';
    $editable = $editable ?? false;
@endphp

@if ($mahjongTeamHistory->isNotEmpty())
    <div class="{{ $cardClass }}" id="{{ $idPrefix }}-card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h6 class="mb-0"><i class="bi bi-clock-history me-1"></i> Riwayat Meja</h6>
            @if ($editable)
                <span class="small text-muted">Klik nomor ronde untuk mengubah skor.</span>
            @endif
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs flex-wrap" id="{{ $idPrefix }}-babak-tabs" role="tablist">
                @foreach ($mahjongTeamHistory as $index => $babakSection)
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
                @foreach ($mahjongTeamHistory as $index => $babakSection)
                    <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                         id="{{ $idPrefix }}-babak-{{ $babakSection['babak'] }}"
                         role="tabpanel"
                         aria-labelledby="{{ $idPrefix }}-babak-{{ $babakSection['babak'] }}-tab">
                        <div class="accordion mahjong-team-history-ronde-accordion"
                             id="{{ $idPrefix }}-b{{ $babakSection['babak'] }}">
                            @foreach ($babakSection['rondes'] as $rondeIndex => $rondeSection)
                                @php
                                    $rondeCollapseId = $idPrefix.'-b'.$babakSection['babak'].'-r'.$rondeSection['ronde'];
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
                                         data-bs-parent="#{{ $idPrefix }}-b{{ $babakSection['babak'] }}">
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
                                                        <table class="table table-sm table-bordered table-hover mb-0 align-middle {{ $editable ? 'mahjong-group-score-table' : '' }}"
                                                               @if ($editable)
                                                                   data-grup-id="{{ $histMeja->id }}"
                                                                   data-grup-name="{{ $histMeja->nama }}"
                                                                   data-update-url="{{ route('admin.matchmaking.mahjong-team-meja-point-entries.update', $histMeja) }}"
                                                                   data-score-scope="table"
                                                               @endif>
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th class="text-center {{ $editable ? 'mahjong-ronde-head' : '' }}" style="width:4.5rem">Ronde</th>
                                                                    @foreach ($histMembers as $histMember)
                                                                        @php
                                                                            $histPemainIds = array_values(array_filter([
                                                                                (int) ($histMember->id_pemain ?: optional($histMember->turnamenPeserta)->id_pemain1),
                                                                            ]));
                                                                            $histLabel = trim($histMember->display_name.(optional($histMember->grup)->nama ? ' ('.$histMember->grup->nama.')' : ''));
                                                                        @endphp
                                                                        <th class="text-center {{ $editable ? 'mahjong-player-head' : '' }}"
                                                                            @if ($editable)
                                                                                data-member-id="{{ $histMember->id }}"
                                                                                data-label="{{ $histLabel }}"
                                                                            @endif>
                                                                            <div class="fw-semibold">
                                                                                @if ($linkPemain)
                                                                                    <x-pemain-names :pemain-ids="$histPemainIds" :nama="$histMember->display_name" />
                                                                                @else
                                                                                    <span class="mahjong-player-name">{{ $histMember->display_name }}</span>
                                                                                @endif
                                                                            </div>
                                                                            @if (optional($histMember->grup)->nama)
                                                                                <div class="small text-muted fw-normal">{{ $histMember->grup->nama }}</div>
                                                                            @endif
                                                                        </th>
                                                                    @endforeach
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @forelse ($histRounds as $histRoundIndex => $histRound)
                                                                    <tr class="{{ $editable ? 'mahjong-round-row' : '' }}" @if ($editable) data-round="{{ $histRoundIndex + 1 }}" @endif>
                                                                        <td class="text-center {{ $editable ? 'mahjong-round-number-cell' : 'fw-semibold text-muted' }}">
                                                                            @if ($editable)
                                                                                <button type="button"
                                                                                        class="btn btn-link btn-sm text-decoration-none fw-semibold p-0 btn-mahjong-edit-ronde"
                                                                                        title="Edit ronde {{ $histRoundIndex + 1 }}">
                                                                                    <span class="mahjong-round-label">{{ $histRoundIndex + 1 }}</span>
                                                                                    <i class="bi bi-pencil-square ms-1"></i>
                                                                                </button>
                                                                            @else
                                                                                {{ $histRoundIndex + 1 }}
                                                                            @endif
                                                                        </td>
                                                                        @foreach ($histMembers as $histMember)
                                                                            @php
                                                                                $histEntry = $histRound[(int) $histMember->id] ?? null;
                                                                            @endphp
                                                                            <td class="text-center {{ $editable ? 'mahjong-round-cell' : '' }}"
                                                                                @if ($editable) data-member-id="{{ $histMember->id }}" @endif>
                                                                                @if ($histEntry)
                                                                                    <span class="badge text-bg-light text-dark border {{ $editable ? 'mahjong-poin-entry' : '' }} {{ $histEntry->is_winner ? 'border-warning' : '' }}"
                                                                                          @if ($editable)
                                                                                              data-entry-id="{{ $histEntry->id }}"
                                                                                              data-poin="{{ (int) $histEntry->poin }}"
                                                                                              data-is-winner="{{ $histEntry->is_winner ? '1' : '0' }}"
                                                                                          @endif>
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
                                                                                <span @if ($editable) class="mahjong-subtotal" data-member-id="{{ $histMember->id }}" @endif>
                                                                                    {{ (int) ($histTotals[(int) $histMember->id] ?? 0) }}
                                                                                    ({{ (int) ($histWins[(int) $histMember->id] ?? 0) }})
                                                                                </span>
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
