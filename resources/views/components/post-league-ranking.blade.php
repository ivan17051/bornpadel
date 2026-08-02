@props([
    'ranking' => null,
    'turnamen' => null,
])

@php
    $sections = collect($ranking['sections'] ?? []);
    $hasBracket = (bool) ($ranking['has_bracket'] ?? false);
    $isDouble = (bool) ($ranking['is_double'] ?? optional($turnamen)->playsAsPairs());
    $unitLabel = $isDouble ? 'Pasangan' : 'Pemain';
@endphp

@if ($sections->isNotEmpty())
    <div class="post-league-ranking mt-4" id="post-league-ranking">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">
                <i class="bi bi-list-ol me-2"></i>Peringkat Lintas Grup
                @if ($turnamen)
                    <small class="text-muted fw-normal">— {{ $turnamen->nama }}</small>
                @endif
            </h5>
            @if ($hasBracket)
                <span class="badge text-bg-success">
                    <i class="bi bi-flag-fill me-1"></i> Highlight: lolos knockout
                </span>
            @endif
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width:3rem">#</th>
                                <th>{{ $unitLabel }}</th>
                                <th>Grup</th>
                                <th class="text-center">Poin</th>
                                <th class="text-center d-none d-sm-table-cell">Set</th>
                                <th class="text-center d-none d-md-table-cell" title="Selisih game">GD</th>
                                @if ($hasBracket)
                                    <th class="text-center" style="width:5.5rem"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sections as $section)
                                <tr class="table-secondary">
                                    <td colspan="{{ $hasBracket ? 7 : 6 }}" class="fw-semibold text-uppercase small py-2">
                                        {{ $section['label'] ?? ('Juara ' . ($section['place'] ?? '')) }}
                                    </td>
                                </tr>
                                @foreach ($section['rows'] ?? [] as $row)
                                    <tr class="{{ ! empty($row['advances']) ? 'table-success' : '' }}">
                                        <td class="text-center fw-bold">{{ $row['overall_rank'] }}</td>
                                        <td class="fw-semibold">
                                            <x-pemain-names :pemain-ids="$row['pemain_ids'] ?? []" :nama="$row['nama']" />
                                        </td>
                                        <td class="text-muted">{{ $row['grup'] ?? '—' }}</td>
                                        <td class="text-center">
                                            <span class="badge text-bg-primary">{{ $row['poin_didapat'] ?? 0 }}</span>
                                        </td>
                                        <td class="text-center d-none d-sm-table-cell">{{ $row['set_menang'] ?? 0 }}</td>
                                        <td class="text-center d-none d-md-table-cell">
                                            {{ $row['games_diff_label'] ?? \App\Models\GrupMember::formatGameDifference($row['games_menang'] ?? 0) }}
                                        </td>
                                        @if ($hasBracket)
                                            <td class="text-center">
                                                @if (! empty($row['advances']))
                                                    <span class="badge text-bg-success">Lolos</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
@endif
