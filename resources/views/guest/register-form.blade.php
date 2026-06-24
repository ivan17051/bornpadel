@extends('layouts.guest')

@section('title', 'Pendaftaran')

@section('content')
@php
    $photoService = app(\App\Services\PemainPhotoService::class);
    $previewSrc = $existingPemain && $existingPemain->foto
        ? $photoService->url($existingPemain->foto)
        : null;
    $partnerPreviewSrc = isset($existingPartner) && $existingPartner && $existingPartner->foto
        ? $photoService->url($existingPartner->foto)
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
                        <div class="info-label">No. HP Pemain 1</div>
                        <strong>{{ $noHp }}</strong>
                    </div>
                </div>
            </div>
        </div>

        @if ($isExisting)
            <div class="alert alert-info guest-card mb-4">
                <i class="bi bi-person-check me-2"></i>
                Data pemain 1 ditemukan. Periksa dan perbarui jika ada perubahan, lalu kirim pendaftaran turnamen ini.
            </div>
        @else
            <div class="alert alert-light border guest-card mb-4">
                <i class="bi bi-person-plus me-2"></i>
                Nomor HP pemain 1 belum terdaftar. Lengkapi data di bawah untuk mendaftar.
            </div>
        @endif

        @if ($isDouble)
            <div class="alert alert-light border guest-card mb-4">
                <i class="bi bi-people me-2"></i>
                Turnamen <strong>double</strong> memerlukan data lengkap untuk 2 pemain.
            </div>
        @endif

        <div class="card guest-card {{ $isDouble ? 'mb-4' : '' }}">
            <div class="card-header py-3">
                <i class="bi bi-person-vcard me-2"></i>
                {{ $isDouble ? 'Pemain 1' : 'Data Peserta' }}
            </div>
            <div class="card-body p-4">
                <form action="{{ route('guest.register.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                    @csrf
                    <input type="hidden" name="no_hp" value="{{ old('no_hp', $noHp) }}">

                    <x-pemain-photo-input
                        input-id="guest-foto"
                        preview-id="guest-foto-preview"
                        :preview-src="$previewSrc" />

                    <div class="mb-3">
                        <label for="nama" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text"
                               name="nama"
                               id="nama"
                               class="form-control @error('nama') is-invalid @enderror"
                               value="{{ old('nama', optional($existingPemain)->nama) }}"
                               placeholder="Masukkan nama lengkap"
                               required
                               autocomplete="name">
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="tgl_lahir" class="form-label fw-semibold">Tanggal Lahir <span class="text-danger">*</span></label>
                        <input type="date"
                               name="tgl_lahir"
                               id="tgl_lahir"
                               class="form-control @error('tgl_lahir') is-invalid @enderror"
                               value="{{ old('tgl_lahir', optional(optional($existingPemain)->tgl_lahir)->format('Y-m-d')) }}"
                               required
                               max="{{ date('Y-m-d', strtotime('-1 day')) }}">
                        @error('tgl_lahir')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="gender" class="form-label fw-semibold">Jenis Kelamin <span class="text-danger">*</span></label>
                        <select name="gender"
                                id="gender"
                                class="form-select @error('gender') is-invalid @enderror"
                                required>
                            <option value="" disabled {{ old('gender', optional($existingPemain)->gender) ? '' : 'selected' }}>Pilih jenis kelamin</option>
                            <option value="male" {{ old('gender', optional($existingPemain)->gender) === 'male' ? 'selected' : '' }}>Laki-laki</option>
                            <option value="female" {{ old('gender', optional($existingPemain)->gender) === 'female' ? 'selected' : '' }}>Perempuan</option>
                        </select>
                        @error('gender')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="rating" class="form-label fw-semibold">Rating (opsional)</label>
                        <input type="number"
                               name="rating"
                               id="rating"
                               class="form-control @error('rating') is-invalid @enderror"
                               value="{{ old('rating', optional($existingPemain)->rating) }}"
                               placeholder="Contoh: 3.5"
                               min="0"
                               max="10"
                               step="0.1">
                        <div class="form-text">Perkiraan level permainan Anda (skala 0–10).</div>
                        @error('rating')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @if ($isDouble)
                        <hr class="my-4">

                        <h2 class="h5 fw-bold mb-3">
                            <i class="bi bi-person-vcard me-2"></i> Pemain 2
                        </h2>

                        <div class="mb-3">
                            <label for="partner_no_hp" class="form-label fw-semibold">Nomor HP / WhatsApp <span class="text-danger">*</span></label>
                            <input type="tel"
                                   name="partner_no_hp"
                                   id="partner_no_hp"
                                   class="form-control @error('partner_no_hp') is-invalid @enderror"
                                   value="{{ old('partner_no_hp', optional($existingPartner)->no_hp) }}"
                                   placeholder="08xxxxxxxxxx"
                                   required
                                   inputmode="tel"
                                   autocomplete="tel">
                            @error('partner_no_hp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <x-pemain-photo-input
                            input-id="partner-foto"
                            preview-id="partner-foto-preview"
                            input-name="partner_foto"
                            label="Foto Pemain 2 (opsional)"
                            :preview-src="$partnerPreviewSrc" />

                        <div class="mb-3">
                            <label for="partner_nama" class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="partner_nama"
                                   id="partner_nama"
                                   class="form-control @error('partner_nama') is-invalid @enderror"
                                   value="{{ old('partner_nama', optional($existingPartner)->nama) }}"
                                   placeholder="Masukkan nama lengkap pemain 2"
                                   required
                                   autocomplete="name">
                            @error('partner_nama')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="partner_tgl_lahir" class="form-label fw-semibold">Tanggal Lahir <span class="text-danger">*</span></label>
                            <input type="date"
                                   name="partner_tgl_lahir"
                                   id="partner_tgl_lahir"
                                   class="form-control @error('partner_tgl_lahir') is-invalid @enderror"
                                   value="{{ old('partner_tgl_lahir', optional(optional($existingPartner)->tgl_lahir)->format('Y-m-d')) }}"
                                   required
                                   max="{{ date('Y-m-d', strtotime('-1 day')) }}">
                            @error('partner_tgl_lahir')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="partner_gender" class="form-label fw-semibold">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select name="partner_gender"
                                    id="partner_gender"
                                    class="form-select @error('partner_gender') is-invalid @enderror"
                                    required>
                                <option value="" disabled {{ old('partner_gender', optional($existingPartner)->gender) ? '' : 'selected' }}>Pilih jenis kelamin</option>
                                <option value="male" {{ old('partner_gender', optional($existingPartner)->gender) === 'male' ? 'selected' : '' }}>Laki-laki</option>
                                <option value="female" {{ old('partner_gender', optional($existingPartner)->gender) === 'female' ? 'selected' : '' }}>Perempuan</option>
                            </select>
                            @error('partner_gender')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="partner_rating" class="form-label fw-semibold">Rating (opsional)</label>
                            <input type="number"
                                   name="partner_rating"
                                   id="partner_rating"
                                   class="form-control @error('partner_rating') is-invalid @enderror"
                                   value="{{ old('partner_rating', optional($existingPartner)->rating) }}"
                                   placeholder="Contoh: 3.5"
                                   min="0"
                                   max="10"
                                   step="0.1">
                            @error('partner_rating')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-bp btn-lg">
                            <i class="bi bi-send me-2"></i>
                            {{ $isExisting ? 'Perbarui & Daftar Turnamen' : 'Kirim Pendaftaran' }}
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
