@extends('layouts.admin')

@section('title', 'Manajemen Turnamen')
@section('page-title', 'Manajemen Turnamen')

@section('breadcrumb')
    <li class="breadcrumb-item active">Turnamen</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.turnamen.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted">Cari</label>
                <input type="text" name="search" class="form-control" placeholder="Nama turnamen..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach (['draft', 'open', 'ongoing', 'completed'] as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a href="{{ route('admin.turnamen.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center row">
        <div class="col-md-6">
            <h5 class="card-title mb-0">Daftar Turnamen</h5>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('admin.turnamen.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Tambah Turnamen
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th class="d-none d-md-table-cell">Tanggal</th>
                        <th class="d-none d-md-table-cell">Biaya</th>
                        <th class="d-none d-lg-table-cell">Jenis</th>
                        <th>Status</th>
                        <th class="d-none d-lg-table-cell">Dibuat</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($turnamen as $item)
                        <tr>
                            <td>{{ $turnamen->firstItem() + $loop->index }}</td>
                            <td>
                                <strong>{{ $item->nama }}</strong>
                                <div class="small text-muted d-md-none">
                                    {{ optional($item->tanggal)->format('d M Y') ?? '—' }}
                                    · Rp {{ number_format($item->harga, 0, ',', '.') }}
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                {{ optional($item->tanggal)->format('d M Y') ?? '—' }}
                            </td>
                            <td class="d-none d-md-table-cell">
                                Rp {{ number_format($item->harga, 0, ',', '.') }}
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <span class="badge text-bg-light text-dark border">{{ $item->jenis_label }}</span>
                            </td>
                            <td>
                                @php
                                    $statusClass = 'bg-warning text-dark';
                                    if ($item->status === 'open') {
                                        $statusClass = 'bg-success';
                                    } elseif ($item->status === 'ongoing') {
                                        $statusClass = 'bg-primary';
                                    } elseif ($item->status === 'completed') {
                                        $statusClass = 'bg-secondary';
                                    }
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ ucfirst($item->status) }}</span>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                {{ optional($item->doc)->format('d M Y') ?? '—' }}
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.turnamen-operasi.index', ['id_turnamen' => $item->id]) }}"
                                   class="btn btn-sm btn-outline-success"
                                   title="Kelola turnamen">
                                    <i class="bi bi-gear-wide-connected"></i>
                                </a>
                                <a href="{{ route('admin.turnamen.edit', $item) }}"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        title="Hapus"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteTurnamenModal"
                                        data-turnamen-id="{{ $item->id }}"
                                        data-turnamen-name="{{ $item->nama }}"
                                        data-delete-url="{{ route('admin.turnamen.destroy', $item) }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Belum ada turnamen. <a href="{{ route('admin.turnamen.create') }}">Buat turnamen pertama</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($turnamen->hasPages())
        <div class="card-footer">
            {{ $turnamen->links() }}
        </div>
    @endif
</div>

<div class="modal fade" id="deleteTurnamenModal" tabindex="-1" aria-labelledby="deleteTurnamenModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-danger">
            <form method="POST" id="deleteTurnamenForm">
                @csrf
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteTurnamenModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i> Hapus Turnamen
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger mb-3">
                        <strong>Peringatan:</strong> Menghapus turnamen <strong id="deleteTurnamenName"></strong> akan
                        <strong>menghapus permanen</strong> seluruh data terkait, termasuk:
                        <ul class="mb-0 mt-2">
                            <li>Pendaftaran peserta & pasangan</li>
                            <li>Grup dan matchmaking</li>
                            <li>Semua pertandingan & skor</li>
                            <li>Data klasemen dan bracket</li>
                        </ul>
                    </div>
                    <p class="text-muted small mb-3">Tindakan ini tidak dapat dibatalkan.</p>
                    <div class="mb-0">
                        <label for="deleteTurnamenPassword" class="form-label">Password Admin <span class="text-danger">*</span></label>
                        <input type="password"
                               name="password"
                               id="deleteTurnamenPassword"
                               class="form-control"
                               autocomplete="current-password"
                               placeholder="Masukkan password admin">
                        <div class="form-text">Konfirmasi identitas admin untuk melanjutkan penghapusan.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" id="deleteTurnamenSubmit" disabled>
                        <i class="bi bi-trash me-1"></i> Hapus Turnamen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('deleteTurnamenModal');
    if (!modal) return;

    const form = document.getElementById('deleteTurnamenForm');
    const nameEl = document.getElementById('deleteTurnamenName');
    const passwordEl = document.getElementById('deleteTurnamenPassword');
    const submitBtn = document.getElementById('deleteTurnamenSubmit');

    const toggleSubmit = () => {
        if (submitBtn) {
            submitBtn.disabled = !passwordEl.value.trim();
        }
    };

    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;

        form.action = button.getAttribute('data-delete-url');
        nameEl.textContent = button.getAttribute('data-turnamen-name');
        passwordEl.value = '';
        toggleSubmit();
    });

    passwordEl.addEventListener('input', toggleSubmit);

    form.addEventListener('submit', function (event) {
        if (!passwordEl.value.trim()) {
            event.preventDefault();
            submitBtn.disabled = true;
        }
    });
});
</script>
@endpush
