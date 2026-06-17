@extends('layouts.guest')

@section('title', 'Beranda')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        @if ($turnamen)
            <div class="guest-hero mb-4">
                <div class="position-relative" style="z-index: 1;">
                    <span class="badge {{ $turnamen->isRegistrationOpen() ? 'badge-open' : 'bg-primary' }} mb-3">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>
                        {{ $turnamen->isRegistrationOpen() ? 'Pendaftaran Dibuka' : 'Turnamen Berlangsung' }}
                    </span>
                    <h1 class="display-6 fw-bold mb-2">{{ $turnamen->nama }}</h1>
                    <p class="mb-0 opacity-75">Bergabunglah dalam turnamen padel terbaik di kota ini.</p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-5">
                    <div class="card guest-card h-100">
                        <div class="card-header py-3">
                            <i class="bi bi-cash-coin me-2 text-primary"></i> Biaya Pendaftaran
                        </div>
                        <div class="card-body">
                            <div class="display-6 fw-bold text-primary mb-1">
                                Rp {{ number_format($turnamen->harga, 0, ',', '.') }}
                            </div>
                            <p class="text-muted small mb-0">Per peserta. Pembayaran dilakukan setelah verifikasi pendaftaran.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card guest-card h-100">
                        <div class="card-header py-3">
                            <i class="bi bi-card-checklist me-2 text-primary"></i> Syarat & Ketentuan
                        </div>
                        <div class="card-body">
                            @if ($turnamen->syarat)
                                <div class="text-secondary" style="white-space: pre-line;">{{ $turnamen->syarat }}</div>
                            @else
                                <p class="text-muted mb-0">Belum ada syarat khusus untuk turnamen ini.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5 d-flex flex-wrap justify-content-center gap-2">
                @if ($turnamen->isRegistrationOpen())
                    <a href="{{ route('guest.register') }}" class="btn btn-bp btn-lg px-5">
                        <i class="bi bi-person-plus me-2"></i> Daftar Sekarang
                    </a>
                @endif
                @if (in_array($turnamen->status, ['ongoing', 'completed']))
                    <a href="{{ route('guest.standings') }}" class="btn btn-outline-success btn-lg px-4">
                        <i class="bi bi-bar-chart-steps me-2"></i> Klasemen
                    </a>
                    <a href="{{ route('guest.bracket') }}" class="btn btn-outline-primary btn-lg px-4">
                        <i class="bi bi-diagram-2 me-2"></i> Bracket
                    </a>
                @endif
            </div>

            @if (isset($standings) && $standings->isNotEmpty())
                <div class="mt-5">
                    <x-group-leaderboard :standings="$standings" :turnamen="$turnamen" :refreshable="true" />
                </div>
            @endif

            @if (! empty($bracket))
                <div class="mt-5">
                    <x-tournament-bracket :bracket="$bracket" :turnamen="$turnamen" :refreshable="true" />
                </div>
            @endif
        @else
            <div class="card guest-card text-center py-5 px-3">
                <div class="card-body">
                    <i class="bi bi-calendar-x display-4 text-muted mb-3 d-block"></i>
                    <h2 class="h4 fw-bold mb-2">Belum Ada Turnamen Aktif</h2>
                    <p class="text-muted mb-0 mx-auto" style="max-width: 28rem;">
                        Saat ini tidak ada turnamen dengan status pendaftaran terbuka.
                        Silakan kembali lagi nanti.
                    </p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if (isset($standings) && $standings->isNotEmpty())
<script src="{{ asset('public/js/leaderboard.js') }}"></script>
@endif
@if (! empty($bracket))
<script src="{{ asset('public/js/bracket.js') }}"></script>
@endif
@endpush
