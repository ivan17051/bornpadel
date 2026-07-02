@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active">Dashboard</li>
@endsection

@section('content')
@php
    $entryLabel = ($turnamen && $turnamen->isDouble()) ? 'Pasangan' : 'Pemain';
    $entryLabelPlural = ($turnamen && $turnamen->isDouble()) ? 'Pasangan' : 'Pemain';
@endphp
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="small-box text-bg-primary">
            <div class="inner">
                <h3>{{ $stats['total_pemain'] }}</h3>
                <p>Total {{ $entryLabelPlural }}</p>
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
            <a href="{{ route('admin.pemain.index', array_filter(['status' => 'paid', 'id_turnamen' => optional($turnamen)->id])) }}" class="small-box-footer link-dark">
                Review <i class="bi bi-arrow-right-circle"></i>
            </a>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3>{{ $stats['approved_pemain'] }}</h3>
                <p>{{ $entryLabel }} Disetujui</p>
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
@endsection
