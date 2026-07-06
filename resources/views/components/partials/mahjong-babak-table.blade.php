@props([
    'babak',
    'rounds' => collect(),
    'rows' => collect(),
])

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width:3rem">#</th>
                        <th>Pemain</th>
                        @foreach ($rounds as $round)
                            <th class="text-center">{{ $round['label'] ?? ('Ronde ' . ($round['round'] ?? '')) }}</th>
                        @endforeach
                        <th class="text-center">Total Babak</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="{{ ($row['rank'] ?? 0) === 1 ? 'table-success' : '' }}">
                            <td class="text-center fw-bold">
                                @if (($row['rank'] ?? 0) === 1)
                                    <i class="bi bi-trophy-fill text-warning"></i>
                                @else
                                    {{ $row['rank'] ?? '—' }}
                                @endif
                            </td>
                            <td class="fw-semibold">
                                <x-pemain-names :pemain-ids="$row['pemain_ids'] ?? []" :nama="$row['nama']" />
                            </td>
                            @foreach ($row['round_scores'] ?? [] as $score)
                                <td class="text-center">
                                    <span class="badge text-bg-secondary">{{ $score }}</span>
                                </td>
                            @endforeach
                            @for ($i = count($row['round_scores'] ?? []); $i < $rounds->count(); $i++)
                                <td class="text-center text-muted">—</td>
                            @endfor
                            <td class="text-center">
                                <span class="badge text-bg-primary">{{ $row['total_babak'] ?? 0 }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 3 + $rounds->count() }}" class="text-center text-muted py-4">
                                Belum ada data pemain pada babak ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
