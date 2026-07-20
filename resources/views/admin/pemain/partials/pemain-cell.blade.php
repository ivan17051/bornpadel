@if ($pemain)
    @php
        $currentTurnamenId = isset($turnamen) ? $turnamen->id : (isset($exceptTurnamenId) ? $exceptTurnamenId : null);
        $isExistingProfile = $pemain->hasPriorRegistrations($currentTurnamenId);
    @endphp
    <div class="d-flex align-items-center gap-2">
        <x-pemain-avatar :pemain="$pemain" :size="40" />
        <div>
            <strong class="d-block">
                <x-pemain-link :pemain="$pemain" class="text-decoration-none text-dark" />
            </strong>
            <div class="small text-muted">{{ $pemain->no_hp }}</div>
            @if ($isExistingProfile)
                <span class="badge text-bg-warning text-dark mt-1" title="Profil sudah ada sebelum turnamen ini">
                    Profil existing
                </span>
            @endif
            <div class="small text-muted d-lg-none">
                {{ $pemain->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}
                · Rating {{ number_format($pemain->rating, 1) }}
            </div>
        </div>
    </div>
@else
    <span class="text-muted small">—</span>
@endif
