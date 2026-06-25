@extends('layouts.guest')

@section('title', 'Pendaftaran')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold mb-1">Pendaftaran Turnamen</h1>
            <p class="text-muted mb-0">{{ $turnamen->nama }}</p>
        </div>

        <div class="card guest-card mb-4">
            <div class="card-body py-3 px-4">
                <div class="row text-center g-3">
                    <div class="col-4">
                        <div class="info-label">Biaya</div>
                        <strong class="text-primary">Rp {{ number_format($turnamen->harga, 0, ',', '.') }}</strong>
                    </div>
                    <div class="col-4">
                        <div class="info-label">Jenis</div>
                        <strong>{{ $turnamen->jenis_label }}</strong>
                    </div>
                    <div class="col-4">
                        <div class="info-label">Status</div>
                        <span class="badge badge-open">Pendaftaran Dibuka</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card guest-card">
            <div class="card-header py-3">
                <i class="bi bi-phone me-2"></i> Verifikasi Nomor HP
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    @if ($turnamen->isDouble())
                        Masukkan nomor HP / WhatsApp untuk <strong>pemain 1</strong> dan <strong>pemain 2</strong>.
                        Pada langkah berikutnya, data yang sudah ada akan ditampilkan; jika belum terdaftar, Anda akan mengisi form untuk masing-masing pemain.
                    @else
                        Masukkan nomor HP / WhatsApp Anda terlebih dahulu. Jika sudah pernah terdaftar,
                        data Anda akan ditampilkan untuk diperiksa dan diperbarui.
                    @endif
                </p>

                <form action="{{ route('guest.register.lookup') }}" method="POST" novalidate>
                    @csrf

                    <div class="mb-4">
                        <label for="no_hp" class="form-label fw-semibold">
                            Nomor HP / WhatsApp Pemain 1
                            <span class="text-danger">*</span>
                        </label>
                        <input type="tel"
                               name="no_hp"
                               id="no_hp"
                               class="form-control form-control-lg @error('no_hp') is-invalid @enderror"
                               value="{{ old('no_hp') }}"
                               placeholder="08xxxxxxxxxx"
                               required
                               autofocus
                               inputmode="tel"
                               autocomplete="tel">
                        @error('no_hp')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    @if ($turnamen->isDouble())
                        <div class="mb-4">
                            <label for="partner_no_hp" class="form-label fw-semibold">
                                Nomor HP / WhatsApp Pemain 2
                                <span class="text-danger">*</span>
                            </label>
                            <input type="tel"
                                   name="partner_no_hp"
                                   id="partner_no_hp"
                                   class="form-control form-control-lg @error('partner_no_hp') is-invalid @enderror"
                                   value="{{ old('partner_no_hp') }}"
                                   placeholder="08xxxxxxxxxx"
                                   required
                                   inputmode="tel"
                                   autocomplete="tel">
                            @error('partner_no_hp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-bp btn-lg">
                            <i class="bi bi-search me-2"></i> Lanjutkan
                        </button>
                        <a href="{{ route('guest.landing') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
