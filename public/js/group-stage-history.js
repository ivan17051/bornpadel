/**
 * Group stage match history modal for standings pages.
 */
(function () {
    const modalEl = document.getElementById('groupStageHistoryModal');
    if (!modalEl) {
        return;
    }

    const historyUrl = modalEl.dataset.historyUrl;
    if (!historyUrl) {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const metaEl = document.getElementById('group-stage-history-meta');
    const loadingEl = document.getElementById('group-stage-history-loading');
    const errorEl = document.getElementById('group-stage-history-error');
    const emptyEl = document.getElementById('group-stage-history-empty');
    const contentEl = document.getElementById('group-stage-history-content');
    const rowsEl = document.getElementById('group-stage-history-rows');

    const resultBadgeClass = (result) => {
        if (result === 'win') {
            return 'text-bg-success';
        }
        if (result === 'loss') {
            return 'text-bg-danger';
        }
        if (result === 'pending') {
            return 'text-bg-warning';
        }

        return 'text-bg-secondary';
    };

    const resetModal = () => {
        loadingEl.classList.add('d-none');
        errorEl.classList.add('d-none');
        emptyEl.classList.add('d-none');
        contentEl.classList.add('d-none');
        errorEl.textContent = '';
        rowsEl.innerHTML = '';
        metaEl.innerHTML = '';
    };

    const setScoreBadgeClass = (own, opponent) => {
        if (own > opponent) {
            return 'text-bg-success';
        }
        if (own < opponent) {
            return 'text-bg-danger';
        }

        return 'text-bg-secondary';
    };

    const renderScoreCell = (match) => {
        const sets = match.sets || [];

        if (!sets.length) {
            if (match.result === 'scheduled' || match.result === 'pending') {
                return '<span class="badge text-bg-secondary">Belum dimainkan</span>';
            }

            return '<span class="badge text-bg-light text-dark border">—</span>';
        }

        return `
            <div class="d-inline-flex flex-wrap gap-1 justify-content-center">
                ${sets.map((set) => `
                    <span class="badge ${setScoreBadgeClass(set.own, set.opponent)}"
                          title="Set ${set.set}">
                        ${set.own}-${set.opponent}
                    </span>
                `).join('')}
            </div>`;
    };

    const renderRows = (matches) => {
        rowsEl.innerHTML = matches.map((match) => `
                <tr>
                    <td class="fw-semibold">${match.opponent || '—'}</td>
                    <td class="text-center">${renderScoreCell(match)}</td>
                    <td class="text-center">
                        <span class="badge ${resultBadgeClass(match.result)}">${match.result_label}</span>
                    </td>
                </tr>`).join('');
    };

    const loadHistory = async (grupId, pesertaId, participantName) => {
        resetModal();
        loadingEl.classList.remove('d-none');
        modal.show();

        metaEl.innerHTML = `
            <div class="fw-semibold">${participantName || 'Peserta'}</div>
            <div class="small text-muted">Fase grup</div>`;

        try {
            const url = new URL(historyUrl, window.location.origin);
            url.searchParams.set('id_grup', grupId);
            url.searchParams.set('id_peserta', pesertaId);

            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
            });
            const json = await response.json();

            loadingEl.classList.add('d-none');

            if (!response.ok || !json.success) {
                errorEl.textContent = json.message || 'Gagal memuat riwayat pertandingan.';
                errorEl.classList.remove('d-none');
                return;
            }

            const participant = json.data.participant || {};
            const matches = json.data.matches || [];

            metaEl.innerHTML = `
                <div class="fw-semibold">${participant.nama || participantName || 'Peserta'}</div>
                <div class="small text-muted">${participant.grup_nama || 'Fase grup'}</div>`;

            if (matches.length === 0) {
                emptyEl.classList.remove('d-none');
                return;
            }

            renderRows(matches);
            contentEl.classList.remove('d-none');
        } catch (error) {
            loadingEl.classList.add('d-none');
            errorEl.textContent = 'Gagal memuat riwayat pertandingan.';
            errorEl.classList.remove('d-none');
            console.warn('Group stage history failed:', error);
        }
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.btn-group-stage-history');

        if (!button) {
            return;
        }

        event.preventDefault();

        const grupId = button.dataset.grupId;
        const pesertaId = button.dataset.pesertaId;
        const participantName = button.dataset.participantName || '';

        if (!grupId || !pesertaId) {
            return;
        }

        loadHistory(grupId, pesertaId, participantName);
    });
})();
