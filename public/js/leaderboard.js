/**
 * Live leaderboard — auto-refresh for guest & admin pages
 */
(function () {
    const container = document.getElementById('live-leaderboard');
    if (!container) return;

    const refreshUrl = container.dataset.refreshUrl;
    const isMahjong = container.dataset.mahjong === '1';
    const profileBase = '/pemain/';

    const renderNameCell = (row) => {
        const ids = row.pemain_ids || (row.id_pemain ? [row.id_pemain] : []);

        if (!ids.length) {
            return row.nama || '—';
        }

        if (ids.length === 1) {
            return `<a href="${profileBase}${ids[0]}" class="pemain-profile-link">${row.nama || 'Pemain'}</a>`;
        }

        const names = String(row.nama || '').split(' / ');

        return ids.map((id, index) => {
            const label = names[index] || 'Pemain';
            const link = `<a href="${profileBase}${id}" class="pemain-profile-link">${label}</a>`;
            return index < ids.length - 1 ? `${link}<span class="text-muted"> / </span>` : link;
        }).join('');
    };

    const renderHeader = (title = 'Klasemen Grup') => `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0"><i class="bi bi-bar-chart-steps me-2"></i>${title}</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-refresh-leaderboard">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>`;

    const bindRefreshButton = () => {
        document.getElementById('btn-refresh-leaderboard')
            ?.addEventListener('click', fetchStandings);
    };

    const renderEmpty = (title) => {
        container.innerHTML = renderHeader(title) + `
            <div class="alert alert-light border text-center mb-0">
                <i class="bi bi-trophy text-muted d-block mb-2 fs-4"></i>
                Belum ada data klasemen.
            </div>`;
        bindRefreshButton();
    };

    const renderMahjongStandings = (rows) => {
        if (!rows || rows.length === 0) {
            renderEmpty('Klasemen Mahjong');
            return;
        }

        const body = rows.map((row) => `
            <tr class="${row.rank === 1 ? 'table-success' : ''}">
                <td class="text-center fw-bold">
                    ${row.rank === 1 ? '<i class="bi bi-trophy-fill text-warning"></i>' : row.rank}
                </td>
                <td class="fw-semibold">${renderNameCell(row)}</td>
                <td class="text-center text-muted d-none d-md-table-cell">${row.grup_nama || '—'}</td>
                <td class="text-center text-muted">${row.poin_akumulasi ?? 0}</td>
                <td class="text-center"><span class="badge text-bg-secondary">${row.poin_didapat ?? 0}</span></td>
                <td class="text-center"><span class="badge text-bg-primary">${row.total_poin ?? 0}</span></td>
            </tr>
        `).join('');

        container.innerHTML = renderHeader('Klasemen Mahjong') + `
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:3rem">#</th>
                                    <th>Pemain</th>
                                    <th class="text-center d-none d-md-table-cell">Grup</th>
                                    <th class="text-center">Akumulasi</th>
                                    <th class="text-center">Babak</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>${body}</tbody>
                        </table>
                    </div>
                </div>
            </div>
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>`;
        bindRefreshButton();
    };

    const renderGroupStandings = (groups) => {
        if (!groups || groups.length === 0) {
            renderEmpty('Klasemen Grup');
            return;
        }

        const cards = groups.map((grup) => {
            const isMahjongGroup = grup.is_mahjong;
            const rows = grup.standings.map((row) => {
                if (isMahjongGroup) {
                    return `
                        <tr class="${row.rank === 1 ? 'table-success' : ''}">
                            <td class="text-center fw-bold">
                                ${row.rank === 1 ? '<i class="bi bi-trophy-fill text-warning"></i>' : row.rank}
                            </td>
                            <td class="fw-semibold">${renderNameCell(row)}</td>
                            <td class="text-center text-muted">${row.poin_akumulasi ?? 0}</td>
                            <td class="text-center"><span class="badge text-bg-secondary">${row.poin_didapat}</span></td>
                            <td class="text-center"><span class="badge text-bg-primary">${row.total_poin ?? row.poin_didapat}</span></td>
                        </tr>`;
                }

                return `
                    <tr class="${row.rank === 1 ? 'table-success' : ''}">
                        <td class="text-center fw-bold">
                            ${row.rank === 1 ? '<i class="bi bi-trophy-fill text-warning"></i>' : row.rank}
                        </td>
                        <td class="fw-semibold">${renderNameCell(row)}</td>
                        <td class="text-center"><span class="badge text-bg-primary">${row.poin_didapat}</span></td>
                        <td class="text-center d-none d-sm-table-cell">${row.set_menang}</td>
                        <td class="text-center d-none d-md-table-cell">${row.games_menang}</td>
                    </tr>`;
            }).join('');

            const head = isMahjongGroup
                ? `<tr>
                        <th class="text-center" style="width:3rem">#</th>
                        <th>Pemain</th>
                        <th class="text-center">Akumulasi</th>
                        <th class="text-center">Babak</th>
                        <th class="text-center">Total</th>
                   </tr>`
                : `<tr>
                        <th class="text-center" style="width:3rem">#</th>
                        <th>Pemain</th>
                        <th class="text-center">Poin</th>
                        <th class="text-center d-none d-sm-table-cell">Set</th>
                        <th class="text-center d-none d-md-table-cell">Games</th>
                   </tr>`;

            return `
                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold py-3">
                            <i class="bi bi-diagram-3 me-2 text-primary"></i>${grup.nama}
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">${head}</thead>
                                    <tbody>${rows}</tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>`;
        }).join('');

        container.innerHTML = renderHeader('Klasemen Grup') + `
            <div class="row g-4">${cards}</div>
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>`;
        bindRefreshButton();
    };

    const fetchStandings = async () => {
        try {
            const response = await fetch(refreshUrl, {
                headers: { 'Accept': 'application/json' },
            });
            const json = await response.json();

            if (!json.success || !json.data) {
                return;
            }

            if (json.type === 'mahjong' || isMahjong) {
                renderMahjongStandings(json.data);
            } else {
                renderGroupStandings(json.data);
            }
        } catch (e) {
            console.warn('Leaderboard refresh failed:', e);
        }
    };

    bindRefreshButton();
    setInterval(fetchStandings, 30000);
})();
