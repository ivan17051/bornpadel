/**
 * Live leaderboard — auto-refresh for guest & admin pages
 */
(function () {
    const container = document.getElementById('live-leaderboard');
    if (!container) return;

    const refreshUrl = container.dataset.refreshUrl;
    const isMahjong = container.dataset.mahjong === '1';
    const isFriendly = container.dataset.friendly === '1';
    const showGroupHistory = container.dataset.showGroupHistory === '1';
    const profileBase = container.dataset.profileBase || '/pemain/';

    const renderHistoryButton = (row, grupId) => {
        if (!showGroupHistory || !row.id_peserta || !grupId) {
            return '';
        }

        const name = String(row.nama || '').replace(/"/g, '&quot;');

        return `
            <td class="text-end">
                <button type="button"
                        class="btn btn-sm btn-outline-secondary btn-group-stage-history"
                        title="Riwayat pertandingan"
                        aria-label="Riwayat pertandingan ${name}"
                        data-grup-id="${grupId}"
                        data-peserta-id="${row.id_peserta}"
                        data-participant-name="${name}">
                    <i class="bi bi-clock-history"></i>
                </button>
            </td>`;
    };

    const formatGameDiff = (value) => {
        if (typeof value === 'string' && (value.startsWith('+') || value.startsWith('-') || value === '0')) {
            return value;
        }

        const diff = Number(value) || 0;
        return diff > 0 ? `+${diff}` : String(diff);
    };

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

    const renderMahjongBabakTable = (section) => {
        const rounds = section.rounds || [];
        const rows = section.rows || [];

        const headerCells = rounds.map((round) => `
            <th class="text-center">${round.label || ('Ronde ' + round.round)}</th>
        `).join('');

        const bodyRows = rows.length
            ? rows.map((row) => {
                const roundScores = row.round_scores || [];
                const roundCells = rounds.map((round, index) => `
                    <td class="text-center">
                        <span class="badge text-bg-secondary">${roundScores[index] ?? 0}</span>
                    </td>
                `).join('');

                return `
                    <tr class="${row.rank === 1 ? 'table-success' : ''}">
                        <td class="text-center fw-bold">
                            ${row.rank === 1 ? '<i class="bi bi-trophy-fill text-warning"></i>' : row.rank}
                        </td>
                        <td class="fw-semibold">${renderNameCell(row)}</td>
                        ${roundCells}
                        <td class="text-center">
                            <span class="badge text-bg-primary">${row.total_babak ?? 0}</span>
                        </td>
                    </tr>`;
            }).join('')
            : `<tr>
                    <td colspan="${3 + rounds.length}" class="text-center text-muted py-4">
                        Belum ada data pemain pada babak ini.
                    </td>
               </tr>`;

        return `
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width:3rem">#</th>
                                    <th>Pemain</th>
                                    ${headerCells}
                                    <th class="text-center">Total Babak</th>
                                </tr>
                            </thead>
                            <tbody>${bodyRows}</tbody>
                        </table>
                    </div>
                </div>
            </div>`;
    };

    const renderEmpty = (title) => {
        container.innerHTML = renderHeader(title) + `
            <div class="alert alert-light border text-center mb-0">
                <i class="bi bi-trophy text-muted d-block mb-2 fs-4"></i>
                Belum ada data klasemen.
            </div>`;
        bindRefreshButton();
    };

    const renderMahjongStandings = (payload) => {
        const sections = payload?.sections || payload || [];

        if (!sections || sections.length === 0) {
            renderEmpty('Klasemen Mahjong');
            return;
        }

        const sortedSections = [...sections].sort((a, b) => (b.babak || 0) - (a.babak || 0));
        const sectionHtml = sortedSections.map((section) => `
            <div class="mb-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-layers me-1 text-primary"></i>Babak ${section.babak}
                    </h6>
                    ${section.is_active ? '<span class="badge text-bg-success">Berlangsung</span>' : ''}
                </div>
                ${renderMahjongBabakTable(section)}
            </div>
        `).join('');

        container.innerHTML = renderHeader('Klasemen Mahjong') + sectionHtml + `
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>`;
        bindRefreshButton();
    };

    const renderGroupCardsHtml = (groups) => {
        return groups.map((grup) => {
            const isMahjongGroup = grup.is_mahjong;
            const rows = (grup.standings || []).map((row) => {
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
                        <td class="text-center d-none d-md-table-cell">${formatGameDiff(row.games_diff_label ?? row.games_menang)}</td>
                        ${renderHistoryButton(row, grup.id)}
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
                        <th class="text-center d-none d-md-table-cell" title="Selisih game">GD</th>
                        ${showGroupHistory ? '<th class="text-end" style="width:4rem"></th>' : ''}
                   </tr>`;

            return `
                <div class="col-lg-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold py-3 d-flex align-items-center justify-content-between gap-2">
                            <span>
                                <i class="bi bi-diagram-3 me-2 text-primary"></i>${grup.nama}
                                ${grup.matches_complete
                                    ? `&nbsp;<span class="text-success" title="Semua pertandingan grup sudah selesai">
                                           <i class="bi bi-check-circle"></i>
                                       </span>`
                                    : ''}
                            </span>
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
    };

    const renderPostLeagueHtml = (postLeague) => {
        const sections = postLeague?.sections || [];
        if (!sections.length) {
            return '';
        }

        const hasBracket = !!postLeague.has_bracket;
        const unitLabel = postLeague.is_double ? 'Pasangan' : 'Pemain';
        const colSpan = hasBracket ? 7 : 6;

        const body = sections.map((section) => {
            const header = `
                <tr class="table-secondary">
                    <td colspan="${colSpan}" class="fw-semibold text-uppercase small py-2">
                        ${section.label || ('Juara ' + (section.place || ''))}
                    </td>
                </tr>`;

            const rows = (section.rows || []).map((row) => `
                <tr class="${row.advances ? 'table-success' : ''}">
                    <td class="text-center fw-bold">${row.overall_rank}</td>
                    <td class="fw-semibold">${renderNameCell(row)}</td>
                    <td class="text-muted">${row.grup || '—'}</td>
                    <td class="text-center"><span class="badge text-bg-primary">${row.poin_didapat ?? 0}</span></td>
                    <td class="text-center d-none d-sm-table-cell">${row.set_menang ?? 0}</td>
                    <td class="text-center d-none d-md-table-cell">${formatGameDiff(row.games_diff_label ?? row.games_menang)}</td>
                    ${hasBracket ? `<td class="text-center">${row.advances ? '<span class="badge text-bg-success">Lolos</span>' : ''}</td>` : ''}
                </tr>`).join('');

            return header + rows;
        }).join('');

        return `
            <div class="post-league-ranking mt-4" id="post-league-ranking">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ol me-2"></i>Peringkat Lintas Grup
                    </h5>
                    ${hasBracket ? `<span class="badge text-bg-success"><i class="bi bi-flag-fill me-1"></i> Highlight: lolos knockout</span>` : ''}
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width:3rem">#</th>
                                        <th>${unitLabel}</th>
                                        <th>Grup</th>
                                        <th class="text-center">Poin</th>
                                        <th class="text-center d-none d-sm-table-cell">Set</th>
                                        <th class="text-center d-none d-md-table-cell" title="Selisih game">GD</th>
                                        ${hasBracket ? '<th class="text-center" style="width:5.5rem"></th>' : ''}
                                    </tr>
                                </thead>
                                <tbody>${body}</tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>`;
    };

    const renderGroupStandings = (groups, postLeague = null) => {
        if (!groups || groups.length === 0) {
            renderEmpty('Klasemen Grup');
            return;
        }

        const cards = renderGroupCardsHtml(groups);
        const groupPanel = container.querySelector('#group-standings-panel');
        const postHost = container.querySelector('#post-league-ranking-host');

        if (groupPanel) {
            groupPanel.innerHTML = `
                <div class="group-leaderboard">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="bi bi-bar-chart-steps me-2"></i>Klasemen Grup
                        </h5>
                    </div>
                    <div class="row g-4">${cards}</div>
                </div>`;

            if (postHost) {
                postHost.innerHTML = renderPostLeagueHtml(postLeague);
            }

            bindRefreshButton();
            return;
        }

        container.innerHTML = renderHeader('Klasemen Grup') + `
            <div class="row g-4">${cards}</div>
            ${renderPostLeagueHtml(postLeague)}
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>`;
        bindRefreshButton();
    };

    const renderFriendlyStandings = (rows, matchSessions = []) => {
        const standingsPanel = container.querySelector('#friendly-standings-panel');
        const matchesPanel = container.querySelector('#friendly-matches-public');
        const rowsList = Array.isArray(rows) ? rows : [];

        const membersCell = (row) => (row.members || []).map((m) => m.nama).filter(Boolean).join(', ') || '—';

        const standingsHtml = rowsList.length === 0
            ? `<div class="alert alert-light border text-center mb-0">
                    <i class="bi bi-trophy text-muted d-block mb-2 fs-4"></i>
                    Belum ada data klasemen grup.
               </div>`
            : `<div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width:3rem">#</th>
                                        <th>Grup</th>
                                        <th>Anggota</th>
                                        <th class="text-center">Poin</th>
                                        <th class="text-center d-none d-sm-table-cell">Set</th>
                                        <th class="text-center d-none d-md-table-cell">GD</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${rowsList.map((row) => `
                                        <tr class="${row.rank === 1 ? 'table-success' : ''}">
                                            <td class="text-center fw-bold">
                                                ${row.rank === 1 ? '<i class="bi bi-trophy-fill text-warning"></i>' : row.rank}
                                            </td>
                                            <td class="fw-semibold">${row.nama || '—'}</td>
                                            <td class="small text-muted">${membersCell(row)}</td>
                                            <td class="text-center"><span class="badge text-bg-primary">${row.poin_didapat ?? 0}</span></td>
                                            <td class="text-center d-none d-sm-table-cell">${row.set_menang ?? 0}</td>
                                            <td class="text-center d-none d-md-table-cell">${formatGameDiff(row.game_diff_label ?? row.game_menang)}</td>
                                        </tr>`).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>`;

        const headerHtml = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="bi bi-bar-chart-steps me-2"></i>Klasemen Group Match
                </h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-refresh-leaderboard">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>`;

        if (standingsPanel) {
            standingsPanel.innerHTML = headerHtml + standingsHtml;
        }

        if (matchesPanel) {
            matchesPanel.outerHTML = renderFriendlyMatchSessionsHtml(matchSessions);
        } else if (!standingsPanel) {
            container.innerHTML = headerHtml + standingsHtml
                + renderFriendlyMatchSessionsHtml(matchSessions)
                + `<p class="text-muted small text-end mt-2 mb-0">
                        <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
                   </p>`;
        }

        bindRefreshButton();
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const renderFriendlyMatchSessionsHtml = (sessions) => {
        const list = Array.isArray(sessions) ? sessions : [];

        if (list.length === 0) {
            return `
                <div class="friendly-match-schedule mt-4" id="friendly-matches-public">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning-charge me-2"></i>Pertandingan per Sesi
                        </h5>
                    </div>
                    <div class="alert alert-light border text-center mb-0">
                        <i class="bi bi-calendar-x text-muted d-block mb-2 fs-4"></i>
                        Belum ada slot pertandingan. Slot antar grup dibuat setelah kerangka grup lengkap.
                    </div>
                </div>`;
        }

        const sessionsHtml = list.map((session) => {
            const label = session.label
                || (session.sesi ? `Sesi ${session.sesi}` : 'Pertandingan');
            const matches = session.matches || [];

            const rows = matches.map((match) => {
                const badge = match.status_badge || 'secondary';
                const badgeClass = badge === 'warning'
                    ? 'text-bg-warning text-dark'
                    : `text-bg-${badge}`;
                const pairNote = match.winner
                    ? `<div class="small text-success mt-1"><i class="bi bi-trophy me-1"></i>${escapeHtml(match.winner)}</div>`
                    : (!match.pairs_assigned
                        ? `<div class="small text-warning mt-1"><i class="bi bi-people me-1"></i>Belum diisi pasangan</div>`
                        : '');

                return `
                    <tr>
                        <td>
                            <div class="fw-semibold">${escapeHtml(match.grup1 || '—')} vs ${escapeHtml(match.grup2 || '—')}</div>
                        </td>
                        <td>
                            <div>${escapeHtml(match.side1 || 'TBD')}</div>
                            <div class="text-muted small">vs ${escapeHtml(match.side2 || 'TBD')}</div>
                            ${pairNote}
                        </td>
                        <td class="text-center">${escapeHtml(match.score || '—')}</td>
                        <td><span class="badge ${badgeClass}">${escapeHtml(match.status_label || '—')}</span></td>
                    </tr>`;
            }).join('');

            return `
                <div class="card border-0 shadow-sm friendly-match-session" data-sesi="${session.sesi || ''}">
                    <div class="card-header bg-transparent border-bottom-0 pb-0">
                        <h6 class="mb-0 text-secondary">
                            <i class="bi bi-clock-history me-1"></i>${escapeHtml(label)}
                            <span class="badge text-bg-light text-dark border ms-1">${matches.length} tanding</span>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Grup</th>
                                        <th>Pasangan</th>
                                        <th class="text-center">Skor</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>${rows || '<tr><td colspan="4" class="text-muted text-center py-3">Tidak ada pertandingan di sesi ini.</td></tr>'}</tbody>
                            </table>
                        </div>
                    </div>
                </div>`;
        }).join('');

        return `
            <div class="friendly-match-schedule mt-4" id="friendly-matches-public">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-charge me-2"></i>Pertandingan per Sesi
                    </h5>
                </div>
                <div class="d-flex flex-column gap-4">${sessionsHtml}</div>
            </div>`;
    };

    const fetchStandings = async () => {
        try {
            const response = await fetch(refreshUrl, {
                headers: { 'Accept': 'application/json' },
            });
            const json = await response.json();

            if (!json.success) {
                return;
            }

            if (json.type === 'mahjong' || isMahjong) {
                if (!json.data) return;
                renderMahjongStandings(json.data);
            } else if (json.type === 'friendly' || isFriendly) {
                renderFriendlyStandings(json.data || [], json.matches || []);
            } else {
                if (!json.data) return;
                renderGroupStandings(json.data, json.post_league || null);
            }
        } catch (e) {
            console.warn('Leaderboard refresh failed:', e);
        }
    };

    bindRefreshButton();
    setInterval(fetchStandings, 30000);
})();
