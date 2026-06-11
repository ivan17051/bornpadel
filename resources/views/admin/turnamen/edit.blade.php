@extends('layouts.admin')

@section('title', 'Edit Turnamen')
@section('page-title', 'Edit Turnamen')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.turnamen.index') }}">Turnamen</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ $turnamen->nama }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.turnamen.update', $turnamen) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('admin.turnamen._form', ['turnamen' => $turnamen])
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
                        </button>
                        <a href="{{ route('admin.turnamen.index') }}" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
