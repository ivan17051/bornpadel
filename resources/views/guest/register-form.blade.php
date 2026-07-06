@extends('layouts.guest')

@section('title', 'Pendaftaran')

@section('content')
@php
    $photoService = app(\App\Services\PemainPhotoService::class);
    $previewSrc = $existingPemain && $existingPemain->foto
        ? $photoService->url($existingPemain->foto)
        : null;
    $isDouble = $turnamen->isDouble();
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
            </div>
        </div>

        @if ($isDouble)
            <div class="alert alert-info guest-card mb-4">
                <i class="bi bi-people me-2"></i>
                Turnamen <strong>double</strong> — Anda mendaftar sebagai individu. Pasangan akan dibentuk secara acak setelah pendaftaran ditutup.
            </div>
        @elseif ($isExisting)
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

        <div class="card guest-card">
            <div class="card-header py-3">
                <i class="bi bi-person-vcard me-2"></i> Data Peserta
            </div>
            <div class="card-body p-4">
                <form action="{{ route('guest.register.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="no_hp" value="{{ old('no_hp', $noHp) }}">
                    <input type="hidden" name="id_turnamen" value="{{ old('id_turnamen', $turnamen->id) }}">

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
                            <i class="bi bi-send me-2"></i> Kirim Pendaftaran
                        </button>
                        <a href="{{ route('guest.register', ['id_turnamen' => $turnamen->id]) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Ganti Nomor HP
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
