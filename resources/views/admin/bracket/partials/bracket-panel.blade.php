<x-tournament-bracket :bracket="$bracket" :turnamen="$turnamen" :refreshable="true" :editable="true" />

@once
<div class="modal fade" id="bracketSwapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-arrow-left-right me-2"></i>Tukar Peserta Bracket
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Tukar posisi <strong id="bracket-swap-source" class="text-dark">peserta</strong>
                    dengan peserta lain di babak pertama. Klik salah satu nama untuk menukar.
                </p>
                <div class="list-group" id="bracket-swap-list"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('public/js/bracket-swap.js') }}"></script>
@endpush
@endonce
