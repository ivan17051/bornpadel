@extends('layouts.admin')

@section('title', 'Edit Pemain')
@section('page-title', 'Edit Pemain')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('admin.pemain.index', request()->only('id_turnamen')) }}">Pemain</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
@php
    $photoService = app(\App\Services\PemainPhotoService::class);
    $fotoUrl = $pemain->foto_url;
@endphp

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ $fotoUrl }}"
                         id="edit-foto-preview"
                         alt="{{ $pemain->nama }}"
                         width="70"
                         height="70"
                         data-fallback="{{ $photoService->placeholderUrl() }}"
                         onerror="if (this.dataset.fallback) { this.onerror = null; this.src = this.dataset.fallback; }"
                         class="pemain-avatar rounded-circle object-fit-cover bg-light flex-shrink-0"
                         style="width: 70px; height: 70px; min-width: 70px; min-height: 70px;">
                    <h5 class="card-title mb-0">{{ $pemain->nama }}</h5>
                </div>
                @if ($pemain->usia)
                    <span class="badge text-bg-secondary">{{ $pemain->usia }} tahun</span>
                @endif
            </div>
            <div class="card-body">
                @if ($turnamenPesertaEntries->isNotEmpty())
                    <div class="mb-4">
                        <h6 class="text-muted text-uppercase small mb-2">Riwayat Turnamen</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Turnamen</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($turnamenPesertaEntries as $peserta)
                                        <tr>
                                            <td>
                                                {{ optional($peserta->turnamen)->nama ?? '—' }}
                                                @if ($peserta->id_pemain2 && $peserta->pemain2)
                                                    <div class="small text-muted">Partner: {{ $peserta->pemain2->nama }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge status-badge-{{ $peserta->status }}">
                                                    {{ ucfirst($peserta->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="form-text mb-0">Ubah status pendaftaran dari halaman daftar pemain dengan filter turnamen.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.pemain.update', $pemain) }}"
                      method="POST"
                      enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    @if (request('id_turnamen'))
                        <input type="hidden" name="id_turnamen" value="{{ request('id_turnamen') }}">
                    @endif
                    <x-pemain-photo-input
                        input-id="edit-foto"
                        preview-id="edit-foto-preview"
                        label="Foto Pemain"
                        :preview-src="$fotoUrl"
                        :show-preview="false" />
                    <p class="form-text">Format: JPG, PNG, atau WebP. Maks. 5 MB. Kosongkan jika tidak ingin mengubah foto.</p>
                    @include('admin.pemain._form', ['pemain' => $pemain])
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> Simpan Perubahan
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
