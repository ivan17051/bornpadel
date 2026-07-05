<div class="mt-3">
    <h6 class="fw-semibold mb-3">
        <i class="bi bi-table me-1 text-primary"></i>Rekap Babak {{ $babak }}
    </h6>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:3rem">#</th>
                            <th>Pemain</th>
                            <th class="text-center d-none d-md-table-cell">Grup</th>
                            <th class="text-center">Poin Babak</th>
                            <th class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($standings as $row)
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
                                <td class="text-center text-muted d-none d-md-table-cell">
                                    {{ $row['grup_nama'] ?? '—' }}
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-secondary">{{ $row['poin_babak'] ?? $row['poin_didapat'] ?? 0 }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-primary">{{ $row['total_poin'] ?? 0 }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
