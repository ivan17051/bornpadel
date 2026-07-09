<div class="modal fade" id="groupStageHistoryModal"
     tabindex="-1"
     aria-labelledby="groupStageHistoryModalLabel"
     aria-hidden="true"
     data-history-url="{{ $historyUrl ?? route('api.guest.standings.group-history') }}">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="groupStageHistoryModalLabel">
                    <i class="bi bi-clock-history me-2"></i>Riwayat Fase Grup
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="group-stage-history-meta" class="mb-3"></div>
                <div id="group-stage-history-loading" class="text-center py-4 text-muted d-none">
                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                    Memuat riwayat…
                </div>
                <div id="group-stage-history-error" class="alert alert-danger d-none mb-0"></div>
                <div id="group-stage-history-empty" class="alert alert-light border text-center d-none mb-0">
                    Belum ada pertandingan fase grup.
                </div>
                <div id="group-stage-history-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Lawan</th>
                                    <th class="text-center">Skor</th>
                                    <th class="text-center">Hasil</th>
                                </tr>
                            </thead>
                            <tbody id="group-stage-history-rows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
