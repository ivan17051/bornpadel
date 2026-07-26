<div class="modal fade" id="tambahPemainModal" tabindex="-1" aria-labelledby="tambahPemainModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <form id="tambahPemainForm" class="modal-content" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title" id="tambahPemainModalLabel">
                    <i class="bi bi-person-plus me-2"></i> Tambah Pemain Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Buat profil baru dan daftarkan ke
                    <strong>{{ $turnamen->nama }}</strong>.
                    Jika nomor HP sudah ada, gunakan tabel di halaman ini.
                </p>

                <input type="hidden" name="id_turnamen" value="{{ $turnamen->id }}">

                <div class="mb-3">
                    <label for="modal_status" class="form-label">Status Pendaftaran <span class="text-danger">*</span></label>
                    <select name="status" id="modal_status" class="form-select" required>
                        @foreach (['approved' => 'Approved', 'pending' => 'Pending', 'unpaid' => 'Unpaid', 'paid' => 'Paid'] as $value => $label)
                            <option value="{{ $value }}" {{ $value === 'approved' ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <x-pemain-photo-input
                    input-id="modal-foto"
                    preview-id="modal-foto-preview"
                    input-name="foto"
                    label="Foto Pemain" />

                <x-phone-input
                    name="no_hp"
                    id="modal_no_hp"
                    label="Nomor HP / WhatsApp" />

                <div class="mb-3">
                    <label for="modal_nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" id="modal_nama" class="form-control" required maxlength="255">
                </div>

                <div class="mb-3">
                    <label for="modal_tgl_lahir" class="form-label">Tanggal Lahir <span class="text-muted fw-normal">(opsional)</span></label>
                    <input type="date"
                           name="tgl_lahir"
                           id="modal_tgl_lahir"
                           class="form-control"
                           max="{{ date('Y-m-d', strtotime('-1 day')) }}">
                </div>

                <div class="mb-3">
                    <label for="modal_gender" class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                    <select name="gender" id="modal_gender" class="form-select" required>
                        <option value="" disabled selected>Pilih jenis kelamin</option>
                        <option value="male">Laki-laki</option>
                        <option value="female">Perempuan</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="modal_rating" class="form-label">Rating</label>
                    <input type="number"
                           name="rating"
                           id="modal_rating"
                           class="form-control"
                           min="0"
                           max="10"
                           step="0.1">
                    <div class="form-text">Skala 0–10. Kosongkan jika belum ada rating.</div>
                </div>

                <div class="mb-0">
                    <label for="modal_bukti_bayar" class="form-label">Bukti Pembayaran</label>
                    <input type="file"
                           name="bukti_bayar"
                           id="modal_bukti_bayar"
                           class="form-control"
                           accept="image/jpeg,image/png,image/webp,application/pdf">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary" id="btn-save-new-pemain">
                    <i class="bi bi-check-lg me-1"></i> Simpan & Daftarkan
                </button>
            </div>
        </form>
    </div>
</div>

@once
@push('styles')
<style>
    #tambahPemainModal .modal-dialog {
        max-height: calc(100dvh - 2rem);
        margin: 1rem auto;
    }

    #tambahPemainModal .modal-content {
        max-height: calc(100dvh - 2rem);
    }

    #tambahPemainModal .modal-body {
        overscroll-behavior: contain;
    }
</style>
@endpush
@endonce
