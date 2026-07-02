@extends('layouts.admin')

@section('title', 'Manajemen Pemain')
@section('page-title', 'Manajemen Pemain')

@section('breadcrumb')
    <li class="breadcrumb-item active">Pemain</li>
@endsection

@section('sweetalert-flash', true)

@section('content')
@include('admin.partials.turnamen-filter', [
    'filterRoute' => route('admin.pemain.index'),
    'sweetAlert' => true,
    'requireTurnamenSelection' => true,
])

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.pemain.index') }}" class="row g-2 align-items-end">
            @if (request('id_turnamen'))
                <input type="hidden" name="id_turnamen" value="{{ request('id_turnamen') }}">
            @endif
            <div class="col-md-5">
                <label class="form-label small text-muted">Cari</label>
                <input type="text" name="search" class="form-control" placeholder="Nama atau no. HP..."
                       value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Status Pendaftaran</label>
                <select name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="unpaid" {{ request('status') === 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                    <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a href="{{ route('admin.pemain.index', request()->only('id_turnamen')) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card pemain-table-card">
    <div class="card-header d-flex justify-content-between align-items-center row">
        <div class="col-md-6">
            <h5 class="card-title mb-0">
                Daftar Pemain
                @if ($turnamen)
                    <small class="text-muted fw-normal">— {{ $turnamen->nama }}</small>
                @endif
            </h5>
        </div>
        <div class="col-md-6 text-end">
            <div class="align-items-center gap-2">
                <span class="badge text-bg-secondary">
                    @if (! empty($isDoubleView))
                        {{ $peserta->total() }} pasangan
                    @else
                        {{ $pemain->total() }} pemain
                    @endif
                </span>
                <a href="{{ route('admin.pemain.create', request()->only('id_turnamen')) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Pemain
                </a>
                
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive" id="pemain-table-wrapper">
            <table class="table table-hover table-striped mb-0 align-middle" id="pemain-table">
                <thead class="table-light">
                    <tr>
                        @if (! empty($isDoubleView))
                            <th>#</th>
                            <th>Pemain 1</th>
                            <th class="d-none d-lg-table-cell">Gender</th>
                            <th class="d-none d-lg-table-cell">Rating</th>
                            <th>Pemain 2</th>
                            <th class="d-none d-lg-table-cell">Gender</th>
                            <th class="d-none d-lg-table-cell">Rating</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        @else
                            <th style="width: 3.5rem;"></th>
                            <th>#</th>
                            <th>Nama</th>
                            <th class="d-none d-md-table-cell">No. HP</th>
                            <th class="d-none d-lg-table-cell">Gender</th>
                            <th class="d-none d-lg-table-cell">Rating</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php
                        $turnamenOngoing = $turnamen && $turnamen->status === 'ongoing';
                    @endphp
                    @if (! empty($isDoubleView))
                        @forelse ($peserta as $entry)
                            @php
                                $pemain1 = $entry->pemain1;
                                $pemain2 = $entry->pemain2;
                            @endphp
                            <tr data-peserta-id="{{ $entry->id }}">
                                <td>{{ $peserta->firstItem() + $loop->index }}</td>
                                <td>
                                    @include('admin.pemain.partials.pemain-cell', ['pemain' => $pemain1])
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    {{ $pemain1 && $pemain1->gender === 'male' ? 'Laki-laki' : ($pemain1 ? 'Perempuan' : '—') }}
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    {{ $pemain1 ? number_format($pemain1->rating, 1) : '—' }}
                                </td>
                                <td>
                                    @include('admin.pemain.partials.pemain-cell', ['pemain' => $pemain2])
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    {{ $pemain2 && $pemain2->gender === 'male' ? 'Laki-laki' : ($pemain2 ? 'Perempuan' : '—') }}
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    {{ $pemain2 ? number_format($pemain2->rating, 1) : '—' }}
                                </td>
                                <td>
                                    <span class="badge status-badge-{{ $entry->status }}" data-status-cell>
                                        {{ ucfirst($entry->status) }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @include('admin.pemain.partials.pemain-pair-row-actions', [
                                        'peserta' => $entry,
                                        'pemain1' => $pemain1,
                                        'pemain2' => $pemain2,
                                        'turnamen' => $turnamen,
                                        'registrationStatus' => $entry->status,
                                        'turnamenOngoing' => $turnamenOngoing,
                                    ])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    @if ($turnamen)
                                        Belum ada pasangan terdaftar pada turnamen ini.
                                    @else
                                        Pilih turnamen untuk melihat daftar pemain.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    @else
                    @forelse ($pemain as $item)
                        @php
                            $peserta = $item->pesertaForTurnamen($turnamen);
                            $registrationStatus = optional($peserta)->status;
                        @endphp
                        <tr data-pemain-id="{{ $item->id }}">
                            <td>
                                <x-pemain-avatar :pemain="$item" :size="40" />
                            </td>
                            <td>{{ $pemain->firstItem() + $loop->index }}</td>
                            <td>
                                <strong>
                                    <x-pemain-link :pemain="$item" class="text-decoration-none text-dark" />
                                </strong>
                                <div class="small text-muted d-md-none">{{ $item->no_hp }}</div>
                            </td>
                            <td class="d-none d-md-table-cell">{{ $item->no_hp }}</td>
                            <td class="d-none d-lg-table-cell">{{ $item->gender === 'male' ? 'Laki-laki' : 'Perempuan' }}</td>
                            <td class="d-none d-lg-table-cell">{{ number_format($item->rating, 1) }}</td>
                            <td>
                                @if ($registrationStatus)
                                    <span class="badge status-badge-{{ $registrationStatus }}" data-status-cell>
                                        {{ ucfirst($registrationStatus) }}
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @include('admin.pemain.partials.pemain-row-actions', [
                                    'pemain' => $item,
                                    'peserta' => $peserta,
                                    'turnamen' => $turnamen,
                                    'registrationStatus' => $registrationStatus,
                                    'turnamenOngoing' => $turnamenOngoing,
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                @if ($turnamen)
                                    Belum ada pemain terdaftar pada turnamen ini.
                                @else
                                    Pilih turnamen untuk melihat daftar pemain.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @if ((! empty($isDoubleView) && $peserta->hasPages()) || (empty($isDoubleView) && $pemain->hasPages()))
        <div class="card-footer">
            {{ ! empty($isDoubleView) ? $peserta->links() : $pemain->links() }}
        </div>
    @endif
</div>

<div class="modal fade" id="buktiBayarModal" tabindex="-1" aria-labelledby="buktiBayarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="buktiBayarModalLabel">
                    <i class="bi bi-receipt me-2"></i>Bukti Pembayaran
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body text-center">
                <p id="bukti-bayar-modal-label" class="small text-muted mb-3"></p>
                <img id="bukti-bayar-image" src="" alt="Bukti pembayaran" class="img-fluid rounded border d-none">
                <iframe id="bukti-bayar-pdf" title="Bukti pembayaran PDF" class="d-none w-100 rounded border" style="height: 70vh;"></iframe>
                <div id="bukti-bayar-empty" class="text-muted py-4 d-none">
                    <i class="bi bi-file-earmark-x display-6 d-block mb-2"></i>
                    Belum ada bukti bayar yang diunggah.
                </div>
            </div>
            <div class="modal-footer">
                <a id="bukti-bayar-open-tab" href="#" target="_blank" rel="noopener" class="btn btn-outline-primary d-none">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Buka di Tab Baru
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .pemain-table-card,
    .pemain-table-card > .card-body {
        overflow: visible;
    }

    #pemain-table-wrapper .dropdown-menu {
        z-index: 1080;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    BornPadelAdmin.initPemainActions();

    @if (session('success'))
        BornPadelAdmin.showAlert(@json(session('success')), 'success');
    @endif

    @if (session('error'))
        BornPadelAdmin.showAlert(@json(session('error')), 'error');
    @endif

    @if (request('id_turnamen') && ! $turnamen)
        BornPadelAdmin.showAlert('Turnamen tidak ditemukan.', 'error');
    @endif

    @if (auth()->user()->isPanitia() && $turnamenList->isEmpty())
        BornPadelAdmin.showAlert('Akun panitia belum ditugaskan ke turnamen.', 'warning');
    @endif

});
</script>
@endpush
