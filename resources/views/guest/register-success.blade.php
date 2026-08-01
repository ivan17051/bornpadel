@extends('layouts.guest')

@section('title', 'Pendaftaran Berhasil')

@section('og')
    @include('guest.partials.og-meta', [
        'ogTurnamen' => $turnamen ?? null,
        'ogUrl' => isset($turnamen) && $turnamen
            ? route('guest.register', ['id_turnamen' => $turnamen->id])
            : route('guest.landing'),
        'ogTitle' => isset($turnamen) && $turnamen
            ? 'Pendaftaran berhasil — ' . $turnamen->nama
            : 'Pendaftaran berhasil — Born Padel',
    ])
@endsection

@section('content')
@php
    $isDouble = $turnamen && $turnamen->requiresPairRegistration();
    $randomizesPartners = $turnamen && $turnamen->randomizesPartners();
    $isGroupRegistration = $isGroupRegistration ?? false;
    $namaGrup = $namaGrup ?? null;
    $playerLabels = $isGroupRegistration
        ? ['Pemain 1', 'Pemain 2', 'Pemain 3', 'Pemain 4']
        : ($isDouble ? ['Pemain 1', 'Pemain 2'] : ['Peserta']);
@endphp

<div class="row justify-content-center">
    <div class="col-lg-6 col-xl-5">
        <div class="card guest-card text-center">
            <div class="card-body p-4 p-md-5">
                @if ($playerModels->isNotEmpty())
                    <div class="d-flex justify-content-center gap-3 mb-4 flex-wrap">
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
                </p>

                @if ($isGroupRegistration && $namaGrup)
                    <div class="alert alert-light border text-start mb-4">
                        <i class="bi bi-people-fill me-2"></i>
                        Grup <strong>{{ $namaGrup }}</strong> berhasil didaftarkan ({{ count($players) }} pemain).
                    </div>
                @elseif ($randomizesPartners)
                    <div class="alert alert-light border text-start mb-4">
                        <i class="bi bi-shuffle me-2"></i>
                        Pasangan untuk turnamen single akan ditentukan secara acak setelah pendaftaran ditutup oleh panitia.
                    </div>
                @elseif ($isDouble && count($players) === 1)
                    <div class="alert alert-light border text-start mb-4">
                        <i class="bi bi-people me-2"></i>
                        Pendaftaran individu berhasil. Pasangan akan diatur oleh panitia sebelum pertandingan dimulai.
                    </div>
                @endif

                @foreach ($players as $index => $player)
                    @php
                        $playerStatus = $player['status'] ?? 'unpaid';
                    @endphp
                    <div class="card bg-light border-0 text-start {{ $loop->last ? 'mb-4' : 'mb-3' }}">
                        <div class="card-body py-3">
                            @if ($isDouble || $isGroupRegistration)
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
                                    @if ($playerStatus === 'paid')
                                        <span class="badge bg-info text-dark">
                                            <i class="bi bi-hourglass-split me-1"></i> Menunggu Verifikasi Admin
                                        </span>
                                    @elseif ($playerStatus === 'approved')
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i> Disetujui
                                        </span>
                                    @else
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-credit-card me-1"></i> Belum Upload Bukti Bayar
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <p class="small text-muted mb-4">
                    @if (collect($players)->contains(fn ($player) => ($player['status'] ?? 'unpaid') === 'unpaid'))
                        Silakan unggah bukti pembayaran jika belum dilakukan. Tim kami akan memverifikasi setelah bukti diterima.
                    @else
                        Tim kami akan menghubungi Anda melalui WhatsApp setelah pendaftaran disetujui.
                    @endif
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
