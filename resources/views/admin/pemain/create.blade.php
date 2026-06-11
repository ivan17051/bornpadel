@extends('layouts.admin')

@section('title', 'Tambah Pemain')
@section('page-title', 'Tambah Pemain')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pemain.index') }}">Pemain</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Form Pemain Baru</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.pemain.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @include('admin.pemain._form', [
                        'showRegistrationFields' => true,
                        'showPhotoField' => true,
                        'turnamenList' => $turnamenList,
                    ])
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan
                        </button>
                        <a href="{{ route('admin.pemain.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
