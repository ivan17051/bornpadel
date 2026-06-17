@extends('layouts.admin')

@section('title', 'Edit Pengguna')
@section('page-title', 'Edit Pengguna')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pengguna.index') }}">Pengguna</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ $user->name }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.pengguna.update', $user) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('admin.pengguna._form', ['user' => $user])
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                        </button>
                        <a href="{{ route('admin.pengguna.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
