@extends('layouts.guest')

@section('title', 'Pendaftaran')

@section('content')
@php
    $isDouble = $turnamen->requiresPairRegistration();
    $registrationMode = old('registration_mode', $isDouble ? 'single' : 'single');
    $isPairMode = $isDouble && $registrationMode === 'pair';
    $capacityLabel = $turnamen->maks_peserta
        ? number_format($turnamen->maks_peserta)
        : 'Tidak Terbatas';
    $hargaSatuan = (float) $turnamen->harga;
    $hargaTampil = $isPairMode ? $hargaSatuan * 2 : $hargaSatuan;
@endphp

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
                        <strong class="text-primary" id="register-harga-display">
                            Rp {{ number_format($hargaTampil, 0, ',', '.') }}
                        </strong>
                        <div class="small text-muted {{ $isPairMode ? '' : 'd-none' }}" id="register-harga-note">
                            2 × Rp {{ number_format($hargaSatuan, 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="info-label">Jenis</div>
                        <strong>{{ $turnamen->jenis_label }}</strong>
                    </div>
                    <div class="col-4">
                        <div class="info-label">Peserta</div>
                        <strong>{{ $capacityLabel }}</strong>
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
                <i class="bi bi-phone me-2"></i> Cek Nomor HP
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-4">
                    @if ($isDouble)
                        Turnamen double: daftar sendiri dulu, lalu pasangan diatur kemudian. Opsional: daftar langsung berpasangan.
                    @elseif ($turnamen->randomizesPartners())
                        Turnamen single: daftar sendiri. Pasangan akan diacak otomatis setelah pendaftaran ditutup.
                    @else
                        Masukkan nomor HP untuk melanjutkan.
                    @endif
                    Jika nomor sudah punya profil, Anda hanya perlu mengonfirmasi data yang ada
                    (profil tidak bisa diubah lewat form pendaftaran).
                </p>

                <form action="{{ route('guest.register.lookup') }}" method="POST" novalidate id="register-lookup-form"
                      data-harga-satuan="{{ $hargaSatuan }}">
                    @csrf
                    <input type="hidden" name="id_turnamen" value="{{ $turnamen->id }}">

                    @if ($isDouble)
                        <div class="mb-4">
                            <div class="info-label mb-2">Mode Pendaftaran</div>
                            <div class="btn-group w-100" role="group" aria-label="Mode pendaftaran">
                                <input type="radio"
                                       class="btn-check"
                                       name="registration_mode"
                                       id="registration-mode-single"
                                       value="single"
                                       autocomplete="off"
                                       {{ $registrationMode !== 'pair' ? 'checked' : '' }}>
                                <label class="btn btn-outline-primary" for="registration-mode-single">Individu</label>

                                <input type="radio"
                                       class="btn-check"
                                       name="registration_mode"
                                       id="registration-mode-pair"
                                       value="pair"
                                       autocomplete="off"
                                       {{ $registrationMode === 'pair' ? 'checked' : '' }}>
                                <label class="btn btn-outline-primary" for="registration-mode-pair">Berpasangan</label>
                            </div>
                            @error('registration_mode')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    @else
                        <input type="hidden" name="registration_mode" value="single">
                    @endif

                    <div class="mb-4">
                        <x-phone-input name="no_hp"
                                       id="guest_no_hp"
                                       label="{{ $isDouble ? 'Nomor HP Pemain 1' : 'Nomor HP / WhatsApp' }}"
                                       :value="old('no_hp')"
                                       size="lg" />
                    </div>

                    @if ($isDouble)
                        <div id="player-2-phone-section" class="mb-4 {{ $isPairMode ? '' : 'd-none' }}">
                            <x-phone-input name="no_hp_2"
                                           id="guest_no_hp_2"
                                           label="Nomor HP Pemain 2"
                                           :value="old('no_hp_2')"
                                           :required="$isPairMode"
                                           size="lg"
                                           error-key="no_hp_2" />
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

@if ($isDouble)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('register-lookup-form');
    if (!form) return;

    const hargaSatuan = parseFloat(form.dataset.hargaSatuan || '0');
    const hargaDisplay = document.getElementById('register-harga-display');
    const hargaNote = document.getElementById('register-harga-note');
    const playerTwoSection = document.getElementById('player-2-phone-section');
    const playerTwoInput = document.getElementById('guest_no_hp_2');
    const modeInputs = form.querySelectorAll('input[name="registration_mode"]');

    const formatRp = (value) => 'Rp ' + Math.round(value).toLocaleString('id-ID');

    const syncMode = () => {
        const mode = form.querySelector('input[name="registration_mode"]:checked')?.value || 'single';
        const isPair = mode === 'pair';

        if (playerTwoSection) {
            playerTwoSection.classList.toggle('d-none', !isPair);
        }
        if (playerTwoInput) {
            playerTwoInput.required = isPair;
            if (!isPair) {
                playerTwoInput.value = '';
            }
        }
        if (hargaDisplay) {
            hargaDisplay.textContent = formatRp(isPair ? hargaSatuan * 2 : hargaSatuan);
        }
        if (hargaNote) {
            hargaNote.classList.toggle('d-none', !isPair);
        }
    };

    modeInputs.forEach((input) => input.addEventListener('change', syncMode));
    syncMode();
});
</script>
@endpush
@endif
