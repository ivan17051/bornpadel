@php
    $kategoriList = $kategoriList ?? ($turnamen->relationLoaded('kategori')
        ? $turnamen->kategori->sortBy(fn ($k) => sprintf('%08d-%08d', $k->urutan, $k->id))->values()
        : $turnamen->kategori()->ordered()->get());
    $kategoriService = app(\App\Services\TurnamenKategoriService::class);
    $isFriendly = $turnamen->isFriendly();
    $openAddModal = session('open_kategori_modal') === true;
    $openEditId = session('open_kategori_edit_id') ? (int) session('open_kategori_edit_id') : null;
    $minFriendlyPpg = \App\Models\Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP;
    $defaultFriendlyPpg = \App\Models\Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP;
@endphp

<div class="card h-100" id="kategori-manager"
     data-is-friendly="{{ $isFriendly ? '1' : '0' }}"
     data-min-ppg="{{ $minFriendlyPpg }}"
     data-default-ppg="{{ $defaultFriendlyPpg }}">
    <div class="card-header d-flex align-items-center gap-2">
        <h5 class="card-title mb-0">Kategori</h5>
        <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
            @if ($kategoriList->count() > 1)
                <span class="badge text-bg-primary">{{ $kategoriList->count() }}</span>
            @endif
            <button type="button"
                    class="btn btn-sm btn-success"
                    data-bs-toggle="modal"
                    data-bs-target="#tambahKategoriModal"
                    title="Tambah kategori">
                <i class="bi bi-plus-lg me-1"></i>Tambah
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 2rem;">#</th>
                        <th>Nama</th>
                        <th class="text-end">Maks.</th>
                        <th>Status</th>
                        <th class="text-end pe-3" style="min-width: 6.5rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($kategoriList as $kat)
                        @php
                            $canDelete = $kategoriService->canDelete($kat);
                            $canPublish = $kategoriService->canPublish($kat);
                            $canUnpublish = $kategoriService->canUnpublish($kat);
                            $canEditPpg = $kategoriService->canEditPlayersPerGroup($kat);
                            if ($kat->status === 'open') {
                                $statusBadge = 'success';
                            } elseif ($kat->status === 'ongoing') {
                                $statusBadge = 'primary';
                            } elseif ($kat->status === 'draft') {
                                $statusBadge = 'warning text-dark';
                            } else {
                                $statusBadge = 'secondary';
                            }
                            $ppg = $kat->players_per_group ?? $defaultFriendlyPpg;
                        @endphp
                        <tr>
                            <td class="ps-3 text-muted">{{ $kat->urutan }}</td>
                            <td>
                                <div class="fw-semibold text-break">{{ $kat->nama }}</div>
                                @if ($kat->is_default)
                                    <span class="badge text-bg-secondary mt-1">Default</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap small">
                                {{ $kat->maks_peserta ? number_format($kat->maks_peserta) : '—' }}
                            </td>
                            <td>
                                <span class="badge bg-{{ $statusBadge }}">{{ ucfirst($kat->status) }}</span>
                            </td>
                            <td class="text-end text-nowrap pe-3">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary btn-edit-kategori"
                                        title="Edit kategori"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editKategoriModal"
                                        data-id="{{ $kat->id }}"
                                        data-action="{{ route('admin.turnamen.kategori.update', [$turnamen, $kat]) }}"
                                        data-nama="{{ $kat->nama }}"
                                        data-urutan="{{ $kat->urutan }}"
                                        data-harga="{{ $kat->harga }}"
                                        data-maks="{{ $kat->maks_peserta }}"
                                        data-ppg="{{ $ppg }}"
                                        data-can-edit-ppg="{{ $canEditPpg ? '1' : '0' }}"
                                        data-status="{{ $kat->status }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($canPublish)
                                    <form action="{{ route('admin.turnamen.kategori.status', [$turnamen, $kat]) }}"
                                          method="POST"
                                          class="d-inline">
                                        @csrf
                                        <input type="hidden" name="status" value="open">
                                        <button type="submit" class="btn btn-sm btn-success" title="Buka pendaftaran">
                                            <i class="bi bi-unlock"></i>
                                        </button>
                                    </form>
                                @elseif ($canUnpublish)
                                    <form action="{{ route('admin.turnamen.kategori.status', [$turnamen, $kat]) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Kembalikan kategori ke draft? Pendaftaran akan ditutup.');">
                                        @csrf
                                        <input type="hidden" name="status" value="draft">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" title="Kembalikan ke draft">
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    </form>
                                @endif
                                @if ($canDelete)
                                    <form action="{{ route('admin.turnamen.kategori.destroy', [$turnamen, $kat]) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Hapus kategori {{ addslashes($kat->nama) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 border-top">
            <div class="form-text mb-0">
                Baru = <strong>draft</strong>. Klik <strong><i class="bi bi-unlock"></i></strong> untuk buka pendaftaran.
                Edit data lewat tombol pensil.
            </div>
        </div>
    </div>
