@extends('layouts.admin')

@section('title', 'Manajemen Pengguna')
@section('page-title', 'Manajemen Pengguna')

@section('breadcrumb')
    <li class="breadcrumb-item active">Pengguna</li>
@endsection

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.pengguna.index') }}" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted">Cari</label>
                <input type="text" name="search" class="form-control" placeholder="Nama, username, atau email..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Role</label>
                <select name="role" class="form-select">
                    <option value="">Semua Role</option>
                    @foreach (['admin' => 'Admin', 'panitia' => 'Panitia'] as $value => $label)
                        <option value="{{ $value }}" {{ request('role') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a href="{{ route('admin.pengguna.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center row">
        <div class="col-md-6">
            <h5 class="card-title mb-0">Daftar Pengguna</h5>
        </div>
        <div class="col-md-6 text-end">
            <a href="{{ route('admin.pengguna.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Tambah Pengguna
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
                        <th class="d-none d-md-table-cell">Username</th>
                        <th class="d-none d-lg-table-cell">Email</th>
                        <th>Role</th>
                        <th class="d-none d-md-table-cell">Turnamen</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $item)
                        <tr>
                            <td>{{ $users->firstItem() + $loop->index }}</td>
                            <td>
                                <strong>{{ $item->name }}</strong>
                                <div class="small text-muted d-md-none">{{ $item->username }}</div>
                            </td>
                            <td class="d-none d-md-table-cell">{{ $item->username }}</td>
                            <td class="d-none d-lg-table-cell">{{ $item->email }}</td>
                            <td>
                                @if ($item->role === 'admin')
                                    <span class="badge bg-danger">Admin</span>
                                @else
                                    <span class="badge bg-info text-dark">Panitia</span>
                                @endif
                            </td>
                            <td class="d-none d-md-table-cell">
                                @if ($item->role === 'admin')
                                    <span class="text-muted">Semua turnamen</span>
                                @elseif ($item->turnamen)
                                    {{ $item->turnamen->nama }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.pengguna.edit', $item) }}"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('admin.pengguna.destroy', $item) }}"
                                      method="POST"
                                      class="d-inline"
                                      onsubmit="return confirm('Hapus pengguna {{ $item->name }}?')">
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
                            <td colspan="7" class="text-center text-muted py-4">
                                Belum ada pengguna. <a href="{{ route('admin.pengguna.create') }}">Tambah pengguna pertama</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($users->hasPages())
        <div class="card-footer">
            {{ $users->links() }}
        </div>
    @endif
</div>
@endsection
