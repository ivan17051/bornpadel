@extends('layouts.guest')

@section('title', 'Beranda')

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

                                @if ($item->isRegistrationOpen())
                                    <p class="fw-semibold text-primary mb-3">
                                        Rp {{ number_format($item->harga, 0, ',', '.') }}
                                        <span class="text-muted fw-normal small">
                                            / {{ $item->isDouble() ? 'pasang' : 'peserta' }}
                                        </span>
                                    </p>
                                @elseif ($item->status === 'completed' && $item->champion_label)
                                    <p class="mb-3">
                                        <span class="text-muted small text-uppercase">
                                            <i class="bi bi-trophy me-1"></i> Juara
                                        </span>
                                        <span class="fw-semibold d-block">{{ $item->champion_label }}</span>
                                    </p>
                                @endif

                                <div class="mt-auto d-flex flex-wrap gap-2">
                                    @if ($item->isRegistrationOpen())
                                        <a href="{{ route('guest.register', ['id_turnamen' => $item->id]) }}"
                                           class="btn btn-bp flex-grow-1">
                                            <i class="bi bi-person-plus me-1"></i> Daftar
                                        </a>
                                    @else
                                        <a href="{{ route('guest.standings', ['id_turnamen' => $item->id]) }}"
                                           class="btn btn-outline-success flex-grow-1">
                                            <i class="bi bi-bar-chart-steps me-1"></i> Klasemen
                                        </a>
                                        <a href="{{ route('guest.bracket', ['id_turnamen' => $item->id]) }}"
                                           class="btn btn-outline-primary flex-grow-1">
                                            <i class="bi bi-diagram-2 me-1"></i> Bracket
                                        </a>
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
