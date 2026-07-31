@extends('layouts.guest')

@section('title', 'Pendaftaran')

@section('og')
    @include('guest.partials.og-meta', [
        'ogTurnamen' => $turnamen,
        'ogUrl' => route('guest.register', ['id_turnamen' => $turnamen->id]),
    ])
@endsection

@section('content')
@php
    $isDouble = $turnamen->requiresPairRegistration();
    $isPairMode = $isDouble && ($registrationMode ?? 'single') === 'pair';
    $isGroupMode = $turnamen->allowsGroupRegistration() && ($registrationMode ?? 'single') === 'group';
    $capacityLabel = $turnamen->maks_peserta
        ? number_format($turnamen->maks_peserta)
        : 'Tidak Terbatas';
    $hargaSatuan = (float) $turnamen->harga;
    $hargaMultiplier = $isGroupMode ? 4 : ($isPairMode ? 2 : 1);
    $hargaTampil = $hargaSatuan * $hargaMultiplier;
    $existingFlags = [
        $isExisting,
        $isPairMode || $isGroupMode ? ($isExisting2 ?? false) : true,
        $isGroupMode ? ($isExisting3 ?? false) : true,
        $isGroupMode ? ($isExisting4 ?? false) : true,
    ];
    $bothExisting = ! in_array(false, $existingFlags, true);
    $anyExisting = $isExisting
        || ($isExisting2 ?? false)
        || ($isExisting3 ?? false)
        || ($isExisting4 ?? false);
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
            @if ($isGroupMode)
                <span class="badge text-bg-primary mt-2">Pendaftaran Satu Grup</span>
            @endif
        </div>

        <div class="card guest-card mb-4">
            <div class="card-body py-3 px-4">
                <div class="row text-center g-3">
                    <div class="col-6 col-md-3">
                        <div class="info-label">Biaya</div>
                        <strong class="text-primary">Rp {{ number_format($hargaTampil, 0, ',', '.') }}</strong>
                        @if ($hargaMultiplier > 1)
                            <div class="small text-muted">{{ $hargaMultiplier }} × Rp {{ number_format($hargaSatuan, 0, ',', '.') }}</div>
                        @endif
                    </div>
                    @if ($isGroupMode)
                        <div class="col-6 col-md-3">
                            <div class="info-label">Nama Grup</div>
                            <strong class="small">{{ $namaGrup }}</strong>
                        </div>
                    @endif
                    <div class="col-6 col-md-3">
                        <div class="info-label">HP Pemain 1</div>
                        <strong class="small">{{ $noHp }}</strong>
                    </div>
                    @if ($isPairMode || $isGroupMode)
                        <div class="col-6 col-md-3">
                            <div class="info-label">HP Pemain 2</div>
                            <strong class="small">{{ $noHp2 }}</strong>
                        </div>
                    @endif
                    @if (! $isGroupMode)
                        <div class="col-6 col-md-3">
                            <div class="info-label">Peserta</div>
                            <strong>{{ $capacityLabel }}</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($anyExisting)
            <div class="alert alert-warning guest-card mb-4">
                <i class="bi bi-shield-check me-2"></i>
                Nomor yang sudah punya profil hanya perlu dikonfirmasi. Data profil tidak bisa diubah lewat form ini.
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
                    @if ($isGroupMode)
                        <input type="hidden" name="nama_grup" value="{{ old('nama_grup', $namaGrup) }}">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Nama Grup</label>
                            <input type="text" class="form-control" value="{{ $namaGrup }}" readonly>
                            @error('nama_grup')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    @php
                        $playerBlocks = [
                            [
                                'label' => 'Pemain 1',
                                'existing' => $isExisting,
                                'pemain' => $existingPemain,
                                'phone' => $noHp,
                                'prefix' => '',
                                'foto' => 'foto',
                                'inputId' => 'guest-foto',
                                'previewId' => 'guest-foto-preview',
                            ],
                        ];

                        if ($isPairMode || $isGroupMode) {
                            $playerBlocks[] = [
                                'label' => 'Pemain 2',
                                'existing' => $isExisting2 ?? false,
                                'pemain' => $existingPemain2 ?? null,
                                'phone' => $noHp2,
                                'prefix' => 'player_2',
                                'foto' => 'foto_2',
                                'inputId' => 'guest-foto-2',
                                'previewId' => 'guest-foto-2-preview',
                            ];
                        }

                        if ($isGroupMode) {
                            $playerBlocks[] = [
                                'label' => 'Pemain 3',
                                'existing' => $isExisting3 ?? false,
                                'pemain' => $existingPemain3 ?? null,
                                'phone' => $noHp3,
                                'prefix' => 'player_3',
                                'foto' => 'foto_3',
                                'inputId' => 'guest-foto-3',
                                'previewId' => 'guest-foto-3-preview',
                            ];
                            $playerBlocks[] = [
                                'label' => 'Pemain 4',
                                'existing' => $isExisting4 ?? false,
                                'pemain' => $existingPemain4 ?? null,
                                'phone' => $noHp4,
                                'prefix' => 'player_4',
                                'foto' => 'foto_4',
                                'inputId' => 'guest-foto-4',
                                'previewId' => 'guest-foto-4-preview',
                            ];
                        }
                    @endphp

                    @foreach ($playerBlocks as $index => $block)
                        <div class="registration-player-block {{ $index > 0 ? 'border-top pt-4' : '' }} mb-4">
                            <h6 class="fw-semibold text-primary mb-3">
                                <i class="bi bi-{{ $index + 1 }}-circle me-1"></i> {{ $block['label'] }}
                            </h6>
                            @if ($block['existing'])
                                @include('guest.partials.register-existing-confirm', [
                                    'pemain' => $block['pemain'],
                                    'phoneValue' => $block['phone'],
                                    'prefix' => $block['prefix'],
                                ])
                            @else
                                @include('guest.partials.register-player-fields', [
                                    'prefix' => $block['prefix'],
                                    'labelPrefix' => $block['label'],
                                    'existingPemain' => null,
                                    'previewSrc' => null,
                                    'inputId' => $block['inputId'],
                                    'previewId' => $block['previewId'],
                                    'inputName' => $block['foto'],
                                    'phoneReadonly' => true,
                                    'phoneValue' => $block['phone'],
                                ])
                            @endif
                        </div>
                    @endforeach

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
                            @if ($bothExisting && ($isGroupMode || $isPairMode))
                                <i class="bi bi-check2-circle me-2"></i> Ya, Ini Kami — Daftar
                            @elseif ($bothExisting)
                                <i class="bi bi-check2-circle me-2"></i> Ya, Ini Saya — Daftar
                            @elseif ($anyExisting)
                                <i class="bi bi-send me-2"></i> Konfirmasi & Kirim Pendaftaran
                            @else
                                <i class="bi bi-send me-2"></i> Kirim Pendaftaran
                            @endif
                        </button>
                        <a href="{{ route('guest.register', ['id_turnamen' => $turnamen->id]) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>
                            @if ($isGroupMode || $isPairMode)
                                {{ $anyExisting ? 'Bukan Kami — Ganti Nomor HP' : 'Ganti Nomor HP' }}
                            @else
                                {{ $anyExisting ? 'Bukan Saya — Ganti Nomor HP' : 'Ganti Nomor HP' }}
                            @endif
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
