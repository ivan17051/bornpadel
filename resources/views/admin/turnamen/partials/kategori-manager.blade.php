@php
    $kategoriList = $kategoriList ?? ($turnamen->relationLoaded('kategori')
        ? $turnamen->kategori->sortBy(fn ($k) => sprintf('%08d-%08d', $k->urutan, $k->id))->values()
        : $turnamen->kategori()->ordered()->get());
    $kategoriService = app(\App\Services\TurnamenKategoriService::class);
    $isFriendly = $turnamen->isFriendly();
@endphp

<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="card-title mb-0">Kategori Kompetisi</h5>
            <div class="small text-muted">
                Setiap kategori punya kapasitas, biaya, dan matchmaking sendiri.
                Jenis turnamen ({{ $turnamen->jenis_label }}) tetap sama untuk semua kategori.
            </div>
        </div>
        @if ($kategoriList->count() > 1)
            <span class="badge text-bg-primary">{{ $kategoriList->count() }} kategori</span>
        @endif
    </div>
    <div class="card-body">
        @foreach ($kategoriList as $kat)
            <form action="{{ route('admin.turnamen.kategori.update', [$turnamen, $kat]) }}"
                  method="POST"
                  id="kategori-form-{{ $kat->id }}"
                  class="d-none">
                @csrf
                @method('PUT')
            </form>
        @endforeach

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-4">
                <thead>
                    <tr>
                        <th style="width: 3rem;">#</th>
                        <th>Nama</th>
                        <th style="width: 9rem;">Biaya (Rp)</th>
                        <th style="width: 8rem;">Maks. peserta</th>
                        @if ($isFriendly)
                            <th style="width: 7rem;">/ grup</th>
                        @endif
                        <th style="width: 7rem;">Status</th>
                        <th style="width: 14rem;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($kategoriList as $kat)
                        @php
                            $canDelete = $kategoriService->canDelete($kat);
                            $canPublish = $kategoriService->canPublish($kat);
                            $canUnpublish = $kategoriService->canUnpublish($kat);
                            if ($kat->status === 'open') {
                                $statusBadge = 'success';
                            } elseif ($kat->status === 'ongoing') {
                                $statusBadge = 'primary';
                            } elseif ($kat->status === 'draft') {
                                $statusBadge = 'warning text-dark';
                            } else {
                                $statusBadge = 'secondary';
                            }
                        @endphp
                        <tr>
                            <td>
                                <input form="kategori-form-{{ $kat->id }}"
                                       type="number"
                                       name="urutan"
                                       class="form-control form-control-sm"
                                       value="{{ old('urutan', $kat->urutan) }}"
                                       min="1">
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <input form="kategori-form-{{ $kat->id }}"
                                           type="text"
                                           name="nama"
                                           class="form-control form-control-sm"
                                           value="{{ old('nama', $kat->nama) }}"
                                           required
                                           maxlength="255">
                                    @if ($kat->is_default)
                                        <span class="badge text-bg-secondary flex-shrink-0">Default</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <input form="kategori-form-{{ $kat->id }}"
                                       type="number"
                                       name="harga"
                                       class="form-control form-control-sm"
                                       value="{{ old('harga', $kat->harga) }}"
                                       min="0"
                                       step="1000"
                                       required>
                            </td>
                            <td>
                                <input form="kategori-form-{{ $kat->id }}"
                                       type="number"
                                       name="maks_peserta"
                                       class="form-control form-control-sm"
                                       value="{{ old('maks_peserta', $kat->maks_peserta) }}"
                                       min="1"
                                       placeholder="—">
                            </td>
                            @if ($isFriendly)
                                <td>
                                    <input form="kategori-form-{{ $kat->id }}"
                                           type="number"
                                           name="players_per_group"
                                           class="form-control form-control-sm"
                                           value="{{ old('players_per_group', $kat->players_per_group ?? \App\Models\Turnamen::DEFAULT_FRIENDLY_PLAYERS_PER_GROUP) }}"
                                           min="{{ \App\Models\Turnamen::MIN_FRIENDLY_PLAYERS_PER_GROUP }}"
                                           @if (! $kategoriService->canEditPlayersPerGroup($kat)) readonly @endif>
                                </td>
                            @endif
                            <td>
                                <span class="badge bg-{{ $statusBadge }}">
                                    {{ ucfirst($kat->status) }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($canPublish)
                                    <form action="{{ route('admin.turnamen.kategori.status', [$turnamen, $kat]) }}"
                                          method="POST"
                                          class="d-inline">
                                        @csrf
                                        <input type="hidden" name="status" value="open">
                                        <button type="submit"
                                                class="btn btn-sm btn-success"
                                                title="Buka pendaftaran kategori ini">
                                            <i class="bi bi-unlock me-1"></i>Buka
                                        </button>
                                    </form>
                                @elseif ($canUnpublish)
                                    <form action="{{ route('admin.turnamen.kategori.status', [$turnamen, $kat]) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('Kembalikan kategori ke draft? Pendaftaran akan ditutup.');">
                                        @csrf
                                        <input type="hidden" name="status" value="draft">
                                        <button type="submit"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Kembalikan ke draft (tutup pendaftaran)">
                                            <i class="bi bi-lock me-1"></i>Draft
                                        </button>
                                    </form>
                                @endif
                                <button form="kategori-form-{{ $kat->id }}" type="submit" class="btn btn-sm btn-outline-primary" title="Simpan">
                                    <i class="bi bi-check-lg"></i>
                                </button>
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
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Tidak dapat dihapus (default atau masih ada data)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h6 class="text-muted text-uppercase small mb-2">Tambah Kategori</h6>
        <form action="{{ route('admin.turnamen.kategori.store', $turnamen) }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label small mb-1">Nama <span class="text-danger">*</span></label>
                <input type="text" name="nama" class="form-control" value="{{ old('nama') }}" required maxlength="255" placeholder="mis. Open Beginner">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Biaya (Rp)</label>
                <input type="number" name="harga" class="form-control" value="{{ old('harga') }}" min="0" step="1000" placeholder="Salin dari default">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Maks. peserta</label>
                <input type="number" name="maks_peserta" class="form-control" value="{{ old('maks_peserta') }}" min="1" placeholder="Opsional">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-plus-lg me-1"></i> Tambah
                </button>
            </div>
        </form>
        <div class="form-text mt-2">
            Kategori baru berstatus <strong>draft</strong> (belum bisa daftar). Klik <strong>Buka</strong> untuk membuka pendaftaran.
            Status <strong>ongoing</strong> / <strong>completed</strong> diubah lewat matchmaking (Tutup Pendaftaran / Selesaikan).
            Kategori dengan peserta/grup/pertandingan tidak dapat dihapus.
        </div>
    </div>
</div>
