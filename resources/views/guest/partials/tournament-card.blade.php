@php
    if ($item->status === 'open') {
        $statusClass = 'success';
        $statusLabel = 'Pendaftaran Dibuka';
    } elseif ($item->status === 'ongoing') {
        $statusClass = 'primary';
        $statusLabel = 'Berlangsung';
    } else {
        $statusClass = 'secondary';
        $statusLabel = 'Selesai';
    }

    $capacityLabel = $item->maks_peserta
        ? number_format($item->maks_peserta)
        : 'Tidak Terbatas';
    $pesertaLabel = $item->registered_count ?? 0;
    $actionCount = $item->isRegistrationOpen()
        ? 2
        : ($item->isMahjong() ? 2 : 3);
@endphp
<div class="col-12 col-md-6">
    <div class="card guest-card h-100">
        <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                <span class="badge text-bg-light text-dark border">{{ $item->jenis_label }}</span>
            </div>

            <h2 class="h5 fw-bold mb-2">{{ $item->nama }}</h2>

            @if ($item->tanggal)
                <p class="text-muted small mb-2">
                    <i class="bi bi-calendar3 me-1"></i>
                    {{ $item->tanggal->format('d M Y') }}
                </p>
            @endif

            <p class="small guest-card-meta {{ $item->syarat ? 'mb-2' : 'mb-3' }}">
                <span>
                    <i class="bi bi-people me-1 text-primary"></i>
                    <span class="text-muted">Peserta:</span>
                    <strong>{{ $pesertaLabel }} / {{ $capacityLabel }}</strong>
                    @if ($item->maks_peserta)
                        <span class="text-muted">· {{ $item->approved_count ?? 0 }} disetujui</span>
                    @endif
                </span>
                @if ($item->isRegistrationOpen())
                    <span class="guest-card-meta-price fw-semibold text-primary">
                        Rp {{ number_format($item->harga, 0, ',', '.') }}
                        <span class="text-muted fw-normal">
                            / orang
                        </span>
                    </span>
                @endif
            </p>

            @if ($item->syarat)
                <div class="guest-card-syarat mb-3">
                    <div class="text-muted small text-uppercase mb-1">
                        <i class="bi bi-card-text me-1"></i> Syarat
                    </div>
                    <div class="text-secondary small guest-card-syarat-text">{{ $item->syarat }}</div>
                </div>
            @endif

            <div class="mt-auto guest-card-actions guest-card-actions--{{ $actionCount }}">
                <a href="{{ route('guest.participants', ['id_turnamen' => $item->id]) }}"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-people me-1"></i> Daftar Peserta
                </a>
                @if ($item->isRegistrationOpen())
                    <a href="{{ route('guest.register', ['id_turnamen' => $item->id]) }}"
                       class="btn btn-bp">
                        <i class="bi bi-person-plus me-1"></i> Daftar
                    </a>
                @else
                    <a href="{{ route('guest.standings', ['id_turnamen' => $item->id]) }}"
                       class="btn btn-outline-success">
                        <i class="bi bi-bar-chart-steps me-1"></i> Klasemen
                    </a>
                    @if (! $item->isMahjong())
                        <a href="{{ route('guest.bracket', ['id_turnamen' => $item->id]) }}"
                           class="btn btn-outline-primary">
                            <i class="bi bi-diagram-2 me-1"></i> Bracket
                        </a>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
