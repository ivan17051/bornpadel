@extends('layouts.guest')

@section('title', 'Pendaftaran')

@section('content')
@php
    $photoService = app(\App\Services\PemainPhotoService::class);
    $previewSrc = $existingPemain && $existingPemain->foto
        ? $photoService->url($existingPemain->foto)
        : null;
    $partnerPreviewSrc = $existingPartner && $existingPartner->foto
        ? $photoService->url($existingPartner->foto)
        : null;
    $isDouble = $turnamen->isDouble();
    $hasPartnerErrors = $errors->hasAny([
        'partner_no_hp', 'partner_nama', 'partner_tgl_lahir', 'partner_gender', 'partner_rating', 'partner_foto',
    ]);
@endphp

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold mb-1">Form Pendaftaran</h1>
            <p class="text-muted mb-0">{{ $turnamen->nama }}</p>
            <span class="badge text-bg-light text-dark border mt-2">{{ $turnamen->jenis_label }}</span>
        </div>

        <div class="card guest-card mb-4">
            <div class="card-body py-3 px-4">
                @if ($isDouble)
                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="info-label">Biaya</div>
                            <strong class="text-primary">Rp {{ number_format($turnamen->harga, 0, ',', '.') }}</strong>
                        </div>
                        <div class="col-4">
                            <div class="info-label">HP Pemain 1</div>
                            <strong>{{ $noHp }}</strong>
                        </div>
                        <div class="col-4">
                            <div class="info-label">HP Pemain 2</div>
                            <strong>{{ $partnerNoHp }}</strong>
                        </div>
                    </div>
                @else
                    <div class="row text-center g-3">
                        <div class="col-6">
                            <div class="info-label">Biaya</div>
                            <strong class="text-primary">Rp {{ number_format($turnamen->harga, 0, ',', '.') }}</strong>
                        </div>
                        <div class="col-6">
                            <div class="info-label">No. HP</div>
                            <strong>{{ $noHp }}</strong>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if (! $isDouble)
            @if ($isExisting)
                <div class="alert alert-info guest-card mb-4">
                    <i class="bi bi-person-check me-2"></i>
                    Data pemain ditemukan. Periksa dan perbarui jika ada perubahan, lalu kirim pendaftaran turnamen ini.
                </div>
            @else
                <div class="alert alert-light border guest-card mb-4">
                    <i class="bi bi-person-plus me-2"></i>
                    Nomor HP belum terdaftar. Lengkapi data di bawah untuk mendaftar.
                </div>
            @endif
        @endif

        <div class="card guest-card">
            <div class="card-header py-3">
                <i class="bi bi-person-vcard me-2"></i> Data Peserta
            </div>
            <div class="card-body p-4">
                <form action="{{ route('guest.register.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="no_hp" value="{{ old('no_hp', $noHp) }}">
                    @if ($isDouble)
                        <input type="hidden" name="partner_no_hp" value="{{ old('partner_no_hp', $partnerNoHp) }}">
                    @endif

                    @if ($isDouble)
                        <ul class="nav nav-tabs mb-4" id="register-player-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $hasPartnerErrors ? '' : 'active' }}"
                                        id="player1-tab-btn"
                                        data-bs-toggle="tab"
                                        data-bs-target="#player1-tab"
                                        type="button"
                                        role="tab"
                                        aria-controls="player1-tab"
                                        aria-selected="{{ $hasPartnerErrors ? 'false' : 'true' }}">
                                    <i class="bi bi-person me-1"></i> Pemain 1
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $hasPartnerErrors ? 'active' : '' }}"
                                        id="player2-tab-btn"
                                        data-bs-toggle="tab"
                                        data-bs-target="#player2-tab"
                                        type="button"
                                        role="tab"
                                        aria-controls="player2-tab"
                                        aria-selected="{{ $hasPartnerErrors ? 'true' : 'false' }}">
                                    <i class="bi bi-person-plus me-1"></i> Pemain 2
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="register-player-tab-content">
                            <div class="tab-pane fade {{ $hasPartnerErrors ? '' : 'show active' }}"
                                 id="player1-tab"
                                 role="tabpanel"
                                 aria-labelledby="player1-tab-btn"
                                 tabindex="0">
                                @if ($isExisting)
                                    <div class="alert alert-info py-2 small mb-3">
                                        <i class="bi bi-person-check me-1"></i>
                                        Data pemain 1 ditemukan. Periksa dan perbarui jika perlu.
                                    </div>
                                @else
                                    <div class="alert alert-light border py-2 small mb-3">
                                        <i class="bi bi-person-plus me-1"></i>
                                        Pemain 1 belum terdaftar. Lengkapi data di bawah.
                                    </div>
                                @endif

                                @include('guest.partials.register-player-fields', [
                                    'prefix' => '',
                                    'labelPrefix' => 'Pemain 1',
                                    'existingPemain' => $existingPemain,
                                    'previewSrc' => $previewSrc,
                                    'inputId' => 'guest-foto',
                                    'previewId' => 'guest-foto-preview',
                                    'phoneReadonly' => true,
                                    'phoneValue' => $noHp,
                                ])
                            </div>

                            <div class="tab-pane fade {{ $hasPartnerErrors ? 'show active' : '' }}"
                                 id="player2-tab"
                                 role="tabpanel"
                                 aria-labelledby="player2-tab-btn"
                                 tabindex="0">
                                @if ($isPartnerExisting)
                                    <div class="alert alert-info py-2 small mb-3">
                                        <i class="bi bi-person-check me-1"></i>
                                        Data pemain 2 ditemukan. Periksa dan perbarui jika perlu.
                                    </div>
                                @else
                                    <div class="alert alert-light border py-2 small mb-3">
                                        <i class="bi bi-person-plus me-1"></i>
                                        Pemain 2 belum terdaftar. Lengkapi data di bawah.
                                    </div>
                                @endif

                                @include('guest.partials.register-player-fields', [
                                    'prefix' => 'partner_',
                                    'labelPrefix' => 'Pemain 2',
                                    'existingPemain' => $existingPartner,
                                    'previewSrc' => $partnerPreviewSrc,
                                    'inputId' => 'partner-foto',
                                    'previewId' => 'partner-foto-preview',
                                    'inputName' => 'partner_foto',
                                    'phoneReadonly' => true,
                                    'phoneValue' => $partnerNoHp,
                                ])
                            </div>
                        </div>
                    @else
                        @include('guest.partials.register-player-fields', [
                            'prefix' => '',
                            'labelPrefix' => 'Peserta',
                            'existingPemain' => $existingPemain,
                            'previewSrc' => $previewSrc,
                            'inputId' => 'guest-foto',
                            'previewId' => 'guest-foto-preview',
                            'phoneReadonly' => true,
                            'phoneValue' => $noHp,
                        ])
                    @endif

                    <div class="d-grid gap-2 mt-2">
                        <button type="submit" class="btn btn-bp btn-lg">
                            <i class="bi bi-send me-2"></i> Kirim Pendaftaran
                        </button>
                        <a href="{{ route('guest.register') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Ganti Nomor HP
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
