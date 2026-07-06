@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
@if ($assignedTurnamen ?? null)
    <div class="alert alert-light border mb-4 py-3">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar-event text-primary"></i>
            <div>
                <div class="small text-muted mb-0">Turnamen Anda</div>
                <strong>{{ $assignedTurnamen->nama }}</strong>
                <span class="badge bg-secondary ms-1">{{ ucfirst($assignedTurnamen->status) }}</span>
            </div>
        </div>
    </div>
@endif

<div class="row g-3 mb-4">
    @if ($isAdmin)
        <div class="col-sm-6 col-xl-3">
            <div class="small-box text-bg-primary">
                <div class="inner">
                    <h3>{{ $globalStats['total_turnamen'] }}</h3>
                    <p>Total Turnamen</p>
                </div>
                <i class="small-box-icon bi bi-calendar-event"></i>
                <a href="{{ route('admin.turnamen.index') }}" class="small-box-footer link-light">
                    Kelola turnamen <i class="bi bi-arrow-right-circle"></i>
                </a>
            </div>
        </div>
    @endif
    <div class="col-sm-6 col-xl-3">
        <div class="small-box text-bg-info">
            <div class="inner">
                <h3>{{ $globalStats['total_pemain_directory'] }}</h3>
                <p>Semua Pemain</p>
            </div>
            <i class="small-box-icon bi bi-database"></i>
            <a href="{{ route('admin.pemain.directory') }}" class="small-box-footer link-light">
                Direktori pemain <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3>{{ $registrationStats['needs_review'] }}</h3>
                <p>Perlu Verifikasi</p>
            </div>
            <i class="small-box-icon bi bi-hourglass-split"></i>
            <a href="{{ route('admin.pemain.index', array_filter(['status' => 'paid', 'id_turnamen' => optional($assignedTurnamen)->id])) }}" class="small-box-footer link-dark">
                Review <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3>{{ $registrationStats['total'] }}</h3>
                <p>Total Pendaftaran</p>
            </div>
            <i class="small-box-icon bi bi-people"></i>
            <a href="{{ route('admin.pemain.index', array_filter(['id_turnamen' => optional($assignedTurnamen)->id])) }}" class="small-box-footer link-light">
                Pemain terdaftar <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>
</div>

