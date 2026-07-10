<div class="modal fade" id="setPartnerModal" tabindex="-1" aria-labelledby="setPartnerModalLabel" aria-hidden="true"
     data-set-partner-url="{{ route('admin.peserta.partner.set', ['peserta' => '__PESERTA_ID__']) }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="setPartnerForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="setPartnerModalLabel">
                        <i class="bi bi-people me-2"></i> <span id="set-partner-modal-title">Set Partner</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Atur pasangan untuk <strong id="set-partner-pemain-name"></strong>.
                    </p>

                    <label for="partner_peserta_id" class="form-label">Pilih peserta terdaftar</label>
                    <select class="form-select" id="partner_peserta_id" name="partner_peserta_id">
                        <option value=""></option>
                        @foreach ($soloPesertaOptions as $option)
                            <option value="{{ $option->id }}">
                                {{ $option->pemain1->nama ?? 'Pemain' }}
                                @if ($option->pemain1 && $option->pemain1->no_hp)
                                    — {{ $option->pemain1->no_hp }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Hanya menampilkan peserta terdaftar yang belum memiliki pasangan.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btn-save-partner">
                        <i class="bi bi-check-lg me-1"></i> Simpan Pasangan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" crossorigin="anonymous">
<style>
    #setPartnerModal .select2-container {
        width: 100% !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" crossorigin="anonymous"></script>
@endpush
