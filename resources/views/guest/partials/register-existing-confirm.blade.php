@php
    $genderLabel = $pemain->gender === 'male' ? 'Laki-laki' : ($pemain->gender === 'female' ? 'Perempuan' : '—');
    $photoService = app(\App\Services\PemainPhotoService::class);
    $avatarSrc = $pemain->foto ? $photoService->url($pemain->foto) : null;
    $fieldPrefix = $prefix !== '' ? rtrim($prefix, '.') : '';
    $phoneFieldName = $fieldPrefix === '' ? 'no_hp' : $fieldPrefix . '[no_hp]';
@endphp

<div class="border rounded-3 p-3 bg-light">
    <div class="d-flex align-items-start gap-3">
        @if ($avatarSrc)
            <img src="{{ $avatarSrc }}"
                 alt="{{ $pemain->nama }}"
                 class="rounded-circle flex-shrink-0"
                 style="width: 64px; height: 64px; object-fit: cover;">
        @else
            <div class="rounded-circle bg-white border d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width: 64px; height: 64px;">
                <i class="bi bi-person fs-3 text-muted"></i>
            </div>
        @endif

        <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge text-bg-warning text-dark">Profil sudah ada</span>
            </div>
            <div class="fw-semibold text-truncate">{{ $pemain->nama }}</div>
            <div class="small text-muted">{{ $phoneValue }}</div>
            <div class="small text-muted">
                {{ $genderLabel }}
                · Rating {{ number_format((float) $pemain->rating, 1) }}
            </div>
        </div>
    </div>

    <input type="hidden" name="{{ $phoneFieldName }}" value="{{ $phoneValue }}">

    <p class="small text-muted mb-0 mt-3">
        Profil tidak bisa diubah di sini. Jika ini bukan Anda, ganti nomor HP.
        Perubahan data hanya bisa dilakukan oleh admin.
    </p>
</div>
