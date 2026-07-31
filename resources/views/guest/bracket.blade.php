@extends('layouts.guest')

@section('title', 'Bracket Knockout')

@section('og')
    @include('guest.partials.og-meta', [
        'ogTurnamen' => $turnamen ?? null,
        'ogUrl' => isset($turnamen) && $turnamen
            ? route('guest.bracket', ['id_turnamen' => $turnamen->id])
            : route('guest.bracket'),
        'ogTitle' => isset($turnamen) && $turnamen
            ? 'Bracket — ' . $turnamen->nama
            : 'Bracket — Born Padel',
    ])
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-12">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold">Bracket Knockout</h1>
            @if ($turnamen)
                <p class="text-muted mb-0">{{ $turnamen->nama }}</p>
            @endif
        </div>

        <x-tournament-bracket :bracket="$bracket" :turnamen="$turnamen" :refreshable="true" />

        <div class="text-center mt-4 d-flex flex-wrap justify-content-center gap-2">
            @if ($turnamen)
                <a href="{{ route('guest.standings', ['id_turnamen' => $turnamen->id]) }}" class="btn btn-outline-primary">
                    <i class="bi bi-bar-chart-steps me-1"></i> Klasemen Grup
                </a>
            @endif
            <a href="{{ route('guest.landing') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Beranda
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('public/js/bracket.js') }}?v={{ @filemtime(base_path('public/js/bracket.js')) }}"></script>
@endpush