@if ($isAdmin && $recentTurnamen->isNotEmpty())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center row">
            <div class="col-md-6">
                <h5 class="card-title mb-0">
                    <i class="bi bi-calendar-event me-2 text-primary"></i>Semua Turnamen
                </h5>
            </div>
            <div class="col-md-6 text-end">
                <a href="{{ route('admin.turnamen.index') }}" class="btn btn-sm btn-outline-primary">
                    Kelola turnamen
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Turnamen</th>
                            <th class="d-none d-md-table-cell">Tanggal</th>
                            <th class="d-none d-lg-table-cell">Jenis</th>
                            <th>Status</th>
                            <th class="text-center">Peserta</th>
                            <th class="text-center d-none d-sm-table-cell">Approved</th>
                            <th class="text-center d-none d-lg-table-cell">Grup</th>
                            <th class="text-center d-none d-xl-table-cell">Pertandingan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentTurnamen as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->nama }}</strong>
                                    <div class="small text-muted d-md-none">
                                        {{ optional($item->tanggal)->format('d M Y') ?? '—' }}
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">{{ optional($item->tanggal)->format('d M Y') ?? '—' }}</td>
                                <td class="d-none d-lg-table-cell">
                                    <span class="badge text-bg-light text-dark border">{{ $item->jenis_label }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $item->status === 'open' ? 'success' : ($item->status === 'ongoing' ? 'primary' : ($item->status === 'completed' ? 'secondary' : 'dark')) }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-center">{{ $item->turnamen_peserta_count }}</td>
                                <td class="text-center d-none d-sm-table-cell">{{ $item->approved_count }}</td>
                                <td class="text-center d-none d-lg-table-cell">{{ $item->grup_count }}</td>
                                <td class="text-center d-none d-xl-table-cell">{{ $item->isMahjong() ? '—' : $item->pertandingan_count }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.turnamen-operasi.index', ['id_turnamen' => $item->id]) }}"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Kelola turnamen">
                                        <i class="bi bi-gear-wide-connected me-1"></i> Kelola
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

@if ($recentRegistrations->isNotEmpty() || $isAdmin)
    <div class="row g-3 mb-4">
        @if ($recentRegistrations->isNotEmpty())
            <div class="col-lg-{{ $isAdmin ? '8' : '12' }}">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center row">
                        <div class="col-md-6">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2 text-primary"></i>Pendaftaran Perlu Tindakan
                            </h5>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="{{ route('admin.pemain.index', array_filter(['status' => 'paid', 'id_turnamen' => optional($assignedTurnamen)->id])) }}" class="btn btn-sm btn-outline-primary">
                                Lihat semua
                            </a>
                        </div>
                        <!-- <div class="col-md-6 text-end">
                            <a href="{{ route('admin.pemain.index', array_filter(['status' => 'paid', 'id_turnamen' => optional($assignedTurnamen)->id])) }}" class="btn btn-sm btn-outline-primary">
                                Lihat semua
                            </a>
                        </div> -->
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        @if ($isAdmin)
                                            <th>Turnamen</th>
                                        @endif
                                        <th>Pemain</th>
                                        <th>Status</th>
                                        <th class="d-none d-lg-table-cell">Diperbarui</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentRegistrations as $peserta)
                                        <tr>
                                            @if ($isAdmin)
                                                <td>
                                                    <span class="small">{{ optional($peserta->turnamen)->nama ?? '—' }}</span>
                                                </td>
                                            @endif
                                            <td class="fw-semibold">{{ $peserta->display_name }}</td>
                                            <td>
                                                <span class="badge status-badge-{{ $peserta->status }}">{{ ucfirst($peserta->status) }}</span>
                                            </td>
                                            <td class="d-none d-lg-table-cell text-muted small">
                                                {{ optional($peserta->updated_at)->diffForHumans() }}
                                            </td>
                                            <td class="text-end">
                                                @if ($peserta->pemain1)
                                                    <a href="{{ route('admin.pemain.edit', ['pemain' => $peserta->pemain1, 'from' => 'index', 'id_turnamen' => $peserta->id_turnamen]) }}"
                                                       class="btn btn-sm btn-outline-secondary">
                                                        Review
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($isAdmin)
            <div class="col-lg-{{ $recentRegistrations->isNotEmpty() ? '4' : '12' }}">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-2">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-bar-chart me-1 text-primary"></i> Status Turnamen
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-center" style="width:4rem">Jumlah</th>
                                        <th class="text-end" style="width:4rem"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <span class="badge bg-success me-1">&nbsp;</span>
                                            Pendaftaran Dibuka
                                        </td>
                                        <td class="text-center fw-semibold">{{ $globalStats['turnamen_open'] }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.turnamen.index', ['status' => 'open']) }}" class="small text-decoration-none">
                                                Lihat <i class="bi bi-arrow-right-circle"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary me-1">&nbsp;</span>
                                            Sedang Berlangsung
                                        </td>
                                        <td class="text-center fw-semibold">{{ $globalStats['turnamen_ongoing'] }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.turnamen.index', ['status' => 'ongoing']) }}" class="small text-decoration-none">
                                                Lihat <i class="bi bi-arrow-right-circle"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary me-1">&nbsp;</span>
                                            Turnamen Selesai
                                        </td>
                                        <td class="text-center fw-semibold">{{ $globalStats['turnamen_completed'] }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('admin.turnamen.index', ['status' => 'completed']) }}" class="small text-decoration-none">
                                                Lihat <i class="bi bi-arrow-right-circle"></i>
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif
@endsection
