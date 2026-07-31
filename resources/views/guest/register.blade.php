@extends('layouts.guest')

@section('title', 'Pendaftaran')

@section('og')
    @include('guest.partials.og-meta', [
        'ogTurnamen' => $turnamen,
        'ogUrl' => route('guest.register', ['id_turnamen' => $turnamen->id]),
        'ogDescription' => trim(sprintf(
            'Daftar %s%s. %s',
            $turnamen->jenis_label,
            $turnamen->tanggal ? ' · ' . $turnamen->tanggal->format('d M Y') : '',
            $turnamen->status === 'open' ? 'Pendaftaran dibuka.' : ''
        )),
    ])
@endsection

@section('content')
@php
    $isDouble = $turnamen->requiresPairRegistration();
    $allowsGroup = $turnamen->allowsGroupRegistration();
    $registrationMode = old('registration_mode', 'single');
    $isPairMode = $isDouble && $registrationMode === 'pair';
    $isGroupMode = $allowsGroup && $registrationMode === 'group';
    $capacityLabel = $turnamen->maks_peserta
        ? number_format($turnamen->maks_peserta)
        : 'Tidak Terbatas';
    $hargaSatuan = (float) $turnamen->harga;
    $hargaMultiplier = $isGroupMode ? 4 : ($isPairMode ? 2 : 1);
    $hargaTampil = $hargaSatuan * $hargaMultiplier;
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
                        <div class="small text-muted {{ $hargaMultiplier > 1 ? '' : 'd-none' }}" id="register-harga-note">
                            <span id="register-harga-multiplier">{{ $hargaMultiplier }}</span> × Rp {{ number_format($hargaSatuan, 0, ',', '.') }}
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
                    @elseif ($allowsGroup)
                        Group Match: daftar individu, atau daftar satu grup lengkap (4 pemain + nama grup).
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

                    @if ($isDouble || $allowsGroup)
                        <div class="mb-4">
                            <div class="info-label mb-2">Mode Pendaftaran</div>
                            <div class="btn-group w-100" role="group" aria-label="Mode pendaftaran">
                                <input type="radio"
                                       class="btn-check"
                                       name="registration_mode"
                                       id="registration-mode-single"
                                       value="single"
                                       autocomplete="off"
                                       {{ $registrationMode === 'single' || (! $isPairMode && ! $isGroupMode) ? 'checked' : '' }}>
                                <label class="btn btn-outline-primary" for="registration-mode-single">Individu</label>

                                @if ($isDouble)
                                    <input type="radio"
                                           class="btn-check"
                                           name="registration_mode"
                                           id="registration-mode-pair"
                                           value="pair"
                                           autocomplete="off"
                                           {{ $registrationMode === 'pair' ? 'checked' : '' }}>
                                    <label class="btn btn-outline-primary" for="registration-mode-pair">Berpasangan</label>
                                @endif

                                @if ($allowsGroup)
                                    <input type="radio"
                                           class="btn-check"
                                           name="registration_mode"
                                           id="registration-mode-group"
                                           value="group"
                                           autocomplete="off"
                                           {{ $registrationMode === 'group' ? 'checked' : '' }}>
                                    <label class="btn btn-outline-primary" for="registration-mode-group">Satu Grup (4 pemain)</label>
                                @endif
                            </div>
                            @error('registration_mode')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    @else
                        <input type="hidden" name="registration_mode" value="single">
                    @endif

                    <div class="mb-4 {{ $isGroupMode ? '' : '' }}" id="nama-grup-section" style="{{ $isGroupMode ? '' : 'display:none' }}">
                        <label for="nama_grup" class="form-label fw-semibold">Nama Grup <span class="text-danger">*</span></label>
                        <input type="text"
                               name="nama_grup"
                               id="nama_grup"
                               class="form-control form-control-lg @error('nama_grup') is-invalid @enderror"
                               value="{{ old('nama_grup') }}"
                               maxlength="255"
                               placeholder="Contoh: Smash Brothers">
                        @error('nama_grup')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <x-phone-input name="no_hp"
                                       id="guest_no_hp"
                                       label="{{ ($isDouble || $allowsGroup) ? 'Nomor HP Pemain 1' : 'Nomor HP / WhatsApp' }}"
                                       :value="old('no_hp')"
                                       size="lg" />
                    </div>

                    @if ($isDouble || $allowsGroup)
                        <div id="player-2-phone-section" class="mb-4 {{ ($isPairMode || $isGroupMode) ? '' : 'd-none' }}">
                            <x-phone-input name="no_hp_2"
                                           id="guest_no_hp_2"
                                           label="Nomor HP Pemain 2"
                                           :value="old('no_hp_2')"
                                           :required="$isPairMode || $isGroupMode"
                                           size="lg"
                                           error-key="no_hp_2" />
                        </div>
                    @endif

                    @if ($allowsGroup)
                        <div id="player-3-phone-section" class="mb-4 {{ $isGroupMode ? '' : 'd-none' }}">
                            <x-phone-input name="no_hp_3"
                                           id="guest_no_hp_3"
                                           label="Nomor HP Pemain 3"
                                           :value="old('no_hp_3')"
                                           :required="$isGroupMode"
                                           size="lg"
                                           error-key="no_hp_3" />
                        </div>
                        <div id="player-4-phone-section" class="mb-4 {{ $isGroupMode ? '' : 'd-none' }}">
                            <x-phone-input name="no_hp_4"
                                           id="guest_no_hp_4"
                                           label="Nomor HP Pemain 4"
                                           :value="old('no_hp_4')"
                                           :required="$isGroupMode"
                                           size="lg"
                                           error-key="no_hp_4" />
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

