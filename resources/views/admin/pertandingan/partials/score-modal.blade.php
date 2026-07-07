<div class="modal fade" id="scoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trophy me-2"></i>Input Skor Pertandingan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="score-modal-meta" class="mb-3 small text-muted"></div>
                <div id="score-modal-readonly" class="d-none"></div>
                <form id="score-form">
                    <div class="row fw-semibold text-center mb-2">
                        <div class="col-4">Set</div>
                        <div class="col-4" id="score-p1-name">Pemain 1</div>
                        <div class="col-4" id="score-p2-name">Pemain 2</div>
                    </div>
                    <div id="score-sets-container"></div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-set">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Set
                        </button>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Pemenang ditentukan dari jumlah set yang dimenangkan. Tambah atau hapus set sesuai kebutuhan.
                    </div>
                    <div id="score-form-error" class="alert alert-danger small mt-2 d-none"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" id="btn-save-score">
                    <i class="bi bi-check-lg me-1"></i> Simpan & Selesaikan
                </button>
            </div>
        </div>
    </div>
</div>
