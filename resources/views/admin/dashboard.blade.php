@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3>{{ $stats['total_pemain'] }}</h3>
                <p>Total Pemain</p>
            </div>
            <i class="small-box-icon bi bi-people"></i>
            <a href="{{ route('admin.pemain.index') }}" class="small-box-footer link-light">
                Lihat detail <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3>{{ $stats['pending_pemain'] }}</h3>
                <p>Menunggu Verifikasi</p>
            </div>
            <i class="small-box-icon bi bi-hourglass-split"></i>
            <a href="{{ route('admin.pemain.index', array_filter(['status' => 'pending', 'id_turnamen' => optional($turnamen)->id])) }}" class="small-box-footer link-dark">
                Review <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3>{{ $stats['approved_pemain'] }}</h3>
                <p>Pemain Disetujui</p>
            </div>
            <i class="small-box-icon bi bi-check-circle"></i>
            <a href="{{ route('admin.pemain.index', array_filter(['status' => 'approved', 'id_turnamen' => optional($turnamen)->id])) }}" class="small-box-footer link-light">
                Lihat <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="small-box text-bg-info">
            <div class="inner">
                <h3>{{ $stats['total_pertandingan'] }}</h3>
                <p>Total Pertandingan</p>
            </div>
            <i class="small-box-icon bi bi-trophy"></i>
            <a href="{{ route('admin.matchmaking.index') }}" class="small-box-footer link-light">
                Matchmaking <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>
</div>

@if ($turnamen)
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Turnamen Aktif</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h4 class="mb-1">{{ $turnamen->nama }}</h4>
                    <p class="text-muted mb-2">Biaya: Rp {{ number_format($turnamen->harga, 0, ',', '.') }}</p>
                    <span class="badge bg-{{ $turnamen->status === 'open' ? 'success' : ($turnamen->status === 'ongoing' ? 'primary' : 'secondary') }}">
                        {{ strtoupper($turnamen->status) }}
                    </span>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="{{ route('admin.matchmaking.index') }}" class="btn btn-outline-primary">
                        <i class="bi bi-shuffle me-1"></i> Kelola Matchmaking
                    </a>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="alert alert-info mb-0">
        <i class="bi bi-info-circle me-2"></i> Tidak ada turnamen dengan status open atau ongoing.
    </div>
@endif
@endsection