@if ($isDouble || $allowsGroup)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('register-lookup-form');
    if (!form) return;

    const hargaSatuan = parseFloat(form.dataset.hargaSatuan || '0');
    const hargaDisplay = document.getElementById('register-harga-display');
    const hargaNote = document.getElementById('register-harga-note');
    const hargaMultiplierEl = document.getElementById('register-harga-multiplier');
    const namaGrupSection = document.getElementById('nama-grup-section');
    const namaGrupInput = document.getElementById('nama_grup');
    const playerTwoSection = document.getElementById('player-2-phone-section');
    const playerTwoInput = document.getElementById('guest_no_hp_2');
    const playerThreeSection = document.getElementById('player-3-phone-section');
    const playerThreeInput = document.getElementById('guest_no_hp_3');
    const playerFourSection = document.getElementById('player-4-phone-section');
    const playerFourInput = document.getElementById('guest_no_hp_4');
    const modeInputs = form.querySelectorAll('input[name="registration_mode"]');

    const formatRp = (value) => 'Rp ' + Math.round(value).toLocaleString('id-ID');

    const syncMode = () => {
        const mode = form.querySelector('input[name="registration_mode"]:checked')?.value || 'single';
        const isPair = mode === 'pair';
        const isGroup = mode === 'group';
        const multiplier = isGroup ? 4 : (isPair ? 2 : 1);

        if (playerTwoSection) {
            playerTwoSection.classList.toggle('d-none', !(isPair || isGroup));
        }
        if (playerTwoInput) {
            playerTwoInput.required = isPair || isGroup;
            if (!(isPair || isGroup)) playerTwoInput.value = '';
        }
        if (playerThreeSection) {
            playerThreeSection.classList.toggle('d-none', !isGroup);
        }
        if (playerThreeInput) {
            playerThreeInput.required = isGroup;
            if (!isGroup) playerThreeInput.value = '';
        }
        if (playerFourSection) {
            playerFourSection.classList.toggle('d-none', !isGroup);
        }
        if (playerFourInput) {
            playerFourInput.required = isGroup;
            if (!isGroup) playerFourInput.value = '';
        }
        if (namaGrupSection) {
            namaGrupSection.style.display = isGroup ? '' : 'none';
        }
        if (namaGrupInput) {
            namaGrupInput.required = isGroup;
            if (!isGroup) namaGrupInput.value = '';
        }
        if (hargaDisplay) {
            hargaDisplay.textContent = formatRp(hargaSatuan * multiplier);
        }
        if (hargaNote) {
            hargaNote.classList.toggle('d-none', multiplier <= 1);
        }
        if (hargaMultiplierEl) {
            hargaMultiplierEl.textContent = String(multiplier);
        }
    };

    modeInputs.forEach((input) => input.addEventListener('change', syncMode));
    syncMode();
});
</script>
@endpush
@endif
