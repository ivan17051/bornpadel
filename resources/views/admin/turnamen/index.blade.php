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
                        <th class="d-none d-md-table-cell">Biaya</th>
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
                                    Rp {{ number_format($item->harga, 0, ',', '.') }}
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                Rp {{ number_format($item->harga, 0, ',', '.') }}
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
                                <a href="{{ route('admin.turnamen.edit', $item) }}"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.turnamen.destroy', $item) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Hapus turnamen {{ $item->nama }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
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
@endsection
