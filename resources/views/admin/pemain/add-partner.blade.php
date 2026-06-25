@extends('layouts.admin')

@section('title', 'Tambah Pemain 2')
@section('page-title', 'Tambah Pemain 2')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen]) }}">Pemain</a></li>
    <li class="breadcrumb-item active">Tambah Pemain 2</li>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-5 mb-4 mb-lg-0">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Pasangan Turnamen</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small text-uppercase">Turnamen</div>
                    <strong>{{ $peserta->turnamen->nama }}</strong>
                </div>
                <div class="mb-3">
                    <div class="text-muted small text-uppercase">Pemain 1</div>
                    <strong>{{ $peserta->pemain1->nama }}</strong>
                    <div class="small text-muted">{{ $peserta->pemain1->no_hp }}</div>
                </div>
                <div>
                    <div class="text-muted small text-uppercase">Status</div>
                    <span class="badge status-badge-{{ $peserta->status }}">{{ ucfirst($peserta->status) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Data Pemain 2</h5>
            </div>
            <div class="card-body">
                @if (! $showForm)
                    <div class="alert alert-light border mb-4">
                        <i class="bi bi-search me-2"></i>
                        Cari nomor HP pemain 2 terlebih dahulu.
                    </div>
                    <form action="{{ route('admin.pemain.peserta.partner.lookup', $peserta) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label for="lookup_no_hp" class="form-label">Nomor HP / WhatsApp Pemain 2 <span class="text-danger">*</span></label>
                            <input type="tel"
                                   name="no_hp"
                                   id="lookup_no_hp"
                                   class="form-control @error('no_hp') is-invalid @enderror"
                                   value="{{ old('no_hp') }}"
                                   placeholder="08xxxxxxxxxx"
                                   required>
                            @error('no_hp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i> Cari & Lanjutkan
                            </button>
                            <a href="{{ route('admin.pemain.index', ['id_turnamen' => $peserta->id_turnamen]) }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                @else
                    <form action="{{ route('admin.pemain.peserta.partner.store', $peserta) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @include('admin.pemain.partials.player-fields', [
                            'prefix' => '',
                            'labelPrefix' => 'Pemain 2',
                            'existingPemain' => $existingPemain,
                            'previewSrc' => $existingPemain && $existingPemain->foto ? app(\App\Services\PemainPhotoService::class)->url($existingPemain->foto) : null,
                            'inputId' => 'partner-foto',
                            'previewId' => 'partner-foto-preview',
                            'phoneReadonly' => true,
                            'phoneValue' => $noHp,
                        ])
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Simpan Pemain 2
                            </button>
                            <a href="{{ route('admin.pemain.peserta.partner.create', ['peserta' => $peserta->id]) }}" class="btn btn-outline-secondary">Ganti Nomor HP</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
