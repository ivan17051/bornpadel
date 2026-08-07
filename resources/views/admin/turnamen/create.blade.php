@extends('layouts.admin')

@section('title', 'Tambah Turnamen')
@section('page-title', 'Tambah Turnamen')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.turnamen.index') }}">Turnamen</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Form Turnamen Baru</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.turnamen.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @include('admin.turnamen._form')
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan
                        </button>
                        <a href="{{ route('admin.turnamen.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <h5 class="card-title mb-0">Kategori</h5>
                <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                    <button type="button" class="btn btn-sm btn-success" disabled title="Simpan turnamen terlebih dahulu">
                        <i class="bi bi-plus-lg me-1"></i>Tambah
                    </button>
                </div>
            </div>
            <div class="card-body text-center text-muted py-5">
                <i class="bi bi-tags display-6 d-block mb-3 opacity-50"></i>
                <p class="mb-2 fw-semibold text-body">Belum tersedia</p>
                <p class="small mb-0">
                    Simpan turnamen dulu. Kategori default <strong>Umum</strong> dibuat otomatis,
                    lalu Anda dapat menambah kategori dari tombol <i class="bi bi-plus-lg"></i>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('public/js/pemain-photo-preview.js') }}"></script>
@endpush
