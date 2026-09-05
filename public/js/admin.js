/**
 * Born Padel Admin — shared AJAX action handlers
 */
const BornPadelAdmin = (function () {
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    let partnerModalDocumentListenerBound = false;

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

    const resolveWorkspaceKategoriId = (el = null) => {
        const fromEl = el && el.dataset ? el.dataset.kategori : null;
        if (fromEl) {
            const parsed = parseInt(fromEl, 10);
            if (!Number.isNaN(parsed) && parsed > 0) return parsed;
        }
        const root = document.querySelector('[data-workspace-kategori]');
        if (root && root.dataset.workspaceKategori) {
            const parsed = parseInt(root.dataset.workspaceKategori, 10);
            if (!Number.isNaN(parsed) && parsed > 0) return parsed;
        }
        const select = document.getElementById('id_kategori');
        if (select && select.value) {
            const parsed = parseInt(select.value, 10);
            if (!Number.isNaN(parsed) && parsed > 0) return parsed;
        }
        return null;
    };
    const apiRequest = async (url, method = 'POST', body = null) => {
        const options = {
            method,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        };

        if (body) {
            options.headers['Content-Type'] = 'application/json';
            if (body.id_turnamen && !body.id_kategori) {
                const kategoriId = resolveWorkspaceKategoriId();
                if (kategoriId) body.id_kategori = kategoriId;
            }
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const firstValidationError = data.errors
                ? Object.values(data.errors).flat().find(Boolean)
                : null;
            throw new Error(firstValidationError || data.message || 'Terjadi kesalahan.');
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

    const initPemainTableSort = () => {
        document.querySelectorAll('#pemain-table .pemain-table-sort-link').forEach((link) => {
            link.addEventListener('click', () => showPageLoader());
        });
    };

    const initBulkApproval = () => {
        const cards = Array.from(document.querySelectorAll('.pemain-table-card[data-bulk-approve-url]'));
        if (cards.length === 0) return;

        cards.forEach((card) => {
            const bulkBtns = Array.from(card.querySelectorAll('.btn-bulk-approve'));
            const selectAll = card.querySelector('.select-all-approvable');
            if (bulkBtns.length === 0) return;

            const checkboxes = () => Array.from(card.querySelectorAll('.peserta-bulk-checkbox'));

            const updateBulkControls = () => {
                const selected = checkboxes().filter((cb) => cb.checked);
                const count = selected.length;

                bulkBtns.forEach((btn) => {
                    btn.disabled = count === 0;
                    btn.title = count === 0
                        ? 'Pilih peserta pada tabel terlebih dahulu'
                        : `Setujui ${count} peserta terpilih`;
                });

                if (selectAll) {
                    const all = checkboxes();
                    selectAll.checked = all.length > 0 && selected.length === all.length;
                    selectAll.indeterminate = selected.length > 0 && selected.length < all.length;
                }
            };

            checkboxes().forEach((cb) => cb.addEventListener('change', updateBulkControls));

            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    checkboxes().forEach((cb) => {
                        cb.checked = selectAll.checked;
                    });
                    updateBulkControls();
                });
            }

            const runBulkApprove = async (triggerBtn) => {
                const selectedIds = checkboxes()
                    .filter((cb) => cb.checked)
                    .map((cb) => parseInt(cb.value, 10));

                if (!selectedIds.length) return;

                const confirmed = await confirmAction({
                    title: `Setujui ${selectedIds.length} peserta terpilih?`,
                    confirmText: 'Ya, setujui semua',
                    icon: 'question',
                    confirmButtonColor: '#198754',
                });

                if (!confirmed) return;

                bulkBtns.forEach((btn) => setButtonLoading(btn, true));

                try {
                    await apiRequest(card.dataset.bulkApproveUrl, 'POST', {
                        id_turnamen: parseInt(card.dataset.turnamenId, 10),
                        peserta_ids: selectedIds,
                    });
                    showAlert(`${selectedIds.length} peserta berhasil disetujui.`, 'success');
                    reloadPage();
                } catch (e) {
                    showAlert(e.message, 'error');
                    bulkBtns.forEach((btn) => setButtonLoading(btn, false));
                }
            };

            bulkBtns.forEach((btn) => {
                btn.addEventListener('click', () => runBulkApprove(btn));
            });

            updateBulkControls();
        });
    };

    const apiRequestFormData = async (url, formData) => {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            const firstValidationError = data.errors
                ? Object.values(data.errors).flat().find(Boolean)
                : null;
            throw new Error(firstValidationError || data.message || 'Terjadi kesalahan.');
        }

        return data;
    };

    const syncPhoneInputsIn = (root) => {
        (root || document).querySelectorAll('[data-phone-input]').forEach((group) => {
            const country = group.querySelector('[data-phone-country]');
            const local = group.querySelector('[data-phone-local]');
            const hidden = group.querySelector('[data-phone-hidden]');
            if (!country || !local || !hidden) return;

            let digits = String(local.value || '').replace(/\D+/g, '');
            if (digits.startsWith('0')) {
                digits = digits.replace(/^0+/, '');
            }
            const code = country.value || '+62';
            hidden.value = digits ? `${code}${digits}` : '';
        });
    };

    const initAvailablePemainActions = () => {
        const card = document.getElementById('available-pemain-card');
        if (!card) return;

        const turnamenId = String(card.dataset.turnamenId || '');
        const storageKey = `bornpadel.available-pemain.selected.${turnamenId}`;
        const bulkBtns = Array.from(document.querySelectorAll('.btn-bulk-register'));
        const selectAll = document.getElementById('select-all-available');
        const selectedCountBadge = document.getElementById('available-selected-count');
        const checkboxes = () => Array.from(document.querySelectorAll('.available-pemain-checkbox'));

        const readSelection = () => {
            try {
                const raw = sessionStorage.getItem(storageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                return new Set(
                    (Array.isArray(parsed) ? parsed : [])
                        .map((id) => parseInt(id, 10))
                        .filter((id) => Number.isInteger(id) && id > 0)
                );
            } catch (e) {
                return new Set();
            }
        };

        const writeSelection = (selected) => {
            sessionStorage.setItem(storageKey, JSON.stringify(Array.from(selected)));
        };

        let selectedIds = readSelection();

        const updateBulkControls = () => {
            const count = selectedIds.size;
            const visible = checkboxes();
            const visibleChecked = visible.filter((cb) => cb.checked);

            bulkBtns.forEach((btn) => {
                btn.disabled = count === 0;
                btn.title = count === 0
                    ? 'Pilih pemain pada tabel terlebih dahulu'
                    : `Daftarkan ${count} pemain terpilih`;
            });

            if (selectedCountBadge) {
                if (count > 0) {
                    selectedCountBadge.textContent = `${count} dipilih`;
                    selectedCountBadge.classList.remove('d-none');
                } else {
                    selectedCountBadge.classList.add('d-none');
                }
            }

            if (selectAll) {
                selectAll.checked = visible.length > 0 && visibleChecked.length === visible.length;
                selectAll.indeterminate = visibleChecked.length > 0 && visibleChecked.length < visible.length;
            }
        };

        const syncCheckbox = (cb) => {
            const id = parseInt(cb.value, 10);
            if (!Number.isInteger(id)) return;

            if (cb.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
        };

        const restoreVisibleChecks = () => {
            checkboxes().forEach((cb) => {
                const id = parseInt(cb.value, 10);
                cb.checked = selectedIds.has(id);
            });
            updateBulkControls();
        };

        checkboxes().forEach((cb) => {
            cb.addEventListener('change', () => {
                syncCheckbox(cb);
                writeSelection(selectedIds);
                updateBulkControls();
            });
        });

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                checkboxes().forEach((cb) => {
                    cb.checked = selectAll.checked;
                    syncCheckbox(cb);
                });
                writeSelection(selectedIds);
                updateBulkControls();
            });
        }

        const clearSelection = () => {
            selectedIds = new Set();
            writeSelection(selectedIds);
            checkboxes().forEach((cb) => {
                cb.checked = false;
            });

            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }

            updateBulkControls();
        };

        const runBulkRegister = async () => {
            const ids = Array.from(selectedIds);

            if (!ids.length) return;

            const confirmed = await confirmAction({
                title: `Daftarkan ${ids.length} pemain terpilih?`,
                text: 'Pemain akan didaftarkan dengan status Approved. Termasuk pilihan dari hasil pencarian lain.',
                confirmText: 'Ya, daftarkan',
                icon: 'question',
                confirmButtonColor: '#198754',
            });

            if (!confirmed) return;

            bulkBtns.forEach((btn) => setButtonLoading(btn, true));

            try {
                const result = await apiRequest(card.dataset.bulkRegisterUrl, 'POST', {
                    id_turnamen: parseInt(card.dataset.turnamenId, 10),
                    pemain_ids: ids,
                    status: 'approved',
                });
                clearSelection();
                showAlert(result.message || `${ids.length} pemain berhasil didaftarkan.`, 'success');
                reloadPage();
            } catch (e) {
                showAlert(e.message, 'error');
                bulkBtns.forEach((btn) => setButtonLoading(btn, false));
            }
        };

        bulkBtns.forEach((btn) => {
            btn.addEventListener('click', () => runBulkRegister());
        });

        restoreVisibleChecks();

        const form = document.getElementById('tambahPemainForm');
        const modalEl = document.getElementById('tambahPemainModal');
        const saveBtn = document.getElementById('btn-save-new-pemain');

        if (!form || !modalEl || !saveBtn) return;

        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            syncPhoneInputsIn(form);

            const original = saveBtn.innerHTML;
            setButtonLoading(saveBtn, true, original);

            try {
                const formData = new FormData(form);
                const result = await apiRequestFormData(card.dataset.storeNewUrl, formData);
                showAlert(result.message || 'Pemain baru berhasil didaftarkan.', 'success');

                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.hide();
                form.reset();
                reloadPage();
            } catch (e) {
                showAlert(e.message, 'error');
                setButtonLoading(saveBtn, false, original);
            }
        });
    };

    const initPartnerModal = () => {
        const modalEl = document.getElementById('setPartnerModal');
        const form = document.getElementById('setPartnerForm');
        if (!modalEl || !form) return;

        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;

        const $ = jQuery;

        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const titleEl = document.getElementById('set-partner-modal-title');
        const nameEl = document.getElementById('set-partner-pemain-name');
        const partnerSelect = document.getElementById('partner_peserta_id');
        const saveBtn = document.getElementById('btn-save-partner');
        const $partnerSelect = $(partnerSelect);
        let activePesertaId = null;
        let select2Initialized = false;

        const destroyPartnerSelect2 = () => {
            if ($partnerSelect.hasClass('select2-hidden-accessible')) {
                $partnerSelect.select2('destroy');
                select2Initialized = false;
            }
        };

        const initPartnerSelect2 = () => {
            destroyPartnerSelect2();

            $partnerSelect.select2({
                theme: 'bootstrap-5',
                dropdownParent: $(modalEl),
                placeholder: 'Cari nama atau nomor HP...',
                allowClear: true,
                width: '100%',
            });

            select2Initialized = true;
        };

        const refreshPartnerOptions = (excludePesertaId) => {
            Array.from(partnerSelect.options).forEach((option) => {
                if (!option.value) {
                    option.disabled = false;
                    return;
                }

                option.disabled = parseInt(option.value, 10) === parseInt(excludePesertaId, 10);
            });

            if (!select2Initialized) {
                initPartnerSelect2();
            }

            $partnerSelect.val(null).trigger('change');
        };

        const openPartnerModal = (btn) => {
            activePesertaId = btn.dataset.pesertaId;
            const isChange = btn.dataset.partnerMode === 'change';

            if (titleEl) titleEl.textContent = isChange ? 'Ubah Partner' : 'Set Partner';
            if (nameEl) nameEl.textContent = btn.dataset.pemainName || 'Pemain';

            refreshPartnerOptions(activePesertaId);
            modal.show();
        };

        if (!partnerModalDocumentListenerBound) {
            document.addEventListener('click', (event) => {
                const btn = event.target.closest('.btn-set-partner');
                if (!btn) return;

                event.preventDefault();
                event.stopPropagation();
                openPartnerModal(btn);
            });

            partnerModalDocumentListenerBound = true;
        }

        modalEl.addEventListener('hidden.bs.modal', () => {
            $partnerSelect.val(null).trigger('change');
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!activePesertaId) return;

            const partnerId = partnerSelect?.value;
            if (!partnerId) {
                showToast('Pilih peserta pasangan terlebih dahulu.', 'error');
                return;
            }

            const payload = {
                partner_peserta_id: parseInt(partnerId, 10),
            };

            const original = saveBtn.innerHTML;
            setButtonLoading(saveBtn, true);

            try {
                const partnerUrl = (modalEl.dataset.setPartnerUrl || '/admin/peserta/__PESERTA_ID__/partner')
                    .replace('__PESERTA_ID__', activePesertaId);
                await apiRequest(partnerUrl, 'POST', payload);
                showAlert('Pasangan berhasil diperbarui.', 'success');
                modal.hide();
                reloadPage();
            } catch (e) {
                showAlert(e.message, 'error');
            } finally {
                setButtonLoading(saveBtn, false, original);
            }
        });
    };

    const initPemainActions = () => {
        initBulkApproval();
        initPartnerModal();
        initPemainTableSort();

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
                    title: `Hapus ${name} dari turnamen?`,
                    text: 'Profil pemain tetap tersimpan di database. Pemain hanya dihapus dari pendaftaran turnamen ini.',
                    confirmText: 'Ya, hapus',
                    icon: 'warning',
                    confirmButtonColor: '#dc3545',
                });
                if (!confirmed) return;

                try {
                    const body = btn.dataset.turnamen
                        ? { id_turnamen: parseInt(btn.dataset.turnamen, 10) }
                        : null;
                    await apiRequest(btn.dataset.url, 'DELETE', body);
                    showAlert('Pemain berhasil dihapus dari turnamen.', 'success');
                    reloadPage();
                } catch (e) {
                    showAlert(e.message, 'error');
                }
            });
        });

        const buktiBayarModalEl = document.getElementById('buktiBayarModal');
        if (buktiBayarModalEl) {
            const buktiBayarModal = new bootstrap.Modal(buktiBayarModalEl);
            const imageEl = document.getElementById('bukti-bayar-image');
            const pdfEl = document.getElementById('bukti-bayar-pdf');
            const emptyEl = document.getElementById('bukti-bayar-empty');
            const labelEl = document.getElementById('bukti-bayar-modal-label');
            const openTabEl = document.getElementById('bukti-bayar-open-tab');

            const resetBuktiBayarModal = () => {
                imageEl.classList.add('d-none');
                imageEl.removeAttribute('src');
                pdfEl.classList.add('d-none');
                pdfEl.removeAttribute('src');
                emptyEl.classList.add('d-none');
                openTabEl.classList.add('d-none');
                openTabEl.setAttribute('href', '#');
                labelEl.textContent = '';
            };

            buktiBayarModalEl.addEventListener('hidden.bs.modal', resetBuktiBayarModal);

            document.querySelectorAll('.btn-view-bukti-bayar').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const url = btn.dataset.url || '';
                    const label = btn.dataset.label || 'Pendaftaran';

                    resetBuktiBayarModal();
                    labelEl.textContent = label;

                    if (!url) {
                        emptyEl.classList.remove('d-none');
                        buktiBayarModal.show();
                        return;
                    }

                    openTabEl.href = url;
                    openTabEl.classList.remove('d-none');

                    if (url.toLowerCase().includes('.pdf')) {
                        pdfEl.src = url;
                        pdfEl.classList.remove('d-none');
                    } else {
                        imageEl.src = url;
                        imageEl.classList.remove('d-none');
                    }

                    buktiBayarModal.show();
                });
            });
        }

        initFriendlyRegistrationGroupEdit();
    };

    const initFriendlyRegistrationGroupEdit = () => {
        const panel = document.getElementById('pemain-table-card');
        if (!panel || panel.dataset.regEdit !== '1') {
            return;
        }

        const turnamenId = parseInt(panel.dataset.turnamenId, 10);
        const kategoriId = panel.dataset.kategori ? parseInt(panel.dataset.kategori, 10) : null;
        const assignUrl = panel.dataset.regAssignUrl;
        const removeUrl = panel.dataset.regRemoveUrl;
        const renameTemplate = panel.dataset.regRenameUrlTemplate || '';
        let groups = [];

        try {
            groups = JSON.parse(panel.dataset.regGroups || '[]');
        } catch (e) {
            groups = [];
        }

        const basePayload = () => {
            const payload = { id_turnamen: turnamenId };
            if (kategoriId) {
                payload.id_kategori = kategoriId;
            }
            return payload;
        };

        const moveModalEl = document.getElementById('friendlyRegGroupModal');
        const renameModalEl = document.getElementById('friendlyRegRenameModal');
        if (!moveModalEl || !renameModalEl) {
            return;
        }

        const moveModal = new bootstrap.Modal(moveModalEl);
        const renameModal = new bootstrap.Modal(renameModalEl);
        const moveSelect = document.getElementById('friendly-reg-target-group');
        const moveNameEl = document.getElementById('friendly-reg-player-name');
        const moveHintEl = document.getElementById('friendly-reg-slots-hint');
        const moveSaveBtn = document.getElementById('btn-save-friendly-reg');
        const renameInput = document.getElementById('friendly-reg-rename-input');
        const renameSaveBtn = document.getElementById('btn-save-friendly-reg-rename');

        let activeMovePesertaId = null;
        let activeRenameGroupId = null;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        const openMoveModal = (pesertaId, playerName, fromGroupId) => {
            activeMovePesertaId = parseInt(pesertaId, 10) || 0;
            const fromId = fromGroupId ? parseInt(fromGroupId, 10) : null;
            const targets = groups.filter((g) => g.slots > 0 && g.id !== fromId);

            if (!targets.length) {
                showAlert('Tidak ada grup dengan slot kosong.', 'warning');
                return;
            }

            if (moveNameEl) {
                moveNameEl.textContent = playerName || 'pemain';
            }

            moveSelect.innerHTML = targets.map((g) =>
                `<option value="${g.id}">${escapeHtml(g.nama)} (sisa ${g.slots})</option>`
            ).join('');

            if (moveHintEl) {
                moveHintEl.textContent = fromId
                    ? 'Hanya grup yang belum penuh yang muncul di daftar.'
                    : 'Pemain tanpa grup akan dimasukkan ke grup yang dipilih.';
            }

            moveModal.show();
        };

        panel.querySelectorAll('.friendly-reg-move-open').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                openMoveModal(btn.dataset.pesertaId, btn.dataset.pemainName, btn.dataset.fromGroupId);
            });
        });

        if (moveSaveBtn) {
            moveSaveBtn.addEventListener('click', async () => {
                if (!activeMovePesertaId) {
                    return;
                }

                const groupId = parseInt(moveSelect.value, 10);
                if (!groupId) {
                    showAlert('Pilih grup tujuan.', 'warning');
                    return;
                }

                const original = moveSaveBtn.innerHTML;
                setButtonLoading(moveSaveBtn, true);

                try {
                    await apiRequest(assignUrl, 'POST', {
                        ...basePayload(),
                        id_peserta: activeMovePesertaId,
                        id_grup_pendaftaran: groupId,
                    });
                    moveModal.hide();
                    showAlert('Pemain berhasil dipindah ke grup.', 'success');
                    reloadPage();
                } catch (e) {
                    showAlert(e.message, 'error');
                } finally {
                    setButtonLoading(moveSaveBtn, false, original);
                }
            });
        }

        panel.querySelectorAll('.friendly-reg-remove').forEach((btn) => {
            btn.addEventListener('click', async (event) => {
                event.preventDefault();
                const name = btn.dataset.pemainName || 'pemain ini';
                const confirmed = await confirmAction({
                    title: `Lepas ${name} dari grup?`,
                    text: 'Pemain tetap terdaftar di turnamen sebagai individu (tanpa grup).',
                    confirmText: 'Ya, lepas',
                    icon: 'warning',
                    confirmButtonColor: '#dc3545',
                });
                if (!confirmed) return;

                try {
                    await apiRequest(removeUrl, 'POST', {
                        ...basePayload(),
                        id_peserta: parseInt(btn.dataset.pesertaId, 10),
                    });
                    showAlert('Pemain dilepas dari grup.', 'success');
                    reloadPage();
                } catch (e) {
                    showAlert(e.message, 'error');
                }
            });
        });

        panel.querySelectorAll('.friendly-reg-rename-open').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                activeRenameGroupId = parseInt(btn.dataset.groupId, 10) || 0;
                if (renameInput) {
                    renameInput.value = btn.dataset.groupName || '';
                }
                renameModal.show();
            });
        });

        if (renameSaveBtn) {
            renameSaveBtn.addEventListener('click', async () => {
                if (!activeRenameGroupId) {
                    return;
                }

                const nama = (renameInput?.value || '').trim();
                if (!nama) {
                    showAlert('Nama grup wajib diisi.', 'warning');
                    return;
                }

                const original = renameSaveBtn.innerHTML;
                setButtonLoading(renameSaveBtn, true);

                try {
                    const url = renameTemplate.replace('__ID__', String(activeRenameGroupId));
                    await apiRequest(url, 'PATCH', {
                        ...basePayload(),
                        nama,
                    });
                    renameModal.hide();
                    showAlert('Nama grup berhasil diperbarui.', 'success');
                    reloadPage();
                } catch (e) {
                    showAlert(e.message, 'error');
                } finally {
                    setButtonLoading(renameSaveBtn, false, original);
                }
            });
        }
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
                const randomizesPartners = closeBtn.dataset.randomizePartners === '1';
                const pairsPreview = parseInt(closeBtn.dataset.pairsPreview || '0', 10);
                const confirmed = await confirmAction({
                    title: 'Tutup pendaftaran turnamen ini?',
                    text: randomizesPartners
                        ? `Pemain tidak bisa mendaftar lagi. Sistem akan memasangkan ${pairsPreview} pasangan secara acak dari pemain approved.`
                        : 'Pemain tidak bisa mendaftar lagi.',
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

        const generateMatchesBtn = document.getElementById('btn-generate-group-matches');

        if (generateMatchesBtn) {
            generateMatchesBtn.addEventListener('click', async () => {
                const confirmed = await confirmAction({
                    title: 'Buat matchmaking fase grup?',
                    text: 'Jadwal round-robin akan dibuat dan susunan grup akan dikunci. Setelah ini peserta tidak dapat ditukar atau diacak ulang.',
                    confirmText: 'Ya, buat matchmaking',
                });
                if (!confirmed) return;

                const original = generateMatchesBtn.innerHTML;
                setButtonLoading(generateMatchesBtn, true);

                try {
                    const data = await apiRequest(generateMatchesBtn.dataset.url, 'POST', {
                        id_turnamen: parseInt(generateMatchesBtn.dataset.turnamen, 10),
                    });
                    showToast(data.message);
                    reloadPage();
                } catch (e) {
                    showToast(e.message, 'error');
                    setButtonLoading(generateMatchesBtn, false, original);
                }
            });
        }

        const groupSwapContainer = document.getElementById('matchmaking-groups-accordion');
        const groupSwapModalEl = document.getElementById('groupMemberSwapModal');

        if (groupSwapContainer
            && groupSwapModalEl
            && groupSwapContainer.dataset.groupSwap === '1'
            && typeof bootstrap !== 'undefined') {
            const groupSwapModal = new bootstrap.Modal(groupSwapModalEl);
            const groupSwapList = document.getElementById('group-swap-list');
            const groupSwapSourceLabel = document.getElementById('group-swap-source-label');
            const groupSwapSourceGroup = document.getElementById('group-swap-source-group');
            const swapUrl = groupSwapContainer.dataset.swapUrl;
            const turnamenId = parseInt(groupSwapContainer.dataset.turnamen || '0', 10);
            let swapSource = null;

            const escapeHtml = (value) => {
                const div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            };

            const collectSwapCandidates = (sourceMemberId, sourceGroupId) =>
                Array.from(groupSwapContainer.querySelectorAll('.group-member-swap-source'))
                    .map((el) => ({
                        id: parseInt(el.dataset.memberId || '0', 10),
                        groupId: el.dataset.groupId,
                        groupName: el.dataset.groupName || '',
                        label: el.dataset.label || el.textContent.trim(),
                    }))
                    .filter((candidate) =>
                        candidate.id
                        && candidate.id !== sourceMemberId
                        && String(candidate.groupId) !== String(sourceGroupId)
                    );

            const openSwapModal = () => {
                if (!swapSource) {
                    return;
                }

                groupSwapSourceLabel.textContent = swapSource.label;
                groupSwapSourceGroup.textContent = swapSource.groupName
                    ? ` (${swapSource.groupName})`
                    : '';

                const candidates = collectSwapCandidates(swapSource.id, swapSource.groupId);
                const grouped = {};

                candidates.forEach((candidate) => {
                    const key = candidate.groupName || 'Grup lain';
                    if (!grouped[key]) {
                        grouped[key] = [];
                    }
                    grouped[key].push(candidate);
                });

                const sections = Object.keys(grouped).map((groupName) => {
                    const items = grouped[groupName]
                        .map((candidate) => `
                            <button type="button"
                                    class="list-group-item list-group-item-action d-flex align-items-center group-swap-option"
                                    data-member-id="${candidate.id}">
                                <i class="bi bi-arrow-left-right me-2 text-muted"></i>
                                <span>${escapeHtml(candidate.label)}</span>
                            </button>`)
                        .join('');

                    return `
                        <div class="mb-2">
                            <div class="small text-muted fw-semibold px-3 py-1">${escapeHtml(groupName)}</div>
                            ${items}
                        </div>`;
                });

                groupSwapList.innerHTML = sections.length
                    ? sections.join('')
                    : '<div class="text-muted small p-3 text-center">Tidak ada peserta di grup lain yang dapat ditukar.</div>';

                groupSwapModal.show();
            };

            const startSwapFromEl = (memberEl) => {
                swapSource = {
                    id: parseInt(memberEl.dataset.memberId || '0', 10),
                    groupId: memberEl.dataset.groupId,
                    groupName: memberEl.dataset.groupName || '',
                    label: memberEl.dataset.label || memberEl.textContent.trim(),
                };

                if (!swapSource.id) {
                    return;
                }

                openSwapModal();
            };

            groupSwapContainer.addEventListener('click', (event) => {
                const memberEl = event.target.closest('.group-member-swap-source');
                if (!memberEl || !groupSwapContainer.contains(memberEl)) {
                    return;
                }

                event.preventDefault();
                startSwapFromEl(memberEl);
            });

            groupSwapContainer.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                const memberEl = event.target.closest('.group-member-swap-source');
                if (!memberEl || !groupSwapContainer.contains(memberEl)) {
                    return;
                }

                event.preventDefault();
                startSwapFromEl(memberEl);
            });

            groupSwapList.addEventListener('click', async (event) => {
                const option = event.target.closest('.group-swap-option');
                if (!option || !swapSource) {
                    return;
                }

                const secondId = parseInt(option.dataset.memberId || '0', 10);
                if (!secondId) {
                    return;
                }

                option.disabled = true;

                try {
                    const data = await apiRequest(swapUrl, 'PATCH', {
                        id_turnamen: turnamenId,
                        first_member_id: swapSource.id,
                        second_member_id: secondId,
                    });
                    groupSwapModal.hide();
                    showToast(data.message);
                    reloadPage();
                } catch (e) {
                    option.disabled = false;
                    showToast(e.message, 'error');
                }
            });
        }

        const resetGroupsBtn = document.getElementById('btn-reset-groups');

        if (resetGroupsBtn) {
            resetGroupsBtn.addEventListener('click', async () => {
                const isMahjong = resetGroupsBtn.dataset.mahjong === '1';
                const confirmed = await confirmAction({
                    title: 'Reset grup dan matchmaking?',
                    text: isMahjong
                        ? 'Semua grup, babak, ronde, dan seluruh riwayat poin Mahjong akan dihapus. Pendaftaran pemain tetap disimpan.'
                        : 'Semua grup dan pertandingan terjadwal akan dihapus. Pendaftaran dan pasangan tetap disimpan.',
                    confirmText: 'Ya, reset data',
                });
                if (!confirmed) return;

                const original = resetGroupsBtn.innerHTML;
                setButtonLoading(resetGroupsBtn, true);

                try {
                    const data = await apiRequest(resetGroupsBtn.dataset.url, 'DELETE', {
                        id_turnamen: parseInt(resetGroupsBtn.dataset.turnamen, 10),
                    });
                    showToast(data.message);
                    reloadPage();
                } catch (e) {
                    showToast(e.message, 'error');
                    setButtonLoading(resetGroupsBtn, false, original);
                }
            });
        }

        const resetBracketBtn = document.getElementById('btn-reset-bracket');

        if (resetBracketBtn) {
            resetBracketBtn.addEventListener('click', async () => {
                const hasScores = resetBracketBtn.dataset.hasScores === '1';
                let password = null;

                if (hasScores) {
                    if (!window.Swal) {
                        showToast('Skor knockout sudah ada. Konfirmasi password membutuhkan dialog yang tersedia.', 'error');
                        return;
                    }

                    const result = await window.Swal.fire({
                        title: 'Reset bracket dengan skor?',
                        html: 'Skor knockout akan dihapus dan poin kemenangan knockout dibatalkan. Klasemen grup tetap disimpan.<br><br>Masukkan password akun Anda untuk melanjutkan.',
                        icon: 'warning',
                        input: 'password',
                        inputPlaceholder: 'Password akun',
                        inputAttributes: {
                            autocapitalize: 'off',
                            autocomplete: 'current-password',
                        },
                        showCancelButton: true,
                        confirmButtonText: 'Ya, reset bracket',
                        cancelButtonText: 'Batal',
                        reverseButtons: true,
                        confirmButtonColor: '#cda858',
                        preConfirm: (value) => {
                            if (!value || !String(value).trim()) {
                                window.Swal.showValidationMessage('Password wajib diisi.');
                                return false;
                            }

                            return String(value).trim();
                        },
                    });

                    if (!result.isConfirmed) return;
                    password = result.value;
                } else {
                    const confirmed = await confirmAction({
                        title: 'Reset bracket knockout?',
                        text: 'Semua pertandingan knockout (termasuk Final & Juara 3) akan dihapus. Klasemen grup tetap disimpan, lalu Anda bisa membuat bracket lagi.',
                        confirmText: 'Ya, reset bracket',
                    });
                    if (!confirmed) return;
                }

                const original = resetBracketBtn.innerHTML;
                setButtonLoading(resetBracketBtn, true);

                try {
                    const payload = {
                        id_turnamen: parseInt(resetBracketBtn.dataset.turnamen, 10),
                    };

                    if (password) {
                        payload.password = password;
                    }

                    const data = await apiRequest(resetBracketBtn.dataset.url, 'DELETE', payload);
                    showToast(data.message);
                    reloadPage();
                } catch (e) {
                    showToast(e.message, 'error');
                    setButtonLoading(resetBracketBtn, false, original);
                }
            });
        }

        const endGroupBtn = document.getElementById('btn-end-group-stage');

        if (endGroupBtn && !endGroupBtn.disabled) {
            const modalEl = document.getElementById('endGroupStageModal');
            const confirmBtn = document.getElementById('btn-confirm-end-group-stage');
            const jumlahInput = document.getElementById('jumlah-lolos-input');
            const jumlahTotalInput = document.getElementById('jumlah-lolos-total-input');
            const perGroupWrap = document.getElementById('jumlah-lolos-per-group-wrap');
            const totalWrap = document.getElementById('jumlah-lolos-total-wrap');
            const totalPreview = document.getElementById('jumlah-lolos-total-preview');
            const modeInputs = modalEl
                ? modalEl.querySelectorAll('input[name="qualification_mode"]')
                : [];
            const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
            const isMahjong = endGroupBtn.dataset.mahjong === '1';

            const selectedMode = () => {
                const checked = modalEl?.querySelector('input[name="qualification_mode"]:checked');
                return checked ? checked.value : 'per_group';
            };

            const updateTotalPreview = () => {
                if (!jumlahTotalInput || !totalPreview) return;

                const total = parseInt(jumlahTotalInput.value || '0', 10);
                const groups = parseInt(jumlahTotalInput.dataset.groupCount || endGroupBtn.dataset.groupCount || '0', 10);
                const participants = parseInt(
                    jumlahTotalInput.dataset.participantCount || endGroupBtn.dataset.participantCount || '0',
                    10
                );

                if (!total || !groups) {
                    totalPreview.textContent = '';
                    return;
                }

                if (total < groups) {
                    totalPreview.textContent = `Minimal ${groups} (juara 1 tiap grup).`;
                    return;
                }

                if (participants && total > participants) {
                    totalPreview.textContent = `Maksimal ${participants} peserta di fase grup.`;
                    return;
                }

                const base = Math.floor(total / groups);
                const automatic = base * groups;
                const extra = total - automatic;

                totalPreview.textContent = extra > 0
                    ? `${groups} grup × top ${base} = ${automatic}, lalu ${extra} terbaik dari sisa = ${total} lolos.`
                    : `${groups} grup × top ${base} = ${total} lolos.`;
            };

            const syncQualificationModeUi = () => {
                if (isMahjong) return;

                const mode = selectedMode();
                const isTotal = mode === 'total';

                if (perGroupWrap) perGroupWrap.classList.toggle('d-none', isTotal);
                if (totalWrap) totalWrap.classList.toggle('d-none', !isTotal);
                if (jumlahInput) jumlahInput.required = !isTotal;
                if (jumlahTotalInput) jumlahTotalInput.required = isTotal;
                if (isTotal) updateTotalPreview();
            };

            modeInputs.forEach((input) => {
                input.addEventListener('change', syncQualificationModeUi);
            });

            if (jumlahTotalInput) {
                jumlahTotalInput.addEventListener('input', updateTotalPreview);
            }

            endGroupBtn.addEventListener('click', () => {
                syncQualificationModeUi();
                if (modal) {
                    modal.show();
                }
            });

            if (confirmBtn) {
                confirmBtn.addEventListener('click', async () => {
                    const mode = isMahjong ? 'per_group' : selectedMode();
                    const parsed = parseInt(
                        (mode === 'total' ? jumlahTotalInput?.value : jumlahInput?.value) || '0',
                        10
                    );
                    const minLolos = isMahjong
                        ? 4
                        : (mode === 'total'
                            ? Math.max(2, parseInt(endGroupBtn.dataset.groupCount || '2', 10))
                            : 1);

                    if (!parsed || parsed < minLolos) {
                        showToast(`Jumlah lolos harus angka minimal ${minLolos}.`, 'error');
                        return;
                    }

                    if (isMahjong && parsed > 4 && parsed % 4 !== 0) {
                        showToast('Jumlah pemain lolos harus kelipatan 4.', 'error');
                        return;
                    }

                    const original = confirmBtn.innerHTML;
                    setButtonLoading(confirmBtn, true);

                    try {
                        const payload = {
                            tournament_id: parseInt(endGroupBtn.dataset.turnamen, 10),
                            id_turnamen: parseInt(endGroupBtn.dataset.turnamen, 10),
                            jumlah_lolos: parsed,
                        };

                        if (!isMahjong) {
                            payload.qualification_mode = mode;
                        }

                        const data = await apiRequest(endGroupBtn.dataset.url, 'POST', payload);

                        if (isMahjong && data.needs_tiebreak) {
                            modal?.hide();
                            setButtonLoading(confirmBtn, false, original);
                            openMahjongAdvanceTiebreakModal(parsed, data.data || {});
                            return;
                        }

                        modal?.hide();
                        showToast(data.message);
                        if (isMahjong) {
                            reloadPage();
                        } else {
                            goTo(endGroupBtn.dataset.bracketUrl || data.redirect_url);
                        }
                    } catch (e) {
                        showToast(e.message, 'error');
                        setButtonLoading(confirmBtn, false, original);
                    }
                });
            }

            const tiebreakModalEl = document.getElementById('mahjongAdvanceTiebreakModal');
            const tiebreakList = document.getElementById('mahjong-tiebreak-list');
            const tiebreakHelp = document.getElementById('mahjong-tiebreak-help');
            const tiebreakAuto = document.getElementById('mahjong-tiebreak-auto');
            const tiebreakConfirmBtn = document.getElementById('btn-confirm-mahjong-tiebreak');
            const tiebreakModal = (isMahjong && tiebreakModalEl && typeof bootstrap !== 'undefined')
                ? new bootstrap.Modal(tiebreakModalEl)
                : null;
            let pendingMahjongJumlahLolos = null;

            const escapeHtml = (value) => {
                const div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            };

            const openMahjongAdvanceTiebreakModal = (jumlahLolos, payload) => {
                if (!tiebreakModal || !tiebreakList) {
                    showToast('Modal pemilihan seri tidak tersedia.', 'error');
                    return;
                }

                pendingMahjongJumlahLolos = jumlahLolos;
                const slots = parseInt(payload.slots_remaining || '0', 10);
                const contested = Array.isArray(payload.contested) ? payload.contested : [];
                const autoQualified = Array.isArray(payload.auto_qualified) ? payload.auto_qualified : [];

                if (tiebreakHelp) {
                    tiebreakHelp.textContent = `Ada ${contested.length} pemain dengan total poin, menang, dan akumulasi sama. Pilih ${slots} pemain yang lolos.`;
                }

                if (tiebreakAuto) {
                    if (autoQualified.length) {
                        tiebreakAuto.classList.remove('d-none');
                        tiebreakAuto.innerHTML = `
                            <div class="small text-muted mb-1">Sudah lolos otomatis (${autoQualified.length}):</div>
                            <div class="small">${autoQualified.map((row) => escapeHtml(row.nama)).join(', ')}</div>
                        `;
                    } else {
                        tiebreakAuto.classList.add('d-none');
                        tiebreakAuto.innerHTML = '';
                    }
                }

                tiebreakList.innerHTML = contested.map((row) => `
                    <label class="list-group-item list-group-item-action d-flex align-items-start gap-2">
                        <input class="form-check-input mt-1 mahjong-tiebreak-pick"
                               type="checkbox"
                               value="${parseInt(row.id_peserta || '0', 10)}">
                        <span class="flex-grow-1">
                            <span class="fw-semibold d-block">${escapeHtml(row.nama)}</span>
                            <span class="small text-muted">
                                ${row.grup_nama ? escapeHtml(row.grup_nama) + ' · ' : ''}
                                Total ${parseInt(row.total_babak || '0', 10)}
                                · W ${parseInt(row.menang || '0', 10)}
                                · Akumulasi ${parseInt(row.poin_akumulasi || '0', 10)}
                            </span>
                        </span>
                    </label>
                `).join('');

                tiebreakList.dataset.slotsRemaining = String(slots);
                tiebreakModal.show();
            };

            if (tiebreakConfirmBtn) {
                tiebreakConfirmBtn.addEventListener('click', async () => {
                    const slots = parseInt(tiebreakList?.dataset.slotsRemaining || '0', 10);
                    const picks = Array.from(document.querySelectorAll('.mahjong-tiebreak-pick:checked'))
                        .map((el) => parseInt(el.value || '0', 10))
                        .filter((id) => id > 0);

                    if (!pendingMahjongJumlahLolos || !slots) {
                        showToast('Data seri tidak valid. Coba ulangi lanjut babak.', 'error');
                        return;
                    }

                    if (picks.length !== slots) {
                        showToast(`Pilih tepat ${slots} pemain.`, 'error');
                        return;
                    }

                    const original = tiebreakConfirmBtn.innerHTML;
                    setButtonLoading(tiebreakConfirmBtn, true);

                    try {
                        const data = await apiRequest(endGroupBtn.dataset.url, 'POST', {
                            tournament_id: parseInt(endGroupBtn.dataset.turnamen, 10),
                            id_turnamen: parseInt(endGroupBtn.dataset.turnamen, 10),
                            jumlah_lolos: pendingMahjongJumlahLolos,
                            tiebreak_peserta_ids: picks,
                        });

                        if (data.needs_tiebreak) {
                            showToast(data.message || 'Masih ada seri yang harus dipilih.', 'error');
                            setButtonLoading(tiebreakConfirmBtn, false, original);
                            openMahjongAdvanceTiebreakModal(pendingMahjongJumlahLolos, data.data || {});
                            return;
                        }

                        tiebreakModal?.hide();
                        showToast(data.message);
                        reloadPage();
                    } catch (e) {
                        showToast(e.message, 'error');
                        setButtonLoading(tiebreakConfirmBtn, false, original);
                    }
                });
            }
        }

        const reshuffleBtn = document.getElementById('btn-reshuffle-groups');

        if (reshuffleBtn) {
            reshuffleBtn.addEventListener('click', async () => {
                const confirmed = await confirmAction({
                    title: 'Acak ulang grup?',
                    text: 'Pemain akan dibagi ulang ke grup baru (4 per grup). Total poin babak saat ini dijumlahkan ke akumulasi.',
                    confirmText: 'Ya, reshuffle',
                });
                if (!confirmed) return;

                const original = reshuffleBtn.innerHTML;
                setButtonLoading(reshuffleBtn, true);

                try {
                    const data = await apiRequest(reshuffleBtn.dataset.url, 'POST', {
                        id_turnamen: parseInt(reshuffleBtn.dataset.turnamen, 10),
                        mode: 'random',
                    });
                    showToast(data.message);
                    reloadPage();
                } catch (e) {
                    showToast(e.message, 'error');
                    setButtonLoading(reshuffleBtn, false, original);
                }
            });
        }

        const mahjongExternalScoringToggle = document.getElementById('mahjong-external-scoring-toggle');

        if (mahjongExternalScoringToggle) {
            mahjongExternalScoringToggle.addEventListener('change', async () => {
                const enabled = mahjongExternalScoringToggle.checked;
                mahjongExternalScoringToggle.disabled = true;

                try {
                    const data = await apiRequest(mahjongExternalScoringToggle.dataset.url, 'PATCH', {
                        id_turnamen: parseInt(mahjongExternalScoringToggle.dataset.turnamen, 10),
                        enabled,
                    });
                    showToast(data.message);
                } catch (e) {
                    mahjongExternalScoringToggle.checked = !enabled;
                    showToast(e.message, 'error');
                } finally {
                    mahjongExternalScoringToggle.disabled = false;
                }
            });
        }

        function formatMahjongEntryLabel(poin) {
            const value = parseInt(poin, 10) || 0;
            return (value > 0 ? '+' : '') + value;
        }

        function renderMahjongMemberPoints(memberId, data) {
            if (!data) return;

            const babakBadge = document.querySelector(`.mahjong-poin-babak[data-member-id="${memberId}"]`);
            const totalBadge = document.querySelector(`.mahjong-total-poin[data-member-id="${memberId}"]`);
            const menangBadge = document.querySelector(`.mahjong-menang[data-member-id="${memberId}"]`);
            const entriesWrap = document.querySelector(`.mahjong-poin-entries[data-member-id="${memberId}"]`);

            if (babakBadge) {
                babakBadge.textContent = data.poin_didapat;
            }
            if (totalBadge) {
                totalBadge.textContent = data.total_poin;
            }
            if (menangBadge) {
                menangBadge.textContent = data.menang ?? 0;
            }
            if (entriesWrap) {
                const entries = Array.isArray(data.entries) ? data.entries : [];
                const destroyTemplate = entriesWrap.dataset.destroyUrlTemplate || '';
                entriesWrap.innerHTML = entries.map((entry) => {
                    const deleteUrl = destroyTemplate.replace('__ENTRY__', entry.id);
                    const winnerClass = entry.is_winner ? ' border-warning' : '';
                    const winnerIcon = entry.is_winner
                        ? '<i class="bi bi-trophy-fill text-warning me-1" title="Pemenang ronde"></i>'
                        : '';
                    return `
                        <span class="badge text-bg-light text-dark border mahjong-poin-entry${winnerClass}" data-entry-id="${entry.id}">
                            ${winnerIcon}${formatMahjongEntryLabel(entry.poin)}
                            <button type="button"
                                    class="btn btn-link btn-sm p-0 ms-1 text-danger btn-delete-mahjong-poin"
                                    data-member-id="${memberId}"
                                    data-entry-id="${entry.id}"
                                    data-url="${deleteUrl}"
                                    title="Hapus entri">
                                <i class="bi bi-x"></i>
                            </button>
                        </span>
                    `;
                }).join('');
            }
        }

        const mahjongGroupPointsModalEl = document.getElementById('mahjongGroupPointsModal');
        const mahjongGroupPointsFields = document.getElementById('mahjong-group-points-fields');
        const mahjongGroupPointsHelp = document.getElementById('mahjong-group-points-help');
        const mahjongGroupPointsTitle = document.getElementById('mahjongGroupPointsModalLabel');
        const saveMahjongGroupPointsBtn = document.getElementById('btn-save-mahjong-group-points');
        let mahjongGroupPointsModal = null;
        let activeMahjongGroupPointsUrl = null;
        let activeMahjongWinnerMemberId = null;

        function setMahjongWinnerSelection(memberId) {
            activeMahjongWinnerMemberId = memberId ? parseInt(memberId, 10) : null;
            if (!mahjongGroupPointsFields) {
                return;
            }

            mahjongGroupPointsFields.querySelectorAll('.mahjong-winner-pick').forEach((btn) => {
                const isSelected = parseInt(btn.dataset.memberId, 10) === activeMahjongWinnerMemberId;
                btn.classList.toggle('btn-warning', isSelected);
                btn.classList.toggle('btn-outline-secondary', !isSelected);
                btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                const icon = btn.querySelector('.mahjong-winner-icon');
                if (icon) {
                    icon.className = isSelected
                        ? 'bi bi-trophy-fill text-dark mahjong-winner-icon me-1'
                        : 'bi bi-trophy mahjong-winner-icon me-1';
                }
            });
        }

        if (mahjongGroupPointsModalEl && typeof bootstrap !== 'undefined') {
            mahjongGroupPointsModal = bootstrap.Modal.getOrCreateInstance(mahjongGroupPointsModalEl);
        }

        function openMahjongGroupPointsModal(btn) {
            if (!mahjongGroupPointsModal || !mahjongGroupPointsFields) {
                return;
            }

            let members = [];
            try {
                members = JSON.parse(btn.dataset.members || '[]');
            } catch (e) {
                members = [];
            }

            if (!Array.isArray(members) || members.length === 0) {
                showToast('Anggota grup tidak ditemukan.', 'error');
                return;
            }

            const escapeHtml = (value) => {
                const div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            };

            activeMahjongGroupPointsUrl = btn.dataset.url || null;
            activeMahjongWinnerMemberId = null;
            const groupName = btn.dataset.grupName || 'Grup';

            if (mahjongGroupPointsTitle) {
                mahjongGroupPointsTitle.innerHTML = `<i class="bi bi-pencil-square me-1"></i> Input Poin — ${escapeHtml(groupName)}`;
            }
            if (mahjongGroupPointsHelp) {
                mahjongGroupPointsHelp.textContent = `Opsional: klik nama pemain untuk menandai pemenang ronde, lalu isi poin keempat pemain di ${groupName} dan simpan.`;
            }

            mahjongGroupPointsFields.innerHTML = members.map((member) => `
                <div class="row g-2 align-items-center">
                    <div class="col-7">
                        <button type="button"
                                class="btn btn-outline-secondary btn-sm w-100 text-start mahjong-winner-pick"
                                data-member-id="${member.id}"
                                aria-pressed="false"
                                title="Tandai sebagai pemenang ronde">
                            <i class="bi bi-trophy mahjong-winner-icon me-1"></i>
                            <span class="fw-semibold">${escapeHtml(member.name || 'Pemain')}</span>
                        </button>
                    </div>
                    <div class="col-5">
                        <input type="number"
                               class="form-control text-center mahjong-group-poin-input"
                               id="mahjong-group-poin-${member.id}"
                               data-member-id="${member.id}"
                               placeholder="0"
                               required>
                    </div>
                </div>
            `).join('');

            mahjongGroupPointsModal.show();

            const firstInput = mahjongGroupPointsFields.querySelector('.mahjong-group-poin-input');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 150);
            }
        }

        document.querySelectorAll('.btn-mahjong-input-poin').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                openMahjongGroupPointsModal(btn);
            });
        });

        if (mahjongGroupPointsFields) {
            mahjongGroupPointsFields.addEventListener('click', (event) => {
                const pickBtn = event.target.closest('.mahjong-winner-pick');
                if (!pickBtn || !mahjongGroupPointsFields.contains(pickBtn)) {
                    return;
                }
                event.preventDefault();
                setMahjongWinnerSelection(pickBtn.dataset.memberId);
            });
        }

        if (saveMahjongGroupPointsBtn) {
            saveMahjongGroupPointsBtn.addEventListener('click', async () => {
                if (!activeMahjongGroupPointsUrl || !mahjongGroupPointsFields) {
                    return;
                }

                const inputs = Array.from(mahjongGroupPointsFields.querySelectorAll('.mahjong-group-poin-input'));
                const scores = [];

                for (const input of inputs) {
                    if (input.value === '' || input.value === null) {
                        showToast('Isi poin untuk semua pemain.', 'error');
                        input.focus();
                        return;
                    }

                    const poin = parseInt(input.value, 10);
                    if (Number.isNaN(poin)) {
                        showToast('Poin harus berupa angka.', 'error');
                        input.focus();
                        return;
                    }

                    scores.push({
                        id: parseInt(input.dataset.memberId, 10),
                        poin,
                    });
                }

                const original = saveMahjongGroupPointsBtn.innerHTML;
                setButtonLoading(saveMahjongGroupPointsBtn, true);

                try {
                    const payload = { scores };
                    if (activeMahjongWinnerMemberId) {
                        payload.id_grup_member_pemenang = activeMahjongWinnerMemberId;
                    }

                    const data = await apiRequest(activeMahjongGroupPointsUrl, 'POST', payload);
                    const members = data?.data?.members || [];
                    members.forEach((member) => {
                        renderMahjongMemberPoints(member.id, member);
                    });
                    mahjongGroupPointsModal?.hide();
                    activeMahjongWinnerMemberId = null;
                    showToast(data.message);
                } catch (e) {
                    showToast(e.message, 'error');
                } finally {
                    setButtonLoading(saveMahjongGroupPointsBtn, false, original);
                }
            });
        }

        if (mahjongGroupPointsFields) {
            mahjongGroupPointsFields.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                event.preventDefault();
                saveMahjongGroupPointsBtn?.click();
            });
        }

        document.addEventListener('click', async (event) => {
            const btn = event.target.closest('.btn-delete-mahjong-poin');
            if (!btn) {
                return;
            }

            event.preventDefault();
            const memberId = btn.dataset.memberId;
            const url = btn.dataset.url;
            if (!url) {
                return;
            }

            try {
                const data = await apiRequest(url, 'DELETE');
                renderMahjongMemberPoints(memberId, data.data);
                showToast(data.message);
            } catch (e) {
                showToast(e.message, 'error');
            }
        });
        const initFriendlyMatchActions = () => {
            const panel = document.getElementById('friendly-matches-panel');
            if (!panel) return;

            let groups = [];
            try {
                groups = JSON.parse(panel.dataset.groups || '[]');
            } catch (e) {
                groups = [];
            }

            const modalEl = document.getElementById('friendlyMatchModal');
            const titleEl = document.getElementById('friendlyMatchModalLabel');
            const helpEl = document.getElementById('friendly-match-help');
            const grup1Select = document.getElementById('friendly-grup1');
            const grup2Select = document.getElementById('friendly-grup2');
            const side1Box = document.getElementById('friendly-side1-players');
            const side2Box = document.getElementById('friendly-side2-players');
            const saveBtn = document.getElementById('btn-save-friendly-match');

            let mode = 'create';
            let assignMatchId = null;
            let prefillSide1 = [];
            let prefillSide2 = [];

            const fillGroupSelects = () => {
                if (!grup1Select || !grup2Select) return;
                const options = groups.map((g) => `<option value="${g.id}">${g.nama}</option>`).join('');
                grup1Select.innerHTML = options;
                grup2Select.innerHTML = options;
                if (groups.length > 1) {
                    grup2Select.value = String(groups[1].id);
                }
            };

            const renderPlayerChecks = (box, groupId, name, selectedIds = []) => {
                if (!box) return;
                const group = groups.find((g) => String(g.id) === String(groupId));
                if (!group) {
                    box.innerHTML = '<div class="text-muted small">Pilih grup terlebih dahulu.</div>';
                    return;
                }

                const selected = new Set((selectedIds || []).map((id) => String(id)));

                box.innerHTML = group.members.map((m) => `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="${name}" value="${m.id}" id="${name}-${m.id}"
                            ${selected.has(String(m.id)) ? 'checked' : ''}>
                        <label class="form-check-label" for="${name}-${m.id}">${m.nama}</label>
                    </div>
                `).join('');
            };

            const selectedIds = (box) => Array.from(box?.querySelectorAll('input:checked') || [])
                .map((el) => parseInt(el.value, 10));

            const setModeCreate = () => {
                mode = 'create';
                assignMatchId = null;
                prefillSide1 = [];
                prefillSide2 = [];
                if (titleEl) titleEl.textContent = 'Tambah Pertandingan Group Match';
                if (helpEl) {
                    helpEl.textContent = 'Pilih 2 grup yang bertanding, lalu pilih 2 pemain dari masing-masing grup. Pemain boleh bermain berulang; anggota grup boleh tidak ikut tanding.';
                }
                if (grup1Select) grup1Select.disabled = false;
                if (grup2Select) grup2Select.disabled = false;
                fillGroupSelects();
                renderPlayerChecks(side1Box, grup1Select?.value, 'side1');
                renderPlayerChecks(side2Box, grup2Select?.value, 'side2');
                if (saveBtn) {
                    saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Simpan Tanding';
                }
            };

            const setModeAssign = ({ matchId, grup1, grup2, side1, side2 }) => {
                mode = 'assign';
                assignMatchId = matchId;
                prefillSide1 = side1 || [];
                prefillSide2 = side2 || [];
                if (titleEl) titleEl.textContent = 'Isi Pasangan Group Match';
                if (helpEl) {
                    helpEl.textContent = 'Pilih 2 pemain dari masing-masing grup untuk slot ini. Pasangan bisa diubah selama skor belum diisi.';
                }
                fillGroupSelects();
                if (grup1Select) {
                    grup1Select.value = String(grup1);
                    grup1Select.disabled = true;
                }
                if (grup2Select) {
                    grup2Select.value = String(grup2);
                    grup2Select.disabled = true;
                }
                renderPlayerChecks(side1Box, grup1, 'side1', prefillSide1);
                renderPlayerChecks(side2Box, grup2, 'side2', prefillSide2);
                if (saveBtn) {
                    saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Simpan Pasangan';
                }
            };

            fillGroupSelects();
            renderPlayerChecks(side1Box, grup1Select?.value, 'side1');
            renderPlayerChecks(side2Box, grup2Select?.value, 'side2');

            grup1Select?.addEventListener('change', () => {
                if (mode === 'assign') return;
                renderPlayerChecks(side1Box, grup1Select.value, 'side1');
            });
            grup2Select?.addEventListener('change', () => {
                if (mode === 'assign') return;
                renderPlayerChecks(side2Box, grup2Select.value, 'side2');
            });

            modalEl?.addEventListener('show.bs.modal', (event) => {
                const trigger = event.relatedTarget;
                if (!trigger) {
                    setModeCreate();
                    return;
                }

                const triggerMode = trigger.dataset.mode || 'create';
                if (triggerMode === 'assign') {
                    let side1 = [];
                    let side2 = [];
                    try {
                        side1 = JSON.parse(trigger.dataset.side1 || '[]');
                        side2 = JSON.parse(trigger.dataset.side2 || '[]');
                    } catch (e) {
                        side1 = [];
                        side2 = [];
                    }

                    setModeAssign({
                        matchId: parseInt(trigger.dataset.matchId, 10),
                        grup1: parseInt(trigger.dataset.grup1, 10),
                        grup2: parseInt(trigger.dataset.grup2, 10),
                        side1,
                        side2,
                    });
                } else {
                    setModeCreate();
                }
            });

            saveBtn?.addEventListener('click', async () => {
                const side1 = selectedIds(side1Box);
                const side2 = selectedIds(side2Box);

                if (String(grup1Select.value) === String(grup2Select.value)) {
                    showAlert('Pilih dua grup yang berbeda.', 'warning');
                    return;
                }

                if (side1.length !== 2 || side2.length !== 2) {
                    showAlert('Pilih tepat 2 pemain di setiap sisi.', 'warning');
                    return;
                }

                const original = saveBtn.innerHTML;
                setButtonLoading(saveBtn, true);

                try {
                    if (mode === 'assign' && assignMatchId) {
                        const url = (panel.dataset.assignUrlTemplate || '').replace('__ID__', String(assignMatchId));
                        await apiRequest(url, 'POST', {
                            tournament_id: parseInt(panel.dataset.turnamen, 10),
                            side1_pemain_ids: side1,
                            side2_pemain_ids: side2,
                        });
                        showAlert('Pasangan berhasil disimpan.', 'success');
                    } else {
                        await apiRequest(panel.dataset.createUrl, 'POST', {
                            tournament_id: parseInt(panel.dataset.turnamen, 10),
                            id_grup1: parseInt(grup1Select.value, 10),
                            id_grup2: parseInt(grup2Select.value, 10),
                            side1_pemain_ids: side1,
                            side2_pemain_ids: side2,
                        });
                        showAlert('Pertandingan Group Match berhasil ditambahkan.', 'success');
                    }
                    reloadPage();
                } catch (e) {
                    showAlert(e.message, 'error');
                    setButtonLoading(saveBtn, false, original);
                }
            });

            document.querySelectorAll('.btn-delete-friendly-match').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const confirmed = await confirmAction({
                        title: 'Hapus pertandingan ini?',
                        confirmText: 'Ya, hapus',
                    });
                    if (!confirmed) return;

                    const original = btn.innerHTML;
                    setButtonLoading(btn, true);

                    try {
                        await apiRequest(btn.dataset.url, 'DELETE', {
                            tournament_id: parseInt(btn.dataset.turnamen, 10),
                        });
                        showAlert('Pertandingan dihapus.', 'success');
                        reloadPage();
                    } catch (e) {
                        showAlert(e.message, 'error');
                        setButtonLoading(btn, false, original);
                    }
                });
            });
        };

        initFriendlyMatchActions();

        const completeTournamentBtn = document.getElementById('btn-complete-tournament');

        if (completeTournamentBtn) {
            completeTournamentBtn.addEventListener('click', async () => {
                const isFriendly = document.getElementById('friendly-matches-panel') !== null;
                const pendingThirdPlace = completeTournamentBtn.dataset.pendingThirdPlace === '1';
                const confirmed = await confirmAction({
                    title: 'Selesaikan turnamen?',
                    text: isFriendly
                        ? 'Klasemen Group Match akan dikunci. Total poin pemain tidak berubah.'
                        : (pendingThirdPlace
                            ? 'Juara 3 belum dimainkan. Lanjut tanpa perebutan juara 3? Pertandingan itu akan dibatalkan, lalu poin bonus juara akan ditambahkan.'
                            : 'Poin bonus juara 1, 2, dan 3 akan ditambahkan ke total poin pemain.'),
                    confirmText: 'Ya, selesaikan',
                });
                if (!confirmed) return;

                const original = completeTournamentBtn.innerHTML;
                setButtonLoading(completeTournamentBtn, true);

                try {
                    const data = await apiRequest(completeTournamentBtn.dataset.url, 'POST', {
                        tournament_id: parseInt(completeTournamentBtn.dataset.turnamen, 10),
                        id_turnamen: parseInt(completeTournamentBtn.dataset.turnamen, 10),
                    });
                    showToast(data.message);
                    reloadPage();
                } catch (e) {
                    showToast(e.message, 'error');
                    setButtonLoading(completeTournamentBtn, false, original);
                }
            });
        }

        document.querySelectorAll('.btn-matchmaking-grup').forEach((btn) => {
            if (btn.disabled) {
                return;
            }

            btn.addEventListener('click', async () => {
                const mode = btn.dataset.mode || 'random';
                const isMahjong = btn.dataset.mahjong === '1';
                const isFriendly = btn.dataset.friendly === '1';
                const total = parseInt(previewEl?.dataset.approved || '0', 10);
                const playersPerGroup = parseInt(
                    previewEl?.dataset.playersPerGroup || (isFriendly ? '4' : '4'),
                    10
                ) || 4;
                const minApproved = isFriendly ? playersPerGroup * 2 : 4;

                let previewText;
                if (isMahjong || isFriendly) {
                    if (total < minApproved || total % playersPerGroup !== 0) {
                        showAlert(
                            isFriendly
                                ? `Jumlah pemain approved harus minimal ${minApproved} dan kelipatan ${playersPerGroup}.`
                                : 'Jumlah pemain approved harus minimal 4 dan kelipatan 4.',
                            'error'
                        );
                        return;
                    }
                    previewText = `${total} pemain → ${total / playersPerGroup} grup (${playersPerGroup} pemain per grup)`;
                } else {
                    const groupSettings = getGroupSettings();
                    const sizes = calculateGroupSizes(
                        total,
                        groupSettings.min_pemain_grup,
                        groupSettings.max_pemain_grup
                    );

                    if (!sizes) {
                        showAlert('Pemain tidak cukup atau batas min/max grup tidak valid.', 'error');
                        return;
                    }

                    previewText = `${total} pemain → ${sizes.length} grup (${sizes.join(' + ')})`;
                }

                const confirmed = await confirmAction(mode === 'by_rating'
                    ? {
                        title: isFriendly
                            ? 'Isi grup Group Match berdasarkan rating?'
                            : (isMahjong ? 'Buat grup berdasarkan rating?' : 'Kelompokkan pemain berdasarkan rating?'),
                        text: isMahjong
                            ? `${previewText}. Tidak ada pertandingan head-to-head.`
                            : (isFriendly
                                ? `${previewText}. Hanya pemain belum digrup yang diacak (jika kerangka sudah ada).`
                                : `${previewText}. Grup dapat diubah kembali sebelum matchmaking dibuat.`),
                        confirmText: isFriendly || isMahjong ? 'Ya, lanjutkan' : 'Ya, buat grup rating',
                    }
                    : {
                        title: isFriendly
                            ? 'Acak pemain Group Match ke grup?'
                            : (isMahjong ? 'Buat grup Mahjong?' : 'Acak pemain ke grup?'),
                        text: isMahjong
                            ? `${previewText}. Tidak ada pertandingan head-to-head.`
                            : (isFriendly
                                ? `${previewText}. Hanya pemain belum digrup yang diacak (jika kerangka sudah ada).`
                                : `${previewText}. Grup dapat diubah kembali sebelum matchmaking dibuat.`),
                        confirmText: isFriendly || isMahjong ? 'Ya, lanjutkan' : 'Ya, random grup',
                    });
                if (!confirmed) return;

                const original = btn.innerHTML;
                setButtonLoading(btn, true);

                try {
                    const payload = {
                        id_turnamen: parseInt(btn.dataset.turnamen, 10),
                        mode,
                    };

                    if (!isMahjong && !isFriendly) {
                        Object.assign(payload, getGroupSettings());
                    }

                    const data = await apiRequest(btn.dataset.url, 'POST', payload);
                    showToast(data.message);
                    reloadPage();
                } catch (e) {
                    showToast(e.message, 'error');
                    setButtonLoading(btn, false, original);
                }
            });
        });

        document.querySelectorAll('.btn-friendly-skeleton').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const confirmed = await confirmAction({
                    title: 'Buat kerangka grup kosong?',
                    text: 'Grup A, B, C, … akan dibuat tanpa anggota. Anda bisa menyusun manual lalu acak sisa.',
                    confirmText: 'Ya, buat kerangka',
                });
                if (!confirmed) return;

                const original = btn.innerHTML;
                setButtonLoading(btn, true);

                try {
                    const data = await apiRequest(btn.dataset.url, 'POST', {
                        id_turnamen: parseInt(btn.dataset.turnamen, 10),
                    });
                    showToast(data.message);
                    reloadPage();
                } catch (e) {
                    showToast(e.message, 'error');
                    setButtonLoading(btn, false, original);
                }
            });
        });

        const initFriendlyAssignModal = () => {
            if (!groupSwapContainer || groupSwapContainer.dataset.friendlyEdit !== '1') {
                return;
            }

            const modalEl = document.getElementById('friendlyAssignModal');
            const selectEl = document.getElementById('friendly-assign-peserta');
            const saveBtn = document.getElementById('btn-save-friendly-assign');
            const helpEl = document.getElementById('friendly-assign-help');
            const slotsHintEl = document.getElementById('friendly-assign-slots-hint');
            const titleEl = document.getElementById('friendlyAssignModalLabel');

            if (!modalEl || !selectEl || !saveBtn) {
                return;
            }

            if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
                return;
            }

            const $ = jQuery;

            if (modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }

            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            const $select = $(selectEl);
            let activeGrup = null;

            let unassignedOptions = [];
            try {
                unassignedOptions = JSON.parse(groupSwapContainer.dataset.unassigned || '[]');
            } catch (e) {
                unassignedOptions = [];
            }

            const destroySelect2 = () => {
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
            };

            const openAssignModal = (item) => {
                const slotsRemaining = parseInt(item.dataset.slotsRemaining || '0', 10);
                if (slotsRemaining <= 0) {
                    showAlert('Grup ini sudah penuh.', 'warning');
                    return;
                }

                if (!unassignedOptions.length) {
                    showAlert('Tidak ada pemain yang belum digrup.', 'info');
                    return;
                }

                activeGrup = {
                    id: parseInt(item.dataset.grupId, 10),
                    name: item.dataset.grupName || 'Grup',
                    slotsRemaining,
                    playersPerGroup: parseInt(item.dataset.playersPerGroup || '4', 10) || 4,
                };

                if (titleEl) {
                    titleEl.textContent = `Tambah Pemain — ${activeGrup.name}`;
                }
                if (helpEl) {
                    helpEl.textContent = `Pilih hingga ${slotsRemaining} pemain yang belum digrup untuk ${activeGrup.name}.`;
                }
                if (slotsHintEl) {
                    slotsHintEl.textContent = `Sisa slot: ${slotsRemaining} dari ${activeGrup.playersPerGroup}.`;
                }

                destroySelect2();
                selectEl.innerHTML = '';
                unassignedOptions.forEach((opt) => {
                    const option = document.createElement('option');
                    option.value = String(opt.id);
                    option.textContent = opt.text;
                    selectEl.appendChild(option);
                });

                $select.select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $(modalEl),
                    placeholder: 'Cari dan pilih pemain...',
                    allowClear: true,
                    width: '100%',
                    closeOnSelect: false,
                    maximumSelectionLength: slotsRemaining,
                });

                $select.val(null).trigger('change');
                modal.show();
            };

            groupSwapContainer.querySelectorAll('.accordion-item[data-friendly-grup="1"]').forEach((item) => {
                item.querySelectorAll('.friendly-grup-assign-open').forEach((btn) => {
                    btn.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        openAssignModal(item);
                    });
                });
            });

            modalEl.addEventListener('hidden.bs.modal', () => {
                destroySelect2();
                activeGrup = null;
            });

            saveBtn.addEventListener('click', async () => {
                if (!activeGrup) {
                    return;
                }

                const selected = ($select.val() || []).map((id) => parseInt(id, 10)).filter(Boolean);

                if (!selected.length) {
                    showAlert('Pilih minimal satu pemain.', 'warning');
                    return;
                }

                if (selected.length > activeGrup.slotsRemaining) {
                    showAlert(`Maksimal ${activeGrup.slotsRemaining} pemain untuk grup ini.`, 'warning');
                    return;
                }

                const original = saveBtn.innerHTML;
                setButtonLoading(saveBtn, true);

                try {
                    const data = await apiRequest(groupSwapContainer.dataset.assignUrl, 'POST', {
                        id_turnamen: parseInt(groupSwapContainer.dataset.turnamen, 10),
                        id_grup: activeGrup.id,
                        id_peserta: selected,
                    });
                    modal.hide();
                    showAlert(data.message || 'Pemain dimasukkan ke grup.', 'success');
                    reloadPage();
                } catch (e) {
                    showAlert(e.message, 'error');
                    setButtonLoading(saveBtn, false, original);
                }
            });
        };

        initFriendlyAssignModal();

        if (groupSwapContainer && groupSwapContainer.dataset.friendlyEdit === '1') {
            groupSwapContainer.querySelectorAll('.btn-rename-grup').forEach((btn) => {
                btn.addEventListener('click', async (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const currentName = btn.dataset.grupName || '';
                    let nextName = currentName;

                    if (window.Swal) {
                        const result = await window.Swal.fire({
                            title: 'Ubah nama grup',
                            input: 'text',
                            inputValue: currentName,
                            showCancelButton: true,
                            confirmButtonText: 'Simpan',
                            cancelButtonText: 'Batal',
                            inputValidator: (value) => {
                                if (!String(value || '').trim()) {
                                    return 'Nama grup wajib diisi';
                                }
                                return null;
                            },
                        });
                        if (!result.isConfirmed) return;
                        nextName = String(result.value || '').trim();
                    } else {
                        nextName = String(window.prompt('Nama grup baru', currentName) || '').trim();
                        if (!nextName) return;
                    }

                    const url = (groupSwapContainer.dataset.renameUrlTemplate || '')
                        .replace('__ID__', String(btn.dataset.grupId));

                    try {
                        await apiRequest(url, 'PATCH', {
                            id_turnamen: parseInt(groupSwapContainer.dataset.turnamen, 10),
                            nama: nextName,
                        });
                        showAlert('Nama grup diperbarui.', 'success');
                        reloadPage();
                    } catch (e) {
                        showAlert(e.message, 'error');
                    }
                });
            });

            groupSwapContainer.querySelectorAll('.btn-friendly-unassign').forEach((btn) => {
                btn.addEventListener('click', async (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const confirmed = await confirmAction({
                        title: 'Lepas pemain dari grup?',
                        confirmText: 'Ya, lepas',
                    });
                    if (!confirmed) return;

                    const url = (groupSwapContainer.dataset.unassignUrlTemplate || '')
                        .replace('__ID__', String(btn.dataset.memberId));

                    try {
                        await apiRequest(url, 'DELETE', {
                            id_turnamen: parseInt(groupSwapContainer.dataset.turnamen, 10),
                        });
                        showAlert('Pemain dilepas dari grup.', 'success');
                        reloadPage();
                    } catch (e) {
                        showAlert(e.message, 'error');
                    }
                });
            });
        }
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
        const passwordWrap = document.getElementById('score-password-wrap');
        const passwordInput = document.getElementById('score-confirm-password');
        const titleEl = document.getElementById('score-modal-title');
        const MIN_SETS = 1;
        const MAX_SETS = 7;
        let storeUrl = null;
        let isReadonly = false;
        let isEditMode = false;

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
            saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Simpan & Selesaikan';
            if (titleEl) {
                titleEl.innerHTML = '<i class="bi bi-trophy me-2"></i>Input Skor Pertandingan';
            }
            if (passwordWrap) {
                passwordWrap.classList.add('d-none');
            }
            if (passwordInput) {
                passwordInput.value = '';
            }
            isReadonly = false;
            isEditMode = false;
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
                } else if (readonly || (match.status === 'completed' && !match.editable)) {
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
                } else {
                    if (match.skor.length) {
                        renderSets(match.skor);
                    }

                    if (match.status === 'completed' && match.editable) {
                        isEditMode = true;
                        saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Perbarui Skor';
                        if (titleEl) {
                            titleEl.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Edit Skor Pertandingan';
                        }
                        if (passwordWrap) {
                            passwordWrap.classList.remove('d-none');
                        }
                    } else {
                        saveBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Simpan & Selesaikan';
                    }
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
                errorEl.textContent = 'Minimal 1 set harus diisi.';
                errorEl.classList.remove('d-none');
                return;
            }

            const payload = { sets };

            if (isEditMode) {
                const password = (passwordInput?.value || '').trim();
                if (!password) {
                    errorEl.textContent = 'Password wajib diisi untuk mengubah skor.';
                    errorEl.classList.remove('d-none');
                    passwordInput?.focus();
                    return;
                }
                payload.password = password;
            }

            errorEl.classList.add('d-none');
            const original = saveBtn.innerHTML;
            setButtonLoading(saveBtn, true);

            try {
                const data = await apiRequest(storeUrl, 'POST', payload);
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

    /**
     * Submit tournament filter without carrying an old id_kategori to a different turnamen.
     */
    const submitTurnamenFilter = (form) => {
        if (!form) {
            return;
        }

        form.querySelectorAll('input[name="id_kategori"]').forEach((el) => {
            el.disabled = true;
        });

        showPageLoader();
        form.submit();
    };

    const initTurnamenFilterSelect = () => {
        const select = document.getElementById('id_turnamen');

        if (!select || select.dataset.select2Turnamen !== '1') {
            return;
        }

        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
            return;
        }

        const $select = jQuery(select);
        const form = select.closest('form');
        const placeholder = select.dataset.placeholder || 'Pilih turnamen';
        const allowClear = select.dataset.allowClear === '1';
        const visibleMax = parseInt(select.dataset.turnamenVisibleMax || '5', 10);
        // ~2.5rem per option row (bootstrap-5 theme)
        const resultsMaxHeight = `${Math.max(1, visibleMax) * 2.5}rem`;

        $select.select2({
            theme: 'bootstrap-5',
            placeholder,
            allowClear,
            width: '100%',
            minimumResultsForSearch: 0,
            dropdownCssClass: 'turnamen-filter-select2-dropdown',
        });

        $select.on('select2:open', function () {
            const optionsEl = document.querySelector(
                '.turnamen-filter-select2-dropdown .select2-results__options'
            );

            if (optionsEl) {
                optionsEl.style.maxHeight = resultsMaxHeight;
            }
        });

        $select.on('change', function () {
            submitTurnamenFilter(form);
        });
    };

    return {
        initPemainActions,
        initAvailablePemainActions,
        initMatchmakingActions,
        initScoreModal,
        initPasswordModal,
        initTurnamenFilterSelect,
        submitTurnamenFilter,
        showToast,
        showAlert,
        apiRequest,
    };
})();
