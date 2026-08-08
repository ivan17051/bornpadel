@extends('layouts.guest')

@section('title', 'Pendaftaran')

@php
    $kategori = $kategori ?? null;
    $registerQuery = array_filter([
        'id_turnamen' => $turnamen->id,
        'id_kategori' => optional($kategori)->id,
    ]);
    $kategoriList = $kategoriList
        ?? ($turnamen->relationLoaded('kategori')
            ? $turnamen->kategori
            : $turnamen->kategori()->orderBy('urutan')->orderBy('id')->get());
@endphp

@section('og')
    @include('guest.partials.og-meta', [
        'ogTurnamen' => $turnamen,
        'ogUrl' => route('guest.register', $registerQuery),
    ])
@endsection

@section('content')
@php
    $isDouble = $turnamen->requiresPairRegistration();
    $isPairMode = $isDouble && ($registrationMode ?? 'single') === 'pair';
    $isGroupMode = $turnamen->allowsGroupRegistration() && ($registrationMode ?? 'single') === 'group';
    $groupSize = $groupSize ?? ($isGroupMode
        ? ($kategori ? $kategori->friendlyPlayersPerGroup() : $turnamen->friendlyPlayersPerGroup())
        : 4);
    $phones = $phones ?? array_filter([
        $noHp ?? '',
        $noHp2 ?? null,
        $noHp3 ?? null,
        $noHp4 ?? null,
    ], fn ($v) => $v !== null);
    $existingPlayers = $existingPlayers ?? [
        $existingPemain ?? null,
        $existingPemain2 ?? null,
        $existingPemain3 ?? null,
        $existingPemain4 ?? null,
    ];
    $maksPeserta = $kategori ? $kategori->maks_peserta : $turnamen->maks_peserta;
    $capacityLabel = $maksPeserta
        ? number_format($maksPeserta)
        : 'Tidak Terbatas';
    $hargaSatuan = (float) ($kategori ? $kategori->harga : $turnamen->harga);
    $hargaMultiplier = $isGroupMode ? $groupSize : ($isPairMode ? 2 : 1);
    $hargaTampil = $hargaSatuan * $hargaMultiplier;
    $playerCount = $isGroupMode ? $groupSize : ($isPairMode ? 2 : 1);
    $existingFlags = [];
    for ($i = 0; $i < $playerCount; $i++) {
        $existingFlags[] = (bool) ($existingPlayers[$i] ?? false);
    }
    $bothExisting = ! in_array(false, $existingFlags, true);
    $anyExisting = in_array(true, $existingFlags, true);
@endphp

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold mb-1">Form Pendaftaran</h1>
            <p class="text-muted mb-0">{{ $turnamen->nama }}</p>
            <span class="badge text-bg-light text-dark border mt-2">{{ $turnamen->jenis_label }}</span>
            @if ($kategori)
                <span class="badge text-bg-primary mt-2">{{ $kategori->nama }}</span>
            @endif
            @if ($isPairMode)
                <span class="badge text-bg-primary mt-2">Pendaftaran Berpasangan</span>
            @endif
            @if ($isGroupMode)
                <span class="badge text-bg-primary mt-2">Pendaftaran Satu Grup ({{ $groupSize }})</span>
            @endif
        </div>

        @include('guest.partials.tournament-nav', [
            'turnamen' => $turnamen,
            'kategori' => $kategori,
            'kategoriList' => $kategoriList,
            'activeTab' => 'register',
        ])

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
                    @for ($i = 0; $i < min(2, $playerCount); $i++)
                        <div class="col-6 col-md-3">
                            <div class="info-label">HP Pemain {{ $i + 1 }}</div>
                            <strong class="small">{{ $phones[$i] ?? '' }}</strong>
                        </div>
                    @endfor
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
                    @if ($kategori)
                        <input type="hidden" name="id_kategori" value="{{ old('id_kategori', $kategori->id) }}">
                    @endif
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
                        $playerBlocks = [];
                        for ($i = 0; $i < $playerCount; $i++) {
                            $n = $i + 1;
                            $playerBlocks[] = [
                                'label' => 'Pemain ' . $n,
                                'existing' => (bool) ($existingPlayers[$i] ?? false),
                                'pemain' => $existingPlayers[$i] ?? null,
                                'phone' => $phones[$i] ?? '',
                                'prefix' => $n === 1 ? '' : 'player_' . $n,
                                'foto' => $n === 1 ? 'foto' : 'foto_' . $n,
                                'inputId' => $n === 1 ? 'guest-foto' : 'guest-foto-' . $n,
                                'previewId' => $n === 1 ? 'guest-foto-preview' : 'guest-foto-' . $n . '-preview',
                            ];
                        }
                    @endphp

                    @foreach ($playerBlocks as $index => $block)
                        <div class="registration-player-block {{ $index > 0 ? 'border-top pt-4' : '' }} mb-4">
                            <h6 class="fw-semibold text-primary mb-3">
                                <i class="bi bi-person-circle me-1"></i> {{ $block['label'] }}
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
                        <a href="{{ route('guest.register', $registerQuery) }}" class="btn btn-outline-secondary">
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
