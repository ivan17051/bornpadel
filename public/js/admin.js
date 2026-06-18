/**
 * Born Padel Admin — shared AJAX action handlers
 */
const BornPadelAdmin = (function () {
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const showPageLoader = () => {
        if (window.BornPadelPageLoader) {
            window.BornPadelPageLoader.show();
        }
    };

    const reloadPage = () => {
        showPageLoader();
        window.location.reload();
    };

    const goTo = (url) => {
        showPageLoader();
        window.location.href = url;
    };

    const showToast = (message, type = 'success') => {
        const container = document.getElementById('toast-container');
        if (!container) {
            alert(message);
            return;
        }

        const id = 'toast-' + Date.now();
        const bgClass = type === 'success' ? 'text-bg-success' : 'text-bg-danger';

        container.insertAdjacentHTML('beforeend', `
            <div id="${id}" class="toast align-items-center ${bgClass} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);

        const toastEl = document.getElementById(id);
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    };

    const apiRequest = async (url, method = 'POST', body = null) => {
        const options = {
            method,
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        if (body) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.message || 'Terjadi kesalahan.');
        }

        return data;
    };

    const setButtonLoading = (btn, loading, originalHtml) => {
        if (loading) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';
        } else {
            btn.disabled = false;
            btn.innerHTML = originalHtml || btn.dataset.originalHtml || btn.innerHTML;
        }
    };

    const showAlert = (message, type = 'info') => {
        if (!window.Swal) {
            alert(message);
            return;
        }

        const iconMap = {
            success: 'success',
            error: 'error',
            warning: 'warning',
            info: 'info',
        };

        window.Swal.fire({
            toast: true,
            position: 'top-end',
            icon: iconMap[type] || 'info',
            title: message,
            showConfirmButton: false,
            timer: 4500,
            timerProgressBar: true,
        });
    };

    const confirmAction = async ({
        title,
        text,
        confirmText = 'Ya, lanjutkan',
        icon = 'warning',
        confirmButtonColor = '#cda858',
    }) => {
        if (window.Swal) {
            const result = await window.Swal.fire({
                title,
                text,
                icon,
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Batal',
                reverseButtons: true,
                confirmButtonColor,
            });

            return result.isConfirmed;
        }

        return confirm(text ? `${title}\n\n${text}` : title);
    };

    const initPemainActions = () => {
        document.querySelectorAll('.btn-approve').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const confirmed = await confirmAction({
                    title: 'Setujui pemain ini?',
                    confirmText: 'Ya, setujui',
                    icon: 'question',
                    confirmButtonColor: '#198754',
                });
                if (!confirmed) return;

                try {
                    await apiRequest(btn.dataset.url, 'PATCH', {
                        status: 'approved',
                        id_turnamen: parseInt(btn.dataset.turnamen, 10),
                    });
                    showAlert('Pemain berhasil disetujui.', 'success');
                    reloadPage();
                } catch (e) {
                    showAlert(e.message, 'error');
                }
            });
        });

        document.querySelectorAll('.btn-reject').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const confirmed = await confirmAction({
                    title: 'Tolak pemain ini?',
                    confirmText: 'Ya, tolak',
                    icon: 'warning',
                    confirmButtonColor: '#ffc107',
                });
                if (!confirmed) return;

                try {
                    await apiRequest(btn.dataset.url, 'PATCH', {
                        status: 'rejected',
                        id_turnamen: parseInt(btn.dataset.turnamen, 10),
                    });
                    showAlert('Pemain ditolak.', 'warning');
                    reloadPage();
                } catch (e) {
                    showAlert(e.message, 'error');
                }
            });
        });

        document.querySelectorAll('.btn-delete-pemain').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const name = btn.dataset.name || 'pemain ini';
                const confirmed = await confirmAction({
                    title: `Hapus profil ${name}?`,
                    text: 'Tindakan ini tidak dapat dibatalkan.',
                    confirmText: 'Ya, hapus',
                    icon: 'warning',
                    confirmButtonColor: '#dc3545',
                });
                if (!confirmed) return;

                try {
                    await apiRequest(btn.dataset.url, 'DELETE');
                    showAlert('Profil pemain berhasil dihapus.', 'success');
                    btn.closest('tr')?.remove();
                } catch (e) {
                    showAlert(e.message, 'error');
                }
            });
        });
    };

    const initMatchmakingActions = () => {
        const minInput = document.getElementById('min-pemain-grup');
        const maxInput = document.getElementById('max-pemain-grup');
        const previewEl = document.getElementById('group-split-preview');

        const calculateGroupSizes = (total, min, max) => {
            if (total < min || min > max) {
                return null;
            }

            const minGroups = Math.ceil(total / max);
            const maxGroups = Math.floor(total / min);

            for (let groupCount = minGroups; groupCount <= maxGroups; groupCount++) {
                const base = Math.floor(total / groupCount);
                const remainder = total % groupCount;
                const sizes = [];

                for (let i = 0; i < groupCount; i++) {
                    sizes.push(base + (i < remainder ? 1 : 0));
                }

                if (Math.min(...sizes) >= min && Math.max(...sizes) <= max) {
                    return sizes;
                }
            }

            return null;
        };

        const updateGroupSplitPreview = () => {
            if (!previewEl || !minInput || !maxInput) {
                return;
            }

            const total = parseInt(previewEl.dataset.approved, 10) || 0;
            const min = parseInt(minInput.value, 10);
            const max = parseInt(maxInput.value, 10);
            const sizes = calculateGroupSizes(total, min, max);

            if (!sizes) {
                previewEl.textContent = 'Pemain tidak cukup untuk pembagian grup dengan batas ini.';
                return;
            }

            previewEl.textContent = `${total} pemain → ${sizes.length} grup (${sizes.join(' + ')})`;
        };

        if (minInput && maxInput) {
            minInput.addEventListener('input', updateGroupSplitPreview);
            maxInput.addEventListener('input', updateGroupSplitPreview);
        }

        const getGroupSettings = () => ({
            min_pemain_grup: parseInt(minInput?.value || '3', 10),
            max_pemain_grup: parseInt(maxInput?.value || '4', 10),
        });

        const closeBtn = document.getElementById('btn-close-registration');

        if (closeBtn && !closeBtn.disabled) {
            closeBtn.addEventListener('click', async () => {
                const confirmed = await confirmAction({
                    title: 'Tutup pendaftaran turnamen ini?',
                    text: 'Pemain tidak bisa mendaftar lagi.',
                    confirmText: 'Ya, tutup pendaftaran',
                });
                if (!confirmed) return;

                const original = closeBtn.innerHTML;
                setButtonLoading(closeBtn, true);

                try {
                    const data = await apiRequest(closeBtn.dataset.url, 'POST', {
                        id_turnamen: parseInt(closeBtn.dataset.turnamen, 10),
                    });
                    showToast(data.message);
                    reloadPage();
                } catch (e) {
                    showToast(e.message, 'error');
                    setButtonLoading(closeBtn, false, original);
                }
            });
        }

        const endGroupBtn = document.getElementById('btn-end-group-stage');

        if (endGroupBtn && !endGroupBtn.disabled) {
            endGroupBtn.addEventListener('click', async () => {
                const confirmed = await confirmAction({
                    title: 'Akhiri fase grup dan buat bracket knockout?',
                    text: '2 pemain teratas dari setiap grup akan lolos ke babak gugur.',
                    confirmText: 'Ya, buat bracket',
                });
                if (!confirmed) return;

                const original = endGroupBtn.innerHTML;
                setButtonLoading(endGroupBtn, true);

                try {
                    const data = await apiRequest(endGroupBtn.dataset.url, 'POST', {
                        id_turnamen: parseInt(endGroupBtn.dataset.turnamen, 10),
                    });
                    showToast(data.message);
                    goTo('/admin/bracket');
                } catch (e) {
                    showToast(e.message, 'error');
                    setButtonLoading(endGroupBtn, false, original);
                }
            });
        }

        document.querySelectorAll('.btn-matchmaking-grup').forEach((btn) => {
            if (btn.disabled) {
                return;
            }

            btn.addEventListener('click', async () => {
                const mode = btn.dataset.mode || 'random';
                const groupSettings = getGroupSettings();
                const total = parseInt(previewEl?.dataset.approved || '0', 10);
                const sizes = calculateGroupSizes(
                    total,
                    groupSettings.min_pemain_grup,
                    groupSettings.max_pemain_grup
                );

                if (!sizes) {
                    showAlert('Pemain tidak cukup atau batas min/max grup tidak valid.', 'error');
                    return;
                }

                const previewText = `${total} pemain → ${sizes.length} grup (${sizes.join(' + ')})`;
                const confirmed = await confirmAction(mode === 'by_rating'
                    ? {
                        title: 'Kelompokkan pemain berdasarkan rating?',
                        text: `${previewText}. Jadwal pertandingan akan dibuat. Tindakan ini tidak dapat diulang.`,
                        confirmText: 'Ya, buat grup rating',
                    }
                    : {
                        title: 'Acak pemain ke grup?',
                        text: `${previewText}. Jadwal pertandingan akan dibuat. Tindakan ini tidak dapat diulang.`,
                        confirmText: 'Ya, random grup',
                    });
                if (!confirmed) return;

                const original = btn.innerHTML;
                setButtonLoading(btn, true);

                try {
                    const data = await apiRequest(btn.dataset.url, 'POST', {
                        id_turnamen: parseInt(btn.dataset.turnamen, 10),
                        mode,
                        ...groupSettings,
                    });
                    showToast(data.message);
                    reloadPage();
                } catch (e) {
                    showToast(e.message, 'error');
                    setButtonLoading(btn, false, original);
                }
            });
        });
    };

    const initScoreModal = () => {
        const modalEl = document.getElementById('scoreModal');
        if (!modalEl) return;

        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('score-form');
        const setsContainer = document.getElementById('score-sets-container');
        const addSetBtn = document.getElementById('btn-add-set');
        const errorEl = document.getElementById('score-form-error');
        const saveBtn = document.getElementById('btn-save-score');
        const metaEl = document.getElementById('score-modal-meta');
        const readonlyEl = document.getElementById('score-modal-readonly');
        const MIN_SETS = 3;
        const MAX_SETS = 5;
        let storeUrl = null;
        let isReadonly = false;

        const buildSetRow = (setNumber, values = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 align-items-center set-row';
            row.dataset.set = String(setNumber);

            const p1 = values.p1 ?? '';
            const p2 = values.p2 ?? '';

            row.innerHTML = `
                <div class="col-4 text-center">
                    <span class="badge text-bg-secondary set-label">Set ${setNumber}</span>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1 btn-remove-set d-none" title="Hapus set">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
                <div class="col-4">
                    <input type="number" class="form-control form-control-sm text-center skor-p1"
                           min="0" max="99" placeholder="0" value="${p1}">
                </div>
                <div class="col-4">
                    <input type="number" class="form-control form-control-sm text-center skor-p2"
                           min="0" max="99" placeholder="0" value="${p2}">
                </div>
            `;

            return row;
        };

        const updateSetControls = () => {
            const rows = setsContainer.querySelectorAll('.set-row');
            const count = rows.length;

            rows.forEach((row, index) => {
                row.dataset.set = String(index + 1);
                const label = row.querySelector('.set-label');
                if (label) {
                    label.textContent = `Set ${index + 1}`;
                }

                const removeBtn = row.querySelector('.btn-remove-set');
                if (removeBtn) {
                    removeBtn.classList.toggle('d-none', count <= MIN_SETS);
                }
            });

            if (addSetBtn) {
                addSetBtn.disabled = count >= MAX_SETS;
                addSetBtn.classList.toggle('d-none', count >= MAX_SETS);
            }
        };

        const renderSets = (existingScores = []) => {
            setsContainer.innerHTML = '';

            const rowCount = Math.min(
                MAX_SETS,
                Math.max(MIN_SETS, existingScores.length || MIN_SETS)
            );

            for (let i = 0; i < rowCount; i++) {
                const score = existingScores[i];
                const values = score
                    ? { p1: score.skor_pemain1, p2: score.skor_pemain2 }
                    : {};
                setsContainer.appendChild(buildSetRow(i + 1, values));
            }

            updateSetControls();
        };

        const resetForm = () => {
            renderSets();
            if (addSetBtn) addSetBtn.classList.remove('d-none');
            errorEl.classList.add('d-none');
            form.classList.remove('d-none');
            readonlyEl.classList.add('d-none');
            saveBtn.classList.remove('d-none');
            isReadonly = false;
        };

        if (addSetBtn) {
            addSetBtn.addEventListener('click', () => {
                const count = setsContainer.querySelectorAll('.set-row').length;
                if (count >= MAX_SETS) return;

                setsContainer.appendChild(buildSetRow(count + 1));
                updateSetControls();
            });
        }

        setsContainer.addEventListener('click', (event) => {
            const removeBtn = event.target.closest('.btn-remove-set');
            if (!removeBtn) return;

            const rows = setsContainer.querySelectorAll('.set-row');
            if (rows.length <= MIN_SETS) return;

            removeBtn.closest('.set-row')?.remove();
            updateSetControls();
        });

        const openModal = async (showUrl, saveUrl, readonly) => {
            resetForm();
            storeUrl = saveUrl;
            isReadonly = readonly;

            try {
                const response = await fetch(showUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await response.json();
                const match = json.data;

                document.getElementById('score-p1-name').textContent = match.pemain1.nama;
                document.getElementById('score-p2-name').textContent = match.pemain2.nama;
                metaEl.innerHTML = `<strong>${match.nama_ronde}</strong>${match.grup ? ' · ' + match.grup : ''}`;

                if (!match.ready_for_scoring && match.status !== 'completed') {
                    form.classList.add('d-none');
                    saveBtn.classList.add('d-none');
                    if (addSetBtn) addSetBtn.classList.add('d-none');
                    readonlyEl.classList.remove('d-none');
                    readonlyEl.innerHTML = '<p class="text-muted mb-0">Menunggu kedua pemain ditentukan dari pertandingan sebelumnya.</p>';
                } else if (readonly || match.status === 'completed') {
                    isReadonly = true;
                    form.classList.add('d-none');
                    saveBtn.classList.add('d-none');
                    if (addSetBtn) addSetBtn.classList.add('d-none');
                    readonlyEl.classList.remove('d-none');
                    readonlyEl.innerHTML = match.skor.length
                        ? match.skor.map((s) =>
                            `<div class="d-flex justify-content-between border-bottom py-2">
                                <span>Set ${s.set_ke}</span>
                                <strong>${s.skor_pemain1} - ${s.skor_pemain2}</strong>
                            </div>`
                          ).join('')
                        : '<p class="text-muted mb-0">Belum ada skor.</p>';
                } else if (match.skor.length) {
                    renderSets(match.skor);
                }

                modal.show();
            } catch (e) {
                showToast('Gagal memuat data pertandingan.', 'error');
            }
        };

        document.querySelectorAll('.btn-input-score').forEach((btn) => {
            btn.addEventListener('click', () => {
                openModal(btn.dataset.showUrl, btn.dataset.storeUrl, false);
            });
        });

        document.querySelectorAll('.btn-view-score').forEach((btn) => {
            btn.addEventListener('click', () => {
                openModal(btn.dataset.showUrl, null, true);
            });
        });

        saveBtn.addEventListener('click', async () => {
            if (isReadonly || !storeUrl) return;

            const sets = [];
            form.querySelectorAll('.set-row').forEach((row) => {
                const p1 = row.querySelector('.skor-p1').value;
                const p2 = row.querySelector('.skor-p2').value;
                if (p1 !== '' && p2 !== '') {
                    sets.push({
                        skor_pemain1: parseInt(p1, 10),
                        skor_pemain2: parseInt(p2, 10),
                    });
                }
            });

            if (sets.length < MIN_SETS) {
                errorEl.textContent = `Minimal ${MIN_SETS} set harus diisi.`;
                errorEl.classList.remove('d-none');
                return;
            }

            errorEl.classList.add('d-none');
            const original = saveBtn.innerHTML;
            setButtonLoading(saveBtn, true);

            try {
                const data = await apiRequest(storeUrl, 'POST', { sets });
                showToast(data.message);
                modal.hide();
                reloadPage();
            } catch (e) {
                errorEl.textContent = e.message;
                errorEl.classList.remove('d-none');
                setButtonLoading(saveBtn, false, original);
            }
        });
    };

    const initPasswordModal = () => {
        const modalEl = document.getElementById('passwordModal');
        const openBtn = document.getElementById('btn-open-password-modal');
        const saveBtn = document.getElementById('btn-save-password');
        const form = document.getElementById('password-form');

        if (!modalEl || !openBtn || !saveBtn || !form) {
            return;
        }

        const modal = new bootstrap.Modal(modalEl);
        const updateUrl = saveBtn.dataset.url;

        const clearErrors = () => {
            form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
            form.querySelectorAll('[data-feedback]').forEach((el) => {
                el.textContent = '';
            });
        };

        const showErrors = (errors) => {
            if (!errors) {
                return;
            }

            Object.entries(errors).forEach(([field, messages]) => {
                const input = form.querySelector(`[name="${field}"]`);
                const feedback = form.querySelector(`[data-feedback="${field}"]`);

                if (input) {
                    input.classList.add('is-invalid');
                }
                if (feedback) {
                    feedback.textContent = messages[0] || '';
                }
            });
        };

        const resetForm = () => {
            form.reset();
            clearErrors();
        };

        openBtn.addEventListener('click', () => {
            resetForm();
            modal.show();
        });

        modalEl.addEventListener('hidden.bs.modal', resetForm);

        saveBtn.addEventListener('click', async () => {
            clearErrors();

            const original = saveBtn.innerHTML;
            setButtonLoading(saveBtn, true);

            try {
                const response = await fetch(updateUrl, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        current_password: form.current_password.value,
                        password: form.password.value,
                        password_confirmation: form.password_confirmation.value,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    if (data.errors) {
                        showErrors(data.errors);
                    } else {
                        showToast(data.message || 'Terjadi kesalahan.', 'error');
                    }
                    return;
                }

                showToast(data.message);
                modal.hide();
            } catch (e) {
                showToast(e.message || 'Terjadi kesalahan.', 'error');
            } finally {
                setButtonLoading(saveBtn, false, original);
            }
        });
    };

    return {
        initPemainActions,
        initMatchmakingActions,
        initScoreModal,
        initPasswordModal,
        showToast,
        showAlert,
        apiRequest,
    };
})();
