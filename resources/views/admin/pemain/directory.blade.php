@extends('layouts.admin')

@section('title', 'Database Pemain')
@section('page-title', 'Database Pemain')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pemain.index') }}">Pemain</a></li>
    <li class="breadcrumb-item active">Database</li>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Total Pemain</div>
                <div class="h3 mb-0">{{ number_format($totalPemain) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Belum Terdaftar Turnamen</div>
                <div class="h3 mb-0 text-warning">{{ number_format($unregisteredCount) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-center">
                <a href="{{ route('admin.pemain.index') }}" class="btn btn-outline-primary btn-sm mb-2">
                    <i class="bi bi-funnel me-1"></i> Manajemen per Turnamen
                </a>
                <a href="{{ route('admin.pemain.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Pemain
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.pemain.directory') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Cari</label>
                <input type="text" name="search" class="form-control" placeholder="Nama atau no. HP..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Gender</label>
                <select name="gender" class="form-select">
                    <option value="">Semua</option>
                    <option value="male" {{ request('gender') === 'male' ? 'selected' : '' }}>Laki-laki</option>
                    <option value="female" {{ request('gender') === 'female' ? 'selected' : '' }}>Perempuan</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Pendaftaran Turnamen</label>
                <select name="registration" class="form-select">
                    <option value="">Semua</option>
                    <option value="registered" {{ request('registration') === 'registered' ? 'selected' : '' }}>Sudah pernah terdaftar</option>
                    <option value="none" {{ request('registration') === 'none' ? 'selected' : '' }}>Belum terdaftar turnamen</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a href="{{ route('admin.pemain.directory') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Semua Pemain (Master Data)</h5>
        <span class="badge text-bg-secondary">{{ $pemain->total() }} hasil</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 3rem;"></th>
                        <th>#</th>
                        <th>Nama</th>
                        <th class="d-none d-lg-table-cell">No. HP</th>
                        <th class="d-none d-xl-table-cell">Usia</th>
                        <th class="d-none d-lg-table-cell">Gender</th>
                        <th class="d-none d-md-table-cell">Rating</th>
                        <th class="d-none d-lg-table-cell">Total Poin</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pemain as $item)
                        @php
                            $phoneService = app(\App\Services\PhoneNumberService::class);
                            $parsedPhone = $phoneService->parse($item->no_hp);
                        @endphp
                        <tr>
                            <td>
                                <x-pemain-avatar :pemain="$item" :size="40" />
                            </td>
                            <td>{{ $pemain->firstItem() + $loop->index }}</td>
                            <td>
                                <x-pemain-link :pemain="$item" class="text-decoration-none text-dark fw-semibold" />
                                <div class="small text-muted d-lg-none">{{ $parsedPhone['country_code'] }} {{ $parsedPhone['local_number'] }}</div>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                {{ $parsedPhone['country_code'] }} {{ $parsedPhone['local_number'] }}
                            </td>
                            <td class="d-none d-xl-table-cell">{{ $item->usia ?? '—' }}</td>
                            <td class="d-none d-lg-table-cell">
                                {{ $item->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}
                            </td>
                            <td class="d-none d-md-table-cell">{{ number_format($item->rating, 1) }}</td>
                            <td class="d-none d-lg-table-cell">
                                <span class="badge text-bg-primary">{{ number_format($item->total_poin ?? 0) }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('guest.pemain.show', $item) }}"
                                   class="btn btn-sm btn-outline-secondary"
                                   title="Profil publik">
                                    <i class="bi bi-person-badge"></i>
                                </a>
                                <a href="{{ route('admin.pemain.edit', $item) }}"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Belum ada data pemain.</td>
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
