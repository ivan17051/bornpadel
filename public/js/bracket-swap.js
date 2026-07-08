/**
 * Admin bracket participant swap.
 *
 * Clicking an editable bracket-player opens a modal listing the other
 * first-round participants. Selecting one swaps their positions.
 * Uses event delegation on #live-bracket so it survives auto-refresh re-renders.
 */
(function () {
    const container = document.getElementById('live-bracket');
    if (!container || container.dataset.editable !== '1') return;

    const modalEl = document.getElementById('bracketSwapModal');
    if (!modalEl || !window.bootstrap) return;

    const modal = new bootstrap.Modal(modalEl);
    const listEl = document.getElementById('bracket-swap-list');
    const sourceLabelEl = document.getElementById('bracket-swap-source');
    const swapUrl = container.dataset.swapUrl;
    const turnamenId = container.dataset.turnamen ? parseInt(container.dataset.turnamen, 10) : null;

    let source = null;

    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    };

    const labelOf = (playerEl) =>
        playerEl.querySelector('.bracket-player-name')?.textContent.trim() || 'Peserta';

    const collectCandidates = () =>
        Array.from(container.querySelectorAll('.bracket-player.is-editable')).map((el) => ({
            matchId: el.dataset.matchId,
            slot: el.dataset.slot,
            label: labelOf(el),
        }));

    const openModal = () => {
        sourceLabelEl.textContent = source.label;

        const candidates = collectCandidates().filter(
            (c) => !(c.matchId === source.matchId && c.slot === source.slot)
        );

        listEl.innerHTML = candidates.length
            ? candidates
                  .map(
                      (c) => `
                        <button type="button"
                                class="list-group-item list-group-item-action d-flex align-items-center bracket-swap-option"
                                data-match-id="${c.matchId}" data-slot="${c.slot}">
                            <i class="bi bi-arrow-left-right me-2 text-muted"></i>
                            <span>${escapeHtml(c.label)}</span>
                        </button>`
                  )
                  .join('')
            : '<div class="text-muted small p-3 text-center">Tidak ada peserta lain yang dapat ditukar.</div>';

        modal.show();
    };

    container.addEventListener('click', (event) => {
        const player = event.target.closest('.bracket-player.is-editable');
        if (!player || !container.contains(player)) return;

        event.preventDefault();
        source = {
            matchId: player.dataset.matchId,
            slot: player.dataset.slot,
            label: labelOf(player),
        };
        openModal();
    });

    container.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const player = event.target.closest('.bracket-player.is-editable');
        if (!player) return;
        event.preventDefault();
        player.click();
    });

    listEl.addEventListener('click', async (event) => {
        const option = event.target.closest('.bracket-swap-option');
        if (!option || !source) return;

        listEl.querySelectorAll('.bracket-swap-option').forEach((el) => {
            el.disabled = true;
        });

        try {
            await BornPadelAdmin.apiRequest(swapUrl, 'PATCH', {
                id_turnamen: turnamenId,
                source_match: parseInt(source.matchId, 10),
                source_slot: parseInt(source.slot, 10),
                target_match: parseInt(option.dataset.matchId, 10),
                target_slot: parseInt(option.dataset.slot, 10),
            });

            modal.hide();
            BornPadelAdmin.showToast('Peserta bracket berhasil ditukar.');
            setTimeout(() => window.location.reload(), 600);
        } catch (error) {
            BornPadelAdmin.showToast(error.message || 'Gagal menukar peserta.', 'error');
            listEl.querySelectorAll('.bracket-swap-option').forEach((el) => {
                el.disabled = false;
            });
        }
    });
})();
