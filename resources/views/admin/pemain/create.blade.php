@extends('layouts.admin')

@section('title', 'Tambah Pemain')
@section('page-title', 'Tambah Pemain')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pemain.index', request()->only('id_turnamen')) }}">Pemain</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
@php
    $isDoubleForm = $showForm && $selectedTurnamen && $selectedTurnamen->isDouble();
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
                    @if ($isDoubleForm)
                        <div class="mb-2">
                            <div class="text-muted small text-uppercase">No. HP Pemain 2</div>
                            <strong>{{ $partnerNoHp }}</strong>
                        </div>
                    @endif
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
            <div class="card-body">
                @if ($showForm)
                    <form action="{{ route('admin.pemain.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @include('admin.pemain._form', [
                            'turnamenList' => $turnamenList,
                            'selectedTurnamen' => $selectedTurnamen,
                            'showForm' => true,
                            'noHp' => $noHp,
                            'partnerNoHp' => $partnerNoHp,
                            'existingPemain' => $existingPemain,
                            'existingPartner' => $existingPartner,
                            'isPartnerExisting' => $isPartnerExisting ?? false,
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
