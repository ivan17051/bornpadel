@extends('layouts.guest')

@section('title', 'Pendaftaran')

@section('content')
@php
    $isDouble = $turnamen->isDouble();
    $registrationMode = old('registration_mode', 'single');
    $capacityLabel = $turnamen->maks_peserta
        ? number_format($turnamen->maks_peserta)
        : 'Tidak Terbatas';
    $hargaSatuan = (float) $turnamen->harga;
    $hargaTampil = $isDouble && $registrationMode === 'pair'
        ? $hargaSatuan * 2
        : $hargaSatuan;
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
                        <strong class="text-primary" id="register-harga-display"
                                data-harga-single="{{ $hargaSatuan }}"
                                data-harga-pair="{{ $hargaSatuan * 2 }}">
                            Rp {{ number_format($hargaTampil, 0, ',', '.') }}
                        </strong>
                        <div class="small text-muted {{ $isDouble && $registrationMode === 'pair' ? '' : 'd-none' }}"
                             id="register-harga-note">
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
                    Pilih mode pendaftaran, lalu masukkan nomor HP.
                    Jika nomor sudah punya profil, Anda hanya perlu mengonfirmasi data yang ada
                    (profil tidak bisa diubah lewat form pendaftaran).
                </p>

                <form action="{{ route('guest.register.lookup') }}" method="POST" novalidate id="register-lookup-form">
                    @csrf
                    <input type="hidden" name="id_turnamen" value="{{ $turnamen->id }}">

                    @if ($isDouble)
                        <div class="mb-4">
                            <label class="form-label fw-semibold d-block mb-2">Mode Pendaftaran</label>
                            <div class="btn-group w-100" role="group" aria-label="Mode pendaftaran">
                                <input type="radio" class="btn-check" name="registration_mode" id="mode-single" value="single"
                                       {{ $registrationMode === 'single' ? 'checked' : '' }} autocomplete="off">
                                <label class="btn btn-outline-primary" for="mode-single">
                                    <i class="bi bi-person me-1"></i> Daftar 1 Orang
                                </label>

                                <input type="radio" class="btn-check" name="registration_mode" id="mode-pair" value="pair"
                                       {{ $registrationMode === 'pair' ? 'checked' : '' }} autocomplete="off">
                                <label class="btn btn-outline-primary" for="mode-pair">
                                    <i class="bi bi-people me-1"></i> Daftar Berpasangan
                                </label>
                            </div>
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

                    <div id="player-2-phone-section" class="mb-4 {{ $isDouble && $registrationMode === 'pair' ? '' : 'd-none' }}">
                        <x-phone-input name="no_hp_2"
                                       id="guest_no_hp_2"
                                       label="Nomor HP Pemain 2"
                                       :value="old('no_hp_2')"
                                       :required="false"
                                       size="lg"
                                       error-key="no_hp_2" />
                    </div>

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
    const player2Section = document.getElementById('player-2-phone-section');
    const modeInputs = document.querySelectorAll('input[name="registration_mode"]');
    const phone2Group = player2Section?.querySelector('[data-phone-input]');
    const hargaDisplay = document.getElementById('register-harga-display');
    const hargaNote = document.getElementById('register-harga-note');

    if (!player2Section || !modeInputs.length) return;

    const formatRp = (value) => {
        const amount = Math.round(Number(value) || 0);
        return 'Rp ' + amount.toLocaleString('id-ID');
    };

    const togglePlayer2Phone = () => {
        const mode = document.querySelector('input[name="registration_mode"]:checked')?.value || 'single';
        const showPair = mode === 'pair';

        player2Section.classList.toggle('d-none', !showPair);

        if (phone2Group) {
            const localInput = phone2Group.querySelector('[data-phone-local]');
            if (localInput) {
                localInput.required = showPair;
            }
        }

        if (hargaDisplay) {
            const harga = showPair
                ? hargaDisplay.dataset.hargaPair
                : hargaDisplay.dataset.hargaSingle;
            hargaDisplay.textContent = formatRp(harga);
        }

        if (hargaNote) {
            hargaNote.classList.toggle('d-none', !showPair);
        }
    };

    modeInputs.forEach((input) => input.addEventListener('change', togglePlayer2Phone));
    togglePlayer2Phone();
});
</script>
@endpush
@endif
