@php
    /** @var \App\Models\Pemain $pemain */
    $photoService = app(\App\Services\PemainPhotoService::class);
    $avatarSrc = $pemain->foto ? $photoService->url($pemain->foto) : null;
    $genderLabel = $pemain->gender === 'male' ? 'Laki-laki' : ($pemain->gender === 'female' ? 'Perempuan' : '—');
    $priorCount = $pemain->priorRegistrationCount($exceptTurnamenId ?? null);
@endphp

<div class="alert alert-warning border-warning mb-3">
    <div class="d-flex align-items-start gap-3">
        @if ($avatarSrc)
            <img src="{{ $avatarSrc }}"
                 alt="{{ $pemain->nama }}"
                 class="rounded-circle flex-shrink-0 border border-warning"
                 style="width: 56px; height: 56px; object-fit: cover;">
        @else
            <div class="rounded-circle bg-white border border-warning d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width: 56px; height: 56px;">
                <i class="bi bi-person-check fs-4 text-warning"></i>
            </div>
        @endif

        <div class="flex-grow-1 min-w-0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <strong class="text-dark">Profil sudah ada di database</strong>
                <span class="badge text-bg-warning text-dark">Existing</span>
            </div>
            <div class="fw-semibold">{{ $pemain->nama }}</div>
            <div class="small">
                {{ $pemain->no_hp }}
                · {{ $genderLabel }}
                · Rating {{ number_format((float) $pemain->rating, 1) }}
            </div>
            @if ($priorCount > 0)
                <div class="small mt-1">
                    <i class="bi bi-trophy me-1"></i>
                    Pernah terdaftar di {{ $priorCount }} turnamen lain.
                </div>
            @endif
            <div class="small text-muted mt-2 mb-0">
                Profil terkunci di form. Klik <strong>Edit Profil</strong> jika data perlu diubah.
                Menyimpan tanpa edit akan mendaftarkan profil ini tanpa mengubah data.
            </div>
        </div>
    </div>
</div>
