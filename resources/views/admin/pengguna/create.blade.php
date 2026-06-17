@extends('layouts.admin')

@section('title', 'Tambah Pengguna')
@section('page-title', 'Tambah Pengguna')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pengguna.index') }}">Pengguna</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Form Pengguna Baru</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.pengguna.store') }}" method="POST">
                    @csrf
                    @include('admin.pengguna._form')
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan
                        </button>
                        <a href="{{ route('admin.pengguna.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
