@extends('layouts.admin')

@section('title', 'Manajemen Pemain')
@section('page-title', 'Manajemen Pemain')

@section('breadcrumb')
    <li class="breadcrumb-item active">Pemain</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.pemain.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted">Cari</label>
                <input type="text" name="search" class="form-control" placeholder="Nama atau no. HP..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a href="{{ route('admin.pemain.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Daftar Pemain Terdaftar</h5>
        <span class="badge text-bg-secondary">{{ $pemain->total() }} pemain</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle" id="pemain-table">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th class="d-none d-md-table-cell">No. HP</th>
                        <th class="d-none d-lg-table-cell">Gender</th>
                        <th class="d-none d-lg-table-cell">Rating</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pemain as $item)
                        <tr data-pemain-id="{{ $item->id }}">
                            <td>{{ $pemain->firstItem() + $loop->index }}</td>
                            <td>
                                <strong>{{ $item->nama }}</strong>
                                <div class="small text-muted d-md-none">{{ $item->no_hp }}</div>
                            </td>
                            <td class="d-none d-md-table-cell">{{ $item->no_hp }}</td>
                            <td class="d-none d-lg-table-cell">{{ $item->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}</td>
                            <td class="d-none d-lg-table-cell">{{ number_format($item->rating, 1) }}</td>
                            <td>
                                <span class="badge status-badge-{{ $item->status }}" data-status-cell>
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($item->status === 'pending')
                                    <button type="button" class="btn btn-sm btn-success btn-approve"
                                            data-url="{{ route('admin.pemain.status', $item) }}"
                                            title="Setujui">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-warning btn-reject"
                                            data-url="{{ route('admin.pemain.status', $item) }}"
                                            title="Tolak">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                @elseif ($item->status === 'rejected')
                                    <button type="button" class="btn btn-sm btn-outline-success btn-approve"
                                            data-url="{{ route('admin.pemain.status', $item) }}"
                                            title="Setujui">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                @elseif ($item->status === 'approved')
                                    <button type="button" class="btn btn-sm btn-outline-warning btn-reject"
                                            data-url="{{ route('admin.pemain.status', $item) }}"
                                            title="Tolak">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-pemain"
                                        data-url="{{ route('admin.pemain.destroy', $item) }}"
                                        data-name="{{ $item->nama }}"
                                        title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Belum ada pemain terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($pemain->hasPages())
        <div class="card-footer">
            {{ $pemain->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initPemainActions();
});
</script>
@endpush
