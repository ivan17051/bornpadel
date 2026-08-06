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

    $item->loadMissing('kategori');
    $kategoriList = $item->kategori instanceof \Illuminate\Support\Collection
        ? $item->kategori->sortBy([['urutan', 'asc'], ['id', 'asc']])->values()
        : collect();
    $hasMultipleKategori = $kategoriList->count() > 1;
    $defaultKategori = $hasMultipleKategori ? null : $kategoriList->first();
    $openKategori = $kategoriList->filter(fn ($k) => $k->isRegistrationOpen())->values();

    $capacityLabel = $hasMultipleKategori
        ? ($openKategori->count() . ' kategori')
        : ($item->maks_peserta
            ? number_format($item->maks_peserta)
            : 'Tidak Terbatas');
    $pesertaLabel = $item->registered_count ?? 0;
    $registerUrl = route('guest.register', array_filter([
        'id_turnamen' => $item->id,
        'id_kategori' => optional($defaultKategori)->id,
    ]));
    $standingsBase = ['id_turnamen' => $item->id];
    $actionCount = $item->isRegistrationOpen()
        ? 3
        : (($item->isMahjong() || $item->isFriendly()) ? 2 : 3);
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

            @if ($hasMultipleKategori)
                <div class="kategori-radio-pills mb-2">
                    @foreach ($kategoriList as $kat)
                        <span class="kategori-radio-pill" style="cursor: default; padding: 0.35rem 0.75rem;">
                            <span class="kategori-radio-pill__text" style="font-size:0.8rem">{{ $kat->nama }}</span>
                        </span>
                    @endforeach
                </div>
            @endif

            <p class="small guest-card-meta {{ $item->syarat ? 'mb-2' : 'mb-3' }}">
                <span>
                    <i class="bi bi-people me-1 text-primary"></i>
                    <span class="text-muted">Peserta:</span>
                    <strong>{{ $pesertaLabel }}{{ $hasMultipleKategori ? '' : ' / ' . $capacityLabel }}</strong>
                    @if (! $hasMultipleKategori && $item->maks_peserta)
                        <span class="text-muted">&middot; {{ $item->approved_count ?? 0 }} disetujui</span>
                    @elseif ($hasMultipleKategori)
                        <span class="text-muted">&middot; {{ $capacityLabel }}</span>
                    @endif
                </span>
                @if ($item->isRegistrationOpen() && ! $hasMultipleKategori)
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
                <a href="{{ route('guest.participants', array_filter(array_merge($standingsBase, [
                        'id_kategori' => optional($defaultKategori)->id,
                    ]))) }}"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-people me-1"></i> Daftar Peserta
                </a>
                @if ($item->isRegistrationOpen())
                    <a href="{{ $registerUrl }}"
                       class="btn btn-bp">
                        <i class="bi bi-person-plus me-1"></i> Daftar
                    </a>
                    <button type="button"
                            class="btn btn-outline-secondary js-share-register"
                            data-share-url="{{ $registerUrl }}"
                            data-share-title="{{ $item->nama }}"
                            data-share-text="Daftar turnamen {{ $item->nama }} di Born Padel">
                        <i class="bi bi-share me-1"></i> Bagikan
                    </button>
                @else
                    <a href="{{ route('guest.standings', array_filter(array_merge($standingsBase, [
                            'id_kategori' => optional($defaultKategori)->id,
                        ]))) }}"
                       class="btn btn-outline-success">
                        <i class="bi bi-bar-chart-steps me-1"></i> Klasemen
                    </a>
                    @if (! $item->isMahjong() && ! $item->isFriendly())
                        <a href="{{ route('guest.bracket', array_filter(array_merge($standingsBase, [
                                'id_kategori' => optional($defaultKategori)->id,
                            ]))) }}"
                           class="btn btn-outline-primary">
                            <i class="bi bi-diagram-2 me-1"></i> Bracket
                        </a>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
