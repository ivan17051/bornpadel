@extends('layouts.guest')

@section('title', 'Beranda')

@push('styles')
<style>
    .guest-card-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.35rem 0.75rem;
    }

    .guest-card-meta-price {
        margin-left: auto;
        text-align: right;
    }

    .guest-card-syarat-text {
        white-space: pre-line;
    }

    .guest-card-actions {
        display: grid;
        gap: 0.5rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .guest-card-actions--3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .guest-card-actions .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.5;
        min-height: 2.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
    }

    @media (max-width: 575.98px) {
        .guest-card-actions--3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .guest-card-actions--3 .btn:last-child {
            grid-column: 1 / -1;
        }
    }
</style>
@endpush

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-11 col-xl-10">
        <div class="text-center mb-4 mb-md-5">
            <h1 class="h3 fw-bold mb-2">Turnamen Born Padel</h1>
            <p class="text-muted mb-0">Daftar turnamen terbuka atau lihat klasemen dan bracket turnamen berlangsung.</p>
        </div>

        @if ($publicTournaments->isNotEmpty())
            <div class="row g-4">
                @foreach ($publicTournaments as $item)
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

                                @if (! $item->isRegistrationOpen() && $item->status === 'completed' && $item->champion_label)
                                    <p class="mb-3">
                                        <span class="text-muted small text-uppercase">
                                            <i class="bi bi-trophy me-1"></i> Juara
                                        </span>
                                        <span class="fw-semibold d-block">{{ $item->champion_label }}</span>
                                    </p>
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
                @endforeach
            </div>
        @else
            <div class="card guest-card text-center py-5 px-3">
                <div class="card-body">
                    <i class="bi bi-calendar-x display-4 text-muted mb-3 d-block"></i>
                    <h2 class="h4 fw-bold mb-2">Belum Ada Turnamen Aktif</h2>
                    <p class="text-muted mb-0 mx-auto" style="max-width: 28rem;">
                        Saat ini tidak ada turnamen terbuka, berlangsung, atau selesai dalam 30 hari terakhir.
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
