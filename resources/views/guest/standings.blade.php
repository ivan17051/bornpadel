@extends('layouts.guest')

@section('title', 'Klasemen')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold">{{ optional($turnamen)->isMahjong() ? 'Klasemen Mahjong' : 'Klasemen Grup' }}</h1>
            @if ($turnamen)
                <p class="text-muted mb-0">{{ $turnamen->nama }}</p>
            @endif
        </div>

        @if ($turnamen && $turnamen->isMahjong())
            <x-mahjong-leaderboard :standings="$standings" :turnamen="$turnamen" :refreshable="true" />
        @else
            <x-group-leaderboard :standings="$standings" :turnamen="$turnamen" :refreshable="true" />
        @endif

        <div class="text-center mt-4">
            <a href="{{ route('guest.landing') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('public/js/leaderboard.js') }}"></script>
@endpush
