/**
 * Tournament tree bracket — flexbox layout + SVG connectors
 */
(function () {
    const container = document.getElementById('live-bracket');
    if (!container) return;

    const refreshUrl = container.dataset.refreshUrl;
    const editable = container.dataset.editable === '1';
    const profileBase = container.dataset.profileBase || '/pemain/';

    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const slotAttrs = (match, slot, canEdit) => {
        if (!canEdit) return '';
        const pemainId = slot === 1 ? match.pemain1_id : match.pemain2_id;
        const pesertaId = slot === 1 ? match.peserta1_id : match.peserta2_id;
        return ` role="button" tabindex="0" title="Klik untuk menukar peserta"`
            + ` data-match-id="${match.id}" data-slot="${slot}"`
            + ` data-pemain-id="${pemainId ?? ''}" data-peserta-id="${pesertaId ?? ''}"`;
    };

    const parseScores = (skor, side) => {
        if (!skor) return '';
        return skor.split(', ').map((s) => s.split('-')[side] ?? '').join(' ');
    };

    const statusBadge = (match) => {
        if (match.status === 'scheduled' && match.pemain1_id && match.pemain2_id) {
            return '<div class="bracket-match-status"><span class="badge bg-secondary">Upcoming</span></div>';
        }
        if (match.status === 'scheduled') {
            return '<div class="bracket-match-status"><span class="badge bg-light text-dark border">Menunggu</span></div>';
        }
        if (match.is_bye) {
            return '<div class="bracket-match-status"><span class="badge bg-info text-dark">Bye</span></div>';
        }
        return '';
    };

    const renderPemainNames = (match, side) => {
        const ids = side === 1
            ? (match.pemain1_ids || (match.pemain1_id ? [match.pemain1_id] : []))
            : (match.pemain2_ids || (match.pemain2_id ? [match.pemain2_id] : []));
        const label = side === 1 ? match.pemain1 : match.pemain2;
        const players = side === 1 ? (match.pemain1_players || []) : (match.pemain2_players || []);

        if (players.length) {
            return players.map((player, index) => {
                const name = esc(player.nama || 'Pemain');
                const link = player.id
                    ? `<a href="${profileBase}${player.id}" class="pemain-profile-link">${name}</a>`
                    : name;

                return index < players.length - 1 ? `${link}<span class="text-muted"> / </span>` : link;
            }).join('');
        }

        if (ids.length === 1) {
            return `<a href="${profileBase}${ids[0]}" class="pemain-profile-link">${esc(label || 'Pemain')}</a>`;
        }

        if (ids.length > 1) {
            const names = String(label || '').split(' / ');

            return ids.map((id, index) => {
                const link = `<a href="${profileBase}${id}" class="pemain-profile-link">${esc(names[index] || 'Pemain')}</a>`;
                return index < ids.length - 1 ? `${link}<span class="text-muted"> / </span>` : link;
            }).join('');
        }

        return esc(label || 'TBD');
    };

    const renderPodiumNames = (entry) => {
        const players = entry?.players || [];

        if (players.length) {
            return players.map((player, index) => {
                const name = esc(player.nama || 'Pemain');
                const link = player.id
                    ? `<a href="${profileBase}${player.id}" class="pemain-profile-link">${name}</a>`
                    : name;

                return index < players.length - 1 ? `${link}<span class="text-muted"> / </span>` : link;
            }).join('');
        }

        return esc(entry?.label || '');
    };

    const renderMatchCard = (match, opts = {}) => {
        const { slot1Editable = false, slot2Editable = false, isThirdPlace = false } = opts;
        const p1Winner = match.pemenang_id && match.pemain1_id === match.pemenang_id;
        const p2Winner = match.pemenang_id && match.pemain2_id === match.pemenang_id;
        const p1Scores = match.skor && match.status === 'completed' ? parseScores(match.skor, 0) : '';
        const p2Scores = match.skor && match.status === 'completed' ? parseScores(match.skor, 1) : '';

        return `
            <div class="bracket-match ${isThirdPlace ? 'is-third-place' : ''} ${match.status === 'completed' ? 'is-completed' : ''} ${match.pemenang_id ? 'has-winner' : ''}">
                <div class="bracket-player ${p1Winner ? 'is-winner' : ''} ${!match.pemain1_id ? 'is-tbd' : ''} ${slot1Editable ? 'is-editable' : ''}"${slotAttrs(match, 1, slot1Editable)}>
                    <span class="bracket-player-name">${renderPemainNames(match, 1)}</span>
                    ${p1Scores ? `<span class="bracket-score-badge">${esc(p1Scores)}</span>` : ''}
                </div>
                <div class="bracket-player ${p2Winner ? 'is-winner' : ''} ${!match.pemain2_id ? 'is-tbd' : ''} ${slot2Editable ? 'is-editable' : ''}"${slotAttrs(match, 2, slot2Editable)}>
                    <span class="bracket-player-name">${renderPemainNames(match, 2)}</span>
                    ${p2Scores ? `<span class="bracket-score-badge">${esc(p2Scores)}</span>` : ''}
                </div>
                ${statusBadge(match)}
            </div>`;
    };

    const computeLeafCount = (bracket) => {
        if (!bracket?.length) return 1;
        const first = bracket[0].matches?.filter((m) => !m.is_third_place) ?? [];
        return Math.max(1, first.length);
    };

    const hasThirdPlace = (bracket) => bracket.some((r) => r.matches.some((m) => m.is_third_place));

    const photoPlaceholder = () => container.dataset.photoPlaceholder || '';

    const renderPodiumPhotos = (players = []) => {
        const fallback = photoPlaceholder();
        if (!players.length) {
            return '<div class="bracket-podium-photo bracket-podium-photo--empty"><i class="bi bi-person"></i></div>';
        }

        return players.map((player) => {
            const src = esc(player.foto_url || fallback);
            const name = esc(player.nama || '');
            const onError = fallback
                ? ` onerror="this.onerror=null;this.src='${esc(fallback)}';"`
                : '';
            return `<img src="${src}" alt="${name}" class="bracket-podium-photo" width="56" height="56" loading="lazy" decoding="async" title="${name}"${onError}>`;
        }).join('');
    };

    const renderPodiumCard = (mod, label, icon, entry) => {
        if (!entry?.label) return '';
        return `
            <div class="bracket-podium-card bracket-podium-card--${mod}">
                <div class="bracket-podium-rank">
                    <i class="bi ${icon}"></i>
                    <span>${label}</span>
                </div>
                <div class="bracket-podium-photos">${renderPodiumPhotos(entry.players || [])}</div>
                <div class="bracket-podium-names">${renderPodiumNames(entry)}</div>
            </div>`;
    };

    const resolvePodium = (bracket) => {
        const finalRound = bracket.find((r) => r.nama_ronde === 'Final');
        const finalMatch = finalRound?.matches?.find((m) => !m.is_third_place);
        const thirdMatch = bracket.flatMap((r) => r.matches).find((m) => m.is_third_place);

        return {
            first: finalMatch?.pemenang
                ? { label: finalMatch.pemenang, players: finalMatch.pemenang_players || [] }
                : null,
            second: finalMatch?.runner_up
                ? { label: finalMatch.runner_up, players: finalMatch.runner_up_players || [] }
                : null,
            third: thirdMatch?.pemenang
                ? { label: thirdMatch.pemenang, players: thirdMatch.pemenang_players || [] }
                : null,
        };
    };

    const renderPodium = (bracket) => {
        const { first, second, third } = resolvePodium(bracket);
        if (!first && !second && !third) return '';

        return `
            <div class="bracket-podium mb-4">
                <div class="bracket-podium-grid">
                    ${renderPodiumCard('second', 'Juara 2', 'bi-award-fill', second)}
                    ${renderPodiumCard('first', 'Juara 1', 'bi-trophy-fill', first)}
                    ${renderPodiumCard('third', 'Juara 3', 'bi-award-fill', third)}
                </div>
            </div>`;
    };

    const renderNode = (match, opts = {}) => {
        const {
            extraClass = '',
            subLabel = '',
            slot1Editable = false,
            slot2Editable = false,
            isThirdPlace = false,
        } = opts;

        return `
            <div class="bracket-node ${extraClass}"
                 data-match-id="${match.id}"
                 data-next-win="${match.id_next_pertandingan ?? ''}"
                 data-next-lose="${match.id_next_pertandingan_kalah ?? ''}">
                ${subLabel}
                ${renderMatchCard(match, { slot1Editable, slot2Editable, isThirdPlace })}
            </div>`;
    };

    const renderRound = (round, roundIndex, leafCount, editableRound) => {
        const progression = round.matches.filter((m) => !m.is_third_place);
        const extras = round.matches.filter((m) => m.is_third_place);
        const matchCount = Math.max(1, progression.length);
        const stride = Math.floor(leafCount / matchCount);
        const isFinalCol = round.nama_ronde === 'Final';

        const progressionHtml = progression.map((match) => {
            const isBye = match.status === 'completed'
                && ((match.pemain1_id && !match.pemain2_id) || (!match.pemain1_id && match.pemain2_id));
            const matchEditable = editableRound && !match.skor && (match.status !== 'completed' || isBye);

            let subLabel = '';
            if (isFinalCol && extras.length) {
                subLabel = '<div class="bracket-subround-title bracket-subround-title-first text-center text-uppercase small fw-bold mb-2"><i class="bi bi-trophy me-1"></i>Perebutan Juara 1</div>';
            }

            return `
                <div class="bracket-slot" style="flex: ${stride} 1 0;">
                    ${renderNode(match, {
                        subLabel,
                        slot1Editable: matchEditable && !!match.pemain1_id,
                        slot2Editable: matchEditable && !!match.pemain2_id,
                    })}
                </div>`;
        }).join('');

        const extrasHtml = extras.length ? `
            <div class="bracket-flex bracket-flex--extra">
                ${extras.map((match) => `
                    <div class="bracket-slot bracket-slot--extra">
                        ${renderNode(match, {
                            extraClass: 'bracket-node--third',
                            subLabel: '<div class="bracket-subround-title text-center text-uppercase small fw-bold mb-2"><i class="bi bi-award me-1"></i>Perebutan Juara 3</div>',
                            isThirdPlace: true,
                        })}
                    </div>
                `).join('')}
            </div>` : '';

        return `
            <div class="bracket-col ${isFinalCol ? 'bracket-col--final' : ''}">
                <div class="bracket-col-title text-center text-uppercase small fw-bold text-muted mb-3">${esc(round.nama_ronde)}</div>
                <div class="bracket-flex bracket-flex--tree">
                    ${progressionHtml}
                </div>
                ${extrasHtml}
            </div>`;
    };

    const getMatchRect = (node, board) => {
        const card = node.querySelector('.bracket-match') || node;
        const boardRect = board.getBoundingClientRect();
        const rect = card.getBoundingClientRect();
        return {
            right: rect.right - boardRect.left,
            left: rect.left - boardRect.left,
            cy: rect.top + rect.height / 2 - boardRect.top,
        };
    };

    const addPath = (svg, d, className) => {
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', d);
        path.setAttribute('class', className);
        svg.appendChild(path);
    };

    const drawSingleLink = (svg, from, to, board, className) => {
        const a = getMatchRect(from, board);
        const b = getMatchRect(to, board);
        const midX = a.right + (b.left - a.right) / 2;
        addPath(svg, `M ${a.right} ${a.cy} H ${midX} V ${b.cy} H ${b.left}`, className);
    };

    const drawMergeLink = (svg, sources, target, board, className) => {
        const sorted = [...sources].sort((x, y) => getMatchRect(x, board).cy - getMatchRect(y, board).cy);
        const targetRect = getMatchRect(target, board);

        if (sorted.length === 1) {
            drawSingleLink(svg, sorted[0], target, board, className);
            return;
        }

        const top = getMatchRect(sorted[0], board);
        const bottom = getMatchRect(sorted[sorted.length - 1], board);
        const startX = Math.max(top.right, bottom.right);
        const midX = startX + (targetRect.left - startX) / 2;
        const mergeY = (top.cy + bottom.cy) / 2;

        addPath(svg, `M ${top.right} ${top.cy} H ${midX}`, className);
        addPath(svg, `M ${bottom.right} ${bottom.cy} H ${midX}`, className);
        addPath(svg, `M ${midX} ${top.cy} V ${bottom.cy}`, className);

        if (Math.abs(mergeY - targetRect.cy) < 2) {
            addPath(svg, `M ${midX} ${mergeY} H ${targetRect.left}`, className);
        } else {
            addPath(svg, `M ${midX} ${mergeY} H ${targetRect.left} V ${targetRect.cy}`, className);
        }
    };

    const drawBracketLines = (board) => {
        if (!board) return;

        const svg = board.querySelector('.bracket-svg');
        if (!svg) return;

        const boardRect = board.getBoundingClientRect();
        svg.setAttribute('width', String(boardRect.width));
        svg.setAttribute('height', String(boardRect.height));
        svg.innerHTML = '';

        const nodes = board.querySelectorAll('[data-match-id]');
        const byId = new Map();
        nodes.forEach((node) => byId.set(String(node.dataset.matchId), node));

        const feeders = new Map();

        nodes.forEach((node) => {
            const winId = node.dataset.nextWin;
            const loseId = node.dataset.nextLose;

            if (winId) {
                if (!feeders.has(winId)) feeders.set(winId, { win: [], lose: [] });
                feeders.get(winId).win.push(node);
            }
            if (loseId) {
                if (!feeders.has(loseId)) feeders.set(loseId, { win: [], lose: [] });
                feeders.get(loseId).lose.push(node);
            }
        });

        feeders.forEach((groups, targetId) => {
            const target = byId.get(String(targetId));
            if (!target) return;

            if (groups.win.length) {
                drawMergeLink(svg, groups.win, target, board, 'bracket-line--win');
            }
            if (groups.lose.length) {
                drawMergeLink(svg, groups.lose, target, board, 'bracket-line--lose');
            }
        });
    };

    let resizeTimer;
    const scheduleDraw = (board) => {
        if (!board) return;
        requestAnimationFrame(() => drawBracketLines(board));
    };

    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            scheduleDraw(container.querySelector('.bracket-board'));
        }, 120);
    });

    const renderBracket = (bracket) => {
        const dynamic = document.getElementById('bracket-dynamic');
        if (!dynamic) return;

        if (!bracket || bracket.length === 0) {
            dynamic.innerHTML = `
                <div class="alert alert-light border text-center mb-0">
                    <i class="bi bi-diagram-2 text-muted d-block mb-2 fs-4"></i>
                    Bracket knockout belum tersedia.
                </div>`;
            return;
        }

        const leafCount = computeLeafCount(bracket);
        const third = hasThirdPlace(bracket);

        container.dataset.leafCount = String(leafCount);
        container.dataset.hasThird = third ? '1' : '0';

        const rounds = bracket
            .map((round, i) => renderRound(round, i, leafCount, editable && i === 0))
            .join('');

        const refreshNote = refreshUrl ? `
            <p class="text-muted small text-end mt-2 mb-0">
                <i class="bi bi-broadcast me-1"></i> Diperbarui otomatis setiap 30 detik
            </p>` : '';

        dynamic.innerHTML = `
            ${renderPodium(bracket)}
            <div class="bracket-tree overflow-auto pb-3">
                <div class="bracket-board" style="--leaf-count: ${leafCount}; --round-count: ${bracket.length};">
                    <svg class="bracket-svg" aria-hidden="true"></svg>
                    <div class="bracket-cols">${rounds}</div>
                </div>
            </div>
            ${refreshNote}`;

        scheduleDraw(dynamic.querySelector('.bracket-board'));
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

    window.addEventListener('load', () => {
        scheduleDraw(document.querySelector('#bracket-dynamic .bracket-board'));
    });

    scheduleDraw(document.querySelector('#bracket-dynamic .bracket-board'));
})();
