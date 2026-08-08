@props([
    'sessions' => collect(),
    'turnamen' => null,
])

@php
    $sessions = collect($sessions ?? []);
@endphp

<div class="friendly-match-schedule mt-4" id="friendly-matches-public">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">
            <i class="bi bi-lightning-charge me-2"></i>Pertandingan per Sesi
        </h5>
    </div>

    @if ($sessions->isEmpty())
        <div class="alert alert-light border text-center mb-0">
            <i class="bi bi-calendar-x text-muted d-block mb-2 fs-4"></i>
            Belum ada slot pertandingan. Slot antar grup dibuat setelah kerangka grup lengkap.
        </div>
    @else
        <div class="d-flex flex-column gap-4">
            @foreach ($sessions as $session)
                @php
                    $label = $session['label'] ?? (
                        ! empty($session['sesi']) ? 'Sesi ' . $session['sesi'] : 'Pertandingan'
                    );
                    $matches = collect($session['matches'] ?? []);
                @endphp
                <div class="card border-0 shadow-sm friendly-match-session"
                     data-sesi="{{ $session['sesi'] ?? '' }}">
                    <div class="card-header bg-transparent border-bottom-0 pb-0">
                        <h6 class="mb-0 text-secondary">
                            <i class="bi bi-clock-history me-1"></i>{{ $label }}
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Grup</th>
                                        <th>Pasangan</th>
                                        <th class="text-center">Skor</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($matches as $match)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">
                                                    {{ $match['grup1'] ?? '—' }} vs {{ $match['grup2'] ?? '—' }}
                                                </div>
                                            </td>
                                            <td>
                                                <div>{{ $match['side1'] ?? 'TBD' }}</div>
                                                <div class="text-muted small">vs {{ $match['side2'] ?? 'TBD' }}</div>
                                                @if (! empty($match['winner']))
                                                    <div class="small text-success mt-1">
                                                        <i class="bi bi-trophy me-1"></i>{{ $match['winner'] }}
                                                    </div>
                                                @elseif (empty($match['pairs_assigned']))
                                                    <div class="small text-warning mt-1">
                                                        <i class="bi bi-people me-1"></i>Belum diisi pasangan
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $match['score'] ?? '—' }}</td>
                                            <td>
                                                @php
                                                    $badge = $match['status_badge'] ?? 'secondary';
                                                    $badgeClass = $badge === 'warning'
                                                        ? 'text-bg-warning text-dark'
                                                        : 'text-bg-' . $badge;
                                                @endphp
                                                <span class="badge {{ $badgeClass }}">
                                                    {{ $match['status_label'] ?? '—' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-muted text-center py-3">
                                                Tidak ada pertandingan di sesi ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
