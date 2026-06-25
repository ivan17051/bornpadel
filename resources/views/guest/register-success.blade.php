@extends('layouts.guest')

@section('title', 'Pendaftaran Berhasil')

@section('content')
@php
    $isDouble = $turnamen && $turnamen->isDouble();
    $playerLabels = $isDouble ? ['Pemain 1', 'Pemain 2'] : ['Peserta'];
@endphp

<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5">
        <div class="card guest-card text-center">
            <div class="card-body p-4 p-md-5">
                @if ($playerModels->isNotEmpty())
                    <div class="d-flex justify-content-center gap-3 mb-4">
                        @foreach ($playerModels as $model)
                            <x-pemain-avatar :pemain="$model" :size="96" class="border" />
                        @endforeach
                    </div>
                @else
                    <div class="success-icon mb-4">
                        <i class="bi bi-check-lg"></i>
                    </div>
                @endif

                <h1 class="h3 fw-bold text-primary mb-2">Pendaftaran Berhasil!</h1>
                <p class="text-muted mb-4">
                    Terima kasih telah mendaftar
                    @if ($turnamen)
                        pada <strong>{{ $turnamen->nama }}</strong>.
                    @endif
                    @if ($isDouble)
                        Data kedua pemain sedang menunggu verifikasi admin.
                    @else
                        Data Anda sedang menunggu verifikasi admin.
                    @endif
                </p>

                @foreach ($players as $index => $player)
                    <div class="card bg-light border-0 text-start {{ $loop->last ? 'mb-4' : 'mb-3' }}">
                        <div class="card-body py-3">
                            @if ($isDouble)
                                <div class="small text-muted text-uppercase fw-semibold mb-2">
                                    {{ $playerLabels[$index] ?? 'Pemain ' . ($index + 1) }}
                                </div>
                            @endif
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="info-label">Nama</div>
                                    <strong>{{ $player['nama'] }}</strong>
                                </div>
                                <div class="col-sm-6">
                                    <div class="info-label">No. HP</div>
                                    <strong>{{ $player['no_hp'] }}</strong>
                                </div>
                                <div class="col-12">
                                    <div class="info-label">Status</div>
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-hourglass-split me-1"></i> Menunggu Verifikasi
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <p class="small text-muted mb-4">
                    Tim kami akan menghubungi Anda melalui WhatsApp setelah pendaftaran disetujui.
                </p>

                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    <a href="{{ route('guest.landing') }}" class="btn btn-bp">
                        <i class="bi bi-house me-1"></i> Kembali ke Beranda
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
