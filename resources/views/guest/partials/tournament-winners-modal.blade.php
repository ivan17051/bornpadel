@php
    $photoPlaceholder = app(\App\Services\PemainPhotoService::class)->placeholderUrl();
@endphp

<div class="modal fade" id="tournamentWinnersModal" tabindex="-1" aria-labelledby="tournamentWinnersModalLabel" aria-hidden="true"
     data-profile-base="{{ url('/pemain') }}/">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content guest-card border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="tournamentWinnersModalLabel">
                    <i class="bi bi-trophy me-2 text-warning"></i>
                    <span id="tournament-winners-modal-name">Pemenang Turnamen</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body pt-3">
                <div id="tournament-winners-modal-body">
                    <div class="bracket-podium mb-0">
                        <div class="bracket-podium-grid" id="tournament-winners-podium"></div>
                    </div>
                </div>
                <div id="tournament-winners-empty" class="text-center text-muted py-4 d-none">
                    <i class="bi bi-trophy display-6 d-block mb-2"></i>
                    Data pemenang belum tersedia.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('tournamentWinnersModal');
    const podiumEl = document.getElementById('tournament-winners-podium');
    const emptyEl = document.getElementById('tournament-winners-empty');
    const nameEl = document.getElementById('tournament-winners-modal-name');
    const placeholder = @json($photoPlaceholder);
    const profileBase = modalEl.dataset.profileBase || '/pemain/';

    if (!modalEl || !podiumEl) {
        return;
    }

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const renderPhotos = (players) => {
        if (!players.length) {
            return '<div class="bracket-podium-photo bracket-podium-photo--empty"><i class="bi bi-person"></i></div>';
        }

        return players.map((player) => {
            const src = escapeHtml(player.foto_url || placeholder);
            const name = escapeHtml(player.nama || 'Pemain');

            return `<img src="${src}" alt="${name}" class="bracket-podium-photo" width="56" height="56" loading="lazy" decoding="async" title="${name}" onerror="this.onerror=null;this.src='${placeholder}';">`;
        }).join('');
    };

    const renderPodiumNames = (entry) => {
        const players = entry?.players || [];

        if (players.length) {
            return players.map((player, index) => {
                const name = escapeHtml(player.nama || 'Pemain');
                const link = player.id
                    ? `<a href="${profileBase}${player.id}" class="pemain-profile-link">${name}</a>`
                    : name;

                return index < players.length - 1
                    ? `${link}<span class="text-muted"> / </span>`
                    : link;
            }).join('');
        }

        return escapeHtml(entry?.label || '');
    };

    const renderSlot = (slot) => {
        if (!slot?.entry?.label) {
            return '';
        }

        return `
            <div class="bracket-podium-card bracket-podium-card--${slot.mod}">
                <div class="bracket-podium-rank">
                    <i class="bi ${slot.icon}"></i>
                    <span>${escapeHtml(slot.labelText)}</span>
                </div>
                <div class="bracket-podium-photos">${renderPhotos(slot.entry.players || [])}</div>
                <div class="bracket-podium-names">${renderPodiumNames(slot.entry)}</div>
            </div>
        `;
    };

    modalEl.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) {
            return;
        }

        let payload = {};

        try {
            payload = JSON.parse(trigger.dataset.winners || '{}');
        } catch (error) {
            payload = {};
        }

        if (nameEl) {
            nameEl.textContent = trigger.dataset.tournamentName || 'Pemenang Turnamen';
        }

        const slots = [
            { mod: 'second', labelText: 'Juara 2', icon: 'bi-award-fill', entry: payload.second },
            { mod: 'first', labelText: 'Juara 1', icon: 'bi-trophy-fill', entry: payload.first },
            { mod: 'third', labelText: 'Juara 3', icon: 'bi-award-fill', entry: payload.third },
        ].filter((slot) => slot.entry && slot.entry.label);

        podiumEl.innerHTML = slots.map(renderSlot).join('');

        const hasWinners = slots.length > 0;
        podiumEl.closest('.bracket-podium')?.classList.toggle('d-none', !hasWinners);
        emptyEl?.classList.toggle('d-none', hasWinners);
    });
});
</script>
@endpush
