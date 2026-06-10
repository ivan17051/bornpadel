/**
 * Live knockout bracket refresh
 */
(function () {
    const container = document.getElementById('live-bracket');
    if (!container) return;

    const refreshUrl = container.dataset.refreshUrl;

    const renderMatch = (match) => {
        const p1Winner = match.pemenang_id && match.pemain1_id === match.pemenang_id;
        const p2Winner = match.pemenang_id && match.pemain2_id === match.pemenang_id;
        const p1Scores = match.skor && match.status === 'completed'
            ? match.skor.split(', ').map((s) => s.split('-')[0]).join(' ')
            : '';
        const p2Scores = match.skor && match.status === 'completed'
            ? match.skor.split(', ').map((s) => s.split('-')[1]).join(' ')
            : '';

        let statusBadge = '';
        if (match.status === 'scheduled' && match.pemain1_id && match.pemain2_id) {
            statusBadge = '<div class="bracket-match-status"><span class="badge bg-secondary">Upcoming</span></div>';
        } else if (match.status === 'scheduled') {
            statusBadge = '<div class="bracket-match-status"><span class="badge bg-light text-dark border">Menunggu</span></div>';
        }

        return `
            <div class="bracket-match ${match.status === 'completed' ? 'is-completed' : ''} ${match.pemenang_id ? 'has-winner' : ''}">
                <div class="bracket-player ${p1Winner ? 'is-winner' : ''} ${!match.pemain1_id ? 'is-tbd' : ''}">
                    <span class="bracket-player-name">${match.pemain1}</span>
                    ${p1Scores ? `<span class="bracket-score-badge">${p1Scores}</span>` : ''}
                </div>
                <div class="bracket-player ${p2Winner ? 'is-winner' : ''} ${!match.pemain2_id ? 'is-tbd' : ''}">
                    <span class="bracket-player-name">${match.pemain2}</span>
                    ${p2Scores ? `<span class="bracket-score-badge">${p2Scores}</span>` : ''}
                </div>
                ${statusBadge}
            </div>`;
    };

    const renderBracket = (bracket) => {
        if (!bracket || bracket.length === 0) {
            container.innerHTML = `
                <div class="alert alert-light border text-center mb-0">
                    <i class="bi bi-diagram-2 text-muted d-block mb-2 fs-4"></i>
                    Bracket knockout belum tersedia.
                </div>`;
            return;
        }

        const rounds = bracket.map((round, i) => `
            <div class="bracket-round flex-shrink-0">
                <div class="bracket-round-title text-center text-uppercase small fw-bold text-muted mb-3">${round.nama_ronde}</div>
                <div class="bracket-matches d-flex flex-column justify-content-around h-100">
                    ${round.matches.map(renderMatch).join('')}
                </div>
            </div>
            ${i < bracket.length - 1 ? '<div class="bracket-connector flex-shrink-0"></div>' : ''}
        `).join('');

        const lastRound = bracket[bracket.length - 1];
        const champion = lastRound?.matches?.[0]?.pemenang;
        const championHtml = champion ? `
            <div class="champion-banner text-center mt-4 p-4 rounded-3">
                <i class="bi bi-trophy-fill text-warning fs-2 d-block mb-2"></i>
                <div class="small text-muted text-uppercase">Juara Turnamen</div>
                <div class="h4 fw-bold mb-0">${champion}</div>
            </div>` : '';

        container.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="bi bi-diagram-2 me-2"></i>Bracket Knockout</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-refresh-bracket">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
            <div class="bracket-tree overflow-auto pb-3">
                <div class="bracket-rounds d-flex align-items-stretch gap-0">${rounds}</div>
            </div>
            ${championHtml}
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>`;

        document.getElementById('btn-refresh-bracket')?.addEventListener('click', fetchBracket);
    };

    const fetchBracket = async () => {
        try {
            const response = await fetch(refreshUrl, { headers: { Accept: 'application/json' } });
            const json = await response.json();
            if (json.success && json.data?.bracket) {
                renderBracket(json.data.bracket);
            }
        } catch (e) {
            console.warn('Bracket refresh failed:', e);
        }
    };

    document.getElementById('btn-refresh-bracket')?.addEventListener('click', fetchBracket);
    setInterval(fetchBracket, 30000);
})();
