@extends('layouts.admin')

@section('title', 'Tambah Pemain')
@section('page-title', 'Tambah Pemain')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pemain.index', request()->only('id_turnamen')) }}">Pemain</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
@php
    $photoService = app(\App\Services\PemainPhotoService::class);
    $placeholderAvatar = $photoService->placeholderUrl();
@endphp

<div class="row justify-content-center">
    @if ($showForm)
        <div class="col-lg-5 mb-4 mb-lg-0">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Pendaftaran Turnamen</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <div class="text-muted small text-uppercase">Turnamen</div>
                        <strong>{{ $selectedTurnamen->nama }}</strong>
                        <span class="badge text-bg-light text-dark border ms-1">{{ $selectedTurnamen->jenis_label }}</span>
                    </div>
                    <div class="mb-2">
                        <div class="text-muted small text-uppercase">No. HP Pemain 1</div>
                        <strong>{{ $noHp }}</strong>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase">Status Pendaftaran</div>
                        <strong>{{ ucfirst(old('status', request('status', 'approved'))) }}</strong>
                    </div>
                    <hr>
                    <a href="{{ route('admin.pemain.create', request()->only('id_turnamen')) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Ganti Nomor HP
                    </a>
                </div>
            </div>
        </div>
    @endif

    <div class="col-lg-{{ $showForm ? '7' : '6' }}">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-3">
                @if ($showForm)
                    <img src="{{ $placeholderAvatar }}"
                         id="admin-foto-preview"
                         alt="Preview foto"
                         width="56"
                         height="56"
                         data-fallback="{{ $placeholderAvatar }}"
                         onerror="if (this.dataset.fallback) { this.onerror = null; this.src = this.dataset.fallback; }"
                         class="pemain-avatar rounded-circle object-fit-cover bg-light"
                         style="width: 56px; height: 56px;">
                @endif
                <h5 class="card-title mb-0">{{ $showForm ? 'Data Peserta' : 'Tambah Pemain' }}</h5>
            </div>
            <div class="card-body">
                @if ($showForm)
                    <form action="{{ route('admin.pemain.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @include('admin.pemain._form', [
                            'turnamenList' => $turnamenList,
                            'selectedTurnamen' => $selectedTurnamen,
                            'showForm' => true,
                            'noHp' => $noHp,
                            'existingPemain' => $existingPemain,
                            'existingPartner' => $existingPartner,
                        ])
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Simpan
                            </button>
                            <a href="{{ route('admin.pemain.index', ['id_turnamen' => $selectedTurnamen->id]) }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                @else
                    @include('admin.pemain._form', [
                        'turnamenList' => $turnamenList,
                        'showForm' => false,
                    ])
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .pemain-avatar { object-fit: cover; display: block; }
</style>
@endpush
