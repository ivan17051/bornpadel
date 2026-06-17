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
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ $placeholderAvatar }}"
                         id="admin-foto-preview"
                         alt="Preview foto"
                         width="70"
                         height="70"
                         data-fallback="{{ $placeholderAvatar }}"
                         onerror="if (this.dataset.fallback) { this.onerror = null; this.src = this.dataset.fallback; }"
                         class="pemain-avatar rounded-circle object-fit-cover bg-light flex-shrink-0"
                         style="width: 70px; height: 70px; min-width: 70px; min-height: 70px;">
                    <h5 class="card-title mb-0">Pemain Baru</h5>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-0">
                    <h6 class="text-muted text-uppercase small mb-2">Pendaftaran Turnamen</h6>
                    <div class="mb-3">
                        <label for="id_turnamen" class="form-label">Turnamen <span class="text-danger">*</span></label>
                        <select name="id_turnamen" id="id_turnamen" form="pemain-create-form"
                                class="form-select @error('id_turnamen') is-invalid @enderror" required>
                            <option value="" disabled {{ old('id_turnamen', request('id_turnamen')) ? '' : 'selected' }}>Pilih turnamen</option>
                            @foreach ($turnamenList as $item)
                                <option value="{{ $item->id }}"
                                    {{ (string) old('id_turnamen', request('id_turnamen')) === (string) $item->id ? 'selected' : '' }}>
                                    {{ $item->nama }} — {{ ucfirst($item->status) }}
                                </option>
                            @endforeach
                        </select>
                        @error('id_turnamen')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status Pendaftaran <span class="text-danger">*</span></label>
                        <select name="status" id="status" form="pemain-create-form"
                                class="form-select @error('status') is-invalid @enderror" required>
                            @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                                <option value="{{ $value }}" {{ old('status', 'approved') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <p class="form-text mb-0">Ubah status pendaftaran kapan saja dari halaman daftar pemain dengan filter turnamen.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <form id="pemain-create-form"
                      action="{{ route('admin.pemain.store') }}"
                      method="POST"
                      enctype="multipart/form-data">
                    @csrf
                    <x-pemain-photo-input
                        input-id="admin-foto"
                        preview-id="admin-foto-preview"
                        label="Foto Pemain"
                        :show-preview="false" />
                    <p class="form-text">Format: JPG, PNG, atau WebP. Maks. 5 MB.</p>
                    @include('admin.pemain._form', [
                        'turnamenList' => $turnamenList,
                    ])
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan
                        </button>
                        <a href="{{ route('admin.pemain.index', request()->only('id_turnamen')) }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
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
