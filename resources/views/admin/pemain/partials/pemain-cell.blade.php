@if ($pemain)
    <div class="d-flex align-items-center gap-2">
        <x-pemain-avatar :pemain="$pemain" :size="40" />
        <div>
            <strong class="d-block">
                <x-pemain-link :pemain="$pemain" class="text-decoration-none text-dark" />
            </strong>
            <div class="small text-muted">{{ $pemain->no_hp }}</div>
            <div class="small text-muted d-lg-none">
                {{ $pemain->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}
                · Rating {{ number_format($pemain->rating, 1) }}
            </div>
        </div>
    </div>
@else
    <span class="text-muted small">—</span>
@endif
