@extends('layouts.guest')

@section('title', $pemain->nama)

@section('content')
@php
    $phoneService = app(\App\Services\PhoneNumberService::class);
    $parsedPhone = $phoneService->parse($pemain->no_hp);
@endphp

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card guest-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                    <x-pemain-avatar :pemain="$pemain" :size="120" />
                    <div class="text-center text-md-start flex-grow-1">
                        <h1 class="h3 fw-bold mb-2">{{ $pemain->nama }}</h1>
                        <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-2 mb-3">
                            <span class="badge text-bg-light text-dark border">
                                {{ $pemain->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}
                            </span>
                            @if ($pemain->usia)
                                <span class="badge text-bg-light text-dark border">{{ $pemain->usia }} tahun</span>
                            @endif
                            <span class="badge text-bg-primary">Rating {{ number_format($pemain->rating, 1) }}</span>
                        </div>
                        <div class="row g-3 small">
                            <!-- <div class="col-sm-6">
                                <div class="text-muted text-uppercase">Nomor HP</div>
                                <strong>{{ $parsedPhone['country_code'] }} {{ $parsedPhone['local_number'] }}</strong>
                            </div> -->
                            <div class="col-sm-6">
                                <div class="text-muted text-uppercase">Total Poin</div>
                                <strong class="text-primary fs-5">{{ number_format($pemain->total_poin ?? 0) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card guest-card">
            <div class="card-header py-3">
                <i class="bi bi-clock-history me-2"></i> Riwayat Turnamen
            </div>
            <div class="card-body p-0">
                @if ($tournamentHistory->isEmpty())
                    <div class="p-4 text-center text-muted">Belum ada riwayat turnamen.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Turnamen</th>
                                    <th>Jenis</th>
                                    <th>Partner</th>
                                    <th>Status</th>
                                    <th class="d-none d-md-table-cell">Terdaftar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tournamentHistory as $entry)
                                    <tr>
                                        <td class="fw-semibold">{{ optional($entry['turnamen'])->nama ?? '—' }}</td>
                                        <td>{{ optional($entry['turnamen'])->jenis_label ?? '—' }}</td>
                                        <td>
                                            @if ($entry['partner'])
                                                <x-pemain-link :pemain="$entry['partner']" />
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge status-badge-{{ $entry['status'] }}">
                                                {{ ucfirst($entry['status']) }}
                                            </span>
                                        </td>
                                        <td class="d-none d-md-table-cell text-muted small">
                                            {{ optional($entry['registered_at'])->format('d M Y') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="{{ route('guest.landing') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</div>
@endsection
