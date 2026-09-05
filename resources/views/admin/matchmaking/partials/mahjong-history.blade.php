{{-- Read-only Mahjong group history: babak → ronde → groups --}}
@php
    $mahjongHistory = $mahjongHistory ?? collect();
@endphp

@if ($isMahjong && $mahjongHistory->isNotEmpty())
    <div class="card mb-3" id="mahjong-history-card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h6 class="mb-0">
                <i class="bi bi-clock-history me-1"></i> Riwayat Babak
            </h6>
            
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs flex-wrap" id="mahjong-history-babak-tabs" role="tablist">
                @foreach ($mahjongHistory as $index => $babakSection)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $index === 0 ? 'active' : '' }}"
                                id="mahjong-history-babak-{{ $babakSection['babak'] }}-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#mahjong-history-babak-{{ $babakSection['babak'] }}"
                                type="button"
                                role="tab"
                                aria-controls="mahjong-history-babak-{{ $babakSection['babak'] }}"
                                aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                            Babak {{ $babakSection['babak'] }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content pt-3" id="mahjong-history-babak-content">
                @foreach ($mahjongHistory as $index => $babakSection)
                    <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}"
                         id="mahjong-history-babak-{{ $babakSection['babak'] }}"
                         role="tabpanel"
                         aria-labelledby="mahjong-history-babak-{{ $babakSection['babak'] }}-tab">
                        @forelse ($babakSection['rondes'] as $rondeSection)
                            <div class="mb-4">
                                <h6 class="text-muted text-uppercase small mb-2">
                                    Ronde {{ $rondeSection['ronde'] }}
                                    <span class="fw-normal text-lowercase">
                                        — {{ $rondeSection['groups']->count() }} grup
                                    </span>
                                </h6>

                                <div class="accordion mahjong-history-ronde-accordion"
                                     id="mahjong-history-b{{ $babakSection['babak'] }}-r{{ $rondeSection['ronde'] }}">
                                    @foreach ($rondeSection['groups'] as $historyGrup)
                                        @php
                                            $historyCollapseId = 'mahjong-history-g'.$historyGrup->id;
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
                                                            {{ $historyGrup->members->count() }} pemain
                                                        </span>
                                                    </span>
                                                </button>
                                            </h2>
                                            <div id="{{ $historyCollapseId }}"
                                                 class="accordion-collapse collapse"
                                                 aria-labelledby="{{ $historyCollapseId }}-heading"
                                                 data-bs-parent="#mahjong-history-b{{ $babakSection['babak'] }}-r{{ $rondeSection['ronde'] }}">
                                                <div class="accordion-body p-0">
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
                                                                    $sum = (int) $entries->sum('poin');

                                                                    return $sum !== 0 ? $sum : (int) $member->poin_didapat;
                                                                })->values() as $member)
                                                                    @php
                                                                        $memberEntries = $member->relationLoaded('poinEntries')
                                                                            ? $member->poinEntries
                                                                            : $member->poinEntries()->get();
                                                                        $poinBabak = (int) $memberEntries->sum('poin');
                                                                        if ($poinBabak === 0 && (int) $member->poin_didapat !== 0) {
                                                                            $poinBabak = (int) $member->poin_didapat;
                                                                        }
                                                                        $wins = (int) $memberEntries->where('is_winner', true)->count();
                                                                        $totalAkhir = $member->total_poin;
                                                                    @endphp
                                                                    <tr>
                                                                        <td class="fw-semibold">{{ $member->display_name }}</td>
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