</div>

{{-- Modal: tambah kategori --}}
<div class="modal fade" id="tambahKategoriModal" tabindex="-1" aria-labelledby="tambahKategoriModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.turnamen.kategori.store', $turnamen) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahKategoriModalLabel">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Kategori baru berstatus draft. Jenis mengikuti turnamen ({{ $turnamen->jenis_label }}).
                    </p>
                    <div class="mb-3">
                        <label for="modal-add-kategori-nama" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text"
                               name="nama"
                               id="modal-add-kategori-nama"
                               class="form-control @error('nama') is-invalid @enderror"
                               value="{{ $openAddModal ? old('nama') : '' }}"
                               required
                               maxlength="255"
                               placeholder="mis. Open Beginner">
                        @error('nama')
                            @if ($openAddModal)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @endif
                        @enderror
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modal-add-kategori-harga" class="form-label">Biaya (Rp)</label>
                            <input type="number"
                                   name="harga"
                                   id="modal-add-kategori-harga"
                                   class="form-control"
                                   value="{{ $openAddModal ? old('harga') : '' }}"
                                   min="0"
                                   step="1000"
                                   placeholder="Salin dari default">
                        </div>
                        <div class="col-md-6">
                            <label for="modal-add-kategori-maks" class="form-label">Maks. peserta</label>
                            <input type="number"
                                   name="maks_peserta"
                                   id="modal-add-kategori-maks"
                                   class="form-control"
                                   value="{{ $openAddModal ? old('maks_peserta') : '' }}"
                                   min="1"
                                   placeholder="Opsional">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-lg me-1"></i> Tambah
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: edit kategori --}}
<div class="modal fade" id="editKategoriModal" tabindex="-1" aria-labelledby="editKategoriModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="edit-kategori-form" action="#">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editKategoriModalLabel">Edit Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal-edit-kategori-nama" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text"
                               name="nama"
                               id="modal-edit-kategori-nama"
                               class="form-control @error('nama') is-invalid @enderror"
                               value="{{ $openEditId ? old('nama') : '' }}"
                               required
                               maxlength="255">
                        @error('nama')
                            @if ($openEditId)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @endif
                        @enderror
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <label for="modal-edit-kategori-urutan" class="form-label">Urutan</label>
                            <input type="number"
                                   name="urutan"
                                   id="modal-edit-kategori-urutan"
                                   class="form-control"
                                   value="{{ $openEditId ? old('urutan') : '' }}"
                                   min="1">
                        </div>
                        <div class="col-8">
                            <label for="modal-edit-kategori-harga" class="form-label">Biaya (Rp) <span class="text-danger">*</span></label>
                            <input type="number"
                                   name="harga"
                                   id="modal-edit-kategori-harga"
                                   class="form-control @error('harga') is-invalid @enderror"
                                   value="{{ $openEditId ? old('harga') : '' }}"
                                   min="0"
                                   step="1000"
                                   required>
                            @error('harga')
                                @if ($openEditId)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @endif
                            @enderror
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modal-edit-kategori-maks" class="form-label">Maks. peserta</label>
                            <input type="number"
                                   name="maks_peserta"
                                   id="modal-edit-kategori-maks"
                                   class="form-control"
                                   value="{{ $openEditId ? old('maks_peserta') : '' }}"
                                   min="1"
                                   placeholder="Tanpa batas">
                        </div>
                        <div class="col-md-6 {{ $isFriendly ? '' : 'd-none' }}" id="modal-edit-ppg-wrap">
                            <label for="modal-edit-kategori-ppg" class="form-label">Pemain / grup</label>
                            <input type="number"
                                   name="players_per_group"
                                   id="modal-edit-kategori-ppg"
                                   class="form-control"
                                   value="{{ $openEditId ? old('players_per_group') : '' }}"
                                   min="{{ $minFriendlyPpg }}">
                        </div>
                    </div>
                    <div class="form-text mt-3" id="modal-edit-kategori-status-hint"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var manager = document.getElementById('kategori-manager');
    var editModalEl = document.getElementById('editKategoriModal');
    var editForm = document.getElementById('edit-kategori-form');
    if (!manager || !editModalEl || !editForm) {
        return;
    }

    var namaInput = document.getElementById('modal-edit-kategori-nama');
    var urutanInput = document.getElementById('modal-edit-kategori-urutan');
    var hargaInput = document.getElementById('modal-edit-kategori-harga');
    var maksInput = document.getElementById('modal-edit-kategori-maks');
    var ppgInput = document.getElementById('modal-edit-kategori-ppg');
    var ppgWrap = document.getElementById('modal-edit-ppg-wrap');
    var statusHint = document.getElementById('modal-edit-kategori-status-hint');
    var titleEl = document.getElementById('editKategoriModalLabel');

    function fillEditFromButton(btn) {
        editForm.action = btn.dataset.action || '#';
        namaInput.value = btn.dataset.nama || '';
        urutanInput.value = btn.dataset.urutan || '1';
        hargaInput.value = btn.dataset.harga || '0';
        maksInput.value = btn.dataset.maks || '';
        if (ppgInput) {
            ppgInput.value = btn.dataset.ppg || manager.dataset.defaultPpg || '4';
            var canEditPpg = btn.dataset.canEditPpg === '1';
            ppgInput.readOnly = !canEditPpg;
            if (ppgWrap) {
                ppgWrap.classList.toggle('d-none', manager.dataset.isFriendly !== '1');
            }
        }
        if (titleEl) {
            titleEl.textContent = 'Edit Kategori: ' + (btn.dataset.nama || '');
        }
        if (statusHint) {
            var st = btn.dataset.status || '';
            statusHint.textContent = st ? ('Status saat ini: ' + st + '. Buka/tutup pendaftaran lewat tombol gembok di tabel.') : '';
        }
    }

    document.querySelectorAll('.btn-edit-kategori').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillEditFromButton(btn);
        });
    });

    @if ($openAddModal)
    var addModalEl = document.getElementById('tambahKategoriModal');
    if (addModalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(addModalEl).show();
    }
    @endif

    @if ($openEditId)
    (function () {
        var btn = document.querySelector('.btn-edit-kategori[data-id="{{ $openEditId }}"]');
        if (!btn || typeof bootstrap === 'undefined') {
            return;
        }
        fillEditFromButton(btn);
        // Prefer old input after validation error
        @if (old('nama') !== null)
        namaInput.value = @json(old('nama'));
        @endif
        @if (old('urutan') !== null)
        urutanInput.value = @json(old('urutan'));
        @endif
        @if (old('harga') !== null)
        hargaInput.value = @json(old('harga'));
        @endif
        @if (old('maks_peserta') !== null)
        maksInput.value = @json(old('maks_peserta'));
        @endif
        @if (old('players_per_group') !== null && $isFriendly)
        if (ppgInput) {
            ppgInput.value = @json(old('players_per_group'));
        }
        @endif
        bootstrap.Modal.getOrCreateInstance(editModalEl).show();
    })();
    @endif
});
</script>
@endpush
