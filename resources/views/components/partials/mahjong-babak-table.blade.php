@props([
    'babak',
    'rounds' => collect(),
    'rows' => collect(),
    'advanceKind' => 'none',
    'advanceNote' => null,
    'rankingNote' => null,
])

@php
    $rounds = collect($rounds);
    $rows = collect($rows);
    $showGrup = $rows->contains(fn ($row) => filled($row['grup_nama'] ?? null));
    $colCount = 6 + $rounds->count() + ($showGrup ? 1 : 0);
    $rankingNote = $rankingNote ?: 'Peringkat berdasarkan Total babak, lalu Menang, lalu Akumulasi.';
@endphp

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width:3rem">#</th>
                        <th>Pemain</th>
                        @if ($showGrup)
                            <th>Grup</th>
                        @endif
                        @foreach ($rounds as $round)
                            <th class="text-center">{{ $round['label'] ?? ('Ronde ' . ($round['round'] ?? '')) }}</th>
                        @endforeach
                        <th class="text-center" title="Kriteria 1">Total Babak</th>
                        <th class="text-center" title="Kriteria 2: jumlah menang">W</th>
                        <th class="text-center" title="Kriteria 3">Akumulasi</th>
                        <th class="text-center" style="width:7rem">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $status = $row['advance_status'] ?? null;
                            $rowClass = '';
                            if (in_array($status, ['lolos', 'pratinjau', 'juara'], true)) {
                                $rowClass = 'table-success';
                            } elseif ($status === 'seri') {
                                $rowClass = 'table-warning';
                            }
                            $cutlineStyle = ! empty($row['is_cutline'])
                                ? 'border-bottom: 2px solid var(--bs-success);'
                                : '';
                        @endphp
                        <tr class="{{ $rowClass }}" @if ($cutlineStyle) style="{{ $cutlineStyle }}" @endif>
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
                            @if ($showGrup)
                                <td class="text-muted">{{ $row['grup_nama'] ?? '—' }}</td>
                            @endif
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
                            <td class="text-center">{{ $row['menang'] ?? 0 }}</td>
                            <td class="text-center text-muted">{{ $row['poin_akumulasi'] ?? 0 }}</td>
                            <td class="text-center">
                                @if ($status === 'lolos')
                                    <span class="badge text-bg-success">Lolos</span>
                                @elseif ($status === 'pratinjau')
                                    <span class="badge text-bg-success">Lolos*</span>
                                @elseif ($status === 'seri')
                                    <span class="badge text-bg-warning text-dark">Seri</span>
                                @elseif ($status === 'juara')
                                    <span class="badge text-bg-warning text-dark">Juara</span>
                                @elseif ($status === 'runner_up')
                                    <span class="badge text-bg-light text-dark border">Ke-2</span>
                                @elseif ($status === 'third')
                                    <span class="badge text-bg-light text-dark border">Ke-3</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $colCount }}" class="text-center text-muted py-4">
                                Belum ada data pemain pada babak ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 border-top bg-light small text-muted">
            {{ $rankingNote }}
            @if ($advanceNote)
                <div class="mt-1">
                    @if ($advanceKind === 'preview' && $rows->contains(fn ($row) => ($row['advance_status'] ?? null) === 'pratinjau'))
                        <span class="badge text-bg-success me-1">Lolos*</span> pratinjau berdasarkan total.
                    @endif
                    {{ $advanceNote }}
                </div>
            @endif
        </div>
    </div>
</div>
