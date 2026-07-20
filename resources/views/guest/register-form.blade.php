@extends('layouts.guest')

@section('title', 'Pendaftaran')

@section('content')
@php
    $isDouble = $turnamen->isDouble();
    $isPairMode = $isDouble && ($registrationMode ?? 'single') === 'pair';
    $capacityLabel = $turnamen->maks_peserta
        ? number_format($turnamen->maks_peserta)
        : 'Tidak Terbatas';
    $hargaSatuan = (float) $turnamen->harga;
    $hargaTampil = $isPairMode ? $hargaSatuan * 2 : $hargaSatuan;
    $bothExisting = $isExisting && (! $isPairMode || ($isExisting2 ?? false));
@endphp

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold mb-1">Form Pendaftaran</h1>
            <p class="text-muted mb-0">{{ $turnamen->nama }}</p>
            <span class="badge text-bg-light text-dark border mt-2">{{ $turnamen->jenis_label }}</span>
            @if ($isPairMode)
                <span class="badge text-bg-primary mt-2">Pendaftaran Berpasangan</span>
            @endif
        </div>

        <div class="card guest-card mb-4">
            <div class="card-body py-3 px-4">
                <div class="row text-center g-3">
                    <div class="col-{{ $isPairMode ? '3' : '4' }}">
                        <div class="info-label">Biaya</div>
                        <strong class="text-primary">Rp {{ number_format($hargaTampil, 0, ',', '.') }}</strong>
                        @if ($isPairMode)
                            <div class="small text-muted">2 × Rp {{ number_format($hargaSatuan, 0, ',', '.') }}</div>
                        @endif
                    </div>
                    <div class="col-{{ $isPairMode ? '3' : '4' }}">
                        <div class="info-label">HP Pemain 1</div>
                        <strong class="small">{{ $noHp }}</strong>
                    </div>
                    @if ($isPairMode)
                        <div class="col-3">
                            <div class="info-label">HP Pemain 2</div>
                            <strong class="small">{{ $noHp2 }}</strong>
                        </div>
                    @endif
                    <div class="col-{{ $isPairMode ? '3' : '4' }}">
                        <div class="info-label">Peserta</div>
                        <strong>{{ $capacityLabel }}</strong>
                    </div>
                </div>
            </div>
        </div>

        @if ($isPairMode && ($isExisting || ($isExisting2 ?? false)))
            <div class="alert alert-warning guest-card mb-4">
                <i class="bi bi-shield-check me-2"></i>
                Nomor yang sudah punya profil hanya perlu dikonfirmasi. Data profil tidak bisa diubah lewat form ini.
            </div>
        @elseif ($isExisting)
            <div class="alert alert-warning guest-card mb-4">
                <i class="bi bi-person-check me-2"></i>
                Profil dengan nomor ini sudah ada. Konfirmasi jika ini Anda, lalu kirim pendaftaran.
                Perubahan profil hanya dapat dilakukan oleh admin.
            </div>
        @else
            <div class="alert alert-light border guest-card mb-4">
                <i class="bi bi-person-plus me-2"></i>
                Nomor HP belum terdaftar. Lengkapi data di bawah untuk mendaftar.
            </div>
        @endif

        <div class="card guest-card">
            <div class="card-header py-3">
                <i class="bi bi-person-vcard me-2"></i> Data Peserta
            </div>
            <div class="card-body p-4">
                <form action="{{ route('guest.register.store') }}" method="POST" enctype="multipart/form-data" novalidate id="guest-registration-form">
                    @csrf
                    <input type="hidden" name="id_turnamen" value="{{ old('id_turnamen', $turnamen->id) }}">
                    <input type="hidden" name="registration_mode" value="{{ $registrationMode ?? 'single' }}">

                    <div class="registration-player-block mb-4">
                        <h6 class="fw-semibold text-primary mb-3">
                            <i class="bi bi-1-circle me-1"></i> Pemain 1
                        </h6>
                        @if ($isExisting)
                            @include('guest.partials.register-existing-confirm', [
                                'pemain' => $existingPemain,
                                'phoneValue' => $noHp,
                                'prefix' => '',
                            ])
                        @else
                            @include('guest.partials.register-player-fields', [
                                'prefix' => '',
                                'labelPrefix' => 'Peserta',
                                'existingPemain' => null,
                                'previewSrc' => null,
                                'inputId' => 'guest-foto',
                                'previewId' => 'guest-foto-preview',
                                'phoneReadonly' => true,
                                'phoneValue' => $noHp,
                            ])
                        @endif
                    </div>

                    @if ($isPairMode)
                        <div class="registration-player-block border-top pt-4 mb-4">
                            <h6 class="fw-semibold text-primary mb-3">
                                <i class="bi bi-2-circle me-1"></i> Pemain 2
                            </h6>
                            @if ($isExisting2 ?? false)
                                @include('guest.partials.register-existing-confirm', [
                                    'pemain' => $existingPemain2,
                                    'phoneValue' => $noHp2,
                                    'prefix' => 'player_2',
                                ])
                            @else
                                @include('guest.partials.register-player-fields', [
                                    'prefix' => 'player_2',
                                    'labelPrefix' => 'Pemain 2',
                                    'existingPemain' => null,
                                    'previewSrc' => null,
                                    'inputId' => 'guest-foto-2',
                                    'previewId' => 'guest-foto-2-preview',
                                    'inputName' => 'foto_2',
                                    'phoneReadonly' => true,
                                    'phoneValue' => $noHp2,
                                ])
                            @endif
                        </div>
                    @endif

                    <div class="mb-4">
                        <label for="bukti_bayar" class="form-label fw-semibold">Bukti Pembayaran <span class="text-muted">(opsional)</span></label>
                        <input type="file"
                               name="bukti_bayar"
                               id="bukti_bayar"
                               class="form-control @error('bukti_bayar') is-invalid @enderror"
                               accept="image/jpeg,image/png,image/webp,application/pdf">
                        <div class="form-text">Unggah bukti transfer jika sudah melakukan pembayaran.</div>
                        @error('bukti_bayar')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-grid gap-2 mt-2">
                        <button type="submit" class="btn btn-bp btn-lg">
                            @if ($bothExisting)
                                <i class="bi bi-check2-circle me-2"></i> Ya, Ini Saya — Daftar
                            @elseif ($isExisting || ($isExisting2 ?? false))
                                <i class="bi bi-send me-2"></i> Konfirmasi & Kirim Pendaftaran
                            @else
                                <i class="bi bi-send me-2"></i> Kirim Pendaftaran
                            @endif
                        </button>
                        <a href="{{ route('guest.register', ['id_turnamen' => $turnamen->id]) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>
                            {{ ($isExisting || ($isExisting2 ?? false)) ? 'Bukan Saya — Ganti Nomor HP' : 'Ganti Nomor HP' }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
