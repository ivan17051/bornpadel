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

                <div class="border-top pt-3 mt-3">
                    <div class="info-label mb-2">Syarat & Ketentuan</div>
                    @if ($turnamen->syarat)
                        <div class="text-secondary small" style="white-space: pre-line;">{{ $turnamen->syarat }}</div>
                    @else
                        <p class="text-muted small mb-0">Belum ada syarat khusus untuk turnamen ini.</p>
                    @endif
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
                    <input type="hidden" name="id_turnamen" value="{{ $turnamen->id }}">

                    <div class="mb-4">
                        <x-phone-input name="no_hp"
                                       id="guest_no_hp"
                                       label="Nomor HP / WhatsApp Pemain 1"
                                       :value="old('no_hp')"
                                       size="lg" />
                    </div>

                    @if ($turnamen->isDouble())
                        <div class="mb-4">
                            <x-phone-input name="partner_no_hp"
                                           id="guest_partner_no_hp"
                                           label="Nomor HP / WhatsApp Pemain 2"
                                           :value="old('partner_no_hp')"
                                           size="lg" />
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
